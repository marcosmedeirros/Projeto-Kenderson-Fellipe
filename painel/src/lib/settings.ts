import { eq } from "drizzle-orm";
import { z } from "zod";
import { db } from "@/db";
import { settings } from "@/db/schema";

export const COMMANDS = ["programados", "semcapa", "resumo", "virais", "ideias", "ajuda"] as const;
export type CommandName = (typeof COMMANDS)[number];

const hora = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, { error: "Use o formato HH:MM." });

export const alertasSchema = z.object({
  estoqueMinimoDias: z.coerce.number().int().min(1).max(90),
  capaAvisoDias: z.coerce.number().int().min(1).max(30),
  alertaEstoqueHora: hora,
  resumoSemanalAtivo: z.boolean(),
  resumoSemanalDia: z.coerce.number().int().min(0).max(6),
  resumoSemanalHora: hora,
  relatorioMensalAtivo: z.boolean(),
  relatorioMensalHora: hora,
  viralMultiplicador: z.coerce.number().min(1.2).max(10),
});

export const botSchema = z.object({
  ativo: z.boolean(),
  grupoId: z
    .string()
    .trim()
    .max(80)
    .refine((v) => v === "" || /^[\d-]+@g\.us$/.test(v), { error: "O ID do grupo termina com @g.us." }),
  grupoNome: z.string().trim().max(80),
  comandos: z.object(Object.fromEntries(COMMANDS.map((c) => [c, z.boolean()])) as Record<CommandName, z.ZodBoolean>),
});

export const estadoAutomacoesSchema = z.object({
  ultimoAlertaEstoque: z.string().nullable(),
  ultimoAlertaCapa: z.string().nullable(),
  ultimoResumoSemanal: z.string().nullable(),
  ultimoRelatorioMensal: z.string().nullable(),
});

type SettingsMap = {
  alertas: z.infer<typeof alertasSchema>;
  bot: z.infer<typeof botSchema>;
  automacoes: z.infer<typeof estadoAutomacoesSchema>;
};

const DEFAULTS: SettingsMap = {
  alertas: {
    estoqueMinimoDias: 14,
    capaAvisoDias: 3,
    alertaEstoqueHora: "08:00",
    resumoSemanalAtivo: true,
    resumoSemanalDia: 1,
    resumoSemanalHora: "08:00",
    relatorioMensalAtivo: true,
    relatorioMensalHora: "09:00",
    viralMultiplicador: 2,
  },
  bot: {
    ativo: true,
    grupoId: "",
    grupoNome: "Produção do canal",
    comandos: { programados: true, semcapa: true, resumo: true, virais: true, ideias: true, ajuda: true },
  },
  automacoes: {
    ultimoAlertaEstoque: null,
    ultimoAlertaCapa: null,
    ultimoResumoSemanal: null,
    ultimoRelatorioMensal: null,
  },
};

export type SettingKey = keyof SettingsMap;
export type AlertSettings = SettingsMap["alertas"];
export type BotSettings = SettingsMap["bot"];

const SCHEMAS = { alertas: alertasSchema, bot: botSchema, automacoes: estadoAutomacoesSchema };

export async function getSetting<K extends SettingKey>(key: K): Promise<SettingsMap[K]> {
  const [row] = await db.select().from(settings).where(eq(settings.key, key)).limit(1);
  if (!row) return DEFAULTS[key];
  const parsed = SCHEMAS[key].safeParse({ ...DEFAULTS[key], ...(row.value as object) });
  return (parsed.success ? parsed.data : DEFAULTS[key]) as SettingsMap[K];
}

export async function saveSetting<K extends SettingKey>(key: K, value: SettingsMap[K], userId?: string | null) {
  const data = { key, value, updatedBy: userId ?? null, updatedAt: new Date() };
  await db.insert(settings).values(data).onConflictDoUpdate({ target: settings.key, set: data });
}
