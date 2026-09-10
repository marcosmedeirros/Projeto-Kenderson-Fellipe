import { randomUUID } from "node:crypto";
import {
  boolean,
  customType,
  datetime,
  double,
  index,
  int,
  mysqlTable,
  primaryKey,
  text,
  varchar,
} from "drizzle-orm/mysql-core";

// Compatível com MySQL 5.7+/8 e MariaDB 10.4+ (Hostinger).
// Datas ficam em UTC; chaves de texto indexadas usam no máximo 191 caracteres (limite do utf8mb4).

const ts = (name: string) => datetime(name, { mode: "date", fsp: 3 });
const now = () => new Date();
const uuid = (name: string) => varchar(name, { length: 36 });

/** JSON salvo como texto: funciona igual em MySQL e MariaDB. */
const jsonText = <T>(name: string) =>
  customType<{ data: T; driverData: string }>({
    dataType: () => "longtext",
    toDriver: (value) => JSON.stringify(value),
    fromDriver: (value) => JSON.parse(value) as T,
  })(name);

/* ---------------- Acesso ---------------- */

export const users = mysqlTable("users", {
  id: uuid("id").primaryKey().$defaultFn(randomUUID),
  name: varchar("name", { length: 120 }).notNull(),
  email: varchar("email", { length: 191 }).notNull().unique(),
  passwordHash: varchar("password_hash", { length: 255 }).notNull(),
  role: varchar("role", { length: 20, enum: ["admin", "editor", "visualizador"] }).notNull().default("visualizador"),
  active: boolean("active").notNull().default(true),
  mustChangePassword: boolean("must_change_password").notNull().default(false),
  /** Segredo TOTP criptografado (AES-256-GCM). */
  totpSecret: text("totp_secret"),
  totpEnabled: boolean("totp_enabled").notNull().default(false),
  /** Último passo de tempo aceito, para impedir reuso do mesmo código. */
  totpLastStep: int("totp_last_step"),
  lastLoginAt: ts("last_login_at"),
  createdAt: ts("created_at").notNull().$defaultFn(now),
});

export const sessions = mysqlTable(
  "sessions",
  {
    /** SHA-256 do token do cookie. O token em si nunca é salvo. */
    id: varchar("id", { length: 64 }).primaryKey(),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    twoFactorPending: boolean("two_factor_pending").notNull().default(false),
    twoFactorAttempts: int("two_factor_attempts").notNull().default(0),
    ip: varchar("ip", { length: 64 }),
    userAgent: varchar("user_agent", { length: 300 }),
    createdAt: ts("created_at").notNull().$defaultFn(now),
    lastSeenAt: ts("last_seen_at").notNull().$defaultFn(now),
    expiresAt: ts("expires_at").notNull(),
  },
  (t) => [index("sessions_user_idx").on(t.userId)],
);

export const loginAttempts = mysqlTable(
  "login_attempts",
  {
    id: int("id").autoincrement().primaryKey(),
    email: varchar("email", { length: 191 }),
    ip: varchar("ip", { length: 64 }),
    success: boolean("success").notNull(),
    createdAt: ts("created_at").notNull().$defaultFn(now),
  },
  (t) => [
    index("login_attempts_email_idx").on(t.email, t.createdAt),
    index("login_attempts_ip_idx").on(t.ip, t.createdAt),
  ],
);

export const auditLogs = mysqlTable(
  "audit_logs",
  {
    id: int("id").autoincrement().primaryKey(),
    userId: uuid("user_id").references(() => users.id, { onDelete: "set null" }),
    action: varchar("action", { length: 60 }).notNull(),
    detail: jsonText<Record<string, unknown>>("detail"),
    ip: varchar("ip", { length: 64 }),
    createdAt: ts("created_at").notNull().$defaultFn(now),
  },
  (t) => [index("audit_logs_created_idx").on(t.createdAt)],
);

/* ---------------- Canal ---------------- */

