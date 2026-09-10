<?php

defined('CONTROLADORIA') || exit;

/* ---------------- Programados e capas ---------------- */

function scheduled_videos(): array
{
    $rows = db_all(
        "SELECT id, title, publish_at, is_short, duration_sec, thumbnail_status, thumbnail_source, thumbnail_updated_at
           FROM videos WHERE status = 'agendado' AND publish_at > ? ORDER BY publish_at",
        [to_db(utc_now())]
    );
    return array_map(static fn (array $row) => [
        'id' => $row['id'],
        'title' => $row['title'],
        'publish_at' => from_db($row['publish_at']),
        'is_short' => (bool) $row['is_short'],
        'duration_sec' => $row['duration_sec'] !== null ? (int) $row['duration_sec'] : null,
        'thumbnail_status' => $row['thumbnail_status'],
        'thumbnail_source' => $row['thumbnail_source'],
        'thumbnail_updated_at' => from_db($row['thumbnail_updated_at']),
    ], $rows);
}

function summarize_stock(array $videos, ?DateTimeInterface $now = null): array
{
    $last = $videos ? end($videos)['publish_at'] : null;
    $pending = array_values(array_filter($videos, static fn ($video) => $video['thumbnail_status'] !== 'ok'));
    return [
        'total' => count($videos),
        'shorts' => count(array_filter($videos, static fn ($video) => $video['is_short'])),
        'last_date' => $last,
        'days_covered' => $last ? max(0, days_until($last, $now)) : 0,
        'missing_thumbs' => count($pending),
        'next_missing_thumb' => $pending[0] ?? null,
        'next' => $videos[0] ?? null,
    ];
}

/* ---------------- Desempenho ---------------- */

function month_totals(string $month, ?int $untilDay = null): array
{
    $sql = 'SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(watch_minutes), 0) AS watch_minutes,
                   COALESCE(SUM(subscribers_gained), 0) AS subscribers, COUNT(*) AS days
              FROM channel_daily WHERE day LIKE ?';
    $params = [$month . '-%'];
    if ($untilDay !== null) {
        $sql .= ' AND day <= ?';
        $params[] = $month . '-' . str_pad((string) $untilDay, 2, '0', STR_PAD_LEFT);
    }
    $row = db_one($sql, $params);
    return array_map('intval', $row ?? ['views' => 0, 'watch_minutes' => 0, 'subscribers' => 0, 'days' => 0]);
}

/** Mês atual até hoje, comparado com o mesmo número de dias do mês anterior. */
function month_to_date(): array
{
    $current = month_key();
    $today = (int) substr(day_key(), 8, 2);
    $thisMonth = month_totals($current, $today);
    $lastMonth = month_totals(shift_month($current, -1), $today);
    $change = $lastMonth['views'] > 0 ? ($thisMonth['views'] - $lastMonth['views']) / $lastMonth['views'] : null;
    return $thisMonth + ['month' => $current, 'previous_views' => $lastMonth['views'], 'change' => $change];
}

function daily_series(?int $days = 30, ?string $month = null): array
{
    if ($month !== null) {
        $rows = db_all('SELECT day, views, watch_minutes, subscribers_gained FROM channel_daily WHERE day LIKE ? ORDER BY day', [$month . '-%']);
    } else {
        $rows = db_all('SELECT day, views, watch_minutes, subscribers_gained FROM channel_daily WHERE day >= ? ORDER BY day', [day_key(utc_now()->modify("-$days days"))]);
    }
    return array_map(static fn ($row) => [
        'day' => $row['day'],
        'views' => (int) $row['views'],
        'watch_minutes' => (int) $row['watch_minutes'],
        'subscribers_gained' => (int) $row['subscribers_gained'],
    ], $rows);
}

function day_label(string $day): string
{
    return substr($day, 8, 2) . '/' . substr($day, 5, 2);
}

function top_videos(string $month, int $limit = 8): array
{
    $rows = db_all(
        'SELECT v.id, v.title, v.is_short, m.views, m.watch_minutes, m.avg_view_pct, m.subscribers_gained
           FROM video_metrics m JOIN videos v ON v.id = m.video_id
          WHERE m.month = ? ORDER BY m.views DESC LIMIT ' . max(1, $limit),
        [$month]
    );
    return array_map(static fn ($row) => [
        'id' => $row['id'],
        'title' => $row['title'],
        'is_short' => (bool) $row['is_short'],
        'views' => (int) $row['views'],
        'watch_minutes' => (int) $row['watch_minutes'],
        'avg_view_pct' => (float) $row['avg_view_pct'],
        'subscribers_gained' => (int) $row['subscribers_gained'],
    ], $rows);
}

