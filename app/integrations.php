<?php

defined('CONTROLADORIA') || exit;

const INTEGRATION_KEYS = ['youtube', 'evolution', 'gemini'];

function integration_stored_config(string $key): ?array
{
    $row = db_one('SELECT config FROM integrations WHERE `key` = ?', [$key]);
    if ($row === null || $row['config'] === null) {
        return null;
    }
    try {
        $config = json_decode(decrypt_secret($row['config']), true);
        return is_array($config) ? $config : null;
    } catch (Throwable $error) {
        return null;
    }
}

/** Configuração do Evolution: a do painel tem prioridade sobre o arquivo de configuração. */
function evolution_config(): ?array
{
    $stored = integration_stored_config('evolution');
    if ($stored !== null) {
        return $stored + ['source' => 'painel'];
    }
    $file = config('evolution', []);
    if (!empty($file['url']) && !empty($file['instance']) && !empty($file['api_key'])) {
        return ['base_url' => $file['url'], 'instance' => $file['instance'], 'api_key' => $file['api_key'], 'source' => 'arquivo'];
    }
    return null;
}

function gemini_config(): ?array
{
    $defaultModel = (string) config('gemini.model', 'gemini-2.5-flash') ?: 'gemini-2.5-flash';
    $stored = integration_stored_config('gemini');
    if ($stored !== null) {
        return ['api_key' => $stored['api_key'], 'model' => $stored['model'] ?: $defaultModel, 'source' => 'painel'];
    }
    $key = (string) config('gemini.api_key', '');
    return $key !== '' ? ['api_key' => $key, 'model' => $defaultModel, 'source' => 'arquivo'] : null;
}

function save_integration(string $key, array $config, string $userId): void
{
    db_run(
        'INSERT INTO integrations (`key`, config, status, last_error, updated_by, updated_at) VALUES (?, ?, ?, NULL, ?, ?)
         ON DUPLICATE KEY UPDATE config = VALUES(config), status = VALUES(status), last_error = NULL,
                                 updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
        [$key, encrypt_secret(json_encode($config, JSON_UNESCAPED_UNICODE)), 'conectado', $userId, to_db(utc_now())]
    );
}

function remove_integration(string $key): void
{
    db_exec('DELETE FROM integrations WHERE `key` = ?', [$key]);
}

function mask_secret(string $secret): string
{
    return strlen($secret) <= 4 ? '••••' : '••••' . substr($secret, -4);
}

function youtube_connected(): bool
{
    return db_value('SELECT status FROM integrations WHERE `key` = ?', ['youtube']) === 'conectado';
}

/** Visão segura para a interface: nunca inclui chaves completas. */
function integrations_overview(): array
{
    $evolution = evolution_config();
    $gemini = gemini_config();
    $youtube = db_one('SELECT status, last_sync_at FROM integrations WHERE `key` = ?', ['youtube']);
    return [
        'youtube' => [
            'connected' => ($youtube['status'] ?? null) === 'conectado',
            'last_sync_at' => from_db($youtube['last_sync_at'] ?? null),
        ],
        'evolution' => $evolution === null ? ['configured' => false] : [
            'configured' => true,
            'source' => $evolution['source'],
            'base_url' => $evolution['base_url'],
            'instance' => $evolution['instance'],
            'api_key' => mask_secret($evolution['api_key']),
        ],
        'gemini' => $gemini === null ? ['configured' => false, 'model' => (string) config('gemini.model', 'gemini-2.5-flash')] : [
            'configured' => true,
            'source' => $gemini['source'],
            'model' => $gemini['model'],
            'api_key' => mask_secret($gemini['api_key']),
        ],
    ];
}
