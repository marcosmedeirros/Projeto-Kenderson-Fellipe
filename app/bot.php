<?php

defined('CONTROLADORIA') || exit;

const COMMAND_INFO = [
    'programados' => ['label' => '/programados', 'description' => 'Vídeos agendados, datas e até quando o estoque dura.'],
    'semcapa' => ['label' => '/semcapa', 'description' => 'Vídeos programados que ainda estão sem thumbnail.'],
    'resumo' => ['label' => '/resumo', 'description' => 'Visão rápida: estoque, capas, views do mês e destaques.'],
    'virais' => ['label' => '/virais', 'description' => 'Vídeos que performaram acima da média nos últimos 45 dias.'],
    'ideias' => ['label' => '/ideias', 'description' => 'Ideias de pauta aprovadas e as mais bem avaliadas.'],
    'ajuda' => ['label' => '/ajuda', 'description' => 'Lista os comandos disponíveis.'],
];

const COMMAND_ALIASES = [
    'programados' => 'programados', 'agendados' => 'programados', 'estoque' => 'programados',
    'semcapa' => 'semcapa', 'capas' => 'semcapa', 'capa' => 'semcapa', 'thumbs' => 'semcapa',
    'resumo' => 'resumo',
    'virais' => 'virais', 'viral' => 'virais',
    'ideias' => 'ideias', 'pautas' => 'ideias',
    'ajuda' => 'ajuda', 'comandos' => 'ajuda', 'menu' => 'ajuda', 'help' => 'ajuda',
];

function parse_command(string $text): ?string
{
    $normalized = strtr(mb_strtolower(trim($text)), [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
    ]);
    if (!str_starts_with($normalized, '/')) {
        return null;
    }
    $word = preg_split('/\s+/', substr($normalized, 1))[0] ?? '';
    return COMMAND_ALIASES[str_replace(['-', '_'], '', $word)] ?? null;
}

function scheduled_line(array $video): string
{
    $date = $video['publish_at'];
    $flags = trim(($video['is_short'] ? '[Short] ' : '') . ($video['thumbnail_status'] !== 'ok' ? '⚠️ sem capa' : ''));
    return '• ' . fmt_date($date) . ' (' . fmt_weekday($date) . ') ' . fmt_time($date) . ' · ' . $video['title'] . ($flags !== '' ? ' ' . $flags : '');
}

function build_stock_alert(array $stock, int $minimumDays): string
{
    if ($stock['total'] === 0) {
        return "⚠️ *Nenhum vídeo programado.*\nO estoque acabou. Hora de gravar!";
    }
    return '⚠️ *Estoque baixo:* os vídeos programados cobrem só até *' . fmt_date($stock['last_date']) . '* (' . plural($stock['days_covered'], 'dia', 'dias') . ").\n"
        . "O mínimo combinado é de $minimumDays dias. Bom momento para agendar gravação.";
}

function build_thumb_alert(array $pending): string
{
    $lines = array_map(static fn ($video) => '• ' . fmt_date($video['publish_at']) . ' · ' . $video['title'] . ' (publica ' . fmt_in_days(days_until($video['publish_at'])) . ')', $pending);
    $title = count($pending) === 1 ? '1 vídeo precisa' : count($pending) . ' vídeos precisam';
    return "🖼️ *$title de capa com urgência*\n\n" . implode("\n", $lines);
}

