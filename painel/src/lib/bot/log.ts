import { and, count, eq, gt } from "drizzle-orm";
import { db } from "@/db";
import { botMessages } from "@/db/schema";

type NewBotMessage = typeof botMessages.$inferInsert;

export async function recordBotMessage(message: NewBotMessage) {
  await db.insert(botMessages).values({ ...message, text: message.text.slice(0, 4000) });
}

export async function countRecentCommands(chatId: string, seconds = 60) {
  const [row] = await db
    .select({ n: count() })
    .from(botMessages)
    .where(and(eq(botMessages.chatId, chatId), eq(botMessages.direction, "entrada"), gt(botMessages.createdAt, new Date(Date.now() - seconds * 1000))));
  return row.n;
}
