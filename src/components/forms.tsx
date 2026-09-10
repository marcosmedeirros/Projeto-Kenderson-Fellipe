"use client";

import { Check, CircleAlert, Copy } from "lucide-react";
import { useActionState, useState } from "react";
import { useFormStatus } from "react-dom";
import type { FormState } from "@/lib/form-state";
import { cn } from "./ui";

export function SubmitButton({
  children,
  pendingText,
  className = "btn btn-primary",
  name,
  value,
}: {
  children: React.ReactNode;
  pendingText?: string;
  className?: string;
  name?: string;
  value?: string;
}) {
  const { pending } = useFormStatus();
  return (
    <button type="submit" disabled={pending} className={className} name={name} value={value} aria-busy={pending}>
      {pending && pendingText ? pendingText : children}
    </button>
  );
}

export function FormFeedback({ state }: { state: FormState }) {
  const [copied, setCopied] = useState(false);
  if (!state || (!state.message && !state.error && !state.secret)) return null;
  return (
    <div className="mt-3 space-y-2" aria-live="polite">
      {state.error && (
        <p className="flex items-start gap-2 text-sm text-danger">
          <CircleAlert className="mt-0.5 size-4 shrink-0" />
          {state.error}
        </p>
      )}
      {state.message && (
        <p className="flex items-start gap-2 text-sm text-accent">
          <Check className="mt-0.5 size-4 shrink-0" />
          {state.message}
        </p>
      )}
      {state.secret && (
        <div className="flex items-center gap-2 rounded-lg border border-warn/30 bg-warn/[0.07] p-2 pl-3">
          <code className="flex-1 font-mono text-sm break-all text-warn">{state.secret}</code>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={async () => {
              await navigator.clipboard.writeText(state.secret ?? "");
              setCopied(true);
            }}
          >
            {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
            {copied ? "Copiada" : "Copiar"}
          </button>
        </div>
      )}
    </div>
  );
}

/** Formulário ligado a uma Server Action que devolve mensagem de sucesso ou erro. */
export function ActionForm({
  action,
  children,
  className,
}: {
  action: (state: FormState, formData: FormData) => Promise<FormState>;
  children: React.ReactNode;
  className?: string;
}) {
  const [state, formAction] = useActionState(action, undefined);
  return (
    <form action={formAction} className={cn(className)}>
      {children}
      <FormFeedback state={state} />
    </form>
  );
}
