import { and, asc, desc, eq, gt, gte, inArray, isNotNull, like, sql } from "drizzle-orm";
import { db } from "@/db";
import { botMessages, channelDaily, ideas, searchTerms, videoMetrics, videos } from "@/db/schema";
import { dayKey, daysUntil, monthKey, shiftMonth } from "@/lib/format";

const DAY = 86_400_000;

/* ---------------- Programados e capas ---------------- */

export async function getScheduledVideos() {
  return db
    .select({
      id: videos.id,
      title: videos.title,
      publishAt: videos.publishAt,
      isShort: videos.isShort,
      durationSec: videos.durationSec,
      thumbnailStatus: videos.thumbnailStatus,
      thumbnailSource: videos.thumbnailSource,
      thumbnailUpdatedAt: videos.thumbnailUpdatedAt,
    })
    .from(videos)
    .where(and(eq(videos.status, "agendado"), gt(videos.publishAt, new Date())))
    .orderBy(asc(videos.publishAt));
}

export type ScheduledVideo = Awaited<ReturnType<typeof getScheduledVideos>>[number];

export function summarizeStock(list: ScheduledVideo[], now = new Date()) {
  const lastDate = list.at(-1)?.publishAt ?? null;
  const pending = list.filter((v) => v.thumbnailStatus !== "ok");
  return {
    total: list.length,
    shorts: list.filter((v) => v.isShort).length,
    lastDate,
    daysCovered: lastDate ? Math.max(0, daysUntil(lastDate, now)) : 0,
    missingThumbs: pending.length,
    nextMissingThumb: pending[0] ?? null,
    next: list[0] ?? null,
  };
}

export type StockSummary = ReturnType<typeof summarizeStock>;

/* ---------------- Desempenho ---------------- */

// O MySQL devolve SUM/COUNT como texto ou decimal: converte para número.
const sumInt = (column: Parameters<typeof sql>[1]) => sql<number>`coalesce(sum(${column}), 0)`.mapWith(Number);
const countAll = () => sql<number>`count(*)`.mapWith(Number);

export async function getMonthTotals(month: string, untilDay?: number) {
  const conditions = [like(channelDaily.day, `${month}-%`)];
  if (untilDay) conditions.push(sql`${channelDaily.day} <= ${`${month}-${String(untilDay).padStart(2, "0")}`}`);
  const [row] = await db
    .select({
      views: sumInt(channelDaily.views),
      watchMinutes: sumInt(channelDaily.watchMinutes),
      subscribers: sumInt(channelDaily.subscribersGained),
      days: countAll(),
    })
    .from(channelDaily)
    .where(and(...conditions));
  return row;
}

/** Mês atual até hoje comparado com o mesmo número de dias do mês anterior. */
export async function getMonthToDate(now = new Date()) {
  const current = monthKey(now);
  const today = Number(dayKey(now).slice(8, 10));
  const [thisMonth, lastMonth] = await Promise.all([
    getMonthTotals(current, today),
    getMonthTotals(shiftMonth(current, -1), today),
  ]);
  const change = lastMonth.views > 0 ? (thisMonth.views - lastMonth.views) / lastMonth.views : null;
  return { month: current, ...thisMonth, previousViews: lastMonth.views, change };
}

export async function getDailySeries(options: { days?: number; month?: string } = {}) {
  const where = options.month
    ? like(channelDaily.day, `${options.month}-%`)
    : gte(channelDaily.day, dayKey(new Date(Date.now() - (options.days ?? 30) * DAY)));
  return db.select().from(channelDaily).where(where).orderBy(asc(channelDaily.day));
}

export async function getTopVideos(month: string, limit = 8) {
  return db
    .select({
      id: videos.id,
      title: videos.title,
      isShort: videos.isShort,
      views: videoMetrics.views,
      watchMinutes: videoMetrics.watchMinutes,
      avgViewPct: videoMetrics.avgViewPct,
      subscribersGained: videoMetrics.subscribersGained,
    })
    .from(videoMetrics)
    .innerJoin(videos, eq(videos.id, videoMetrics.videoId))
    .where(eq(videoMetrics.month, month))
    .orderBy(desc(videoMetrics.views))
    .limit(limit);
}

