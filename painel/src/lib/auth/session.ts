import "server-only";
import { createHash, randomBytes } from "node:crypto";
import { and, desc, eq, ne } from "drizzle-orm";
import { cookies } from "next/headers";
import { db } from "@/db";
import { sessions, users } from "@/db/schema";
import { requestMeta } from "@/lib/request";
import { SESSION_COOKIE } from "./constants";
import type { Role } from "./permissions";

const MINUTE = 60_000;
const SESSION_MAX_AGE = 7 * 24 * 60 * MINUTE; // limite absoluto
const IDLE_TIMEOUT = 12 * 60 * MINUTE; // encerra após 12 h sem uso
const PENDING_MAX_AGE = 10 * MINUTE; // tempo para digitar o código de 2 etapas
const TOUCH_INTERVAL = 5 * MINUTE;

export type SessionUser = {
  id: string;
  name: string;
  email: string;
  role: Role;
  totpEnabled: boolean;
  mustChangePassword: boolean;
};

export type CurrentSession = {
  id: string;
  twoFactorPending: boolean;
  twoFactorAttempts: number;
  user: SessionUser;
};

const hashToken = (token: string) => createHash("sha256").update(token).digest("hex");

const cookieOptions = (expires: Date) => ({
  httpOnly: true,
  secure: process.env.NODE_ENV === "production",
  sameSite: "lax" as const,
  path: "/",
  expires,
});

export async function createSession(userId: string, options: { pendingTwoFactor?: boolean } = {}) {
  const token = randomBytes(32).toString("base64url");
  const expiresAt = new Date(Date.now() + (options.pendingTwoFactor ? PENDING_MAX_AGE : SESSION_MAX_AGE));
  const meta = await requestMeta();
  await db.insert(sessions).values({
    id: hashToken(token),
    userId,
    twoFactorPending: Boolean(options.pendingTwoFactor),
    ip: meta.ip,
    userAgent: meta.userAgent,
    expiresAt,
  });
  (await cookies()).set(SESSION_COOKIE, token, cookieOptions(expiresAt));
}

export async function readSession(): Promise<CurrentSession | null> {
  const token = (await cookies()).get(SESSION_COOKIE)?.value;
  if (!token || token.length > 128) return null;
  const id = hashToken(token);

  const [row] = await db
    .select({
      session: sessions,
      user: {
        id: users.id,
        name: users.name,
        email: users.email,
        role: users.role,
        active: users.active,
        totpEnabled: users.totpEnabled,
        mustChangePassword: users.mustChangePassword,
      },
    })
    .from(sessions)
    .innerJoin(users, eq(users.id, sessions.userId))
    .where(eq(sessions.id, id))
    .limit(1);

  if (!row) return null;

  const now = Date.now();
  const idleTooLong = !row.session.twoFactorPending && now - row.session.lastSeenAt.getTime() > IDLE_TIMEOUT;
  if (row.session.expiresAt.getTime() <= now || idleTooLong || !row.user.active) {
    await db.delete(sessions).where(eq(sessions.id, id));
    return null;
  }

  if (now - row.session.lastSeenAt.getTime() > TOUCH_INTERVAL) {
    await db.update(sessions).set({ lastSeenAt: new Date() }).where(eq(sessions.id, id));
  }

  return {
    id,
    twoFactorPending: row.session.twoFactorPending,
    twoFactorAttempts: row.session.twoFactorAttempts,
    user: {
      id: row.user.id,
      name: row.user.name,
      email: row.user.email,
      role: row.user.role,
      totpEnabled: row.user.totpEnabled,
      mustChangePassword: row.user.mustChangePassword,
    },
  };
}

export async function deleteCurrentSession() {
  const store = await cookies();
  const token = store.get(SESSION_COOKIE)?.value;
  if (token) await db.delete(sessions).where(eq(sessions.id, hashToken(token)));
  store.set(SESSION_COOKIE, "", { ...cookieOptions(new Date(0)), maxAge: 0 });
}

export async function registerTwoFactorFailure(sessionId: string, attempts: number) {
  await db.update(sessions).set({ twoFactorAttempts: attempts + 1 }).where(eq(sessions.id, sessionId));
}

export async function revokeUserSessions(userId: string, exceptSessionId?: string) {
  await db
    .delete(sessions)
    .where(exceptSessionId ? and(eq(sessions.userId, userId), ne(sessions.id, exceptSessionId)) : eq(sessions.userId, userId));
}

/** Só remove se a sessão pertencer ao usuário informado. */
export async function revokeSession(userId: string, sessionId: string) {
  await db.delete(sessions).where(and(eq(sessions.id, sessionId), eq(sessions.userId, userId)));
}

export async function listSessions(userId: string) {
  return db
    .select({
      id: sessions.id,
      ip: sessions.ip,
      userAgent: sessions.userAgent,
      createdAt: sessions.createdAt,
      lastSeenAt: sessions.lastSeenAt,
    })
    .from(sessions)
    .where(and(eq(sessions.userId, userId), eq(sessions.twoFactorPending, false)))
    .orderBy(desc(sessions.lastSeenAt));
}
