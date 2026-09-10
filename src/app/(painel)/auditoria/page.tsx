import { count, desc, eq } from "drizzle-orm";
import { ChevronLeft, ChevronRight, ScrollText } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { Badge, Card, CardHeader, EmptyState, PageHeader } from "@/components/ui";
import { db } from "@/db";
import { auditLogs, users } from "@/db/schema";
import { AUDIT_LABEL } from "@/lib/audit";
import { requirePermission } from "@/lib/auth/dal";
import { fmtFullDate } from "@/lib/format";

export const metadata: Metadata = { title: "Auditoria" };

const PAGE_SIZE = 50;

const summarize = (detail: Record<string, unknown> | null) =>
  detail
    ? Object.entries(detail)
        .map(([k, v]) => `${k}: ${typeof v === "object" ? JSON.stringify(v) : String(v)}`)
        .join(" · ")
    : "";

export default async function AuditPage({ searchParams }: PageProps<"/auditoria">) {
  await requirePermission("auditoria");
  const { pagina } = await searchParams;
  const page = Math.max(1, Math.min(10_000, Number.parseInt(typeof pagina === "string" ? pagina : "1", 10) || 1));

  const [rows, [{ total }]] = await Promise.all([
    db
      .select({ id: auditLogs.id, action: auditLogs.action, detail: auditLogs.detail, ip: auditLogs.ip, createdAt: auditLogs.createdAt, userName: users.name })
      .from(auditLogs)
      .leftJoin(users, eq(users.id, auditLogs.userId))
      .orderBy(desc(auditLogs.createdAt))
      .limit(PAGE_SIZE)
      .offset((page - 1) * PAGE_SIZE),
    db.select({ total: count() }).from(auditLogs),
  ]);
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <>
      <PageHeader
        eyebrow="Administração"
        title="Auditoria"
        description="Registro de acessos e alterações feitas no painel. Senhas e chaves nunca são gravadas aqui."
      />

      <Card>
        <CardHeader
          title={`${total} registros`}
          icon={ScrollText}
          action={
            <div className="flex items-center gap-1">
              {page > 1 ? (
                <Link href={`/auditoria?pagina=${page - 1}`} className="btn btn-ghost btn-sm px-2" aria-label="Página anterior"><ChevronLeft className="size-4" /></Link>
              ) : null}
              <span className="font-mono text-xs text-muted">{page} / {pages}</span>
              {page < pages ? (
                <Link href={`/auditoria?pagina=${page + 1}`} className="btn btn-ghost btn-sm px-2" aria-label="Próxima página"><ChevronRight className="size-4" /></Link>
              ) : null}
            </div>
          }
        />
        {rows.length ? (
          <div className="overflow-x-auto">
            <table className="table-base min-w-[760px]">
              <thead>
                <tr>
                  <th>Quando</th>
                  <th>Quem</th>
                  <th>O que aconteceu</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => {
                  const alert = r.action.includes("falha") || r.action.includes("bloqueado");
                  return (
                    <tr key={r.id}>
                      <td className="font-mono text-xs whitespace-nowrap text-muted">{fmtFullDate(r.createdAt)}</td>
                      <td className="text-sm whitespace-nowrap">{r.userName ?? <span className="text-dim">—</span>}</td>
                      <td>
                        <div className="flex items-center gap-2">
                          {alert && <Badge tone="danger">Atenção</Badge>}
                          <span className="text-sm font-semibold">{AUDIT_LABEL[r.action] ?? r.action}</span>
                        </div>
                        {r.detail && <p className="mt-0.5 max-w-xl truncate text-xs text-dim">{summarize(r.detail)}</p>}
                      </td>
                      <td className="font-mono text-xs text-muted">{r.ip ?? "—"}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={ScrollText} title="Nenhum registro ainda" />
        )}
      </Card>
    </>
  );
}
