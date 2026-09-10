"use client";

import { ArrowLeft, CircleAlert, LockKeyhole, Smartphone } from "lucide-react";
import { useActionState } from "react";
import { SubmitButton } from "@/components/forms";
import { cancelTwoFactorAction, loginAction, verifyTwoFactorAction } from "./actions";

function ErrorMessage({ children }: { children?: string }) {
  if (!children) return null;
  return (
    <p className="flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/[0.07] px-3 py-2.5 text-sm text-danger" role="alert">
      <CircleAlert className="mt-0.5 size-4 shrink-0" />
      {children}
    </p>
  );
}

function TwoFactorStep() {
  const [state, action] = useActionState(verifyTwoFactorAction, undefined);
  return (
    <div>
      <span className="grid size-11 place-items-center rounded-xl bg-accent/12 text-accent">
        <Smartphone className="size-5" />
      </span>
      <h2 className="display mt-5 text-[32px] leading-none font-extrabold">Verificação em 2 etapas</h2>
      <p className="mt-2 text-sm text-muted">Digite o código de 6 dígitos que aparece no seu app autenticador.</p>

      {state?.expired ? (
        <div className="mt-6 space-y-4">
          <ErrorMessage>{state.error}</ErrorMessage>
          <form action={cancelTwoFactorAction}>
            <button type="submit" className="btn btn-secondary w-full">Voltar para o login</button>
          </form>
        </div>
      ) : (
        <>
          <form action={action} className="mt-6 space-y-4">
            <div>
              <label htmlFor="code" className="label">Código</label>
              <input
                id="code"
                name="code"
                inputMode="numeric"
                autoComplete="one-time-code"
                pattern="[0-9 ]{6,7}"
                maxLength={7}
                required
                autoFocus
                className="input h-12 text-center font-mono text-2xl tracking-[0.4em]"
                placeholder="000000"
              />
            </div>
            <ErrorMessage>{state?.error}</ErrorMessage>
            <SubmitButton className="btn btn-primary h-11 w-full" pendingText="Verificando…">Confirmar</SubmitButton>
          </form>
          <form action={cancelTwoFactorAction} className="mt-3">
            <button type="submit" className="btn btn-ghost w-full">
              <ArrowLeft className="size-4" />
              Usar outra conta
            </button>
          </form>
        </>
      )}
    </div>
  );
}

export function LoginForm({ initialStep }: { initialStep: "senha" | "2fa" }) {
  const [state, action] = useActionState(loginAction, undefined);

  if (initialStep === "2fa" || state?.step === "2fa") return <TwoFactorStep />;

  return (
    <div>
      <span className="grid size-11 place-items-center rounded-xl bg-panel-2 text-muted">
        <LockKeyhole className="size-5" />
      </span>
      <h2 className="display mt-5 text-[36px] leading-none font-extrabold">Entrar no painel</h2>
      <p className="mt-2 text-sm text-muted">Use o e-mail e a senha que o administrador cadastrou para você.</p>

      <form action={action} className="mt-7 space-y-4">
        <div>
          <label htmlFor="email" className="label">E-mail</label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            required
            autoFocus
            defaultValue={state?.email}
            className="input h-11"
          />
        </div>
        <div>
          <label htmlFor="password" className="label">Senha</label>
          <input id="password" name="password" type="password" autoComplete="current-password" required className="input h-11" />
        </div>
        <ErrorMessage>{state?.error}</ErrorMessage>
        <SubmitButton className="btn btn-primary h-11 w-full" pendingText="Entrando…">Entrar</SubmitButton>
      </form>

      <p className="mt-6 text-xs text-dim">
        Esqueceu a senha? Peça ao administrador para redefinir. Por segurança, o acesso é bloqueado por 15 minutos após 5 tentativas erradas.
      </p>
    </div>
  );
}