export async function getMonthRetention(month: string) {
  const [row] = await db
    .select({
      weighted: sql<number>`coalesce(sum(${videoMetrics.avgViewPct} * ${videoMetrics.views}) / nullif(sum(${videoMetrics.views}), 0), 0)`.mapWith(Number),
    })
    .from(videoMetrics)
    .where(eq(videoMetrics.month, month));
  return row.weighted;
}

const median = (values: number[]) => {
  if (!values.length) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
};

/**
 * Viral = views nos 7 primeiros dias acima de N vezes a mediana do canal
 * (Shorts e vídeos longos comparados separadamente).
 */
export async function getViralVideos(multiplier: number, days = 45) {
  const rows = await db
    .select({
      id: videos.id,
      title: videos.title,
      isShort: videos.isShort,
      publishedAt: videos.publishedAt,
      views: videos.views,
      views7d: videos.views7d,
      likes: videos.likes,
      comments: videos.comments,
    })
    .from(videos)
    .where(and(eq(videos.status, "publicado"), isNotNull(videos.views7d), gte(videos.publishedAt, new Date(Date.now() - 120 * DAY))))
    .orderBy(desc(videos.publishedAt));

  const medians = {
    longos: median(rows.filter((r) => !r.isShort).map((r) => r.views7d ?? 0)),
    shorts: median(rows.filter((r) => r.isShort).map((r) => r.views7d ?? 0)),
  };
  const since = Date.now() - days * DAY;
  const ranked = rows
    .filter((r) => r.publishedAt && r.publishedAt.getTime() >= since)
    .map((r) => {
      const base = r.isShort ? medians.shorts : medians.longos;
      return { ...r, ratio: base > 0 ? (r.views7d ?? 0) / base : 0 };
    })
    .sort((a, b) => b.ratio - a.ratio);

  return { medians, items: ranked.filter((r) => r.ratio >= multiplier), recent: ranked };
}

export async function getSearchTerms(month: string, limit = 10) {
  return db
    .select({ term: searchTerms.term, views: searchTerms.views })
    .from(searchTerms)
    .where(eq(searchTerms.month, month))
    .orderBy(desc(searchTerms.views))
    .limit(limit);
}

/** Termos que cresceram em relação ao mês anterior. */
export async function getRisingTerms(month: string, limit = 6) {
  const [current, previous] = await Promise.all([getSearchTerms(month, 30), getSearchTerms(shiftMonth(month, -1), 50)]);
  const prev = new Map(previous.map((p) => [p.term, p.views]));
  return current
    .map((c) => {
      const before = prev.get(c.term) ?? 0;
      return { ...c, before, growth: before > 0 ? (c.views - before) / before : null };
    })
    .sort((a, b) => (b.growth ?? 99) - (a.growth ?? 99))
    .slice(0, limit);
}

/* ---------------- Ideias e bot ---------------- */

export type IdeaStatus = "nova" | "aprovada" | "gravada" | "descartada";

export async function getIdeas(statuses?: IdeaStatus[]) {
  const query = db.select().from(ideas);
  const filtered = statuses?.length ? query.where(inArray(ideas.status, statuses)) : query;
  return filtered.orderBy(desc(ideas.score), desc(ideas.createdAt));
}

export async function getIdeaCounts() {
  const rows = await db.select({ status: ideas.status, n: countAll() }).from(ideas).groupBy(ideas.status);
  const counts: Record<IdeaStatus, number> = { nova: 0, aprovada: 0, gravada: 0, descartada: 0 };
  for (const r of rows) counts[r.status] = r.n;
  return counts;
}

export async function getRecentBotMessages(limit = 20) {
  return db.select().from(botMessages).orderBy(desc(botMessages.createdAt)).limit(limit);
}
