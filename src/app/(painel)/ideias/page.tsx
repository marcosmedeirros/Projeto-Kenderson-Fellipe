import { Check, Clapperboard, Lightbulb, Plus, RotateCcw, Search, Sparkles, X } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { BarList } from "@/components/charts";
import { ActionForm, SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, cn, EmptyState, PageHeader } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { can } from "@/lib/auth/permissions";
import { getIdeaCounts, getIdeas, getRisingTerms, type IdeaStatus } from "@/lib/channel/queries";
import { fmtAgo, fmtCompact, monthKey } from "@/lib/format";
import { getGeminiConfig } from "@/lib/integrations";
import { createIdeaAction, generateIdeasAction, setIdeaStatusAction } from "./actions";

export const metadata: Metadata = { title: "Ideias" };

const TABS: { key: IdeaStatus | "todas"; label: string }[] = [
  { key: "nova", label: "Novas" },
  { key: "aprovada", label: "Aprovadas" },
  { key: "gravada", label: "Gravadas" },
  { key: "descartada", label: "Descartadas" },
  { key: "todas", label: "Todas" },
];

const SOURCE_LABEL = { buscas: "Buscas", comentarios: "Comentários", desempenho: "Desempenho", manual: "Manual" } as const;

const NEXT_ACTIONS: Record<IdeaStatus, { status: IdeaStatus; label: string; icon: typeof Check; primary?: boolean }[]> = {
  nova: [
    { status: "aprovada", label: "Aprovar", icon: Check, primary: true },
    { status: "descartada", label: "Descartar", icon: X },
  ],
  aprovada: [
    { status: "gravada", label: "Marcar como gravada", icon: Clapperboard, primary: true },
    { status: "nova", label: "Voltar para novas", icon: RotateCcw },
  ],
  gravada: [{ status: "aprovada", label: "Reabrir", icon: RotateCcw }],
  descartada: [{ status: "nova", label: "Restaurar", icon: RotateCcw }],
};

export default async function IdeasPage({ searchParams }: PageProps<"/ideias">) {
  const user = await requireUser();
  const canOperate = can(user.role, "operar");
  const { status } = await searchParams;
  const tab = TABS.find((t) => t.key === status)?.key ?? "nova";

  const [list, counts, rising, gemini] = await Promise.all([
    getIdeas(tab === "todas" ? undefined : [tab]),
    getIdeaCounts(),
    getRisingTerms(monthKey(), 8),
    getGeminiConfig(),
  ]);
  const total = Object.values(counts).reduce((a, b) => a + b, 0);

  return (
    <>
      <PageHeader
        eyebrow="Pauta"
        title="Ideias"
        description="Sugestões de vídeo baseadas no que o público busca e no que performou bem."
        actions={
          canOperate && (
            <ActionForm action={generateIdeasAction} className="flex flex-col items-end">
              <SubmitButton pendingText="Gerando ideias…">
                <Sparkles className="size-4" />
                {gemini ? "Gerar ideias com IA" : "Gerar ideias de exemplo"}
              </SubmitButton>
            </ActionForm>
          )
        }
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div className="min-w-0">
          <nav className="mb-4 flex flex-wrap gap-1" aria-label="Filtrar por status">
            {TABS.map((t) => {
              const n = t.key === "todas" ? total : counts[t.key];
              return (
                <Link
                  key={t.key}
                  href={`/ideias?status=${t.key}`}
                  className={cn("btn btn-sm", tab === t.key ? "bg-panel-3 text-ink" : "btn-ghost")}
                  aria-current={tab === t.key ? "page" : undefined}
                >
                  {t.label}
                  <span className="font-mono text-[11px] text-dim">{n}</span>
                </Link>
              );
            })}
          </nav>

          {list.length ? (
            <ul className="space-y-3">
              {list.map((idea) => (
                <li key={idea.id} className="card flex gap-4 p-4 sm:p-5">
                  <div
                    className={cn(
                      "grid size-12 shrink-0 place-items-center rounded-xl font-display text-lg font-extrabold tabular-nums",
                      idea.score >= 75 ? "bg-accent/12 text-accent" : idea.score >= 50 ? "bg-info/12 text-info" : "bg-panel-3 text-muted",
                    )}
                    title="Potencial estimado (0 a 100)"
                  >
                    {idea.score}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <Badge tone="neutral">{SOURCE_LABEL[idea.source]}</Badge>
                      {idea.generatedBy === "ia" && <Badge tone="info"><Sparkles className="size-3" />IA</Badge>}
                      {idea.generatedBy === "exemplo" && <Badge tone="neutral">Exemplo</Badge>}
                      {tab === "todas" && <Badge tone={idea.status === "aprovada" ? "ok" : idea.status === "descartada" ? "danger" : "neutral"}>{idea.status}</Badge>}
                      <span className="font-mono text-[11px] text-dim">{fmtAgo(idea.createdAt)}</span>
                    </div>
                    <h3 className="mt-2 text-[15px] font-bold text-balance">{idea.title}</h3>
                    <p className="mt-1 text-sm text-muted">{idea.rationale}</p>
                    {canOperate && (
                      <form action={setIdeaStatusAction} className="mt-3 flex flex-wrap gap-2">
                        <input type="hidden" name="id" value={idea.id} />
                        {NEXT_ACTIONS[idea.status].map((a) => (
                          <SubmitButton key={a.status} name="status" value={a.status} className={cn("btn btn-sm", a.primary ? "btn-secondary" : "btn-ghost")}>
                            <a.icon className="size-3.5" />
                            {a.label}
                          </SubmitButton>
                        ))}
                      </form>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <Card>
              <EmptyState
                icon={Lightbulb}
                title="Nenhuma ideia aqui"
                description={tab === "nova" ? "Gere sugestões com o botão acima ou cadastre uma ideia manualmente." : "Nenhuma ideia com este status."}
              />
            </Card>
          )}
        </div>

        <div className="space-y-6">
          {canOperate && (
            <Card>
              <CardHeader title="Cadastrar ideia" icon={Plus} />
              <ActionForm action={createIdeaAction} className="space-y-3 p-5">
                <div>
                  <label htmlFor="title" className="label">Título</label>
                  <input id="title" name="title" required maxLength={140} className="input" placeholder="Ex.: Respondendo quem pediu parte 2" />
                </div>
                <div>
                  <label htmlFor="rationale" className="label">Por que vale gravar</label>
                  <textarea id="rationale" name="rationale" required maxLength={400} rows={3} className="input h-auto py-2" />
                </div>
                <SubmitButton className="btn btn-secondary w-full" pendingText="Salvando…">Salvar ideia</SubmitButton>
              </ActionForm>
            </Card>
          )}

          <Card>
            <CardHeader title="Buscas em alta" description="Termos que mais cresceram este mês" icon={Search} />
            <div className="p-5">
              {rising.length ? (
                <BarList
                  format={fmtCompact}
                  items={rising.map((t) => ({
                    key: t.term,
                    label: t.term,
                    value: t.views,
                    tone: "info",
                    meta: t.growth == null ? "novo neste mês" : `${t.growth >= 0 ? "▲" : "▼"} ${Math.abs(Math.round(t.growth * 100))}%`,
                  }))}
                />
              ) : (
                <p className="text-sm text-muted">Sem dados de busca ainda.</p>
              )}
            </div>
          </Card>
        </div>
      </div>
    </>
  );
}
