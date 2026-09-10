<?php

defined('CONTROLADORIA') || exit;

const SP_TIMEZONE = 'America/Sao_Paulo';
const WEEKDAYS_SHORT = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const WEEKDAYS_LONG = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
const WEEKDAY_LABELS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const MONTH_NAMES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

function sp_time(DateTimeInterface $date): DateTimeImmutable
{
    return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone(SP_TIMEZONE));
}

function fmt_date(DateTimeInterface $date): string
{
    return sp_time($date)->format('d/m');
}

function fmt_weekday(DateTimeInterface $date): string
{
    return WEEKDAYS_SHORT[(int) sp_time($date)->format('w')];
}

function fmt_time(DateTimeInterface $date): string
{
    return sp_time($date)->format('H:i');
}

function fmt_datetime(DateTimeInterface $date): string
{
    return sp_time($date)->format('d/m H:i');
}

function fmt_full_date(DateTimeInterface $date): string
{
    $local = sp_time($date);
    return $local->format('d/m/Y') . ' às ' . $local->format('H:i');
}

function fmt_long_day(DateTimeInterface $date): string
{
    $local = sp_time($date);
    return WEEKDAYS_LONG[(int) $local->format('w')] . ', ' . (int) $local->format('j') . ' de ' . MONTH_NAMES[(int) $local->format('n') - 1];
}

function fmt_number($value): string
{
    return number_format((float) $value, 0, ',', '.');
}

function fmt_decimal(float $value, int $digits = 1): string
{
    return number_format($value, $digits, ',', '.');
}

/** 2.50 → "2,5"; 2.00 → "2"; 1.25 → "1,25" */
function fmt_decimal_trim(float $value, int $maxDigits = 2): string
{
    $text = number_format($value, $maxDigits, ',', '.');
    return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
}

/** 182.400 → "182,4 mil"; 1.250.000 → "1,3 mi" */
function fmt_compact($value): string
{
    $number = (float) $value;
    $units = [[1e9, ' bi'], [1e6, ' mi'], [1e3, ' mil']];
    foreach ($units as $index => [$base, $suffix]) {
        if (abs($number) >= $base) {
            $short = round($number / $base, 1);
            // 999.960 arredondaria para "1.000 mil": sobe para a unidade seguinte.
            if (abs($short) >= 1000 && $index > 0) {
                [$base, $suffix] = $units[$index - 1];
                $short = round($number / $base, 1);
            }
            return number_format($short, floor($short) == $short ? 0 : 1, ',', '.') . $suffix;
        }
    }
    return fmt_number($number);
}

function fmt_percent(float $ratio): string
{
    return fmt_number(round($ratio * 100)) . '%';
}

function fmt_ratio(float $ratio): string
{
    return number_format($ratio, 1, ',', '') . 'x';
}

/** AAAA-MM no fuso de São Paulo. */
function month_key(?DateTimeInterface $date = null): string
{
    return sp_time($date ?? utc_now())->format('Y-m');
}

/** AAAA-MM-DD no fuso de São Paulo. */
function day_key(?DateTimeInterface $date = null): string
{
    return sp_time($date ?? utc_now())->format('Y-m-d');
}

function shift_month(string $key, int $delta): string
{
    [$year, $month] = array_map('intval', explode('-', $key));
    return gmdate('Y-m', gmmktime(12, 0, 0, $month + $delta, 15, $year));
}

function days_in_month(string $key): int
{
    [$year, $month] = array_map('intval', explode('-', $key));
    return (int) gmdate('t', gmmktime(12, 0, 0, $month, 15, $year));
}

function month_label(string $key): string
{
    [$year, $month] = array_map('intval', explode('-', $key));
    return mb_convert_case(MONTH_NAMES[$month - 1], MB_CASE_TITLE) . ' de ' . $year;
}

/** Diferença em dias do calendário de São Paulo: hoje = 0, amanhã = 1. */
function days_until(DateTimeInterface $target, ?DateTimeInterface $from = null): int
{
    $utc = new DateTimeZone('UTC');
    $start = new DateTimeImmutable(day_key($from ?? utc_now()), $utc);
    $end = new DateTimeImmutable(day_key($target), $utc);
    return (int) round(($end->getTimestamp() - $start->getTimestamp()) / 86400);
}

function fmt_in_days(int $days): string
{
    if ($days <= 0) {
        return 'hoje';
    }
    return $days === 1 ? 'amanhã' : "em $days dias";
}

function fmt_ago(DateTimeInterface $date): string
{
    $minutes = (int) round((time() - $date->getTimestamp()) / 60);
    if ($minutes < 1) {
        return 'agora';
    }
    if ($minutes < 60) {
        return "há $minutes min";
    }
    $hours = (int) round($minutes / 60);
    if ($hours < 24) {
        return "há $hours h";
    }
    $days = (int) round($hours / 24);
    return $days === 1 ? 'ontem' : "há $days dias";
}

function fmt_duration(?int $seconds): string
{
    if (!$seconds) {
        return '—';
    }
    $minutes = intdiv($seconds, 60);
    if ($minutes >= 60) {
        return intdiv($minutes, 60) . 'h' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
    return $minutes . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

/** Dia da semana, hora, minuto, dia e chaves atuais em São Paulo. */
function now_in_sao_paulo(?DateTimeInterface $date = null): array
{
    $local = sp_time($date ?? utc_now());
    return [
        'weekday' => (int) $local->format('w'),
        'iso_weekday' => (int) $local->format('N'),
        'hour' => (int) $local->format('G'),
        'minute' => (int) $local->format('i'),
        'day' => (int) $local->format('j'),
        'day_key' => $local->format('Y-m-d'),
        'week_key' => $local->format('o-\WW'),
        'month_key' => $local->format('Y-m'),
    ];
}

function plural(int $count, string $one, string $many): string
{
    return fmt_number($count) . ' ' . ($count === 1 ? $one : $many);
}

function describe_user_agent(?string $agent): string
{
    if (!$agent) {
        return 'Aparelho desconhecido';
    }
    $browser = 'Navegador';
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Safari/' => 'Safari'] as $needle => $name) {
        if (str_contains($agent, $needle)) {
            $browser = $name;
            break;
        }
    }
    $system = '';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Mac OS X' => 'Mac', 'Linux' => 'Linux'] as $needle => $name) {
        if (str_contains($agent, $needle)) {
            $system = $name;
            break;
        }
    }
    return $system !== '' ? "$browser no $system" : $browser;
}
