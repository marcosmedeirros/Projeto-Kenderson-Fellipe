import { ChevronLeft, ChevronRight, Clock, Eye, Flame, Info, Percent, Search, TrendingUp, UserPlus } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { AreaChart, BarList } from "@/components/charts";
import { Badge, Card, CardHeader, EmptyState, PageHeader, Stat } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { getDailySeries, getMonthRetention, getMonthTotals, getRisingTerms, getTopVideos, getViralVideos } from "@/lib/channel/queries";
import { fmtCompact, fmtDate, fmtNumber, monthKey, monthLabel, shiftMonth } from "@/lib/format";
import { getSetting } from "@/lib/settings";

export const metadata: Metadata = { title: "Desempenho" };

export default async function PerformancePage({ searchParams }: PageProps<"/desempenho">) {
  await requireUser();
  const { mes } = await searchParams;
  const current = monthKey();
  const month = typeof mes === "string" && /^\d{4}-(0[1-9]|1[0-2])$/.test(mes) && mes <= current ? mes : current;

  const [totals, previous, series, top, retention, rising, alertas] = await Promise.all([
    getMonthTotals(month),
    getMonthTotals(shiftMonth(month, -1)),
    getDailySeries({ month }),
    getTopVideos(month, 8),
    getMonthRetention(month),
    getRisingTerms(month, 8),
    getSetting("alertas"),
  ]);
  const virais = await getViralVideos(alertas.viralMultiplicador);
  const delta = (a: number, b: number) => (b > 0 && month !== current ? `${a >= b ? "▲" : "▼"} ${Math.abs(Math.round(((a - b) / b) * 100))}% vs mês anterior` : undefined);

  return (
    <>
      <PageHeader
        eyebrow="Canal"
        title="Desempenho"
        description="Views, retenção, vídeos em alta e o que as pessoas buscam para chegar ao canal."
        actions={
          <div className="flex items-center gap-1 rounded-lg border border-line bg-panel p-1">
            <Link href={`/desempenho?mes=${shiftMonth(month, -1)}`} className="btn btn-ghost btn-sm px-2" aria-label="Mês anterior">
              <ChevronLeft className="size-4" />
            </Link>
            <span className="min-w-[150px] text-center text-sm font-semibold">{monthLabel(month)}</span>
            {month < current ? (
              <Link href={`/desempenho?mes=${shiftMonth(month, 1)}`} className="btn btn-ghost btn-sm px-2" aria-label="Próximo mês">
                <ChevronRight className="size-4" />
              </Link>
            ) : (
              <span className="btn btn-sm px-2 text-dim opacity-40"><ChevronRight className="size-4" /></span>
            )}
          </div>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Views" icon={Eye} value={fmtCompact(totals.views)} hint={delta(totals.views, previous.views) ?? `${totals.days} dias com dados`} />
        <Stat label="Horas assistidas" icon={Clock} value={fmtCompact(Math.round(totals.watchMinutes / 60))} hint={delta(totals.watchMinutes, previous.watchMinutes)} />
        <Stat label="Inscritos" icon={UserPlus} value={`+${fmtCompact(totals.subscribers)}`} hint={delta(totals.subscribers, previous.subscribers)} />
        <Stat label="Retenção média" icon={Percent} value={`${Math.round(retention)}%`} hint="ponderada pelas views" />
      </div>

      <Card className="mt-6">
        <CardHeader title={`Views por dia · ${monthLabel(month).toLowerCase()}`} icon={TrendingUp} />
        <div className="p-5">
          <AreaChart data={series.map((d) => ({ label: fmtDate(new Date(`${d.day}T12:00:00-03:00`)), value: d.views }))} formatValue={fmtCompact} height={200} />
        </div>
      </Card>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card>
          <CardHeader title="Mais vistos no mês" icon={Eye} />
          <div className="p-5">
            {top.length ? (
              <BarList
                format={fmtCompact}
                items={top.map((v) => ({
                  key: v.id,
                  label: (
                    <>
                      {v.title} {v.isShort && <Badge tone="info" className="ml-1">Short</Badge>}
                    </>
                  ),
                  value: v.views,
                  meta: `Retenção ${Math.round(v.avgViewPct)}% · +${fmtNumber(v.subscribersGained)} inscritos`,
                }))}
              />
            ) : (
              <EmptyState icon={Eye} title="Sem dados neste mês" />
            )}
          </div>
        </Card>

        <Card>
          <CardHeader title="O que o público buscou" description="Termos de busca do YouTube que trouxeram views" icon={Search} />
          <div className="p-5">
            {rising.length ? (
              <BarList
                format={fmtCompact}
                items={rising.map((t) => ({
                  key: t.term,
                  label: t.term,
                  value: t.views,
                  tone: t.growth != null && t.growth > 0.3 ? "warn" : "info",
                  meta: t.growth == null ? "novo neste mês" : `${t.growth >= 0 ? "▲" : "▼"} ${Math.abs(Math.round(t.growth * 100))}% vs mês anterior`,
                }))}
              />
            ) : (
              <EmptyState icon={Search} title="Sem termos de busca neste mês" />
            )}
          </div>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader
          title="Vídeos em alta"
          description={`Views nos 7 primeiros dias acima de ${alertas.viralMultiplicador}x a mediana do canal (últimos 45 dias)`}
          icon={Flame}
        />
        {virais.recent.length ? (
          <div className="overflow-x-auto">
            <table className="table-base min-w-[680px]">
              <thead>
                <tr>
                  <th>Vídeo</th>
                  <th>Publicado</th>
                  <th className="text-right">Views em 7 dias</th>
                  <th className="text-right">Total</th>
                  <th className="text-right">vs média</th>
                </tr>
              </thead>
              <tbody>
                {virais.recent.slice(0, 12).map((v) => (
                  <tr key={v.id}>
                    <td>
                      <span className="font-semibold">{v.title}</span>
                      {v.isShort && <Badge tone="info" className="ml-2">Short</Badge>}
                    </td>
                    <td className="font-mono text-xs text-muted">{v.publishedAt ? fmtDate(v.publishedAt) : "—"}</td>
                    <td className="text-right tabular-nums">{fmtNumber(v.views7d ?? 0)}</td>
                    <td className="text-right text-muted tabular-nums">{fmtCompact(v.views)}</td>
                    <td className="text-right">
                      <Badge tone={v.ratio >= alertas.viralMultiplicador ? "warn" : v.ratio >= 1 ? "ok" : "neutral"}>
                        {v.ratio >= alertas.viralMultiplicador && <Flame className="size-3" />}
                        {v.ratio.toFixed(1).replace(".", ",")}x
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={Flame} title="Nenhum vídeo publicado recentemente" />
        )}
        <p className="flex items-center gap-2 border-t border-line px-5 py-3 text-xs text-dim">
          <Info className="size-3.5" />
          Mediana atual: {fmtCompact(virais.medians.longos)} views para vídeos e {fmtCompact(virais.medians.shorts)} para Shorts. Os números do YouTube chegam com 2 a 3 dias de atraso.
        </p>
      </Card>
    </>
  );
}
