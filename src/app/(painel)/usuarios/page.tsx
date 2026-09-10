import { asc } from "drizzle-orm";
import { ShieldCheck, UserPlus, Users } from "lucide-react";
import type { Metadata } from "next";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, PageHeader } from "@/components/ui";
import { db } from "@/db";
import { users } from "@/db/schema";
import { requirePermission } from "@/lib/auth/dal";
import { ROLE_DESCRIPTION, ROLE_LABEL, type Role } from "@/lib/auth/permissions";
import { fmtAgo } from "@/lib/format";
import { createUserAction, resetPasswordAction, toggleActiveAction, updateRoleAction } from "./actions";

export const metadata: Metadata = { title: "Usuários" };

const ROLES = Object.keys(ROLE_LABEL) as Role[];

export default async function UsersPage() {
  const admin = await requirePermission("usuarios");
  const list = await db
    .select({
      id: users.id,
      name: users.name,
      email: users.email,
      role: users.role,
      active: users.active,
      totpEnabled: users.totpEnabled,
      mustChangePassword: users.mustChangePassword,
      lastLoginAt: users.lastLoginAt,
    })
    .from(users)
    .orderBy(asc(users.name));

  return (
    <>
      <PageHeader eyebrow="Administração" title="Usuários" description="Quem acessa o painel e o que cada pessoa pode fazer." />

      <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
        <Card className="min-w-0">
          <CardHeader title={`${list.length} ${list.length === 1 ? "usuário" : "usuários"}`} icon={Users} />
          <ul className="divide-y divide-line">
            {list.map((u) => {
              const self = u.id === admin.id;
              return (
                <li key={u.id} className="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto]">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="font-semibold">{u.name}</span>
                      {self && <Badge tone="info">Você</Badge>}
                      {!u.active && <Badge tone="danger">Desativado</Badge>}
                      {u.mustChangePassword && u.active && <Badge tone="warn">Aguardando 1º acesso</Badge>}
                      {u.totpEnabled ? (
                        <Badge tone="ok"><ShieldCheck className="size-3" />2 etapas</Badge>
                      ) : (
                        <Badge tone="neutral">Sem 2 etapas</Badge>
                      )}
                    </div>
                    <p className="mt-0.5 truncate text-sm text-muted">{u.email}</p>
                    <p className="mt-0.5 text-xs text-dim">{u.lastLoginAt ? `Último acesso ${fmtAgo(u.lastLoginAt)}` : "Nunca acessou"}</p>
                  </div>

                  {self ? (
                    <p className="self-center text-sm text-muted">{ROLE_LABEL[u.role]}</p>
                  ) : (
                    <div className="flex flex-wrap items-start gap-2">
                      <ActionForm action={updateRoleAction} className="flex max-w-[260px] flex-wrap gap-2">
                        <input type="hidden" name="userId" value={u.id} />
                        <select name="role" defaultValue={u.role} className="input h-8 w-auto py-0 text-[13px]" aria-label={`Perfil de ${u.name}`}>
                          {ROLES.map((r) => (
                            <option key={r} value={r}>{ROLE_LABEL[r]}</option>
                          ))}
                        </select>
                        <SubmitButton className="btn btn-secondary btn-sm">Salvar</SubmitButton>
                      </ActionForm>
                      <ActionForm action={resetPasswordAction} className="max-w-[300px]">
                        <input type="hidden" name="userId" value={u.id} />
                        <SubmitButton className="btn btn-ghost btn-sm" pendingText="Gerando…">Redefinir senha</SubmitButton>
                      </ActionForm>
                      <ActionForm action={toggleActiveAction} className="max-w-[260px]">
                        <input type="hidden" name="userId" value={u.id} />
                        <SubmitButton className={u.active ? "btn btn-danger btn-sm" : "btn btn-secondary btn-sm"}>
                          {u.active ? "Desativar" : "Reativar"}
                        </SubmitButton>
                      </ActionForm>
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader title="Novo usuário" icon={UserPlus} />
            <ActionForm action={createUserAction} className="space-y-4 p-5">
              <div>
                <label htmlFor="name" className="label">Nome</label>
                <input id="name" name="name" required maxLength={80} className="input" />
              </div>
              <div>
                <label htmlFor="email" className="label">E-mail</label>
                <input id="email" name="email" type="email" required className="input" />
              </div>
              <div>
                <label htmlFor="role" className="label">Perfil</label>
                <select id="role" name="role" defaultValue="editor" className="input">
                  {ROLES.map((r) => (
                    <option key={r} value={r}>{ROLE_LABEL[r]}</option>
                  ))}
                </select>
              </div>
              <SubmitButton className="btn btn-primary w-full" pendingText="Cadastrando…">Cadastrar e gerar senha</SubmitButton>
            </ActionForm>
          </Card>

          <Card>
            <CardHeader title="Perfis" icon={ShieldCheck} />
            <dl className="space-y-3 p-5 text-sm">
              {ROLES.map((r) => (
                <div key={r}>
                  <dt className="font-semibold">{ROLE_LABEL[r]}</dt>
                  <dd className="text-muted">{ROLE_DESCRIPTION[r]}</dd>
                </div>
              ))}
            </dl>
          </Card>
        </div>
      </div>
    </>
  );
}
