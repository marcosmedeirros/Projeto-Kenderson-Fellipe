<?php

defined('CONTROLADORIA') || exit;

// TOTP (RFC 6238): códigos de 6 dígitos a cada 30 s, compatível com Google Authenticator,
// Microsoft Authenticator, 1Password etc.

const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const TOTP_ISSUER = 'Controladoria do Canal';
const TOTP_PERIOD = 30;

function base32_encode_bytes(string $binary): string
{
    $bits = '';
    foreach (str_split($binary) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        $output .= TOTP_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $output;
}

function base32_decode_string(string $input): string
{
    $clean = strtoupper((string) preg_replace('/[\s=]/', '', $input));
    $bits = '';
    foreach (str_split($clean) as $char) {
        $index = strpos(TOTP_ALPHABET, $char);
        if ($index === false) {
            throw new RuntimeException('Segredo TOTP inválido.');
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $output .= chr(bindec($byte));
        }
    }
    return $output;
}

function hotp_code(string $key, int $counter): string
{
    $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $code = (((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3])) % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

function totp_generate_secret(): string
{
    return base32_encode_bytes(random_bytes(20));
}

/**
 * Aceita o código atual e o vizinho (±30 s de relógio).
 * Devolve o passo usado, ou null. Passos já usados são recusados para evitar reuso.
 */
function totp_verify(string $secret, string $code, ?int $lastStep = null): ?int
{
    $code = (string) preg_replace('/\s/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $key = base32_decode_string($secret);
    $current = intdiv(time(), TOTP_PERIOD);
    foreach ([-1, 0, 1] as $drift) {
        $step = $current + $drift;
        if ($lastStep !== null && $step <= $lastStep) {
            continue;
        }
        if (hash_equals(hotp_code($key, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $label = rawurlencode(TOTP_ISSUER . ':' . $account);
    return 'otpauth://totp/' . $label . '?' . http_build_query([
        'secret' => $secret,
        'issuer' => TOTP_ISSUER,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => TOTP_PERIOD,
    ], '', '&', PHP_QUERY_RFC3986);
}