export const videos = mysqlTable(
  "videos",
  {
    /** ID do vídeo no YouTube. */
    id: varchar("id", { length: 64 }).primaryKey(),
    title: varchar("title", { length: 255 }).notNull(),
    status: varchar("status", { length: 12, enum: ["agendado", "publicado", "privado", "rascunho"] }).notNull(),
    publishAt: ts("publish_at"),
    publishedAt: ts("published_at"),
    durationSec: int("duration_sec"),
    isShort: boolean("is_short").notNull().default(false),
    thumbnailStatus: varchar("thumbnail_status", { length: 15, enum: ["ok", "pendente", "desconhecido"] })
      .notNull()
      .default("desconhecido"),
    thumbnailSource: varchar("thumbnail_source", { length: 15, enum: ["automatico", "manual"] }),
    thumbnailUpdatedBy: uuid("thumbnail_updated_by").references(() => users.id, { onDelete: "set null" }),
    thumbnailUpdatedAt: ts("thumbnail_updated_at"),
    views: int("views").notNull().default(0),
    likes: int("likes").notNull().default(0),
    comments: int("comments").notNull().default(0),
    /** Views nos 7 primeiros dias, usado para detectar vídeos virais. */
    views7d: int("views_7d"),
    syncedAt: ts("synced_at"),
  },
  (t) => [index("videos_status_idx").on(t.status, t.publishAt)],
);

export const videoMetrics = mysqlTable(
  "video_metrics",
  {
    videoId: varchar("video_id", { length: 64 })
      .notNull()
      .references(() => videos.id, { onDelete: "cascade" }),
    /** Mês no formato AAAA-MM. */
    month: varchar("month", { length: 7 }).notNull(),
    views: int("views").notNull().default(0),
    watchMinutes: int("watch_minutes").notNull().default(0),
    avgViewPct: double("avg_view_pct").notNull().default(0),
    subscribersGained: int("subscribers_gained").notNull().default(0),
    likes: int("likes").notNull().default(0),
    comments: int("comments").notNull().default(0),
  },
  (t) => [primaryKey({ columns: [t.videoId, t.month] })],
);

export const channelDaily = mysqlTable("channel_daily", {
  /** Dia no formato AAAA-MM-DD (fuso de São Paulo). */
  day: varchar("day", { length: 10 }).primaryKey(),
  views: int("views").notNull().default(0),
  watchMinutes: int("watch_minutes").notNull().default(0),
  subscribersGained: int("subscribers_gained").notNull().default(0),
});

export const searchTerms = mysqlTable(
  "search_terms",
  {
    month: varchar("month", { length: 7 }).notNull(),
    term: varchar("term", { length: 191 }).notNull(),
    views: int("views").notNull().default(0),
  },
  (t) => [primaryKey({ columns: [t.month, t.term] })],
);

export const ideas = mysqlTable("ideas", {
  id: int("id").autoincrement().primaryKey(),
  title: varchar("title", { length: 200 }).notNull(),
  rationale: text("rationale").notNull(),
  source: varchar("source", { length: 15, enum: ["buscas", "comentarios", "desempenho", "manual"] }).notNull(),
  score: int("score").notNull().default(50),
  status: varchar("status", { length: 12, enum: ["nova", "aprovada", "gravada", "descartada"] }).notNull().default("nova"),
  generatedBy: varchar("generated_by", { length: 10, enum: ["ia", "exemplo", "usuario"] }).notNull().default("usuario"),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  createdAt: ts("created_at").notNull().$defaultFn(now),
  updatedAt: ts("updated_at").notNull().$defaultFn(now),
});

/* ---------------- Bot e configurações ---------------- */

export const botMessages = mysqlTable(
  "bot_messages",
  {
    id: int("id").autoincrement().primaryKey(),
    direction: varchar("direction", { length: 10, enum: ["entrada", "saida"] }).notNull(),
    origin: varchar("origin", { length: 12, enum: ["whatsapp", "simulador", "automacao"] }).notNull(),
    chatId: varchar("chat_id", { length: 80 }),
    sender: varchar("sender", { length: 120 }),
    text: text("text").notNull(),
    command: varchar("command", { length: 40 }),
    delivered: boolean("delivered").notNull().default(true),
    createdAt: ts("created_at").notNull().$defaultFn(now),
  },
  (t) => [index("bot_messages_created_idx").on(t.createdAt)],
);

export const settings = mysqlTable("settings", {
  key: varchar("key", { length: 40 }).primaryKey(),
  value: jsonText<unknown>("value").notNull(),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  updatedAt: ts("updated_at").notNull().$defaultFn(now),
});

export const integrations = mysqlTable("integrations", {
  key: varchar("key", { length: 20, enum: ["youtube", "evolution", "gemini"] }).primaryKey(),
  /** JSON criptografado com as credenciais. */
  config: text("config"),
  status: varchar("status", { length: 15, enum: ["desconectado", "conectado", "erro"] }).notNull().default("desconectado"),
  lastSyncAt: ts("last_sync_at"),
  lastError: text("last_error"),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  updatedAt: ts("updated_at").notNull().$defaultFn(now),
});
