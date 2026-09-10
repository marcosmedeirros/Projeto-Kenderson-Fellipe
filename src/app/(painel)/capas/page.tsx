import { CircleCheck, ImageOff, Info, Undo2 } from "lucide-react";
import type { Metadata } from "next";
import { SubmitButton } from "@/components/forms";
import { Badge, Card, CardHeader, cn, EmptyState, PageHeader } from "@/components/ui";
import { requireUser } from "@/lib/auth/dal";
import { can } from "@/lib/auth/permissions";
import { getScheduledVideos, type ScheduledVideo } from "@/lib/channel/queries";
import { daysUntil, fmtAgo, fmtDate, fmtInDays, fmtTime, fmtWeekday } from "@/lib/format";
import { getSetting } from "@/lib/settings";
import { setThumbnailStatusAction } from "./actions";

export const metadata: Metadata = { title: "Capas" };

export default async function ThumbnailsPage() {
  const user = await requireUser();
  const canOperate = can(user.role, "operar");
  const [list, alertas] = await Promise.all([getScheduledVideos(), getSetting("alertas")]);

  const pending = list.filter((v) => v.thumbnailStatus !== "ok");
  const done = list.filter((v) => v.thumbnailStatus === "ok");
  const groups = [
    { key: "urgente", title: `Urgente · publica em até ${alertas.capaAvisoDias} dias`, tone: "danger" as const, items: pending.filter((v) => daysUntil(v.publishAt!) <= alertas.capaAvisoDias) },
    { key: "semana", title: "Nesta semana", tone: "warn" as const, items: pending.filter((v) => { const d = daysUntil(v.publishAt!); return d > alertas.capaAvisoDias && d <= 7; }) },
    { key: "depois", title: "Mais para frente", tone: "neutral" as const, items: pending.filter((v) => daysUntil(v.publishAt!) > 7) },
  ].filter((g) => g.items.length);

  const Row = ({ v, action }: { v: ScheduledVideo; action: React.ReactNode }) => {
    const days = daysUntil(v.publishAt!);
    return (
      <li className="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5">
        <div className="w-16 shrink-0">
          <div className="font-mono text-[10px] text-dim uppercase">{fmtWeekday(v.publishAt!)} {fmtTime(v.publishAt!)}</div>
          <div className="display text-xl leading-tight font-extrabold tabular-nums">{fmtDate(v.publishAt!)}</div>
        </div>
        <div className="min-w-0 flex-1">
          <p className="font-semibold">
            {v.title} {v.isShort && <Badge tone="info" className="ml-1 align-middle">Short</Badge>}
          </p>
          <p className="mt-0.5 text-xs text-muted">
            Publica {fmtInDays(days)}
            {v.thumbnailSource === "manual" && v.thumbnailUpdatedAt
              ? ` · marcado manualmente ${fmtAgo(v.thumbnailUpdatedAt)}`
              : " · verificado automaticamente"}
          </p>
        </div>
        {canOperate && action}
      </li>
    );
  };

  return (
    <>
      <PageHeader
        eyebrow="Canal"
        title="Capas"
        description={
          pending.length
            ? `${pending.length} ${pending.length === 1 ? "vídeo programado está" : "vídeos programados estão"} sem thumbnail.`
            : "Todos os vídeos programados já têm capa."
        }
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          {groups.length ? (
            groups.map((group) => (
              <Card key={group.key}>
                <div className="flex items-center justify-between border-b border-line px-5 py-3">
                  <h2 className={cn("text-sm font-bold", group.tone === "danger" ? "text-danger" : group.tone === "warn" ? "text-warn" : "text-ink")}>
                    {group.title}
                  </h2>
                  <Badge tone={group.tone}>{group.items.length}</Badge>
                </div>
                <ul className="divide-y divide-line">
                  {group.items.map((v) => (
                    <Row
                      key={v.id}
                      v={v}
                      action={
                        <form action={setThumbnailStatusAction}>
                          <input type="hidden" name="videoId" value={v.id} />
                          <input type="hidden" name="status" value="ok" />
                          <SubmitButton className="btn btn-primary btn-sm" pendingText="Salvando…">
                            <CircleCheck className="size-4" /> Marcar capa como feita
                          </SubmitButton>
                        </form>
                      }
                    />
                  ))}
                </ul>
              </Card>
            ))
          ) : (
            <Card>
              <EmptyState icon={CircleCheck} title="Nenhuma capa pendente" description="Quando um vídeo agendado estiver sem thumbnail, ele aparece aqui." />
            </Card>
          )}

          {done.length > 0 && (
            <Card>
              <details>
                <summary className="flex cursor-pointer list-none items-center justify-between px-5 py-3 text-sm font-bold">
                  Com capa pronta
                  <Badge tone="ok">{done.length}</Badge>
                </summary>
                <ul className="divide-y divide-line border-t border-line">
                  {done.map((v) => (
                    <Row
                      key={v.id}
                      v={v}
                      action={
                        v.thumbnailSource === "manual" ? (
                          <form action={setThumbnailStatusAction}>
                            <input type="hidden" name="videoId" value={v.id} />
                            <input type="hidden" name="status" value="pendente" />
                            <SubmitButton className="btn btn-ghost btn-sm" pendingText="Salvando…">
                              <Undo2 className="size-4" /> Desfazer
                            </SubmitButton>
                          </form>
                        ) : (
                          <Badge tone="ok">Capa ok</Badge>
                        )
                      }
                    />
                  ))}
                </ul>
              </details>
            </Card>
          )}
        </div>

        <Card className="h-fit">
          <CardHeader title="Como a capa é verificada" icon={Info} />
          <div className="space-y-3 p-5 text-sm text-muted">
            <p>
              O YouTube não informa diretamente se a thumbnail é personalizada. Ao sincronizar, o sistema compara a capa atual com os
              quadros gerados automaticamente pelo YouTube.
            </p>
            <p>
              Se a verificação não for possível, quem faz a capa pode marcar como feita aqui ou no grupo. Tudo fica registrado na
              auditoria.
            </p>
            <div className="flex items-center gap-2 rounded-lg bg-panel-2 px-3 py-2 text-xs">
              <ImageOff className="size-4 text-warn" />
              No grupo: <code className="font-mono text-ink">/semcapa</code>
            </div>
          </div>
        </Card>
      </div>
    </>
  );
}
