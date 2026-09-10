export const TIME_ZONE = "America/Sao_Paulo";
const DAY = 86_400_000;

const make = (options: Intl.DateTimeFormatOptions, locale = "pt-BR") =>
  new Intl.DateTimeFormat(locale, { timeZone: TIME_ZONE, ...options });

const dayMonth = make({ day: "2-digit", month: "2-digit" });
const weekdayShort = make({ weekday: "short" });
const time = make({ hour: "2-digit", minute: "2-digit" });
const dateTime = make({ day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" });
const fullDate = make({ day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
const longDay = make({ weekday: "long", day: "numeric", month: "long" });
const monthKeyFmt = make({ year: "numeric", month: "2-digit" }, "en-CA");
const dayKeyFmt = make({ year: "numeric", month: "2-digit", day: "2-digit" }, "en-CA");
const partsFmt = make({ weekday: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" }, "en-US");

export const fmtDate = (d: Date) => dayMonth.format(d);
export const fmtWeekday = (d: Date) => weekdayShort.format(d).replace(".", "");
export const fmtTime = (d: Date) => time.format(d);
export const fmtDateTime = (d: Date) => dateTime.format(d).replace(",", "");
export const fmtFullDate = (d: Date) => fullDate.format(d).replace(",", " às");
export const fmtLongDay = (d: Date) => longDay.format(d);

export const fmtNumber = (n: number) => new Intl.NumberFormat("pt-BR").format(n);
export const fmtCompact = (n: number) =>
  new Intl.NumberFormat("pt-BR", { notation: "compact", maximumFractionDigits: 1 }).format(n);
export const fmtPercent = (n: number, digits = 0) =>
  new Intl.NumberFormat("pt-BR", { style: "percent", maximumFractionDigits: digits }).format(n);

/** AAAA-MM no fuso de São Paulo. */
export const monthKey = (d = new Date()) => monthKeyFmt.format(d).slice(0, 7);
/** AAAA-MM-DD no fuso de São Paulo. */
export const dayKey = (d = new Date()) => dayKeyFmt.format(d);

export function shiftMonth(key: string, delta: number) {
  const [y, m] = key.split("-").map(Number);
  const date = new Date(Date.UTC(y, m - 1 + delta, 15));
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
}

export function monthLabel(key: string) {
  const [y, m] = key.split("-").map(Number);
  const label = new Intl.DateTimeFormat("pt-BR", { month: "long", year: "numeric", timeZone: "UTC" }).format(
    new Date(Date.UTC(y, m - 1, 15)),
  );
  return label.charAt(0).toUpperCase() + label.slice(1);
}

/** Dias corridos (arredondado para cima) entre agora e a data. */
export const daysUntil = (d: Date, from = new Date()) => Math.ceil((d.getTime() - from.getTime()) / DAY);

export function fmtInDays(days: number) {
  if (days <= 0) return "hoje";
  if (days === 1) return "amanhã";
  return `em ${days} dias`;
}

export function fmtAgo(d: Date, now = new Date()) {
  const minutes = Math.round((now.getTime() - d.getTime()) / 60_000);
  if (minutes < 1) return "agora";
  if (minutes < 60) return `há ${minutes} min`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `há ${hours} h`;
  const days = Math.round(hours / 24);
  return days === 1 ? "ontem" : `há ${days} dias`;
}

export function fmtDuration(seconds: number | null) {
  if (!seconds) return "—";
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  if (m >= 60) return `${Math.floor(m / 60)}h${String(m % 60).padStart(2, "0")}`;
  return `${m}:${String(s).padStart(2, "0")}`;
}

/** Dia da semana (0 = domingo), hora e minuto atuais em São Paulo. */
export function nowInSaoPaulo(d = new Date()) {
  const parts = Object.fromEntries(partsFmt.formatToParts(d).map((p) => [p.type, p.value]));
  const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  return {
    weekday: weekdays.indexOf(parts.weekday),
    hour: Number(parts.hour),
    minute: Number(parts.minute),
    day: Number(dayKey(d).slice(8, 10)),
    dayKey: dayKey(d),
    monthKey: monthKey(d),
  };
}

export const WEEKDAY_LABEL = ["Domingo", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"];
