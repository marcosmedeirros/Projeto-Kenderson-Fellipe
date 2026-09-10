import { eq } from "drizzle-orm";
import { db } from "@/db";
import { integrations } from "@/db/schema";
import { decrypt, encrypt } from "@/lib/crypto";

export type IntegrationKey = "youtube" | "evolution" | "gemini";
export type EvolutionConfig = { baseUrl: string; instance: string; apiKey: string };
export type GeminiConfig = { apiKey: string; model: string };

const DEFAULT_GEMINI_MODEL = process.env.GEMINI_MODEL || "gemini-2.5-flash";

async function readStored<T>(key: IntegrationKey): Promise<T | null> {
  const [row] = await db.select().from(integrations).where(eq(integrations.key, key)).limit(1);
  if (!row?.config) return null;
  try {
    return JSON.parse(decrypt(row.config)) as T;
  } catch {
    return null;
  }
}

export async function getEvolutionConfig(): Promise<(EvolutionConfig & { source: "painel" | "env" }) | null> {
  const stored = await readStored<EvolutionConfig>("evolution");
  if (stored) return { ...stored, source: "painel" };
  const { EVOLUTION_URL, EVOLUTION_INSTANCE, EVOLUTION_API_KEY } = process.env;
  if (EVOLUTION_URL && EVOLUTION_INSTANCE && EVOLUTION_API_KEY) {
    return { baseUrl: EVOLUTION_URL, instance: EVOLUTION_INSTANCE, apiKey: EVOLUTION_API_KEY, source: "env" };
  }
  return null;
}

export async function getGeminiConfig(): Promise<(GeminiConfig & { source: "painel" | "env" }) | null> {
  const stored = await readStored<GeminiConfig>("gemini");
  if (stored) return { ...stored, model: stored.model || DEFAULT_GEMINI_MODEL, source: "painel" };
  if (process.env.GEMINI_API_KEY) {
    return { apiKey: process.env.GEMINI_API_KEY, model: DEFAULT_GEMINI_MODEL, source: "env" };
  }
  return null;
}

export async function saveIntegration(key: IntegrationKey, config: object, userId: string) {
  const data = {
    key,
    config: encrypt(JSON.stringify(config)),
    status: "conectado" as const,
    lastError: null,
    updatedBy: userId,
    updatedAt: new Date(),
  };
  await db.insert(integrations).values(data).onConflictDoUpdate({ target: integrations.key, set: data });
}

export async function removeIntegration(key: IntegrationKey) {
  await db.delete(integrations).where(eq(integrations.key, key));
}

export const maskSecret = (secret: string) => (secret.length <= 4 ? "••••" : `••••${secret.slice(-4)}`);

/** Visão segura para a interface: nunca inclui chaves completas. */
export async function getIntegrationsOverview() {
  const [rows, evolution, gemini] = await Promise.all([
    db.select({ key: integrations.key, status: integrations.status, lastSyncAt: integrations.lastSyncAt, updatedAt: integrations.updatedAt, lastError: integrations.lastError }).from(integrations),
    getEvolutionConfig(),
    getGeminiConfig(),
  ]);
  const byKey = Object.fromEntries(rows.map((r) => [r.key, r]));
  return {
    youtube: {
      connected: byKey.youtube?.status === "conectado",
      lastSyncAt: byKey.youtube?.lastSyncAt ?? null,
    },
    evolution: evolution
      ? { configured: true, source: evolution.source, baseUrl: evolution.baseUrl, instance: evolution.instance, apiKey: maskSecret(evolution.apiKey), updatedAt: byKey.evolution?.updatedAt ?? null }
      : { configured: false as const },
    gemini: gemini
      ? { configured: true, source: gemini.source, model: gemini.model, apiKey: maskSecret(gemini.apiKey), updatedAt: byKey.gemini?.updatedAt ?? null }
      : { configured: false as const, model: DEFAULT_GEMINI_MODEL },
  };
}

export async function isYoutubeConnected() {
  const [row] = await db.select({ status: integrations.status }).from(integrations).where(eq(integrations.key, "youtube")).limit(1);
  return row?.status === "conectado";
}
