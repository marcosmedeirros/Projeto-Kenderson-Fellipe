<?php

defined('CONTROLADORIA') || exit;

function routes(): array
{
    return [
        'GET /' => 'page_overview',

        'GET /login' => 'page_login',
        'POST /login' => 'action_login',
        'POST /login/2fa' => 'action_login_two_factor',
        'POST /login/cancelar' => 'action_login_cancel',
        'POST /sair' => 'action_logout',
        'GET /primeiro-acesso' => 'page_first_access',
        'POST /primeiro-acesso' => 'action_first_access',

        'GET /programados' => 'page_scheduled',
        'GET /capas' => 'page_thumbnails',
        'POST /capas/status' => 'action_thumbnail_status',
        'GET /desempenho' => 'page_performance',
        'GET /ideias' => 'page_ideas',
        'POST /ideias/gerar' => 'action_ideas_generate',
        'POST /ideias/status' => 'action_idea_status',
        'POST /ideias/nova' => 'action_idea_create',

        'GET /bot' => 'page_bot',
        'POST /bot/simular' => 'action_bot_simulate',
        'POST /bot/config' => 'action_bot_settings',
        'GET /alertas' => 'page_alerts',
        'POST /alertas' => 'action_alerts_save',

        'GET /integracoes' => 'page_integrations',
        'POST /integracoes/evolution' => 'action_evolution_save',
        'POST /integracoes/evolution/testar' => 'action_evolution_test',
        'POST /integracoes/gemini' => 'action_gemini_save',
        'POST /integracoes/remover' => 'action_integration_remove',
        'GET /usuarios' => 'page_users',
        'POST /usuarios/novo' => 'action_user_create',
        'POST /usuarios/perfil' => 'action_user_role',
        'POST /usuarios/status' => 'action_user_toggle',
        'POST /usuarios/senha' => 'action_user_reset_password',
        'GET /auditoria' => 'page_audit',

        'GET /conta' => 'page_account',
        'POST /conta/senha' => 'action_account_password',
        'POST /conta/2fa/iniciar' => 'action_totp_start',
        'POST /conta/2fa/confirmar' => 'action_totp_confirm',
        'POST /conta/2fa/desativar' => 'action_totp_disable',
        'POST /conta/sessoes/encerrar' => 'action_session_revoke',
        'POST /conta/sessoes/encerrar-outras' => 'action_sessions_revoke_others',

        'POST /api/webhooks/evolution' => 'api_evolution_webhook',
        'GET /api/health' => 'api_health',
        'GET /api/cron' => 'api_cron',
        'POST /api/cron' => 'api_cron',
    ];
}

/** Rotas chamadas por outros sistemas: autenticadas por token, sem CSRF. */
const EXTERNAL_ROUTES = ['POST /api/webhooks/evolution', 'GET /api/health', 'GET /api/cron', 'POST /api/cron'];

function dispatch(): void
{
    send_security_headers();

    $method = request_method() === 'HEAD' ? 'GET' : request_method();
    $path = request_path();
    $key = "$method $path";
    $routes = routes();

    if (!isset($routes[$key])) {
        abort(isset($routes["GET $path"]) || isset($routes["POST $path"]) ? 405 : 404);
    }

    if (!config_loaded()) {
        http_response_code(503);
        echo view('layout/setup');
        exit;
    }

    $external = in_array($key, EXTERNAL_ROUTES, true);
    if (!$external) {
        header('Cache-Control: no-store');
        csrf_token();
        if ($method === 'POST') {
            verify_csrf();
        }
    }

    if ($key !== 'GET /api/health') {
        ensure_database_ready();
    }

    $handler = $routes[$key];
    $handler();
}
