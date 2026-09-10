<?php

defined('CONTROLADORIA') || exit;

function page_bot(): void
{
    $user = require_user();
    $bot = get_setting('bot');
    $base = rtrim((string) config('app_url', ''), '/');
    if ($base === '') {
        $base = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'seu-dominio');
    }
    render_page('bot', 'Bot do WhatsApp', [
        'bot' => $bot,
        'connection' => evolution_connection_state(),
        'messages' => recent_bot_messages(30),
        'webhookUrl' => $base . '/api/webhooks/evolution?token=…',
        'hasToken' => (string) config('evolution_webhook_token', '') !== '',
        'enabledCommands' => array_values(array_filter(BOT_COMMANDS, static fn ($command) => !empty($bot['comandos'][$command]))),
        'canConfigure' => can($user['role'], 'automacoes'),
    ]);
}

function action_bot_simulate(): void
{
    $session = current_session();
    if ($session === null || $session['two_factor_pending'] || $session['user']['must_change_password']) {
        json_response(['error' => 'Sessão expirada. Entre novamente.'], 401);
    }
    $text = post_string('text', 200);
    if ($text === '') {
        json_response(['error' => 'Digite um comando, por exemplo /programados.'], 422);
    }
    $command = parse_command($text);
    $bot = get_setting('bot');
    if ($command === null) {
        $reply = 'Não entendi. Envie /ajuda para ver os comandos disponíveis.';
    } elseif (empty($bot['comandos'][$command])) {
        $reply = "O comando /$command está desativado nas configurações do bot.";
    } else {
        $reply = run_command($command);
    }
    record_bot_message(['direction' => 'entrada', 'origin' => 'simulador', 'sender' => $session['user']['name'], 'text' => $text, 'command' => $command]);
    record_bot_message(['direction' => 'saida', 'origin' => 'simulador', 'text' => $reply, 'command' => $command]);
    json_response(['reply' => $reply]);
}

function action_bot_settings(): void
{
    $user = require_permission('automacoes');
    [$data, $error] = validate_bot_settings($_POST);
    if ($error !== null) {
        flash('erro', $error);
    } else {
        save_setting('bot', $data, $user['id']);
        log_audit($user['id'], 'config.bot', ['ativo' => $data['ativo'], 'grupo' => $data['grupoNome']]);
        flash('ok', 'Configurações do bot salvas.');
    }
    redirect('/bot');
}

function page_alerts(): void
{
    $user = require_user();
    $alerts = get_setting('alertas');
    $stock = summarize_stock(scheduled_videos());
    render_page('alerts', 'Alertas', [
        'alerts' => $alerts,
        'bot' => get_setting('bot'),
        'state' => get_setting('automacoes'),
        'stock' => $stock,
        'preview' => build_stock_alert($stock, (int) $alerts['estoqueMinimoDias']),
        'editable' => can($user['role'], 'automacoes'),
        'hasCronToken' => (string) config('cron_token', '') !== '',
    ]);
}

function action_alerts_save(): void
{
    $user = require_permission('automacoes');
    [$data, $error] = validate_alert_settings($_POST);
    if ($error !== null) {
        flash('erro', $error);
    } else {
        save_setting('alertas', $data, $user['id']);
        log_audit($user['id'], 'config.alertas', $data);
        flash('ok', 'Alertas salvos. Os próximos envios já seguem as novas regras.');
    }
    redirect('/alertas');
}
