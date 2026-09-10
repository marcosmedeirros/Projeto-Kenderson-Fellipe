<?php

defined('CONTROLADORIA') || exit;

/**
 * Recebe os eventos do Evolution (messages.upsert).
 * URL no Evolution: https://SEU-DOMINIO/api/webhooks/evolution?token=TOKEN_DO_WEBHOOK
 */
function api_evolution_webhook(): void
{
    $expected = (string) config('evolution_webhook_token', '');
    $token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
    if ($expected === '' || !hash_equals($expected, $token)) {
        json_response(['error' => 'Não autorizado.'], 401);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 256 * 1024) {
        json_response(['error' => 'Conteúdo grande demais.'], 413);
    }

    $ignored = static fn (string $reason) => json_response(['ok' => true, 'ignored' => $reason]);
    $body = json_decode((string) file_get_contents('php://input', false, null, 0, 256 * 1024), true);
    if (!is_array($body) || !in_array($body['event'] ?? null, ['messages.upsert', 'MESSAGES_UPSERT'], true)) {
        $ignored('evento');
    }

    $message = isset($body['data'][0]) ? $body['data'][0] : ($body['data'] ?? null);
    $chatId = $message['key']['remoteJid'] ?? null;
    if (!is_array($message) || !empty($message['key']['fromMe']) || !is_string($chatId)) {
        $ignored('mensagem');
    }

    $bot = get_setting('bot');
    if (!$bot['ativo']) {
        $ignored('bot desligado');
    }
    if ($bot['grupoId'] === '' || $chatId !== $bot['grupoId']) {
        $ignored('grupo não autorizado');
    }

    $text = $message['message']['conversation'] ?? ($message['message']['extendedTextMessage']['text'] ?? '');
    $command = is_string($text) ? parse_command($text) : null;
    if ($command === null || empty($bot['comandos'][$command])) {
        $ignored('não é comando');
    }
    if (count_recent_commands($chatId) >= 15) {
        $ignored('limite por minuto');
    }

    $sender = is_string($message['pushName'] ?? null) ? $message['pushName'] : (explode('@', (string) ($message['key']['participant'] ?? ''))[0] ?: null);
    record_bot_message(['direction' => 'entrada', 'origin' => 'whatsapp', 'chat_id' => $chatId, 'sender' => $sender, 'text' => mb_substr($text, 0, 200), 'command' => $command]);

    $reply = run_command($command);
    $result = evolution_send_text($chatId, $reply);
    record_bot_message(['direction' => 'saida', 'origin' => 'whatsapp', 'chat_id' => $chatId, 'text' => $reply, 'command' => $command, 'delivered' => $result['ok']]);

    json_response(['ok' => $result['ok']]);
}

function api_health(): void
{
    try {
        db()->query('SELECT 1');
        json_response(['ok' => true]);
    } catch (Throwable $error) {
        error_log('[controladoria] health: ' . $error->getMessage());
        json_response(['ok' => false], 503);
    }
}

/** Alternativa ao Cron em PHP: https://SEU-DOMINIO/api/cron?token=TOKEN_DO_CRON */
function api_cron(): void
{
    $expected = (string) config('cron_token', '');
    $token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
    if ($expected === '' || !hash_equals($expected, $token)) {
        json_response(['error' => 'Não autorizado.'], 401);
    }
    run_due_automations();
    json_response(['ok' => true]);
}
