import { ArrowRight, Bot, CalendarClock, Eye, Flame, ImageOff, Lightbulb, ShieldAlert, TriangleAlert, UserPlus } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { AreaChart } from "@/components/charts";
import { StockTimeline } from "@/components/stock-timeline";
import { Badge, Card, CardHeader, EmptyState, Notice, PageHeader, Stat } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import {
  getDailySeries,
  getIdeas,
  getMonthToDate,
  getRecentBotMessages,
  getScheduledVideos,
  getViralVideos,
  summarizeStock,
} from "@/lib/channel/queries";
import { daysUntil, fmtAgo, fmtCompact, fmtDate, fmtInDays, fmtLongDay, fmtPercent, fmtTime, fmtWeekday } from "@/lib/format";
import { getSetting } from "@/lib/settings";

export const metadata: Metadata = { title: "Visão geral" };

export default async function OverviewPage({ searchParams }: PageProps<"/">) {
  const user = await requireUser();
  const params = await searchParams;
  const [scheduled, alertas, mtd, series, approved, messages] = await Promise.all([
    getScheduledVideos(),
    getSetting("alertas"),
    getMonthToDate(),
    getDailySeries({ days: 30 }),
    getIdeas(["aprovada"]),
    getRecentBotMessages(6),
  ]);
  const virais = await getViralVideos(alertas.viralMultiplicador);
  const stock = summarizeStock(scheduled);
  const lowStock = stock.daysCovered < alertas.estoqueMinimoDias;
  const subscribers30d = series.reduce((sum, d) => sum + d.subscribersGained, 0);
  const hours30d = Math.round(series.reduce((sum, d) => sum + d.watchMinutes, 0) / 60);

  return (
    <>
      {params.acesso === "negado" && (
        <Notice tone="danger" icon={ShieldAlert} title="Você não tem permissão para acessar aquela página." className="mb-6">
          Se precisar desse acesso, fale com o administrador do painel.
        </Notice>
      )}

      <PageHeader
        eyebrow={fmtLongDay(new Date())}
        title={`Olá, ${user.name.split(" ")[0]}`}
        description="A situação do canal agora: estoque de vídeos, capas pendentes e o que está performando."
      />

      {lowStock && (
        <Notice
          tone="warn"
          icon={TriangleAlert}
          className="mb-6"
          title={
            stock.total
              ? `O estoque cobre só até ${fmtDate(stock.lastDate!)} (${stock.daysCovered} dias). Hora de gravar.`
              : "Nenhum vídeo programado. Hora de gravar."
          }
          action={
            <Link href="/programados" className="btn btn-secondary btn-sm">
              Ver programados <ArrowRight className="size-3.5" />
            </Link>
          }
        >
          O mínimo combinado é de {alertas.estoqueMinimoDias} dias de vídeos agendados.
        </Notice>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat
          label="Programados"
          icon={CalendarClock}
          value={stock.total}
          hint={stock.shorts ? `${stock.total - stock.shorts} vídeos · ${stock.shorts} Shorts` : "vídeos agendados"}
          href="/programados"
        />
        <Stat
          label="Estoque até"
          icon={CalendarClock}
          tone={lowStock ? "warn" : "neutral"}
          value={stock.lastDate ? fmtDate(stock.lastDate) : "—"}
          hint={stock.lastDate ? `${stock.daysCovered} dias · mínimo ${alertas.estoqueMinimoDias}` : "sem vídeos agendados"}
          href="/programados"
        />
        <Stat
          label="Sem capa"
          icon={ImageOff}
          tone={stock.missingThumbs ? "warn" : "neutral"}
          value={stock.missingThumbs}
          hint={
            stock.nextMissingThumb
              ? `a próxima publica ${fmtInDays(daysUntil(stock.nextMissingThumb.publishAt!))}`
              : "todas as capas prontas"
          }
          href="/capas"
        />
        <Stat
          label="Views no mês"
          icon={Eye}
          value={fmtCompact(mtd.views)}
          hint={
            mtd.change == null ? (
              "mês em andamento"
            ) : (
              <span className={mtd.change >= 0 ? "text-accent" : "text-danger"}>
                {mtd.change >= 0 ? "▲" : "▼"} {fmtPercent(Math.abs(mtd.change))}{" "}
                <span className="text-muted">vs mesmo período do mês passado</span>
              </span>
            )
          }
          href="/desempenho"
        />
      </div>

      <Card className="mt-6">
        <CardHeader
          title="Estoque dos próximos 30 dias"
          description="Cada bloco é um vídeo agendado. Passe o mouse para ver o título."
          icon={CalendarClock}
          action={
            <Link href="/programados" className="btn btn-ghost btn-sm">
              Detalhes <ArrowRight className="size-3.5" />
            </Link>
          }
        />
        <div className="p-5">
          <StockTimeline videos={scheduled} minimumDays={alertas.estoqueMinimoDias} />
        </div>
      </Card>

      <div className="mt-6 grid gap-6 xl:grid-cols-[1.25fr_1fr]">
        <Card>
          <CardHeader title="Próximos lançamentos" icon={CalendarClock} />
          {scheduled.length ? (
            <ul className="divide-y divide-line">
              {scheduled.slice(0, 6).map((v) => (
                <li key={v.id} className="flex items-center gap-4 px-5 py-3">
                  <div className="w-14 shrink-0 text-center">
                    <div className="font-mono text-[10px] text-dim uppercase">{fmtWeekday(v.publishAt!)}</div>
                    <div className="display text-xl leading-tight font-extrabold tabular-nums">{fmtDate(v.publishAt!)}</div>
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{v.title}</p>
                    <p className="mt-0.5 text-xs text-muted">
                      {fmtTime(v.publishAt!)} · publica {fmtInDays(daysUntil(v.publishAt!))}
                      {v.isShort && " · Short"}
                    </p>
                  </div>
                  {v.thumbnailStatus === "ok" ? <Badge tone="ok">Capa ok</Badge> : <Badge tone="warn">Sem capa</Badge>}
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState icon={CalendarClock} title="Nenhum vídeo agendado" description="Quando houver vídeos programados no YouTube, eles aparecem aqui." />
          )}
        </Card>

        <Card>
          <CardHeader title="Views · últimos 30 dias" icon={Eye} />
          <div className="p-5">
            <AreaChart data={series.map((d) => ({ label: fmtDate(new Date(`${d.day}T12:00:00-03:00`)), value: d.views }))} formatValue={fmtCompact} />
            <div className="mt-5 grid grid-cols-3 gap-3 border-t border-line pt-4">
              <div>
                <p className="eyebrow">Views</p>
                <p className="display mt-1 text-2xl font-extrabold">{fmtCompact(series.reduce((s, d) => s + d.views, 0))}</p>
              </div>
              <div>
                <p className="eyebrow">Horas</p>
                <p className="display mt-1 text-2xl font-extrabold">{fmtCompact(hours30d)}</p>
              </div>
              <div>
                <p className="eyebrow flex items-center gap-1"><UserPlus className="size-3" />Inscritos</p>
                <p className="display mt-1 text-2xl font-extrabold">+{fmtCompact(subscribers30d)}</p>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <Card>
          <CardHeader
            title="Em alta"
            description={`Acima de ${alertas.viralMultiplicador}x a média em 7 dias`}
            icon={Flame}
            action={<Link href="/desempenho" className="btn btn-ghost btn-sm">Ver tudo</Link>}
          />
          {virais.items.length ? (
            <ul className="divide-y divide-line">
              {virais.items.slice(0, 4).map((v) => (
                <li key={v.id} className="flex items-center gap-3 px-5 py-3">
                  <span className="display w-14 shrink-0 text-xl font-extrabold text-warn tabular-nums">
                    {v.ratio.toFixed(1).replace(".", ",")}x
                  </span>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">{v.title}</p>
                    <p className="text-xs text-muted">
                      {fmtCompact(v.views7d ?? 0)} views em 7 dias{v.isShort && " · Short"}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState icon={Flame} title="Nenhum vídeo acima da média" description="Nos últimos 45 dias nenhum vídeo passou do multiplicador configurado." />
          )}
        </Card>

        <Card>
          <CardHeader
            title="Ideias aprovadas"
            icon={Lightbulb}
            action={<Link href="/ideias" className="btn btn-ghost btn-sm">Ver ideias</Link>}
          />
          {approved.length ? (
            <ul className="divide-y divide-line">
              {approved.slice(0, 4).map((idea) => (
                <li key={idea.id} className="px-5 py-3">
                  <p className="text-sm font-semibold">{idea.title}</p>
                  <p className="mt-0.5 line-clamp-2 text-xs text-muted">{idea.rationale}</p>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState icon={Lightbulb} title="Nenhuma ideia aprovada" description="Aprove ideias na página de pautas para elas aparecerem aqui." />
          )}
        </Card>

        <Card className="lg:col-span-2 xl:col-span-1">
          <CardHeader title="Bot no WhatsApp" description="Atividade recente" icon={Bot} action={<Link href="/bot" className="btn btn-ghost btn-sm">Abrir</Link>} />
          {messages.length ? (
            <ul className="divide-y divide-line">
              {messages.map((m) => (
                <li key={m.id} className="flex items-start gap-3 px-5 py-3">
                  <Badge tone={m.direction === "entrada" ? "info" : m.origin === "automacao" ? "warn" : "ok"} className="mt-0.5">
                    {m.direction === "entrada" ? m.sender ?? "Grupo" : m.origin === "automacao" ? "Alerta" : "Bot"}
                  </Badge>
                  <p className="min-w-0 flex-1 truncate text-sm text-muted">{m.text.split("\n")[0].replaceAll("*", "")}</p>
                  <span className="shrink-0 font-mono text-[11px] text-dim">{fmtAgo(m.createdAt)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState icon={Bot} title="Sem atividade ainda" description="Os comandos enviados no grupo aparecem aqui." />
          )}
        </Card>
      </div>
    </>
  );
}
