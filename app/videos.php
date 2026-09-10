<?php

defined('CONTROLADORIA') || exit;

// Cadastro manual de vídeos, ideias e da biblioteca de capas.

const VIDEO_STATUSES = ['agendado' => 'Agendado', 'rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'privado' => 'Privado'];
const VIDEO_STATUS_TONES = ['agendado' => 'info', 'rascunho' => 'neutral', 'publicado' => 'ok', 'privado' => 'warn'];
const VIDEO_SOURCES = ['manual' => 'Cadastro manual', 'youtube' => 'YouTube', 'exemplo' => 'Exemplo'];
const VIDEO_THUMB_STATUSES = ['pendente' => 'Sem capa', 'ok' => 'Capa pronta', 'desconhecido' => 'Não verificada'];
const THUMBNAIL_STATUSES = ['rascunho' => 'Rascunho', 'aprovada' => 'Aprovada', 'usada' => 'Em uso', 'arquivada' => 'Arquivada'];
const THUMBNAIL_STATUS_TONES = ['rascunho' => 'neutral', 'aprovada' => 'info', 'usada' => 'ok', 'arquivada' => 'warn'];
const IDEA_SOURCES = ['buscas' => 'Buscas', 'comentarios' => 'Comentários', 'desempenho' => 'Desempenho', 'manual' => 'Manual'];

/* ---------------- Conversões de formulário ---------------- */

/** Aceita link do YouTube (watch, youtu.be, shorts, Studio) ou o ID de 11 caracteres. null = vazio, false = inválido. */
function parse_youtube_id(string $input)
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
        return $input;
    }
    $pattern = '~(?:youtu\.be/|youtube\.com/(?:watch\?(?:[^#]*&)?v=|shorts/|live/|embed/)|studio\.youtube\.com/video/)([A-Za-z0-9_-]{11})~i';
    return preg_match($pattern, $input, $match) ? $match[1] : false;
}

/** Campo datetime-local (horário de São Paulo) → data em UTC. */
function parse_local_datetime(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone(SP_TIMEZONE));
    if ($date === false || $date->format('Y-m-d\TH:i') !== $value) {
        return null;
    }
    return $date->setTimezone(new DateTimeZone('UTC'));
}

function local_datetime_input(?DateTimeInterface $date): string
{
    return $date ? sp_time($date)->format('Y-m-d\TH:i') : '';
}

/** "12:30", "1:02:03" ou segundos. null = vazio, false = inválido. */
function parse_duration(string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (ctype_digit($value)) {
        return (int) $value;
    }
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,3}):([0-5]\d)$/', $value, $match)) {
        return false;
    }
    return (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) $match[3];
}

function duration_input(?int $seconds): string
{
    if (!$seconds) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours > 0 ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60) : sprintf('%d:%02d', $minutes, $seconds % 60);
}

/* ---------------- Vídeos ---------------- */

function hydrate_video(array $row): array
{
    return [
        'id' => $row['id'],
        'youtube_id' => $row['youtube_id'] ?? null,
        'title' => $row['title'],
        'status' => $row['status'],
        'source' => $row['source'] ?? 'youtube',
        'publish_at' => from_db($row['publish_at'] ?? null),
        'published_at' => from_db($row['published_at'] ?? null),
        'duration_sec' => isset($row['duration_sec']) ? (int) $row['duration_sec'] : null,
        'is_short' => (bool) ($row['is_short'] ?? false),
        'thumbnail_status' => $row['thumbnail_status'] ?? 'desconhecido',
        'thumbnail_id' => isset($row['thumbnail_id']) ? (int) $row['thumbnail_id'] : null,
        'views' => (int) ($row['views'] ?? 0),
        'views_7d' => isset($row['views_7d']) ? (int) $row['views_7d'] : null,
        'notes' => $row['notes'] ?? null,
    ];
}

/** Data que importa para o status: publicação feita ou agendada. */
function video_main_date(array $video): ?DateTimeImmutable
{
    return $video['status'] === 'publicado'
        ? ($video['published_at'] ?? $video['publish_at'])
        : ($video['publish_at'] ?? $video['published_at']);
}

function find_video(string $id): ?array
{
    if ($id === '' || strlen($id) > 64) {
        return null;
    }
    $row = db_one('SELECT * FROM videos WHERE id = ?', [$id]);
    return $row !== null ? hydrate_video($row) : null;
}

