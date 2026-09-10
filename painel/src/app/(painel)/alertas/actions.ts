"use server";

import { revalidatePath } from "next/cache";
import { logAudit } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { firstIssue, type FormState } from "@/lib/form-state";
import { requestMeta } from "@/lib/request";
import { alertasSchema, saveSetting } from "@/lib/settings";

export async function saveAlertSettingsAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requirePermission("automacoes");
  const parsed = alertasSchema.safeParse({
    estoqueMinimoDias: formData.get("estoqueMinimoDias"),
    capaAvisoDias: formData.get("capaAvisoDias"),
    alertaEstoqueHora: formData.get("alertaEstoqueHora"),
    resumoSemanalAtivo: formData.get("resumoSemanalAtivo") === "on",
    resumoSemanalDia: formData.get("resumoSemanalDia"),
    resumoSemanalHora: formData.get("resumoSemanalHora"),
    relatorioMensalAtivo: formData.get("relatorioMensalAtivo") === "on",
    relatorioMensalHora: formData.get("relatorioMensalHora"),
    viralMultiplicador: String(formData.get("viralMultiplicador") ?? "").replace(",", "."),
  });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };

  await saveSetting("alertas", parsed.data, user.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "config.alertas", detail: parsed.data, ip });
  revalidatePath("/", "layout");
  return { ok: true, message: "Alertas salvos. Os próximos envios já seguem as novas regras." };
}
