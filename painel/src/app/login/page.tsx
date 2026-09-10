import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { BrandMark } from "@/components/ui";
import { getCurrentSession } from "@/lib/auth/dal";
import { LoginForm } from "./login-form";

export const metadata: Metadata = { title: "Entrar" };

const CLIPS = [
  { left: 6, width: 9, tone: "bg-accent" },
  { left: 19, width: 7, tone: "bg-accent" },
  { left: 31, width: 8, tone: "bg-warn" },
  { left: 45, width: 10, tone: "bg-accent" },
  { left: 61, width: 6, tone: "bg-accent/50" },
];

export default async function LoginPage() {
  const session = await getCurrentSession();
  if (session && !session.twoFactorPending) redirect("/");

  return (
    <main className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
      <section className="relative hidden overflow-hidden border-r border-line bg-panel lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div
          className="pointer-events-none absolute inset-0 opacity-60"
          style={{ background: "radial-gradient(700px 380px at 85% 0%, rgba(45,184,155,.14), transparent 60%)" }}
        />
        <div className="relative flex items-center gap-3">
          <BrandMark className="size-9" />
          <span className="display text-xl font-extrabold">Controladoria</span>
        </div>

        <div className="relative">
          <p className="eyebrow mb-4">Canal Luiz Jordão</p>
          <h1 className="display max-w-md text-[56px] leading-[0.92] font-extrabold text-balance">
            O canal inteiro <span className="text-accent">num lugar só.</span>
          </h1>
          <p className="mt-5 max-w-md text-[17px] text-muted">
            Vídeos programados, capas, desempenho e ideias de pauta, integrados ao bot do WhatsApp.
          </p>

          <div className="mt-10 max-w-lg rounded-xl border border-line bg-bg/60 p-4" aria-hidden="true">
            <div className="mb-3 flex justify-between font-mono text-[10px] tracking-[0.1em] text-dim uppercase">
              <span>Estoque</span>
              <span>próximos 20 dias</span>
            </div>
            <div className="relative h-12 overflow-hidden rounded-md bg-panel-2">
              <div
                className="absolute inset-y-0 right-0 left-[72%]"
                style={{ backgroundImage: "repeating-linear-gradient(135deg, rgba(233,162,59,.18) 0 6px, transparent 6px 12px)" }}
              />
              {CLIPS.map((c) => (
                <div
                  key={c.left}
                  className={`absolute inset-y-2 rounded ${c.tone}`}
                  style={{ left: `${c.left}%`, width: `${c.width}%` }}
                />
              ))}
              <div className="absolute inset-y-0 left-[2%] w-0.5 bg-danger" />
              <div className="absolute inset-y-0 left-[72%] border-l-2 border-dashed border-warn" />
            </div>
          </div>
        </div>

        <p className="relative font-mono text-[11px] text-dim">Acesso restrito · conexão protegida</p>
      </section>

      <section className="flex items-center justify-center px-5 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <BrandMark className="size-9" />
            <span className="display text-xl font-extrabold">Controladoria</span>
          </div>
          <LoginForm initialStep={session?.twoFactorPending ? "2fa" : "senha"} />
        </div>
      </section>
    </main>
  );
}
