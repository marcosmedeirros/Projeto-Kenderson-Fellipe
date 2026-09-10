"use server";

import { eq } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import QRCode from "qrcode";
import { z } from "zod";
import { db } from "@/db";
import { users } from "@/db/schema";
import { logAudit } from "@/lib/audit";
import { getCurrentSession, requireUser } from "@/lib/auth/dal";
import { hashPassword, passwordSchema, verifyPassword } from "@/lib/auth/password";
import { revokeSession, revokeUserSessions } from "@/lib/auth/session";
import { generateTotpSecret, totpUri, verifyTotp } from "@/lib/auth/totp";
import { decrypt, encrypt } from "@/lib/crypto";
import { firstIssue, type FormState } from "@/lib/form-state";
import { requestMeta } from "@/lib/request";

async function checkPassword(userId: string, password: string) {
  const [row] = await db.select({ passwordHash: users.passwordHash }).from(users).where(eq(users.id, userId)).limit(1);
  return Boolean(row) && (await verifyPassword(row.passwordHash, password));
}

export async function changePasswordAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requireUser({ allowPasswordChange: true });
  const current = String(formData.get("current") ?? "");
  const next = String(formData.get("password") ?? "");
  const confirm = String(formData.get("confirm") ?? "");

  const check = passwordSchema.safeParse(next);
  if (!check.success) return { error: firstIssue(check.error.issues) };
  if (next !== confirm) return { error: "A confirmação não é igual à nova senha." };
  if (next === current) return { error: "A nova senha precisa ser diferente da atual." };
  if (!(await checkPassword(user.id, current))) return { error: "A senha atual está incorreta." };

  await db.update(users).set({ passwordHash: await hashPassword(next), mustChangePassword: false }).where(eq(users.id, user.id));
  const session = await getCurrentSession();
  await revokeUserSessions(user.id, session?.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "senha.alterada", ip });

  if (user.mustChangePassword) redirect("/");
  revalidatePath("/conta");
  return { ok: true, message: "Senha alterada. As sessões em outros aparelhos foram encerradas." };
}

export async function startTotpSetupAction(): Promise<{ qr?: string; secret?: string; error?: string }> {
  const user = await requireUser();
  if (user.totpEnabled) return { error: "A verificação em 2 etapas já está ativa." };
  const secret = generateTotpSecret();
  await db.update(users).set({ totpSecret: encrypt(secret), totpLastStep: null }).where(eq(users.id, user.id));
  const qr = await QRCode.toDataURL(totpUri(secret, user.email), {
    margin: 1,
    width: 208,
    color: { dark: "#0a0f14", light: "#ffffff" },
  });
  return { qr, secret: secret.replace(/(.{4})/g, "$1 ").trim() };
}

export async function confirmTotpAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requireUser();
  const [row] = await db.select({ totpSecret: users.totpSecret }).from(users).where(eq(users.id, user.id)).limit(1);
  if (!row?.totpSecret) return { error: "Comece a configuração de novo." };
  const step = verifyTotp(decrypt(row.totpSecret), String(formData.get("code") ?? ""));
  if (step == null) return { error: "Código incorreto. Confira se o horário do celular está automático e tente de novo." };

  await db.update(users).set({ totpEnabled: true, totpLastStep: step }).where(eq(users.id, user.id));
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "2fa.ativado", ip });
  revalidatePath("/conta");
  return { ok: true, message: "Verificação em 2 etapas ativada." };
}

export async function disableTotpAction(_state: FormState, formData: FormData): Promise<FormState> {
  const user = await requireUser();
  if (!(await checkPassword(user.id, String(formData.get("password") ?? "")))) {
    return { error: "Senha incorreta." };
  }
  await db.update(users).set({ totpEnabled: false, totpSecret: null, totpLastStep: null }).where(eq(users.id, user.id));
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "2fa.desativado", ip });
  revalidatePath("/conta");
  return { ok: true, message: "Verificação em 2 etapas desativada." };
}

export async function revokeSessionAction(formData: FormData) {
  const user = await requireUser();
  const id = z.string().regex(/^[a-f0-9]{64}$/).safeParse(formData.get("sessionId"));
  if (!id.success) return;
  await revokeSession(user.id, id.data);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "sessao.encerrada", ip });
  revalidatePath("/conta");
}

export async function revokeOtherSessionsAction() {
  const user = await requireUser();
  const session = await getCurrentSession();
  await revokeUserSessions(user.id, session?.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: user.id, action: "sessao.encerrada", detail: { todas: true }, ip });
  revalidatePath("/conta");
}
