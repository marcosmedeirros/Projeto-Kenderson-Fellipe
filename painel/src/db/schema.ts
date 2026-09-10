import {
  boolean,
  index,
  integer,
  jsonb,
  pgTable,
  primaryKey,
  real,
  serial,
  text,
  timestamp,
  uuid,
} from "drizzle-orm/pg-core";

const ts = (name: string) => timestamp(name, { withTimezone: true });

/* ---------------- Acesso ---------------- */

export const users = pgTable("users", {
  id: uuid("id").primaryKey().defaultRandom(),
  name: text("name").notNull(),
  email: text("email").notNull().unique(),
  passwordHash: text("password_hash").notNull(),
  role: text("role", { enum: ["admin", "editor", "visualizador"] }).notNull().default("visualizador"),
  active: boolean("active").notNull().default(true),
  mustChangePassword: boolean("must_change_password").notNull().default(false),
  /** Segredo TOTP criptografado (AES-256-GCM). */
  totpSecret: text("totp_secret"),
  totpEnabled: boolean("totp_enabled").notNull().default(false),
  /** Último passo de tempo aceito, para impedir reuso do mesmo código. */
  totpLastStep: integer("totp_last_step"),
  lastLoginAt: ts("last_login_at"),
  createdAt: ts("created_at").notNull().defaultNow(),
});

export const sessions = pgTable(
  "sessions",
  {
    /** SHA-256 do token do cookie. O token em si nunca é salvo. */
    id: text("id").primaryKey(),
    userId: uuid("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    twoFactorPending: boolean("two_factor_pending").notNull().default(false),
    twoFactorAttempts: integer("two_factor_attempts").notNull().default(0),
    ip: text("ip"),
    userAgent: text("user_agent"),
    createdAt: ts("created_at").notNull().defaultNow(),
    lastSeenAt: ts("last_seen_at").notNull().defaultNow(),
    expiresAt: ts("expires_at").notNull(),
  },
  (t) => [index("sessions_user_idx").on(t.userId)],
);

export const loginAttempts = pgTable(
  "login_attempts",
  {
    id: serial("id").primaryKey(),
    email: text("email"),
    ip: text("ip"),
    success: boolean("success").notNull(),
    createdAt: ts("created_at").notNull().defaultNow(),
  },
  (t) => [
    index("login_attempts_email_idx").on(t.email, t.createdAt),
    index("login_attempts_ip_idx").on(t.ip, t.createdAt),
  ],
);

export const auditLogs = pgTable(
  "audit_logs",
  {
    id: serial("id").primaryKey(),
    userId: uuid("user_id").references(() => users.id, { onDelete: "set null" }),
    action: text("action").notNull(),
    detail: jsonb("detail").$type<Record<string, unknown>>(),
    ip: text("ip"),
    createdAt: ts("created_at").notNull().defaultNow(),
  },
  (t) => [index("audit_logs_created_idx").on(t.createdAt)],
);

/* ---------------- Canal ---------------- */

export const videos = pgTable(
  "videos",
  {
    /** ID do vídeo no YouTube. */
    id: text("id").primaryKey(),
    title: text("title").notNull(),
    status: text("status", { enum: ["agendado", "publicado", "privado", "rascunho"] }).notNull(),
    publishAt: ts("publish_at"),
    publishedAt: ts("published_at"),
    durationSec: integer("duration_sec"),
    isShort: boolean("is_short").notNull().default(false),
    thumbnailStatus: text("thumbnail_status", { enum: ["ok", "pendente", "desconhecido"] })
      .notNull()
      .default("desconhecido"),
    thumbnailSource: text("thumbnail_source", { enum: ["automatico", "manual"] }),
    thumbnailUpdatedBy: uuid("thumbnail_updated_by").references(() => users.id, { onDelete: "set null" }),
    thumbnailUpdatedAt: ts("thumbnail_updated_at"),
    views: integer("views").notNull().default(0),
    likes: integer("likes").notNull().default(0),
    comments: integer("comments").notNull().default(0),
    /** Views nos 7 primeiros dias, usado para detectar vídeos virais. */
    views7d: integer("views_7d"),
    syncedAt: ts("synced_at"),
  },
  (t) => [index("videos_status_idx").on(t.status, t.publishAt)],
);

export const videoMetrics = pgTable(
  "video_metrics",
  {
    videoId: text("video_id")
      .notNull()
      .references(() => videos.id, { onDelete: "cascade" }),
    /** Mês no formato AAAA-MM. */
    month: text("month").notNull(),
    views: integer("views").notNull().default(0),
    watchMinutes: integer("watch_minutes").notNull().default(0),
    avgViewPct: real("avg_view_pct").notNull().default(0),
    subscribersGained: integer("subscribers_gained").notNull().default(0),
    likes: integer("likes").notNull().default(0),
    comments: integer("comments").notNull().default(0),
  },
  (t) => [primaryKey({ columns: [t.videoId, t.month] })],
);

export const channelDaily = pgTable("channel_daily", {
  /** Dia no formato AAAA-MM-DD (fuso de São Paulo). */
  day: text("day").primaryKey(),
  views: integer("views").notNull().default(0),
  watchMinutes: integer("watch_minutes").notNull().default(0),
  subscribersGained: integer("subscribers_gained").notNull().default(0),
});

export const searchTerms = pgTable(
  "search_terms",
  {
    month: text("month").notNull(),
    term: text("term").notNull(),
    views: integer("views").notNull().default(0),
  },
  (t) => [primaryKey({ columns: [t.month, t.term] })],
);

export const ideas = pgTable("ideas", {
  id: serial("id").primaryKey(),
  title: text("title").notNull(),
  rationale: text("rationale").notNull(),
  source: text("source", { enum: ["buscas", "comentarios", "desempenho", "manual"] }).notNull(),
  score: integer("score").notNull().default(50),
  status: text("status", { enum: ["nova", "aprovada", "gravada", "descartada"] }).notNull().default("nova"),
  generatedBy: text("generated_by", { enum: ["ia", "exemplo", "usuario"] }).notNull().default("usuario"),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  createdAt: ts("created_at").notNull().defaultNow(),
  updatedAt: ts("updated_at").notNull().defaultNow(),
});

/* ---------------- Bot e configurações ---------------- */

export const botMessages = pgTable(
  "bot_messages",
  {
    id: serial("id").primaryKey(),
    direction: text("direction", { enum: ["entrada", "saida"] }).notNull(),
    origin: text("origin", { enum: ["whatsapp", "simulador", "automacao"] }).notNull(),
    chatId: text("chat_id"),
    sender: text("sender"),
    text: text("text").notNull(),
    command: text("command"),
    delivered: boolean("delivered").notNull().default(true),
    createdAt: ts("created_at").notNull().defaultNow(),
  },
  (t) => [index("bot_messages_created_idx").on(t.createdAt)],
);

export const settings = pgTable("settings", {
  key: text("key").primaryKey(),
  value: jsonb("value").notNull(),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  updatedAt: ts("updated_at").notNull().defaultNow(),
});

export const integrations = pgTable("integrations", {
  key: text("key", { enum: ["youtube", "evolution", "gemini"] }).primaryKey(),
  /** JSON criptografado com as credenciais. */
  config: text("config"),
  status: text("status", { enum: ["desconectado", "conectado", "erro"] }).notNull().default("desconectado"),
  lastSyncAt: ts("last_sync_at"),
  lastError: text("last_error"),
  updatedBy: uuid("updated_by").references(() => users.id, { onDelete: "set null" }),
  updatedAt: ts("updated_at").notNull().defaultNow(),
});
