import { count } from "drizzle-orm";
import type { Db } from "@/db/client";
import { botMessages, channelDaily, ideas, searchTerms, videoMetrics, videos } from "@/db/schema";
import { dayKey, monthKey, shiftMonth } from "@/lib/format";

// Dados fictícios para demonstrar o painel enquanto o YouTube não está conectado.
// Tudo é gerado em relação à data atual, então o painel sempre parece "vivo".

const DAY = 86_400_000;

function random(seed: number) {
  return () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const at = (daysFromNow: number, hour: number) => {
  const d = new Date(Date.now() + daysFromNow * DAY);
  // 18:00 em São Paulo = 21:00 UTC
  d.setUTCHours(hour + 3, 0, 0, 0);
  return d;
};

const SCHEDULED = [
  { title: "Respondendo as perguntas mais pedidas de vocês", days: 2, thumb: "ok", short: false },
  { title: "O que ninguém te conta sobre começar do zero", days: 5, thumb: "pendente", short: false },
  { title: "O erro que eu mais cometia (e como parei)", days: 6, thumb: "pendente", short: true },
  { title: "Testei acordar às 5h por 30 dias", days: 9, thumb: "ok", short: false },
  { title: "Reagindo aos comentários do último vídeo", days: 13, thumb: "ok", short: false },
] as const;

const PUBLISHED = [
  { title: "Minha rotina real de gravação (sem cortes)", days: 3, v7: 21_400, short: false },
  { title: "3 hábitos que mudaram meu ano", days: 5, v7: 88_300, short: true },
  { title: "Como organizo a semana em 20 minutos", days: 7, v7: 64_900, short: false },
  { title: "Vale a pena largar tudo para empreender?", days: 10, v7: 19_800, short: false },
  { title: "A pergunta que mais recebo", days: 12, v7: 41_200, short: true },
  { title: "Bastidores: um dia inteiro comigo", days: 14, v7: 23_600, short: false },
  { title: "O que aprendi com 100 vídeos publicados", days: 17, v7: 18_900, short: false },
  { title: "Disciplina x motivação: qual importa mais", days: 19, v7: 27_100, short: false },
  { title: "Não cometa esse erro no começo", days: 21, v7: 212_000, short: true },
  { title: "Metas que eu desisti (e por quê)", days: 24, v7: 16_700, short: false },
  { title: "Respondendo críticas sem filtro", days: 26, v7: 58_400, short: false },
  { title: "Minha mesa de trabalho em 2026", days: 28, v7: 22_300, short: false },
  { title: "Quanto tempo leva para ver resultado", days: 31, v7: 35_900, short: true },
  { title: "Mudei minha alimentação por 1 mês", days: 33, v7: 20_100, short: false },
  { title: "Como lido com dias improdutivos", days: 35, v7: 24_800, short: false },
  { title: "Perguntas rápidas, respostas sinceras", days: 38, v7: 29_700, short: true },
  { title: "O livro que mais me marcou este ano", days: 42, v7: 17_200, short: false },
  { title: "Organização financeira do jeito simples", days: 46, v7: 31_500, short: false },
  { title: "Meu maior arrependimento", days: 52, v7: 26_900, short: false },
  { title: "Planejamento do segundo semestre", days: 60, v7: 19_400, short: false },
  { title: "Rotina da manhã que funciona de verdade", days: 68, v7: 23_000, short: false },
];

const TERMS: [string, number][] = [
  ["luiz jordão", 38_200],
  ["rotina da manhã", 21_700],
  ["como organizar a semana", 18_400],
  ["disciplina", 12_900],
  ["acordar cedo", 11_300],
  ["começar do zero", 9_800],
  ["hábitos que mudam a vida", 8_600],
  ["produtividade", 7_900],
  ["organização financeira", 6_200],
  ["metas 2026", 5_100],
  ["dias improdutivos", 3_400],
  ["mesa de trabalho", 2_700],
];

export async function seedDemoIfEmpty(db: Db) {
  const [{ n }] = await db.select({ n: count() }).from(videos);
  if (n > 0) return false;

  const rand = random(20260910);

  await db.insert(videos).values(
    SCHEDULED.map((v, i) => ({
      id: `demo-agendado-${i + 1}`,
      title: v.title,
      status: "agendado" as const,
      publishAt: at(v.days, v.short ? 12 : 18),
      isShort: v.short,
      durationSec: v.short ? 45 : 600 + Math.round(rand() * 900),
      thumbnailStatus: v.thumb,
      thumbnailSource: "automatico" as const,
      thumbnailUpdatedAt: new Date(),
    })),
  );

  const publishedRows = PUBLISHED.map((v, i) => {
    const age = v.days;
    const growth = age >= 7 ? 1.9 + rand() * 1.4 + age / 60 : 0.5 + age * 0.08;
    const views = Math.round(v.v7 * growth);
    return {
      id: `demo-publicado-${i + 1}`,
      title: v.title,
      status: "publicado" as const,
      publishedAt: at(-v.days, v.short ? 12 : 18),
      isShort: v.short,
      durationSec: v.short ? 30 + Math.round(rand() * 30) : 480 + Math.round(rand() * 1200),
      thumbnailStatus: "ok" as const,
      thumbnailSource: "automatico" as const,
      views,
      likes: Math.round(views * (0.03 + rand() * 0.02)),
      comments: Math.round(views * (0.002 + rand() * 0.003)),
      views7d: age >= 7 ? v.v7 : Math.round(v.v7 * Math.min(1, age / 7)),
      syncedAt: new Date(),
    };
  });
  await db.insert(videos).values(publishedRows);

  // Métricas mensais por vídeo (mês atual e os dois anteriores)
  const current = monthKey();
  const months = [current, shiftMonth(current, -1), shiftMonth(current, -2)];
  const metrics = publishedRows.flatMap((v) => {
    const published = monthKey(v.publishedAt);
    return months
      .filter((m) => m >= published)
      .map((m, idx, list) => {
        const isFirst = m === published;
        const share = isFirst ? 0.72 : 0.28 / Math.max(1, list.length - 1);
        const views = Math.round(v.views * share);
        return {
          videoId: v.id,
          month: m,
          views,
          watchMinutes: Math.round(views * ((v.durationSec ?? 60) / 60) * (v.isShort ? 0.85 : 0.42)),
          avgViewPct: v.isShort ? 70 + rand() * 25 : 34 + rand() * 22,
          subscribersGained: Math.round(views * (0.004 + rand() * 0.004)),
          likes: Math.round(v.likes * share),
          comments: Math.round(v.comments * share),
        };
      });
  });
  await db.insert(videoMetrics).values(metrics);

  // Série diária do canal (últimos 95 dias), com picos após os vídeos virais
  const spikes = publishedRows.filter((v) => (v.views7d ?? 0) > 60_000).map((v) => v.publishedAt.getTime());
  const daily = Array.from({ length: 95 }, (_, i) => {
    const date = new Date(Date.now() - (94 - i) * DAY);
    const weekday = date.getUTCDay();
    const base = 19_000 + i * 45 + (weekday === 0 || weekday === 6 ? -2_500 : 1_200);
    const spike = spikes.reduce((acc, s) => {
      const diff = (date.getTime() - s) / DAY;
      return diff >= 0 && diff < 9 ? acc + 26_000 * Math.exp(-diff / 2.2) : acc;
    }, 0);
    const views = Math.round((base + spike) * (0.88 + rand() * 0.24));
    return {
      day: dayKey(date),
      views,
      watchMinutes: Math.round(views * (2.4 + rand() * 0.8)),
      subscribersGained: Math.round(views * (0.0045 + rand() * 0.002)),
    };
  });
  await db.insert(channelDaily).values(daily).onConflictDoNothing();

  await db.insert(searchTerms).values(
    months.flatMap((m, idx) =>
      TERMS.map(([term, views], t) => {
        const trend = idx === 0 ? 1 : idx === 1 ? 0.78 - (t % 3) * 0.12 : 0.6 - (t % 4) * 0.1;
        const partial = idx === 0 ? 0.36 : 1;
        return { month: m, term, views: Math.max(120, Math.round(views * Math.max(0.15, trend) * partial * (0.9 + rand() * 0.2))) };
      }),
    ),
  );

  await db.insert(ideas).values([
    { title: "Rotina da manhã: versão para quem trabalha à noite", rationale: '"Rotina da manhã" é o 2º termo mais buscado que traz gente ao canal, e ninguém fala da rotina de quem trabalha à noite.', source: "buscas" as const, score: 86, status: "aprovada" as const, generatedBy: "exemplo" as const },
    { title: "Os erros do começo, parte 2", rationale: "O Short sobre erros do começo fez mais de 7x a média em 7 dias. Vale uma sequência em vídeo longo.", source: "desempenho" as const, score: 91, status: "aprovada" as const, generatedBy: "exemplo" as const },
    { title: "Minha semana organizada ao vivo, do zero", rationale: 'Buscas por "como organizar a semana" cresceram e o vídeo sobre o tema segurou 51% de retenção.', source: "buscas" as const, score: 78, status: "nova" as const, generatedBy: "exemplo" as const },
    { title: "Respondendo quem disse que acordar cedo é bobagem", rationale: "Os comentários do vídeo sobre acordar às 5h dividiram opiniões. Polêmica boa gera conversa.", source: "comentarios" as const, score: 72, status: "nova" as const, generatedBy: "exemplo" as const },
    { title: "Finanças para quem ganha pouco", rationale: '"Organização financeira" apareceu entre os termos de busca que mais cresceram no mês.', source: "buscas" as const, score: 64, status: "nova" as const, generatedBy: "exemplo" as const },
    { title: "Tour pela mesa de trabalho", rationale: "Teve pouca busca e o vídeo anterior sobre o tema ficou abaixo da média.", source: "desempenho" as const, score: 31, status: "descartada" as const, generatedBy: "exemplo" as const },
  ]);

  const minutesAgo = (m: number) => new Date(Date.now() - m * 60_000);
  await db.insert(botMessages).values([
    { direction: "saida" as const, origin: "automacao" as const, chatId: "demo@g.us", text: "⚠️ *Estoque baixo:* os vídeos programados cobrem só até a próxima semana.", command: "alerta-estoque", createdAt: minutesAgo(190) },
    { direction: "entrada" as const, origin: "whatsapp" as const, chatId: "demo@g.us", sender: "Luiz", text: "/programados", command: "programados", createdAt: minutesAgo(64) },
    { direction: "saida" as const, origin: "whatsapp" as const, chatId: "demo@g.us", text: "📅 *5 vídeos programados* …", command: "programados", createdAt: minutesAgo(64) },
    { direction: "entrada" as const, origin: "whatsapp" as const, chatId: "demo@g.us", sender: "Kenderson", text: "/semcapa", command: "semcapa", createdAt: minutesAgo(22) },
    { direction: "saida" as const, origin: "whatsapp" as const, chatId: "demo@g.us", text: "🖼️ *2 vídeos sem capa* …", command: "semcapa", createdAt: minutesAgo(22) },
  ]);

  return true;
}
