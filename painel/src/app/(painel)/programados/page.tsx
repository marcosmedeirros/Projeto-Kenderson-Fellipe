import { CalendarClock, ExternalLink } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { StockTimeline } from "@/components/stock-timeline";
import { Badge, Card, CardHeader, cn, EmptyState, PageHeader } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { getScheduledVideos, summarizeStock } from "@/lib/channel/queries";
import { daysUntil, fmtDate, fmtDuration, fmtInDays, fmtTime, fmtWeekday } from "@/lib/format";
import { getSetting } from "@/lib/settings";

export const metadata: Metadata = { title: "Programados" };

const FILTERS = [
  { key: "todos", label: "Todos" },
  { key: "sem-capa", label: "Sem capa" },
  { key: "longos", label: "Vídeos" },
  { key: "shorts", label: "Shorts" },
] as const;

export default async function ScheduledPage({ searchParams }: PageProps<"/programados">) {
  await requireUser();
  const { filtro } = await searchParams;
  const active = FILTERS.find((f) => f.key === filtro)?.key ?? "todos";
  const [list, alertas] = await Promise.all([getScheduledVideos(), getSetting("alertas")]);
  const stock = summarizeStock(list);

  const filtered = list.filter((v) => {
    if (active === "sem-capa") return v.thumbnailStatus !== "ok";
    if (active === "longos") return !v.isShort;
    if (active === "shorts") return v.isShort;
    return true;
  });

  return (
    <>
      <PageHeader
        eyebrow="Canal"
        title="Vídeos programados"
        description={
          stock.total ? (
            <>
              {stock.total} agendados, cobrindo até <strong className="text-ink">{fmtDate(stock.lastDate!)}</strong> ({stock.daysCovered} dias).
              {stock.daysCovered < alertas.estoqueMinimoDias && (
                <span className="text-warn"> Abaixo do mínimo de {alertas.estoqueMinimoDias} dias.</span>
              )}
            </>
          ) : (
            "Nenhum vídeo agendado no momento."
          )
        }
      />

      <Card>
        <CardHeader title="Linha do tempo · 45 dias" icon={CalendarClock} />
        <div className="p-5">
          <StockTimeline videos={list} days={45} minimumDays={alertas.estoqueMinimoDias} />
        </div>
      </Card>

      <Card className="mt-6">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
          <nav className="flex flex-wrap gap-1" aria-label="Filtrar vídeos">
            {FILTERS.map((f) => (
              <Link
                key={f.key}
                href={f.key === "todos" ? "/programados" : `/programados?filtro=${f.key}`}
                className={cn("btn btn-sm", active === f.key ? "bg-panel-3 text-ink" : "btn-ghost")}
                aria-current={active === f.key ? "page" : undefined}
              >
                {f.label}
              </Link>
            ))}
          </nav>
          <span className="font-mono text-xs text-dim">{filtered.length} de {list.length}</span>
        </div>

        {filtered.length ? (
          <div className="overflow-x-auto">
            <table className="table-base min-w-[720px]">
              <thead>
                <tr>
                  <th>Publicação</th>
                  <th>Vídeo</th>
                  <th>Duração</th>
                  <th>Capa</th>
                  <th className="text-right">Studio</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((v) => {
                  const days = daysUntil(v.publishAt!);
                  const demo = v.id.startsWith("demo-");
                  return (
                    <tr key={v.id} className="hover:bg-panel-2/50">
                      <td className="whitespace-nowrap">
                        <span className="font-semibold tabular-nums">{fmtWeekday(v.publishAt!)} {fmtDate(v.publishAt!)}</span>
                        <span className="ml-2 font-mono text-xs text-muted">{fmtTime(v.publishAt!)}</span>
                        <div className={cn("text-xs", days <= 3 ? "text-warn" : "text-dim")}>{fmtInDays(days)}</div>
                      </td>
                      <td>
                        <div className="flex items-center gap-2">
                          <span className="font-semibold">{v.title}</span>
                          {v.isShort && <Badge tone="info">Short</Badge>}
                        </div>
                      </td>
                      <td className="font-mono text-xs text-muted tabular-nums">{fmtDuration(v.durationSec)}</td>
                      <td>{v.thumbnailStatus === "ok" ? <Badge tone="ok">Capa ok</Badge> : <Badge tone="warn">Sem capa</Badge>}</td>
                      <td className="text-right">
                        {demo ? (
                          <span className="text-xs text-dim">exemplo</span>
                        ) : (
                          <a
                            href={`https://studio.youtube.com/video/${encodeURIComponent(v.id)}/edit`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn btn-ghost btn-sm"
                          >
                            Abrir <ExternalLink className="size-3.5" />
                          </a>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={CalendarClock} title="Nenhum vídeo neste filtro" />
        )}
      </Card>
    </>
  );
}
