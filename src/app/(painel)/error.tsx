"use client";

import { TriangleAlert } from "lucide-react";

export default function PainelError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="card mx-auto mt-10 max-w-lg p-8 text-center">
      <span className="mx-auto grid size-11 place-items-center rounded-xl bg-danger/12 text-danger">
        <TriangleAlert className="size-5" />
      </span>
      <h1 className="display mt-4 text-3xl font-extrabold">Algo deu errado</h1>
      <p className="mt-2 text-sm text-muted">Não foi possível carregar esta página. Tente de novo; se continuar, avise o administrador.</p>
      <button type="button" onClick={reset} className="btn btn-primary mt-6">Tentar de novo</button>
    </div>
  );
}