function list_videos(string $status = '', string $search = ''): array
{
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = 'title LIKE ?';
        $params[] = '%' . addcslashes($search, '%_\\') . '%';
    }
    $sql = 'SELECT * FROM videos' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY CASE status WHEN 'agendado' THEN 0 WHEN 'rascunho' THEN 1 WHEN 'privado' THEN 2 ELSE 3 END,
                    CASE WHEN status = 'agendado' THEN publish_at END ASC,
                    COALESCE(published_at, publish_at, created_at) DESC
           LIMIT 500";
    return array_map('hydrate_video', db_all($sql, $params));
}

function video_status_counts(): array
{
    $counts = array_fill_keys(array_keys(VIDEO_STATUSES), 0);
    foreach (db_all('SELECT status, COUNT(*) AS total FROM videos GROUP BY status') as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['total'];
        }
    }
    return $counts;
}

function video_options(): array
{
    return db_all(
        "SELECT id, title, status FROM videos
          ORDER BY CASE status WHEN 'agendado' THEN 0 WHEN 'rascunho' THEN 1 ELSE 2 END, COALESCE(publish_at, published_at, created_at) DESC
          LIMIT 300"
    );
}

function empty_video_form(): array
{
    return [
        'id' => '', 'title' => '', 'status' => 'agendado', 'publish_at' => '', 'youtube' => '', 'is_short' => false,
        'duration' => '', 'thumbnail_status' => 'pendente', 'thumbnail_id' => '', 'notes' => '', 'views' => '', 'views_7d' => '',
    ];
}

function video_to_form(array $video): array
{
    return [
        'id' => $video['id'],
        'title' => $video['title'],
        'status' => $video['status'],
        'publish_at' => local_datetime_input(video_main_date($video)),
        'youtube' => (string) $video['youtube_id'],
        'is_short' => $video['is_short'],
        'duration' => duration_input($video['duration_sec']),
        'thumbnail_status' => $video['thumbnail_status'],
        'thumbnail_id' => $video['thumbnail_id'] !== null ? (string) $video['thumbnail_id'] : '',
        'notes' => (string) $video['notes'],
        'views' => $video['views'] > 0 ? (string) $video['views'] : '',
        'views_7d' => $video['views_7d'] !== null ? (string) $video['views_7d'] : '',
    ];
}

function video_form_from_post(): array
{
    return [
        'id' => post_string('id', 64),
        'title' => post_string('title', 255),
        'status' => post_string('status', 12),
        'publish_at' => post_string('publish_at', 16),
        'youtube' => post_string('youtube', 200),
        'is_short' => post_checkbox('is_short'),
        'duration' => post_string('duration', 10),
        'thumbnail_status' => post_string('thumbnail_status', 15),
        'thumbnail_id' => post_string('thumbnail_id', 10),
        'notes' => post_string('notes', 2000),
        'views' => post_string('views', 12),
        'views_7d' => post_string('views_7d', 12),
    ];
}

/** @return array{0: ?array, 1: ?string} colunas prontas para gravar ou a mensagem de erro */
function validate_video_form(array $form): array
{
    if (mb_strlen($form['title']) < 3) {
        return [null, 'O título precisa ter pelo menos 3 caracteres.'];
    }
    if (!isset(VIDEO_STATUSES[$form['status']])) {
        return [null, 'Escolha um status válido.'];
    }
    $date = $form['publish_at'] !== '' ? parse_local_datetime($form['publish_at']) : null;
    if ($form['publish_at'] !== '' && $date === null) {
        return [null, 'Data e hora da publicação inválidas.'];
    }
    if (in_array($form['status'], ['agendado', 'publicado'], true) && $date === null) {
        return [null, 'Informe a data e a hora da publicação.'];
    }
    if ($form['status'] === 'agendado' && $date <= utc_now()) {
        return [null, 'Para agendar, escolha uma data e hora no futuro.'];
    }

    $youtubeId = parse_youtube_id($form['youtube']);
    if ($youtubeId === false) {
        return [null, 'Link ou ID do YouTube inválido.'];
    }
    if ($youtubeId !== null && db_value('SELECT id FROM videos WHERE youtube_id = ? AND id <> ?', [$youtubeId, $form['id']]) !== null) {
        return [null, 'Esse vídeo do YouTube já está cadastrado.'];
    }

    $duration = parse_duration($form['duration']);
    if ($duration === false) {
        return [null, 'Use a duração no formato 12:30 ou 1:02:03.'];
    }
    if (!isset(VIDEO_THUMB_STATUSES[$form['thumbnail_status']])) {
        return [null, 'Escolha a situação da capa.'];
    }

    $thumbnailId = null;
    if ($form['thumbnail_id'] !== '') {
        $thumbnailId = filter_var($form['thumbnail_id'], FILTER_VALIDATE_INT);
        if ($thumbnailId === false || db_value('SELECT id FROM thumbnails WHERE id = ?', [$thumbnailId]) === null) {
            return [null, 'A capa escolhida não existe mais.'];
        }
    }

    $positive = ['options' => ['min_range' => 0]];
    $views = $form['views'] === '' ? 0 : filter_var($form['views'], FILTER_VALIDATE_INT, $positive);
    $views7d = $form['views_7d'] === '' ? null : filter_var($form['views_7d'], FILTER_VALIDATE_INT, $positive);
    if ($views === false || $views7d === false) {
        return [null, 'Os números de views precisam ser inteiros, sem pontos.'];
    }

    return [[
        'title' => $form['title'],
        'status' => $form['status'],
        'publish_at' => $date ? to_db($date) : null,
        'published_at' => $form['status'] === 'publicado' && $date ? to_db($date) : null,
        'youtube_id' => $youtubeId,
        'is_short' => $form['is_short'] ? 1 : 0,
        'duration_sec' => $duration,
        'thumbnail_status' => $thumbnailId !== null ? 'ok' : $form['thumbnail_status'],
        'thumbnail_id' => $thumbnailId,
        'notes' => $form['notes'] !== '' ? $form['notes'] : null,
        'views' => $views,
        'views_7d' => $views7d,
    ], null];
}

