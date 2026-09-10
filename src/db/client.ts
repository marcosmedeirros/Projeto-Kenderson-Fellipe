import { drizzle, type MySql2Database } from "drizzle-orm/mysql2";
import mysql from "mysql2/promise";
import * as schema from "./schema";

export type Db = MySql2Database<typeof schema>;
export type DbDriver = "mysql";

/**
 * Aceita DATABASE_URL (mysql://usuario:senha@host:3306/banco) ou as variáveis separadas
 * DB_HOST, DB_PORT, DB_USER, DB_PASSWORD e DB_NAME (o formato que a Hostinger usa).
 * As separadas evitam problemas com caracteres especiais na senha, como "@".
 */
function connectionOptions(): mysql.PoolOptions {
  const common: mysql.PoolOptions = {
    waitForConnections: true,
    connectionLimit: Number(process.env.DB_POOL_SIZE || 5),
    timezone: "Z",
    charset: "utf8mb4",
    enableKeepAlive: true,
  };
  const url = process.env.DATABASE_URL?.trim();
  if (url) return { ...common, uri: url };

  const { DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME } = process.env;
  if (!DB_HOST || !DB_USER || !DB_NAME) {
    throw new Error("Banco não configurado: defina DB_HOST, DB_USER, DB_PASSWORD e DB_NAME (ou DATABASE_URL).");
  }
  return { ...common, host: DB_HOST, port: Number(DB_PORT || 3306), user: DB_USER, password: DB_PASSWORD ?? "", database: DB_NAME };
}

export function createDb(): { db: Db; driver: DbDriver } {
  const pool = mysql.createPool(connectionOptions());
  return { db: drizzle(pool, { schema, mode: "default" }), driver: "mysql" };
}
