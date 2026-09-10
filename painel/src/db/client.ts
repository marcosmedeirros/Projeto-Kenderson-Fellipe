import { mkdirSync } from "node:fs";
import path from "node:path";
import { PGlite } from "@electric-sql/pglite";
import { drizzle as drizzlePg, type NodePgDatabase } from "drizzle-orm/node-postgres";
import { drizzle as drizzlePglite } from "drizzle-orm/pglite";
import { Pool } from "pg";
import * as schema from "./schema";

export type Db = NodePgDatabase<typeof schema>;
export type DbDriver = "postgres" | "pglite";

/**
 * Com DATABASE_URL usa Postgres de verdade (produção).
 * Sem ela, usa o Postgres embutido (PGlite) em .data/pglite, só para desenvolvimento.
 */
export function createDb(): { db: Db; driver: DbDriver } {
  const url = process.env.DATABASE_URL?.trim();
  if (url) {
    const pool = new Pool({ connectionString: url, max: 10 });
    return { db: drizzlePg(pool, { schema }), driver: "postgres" };
  }
  if (process.env.NODE_ENV === "production") {
    throw new Error("DATABASE_URL é obrigatória em produção.");
  }
  const dir = path.resolve(process.cwd(), ".data/pglite");
  mkdirSync(dir, { recursive: true });
  const client = new PGlite(dir);
  return { db: drizzlePglite(client, { schema }) as unknown as Db, driver: "pglite" };
}