function run_command(string $command): string
{
    switch ($command) {
        case 'programados':
            $videos = scheduled_videos();
            $alerts = get_setting('alertas');
            $stock = summarize_stock($videos);
            if (!$videos) {
                return build_stock_alert($stock, $alerts['estoqueMinimoDias']);
            }
            $text = '📅 *' . plural(count($videos), 'vídeo programado', 'vídeos programados') . "*\n\n"
                . implode("\n", array_map('scheduled_line', $videos))
                . "\n\nEstoque cobre até *" . fmt_date($stock['last_date']) . '* (' . plural($stock['days_covered'], 'dia', 'dias') . ').';
            if ($stock['days_covered'] < $alerts['estoqueMinimoDias']) {
                $text .= "\n⚠️ Abaixo do mínimo de {$alerts['estoqueMinimoDias']} dias. Bom momento para gravar.";
            }
            return $text;

        case 'semcapa':
            $alerts = get_setting('alertas');
            $pending = array_filter(scheduled_videos(), static fn ($video) => $video['thumbnail_status'] !== 'ok');
            if (!$pending) {
                return '✅ *Todos os vídeos programados já têm capa.*';
            }
            $lines = array_map(static function ($video) use ($alerts) {
                $days = days_until($video['publish_at']);
                return '• ' . ($days <= $alerts['capaAvisoDias'] ? '🔴 ' : '') . fmt_date($video['publish_at']) . ' · ' . $video['title'] . ' (publica ' . fmt_in_days($days) . ')';
            }, $pending);
            return '🖼️ *' . plural(count($pending), 'vídeo sem capa', 'vídeos sem capa') . "*\n\n" . implode("\n", $lines);

        case 'resumo':
            $alerts = get_setting('alertas');
            $stock = summarize_stock(scheduled_videos());
            $mtd = month_to_date();
            $virals = viral_videos((float) $alerts['viralMultiplicador']);
            $approved = count(ideas_list(['aprovada']));
            $change = $mtd['change'] === null ? '' : ' (' . ($mtd['change'] >= 0 ? '+' : '-') . fmt_percent(abs($mtd['change'])) . ' vs mês passado)';
            $lines = [
                '📊 *Resumo do canal · ' . fmt_long_day(utc_now()) . '*',
                '',
                $stock['total'] ? '📅 Programados: *' . $stock['total'] . '* (até ' . fmt_date($stock['last_date']) . ', ' . plural($stock['days_covered'], 'dia', 'dias') . ')' : '📅 Programados: *nenhum*',
                '🖼️ Sem capa: *' . $stock['missing_thumbs'] . '*',
                '👀 Views no mês: *' . fmt_compact($mtd['views']) . '*' . $change,
                '➕ Inscritos no mês: *' . fmt_compact($mtd['subscribers']) . '*',
            ];
            if ($virals['items']) {
                $lines[] = '🔥 Em alta: *' . $virals['items'][0]['title'] . '* (' . fmt_ratio($virals['items'][0]['ratio']) . ' a média)';
            }
            $lines[] = '💡 Ideias aprovadas: *' . $approved . '*';
            if ($stock['days_covered'] < $alerts['estoqueMinimoDias']) {
                $lines[] = '';
                $lines[] = '⚠️ Estoque abaixo do mínimo. Hora de gravar.';
            }
            return implode("\n", $lines);

        case 'virais':
            $alerts = get_setting('alertas');
            $items = viral_videos((float) $alerts['viralMultiplicador'])['items'];
            if (!$items) {
                return 'Nenhum vídeo passou de ' . fmt_decimal((float) $alerts['viralMultiplicador']) . 'x a média nos últimos 45 dias.';
            }
            $lines = array_map(static fn ($video) => '• ' . $video['title'] . ($video['is_short'] ? ' [Short]' : '') . ' · ' . fmt_compact($video['views_7d']) . ' views em 7 dias (*' . fmt_ratio($video['ratio']) . '* a média)', array_slice($items, 0, 8));
            return "🔥 *Vídeos em alta · últimos 45 dias*\n\n" . implode("\n", $lines);

        case 'ideias':
            $ideas = ideas_list(['aprovada', 'nova']);
            if (!$ideas) {
                return '💡 Nenhuma ideia cadastrada ainda. Gere sugestões pelo painel.';
            }
            usort($ideas, static fn ($a, $b) => [$a['status'] !== 'aprovada', -$a['score']] <=> [$b['status'] !== 'aprovada', -$b['score']]);
            $lines = array_map(static fn ($idea) => '• *' . $idea['title'] . '*' . ($idea['status'] === 'aprovada' ? ' ✅' : '') . "\n  " . $idea['rationale'], array_slice($ideas, 0, 6));
            return "💡 *Ideias de pauta*\n\n" . implode("\n", $lines) . "\n\n✅ = aprovada. Veja todas no painel.";

        case 'ajuda':
        default:
            $bot = get_setting('bot');
            $lines = [];
            foreach (BOT_COMMANDS as $name) {
                if (!empty($bot['comandos'][$name])) {
                    $lines[] = COMMAND_INFO[$name]['label'] . ' · ' . COMMAND_INFO[$name]['description'];
                }
            }
            return "🤖 *Comandos disponíveis*\n\n" . implode("\n", $lines);
    }
}

