"use client";

import {
  BellRing,
  Bot,
  CalendarClock,
  ImageOff,
  LayoutDashboard,
  Lightbulb,
  LogOut,
  Menu,
  Plug,
  ScrollText,
  TrendingUp,
  UserRound,
  Users,
  X,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { logoutAction } from "@/lib/auth/actions";
import { BrandMark, cn } from "./ui";

type NavItem = { href: string; label: string; icon: LucideIcon; count?: number; tone?: "warn" };

export function Sidebar({
  user,
  show,
  counts,
}: {
  user: { name: string; email: string; roleLabel: string };
  show: { integracoes: boolean; usuarios: boolean; auditoria: boolean };
  counts: { semCapa: number; estoqueBaixo: boolean };
}) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  const groups: { title: string; items: NavItem[] }[] = [
    {
      title: "Canal",
      items: [
        { href: "/", label: "Visão geral", icon: LayoutDashboard },
        { href: "/programados", label: "Programados", icon: CalendarClock, count: counts.estoqueBaixo ? -1 : undefined, tone: "warn" },
        { href: "/capas", label: "Capas", icon: ImageOff, count: counts.semCapa || undefined, tone: "warn" },
        { href: "/desempenho", label: "Desempenho", icon: TrendingUp },
        { href: "/ideias", label: "Ideias", icon: Lightbulb },
      ],
    },
    {
      title: "Automação",
      items: [
        { href: "/bot", label: "Bot do WhatsApp", icon: Bot },
        { href: "/alertas", label: "Alertas", icon: BellRing },
      ],
    },
    {
      title: "Administração",
      items: [
        ...(show.integracoes ? [{ href: "/integracoes", label: "Integrações", icon: Plug }] : []),
        ...(show.usuarios ? [{ href: "/usuarios", label: "Usuários", icon: Users }] : []),
        ...(show.auditoria ? [{ href: "/auditoria", label: "Auditoria", icon: ScrollText }] : []),
      ],
    },
  ];

  const isActive = (href: string) => (href === "/" ? pathname === "/" : pathname === href || pathname.startsWith(`${href}/`));
  const initials = user.name
    .split(" ")
    .slice(0, 2)
    .map((p) => p[0])
    .join("")
    .toUpperCase();

  return (
    <>
      <div className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-bg/90 px-4 backdrop-blur lg:hidden">
        <Link href="/" className="flex items-center gap-2.5">
          <BrandMark className="size-7" />
          <span className="display text-lg font-extrabold">Controladoria</span>
        </Link>
        <button type="button" className="btn btn-ghost px-2" onClick={() => setOpen(true)} aria-label="Abrir menu">
          <Menu className="size-5" />
        </button>
      </div>

      {open && <div className="fixed inset-0 z-40 bg-black/60 lg:hidden" onClick={() => setOpen(false)} aria-hidden="true" />}

      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-[264px] flex-col border-r border-line bg-panel transition-transform lg:translate-x-0",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className="flex h-16 items-center justify-between px-5">
          <Link href="/" className="flex items-center gap-3" onClick={() => setOpen(false)}>
            <BrandMark className="size-8" />
            <span className="leading-tight">
              <span className="display block text-[19px] font-extrabold">Controladoria</span>
              <span className="block font-mono text-[10px] tracking-[0.1em] text-dim uppercase">Canal Luiz Jordão</span>
            </span>
          </Link>
          <button type="button" className="btn btn-ghost px-2 lg:hidden" onClick={() => setOpen(false)} aria-label="Fechar menu">
            <X className="size-5" />
          </button>
        </div>

        <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
          {groups
            .filter((g) => g.items.length)
            .map((group) => (
              <div key={group.title}>
                <p className="eyebrow mb-2 px-3 text-dim">{group.title}</p>
                <ul className="space-y-0.5">
                  {group.items.map((item) => {
                    const active = isActive(item.href);
                    const Icon = item.icon;
                    return (
                      <li key={item.href}>
                        <Link
                          href={item.href}
                          onClick={() => setOpen(false)}
                          aria-current={active ? "page" : undefined}
                          className={cn(
                            "group relative flex h-9 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition-colors",
                            active ? "bg-panel-3 text-ink" : "text-muted hover:bg-panel-2 hover:text-ink",
                          )}
                        >
                          {active && <span className="absolute top-2 bottom-2 left-0 w-0.5 rounded-full bg-accent" />}
                          <Icon className={cn("size-4", active ? "text-accent" : "text-dim group-hover:text-muted")} />
                          <span className="flex-1">{item.label}</span>
                          {item.count === -1 ? (
                            <span className="size-2 rounded-full bg-warn" title="Estoque abaixo do mínimo" />
                          ) : item.count ? (
                            <span className="badge bg-warn/15 text-warn">{item.count}</span>
                          ) : null}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))}
        </nav>

        <div className="border-t border-line p-3">
          <Link
            href="/conta"
            onClick={() => setOpen(false)}
            className={cn(
              "flex items-center gap-3 rounded-lg px-2.5 py-2 transition-colors hover:bg-panel-2",
              isActive("/conta") && "bg-panel-3",
            )}
          >
            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-accent/15 font-display text-sm font-bold text-accent">
              {initials}
            </span>
            <span className="min-w-0 flex-1 leading-tight">
              <span className="block truncate text-sm font-semibold">{user.name}</span>
              <span className="block truncate text-xs text-dim">{user.roleLabel}</span>
            </span>
            <UserRound className="size-4 text-dim" />
          </Link>
          <form action={logoutAction} className="mt-1">
            <button type="submit" className="btn btn-ghost h-9 w-full justify-start px-2.5 font-semibold">
              <LogOut className="size-4" />
              Sair
            </button>
          </form>
        </div>
      </aside>
    </>
  );
}
