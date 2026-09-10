import type { ScheduledVideo } from "@/lib/channel/queries";
import { fmtDate, fmtTime, fmtWeekday } from "@/lib/format";
import { cn } from "./ui";

const DAY = 86_400_000;

/** Linha do tempo no estilo de um editor de vídeo: cada bloco é um vídeo agendado. */
export function StockTimeline({
  videos,
  days = 30,
  minimumDays,
}: {
  videos: ScheduledVideo[];
  days?: number;
  minimumDays: number;
}) {
  // Componente de servidor: renderiza uma vez por requisição, então ler o relógio aqui é seguro.
  const now = new Date().getTime();
  const pos = (d: Date) => ((d.getTime() - now) / (days * DAY)) * 100;
  const dayWidth = 100 / days;
  const last = videos.at(-1)?.publishAt;
  const lastPos = last ? Math.min(100, Math.max(0, pos(last) + dayWidth)) : 0;
  const minPos = Math.min(100, (minimumDays / days) * 100);
  const visible = videos.filter((v) => v.publishAt && pos(v.publishAt) <= 100);
  const tracks = [
    { key: "longos", label: "Vídeos", items: visible.filter((v) => !v.isShort) },
    { key: "shorts", label: "Shorts", items: visible.filter((v) => v.isShort) },
  ];
  const ticks = Array.from({ length: Math.floor(days / 5) + 1 }, (_, i) => i * 5);

  return (
    <div>
      <div className="overflow-x-auto pb-1">
        <div className="grid min-w-[560px] grid-cols-[64px_1fr] gap-x-3 gap-y-2">
          <div className="col-start-2 row-start-1">
            <div
              className="relative h-7 border-b border-line-2"
              style={{
                backgroundImage: `repeating-linear-gradient(90deg, #2c3a48 0 1px, transparent 1px ${dayWidth}%)`,
                backgroundSize: "100% 5px",
                backgroundPosition: "left bottom",
                backgroundRepeat: "no-repeat",
              }}
            >
              {ticks.map((t) => (
                <span
                  key={t}
                  className={cn(
                    "absolute top-0.5 font-mono text-[11px] text-dim tabular-nums",
                    t === 0 ? "translate-x-0" : t >= days ? "-translate-x-full" : "-translate-x-1/2",
                  )}
                  style={{ left: `${(t / days) * 100}%` }}
                >
                  {t === 0 ? "hoje" : fmtDate(new Date(now + t * DAY))}
                </span>
              ))}
            </div>
          </div>

          {tracks.map((track, index) => (
            <div key={track.key} className="contents">
              <div
                className="flex items-center font-mono text-[11px] font-semibold tracking-[0.06em] text-dim uppercase"
                style={{ gridRow: index + 2 }}
              >
                {track.label}
              </div>
              <div className="relative col-start-2 h-11 overflow-hidden rounded-md bg-panel-2" style={{ gridRow: index + 2 }}>
                <div
                  className="absolute inset-y-0 right-0"
                  style={{
                    left: `${lastPos}%`,
                    backgroundImage: "repeating-linear-gradient(135deg, rgba(233,162,59,.16) 0 6px, transparent 6px 12px)",
                  }}
                />
                {track.items.map((v) => {
                  const missing = v.thumbnailStatus !== "ok";
                  const label = `${fmtWeekday(v.publishAt!)} ${fmtDate(v.publishAt!)} ${fmtTime(v.publishAt!)} · ${v.title}${missing ? " · sem capa" : ""}`;
                  return (
                    <div
                      key={v.id}
                      title={label}
                      aria-label={label}
                      className={cn(
                        "absolute inset-y-1.5 grid place-items-center rounded-[5px] font-mono text-[10px] font-semibold shadow-[inset_0_1px_0_rgba(255,255,255,.22)]",
                        missing ? "bg-warn text-[#231603]" : "bg-accent text-accent-ink",
                      )}
                      style={{ left: `${Math.max(0, pos(v.publishAt!))}%`, width: `max(${dayWidth}%, 14px)` }}
                    >
                      {fmtDate(v.publishAt!).slice(0, 2)}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}

          <div className="pointer-events-none relative col-start-2 row-start-1 row-end-4" aria-hidden="true">
            <div className="absolute inset-y-0 left-0 w-0.5 bg-danger" />
            <div className="absolute top-7 bottom-0 border-l-2 border-dashed border-info/70" style={{ left: `${minPos}%` }} />
            {last && <div className="absolute top-7 bottom-0 border-l-2 border-dashed border-warn" style={{ left: `${lastPos}%` }} />}
          </div>
        </div>
      </div>

      <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-muted">
        <span className="flex items-center gap-2"><i className="size-2.5 rounded-sm bg-accent" />Com capa</span>
        <span className="flex items-center gap-2"><i className="size-2.5 rounded-sm bg-warn" />Sem capa</span>
        <span className="flex items-center gap-2"><i className="h-3 w-0.5 bg-danger" />Hoje</span>
        <span className="flex items-center gap-2"><i className="h-3 border-l-2 border-dashed border-info/70" />Mínimo ({minimumDays} dias)</span>
        {last && (
          <span className="flex items-center gap-2"><i className="h-3 border-l-2 border-dashed border-warn" />Fim do estoque ({fmtDate(last)})</span>
        )}
      </div>
    </div>
  );
}