function build_monthly_report(string $month): string
{
    $alerts = get_setting('alertas');
    $totals = month_totals($month);
    $top = top_videos($month, 3);
    $virals = viral_videos((float) $alerts['viralMultiplicador'], 31);
    $lines = [
        '🗓️ *Relatório de ' . mb_strtolower(month_label($month)) . '*',
        '',
        '👀 Views: *' . fmt_compact($totals['views']) . '*',
        '⏱️ Horas assistidas: *' . fmt_compact(round($totals['watch_minutes'] / 60)) . '*',
        '➕ Inscritos: *' . fmt_compact($totals['subscribers']) . '*',
        '',
        '*Mais vistos*',
    ];
    foreach ($top as $index => $video) {
        $lines[] = ($index + 1) . '. ' . $video['title'] . ' · ' . fmt_compact($video['views']);
    }
    if ($virals['items']) {
        $lines[] = '';
        $lines[] = '🔥 ' . plural(count($virals['items']), 'vídeo viralizou', 'vídeos viralizaram') . ' no período.';
    }
    return implode("\n", $lines);
}

/* ---------------- Evolution API (WhatsApp) ---------------- */

function http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 15): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $response === false ? 0 : $status, 'body' => $response === false ? '' : (string) $response];
}

function evolution_send_text(string $chatId, string $text): array
{
    $config = evolution_config();
    if ($config === null) {
        return ['ok' => false, 'error' => 'WhatsApp não configurado.'];
    }
    $response = http_request(
        'POST',
        rtrim($config['base_url'], '/') . '/message/sendText/' . rawurlencode($config['instance']),
        ['Content-Type: application/json', 'apikey: ' . $config['api_key']],
        json_encode(['number' => $chatId, 'text' => $text], JSON_UNESCAPED_UNICODE)
    );
    if ($response['status'] === 0) {
        return ['ok' => false, 'error' => 'Não foi possível conectar ao Evolution.'];
    }
    if ($response['status'] >= 300) {
        return ['ok' => false, 'error' => 'O Evolution respondeu com erro ' . $response['status'] . '.'];
    }
    return ['ok' => true, 'error' => null];
}

/** open | connecting | close | nao_configurado | erro */
function evolution_connection_state(): string
{
    $config = evolution_config();
    if ($config === null) {
        return 'nao_configurado';
    }
    $response = http_request(
        'GET',
        rtrim($config['base_url'], '/') . '/instance/connectionState/' . rawurlencode($config['instance']),
        ['apikey: ' . $config['api_key']],
        null,
        8
    );
    if ($response['status'] !== 200) {
        return 'erro';
    }
    $state = json_decode($response['body'], true)['instance']['state'] ?? null;
    return in_array($state, ['open', 'connecting', 'close'], true) ? $state : 'erro';
}

function record_bot_message(array $message): void
{
    db_insert('bot_messages', [
        'direction' => $message['direction'],
        'origin' => $message['origin'],
        'chat_id' => $message['chat_id'] ?? null,
        'sender' => isset($message['sender']) ? mb_substr((string) $message['sender'], 0, 120) : null,
        'text' => mb_substr((string) $message['text'], 0, 4000),
        'command' => $message['command'] ?? null,
        'delivered' => array_key_exists('delivered', $message) ? ($message['delivered'] ? 1 : 0) : 1,
        'created_at' => to_db($message['created_at'] ?? utc_now()),
    ]);
}

function count_recent_commands(string $chatId, int $seconds = 60): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM bot_messages WHERE chat_id = ? AND direction = 'entrada' AND created_at > ?",
        [$chatId, to_db(utc_now()->modify("-$seconds seconds"))]
    );
}
