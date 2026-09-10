<?php

defined('CONTROLADORIA') || exit;

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Atrás do CDN da hospedagem a conexão interna pode ser http; o endereço público é o que vale.
    return PHP_SAPI !== 'cli-server' && str_starts_with((string) config('app_url', ''), 'https://');
}

function content_security_policy(): string
{
    return "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'";
}

/** Cópia da CSP dentro da página: o CDN da Hostinger substitui o cabeçalho por uma política genérica. */
function csp_meta_tag(): string
{
    return '<meta http-equiv="Content-Security-Policy" content="' . e(content_security_policy()) . '">';
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    $csp = content_security_policy() . "; frame-ancestors 'none'";
    if (is_https()) {
        $csp .= '; upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
    }
    header('Content-Security-Policy: ' . $csp);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function random_token(int $bytes = 32): string
{
    return base64url_encode(random_bytes($bytes));
}

function encryption_key(): string
{
    $key = base64_decode((string) config('encryption_key', ''), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('encryption_key ausente ou inválida: precisa ser base64 de 32 bytes.');
    }
    return $key;
}

/** AES-256-GCM. Formato: v1.iv.tag.conteudo (base64url). */
function encrypt_secret(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Falha ao criptografar.');
    }
    return implode('.', ['v1', base64url_encode($iv), base64url_encode($tag), base64url_encode($cipher)]);
}

function decrypt_secret(string $payload): string
{
    $parts = explode('.', $payload);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        throw new RuntimeException('Conteúdo criptografado inválido.');
    }
    $plain = openssl_decrypt(base64url_decode($parts[3]), 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, base64url_decode($parts[1]), base64url_decode($parts[2]));
    if ($plain === false) {
        throw new RuntimeException('Não foi possível descriptografar.');
    }
    return $plain;
}

/* ---------------- Cookies e CSRF ---------------- */

/** O prefixo __Host- obriga HTTPS, path "/" e nenhum domínio. */
function cookie_name(string $base): string
{
    return (is_https() ? '__Host-' : '') . 'controladoria_' . $base;
}

function set_app_cookie(string $base, string $value, int $expires): void
{
    setcookie(cookie_name($base), $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Token anti-CSRF (padrão "double submit": cookie + campo do formulário). */
function csrf_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $cookie = $_COOKIE[cookie_name('csrf')] ?? '';
    if (is_string($cookie) && preg_match('/^[A-Za-z0-9_-]{40,64}$/', $cookie)) {
        return $token = $cookie;
    }
    $token = random_token(32);
    set_app_cookie('csrf', $token, 0);
    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $cookie = $_COOKIE[cookie_name('csrf')] ?? '';
    $valid = is_string($sent) && is_string($cookie) && $cookie !== '' && hash_equals($cookie, $sent);

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($valid && $origin !== '' && $origin !== 'null') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $allowed = array_filter([
            parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST),
            parse_url((string) config('app_url', ''), PHP_URL_HOST),
        ]);
        $valid = in_array($originHost, $allowed, true);
    }

    if (!$valid) {
        abort(419, 'O formulário expirou por segurança. Recarregue a página e tente de novo.');
    }
}
