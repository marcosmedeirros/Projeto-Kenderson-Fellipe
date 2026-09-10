import { KeyRound } from "lucide-react";
import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { ActionForm, SubmitButton } from "@/components/forms";
import { BrandMark } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { changePasswordAction } from "../(painel)/conta/actions";

export const metadata: Metadata = { title: "Primeiro acesso" };

export default async function FirstAccessPage() {
  const user = await requireUser({ allowPasswordChange: true });
  if (!user.mustChangePassword) redirect("/");

  return (
    <main className="flex min-h-screen items-center justify-center px-5 py-12">
      <div className="w-full max-w-md">
        <div className="mb-8 flex items-center gap-3">
          <BrandMark className="size-9" />
          <span className="display text-xl font-extrabold">Controladoria</span>
        </div>
        <div className="card p-6 sm:p-8">
          <span className="grid size-11 place-items-center rounded-xl bg-warn/12 text-warn">
            <KeyRound className="size-5" />
          </span>
          <h1 className="display mt-5 text-[32px] leading-none font-extrabold">Crie sua senha</h1>
          <p className="mt-2 text-sm text-muted">
            Olá, {user.name.split(" ")[0]}. Você entrou com uma senha temporária. Antes de continuar, defina uma senha só sua.
          </p>

          <ActionForm action={changePasswordAction} className="mt-6 space-y-4">
            <div>
              <label htmlFor="current" className="label">Senha temporária</label>
              <input id="current" name="current" type="password" autoComplete="current-password" required className="input h-11" />
            </div>
            <div>
              <label htmlFor="password" className="label">Nova senha</label>
              <input id="password" name="password" type="password" autoComplete="new-password" minLength={10} required className="input h-11" />
              <p className="hint">Pelo menos 10 caracteres, com letras e números.</p>
            </div>
            <div>
              <label htmlFor="confirm" className="label">Confirme a nova senha</label>
              <input id="confirm" name="confirm" type="password" autoComplete="new-password" minLength={10} required className="input h-11" />
            </div>
            <SubmitButton className="btn btn-primary h-11 w-full" pendingText="Salvando…">Salvar e entrar</SubmitButton>
          </ActionForm>
        </div>
      </div>
    </main>
  );
}
