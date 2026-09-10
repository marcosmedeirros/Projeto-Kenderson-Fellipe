import { KeyRound, Monitor, ShieldCheck, UserRound } from "lucide-react";
import type { Metadata } from "next";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, KeyValue, PageHeader } from "@/components/ui";
import { getCurrentSession, requireUser } from "@/lib/auth/dal";
import { ROLE_DESCRIPTION, ROLE_LABEL } from "@/lib/auth/permissions";
import { listSessions } from "@/lib/auth/session";
import { fmtAgo, fmtFullDate } from "@/lib/format";
import { describeUserAgent } from "@/lib/user-agent";
import { changePasswordAction, disableTotpAction, revokeOtherSessionsAction, revokeSessionAction } from "./actions";
import { TotpSetup } from "./totp-setup";

export const metadata: Metadata = { title: "Minha conta" };

export default async function AccountPage() {
  const user = await requireUser();
  const [session, sessions] = await Promise.all([getCurrentSession(), listSessions(user.id)]);

  return (
    <>
      <PageHeader eyebrow="Conta" title="Minha conta" description="Seus dados de acesso, senha, verificação em 2 etapas e aparelhos conectados." />

      <div className="grid gap-6 xl:grid-cols-2">
        <Card>
          <CardHeader title="Perfil" icon={UserRound} />
          <div className="p-5">
            <KeyValue
              items={[
                { label: "Nome", value: user.name },
                { label: "E-mail", value: user.email },
                { label: "Perfil", value: <><span className="font-semibold">{ROLE_LABEL[user.role]}</span> <span className="text-muted">· {ROLE_DESCRIPTION[user.role]}</span></> },
              ]}
            />
          </div>
        </Card>

        <Card>
          <CardHeader title="Trocar senha" icon={KeyRound} />
          <ActionForm action={changePasswordAction} className="grid gap-4 p-5 sm:grid-cols-3">
            <div>
              <label htmlFor="current" className="label">Senha atual</label>
              <input id="current" name="current" type="password" autoComplete="current-password" required className="input" />
            </div>
            <div>
              <label htmlFor="password" className="label">Nova senha</label>
              <input id="password" name="password" type="password" autoComplete="new-password" minLength={10} required className="input" />
            </div>
            <div>
              <label htmlFor="confirm" className="label">Confirmar</label>
              <input id="confirm" name="confirm" type="password" autoComplete="new-password" minLength={10} required className="input" />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 sm:col-span-3">
              <p className="text-xs text-muted">10+ caracteres, com letras e números. Os outros aparelhos serão desconectados.</p>
              <SubmitButton className="btn btn-secondary" pendingText="Salvando…">Trocar senha</SubmitButton>
            </div>
          </ActionForm>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader
          title="Verificação em 2 etapas"
          description="Além da senha, pede um código do app autenticador no celular"
          icon={ShieldCheck}
          action={user.totpEnabled ? <Badge tone="ok">Ativa</Badge> : <Badge tone="warn">Desativada</Badge>}
        />
        <div className="p-5">
          {user.totpEnabled ? (
            <ActionForm action={disableTotpAction} className="flex max-w-lg flex-wrap items-end gap-3">
              <div className="min-w-[220px] flex-1">
                <label htmlFor="disable-password" className="label">Para desativar, confirme sua senha</label>
                <input id="disable-password" name="password" type="password" autoComplete="current-password" required className="input" />
              </div>
              <SubmitButton className="btn btn-danger" pendingText="Desativando…">Desativar</SubmitButton>
            </ActionForm>
          ) : (
            <>
              <p className="mb-4 max-w-2xl text-sm text-muted">
                Recomendado para todos, principalmente administradores. Mesmo que alguém descubra sua senha, não consegue entrar sem o seu celular.
              </p>
              <TotpSetup />
            </>
          )}
        </div>
      </Card>

      <Card className="mt-6">
        <CardHeader
          title="Aparelhos conectados"
          description="Sessões expiram após 12 horas sem uso ou 7 dias no total"
          icon={Monitor}
          action={
            sessions.length > 1 && (
              <form action={revokeOtherSessionsAction}>
                <SubmitButton className="btn btn-ghost btn-sm">Encerrar as outras</SubmitButton>
              </form>
            )
          }
        />
        <ul className="divide-y divide-line">
          {sessions.map((s) => {
            const current = s.id === session?.id;
            return (
              <li key={s.id} className="flex flex-wrap items-center gap-4 px-5 py-3.5">
                <Monitor className="size-4 text-dim" />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold">
                    {describeUserAgent(s.userAgent)} {current && <Badge tone="ok" className="ml-1">Este aparelho</Badge>}
                  </p>
                  <p className="text-xs text-muted">
                    IP {s.ip ?? "—"} · entrou em {fmtFullDate(s.createdAt)} · ativo {fmtAgo(s.lastSeenAt)}
                  </p>
                </div>
                {!current && (
                  <form action={revokeSessionAction}>
                    <input type="hidden" name="sessionId" value={s.id} />
                    <SubmitButton className="btn btn-danger btn-sm">Encerrar</SubmitButton>
                  </form>
                )}
              </li>
            );
          })}
        </ul>
      </Card>
    </>
  );
}
