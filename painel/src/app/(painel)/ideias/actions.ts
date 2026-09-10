"use server";

import { eq } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { z } from "zod";
import { db } from "@/db";
import { ideas } from "@/db/schema";
import { generateIdeas } from "@/lib/ai/ideas";
import { logAudit } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { firstIssue, type FormState } from "@/lib/form-state";
import { requestMeta } from "@/lib/request";

export async function generateIdeasAction(): Promise<FormState> {
  const user = await requirePermission("operar");
  try {
    const result = await generateIdeas();
    const { ip } = await requestMeta();
    await logAudit({ userId: user.id, action: "ideia.gerada", detail: { quantidade: result.created, origem: result.generatedBy }, ip });
    revalidatePath("/ideias");
    if (!result.created) return { error: "Não surgiram ideias novas com os dados atuais. Tente de novo mais tarde." };
    return {
      ok: true,
      message:
        result.generatedBy === "ia"
          ? `${result.created} ideias novas geradas pelo Gemini.`
          : `${result.created} ideias de exemplo criadas a partir das buscas. Cadastre a chave do Gemini para usar IA.`,
    };
  } catch (error) {
    console.error("[ideias]", error);
    return { error: error instanceof Error ? error.message : "Não foi possível gerar ideias agora." };
  }
}

const statusSchema = z.object({
  id: z.coerce.number().int().positive(),
  status: z.enum(["nova", "aprovada", "gravada", "descartada"]),
});

export async function setIdeaStatusAction(formData: FormData) {
  const user = await requirePermission("operar");
  const parsed = statusSchema.safeParse({ id: formData.get("id"), status: formData.get("status") });
  if (!parsed.success) return;
  const [idea] = await db
    .update(ideas)
    .set({ status: parsed.data.status, updatedBy: user.id, updatedAt: new Date() })
    .where(eq(ideas.id, parsed.data.id))
    .returning({ title: ideas.title });
  if (idea) {
    const { ip } = await requestMeta();
    await logAudit({ userId: user.id, action: "ideia.status", detail: { ideia: idea.title, status: parsed.data.status }, ip });
  }
  revalidatePath("/", "layout");
}

const createSchema = z.object({
  title: z.string().trim().min(5, { error: "O título precisa ter pelo menos 5 caracteres." }).max(140),
  rationale: z.string().trim().min(5, { error: "Explique em uma frase por que a ideia é boa." }).max(400),
});

export async function createIdeaAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requirePermission("operar");
  const parsed = createSchema.safeParse({ title: formData.get("title"), rationale: formData.get("rationale") });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };
  await db.insert(ideas).values({ ...parsed.data, source: "manual", score: 60, generatedBy: "usuario", updatedBy: user.id });
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "ideia.criada", detail: { ideia: parsed.data.title }, ip });
  revalidatePath("/ideias");
  return { ok: true, message: "Ideia cadastrada." };
}
