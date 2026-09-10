"use client";

import { ShieldCheck } from "lucide-react";
import { useState, useTransition } from "react";
import { ActionForm, SubmitButton } from "@/components/forms";
import { confirmTotpAction, startTotpSetupAction } from "./actions";

export function TotpSetup() {
  const [setup, setSetup] = useState<{ qr: string; secret: string } | null>(null);
  const [error, setError] = useState<string>();
  const [pending, startTransition] = useTransition();

  if (!setup) {
    return (
      <div>
        <button
          type="button"
          className="btn btn-primary"
          disabled={pending}
          onClick={() =>
            startTransition(async () => {
              const result = await startTotpSetupAction();
              if (result.qr && result.secret) setSetup({ qr: result.qr, secret: result.secret });
              else setError(result.error);
            })
          }
        >
          <ShieldCheck className="size-4" />
          {pending ? "Preparando…" : "Ativar verificação em 2 etapas"}
        </button>
        {error && <p className="mt-2 text-sm text-danger">{error}</p>}
      </div>
    );
  }

  return (
    <div className="grid gap-6 sm:grid-cols-[auto_1fr]">
      {/* eslint-disable-next-line @next/next/no-img-element -- QR code gerado no servidor como data URL */}
      <img src={setup.qr} alt="QR code para o app autenticador" width={208} height={208} className="rounded-lg bg-white p-1" />
      <div className="space-y-4 text-sm">
        <ol className="list-decimal space-y-1.5 pl-4 text-muted">
          <li>Abra o Google Authenticator, Microsoft Authenticator ou 1Password.</li>
          <li>Leia o QR code ao lado.</li>
          <li>Digite o código de 6 dígitos que aparecer.</li>
        </ol>
        <div>
          <p className="text-xs text-dim">Não consegue ler? Digite esta chave no app:</p>
          <code className="mt-1 block font-mono text-sm break-all text-ink">{setup.secret}</code>
        </div>
        <ActionForm action={confirmTotpAction} className="max-w-xs">
          <label htmlFor="totp-code" className="label">Código do app</label>
          <div className="flex gap-2">
            <input
              id="totp-code"
              name="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={7}
              required
              className="input font-mono tracking-[0.3em]"
              placeholder="000000"
            />
            <SubmitButton pendingText="…">Confirmar</SubmitButton>
          </div>
        </ActionForm>
      </div>
    </div>
  );
}
