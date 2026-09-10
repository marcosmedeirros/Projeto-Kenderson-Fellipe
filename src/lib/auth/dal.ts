import "server-only";
import { redirect } from "next/navigation";
import { cache } from "react";
import { can, type Permission } from "./permissions";
import { readSession } from "./session";

/** Sessão da requisição atual (lida do banco uma vez por renderização). */
export const getCurrentSession = cache(readSession);

/**
 * Exige um usuário logado e com a verificação em 2 etapas concluída.
 * Deve ser chamada em toda página e em toda Server Action, não só no layout.
 */
export async function requireUser(options: { allowPasswordChange?: boolean } = {}) {
  const session = await getCurrentSession();
  if (!session || session.twoFactorPending) redirect("/login");
  if (session.user.mustChangePassword && !options.allowPasswordChange) redirect("/primeiro-acesso");
  return session.user;
}

export async function requirePermission(permission: Permission) {
  const user = await requireUser();
  if (!can(user.role, permission)) redirect("/?acesso=negado");
  return user;
}
