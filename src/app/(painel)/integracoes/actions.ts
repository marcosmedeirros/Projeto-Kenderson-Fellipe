"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { logAudit } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { getConnectionState } from "@/lib/bot/evolution";
import { firstIssue, type FormState } from "@/lib/form-state";
import { getEvolutionConfig, getGeminiConfig, removeIntegration, saveIntegration } from "@/lib/integrations";
import { requestMeta } from "@/lib/request";

const evolutionSchema = z.object({
  baseUrl: z.url({ protocol: /^https?$/, error: "Informe a URL completa do Evolution, com http:// ou https://." }).max(200),
  instance: z.string().trim().regex(/^[\w.-]{1,64}$/, { error: "O nome da instância só pode ter letras, números, ponto, hífen e _." }),
  apiKey: z.string().trim().max(200),
});

export async function saveEvolutionAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requirePermission("integracoes");
  const parsed = evolutionSchema.safeParse({
    baseUrl: String(formData.get("baseUrl") ?? "").trim(),
    instance: formData.get("instance"),
    apiKey: formData.get("apiKey") ?? "",
  });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };

  const current = await getEvolutionConfig();
  const apiKey = parsed.data.apiKey || (current?.source === "painel" ? current.apiKey : "");
  if (!apiKey) return { error: "Informe a API key da instância." };

  await saveIntegration("evolution", { baseUrl: parsed.data.baseUrl, instance: parsed.data.instance, apiKey }, user.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "integracao.salva", detail: { integracao: "evolution", instancia: parsed.data.instance }, ip });
  revalidatePath("/integracoes");
  const state = await getConnectionState();
  return state === "open"
    ? { ok: true, message: "Evolution salvo e número conectado." }
    : { ok: true, message: "Evolution salvo. O número ainda não aparece como conectado; confira a instância no Evolution." };
}

export async function testEvolutionAction(): Promise<FormState> {
  await requirePermission("integracoes");
  const state = await getConnectionState();
  const messages = {
    open: { ok: true, message: "Conexão ok: o número do bot está online." },
    connecting: { error: "O Evolution respondeu, mas o número ainda está conectando." },
    close: { error: "O Evolution respondeu, mas o número está desconectado. Leia o QR code de novo." },
    nao_configurado: { error: "Salve as configurações do Evolution primeiro." },
    erro: { error: "Não foi possível falar com o Evolution. Confira a URL, a instância e a API key." },
  } as const;
  return messages[state];
}

const geminiSchema = z.object({
  apiKey: z.string().trim().max(200),
  model: z.string().trim().regex(/^[\w.-]{3,60}$/, { error: "Nome de modelo inválido." }),
});

export async function saveGeminiAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requirePermission("integracoes");
  const parsed = geminiSchema.safeParse({ apiKey: formData.get("apiKey") ?? "", model: formData.get("model") });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };

  const current = await getGeminiConfig();
  const apiKey = parsed.data.apiKey || (current?.source === "painel" ? current.apiKey : "");
  if (!apiKey) return { error: "Informe a chave da API do Gemini." };

  await saveIntegration("gemini", { apiKey, model: parsed.data.model }, user.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "integracao.salva", detail: { integracao: "gemini", modelo: parsed.data.model }, ip });
  revalidatePath("/integracoes");
  return { ok: true, message: "Gemini salvo. A geração de ideias já usa a IA." };
}

export async function removeIntegrationAction(formData: FormData) {
  const user = await requirePermission("integracoes");
  const key = z.enum(["evolution", "gemini"]).safeParse(formData.get("key"));
  if (!key.success) return;
  await removeIntegration(key.data);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "integracao.removida", detail: { integracao: key.data }, ip });
  revalidatePath("/integracoes");
}
