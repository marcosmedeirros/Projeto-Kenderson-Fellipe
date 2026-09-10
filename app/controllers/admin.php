<?php

defined('CONTROLADORIA') || exit;

/* ---------------- Integrações ---------------- */

function page_integrations(): void
{
    require_permission('integracoes');
    render_page('integrations', 'Integrações', ['overview' => integrations_overview()]);
}

function action_evolution_save(): void
{
    $user = require_permission('integracoes');
    $baseUrl = post_string('baseUrl', 200);
    $instance = post_string('instance', 64);
    $apiKey = post_string('apiKey', 200);

    $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        flash('erro', 'Informe a URL completa do Evolution, com http:// ou https://.');
        redirect('/integracoes');
    }
    if (!preg_match('/^[\w.-]{1,64}$/', $instance)) {
        flash('erro', 'O nome da instância só pode ter letras, números, ponto, hífen e _.');
        redirect('/integracoes');
    }
    $current = evolution_config();
    if ($apiKey === '' && ($current['source'] ?? null) === 'painel') {
        $apiKey = $current['api_key'];
    }
    if ($apiKey === '') {
        flash('erro', 'Informe a API key da instância.');
        redirect('/integracoes');
    }

    save_integration('evolution', ['base_url' => $baseUrl, 'instance' => $instance, 'api_key' => $apiKey], $user['id']);
    log_audit($user['id'], 'integracao.salva', ['integracao' => 'evolution', 'instancia' => $instance]);
    flash('ok', evolution_connection_state() === 'open'
        ? 'Evolution salvo e número conectado.'
        : 'Evolution salvo. O número ainda não aparece como conectado; confira a instância no Evolution.');
    redirect('/integracoes');
}

function action_evolution_test(): void
{
    require_permission('integracoes');
    $messages = [
        'open' => ['ok', 'Conexão ok: o número do bot está online.'],
        'connecting' => ['erro', 'O Evolution respondeu, mas o número ainda está conectando.'],
        'close' => ['erro', 'O Evolution respondeu, mas o número está desconectado. Leia o QR code de novo.'],
        'nao_configurado' => ['erro', 'Salve as configurações do Evolution primeiro.'],
        'erro' => ['erro', 'Não foi possível falar com o Evolution. Confira a URL, a instância e a API key.'],
    ];
    [$type, $message] = $messages[evolution_connection_state()];
    flash($type, $message);
    redirect('/integracoes');
}

function action_gemini_save(): void
{
    $user = require_permission('integracoes');
    $apiKey = post_string('apiKey', 200);
    $model = post_string('model', 60);
    if (!preg_match('/^[\w.-]{3,60}$/', $model)) {
        flash('erro', 'Nome de modelo inválido.');
        redirect('/integracoes');
    }
    $current = gemini_config();
    if ($apiKey === '' && ($current['source'] ?? null) === 'painel') {
        $apiKey = $current['api_key'];
    }
    if ($apiKey === '') {
        flash('erro', 'Informe a chave da API do Gemini.');
        redirect('/integracoes');
    }
    save_integration('gemini', ['api_key' => $apiKey, 'model' => $model], $user['id']);
    log_audit($user['id'], 'integracao.salva', ['integracao' => 'gemini', 'modelo' => $model]);
    flash('ok', 'Gemini salvo. A geração de ideias já usa a IA.');
    redirect('/integracoes');
}

function action_integration_remove(): void
{
    $user = require_permission('integracoes');
    $key = post_string('key', 20);
    if (in_array($key, ['evolution', 'gemini'], true)) {
        remove_integration($key);
        log_audit($user['id'], 'integracao.removida', ['integracao' => $key]);
        flash('ok', 'Integração removida.');
    }
    redirect('/integracoes');
}

/* ---------------- Usuários ---------------- */

function page_users(): void
{
    $admin = require_permission('usuarios');
    render_page('users', 'Usuários', [
        'admin' => $admin,
        'users' => db_all('SELECT id, name, email, role, active, totp_enabled, must_change_password, last_login_at FROM users ORDER BY name'),
    ]);
}

