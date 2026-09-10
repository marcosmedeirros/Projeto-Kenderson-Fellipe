import {
  getIdeas,
  getMonthToDate,
  getScheduledVideos,
  getTopVideos,
  getViralVideos,
  summarizeStock,
  type StockSummary,
  type ScheduledVideo,
} from "@/lib/channel/queries";
import { fmtCompact, fmtDate, fmtInDays, fmtLongDay, fmtPercent, fmtTime, fmtWeekday, daysUntil, monthLabel } from "@/lib/format";
import { getMonthTotals } from "@/lib/channel/queries";
import { COMMANDS, getSetting, type CommandName } from "@/lib/settings";

export const COMMAND_INFO: Record<CommandName, { label: string; description: string }> = {
  programados: { label: "/programados", description: "Vídeos agendados, datas e até quando o estoque dura." },
  semcapa: { label: "/semcapa", description: "Vídeos programados que ainda estão sem thumbnail." },
  resumo: { label: "/resumo", description: "Visão rápida: estoque, capas, views do mês e destaques." },
  virais: { label: "/virais", description: "Vídeos que performaram acima da média nos últimos 45 dias." },
  ideias: { label: "/ideias", description: "Ideias de pauta aprovadas e as mais bem avaliadas." },
  ajuda: { label: "/ajuda", description: "Lista os comandos disponíveis." },
};

const ALIASES: Record<string, CommandName> = {
  programados: "programados",
  agendados: "programados",
  estoque: "programados",
  semcapa: "semcapa",
  capas: "semcapa",
  capa: "semcapa",
  thumbs: "semcapa",
  resumo: "resumo",
  virais: "virais",
  viral: "virais",
  ideias: "ideias",
  pautas: "ideias",
  ajuda: "ajuda",
  comandos: "ajuda",
  menu: "ajuda",
  help: "ajuda",
};

export function parseCommand(text: string): CommandName | null {
  const normalized = text.trim().toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
  if (!normalized.startsWith("/")) return null;
  const word = normalized.slice(1).split(/\s+/)[0]?.replace(/[-_]/g, "") ?? "";
  return ALIASES[word] ?? null;
}

const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;

const videoLine = (v: ScheduledVideo) => {
  const d = v.publishAt!;
  const flags = [v.isShort ? "[Short]" : "", v.thumbnailStatus !== "ok" ? "⚠️ sem capa" : ""].filter(Boolean).join(" ");
  return `• ${fmtDate(d)} (${fmtWeekday(d)}) ${fmtTime(d)} · ${v.title}${flags ? ` ${flags}` : ""}`;
};

export function buildStockAlert(stock: StockSummary, minimumDays: number) {
  if (stock.total === 0) return "⚠️ *Nenhum vídeo programado.*\nO estoque acabou. Hora de gravar!";
  return [
    `⚠️ *Estoque baixo:* os vídeos programados cobrem só até *${fmtDate(stock.lastDate!)}* (${plural(stock.daysCovered, "dia", "dias")}).`,
    `O mínimo combinado é de ${minimumDays} dias. Bom momento para agendar gravação.`,
  ].join("\n");
}

export function buildThumbAlert(pending: ScheduledVideo[]) {
  const lines = pending.map((v) => `• ${fmtDate(v.publishAt!)} · ${v.title} (publica ${fmtInDays(daysUntil(v.publishAt!))})`);
  return [`🖼️ *${plural(pending.length, "vídeo precisa", "vídeos precisam")} de capa com urgência*`, "", ...lines].join("\n");
}

async function programados() {
  const [list, alertas] = await Promise.all([getScheduledVideos(), getSetting("alertas")]);
  const stock = summarizeStock(list);
  if (!list.length) return buildStockAlert(stock, alertas.estoqueMinimoDias);
  const footer = [`Estoque cobre até *${fmtDate(stock.lastDate!)}* (${plural(stock.daysCovered, "dia", "dias")}).`];
  if (stock.daysCovered < alertas.estoqueMinimoDias) {
    footer.push(`⚠️ Abaixo do mínimo de ${alertas.estoqueMinimoDias} dias. Bom momento para gravar.`);
  }
  return [`📅 *${plural(list.length, "vídeo programado", "vídeos programados")}*`, "", ...list.map(videoLine), "", ...footer].join("\n");
}

async function semcapa() {
  const [list, alertas] = await Promise.all([getScheduledVideos(), getSetting("alertas")]);
  const pending = list.filter((v) => v.thumbnailStatus !== "ok");
  if (!pending.length) return "✅ *Todos os vídeos programados já têm capa.*";
  const lines = pending.map((v) => {
    const days = daysUntil(v.publishAt!);
    const urgent = days <= alertas.capaAvisoDias ? "🔴 " : "";
    return `• ${urgent}${fmtDate(v.publishAt!)} · ${v.title} (publica ${fmtInDays(days)})`;
  });
  return [`🖼️ *${plural(pending.length, "vídeo sem capa", "vídeos sem capa")}*`, "", ...lines].join("\n");
}