function month_retention(string $month): float
{
    return (float) db_value(
        'SELECT COALESCE(SUM(avg_view_pct * views) / NULLIF(SUM(views), 0), 0) FROM video_metrics WHERE month = ?',
        [$month]
    );
}

function median(array $values): float
{
    if (!$values) {
        return 0.0;
    }
    sort($values);
    $middle = intdiv(count($values), 2);
    return count($values) % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
}

/**
 * Viral = views nos 7 primeiros dias acima de N vezes a mediana do canal
 * (Shorts e vídeos longos comparados separadamente).
 */
function viral_videos(float $multiplier, int $days = 45): array
{
    $rows = db_all(
        "SELECT id, title, is_short, published_at, views, views_7d, likes, comments
           FROM videos WHERE status = 'publicado' AND views_7d IS NOT NULL AND published_at >= ?
          ORDER BY published_at DESC",
        [to_db(utc_now()->modify('-120 days'))]
    );
    $videos = array_map(static fn ($row) => [
        'id' => $row['id'],
        'title' => $row['title'],
        'is_short' => (bool) $row['is_short'],
        'published_at' => from_db($row['published_at']),
        'views' => (int) $row['views'],
        'views_7d' => (int) $row['views_7d'],
    ], $rows);

    $medians = [
        'longos' => median(array_column(array_filter($videos, static fn ($v) => !$v['is_short']), 'views_7d')),
        'shorts' => median(array_column(array_filter($videos, static fn ($v) => $v['is_short']), 'views_7d')),
    ];
    $since = time() - $days * 86400;
    $recent = [];
    foreach ($videos as $video) {
        if ($video['published_at'] === null || $video['published_at']->getTimestamp() < $since) {
            continue;
        }
        $base = $video['is_short'] ? $medians['shorts'] : $medians['longos'];
        $video['ratio'] = $base > 0 ? $video['views_7d'] / $base : 0.0;
        $recent[] = $video;
    }
    usort($recent, static fn ($a, $b) => $b['ratio'] <=> $a['ratio']);

    return [
        'medians' => $medians,
        'items' => array_values(array_filter($recent, static fn ($video) => $video['ratio'] >= $multiplier)),
        'recent' => $recent,
    ];
}

function search_terms(string $month, int $limit = 10): array
{
    $rows = db_all('SELECT term, views FROM search_terms WHERE month = ? ORDER BY views DESC LIMIT ' . max(1, $limit), [$month]);
    return array_map(static fn ($row) => ['term' => $row['term'], 'views' => (int) $row['views']], $rows);
}

/** Termos que mais cresceram em relação ao mês anterior. */
function rising_terms(string $month, int $limit = 6): array
{
    $previous = [];
    foreach (search_terms(shift_month($month, -1), 50) as $row) {
        $previous[$row['term']] = $row['views'];
    }
    $terms = [];
    foreach (search_terms($month, 30) as $row) {
        $before = $previous[$row['term']] ?? 0;
        $terms[] = $row + ['before' => $before, 'growth' => $before > 0 ? ($row['views'] - $before) / $before : null];
    }
    usort($terms, static fn ($a, $b) => ($b['growth'] ?? 99) <=> ($a['growth'] ?? 99));
    return array_slice($terms, 0, $limit);
}

/* ---------------- Ideias e bot ---------------- */

const IDEA_STATUSES = ['nova', 'aprovada', 'gravada', 'descartada'];

function ideas_list(?array $statuses = null): array
{
    $sql = 'SELECT id, title, rationale, source, score, status, generated_by, created_at FROM ideas';
    $params = [];
    if ($statuses) {
        $sql .= ' WHERE status IN (' . db_placeholders($statuses) . ')';
        $params = $statuses;
    }
    $rows = db_all($sql . ' ORDER BY score DESC, created_at DESC', $params);
    return array_map(static fn ($row) => [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'rationale' => $row['rationale'],
        'source' => $row['source'],
        'score' => (int) $row['score'],
        'status' => $row['status'],
        'generated_by' => $row['generated_by'],
        'created_at' => from_db($row['created_at']),
    ], $rows);
}

function idea_counts(): array
{
    $counts = array_fill_keys(IDEA_STATUSES, 0);
    foreach (db_all('SELECT status, COUNT(*) AS total FROM ideas GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }
    return $counts;
}

function recent_bot_messages(int $limit = 20): array
{
    $rows = db_all('SELECT id, direction, origin, chat_id, sender, text, command, delivered, created_at FROM bot_messages ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit));
    return array_map(static fn ($row) => [
        'id' => (int) $row['id'],
        'direction' => $row['direction'],
        'origin' => $row['origin'],
        'sender' => $row['sender'],
        'text' => $row['text'],
        'command' => $row['command'],
        'delivered' => (bool) $row['delivered'],
        'created_at' => from_db($row['created_at']),
    ], $rows);
}
