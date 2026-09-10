import { getEvolutionConfig } from "@/lib/integrations";

// Cliente mínimo da Evolution API v2 (WhatsApp).

export type ConnectionState = "open" | "connecting" | "close" | "nao_configurado" | "erro";

const baseUrl = (url: string) => url.replace(/\/+$/, "");

export async function sendWhatsAppText(chatId: string, text: string): Promise<{ ok: boolean; error?: string }> {
  const config = await getEvolutionConfig();
  if (!config) return { ok: false, error: "WhatsApp não configurado." };
  try {
    const res = await fetch(`${baseUrl(config.baseUrl)}/message/sendText/${encodeURIComponent(config.instance)}`, {
      method: "POST",
      headers: { "Content-Type": "application/json", apikey: config.apiKey },
      body: JSON.stringify({ number: chatId, text }),
      signal: AbortSignal.timeout(15_000),
      cache: "no-store",
    });
    if (!res.ok) return { ok: false, error: `O Evolution respondeu com erro ${res.status}.` };
    return { ok: true };
  } catch {
    return { ok: false, error: "Não foi possível conectar ao Evolution." };
  }
}

export async function getConnectionState(): Promise<ConnectionState> {
  const config = await getEvolutionConfig();
  if (!config) return "nao_configurado";
  try {
    const res = await fetch(`${baseUrl(config.baseUrl)}/instance/connectionState/${encodeURIComponent(config.instance)}`, {
      headers: { apikey: config.apiKey },
      signal: AbortSignal.timeout(8_000),
      cache: "no-store",
    });
    if (!res.ok) return "erro";
    const body = (await res.json()) as { instance?: { state?: string } };
    const state = body.instance?.state;
    return state === "open" || state === "connecting" || state === "close" ? state : "erro";
  } catch {
    return "erro";
  }
}