async function resumo() {
  const [list, mtd, alertas, approved] = await Promise.all([
    getScheduledVideos(),
    getMonthToDate(),
    getSetting("alertas"),
    getIdeas(["aprovada"]),
  ]);
  const stock = summarizeStock(list);
  const virais = await getViralVideos(alertas.viralMultiplicador);
  const change = mtd.change == null ? "" : ` (${mtd.change >= 0 ? "+" : ""}${fmtPercent(mtd.change)} vs mês passado)`;
  const lines = [
    `📊 *Resumo do canal · ${fmtLongDay(new Date())}*`,
    "",
    stock.total
      ? `📅 Programados: *${stock.total}* (até ${fmtDate(stock.lastDate!)}, ${plural(stock.daysCovered, "dia", "dias")})`
      : "📅 Programados: *nenhum*",
    `🖼️ Sem capa: *${stock.missingThumbs}*`,
    `👀 Views no mês: *${fmtCompact(mtd.views)}*${change}`,
    `➕ Inscritos no mês: *${fmtCompact(mtd.subscribers)}*`,
  ];
  if (virais.items[0]) lines.push(`🔥 Em alta: *${virais.items[0].title}* (${virais.items[0].ratio.toFixed(1).replace(".", ",")}x a média)`);
  lines.push(`💡 Ideias aprovadas: *${approved.length}*`);
  if (stock.daysCovered < alertas.estoqueMinimoDias) lines.push("", "⚠️ Estoque abaixo do mínimo. Hora de gravar.");
  return lines.join("\n");
}

async function virais() {
  const alertas = await getSetting("alertas");
  const { items } = await getViralVideos(alertas.viralMultiplicador);
  if (!items.length) {
    return `Nenhum vídeo passou de ${alertas.viralMultiplicador}x a média nos últimos 45 dias.`;
  }
  const lines = items.slice(0, 8).map(
    (v) => `• ${v.title}${v.isShort ? " [Short]" : ""} · ${fmtCompact(v.views7d ?? 0)} views em 7 dias (*${v.ratio.toFixed(1).replace(".", ",")}x* a média)`,
  );
  return ["🔥 *Vídeos em alta · últimos 45 dias*", "", ...lines].join("\n");
}

async function ideias() {
  const list = await getIdeas(["aprovada", "nova"]);
  if (!list.length) return "💡 Nenhuma ideia cadastrada ainda. Gere sugestões pelo painel.";
  const top = [...list.filter((i) => i.status === "aprovada"), ...list.filter((i) => i.status === "nova")].slice(0, 6);
  const lines = top.map((i) => `• *${i.title}*${i.status === "aprovada" ? " ✅" : ""}\n  ${i.rationale}`);
  return ["💡 *Ideias de pauta*", "", ...lines, "", "✅ = aprovada. Veja todas no painel."].join("\n");
}

async function ajuda() {
  const bot = await getSetting("bot");
  const enabled = COMMANDS.filter((c) => bot.comandos[c]);
  return ["🤖 *Comandos disponíveis*", "", ...enabled.map((c) => `${COMMAND_INFO[c].label} · ${COMMAND_INFO[c].description}`)].join("\n");
}

const HANDLERS: Record<CommandName, () => Promise<string>> = { programados, semcapa, resumo, virais, ideias, ajuda };

export function runCommand(command: CommandName) {
  return HANDLERS[command]();
}

export async function buildMonthlyReport(month: string) {
  const alertas = await getSetting("alertas");
  const [totals, top, virais] = await Promise.all([getMonthTotals(month), getTopVideos(month, 3), getViralVideos(alertas.viralMultiplicador, 31)]);
  const lines = [
    `🗓️ *Relatório de ${monthLabel(month).toLowerCase()}*`,
    "",
    `👀 Views: *${fmtCompact(totals.views)}*`,
    `⏱️ Horas assistidas: *${fmtCompact(Math.round(totals.watchMinutes / 60))}*`,
    `➕ Inscritos: *${fmtCompact(totals.subscribers)}*`,
    "",
    "*Mais vistos*",
    ...top.map((v, i) => `${i + 1}. ${v.title} · ${fmtCompact(v.views)}`),
  ];
  if (virais.items.length) lines.push("", `🔥 ${plural(virais.items.length, "vídeo viralizou", "vídeos viralizaram")} no período.`);
  return lines.join("\n");
}
