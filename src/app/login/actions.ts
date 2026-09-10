"use server";

import { eq } from "drizzle-orm";
import { redirect } from "next/navigation";
import { z } from "zod";
import { db } from "@/db";
import { users } from "@/db/schema";
import { logAudit } from "@/lib/audit";
import { dummyVerify, verifyPassword } from "@/lib/auth/password";
import { isLoginBlocked, LOCK_MINUTES, recordLoginAttempt } from "@/lib/auth/rate-limit";
import { createSession, deleteCurrentSession, readSession, registerTwoFactorFailure } from "@/lib/auth/session";
import { verifyTotp } from "@/lib/auth/totp";
import { decrypt } from "@/lib/crypto";
import { requestMeta } from "@/lib/request";

export type LoginState = { error?: string; step?: "senha" | "2fa"; email?: string } | undefined;

const credentialsSchema = z.object({
  email: z.email().max(254),
  password: z.string().min(1).max(200),
});

async function finishLogin(userId: string, ip: string | null) {
  await createSession(userId);
  await db.update(users).set({ lastLoginAt: new Date() }).where(eq(users.id, userId));
  await logAudit({ userId, action: "login.sucesso", ip });
}

export async function loginAction(_state: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const parsed = credentialsSchema.safeParse({ email, password: formData.get("password") });
  if (!parsed.success) return { error: "Informe um e-mail válido e a senha.", email };

  const { ip } = await requestMeta();
  if (await isLoginBlocked(email, ip)) {
    await logAudit({ action: "login.bloqueado", detail: { email }, ip });
    return { error: `Muitas tentativas seguidas. Aguarde ${LOCK_MINUTES} minutos e tente de novo.`, email };
  }

  const [user] = await db.select().from(users).where(eq(users.email, email)).limit(1);
  let valid = false;
  if (user?.active) {
    valid = await verifyPassword(user.passwordHash, parsed.data.password);
  } else {
    await dummyVerify(parsed.data.password);
  }

  if (!user || !valid) {
    await recordLoginAttempt(email, ip, false);
    await logAudit({ userId: user?.id, action: "login.falha", detail: { email }, ip });
    return { error: "E-mail ou senha incorretos.", email };
  }

  await recordLoginAttempt(email, ip, true);

  if (user.totpEnabled) {
    await createSession(user.id, { pendingTwoFactor: true });
    return { step: "2fa" };
  }

  await finishLogin(user.id, ip);
  redirect("/");
}

export type TwoFactorState = { error?: string; expired?: boolean } | undefined;

export async function verifyTwoFactorAction(_state: TwoFactorState, formData: FormData): Promise<TwoFactorState> {
  const session = await readSession();
  if (!session?.twoFactorPending) {
    return { error: "O tempo para digitar o código acabou. Entre novamente.", expired: true };
  }
  if (session.twoFactorAttempts >= 5) {
    await deleteCurrentSession();
    return { error: "Muitos códigos incorretos. Entre novamente.", expired: true };
  }

  const [user] = await db
    .select({ totpSecret: users.totpSecret, totpLastStep: users.totpLastStep })
    .from(users)
    .where(eq(users.id, session.user.id))
    .limit(1);

  const code = String(formData.get("code") ?? "");
  const step = user?.totpSecret ? verifyTotp(decrypt(user.totpSecret), code, user.totpLastStep) : null;
  const { ip } = await requestMeta();

  if (step == null) {
    await registerTwoFactorFailure(session.id, session.twoFactorAttempts);
    await logAudit({ userId: session.user.id, action: "login.2fa_falha", ip });
    return { error: "Código incorreto. Confira o app autenticador e tente de novo." };
  }

  await db.update(users).set({ totpLastStep: step }).where(eq(users.id, session.user.id));
  await deleteCurrentSession();
  await finishLogin(session.user.id, ip);
  redirect("/");
}

export async function cancelTwoFactorAction() {
  await deleteCurrentSession();
  redirect("/login");
}