function other_active_admins(string $exceptUserId): int
{
    return (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?", [$exceptUserId]);
}

function find_target_user(): ?array
{
    $id = post_string('userId', 36);
    if (!preg_match('/^[0-9a-f-]{36}$/', $id)) {
        return null;
    }
    return db_one('SELECT id, name, role, active FROM users WHERE id = ?', [$id]);
}

function action_user_create(): void
{
    $admin = require_permission('usuarios');
    $name = post_string('name', 80);
    $email = mb_strtolower(post_string('email', 254));
    $role = post_string('role', 20);

    if (mb_strlen($name) < 2) {
        flash('erro', 'Informe o nome.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('erro', 'Informe um e-mail válido.');
    } elseif (!array_key_exists($role, ROLE_LABELS)) {
        flash('erro', 'Escolha um perfil válido.');
    } elseif (db_value('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        flash('erro', 'Já existe um usuário com esse e-mail.');
    } else {
        $temporary = generate_temporary_password();
        db_insert('users', [
            'id' => uuid_v4(), 'name' => $name, 'email' => $email, 'password_hash' => hash_password($temporary),
            'role' => $role, 'active' => 1, 'must_change_password' => 1, 'totp_enabled' => 0, 'created_at' => to_db(utc_now()),
        ]);
        log_audit($admin['id'], 'usuario.criado', ['email' => $email, 'perfil' => $role]);
        flash('ok', "$name foi cadastrado. Envie a senha temporária por um canal privado: ela aparece só agora e será trocada no primeiro acesso.", $temporary);
    }
    redirect('/usuarios');
}

function action_user_role(): void
{
    $admin = require_permission('usuarios');
    $target = find_target_user();
    $role = post_string('role', 20);
    if ($target === null || !array_key_exists($role, ROLE_LABELS)) {
        flash('erro', 'Dados inválidos.');
    } elseif ($target['id'] === $admin['id']) {
        flash('erro', 'Você não pode alterar o próprio perfil.');
    } elseif ($target['role'] === 'admin' && $role !== 'admin' && other_active_admins($target['id']) === 0) {
        flash('erro', 'O painel precisa de pelo menos um administrador ativo.');
    } elseif ($target['role'] !== $role) {
        db_exec('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]);
        log_audit($admin['id'], 'usuario.perfil', ['usuario' => $target['name'], 'de' => $target['role'], 'para' => $role]);
        flash('ok', 'Perfil de ' . $target['name'] . ' atualizado.');
    }
    redirect('/usuarios');
}

function action_user_toggle(): void
{
    $admin = require_permission('usuarios');
    $target = find_target_user();
    if ($target === null) {
        flash('erro', 'Usuário não encontrado.');
    } elseif ($target['id'] === $admin['id']) {
        flash('erro', 'Você não pode desativar a própria conta.');
    } elseif ($target['active'] && $target['role'] === 'admin' && other_active_admins($target['id']) === 0) {
        flash('erro', 'O painel precisa de pelo menos um administrador ativo.');
    } else {
        db_exec('UPDATE users SET active = ? WHERE id = ?', [$target['active'] ? 0 : 1, $target['id']]);
        if ($target['active']) {
            revoke_user_sessions($target['id']);
        }
        log_audit($admin['id'], 'usuario.status', ['usuario' => $target['name'], 'ativo' => !$target['active']]);
        flash('ok', $target['active'] ? 'Acesso de ' . $target['name'] . ' desativado e sessões encerradas.' : 'Acesso de ' . $target['name'] . ' reativado.');
    }
    redirect('/usuarios');
}

function action_user_reset_password(): void
{
    $admin = require_permission('usuarios');
    $target = find_target_user();
    if ($target === null) {
        flash('erro', 'Usuário não encontrado.');
    } elseif ($target['id'] === $admin['id']) {
        flash('erro', 'Para trocar a sua senha, use Minha conta.');
    } else {
        $temporary = generate_temporary_password();
        db_exec('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [hash_password($temporary), $target['id']]);
        revoke_user_sessions($target['id']);
        log_audit($admin['id'], 'usuario.senha_redefinida', ['usuario' => $target['name']]);
        flash('ok', 'Nova senha temporária de ' . $target['name'] . '. As sessões do usuário foram encerradas.', $temporary);
    }
    redirect('/usuarios');
}

/* ---------------- Auditoria ---------------- */

function page_audit(): void
{
    require_permission('auditoria');
    $perPage = 50;
    $total = (int) db_value('SELECT COUNT(*) FROM audit_logs');
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) query_string('pagina', '1')));
    $rows = db_all(
        'SELECT a.id, a.action, a.detail, a.ip, a.created_at, u.name AS user_name
           FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
          ORDER BY a.created_at DESC, a.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    render_page('audit', 'Auditoria', ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages]);
}
