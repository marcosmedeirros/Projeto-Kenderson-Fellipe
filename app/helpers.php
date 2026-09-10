<?php

defined('CONTROLADORIA') || exit;

/** Escapa texto para HTML. Use em TODA saída de dados. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Erro com mensagem pensada para aparecer na tela. Qualquer outra exceção mostra só uma mensagem genérica. */
class UserFacingException extends RuntimeException
{
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = '/' . trim(rawurldecode($path), '/');
    return $path;
}

function query_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? mb_substr(trim($value), 0, 200) : $default;
}

/** Texto do formulário, sem espaços nas pontas e com tamanho máximo. */
function post_string(string $key, int $max = 1000): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

/** Texto do formulário sem alterações (para senhas). */
function post_raw(string $key, int $max = 200): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? substr($value, 0, $max) : '';
}

function post_checkbox(string $key): bool
{
    return ($_POST[$key] ?? '') === 'on';
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

/** Volta para uma página interna. Recusa "//site", "/\site", esquemas e caracteres estranhos. */
function back_to(string $fallback): void
{
    $target = post_string('_voltar', 300);
    $safe = preg_match('#^/(?!/)[A-Za-z0-9/_?=&.%-]*$#', $target) === 1;
    redirect($safe ? $target : $fallback);
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * IP do visitante. Na Hostinger o tráfego passa pelo CDN, que ACRESCENTA o IP real no fim do
 * X-Forwarded-For. Por isso vale o último IP público da lista: os primeiros podem ter sido
 * inventados pelo próprio visitante para escapar do limite de tentativas.
 */
function client_ip(): ?string
{
    $public = static fn (string $ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    $forwarded = array_reverse(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
    foreach ($forwarded as $candidate) {
        if ($public($candidate)) {
            return substr($candidate, 0, 64);
        }
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($remote, FILTER_VALIDATE_IP) ? substr($remote, 0, 64) : null;
}

function user_agent(): ?string
{
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agent !== '' ? mb_substr($agent, 0, 300) : null;
}

function utc_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/** Data para gravar no MySQL (sempre em UTC). */
function to_db(DateTimeInterface $date): string
{
    return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
}

function from_db(?string $value): ?DateTimeImmutable
{
    return $value === null ? null : new DateTimeImmutable($value, new DateTimeZone('UTC'));
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

/** Renderiza um template de views/ e devolve o HTML. */
function view(string $__template, array $__data = []): string
{
    extract($__data, EXTR_SKIP);
    ob_start();
    require ROOT_DIR . '/views/' . $__template . '.php';
    return (string) ob_get_clean();
}

/** Página dentro do layout do painel (com menu). */
function render_page(string $template, string $title, array $data = []): void
{
    $content = view('pages/' . $template, $data);
    echo view('layout/app', ['title' => $title, 'content' => $content, 'scripts' => $data['_scripts'] ?? []]);
    exit;
}

/** Página sem menu (login, primeiro acesso). */
function render_bare(string $template, string $title, array $data = []): void
{
    $content = view('pages/' . $template, $data);
    echo view('layout/bare', ['title' => $title, 'content' => $content]);
    exit;
}

function abort(int $status, string $message = ''): void
{
    http_response_code($status);
    echo view('layout/error', ['status' => $status, 'message' => $message]);
    exit;
}

function asset(string $path): string
{
    $file = ROOT_DIR . '/assets/' . $path;
    $version = @filemtime($file) ?: 1;
    return '/assets/' . $path . '?v=' . $version;
}
