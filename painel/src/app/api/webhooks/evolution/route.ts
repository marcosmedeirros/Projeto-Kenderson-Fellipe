import { NextResponse, type NextRequest } from "next/server";
import { parseCommand, runCommand } from "@/lib/bot/commands";
import { sendWhatsAppText } from "@/lib/bot/evolution";
import { countRecentCommands, recordBotMessage } from "@/lib/bot/log";
import { safeEqual } from "@/lib/crypto";
import { getSetting } from "@/lib/settings";

const MAX_BODY_BYTES = 256 * 1024;
const MAX_COMMANDS_PER_MINUTE = 15;

type EvolutionMessage = {
  key?: { remoteJid?: string; fromMe?: boolean; participant?: string };
  pushName?: string;
  message?: { conversation?: string; extendedTextMessage?: { text?: string } };
};

const ignored = (reason: string) => NextResponse.json({ ok: true, ignored: reason });

/**
 * Recebe os eventos do Evolution (messages.upsert).
 * Configure a URL no Evolution como: https://SEU-DOMINIO/api/webhooks/evolution?token=EVOLUTION_WEBHOOK_TOKEN
 */
export async function POST(request: NextRequest) {
  const expected = process.env.EVOLUTION_WEBHOOK_TOKEN;
  const token = request.nextUrl.searchParams.get("token") ?? request.headers.get("x-webhook-token") ?? "";
  if (!expected || !safeEqual(token, expected)) {
    return NextResponse.json({ error: "Não autorizado." }, { status: 401 });
  }

  if (Number(request.headers.get("content-length") ?? 0) > MAX_BODY_BYTES) {
    return NextResponse.json({ error: "Conteúdo grande demais." }, { status: 413 });
  }

  const body = (await request.json().catch(() => null)) as { event?: string; data?: EvolutionMessage | EvolutionMessage[] } | null;
  if (!body || (body.event !== "messages.upsert" && body.event !== "MESSAGES_UPSERT")) return ignored("evento");

  const message = Array.isArray(body.data) ? body.data[0] : body.data;
  const chatId = message?.key?.remoteJid;
  if (!message?.key || message.key.fromMe || !chatId) return ignored("mensagem");

  const bot = await getSetting("bot");
  if (!bot.ativo) return ignored("bot desligado");
  if (!bot.grupoId || chatId !== bot.grupoId) return ignored("grupo não autorizado");

  const text = message.message?.conversation ?? message.message?.extendedTextMessage?.text ?? "";
  const command = parseCommand(text);
  if (!command || !bot.comandos[command]) return ignored("não é comando");

  if ((await countRecentCommands(chatId)) >= MAX_COMMANDS_PER_MINUTE) return ignored("limite por minuto");

  const sender = message.pushName?.slice(0, 80) ?? message.key.participant?.split("@")[0] ?? null;
  await recordBotMessage({ direction: "entrada", origin: "whatsapp", chatId, sender, text: text.slice(0, 200), command });

  const reply = await runCommand(command);
  const result = await sendWhatsAppText(chatId, reply);
  await recordBotMessage({ direction: "saida", origin: "whatsapp", chatId, text: reply, command, delivered: result.ok });

  return NextResponse.json({ ok: result.ok });
}
