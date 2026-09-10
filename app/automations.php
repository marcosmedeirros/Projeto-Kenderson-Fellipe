<?php

defined('CONTROLADORIA') || exit;

/** Se um envio falhar (Evolution fora do ar), tenta de novo depois deste intervalo. */
const AUTOMATION_RETRY_SECONDS = 1800;

function hour_to_minutes(string $hour): int
{
    [$h, $m] = array_map('intval', explode(':', $hour));
    return $h * 60 + $m;
}

function send_to_group(string $chatId, string $text, string $command): bool
{
    $result = evolution_send_text($chatId, $text);
    record_bot_message(['direction' => 'saida', 'origin' => 'automacao', 'chat_id' => $chatId, 'text' => $text, 'command' => $command, 'delivered' => $result['ok']]);
    if (!$result['ok']) {
        error_log("[controladoria] $command não enviado: {$result['error']}");
    }
    return $result['ok'];
}

/** Dispara os envios automáticos que já passaram do horário configurado. Chamado pelo Cron Job. */
function run_due_automations(?DateTimeImmutable $now = null): void
{
    // Evita envio duplicado se duas execuções do Cron se sobrepuserem.
    if ((int) db_value("SELECT GET_LOCK('controladoria_automacoes', 0)") !== 1) {
        return;
    }
    try {
        run_due_automations_locked($now ?? utc_now());
    } finally {
        db_value("SELECT RELEASE_LOCK('controladoria_automacoes')");
    }
}

function run_due_automations_locked(DateTimeImmutable $now): void
{
    $alerts = get_setting('alertas');
    $bot = get_setting('bot');
    $state = get_setting('automacoes');
    $next = $state;
    $sp = now_in_sao_paulo($now);
    $minutesNow = $sp['hour'] * 60 + $sp['minute'];
    $sentAt = to_db($now);

    // Limpeza diária: independe do bot.
    if ($state['limpezaDia'] !== $sp['day_key']) {
        purge_old_login_attempts();
        purge_expired_sessions();
        purge_old_audit_logs();
        $next['limpezaDia'] = $sp['day_key'];
    }

    if ($bot['ativo'] && $bot['grupoId'] !== '' && evolution_config() !== null) {
        $retries = is_array($state['tentativas']) ? $state['tentativas'] : [];
        $canTry = static fn (string $kind): bool => (int) ($retries[$kind] ?? 0) <= $now->getTimestamp();
        // Só marca como enviado quando o WhatsApp confirma; se falhar, agenda nova tentativa.
        $send = static function (string $kind, string $text) use (&$next, $bot, $now): bool {
            $ok = send_to_group($bot['grupoId'], $text, $kind);
            if ($ok) {
                unset($next['tentativas'][$kind]);
            } else {
                $next['tentativas'][$kind] = $now->getTimestamp() + AUTOMATION_RETRY_SECONDS;
            }
            return $ok;
        };

        if ($minutesNow >= hour_to_minutes($alerts['alertaEstoqueHora'])) {
            $videos = null;
            if ($state['alertaEstoqueDia'] !== $sp['day_key'] && $canTry('alerta-estoque')) {
                $videos = scheduled_videos();
                $stock = summarize_stock($videos, $now);
                if ($stock['days_covered'] >= $alerts['estoqueMinimoDias']) {
                    $next['alertaEstoqueDia'] = $sp['day_key'];
                } elseif ($send('alerta-estoque', build_stock_alert($stock, (int) $alerts['estoqueMinimoDias']))) {
                    $next['alertaEstoqueDia'] = $sp['day_key'];
                    $next['alertaEstoqueEnviadoEm'] = $sentAt;
                }
            }
            if ($state['alertaCapaDia'] !== $sp['day_key'] && $canTry('alerta-capa')) {
                $videos ??= scheduled_videos();
                $urgent = array_values(array_filter(
                    $videos,
                    static fn ($video) => $video['thumbnail_status'] !== 'ok' && days_until($video['publish_at'], $now) <= $alerts['capaAvisoDias']
                ));
                if (!$urgent) {
                    $next['alertaCapaDia'] = $sp['day_key'];
                } elseif ($send('alerta-capa', build_thumb_alert($urgent))) {
                    $next['alertaCapaDia'] = $sp['day_key'];
                    $next['alertaCapaEnviadoEm'] = $sentAt;
                }
            }
        }

        // Resumo semanal no dia escolhido; recupera até 2 dias depois se o Cron falhar.
        $configuredDay = (int) $alerts['resumoSemanalDia'] === 0 ? 7 : (int) $alerts['resumoSemanalDia'];
        $daysLate = $sp['iso_weekday'] - $configuredDay;
        $weeklyDue = ($daysLate === 0 && $minutesNow >= hour_to_minutes($alerts['resumoSemanalHora'])) || ($daysLate >= 1 && $daysLate <= 2);
        if ($alerts['resumoSemanalAtivo'] && $state['resumoSemanalSemana'] !== $sp['week_key'] && $weeklyDue && $canTry('resumo-semanal')) {
            if ($send('resumo-semanal', run_command('resumo'))) {
                $next['resumoSemanalSemana'] = $sp['week_key'];
                $next['resumoSemanalEnviadoEm'] = $sentAt;
            }
        }

        // Relatório mensal no dia 1º; recupera até o dia 3 se o Cron falhar.
        $monthlyDue = ($sp['day'] === 1 && $minutesNow >= hour_to_minutes($alerts['relatorioMensalHora'])) || ($sp['day'] > 1 && $sp['day'] <= 3);
        if ($alerts['relatorioMensalAtivo'] && $state['relatorioMensalMes'] !== $sp['month_key'] && $monthlyDue && $canTry('relatorio-mensal')) {
            if ($send('relatorio-mensal', build_monthly_report(shift_month($sp['month_key'], -1)))) {
                $next['relatorioMensalMes'] = $sp['month_key'];
                $next['relatorioMensalEnviadoEm'] = $sentAt;
            }
        }
    }

    if ($next !== $state) {
        save_setting('automacoes', $next);
    }
}
