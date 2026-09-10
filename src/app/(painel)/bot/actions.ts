"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { logAudit } from "@/lib/audit";
import { requirePermission, requireUser } from "@/lib/auth/dal";
import { parseCommand, runCommand } from "@/lib/bot/commands";
import { recordBotMessage } from "@/lib/bot/log";
import { firstIssue, type FormState } from "@/lib/form-state";
import { requestMeta } from "@/lib/request";
import { botSchema, COMMANDS, getSetting, saveSetting } from "@/lib/settings";

export async function simulateCommandAction(text: string): Promise<{ reply?: string; error?: string }> {
  const user = await requireUser();
  const input = z.string().trim().min(1).max(200).safeParse(text);
  if (!input.success) return { error: "Digite um comando, por exemplo /programados." };

  const command = parseCommand(input.data);
  const bot = await getSetting("bot");
  let reply: string;
  if (!command) reply = "Não entendi. Envie /ajuda para ver os comandos disponíveis.";
  else if (!bot.comandos[command]) reply = `O comando /${command} está desativado nas configurações do bot.`;
  else reply = await runCommand(command);

  await recordBotMessage({ direction: "entrada", origin: "simulador", sender: user.name, text: input.data, command });
  await recordBotMessage({ direction: "saida", origin: "simulador", text: reply, command });
  return { reply };
}

export async function saveBotSettingsAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requirePermission("automacoes");
  const parsed = botSchema.safeParse({
    ativo: formData.get("ativo") === "on",
    grupoId: formData.get("grupoId") ?? "",
    grupoNome: formData.get("grupoNome") ?? "",
    comandos: Object.fromEntries(COMMANDS.map((c) => [c, formData.get(`cmd_${c}`) === "on"])),
  });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };

  await saveSetting("bot", parsed.data, user.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "config.bot", detail: { ativo: parsed.data.ativo, grupo: parsed.data.grupoNome }, ip });
  revalidatePath("/bot");
  return { ok: true, message: "Configurações do bot salvas." };
}
