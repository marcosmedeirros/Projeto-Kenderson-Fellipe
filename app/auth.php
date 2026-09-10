<?php

defined('CONTROLADORIA') || exit;

const SESSION_MAX_AGE = 7 * 86400;   // limite absoluto
const SESSION_IDLE_TIMEOUT = 12 * 3600; // encerra após 12 h sem uso
const SESSION_PENDING_MAX_AGE = 600;  // tempo para digitar o código de 2 etapas
const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_MAX_FAILURES_EMAIL = 5;
const LOGIN_MAX_FAILURES_IP = 30;

/* ---------------- Perfis e permissões ---------------- */

const ROLE_LABELS = ['admin' => 'Administrador', 'editor' => 'Editor', 'visualizador' => 'Visualizador'];

const ROLE_DESCRIPTIONS = [
    'admin' => 'Acesso total: integrações, usuários e auditoria.',
    'editor' => 'Opera o canal: capas, ideias, bot e alertas.',
    'visualizador' => 'Só consulta os dados do canal.',
];

const PERMISSIONS = [
    'operar' => ['admin', 'editor'],
    'automacoes' => ['admin', 'editor'],
    'integracoes' => ['admin'],
    'usuarios' => ['admin'],
    'auditoria' => ['admin'],
];

function can(string $role, string $permission): bool
{
    return in_array($role, PERMISSIONS[$permission] ?? [], true);
}

/* ---------------- Senhas ---------------- */

function hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]);
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password(string $hash, string $password): bool
{
    return password_verify($password, $hash);
}

/** Gasta o mesmo tempo de uma verificação real, para não revelar se o e-mail existe. */
function dummy_verify(string $password): void
{
    static $hash = null;
    $hash ??= hash_password('senha-ficticia-para-tempo-constante');
    password_verify($password, $hash);
}

function validate_new_password(string $password): ?string
{
    if (mb_strlen($password) < 10) {
        return 'A senha precisa ter pelo menos 10 caracteres.';
    }
    if (strlen($password) > 72) {
        return 'A senha pode ter no máximo 72 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return 'A senha precisa ter pelo menos uma letra.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'A senha precisa ter pelo menos um número.';
    }
    return null;
}

function generate_temporary_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $body = '';
    for ($i = 0; $i < 14; $i++) {
        $body .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($body, 0, 7) . '-' . substr($body, 7) . random_int(0, 9);
}

/* ---------------- Sessões (guardadas no banco) ---------------- */

function create_session(string $userId, bool $pendingTwoFactor = false): void
{
    $token = random_token(32);
    $now = utc_now();
    $expires = $now->modify('+' . ($pendingTwoFactor ? SESSION_PENDING_MAX_AGE : SESSION_MAX_AGE) . ' seconds');
    db_insert('sessions', [
        'id' => hash('sha256', $token),
        'user_id' => $userId,
        'two_factor_pending' => $pendingTwoFactor ? 1 : 0,
        'two_factor_attempts' => 0,
        'ip' => client_ip(),
        'user_agent' => user_agent(),
        'created_at' => to_db($now),
        'last_seen_at' => to_db($now),
        'expires_at' => to_db($expires),
    ]);
    set_app_cookie('sessao', $token, $expires->getTimestamp());
}

/** Sessão atual (ou null). O token do cookie nunca é salvo, só o hash dele. */
function current_session(bool $refresh = false): ?array
{
    static $loaded = false;
    static $session = null;
    if ($loaded && !$refresh) {
        return $session;
    }
    $loaded = true;
    $session = null;

    $token = $_COOKIE[cookie_name('sessao')] ?? '';
    if (!is_string($token) || $token === '' || strlen($token) > 128) {
        return null;
    }
    $id = hash('sha256', $token);
    $row = db_one(
        'SELECT s.id, s.two_factor_pending, s.two_factor_attempts, s.last_seen_at, s.expires_at,
                u.id AS user_id, u.name, u.email, u.role, u.active, u.totp_enabled, u.must_change_password
           FROM sessions s JOIN users u ON u.id = s.user_id
          WHERE s.id = ? LIMIT 1',
        [$id]
    );
    if ($row === null) {
        return null;
    }

    $now = time();
    $pending = (bool) $row['two_factor_pending'];
    $lastSeen = from_db($row['last_seen_at'])->getTimestamp();
    $expired = from_db($row['expires_at'])->getTimestamp() <= $now
        || (!$pending && $now - $lastSeen > SESSION_IDLE_TIMEOUT)
        || !$row['active'];
    if ($expired) {
        db_exec('DELETE FROM sessions WHERE id = ?', [$id]);
        return null;
    }
    if ($now - $lastSeen > 300) {
        db_exec('UPDATE sessions SET last_seen_at = ? WHERE id = ?', [to_db(utc_now()), $id]);
    }

    return $session = [
        'id' => $id,
        'two_factor_pending' => $pending,
        'two_factor_attempts' => (int) $row['two_factor_attempts'],
        'user' => [
            'id' => $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'totp_enabled' => (bool) $row['totp_enabled'],
            'must_change_password' => (bool) $row['must_change_password'],
        ],
    ];
}