/* ---------------- Biblioteca de capas ---------------- */

function hydrate_thumbnail(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'notes' => $row['notes'],
        'status' => $row['status'],
        'file_name' => $row['file_name'],
        'mime' => $row['mime'],
        'width' => $row['width'] !== null ? (int) $row['width'] : null,
        'height' => $row['height'] !== null ? (int) $row['height'] : null,
        'size_bytes' => (int) $row['size_bytes'],
        'video_id' => $row['video_id'],
        'video_title' => $row['video_title'] ?? null,
        'in_use' => (int) ($row['uses'] ?? 0) > 0,
        'created_at' => from_db($row['created_at']),
    ];
}

const THUMBNAIL_SELECT = 'SELECT t.*, v.title AS video_title, (SELECT COUNT(*) FROM videos u WHERE u.thumbnail_id = t.id) AS uses
                            FROM thumbnails t LEFT JOIN videos v ON v.id = t.video_id';

function list_thumbnails(string $status = ''): array
{
    $sql = THUMBNAIL_SELECT . ($status !== '' ? ' WHERE t.status = ?' : '') . ' ORDER BY t.created_at DESC, t.id DESC LIMIT 300';
    return array_map('hydrate_thumbnail', db_all($sql, $status !== '' ? [$status] : []));
}

function find_thumbnail(int $id): ?array
{
    $row = db_one(THUMBNAIL_SELECT . ' WHERE t.id = ?', [$id]);
    return $row !== null ? hydrate_thumbnail($row) : null;
}

function thumbnail_status_counts(): array
{
    $counts = array_fill_keys(array_keys(THUMBNAIL_STATUSES), 0);
    foreach (db_all('SELECT status, COUNT(*) AS total FROM thumbnails GROUP BY status') as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['total'];
        }
    }
    return $counts;
}

function thumbnail_options(): array
{
    return db_all(
        "SELECT t.id, t.title, v.title AS video_title FROM thumbnails t LEFT JOIN videos v ON v.id = t.video_id
          WHERE t.status <> 'arquivada' ORDER BY t.created_at DESC LIMIT 300"
    );
}

/** Marca a capa como "em uso" no vídeo; outras capas que estavam em uso nesse vídeo voltam para "aprovada". */
function mark_thumbnail_in_use(int $thumbnailId, string $videoId): void
{
    $now = to_db(utc_now());
    db_exec("UPDATE thumbnails SET status = 'aprovada', updated_at = ? WHERE video_id = ? AND status = 'usada' AND id <> ?", [$now, $videoId, $thumbnailId]);
    db_exec("UPDATE thumbnails SET status = 'usada', video_id = ?, updated_at = ? WHERE id = ?", [$videoId, $now, $thumbnailId]);
}

function use_thumbnail_for_video(int $thumbnailId, string $videoId, string $userId): void
{
    $now = to_db(utc_now());
    db_exec(
        "UPDATE videos SET thumbnail_id = ?, thumbnail_status = 'ok', thumbnail_source = 'manual', thumbnail_updated_by = ?, thumbnail_updated_at = ?, updated_at = ? WHERE id = ?",
        [$thumbnailId, $userId, $now, $now, $videoId]
    );
    mark_thumbnail_in_use($thumbnailId, $videoId);
}

function format_bytes(int $bytes): string
{
    return $bytes >= 1048576 ? fmt_decimal($bytes / 1048576) . ' MB' : fmt_number(max(1, round($bytes / 1024))) . ' KB';
}
