"use server";

import { and, count, eq, ne } from "drizzle-orm";
import { revalidatePath } from "next/cache";
import { z } from "zod";
import { db } from "@/db";
import { users } from "@/db/schema";
import { logAudit } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { generateTemporaryPassword, hashPassword } from "@/lib/auth/password";
import { revokeUserSessions } from "@/lib/auth/session";
import { firstIssue, type FormState } from "@/lib/form-state";
import { requestMeta } from "@/lib/request";

const roleSchema = z.enum(["admin", "editor", "visualizador"]);
const idSchema = z.uuid();

async function otherActiveAdmins(exceptUserId: string) {
  const [row] = await db
    .select({ n: count() })
    .from(users)
    .where(and(eq(users.role, "admin"), eq(users.active, true), ne(users.id, exceptUserId)));
  return row.n;
}

async function findUser(id: unknown) {
  const parsed = idSchema.safeParse(id);
  if (!parsed.success) return null;
  const [user] = await db.select({ id: users.id, name: users.name, role: users.role, active: users.active }).from(users).where(eq(users.id, parsed.data)).limit(1);
  return user ?? null;
}

const createSchema = z.object({
  name: z.string().trim().min(2, { error: "Informe o nome." }).max(80),
  email: z.email({ error: "Informe um e-mail válido." }).max(254),
  role: roleSchema,
});

export async function createUserAction(_state: FormState, formData: FormData): Promise<FormState> {
  const admin = await requirePermission("usuarios");
  const parsed = createSchema.safeParse({
    name: formData.get("name"),
    email: String(formData.get("email") ?? "").trim().toLowerCase(),
    role: formData.get("role"),
  });
  if (!parsed.success) return { error: firstIssue(parsed.error.issues) };

  const [existing] = await db.select({ id: users.id }).from(users).where(eq(users.email, parsed.data.email)).limit(1);
  if (existing) return { error: "Já existe um usuário com esse e-mail." };

  const temporary = generateTemporaryPassword();
  await db.insert(users).values({ ...parsed.data, passwordHash: await hashPassword(temporary), mustChangePassword: true });
  const { ip } = await requestMeta();
  await logAudit({ userId: admin.id, action: "usuario.criado", detail: { email: parsed.data.email, perfil: parsed.data.role }, ip });
  revalidatePath("/usuarios");
  return {
    ok: true,
    message: `${parsed.data.name} foi cadastrado. Envie a senha temporária por um canal privado: ela aparece só agora e será trocada no primeiro acesso.`,
    secret: temporary,
  };
}

export async function updateRoleAction(_state: FormState, formData: FormData): Promise<FormState> {
  const admin = await requirePermission("usuarios");
  const target = await findUser(formData.get("userId"));
  const role = roleSchema.safeParse(formData.get("role"));
  if (!target || !role.success) return { error: "Dados inválidos." };
  if (target.id === admin.id) return { error: "Você não pode alterar o próprio perfil." };
  if (target.role === role.data) return { ok: true, message: "Nada mudou." };
  if (target.role === "admin" && (await otherActiveAdmins(target.id)) === 0) {
    return { error: "O painel precisa de pelo menos um administrador ativo." };
  }
  await db.update(users).set({ role: role.data }).where(eq(users.id, target.id));
  const { ip } = await requestMeta();
  await logAudit({ userId: admin.id, action: "usuario.perfil", detail: { usuario: target.name, de: target.role, para: role.data }, ip });
  revalidatePath("/usuarios");
  return { ok: true, message: "Perfil atualizado." };
}

export async function toggleActiveAction(_state: FormState, formData: FormData): Promise<FormState> {
  const admin = await requirePermission("usuarios");
  const target = await findUser(formData.get("userId"));
  if (!target) return { error: "Usuário não encontrado." };
  if (target.id === admin.id) return { error: "Você não pode desativar a própria conta." };
  if (target.active && target.role === "admin" && (await otherActiveAdmins(target.id)) === 0) {
    return { error: "O painel precisa de pelo menos um administrador ativo." };
  }
  await db.update(users).set({ active: !target.active }).where(eq(users.id, target.id));
  if (target.active) await revokeUserSessions(target.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: admin.id, action: "usuario.status", detail: { usuario: target.name, ativo: !target.active }, ip });
  revalidatePath("/usuarios");
  return { ok: true, message: target.active ? "Acesso desativado e sessões encerradas." : "Acesso reativado." };
}

export async function resetPasswordAction(_state: FormState, formData: FormData): Promise<FormState> {
  const admin = await requirePermission("usuarios");
  const target = await findUser(formData.get("userId"));
  if (!target) return { error: "Usuário não encontrado." };
  if (target.id === admin.id) return { error: "Para trocar a sua senha, use Minha conta." };

  const temporary = generateTemporaryPassword();
  await db.update(users).set({ passwordHash: await hashPassword(temporary), mustChangePassword: true }).where(eq(users.id, target.id));
  await revokeUserSessions(target.id);
  const { ip } = await requestMeta();
  await logAudit({ userId: admin.id, action: "usuario.senha_redefinida", detail: { usuario: target.name }, ip });
  revalidatePath("/usuarios");
  return { ok: true, message: "Nova senha temporária gerada. As sessões do usuário foram encerradas.", secret: temporary };
}