function delete_current_session(): void
{
    $token = $_COOKIE[cookie_name('sessao')] ?? '';
    if (is_string($token) && $token !== '') {
        db_exec('DELETE FROM sessions WHERE id = ?', [hash('sha256', $token)]);
    }
    set_app_cookie('sessao', '', time() - 3600);
    current_session(true);
}

function revoke_user_sessions(string $userId, ?string $exceptSessionId = null): void
{
    if ($exceptSessionId !== null) {
        db_exec('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$userId, $exceptSessionId]);
    } else {
        db_exec('DELETE FROM sessions WHERE user_id = ?', [$userId]);
    }
}

function list_user_sessions(string $userId): array
{
    return db_all(
        'SELECT id, ip, user_agent, created_at, last_seen_at FROM sessions
          WHERE user_id = ? AND two_factor_pending = 0 ORDER BY last_seen_at DESC',
        [$userId]
    );
}

/**
 * Exige usuário logado e com a verificação em 2 etapas concluída.
 * Chamada em toda página e em toda ação, não só no menu.
 */
function require_user(bool $allowPasswordChange = false): array
{
    $session = current_session();
    if ($session === null || $session['two_factor_pending']) {
        redirect('/login');
    }
    if ($session['user']['must_change_password'] && !$allowPasswordChange) {
        redirect('/primeiro-acesso');
    }
    return $session['user'];
}

function require_permission(string $permission): array
{
    $user = require_user();
    if (!can($user['role'], $permission)) {
        redirect('/?acesso=negado');
    }
    return $user;
}

/* ---------------- Mensagens entre páginas ---------------- */

function flash(string $type, string $message, ?string $secret = null): void
{
    $session = current_session();
    if ($session === null) {
        return;
    }
    $items = json_decode((string) db_value('SELECT flash FROM sessions WHERE id = ?', [$session['id']]), true) ?: [];
    $items[] = ['type' => $type, 'message' => $message, 'secret' => $secret !== null ? encrypt_secret($secret) : null];
    db_exec('UPDATE sessions SET flash = ? WHERE id = ?', [json_encode($items, JSON_UNESCAPED_UNICODE), $session['id']]);
}

function take_flash(): array
{
    $session = current_session();
    if ($session === null) {
        return [];
    }
    $raw = db_value('SELECT flash FROM sessions WHERE id = ?', [$session['id']]);
    if (!$raw) {
        return [];
    }
    db_exec('UPDATE sessions SET flash = NULL WHERE id = ?', [$session['id']]);
    $items = json_decode((string) $raw, true) ?: [];
    foreach ($items as &$item) {
        $item['secret'] = !empty($item['secret']) ? decrypt_secret($item['secret']) : null;
    }
    return $items;
}

/* ---------------- Proteção contra força bruta ---------------- */

function is_login_blocked(string $email, ?string $ip): bool
{
    $since = to_db(utc_now()->modify('-' . LOGIN_WINDOW_SECONDS . ' seconds'));
    $byEmail = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?', [$email, $since]);
    if ($byEmail >= LOGIN_MAX_FAILURES_EMAIL) {
        return true;
    }
    if ($ip === null) {
        return false;
    }
    $byIp = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [$ip, $since]);
    return $byIp >= LOGIN_MAX_FAILURES_IP;
}

function record_login_attempt(string $email, ?string $ip, bool $success): void
{
    db_insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => $success ? 1 : 0, 'created_at' => to_db(utc_now())]);
    if ($success) {
        db_exec('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$email]);
    }
}

function purge_old_login_attempts(): void
{
    db_exec('DELETE FROM login_attempts WHERE created_at < ?', [to_db(utc_now()->modify('-1 day'))]);
}
