import type { LucideIcon } from "lucide-react";
import Link from "next/link";

export const cn = (...classes: (string | false | null | undefined)[]) => classes.filter(Boolean).join(" ");

export type Tone = "ok" | "warn" | "danger" | "info" | "neutral";

const BADGE_TONE: Record<Tone, string> = {
  ok: "bg-accent/12 text-accent",
  warn: "bg-warn/12 text-warn",
  danger: "bg-danger/12 text-danger",
  info: "bg-info/12 text-info",
  neutral: "bg-panel-3 text-muted",
};

export function Badge({ tone = "neutral", children, className }: { tone?: Tone; children: React.ReactNode; className?: string }) {
  return <span className={cn("badge", BADGE_TONE[tone], className)}>{children}</span>;
}

export function BrandMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" className={className}>
      <rect width="32" height="32" rx="8" fill="#151e27" />
      <rect x="6" y="11" width="7" height="10" rx="2" fill="#2db89b" />
      <rect x="15" y="11" width="5" height="10" rx="2" fill="#e9a23b" />
      <rect x="22" y="11" width="4" height="10" rx="2" fill="#2db89b" opacity=".5" />
      <rect x="9" y="6" width="2" height="20" rx="1" fill="#f0564a" />
    </svg>
  );
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
}: {
  eyebrow?: string;
  title: string;
  description?: React.ReactNode;
  actions?: React.ReactNode;
}) {
  return (
    <header className="mb-7 flex flex-wrap items-end justify-between gap-4">
      <div className="min-w-0">
        {eyebrow && <p className="eyebrow mb-2">{eyebrow}</p>}
        <h1 className="display text-[34px] leading-none font-extrabold text-balance sm:text-[40px]">{title}</h1>
        {description && <p className="mt-2.5 max-w-2xl text-[15px] text-muted">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  );
}

export function Card({ children, className }: { children: React.ReactNode; className?: string }) {
  return <section className={cn("card", className)}>{children}</section>;
}

export function CardHeader({
  title,
  description,
  icon: Icon,
  action,
}: {
  title: string;
  description?: React.ReactNode;
  icon?: LucideIcon;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
      <div className="flex min-w-0 items-start gap-3">
        {Icon && (
          <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-panel-2 text-muted">
            <Icon className="size-4" />
          </span>
        )}
        <div className="min-w-0">
          <h2 className="text-[15px] font-bold">{title}</h2>
          {description && <p className="mt-0.5 text-[13px] text-muted">{description}</p>}
        </div>
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

const STAT_ACCENT: Record<Tone, string> = {
  ok: "text-ink",
  warn: "text-warn",
  danger: "text-danger",
  info: "text-info",
  neutral: "text-ink",
};

export function Stat({
  label,
  value,
  hint,
  tone = "neutral",
  icon: Icon,
  href,
}: {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  tone?: Tone;
  icon?: LucideIcon;
  href?: string;
}) {
  const body = (
    <>
      <div className="flex items-center justify-between">
        <span className="eyebrow">{label}</span>
        {Icon && <Icon className={cn("size-4", tone === "neutral" || tone === "ok" ? "text-dim" : STAT_ACCENT[tone])} />}
      </div>
      <div className={cn("display mt-3 text-[38px] leading-none font-extrabold tabular-nums", STAT_ACCENT[tone])}>{value}</div>
      {hint && <div className="mt-2 text-[13px] text-muted">{hint}</div>}
      {tone === "warn" || tone === "danger" ? (
        <span className={cn("absolute inset-x-0 top-0 h-0.5 rounded-t-xl", tone === "warn" ? "bg-warn" : "bg-danger")} />
      ) : null}
    </>
  );
  const className = "card relative block p-5";
  return href ? (
    <Link href={href} className={cn(className, "transition-colors hover:border-line-2 hover:bg-panel-2/60")}>
      {body}
    </Link>
  ) : (
    <div className={className}>{body}</div>
  );
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
}: {
  icon: LucideIcon;
  title: string;
  description?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col items-center px-6 py-10 text-center">
      <span className="grid size-11 place-items-center rounded-xl bg-panel-2 text-dim">
        <Icon className="size-5" />
      </span>
      <p className="mt-3 font-semibold">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-muted">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

const NOTICE_TONE: Record<Exclude<Tone, "neutral" | "ok">, string> = {
  warn: "border-warn/30 bg-warn/[0.07] text-warn",
  danger: "border-danger/30 bg-danger/[0.07] text-danger",
  info: "border-info/30 bg-info/[0.07] text-info",
};

export function Notice({
  tone = "info",
  icon: Icon,
  title,
  children,
  action,
  className,
}: {
  tone?: "warn" | "danger" | "info";
  icon?: LucideIcon;
  title: string;
  children?: React.ReactNode;
  action?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border px-4 py-3.5", NOTICE_TONE[tone], className)}>
      {Icon && <Icon className="size-5 shrink-0" />}
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{title}</p>
        {children && <div className="mt-0.5 text-sm text-ink/75">{children}</div>}
      </div>
      {action}
    </div>
  );
}

export function KeyValue({ items }: { items: { label: string; value: React.ReactNode }[] }) {
  return (
    <dl className="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2.5 text-sm">
      {items.map((item) => (
        <div key={item.label} className="contents">
          <dt className="text-muted">{item.label}</dt>
          <dd className="min-w-0 break-words">{item.value}</dd>
        </div>
      ))}
    </dl>
  );
}
