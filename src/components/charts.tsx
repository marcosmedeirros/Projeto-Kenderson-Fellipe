import { cn } from "./ui";

/** Gráfico de área responsivo. Os rótulos ficam em HTML para não distorcer com o SVG esticado. */
export function AreaChart({
  data,
  height = 170,
  formatValue,
}: {
  data: { label: string; value: number }[];
  height?: number;
  formatValue: (n: number) => string;
}) {
  if (data.length < 2) {
    return <p className="py-10 text-center text-sm text-muted">Ainda não há dados suficientes para o gráfico.</p>;
  }
  const W = 1000;
  const H = 300;
  const peak = Math.max(...data.map((d) => d.value));
  const top = peak * 1.12 || 1;
  const points = data.map((d, i) => [(i / (data.length - 1)) * W, H - (d.value / top) * H] as const);
  const line = points.map(([x, y], i) => `${i ? "L" : "M"}${x.toFixed(1)},${y.toFixed(1)}`).join(" ");
  const area = `${line} L${W},${H} L0,${H} Z`;
  const last = points[points.length - 1];
  const middle = data[Math.floor(data.length / 2)];

  return (
    <div>
      <div className="relative" style={{ height }}>
        <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" className="absolute inset-0 size-full overflow-visible" aria-hidden="true">
          <defs>
            <linearGradient id="area-fill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#2db89b" stopOpacity="0.32" />
              <stop offset="100%" stopColor="#2db89b" stopOpacity="0" />
            </linearGradient>
          </defs>
          {[0.25, 0.5, 0.75].map((f) => (
            <line key={f} x1="0" x2={W} y1={H * f} y2={H * f} stroke="#1f2a35" strokeWidth="1" vectorEffect="non-scaling-stroke" />
          ))}
          <line x1="0" x2={W} y1={H} y2={H} stroke="#2c3a48" strokeWidth="1" vectorEffect="non-scaling-stroke" />
          <path d={area} fill="url(#area-fill)" />
          <path d={line} fill="none" stroke="#2db89b" strokeWidth="2" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
        </svg>
        <span
          className="absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-accent ring-4 ring-accent/20"
          style={{ left: "100%", top: `${(last[1] / H) * 100}%` }}
        />
        <span className="absolute top-0 left-0 rounded bg-panel/80 px-1 font-mono text-[11px] text-dim">
          pico {formatValue(peak)}
        </span>
      </div>
      <div className="mt-2 flex justify-between font-mono text-[11px] text-dim">
        <span>{data[0].label}</span>
        <span>{middle.label}</span>
        <span>{data[data.length - 1].label}</span>
      </div>
    </div>
  );
}

export function BarList({
  items,
  format,
}: {
  items: { key: string; label: React.ReactNode; value: number; meta?: React.ReactNode; tone?: "accent" | "warn" | "info" }[];
  format: (n: number) => string;
}) {
  const max = Math.max(1, ...items.map((i) => i.value));
  return (
    <ul className="space-y-3.5">
      {items.map((item) => (
        <li key={item.key}>
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="min-w-0 truncate">{item.label}</span>
            <span className="shrink-0 font-mono text-xs text-muted tabular-nums">{format(item.value)}</span>
          </div>
          <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-panel-3">
            <div
              className={cn(
                "h-full rounded-full",
                item.tone === "warn" ? "bg-warn" : item.tone === "info" ? "bg-info" : "bg-accent",
              )}
              style={{ width: `${Math.max(2, (item.value / max) * 100)}%` }}
            />
          </div>
          {item.meta && <div className="mt-1 text-xs text-dim">{item.meta}</div>}
        </li>
      ))}
    </ul>
  );
}
