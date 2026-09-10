import { desc } from "drizzle-orm";
import { z } from "zod";
import { db } from "@/db";
import { ideas } from "@/db/schema";
import { getRisingTerms, getSearchTerms, getTopVideos } from "@/lib/channel/queries";
import { fmtNumber, monthKey, shiftMonth } from "@/lib/format";
import { getGeminiConfig } from "@/lib/integrations";

const ideaSchema = z.object({
  titulo: z.string().min(5).max(140),
  porque: z.string().min(10).max(400),
  fonte: z.enum(["buscas", "comentarios", "desempenho"]),
  pontuacao: z.number().int().min(0).max(100),
});

type IdeaDraft = z.infer<typeof ideaSchema>;

async function buildContext() {
  const month = monthKey();
  const [top, topPrev, terms, rising, existing] = await Promise.all([
    getTopVideos(month, 8),
    getTopVideos(shiftMonth(month, -1), 8),
    getSearchTerms(month, 15),
    getRisingTerms(month, 8),
    db.select({ title: ideas.title }).from(ideas).orderBy(desc(ideas.createdAt)).limit(40),
  ]);
  return { top: [...top, ...topPrev], terms, rising, existing: existing.map((e) => e.title) };
}

async function askGemini(apiKey: string, model: string, context: Awaited<ReturnType<typeof buildContext>>) {
  const prompt = `Você ajuda a equipe de um canal brasileiro do YouTube a decidir as próximas pautas.
Com base nos dados abaixo, sugira 5 ideias de vídeo novas, específicas e diferentes das já cadastradas.
Responda apenas com um array JSON de objetos: {"titulo": string, "porque": string (1 frase citando o dado que motivou), "fonte": "buscas" | "desempenho" | "comentarios", "pontuacao": inteiro de 0 a 100 indicando o potencial}.

Vídeos com mais views recentemente (título, views, retenção média):
${context.top.map((v) => `- ${v.title} | ${v.views} | ${Math.round(v.avgViewPct)}%`).join("\n")}

Termos de busca do YouTube que trouxeram gente ao canal neste mês (termo, views):
${context.terms.map((t) => `- ${t.term} | ${t.views}`).join("\n")}

Termos que mais cresceram vs mês anterior:
${context.rising.map((t) => `- ${t.term} | ${t.views} (antes ${t.before})`).join("\n")}

Ideias já cadastradas (não repetir):
${context.existing.map((t) => `- ${t}`).join("\n") || "- nenhuma"}`;

  const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(model)}:generateContent`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "x-goog-api-key": apiKey },
    body: JSON.stringify({
      contents: [{ role: "user", parts: [{ text: prompt }] }],
      generationConfig: { responseMimeType: "application/json", temperature: 0.8 },
    }),
    signal: AbortSignal.timeout(60_000),
    cache: "no-store",
  });
  if (!res.ok) throw new Error(`O Gemini respondeu com erro ${res.status}.`);
  const body = (await res.json()) as { candidates?: { content?: { parts?: { text?: string }[] } }[] };
  const text = body.candidates?.[0]?.content?.parts?.[0]?.text ?? "[]";
  const parsed = z.array(ideaSchema).safeParse(JSON.parse(text));
  if (!parsed.success) throw new Error("O Gemini devolveu ideias num formato inesperado.");
  return parsed.data;
}

/** Sem chave do Gemini: cria sugestões simples a partir dos termos de busca em alta. */
function exampleIdeas(context: Awaited<ReturnType<typeof buildContext>>): IdeaDraft[] {
  const existing = context.existing.join(" ").toLowerCase();
  const templates = [
    (t: string) => `${t}: o guia que eu queria ter lido antes`,
    (t: string) => `Testei ${t} por 30 dias e isso aconteceu`,
    (t: string) => `Os 5 erros mais comuns sobre ${t}`,
    (t: string) => `${t} na prática: meu passo a passo`,
  ];
  return context.rising
    .filter((t) => !existing.includes(t.term.toLowerCase()))
    .slice(0, 4)
    .map((t, i) => ({
      titulo: templates[i % templates.length](t.term.charAt(0).toUpperCase() + t.term.slice(1)),
      porque:
        t.growth == null
          ? `"${t.term}" apareceu pela primeira vez nas buscas e já trouxe ${fmtNumber(t.views)} views este mês.`
          : `Buscas por "${t.term}" cresceram ${Math.round(t.growth * 100)}% e trouxeram ${fmtNumber(t.views)} views este mês.`,
      fonte: "buscas" as const,
      pontuacao: Math.max(40, 90 - i * 10),
    }));
}

export async function generateIdeas() {
  const [context, gemini] = await Promise.all([buildContext(), getGeminiConfig()]);
  let drafts: IdeaDraft[];
  let generatedBy: "ia" | "exemplo";
  if (gemini) {
    drafts = await askGemini(gemini.apiKey, gemini.model, context);
    generatedBy = "ia";
  } else {
    drafts = exampleIdeas(context);
    generatedBy = "exemplo";
  }
  if (drafts.length) {
    await db.insert(ideas).values(
      drafts.map((d) => ({ title: d.titulo, rationale: d.porque, source: d.fonte, score: d.pontuacao, generatedBy })),
    );
  }
  return { created: drafts.length, generatedBy };
}
