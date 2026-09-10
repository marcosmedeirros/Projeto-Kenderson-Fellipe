import { headers } from "next/headers";

/** IP e navegador da requisição atual. Em produção o IP vem do proxy reverso (Caddy). */
export async function requestMeta() {
  const h = await headers();
  const forwarded = h.get("x-forwarded-for")?.split(",")[0]?.trim();
  const ip = (forwarded || h.get("x-real-ip") || "").slice(0, 64) || null;
  const userAgent = h.get("user-agent")?.slice(0, 300) ?? null;
  return { ip, userAgent };
}
