import path from "node:path";
import { count } from "drizzle-orm";
import { migrate as migratePg } from "drizzle-orm/node-postgres/migrator";
import { migrate as migratePglite } from "drizzle-orm/pglite/migrator";
import type { PgliteDatabase } from "drizzle-orm/pglite";
import { getDbHandle } from "@/db";
import { users } from "@/db/schema";
import { hashPassword, passwordSchema } from "@/lib/auth/password";
import { seedDemoIfEmpty } from "./demo-seed";
import { startScheduler } from "./scheduler";

async function bootstrapAdmin() {
  const { db } = getDbHandle();
  const [{ n }] = await db.select({ n: count() }).from(users);
  if (n > 0) return;

  const email = process.env.ADMIN_EMAIL?.trim().toLowerCase();
  const password = process.env.ADMIN_PASSWORD;
  if (!email || !password) {
    console.warn("[inicialização] Nenhum usuário cadastrado. Defina ADMIN_EMAIL e ADMIN_PASSWORD para criar o primeiro administrador.");
    return;
  }
  if (!passwordSchema.safeParse(password).success) {
    console.error("[inicialização] ADMIN_PASSWORD fraca: use 10+ caracteres com letras e números.");
    return;
  }
  await db.insert(users).values({
    name: process.env.ADMIN_NAME?.trim() || "Administrador",
    email,
    passwordHash: await hashPassword(password),
    role: "admin",
    mustChangePassword: true,
  });
  console.info(`[inicialização] Administrador ${email} criado. A senha deve ser trocada no primeiro acesso.`);
}

export async function boot() {
  const { db, driver } = getDbHandle();

  if (process.env.AUTO_MIGRATE !== "false") {
    const migrationsFolder = path.join(process.cwd(), "drizzle");
    if (driver === "pglite") {
      await migratePglite(db as unknown as PgliteDatabase<Record<string, never>>, { migrationsFolder });
    } else {
      await migratePg(db, { migrationsFolder });
    }
  }

  await bootstrapAdmin();

  if (process.env.DEMO_DATA === "true" && (await seedDemoIfEmpty(db))) {
    console.info("[inicialização] Dados de exemplo carregados.");
  }

  if (process.env.SCHEDULER !== "false") startScheduler();
}
