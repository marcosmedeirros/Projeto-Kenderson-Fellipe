import { buildMonthlyReport, buildStockAlert, buildThumbAlert, runCommand } from "@/lib/bot/commands";
import { sendWhatsAppText } from "@/lib/bot/evolution";
import { recordBotMessage } from "@/lib/bot/log";
import { purgeOldLoginAttempts } from "@/lib/auth/rate-limit";
import { getScheduledVideos, summarizeStock } from "@/lib/channel/queries";
import { daysUntil, nowInSaoPaulo, shiftMonth } from "@/lib/format";
import { getSetting, saveSetting } from "@/lib/settings";

const toMinutes = (hhmm: string) => {
  const [h, m] = hhmm.split(":").map(Number);
  return h * 60 + m;
};

async function sendToGroup(chatId: string, text: string, command: string) {
  const result = await sendWhatsAppText(chatId, text);
  await recordBotMessage({ direction: "saida", origin: "automacao", chatId, text, command, delivered: result.ok });
  if (!result.ok) console.warn(`[automações] ${command} não enviado: ${result.error}`);
}

/** Verifica e dispara os envios automáticos que já passaram do horário configurado hoje. */
export async function runDueAutomations(now = new Date()) {
  const [alertas, bot, estado] = await Promise.all([getSetting("alertas"), getSetting("bot"), getSetting("automacoes")]);
  const sp = nowInSaoPaulo(now);
  const minutesNow = sp.hour * 60 + sp.minute;
  const next = { ...estado };

  if (next.ultimoAlertaEstoque !== sp.dayKey && minutesNow >= toMinutes(alertas.alertaEstoqueHora)) {
    next.ultimoAlertaEstoque = sp.dayKey;
    await purgeOldLoginAttempts();
  }

  const canSend = bot.ativo && Boolean(bot.grupoId);
  if (!canSend) {
    if (JSON.stringify(next) !== JSON.stringify(estado)) await saveSetting("automacoes", next);
    return;
  }

  if (estado.ultimoAlertaEstoque !== sp.dayKey && minutesNow >= toMinutes(alertas.alertaEstoqueHora)) {
    const list = await getScheduledVideos();
    const stock = summarizeStock(list, now);
    if (stock.daysCovered < alertas.estoqueMinimoDias) {
      await sendToGroup(bot.grupoId, buildStockAlert(stock, alertas.estoqueMinimoDias), "alerta-estoque");
    }
    if (next.ultimoAlertaCapa !== sp.dayKey) {
      const urgent = list.filter((v) => v.thumbnailStatus !== "ok" && daysUntil(v.publishAt!, now) <= alertas.capaAvisoDias);
      if (urgent.length) await sendToGroup(bot.grupoId, buildThumbAlert(urgent), "alerta-capa");
      next.ultimoAlertaCapa = sp.dayKey;
    }
  }

  if (
    alertas.resumoSemanalAtivo &&
    sp.weekday === alertas.resumoSemanalDia &&
    estado.ultimoResumoSemanal !== sp.dayKey &&
    minutesNow >= toMinutes(alertas.resumoSemanalHora)
  ) {
    await sendToGroup(bot.grupoId, await runCommand("resumo"), "resumo-semanal");
    next.ultimoResumoSemanal = sp.dayKey;
  }

  if (
    alertas.relatorioMensalAtivo &&
    sp.day === 1 &&
    estado.ultimoRelatorioMensal !== sp.monthKey &&
    minutesNow >= toMinutes(alertas.relatorioMensalHora)
  ) {
    await sendToGroup(bot.grupoId, await buildMonthlyReport(shiftMonth(sp.monthKey, -1)), "relatorio-mensal");
    next.ultimoRelatorioMensal = sp.monthKey;
  }

  if (JSON.stringify(next) !== JSON.stringify(estado)) await saveSetting("automacoes", next);
}

export function startScheduler() {
  const store = globalThis as unknown as { __controladoriaScheduler?: ReturnType<typeof setInterval> };
  if (store.__controladoriaScheduler) return;
  const tick = () => runDueAutomations().catch((error) => console.error("[automações]", error));
  store.__controladoriaScheduler = setInterval(tick, 60_000);
  setTimeout(tick, 20_000);
}
