<?php

defined('CONTROLADORIA') || exit;

const SESSION_MAX_AGE = 7 * 86400;   // limite absoluto
const SESSION_IDLE_TIMEOUT = 12 * 3600; // encerra após 12 h sem uso
const SESSION_PENDING_MAX_AGE = 600;  // tempo para digitar o código de 2 etapas
const TEMP_PASSWORD_TTL = 72 * 3600;  // validade da senha temporária criada pelo administrador
const AUDIT_RETENTION_DAYS = 365;

// Janela de 15 minutos. O limite por e-mail + IP barra quem tenta adivinhar a senha;
// os limites maiores, só por IP ou só por e-mail, pegam ataques espalhados sem deixar
// um desconhecido travar a conta de alguém com poucas tentativas.
const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_MAX_FAILURES_PAIR = 5;
const LOGIN_MAX_FAILURES_IP = 30;
const LOGIN_MAX_FAILURES_EMAIL = 50;
const SENSITIVE_MAX_FAILURES = 5;

// Hashes de uma senha aleatória descartada, com os mesmos parâmetros das senhas reais.
const DUMMY_ARGON2_HASH = '$argon2id$v=19$m=19456,t=2,p=1$VGJidjVkNFhRd1FEODVnZA$JuGGTA26XKllSWTcN4OjMGpYKcfWTaPu0z+ew/4F2Mk';
const DUMMY_BCRYPT_HASH = '$2y$12$vmL5qYU/S3bKjw1a/9RNtO8dswbbU4b.3N3Y8.kth/ZhNSYKDtkhG';

/* ---------------- Perfis e permissões ---------------- */

const ROLE_LABELS = ['admin' => 'Administrador', 'editor' => 'Editor', 'visualizador' => 'Visualizador'];

const ROLE_DESCRIPTIONS = [
    'admin' => 'Acesso total: excluir dados, integrações, usuários e auditoria.',
    'editor' => 'Cadastra e edita vídeos, capas e ideias; opera o bot e os alertas.',
    'visualizador' => 'Só consulta os dados do canal.',
];

const PERMISSIONS = [
    'operar' => ['admin', 'editor'],
    'excluir' => ['admin'],
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

const ARGON2_OPTIONS = ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];
const BCRYPT_OPTIONS = ['cost' => 12];

function hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, ARGON2_OPTIONS);
    }
    return password_hash($password, PASSWORD_BCRYPT, BCRYPT_OPTIONS);
}

/** true quando o hash foi feito com um algoritmo ou parâmetros diferentes dos atuais. */
function password_needs_upgrade(string $hash): bool
{
    return defined('PASSWORD_ARGON2ID')
        ? password_needs_rehash($hash, PASSWORD_ARGON2ID, ARGON2_OPTIONS)
        : password_needs_rehash($hash, PASSWORD_BCRYPT, BCRYPT_OPTIONS);
}

function verify_password(string $hash, string $password): bool
{
    return password_verify($password, $hash);
}

/** Gasta o mesmo tempo de uma verificação real, para não revelar se o e-mail existe. */
function dummy_verify(string $password): void
{
    password_verify($password, defined('PASSWORD_ARGON2ID') ? DUMMY_ARGON2_HASH : DUMMY_BCRYPT_HASH);
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

function temporary_password_expired(array $user): bool
{
    return !empty($user['must_change_password'])
        && !empty($user['temp_password_expires_at'])
        && from_db($user['temp_password_expires_at'])->getTimestamp() <= time();
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
                u.id AS user_id, u.name, u.email, u.role, u.active, u.totp_enabled, u.must_change_password, u.temp_password_expires_at
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
        || !$row['active']
        || temporary_password_expired($row);
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
    db_exec('UPDATE sessions SET flash = ? WHERE id = ?', [json_encode(array_slice($items, -10), JSON_UNESCAPED_UNICODE), $session['id']]);
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

function login_window_start(): string
{
    return to_db(utc_now()->modify('-' . LOGIN_WINDOW_SECONDS . ' seconds'));
}

function is_login_blocked(string $email, ?string $ip): bool
{
    $since = login_window_start();
    $pair = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip <=> ? AND success = 0 AND created_at > ?', [$email, $ip, $since]);
    if ($pair >= LOGIN_MAX_FAILURES_PAIR) {
        return true;
    }
    if ($ip !== null) {
        $byIp = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [$ip, $since]);
        if ($byIp >= LOGIN_MAX_FAILURES_IP) {
            return true;
        }
    }
    $byEmail = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?', [$email, $since]);
    return $byEmail >= LOGIN_MAX_FAILURES_EMAIL;
}

/**
 * Registra uma tentativa. Numa falha, devolve true se ela acabou de bloquear o login
 * (para a auditoria registrar só a mudança, e não cada tentativa).
 */
function record_login_attempt(string $email, ?string $ip, bool $success): bool
{
    if ($success) {
        // Só limpa as falhas deste aparelho: um acerto não zera um ataque vindo de outros IPs.
        db_exec('DELETE FROM login_attempts WHERE email = ? AND ip <=> ? AND success = 0', [$email, $ip]);
        return false;
    }
    db_insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => 0, 'created_at' => to_db(utc_now())]);
    return is_login_blocked($email, $ip);
}

/* Confirmações com senha ou código dentro do painel (trocar senha, desligar 2 etapas). */

function sensitive_attempt_key(array $user): string
{
    return 'acao:' . $user['id'];
}

function sensitive_action_blocked(array $user): bool
{
    return (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?',
        [sensitive_attempt_key($user), login_window_start()]
    ) >= SENSITIVE_MAX_FAILURES;
}

/** Conta um erro. No limite, encerra todas as sessões do usuário: pode ser alguém com o aparelho de outra pessoa. */
function register_sensitive_failure(array $user): void
{
    db_insert('login_attempts', ['email' => sensitive_attempt_key($user), 'ip' => client_ip(), 'success' => 0, 'created_at' => to_db(utc_now())]);
    if (!sensitive_action_blocked($user)) {
        return;
    }
    revoke_user_sessions($user['id']);
    log_audit($user['id'], 'conta.bloqueio_sensivel');
    set_app_cookie('sessao', '', time() - 3600);
    current_session(true);
    redirect('/login');
}

/* ---------------- Limpeza (rodada pelo Cron uma vez por dia) ---------------- */

function purge_old_login_attempts(): void
{
    db_exec('DELETE FROM login_attempts WHERE created_at < ?', [to_db(utc_now()->modify('-1 day'))]);
}

function purge_expired_sessions(): void
{
    db_exec(
        'DELETE FROM sessions WHERE expires_at < ? OR (two_factor_pending = 0 AND last_seen_at < ?)',
        [to_db(utc_now()), to_db(utc_now()->modify('-' . SESSION_IDLE_TIMEOUT . ' seconds'))]
    );
}

function purge_old_audit_logs(): void
{
    db_exec('DELETE FROM audit_logs WHERE created_at < ? LIMIT 5000', [to_db(utc_now()->modify('-' . AUDIT_RETENTION_DAYS . ' days'))]);
}
