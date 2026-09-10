<?php

defined('CONTROLADORIA') || exit;

const BOT_COMMANDS = ['programados', 'semcapa', 'resumo', 'virais', 'ideias', 'ajuda'];

function setting_defaults(): array
{
    return [
        'alertas' => [
            'estoqueMinimoDias' => 14,
            'capaAvisoDias' => 3,
            'alertaEstoqueHora' => '08:00',
            'resumoSemanalAtivo' => true,
            'resumoSemanalDia' => 1,
            'resumoSemanalHora' => '08:00',
            'relatorioMensalAtivo' => true,
            'relatorioMensalHora' => '09:00',
            'viralMultiplicador' => 2.0,
        ],
        'bot' => [
            'ativo' => true,
            'grupoId' => '',
            'grupoNome' => 'Produção do canal',
            'comandos' => array_fill_keys(BOT_COMMANDS, true),
        ],
        'automacoes' => [
            'limpezaDia' => null,
            'alertaEstoqueDia' => null,
            'alertaEstoqueEnviadoEm' => null,
            'alertaCapaDia' => null,
            'alertaCapaEnviadoEm' => null,
            'resumoSemanalSemana' => null,
            'resumoSemanalEnviadoEm' => null,
            'relatorioMensalMes' => null,
            'relatorioMensalEnviadoEm' => null,
            'tentativas' => [],
        ],
    ];
}

function get_setting(string $key): array
{
    $defaults = setting_defaults()[$key];
    $raw = db_value('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
    $stored = $raw !== null ? json_decode((string) $raw, true) : null;
    return is_array($stored) ? array_replace_recursive($defaults, $stored) : $defaults;
}

function save_setting(string $key, array $value, ?string $userId = null): void
{
    db_run(
        'INSERT INTO settings (`key`, `value`, updated_by, updated_at) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
        [$key, json_encode($value, JSON_UNESCAPED_UNICODE), $userId, to_db(utc_now())]
    );
}

/** Cache curto guardado no banco (a hospedagem compartilhada não tem memória compartilhada). */
function cache_get(string $key)
{
    $raw = db_value('SELECT `value` FROM settings WHERE `key` = ?', ['cache:' . $key]);
    $data = $raw !== null ? json_decode((string) $raw, true) : null;
    return is_array($data) && (int) ($data['expira'] ?? 0) > time() ? ($data['valor'] ?? null) : null;
}

function cache_put(string $key, $value, int $seconds): void
{
    db_run(
        'INSERT INTO settings (`key`, `value`, updated_by, updated_at) VALUES (?, ?, NULL, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)',
        ['cache:' . $key, json_encode(['valor' => $value, 'expira' => time() + $seconds], JSON_UNESCAPED_UNICODE), to_db(utc_now())]
    );
}

/** HH:MM até 23:55: o Cron roda a cada 5 minutos e depois disso o envio já cairia no dia seguinte. */
function valid_hour(string $value): bool
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match)) {
        return false;
    }
    return (int) $match[1] * 60 + (int) $match[2] <= 23 * 60 + 55;
}

/** @return array{0: ?array, 1: ?string} dados validados ou mensagem de erro */
function validate_alert_settings(array $input): array
{
    $int = static fn (string $key) => filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    $data = [
        'estoqueMinimoDias' => $int('estoqueMinimoDias'),
        'capaAvisoDias' => $int('capaAvisoDias'),
        'alertaEstoqueHora' => (string) ($input['alertaEstoqueHora'] ?? ''),
        'resumoSemanalAtivo' => ($input['resumoSemanalAtivo'] ?? '') === 'on',
        'resumoSemanalDia' => $int('resumoSemanalDia'),
        'resumoSemanalHora' => (string) ($input['resumoSemanalHora'] ?? ''),
        'relatorioMensalAtivo' => ($input['relatorioMensalAtivo'] ?? '') === 'on',
        'relatorioMensalHora' => (string) ($input['relatorioMensalHora'] ?? ''),
        'viralMultiplicador' => round((float) str_replace(',', '.', (string) ($input['viralMultiplicador'] ?? '')), 2),
    ];
    if ($data['estoqueMinimoDias'] === false || $data['estoqueMinimoDias'] < 1 || $data['estoqueMinimoDias'] > 90) {
        return [null, 'O mínimo de dias de estoque precisa ficar entre 1 e 90.'];
    }
    if ($data['capaAvisoDias'] === false || $data['capaAvisoDias'] < 1 || $data['capaAvisoDias'] > 30) {
        return [null, 'O aviso de capa precisa ficar entre 1 e 30 dias.'];
    }
    if ($data['resumoSemanalDia'] === false || $data['resumoSemanalDia'] < 0 || $data['resumoSemanalDia'] > 6) {
        return [null, 'Escolha um dia da semana válido.'];
    }
    foreach (['alertaEstoqueHora', 'resumoSemanalHora', 'relatorioMensalHora'] as $hour) {
        if (!valid_hour($data[$hour])) {
            return [null, 'Use horários entre 00:00 e 23:55.'];
        }
    }
    if ($data['viralMultiplicador'] < 1.2 || $data['viralMultiplicador'] > 10) {
        return [null, 'O multiplicador de vídeo em alta precisa ficar entre 1,2 e 10.'];
    }
    return [$data, null];
}

/** @return array{0: ?array, 1: ?string} */
function validate_bot_settings(array $input): array
{
    $groupId = mb_substr(trim((string) ($input['grupoId'] ?? '')), 0, 80);
    if ($groupId !== '' && !preg_match('/^[\d-]+@g\.us$/', $groupId)) {
        return [null, 'O ID do grupo termina com @g.us.'];
    }
    $commands = [];
    foreach (BOT_COMMANDS as $command) {
        $commands[$command] = ($input['cmd_' . $command] ?? '') === 'on';
    }
    return [[
        'ativo' => ($input['ativo'] ?? '') === 'on',
        'grupoId' => $groupId,
        'grupoNome' => mb_substr(trim((string) ($input['grupoNome'] ?? '')), 0, 80),
        'comandos' => $commands,
    ], null];
}
