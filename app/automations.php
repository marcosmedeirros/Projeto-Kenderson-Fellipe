<?php

defined('CONTROLADORIA') || exit;

function hour_to_minutes(string $hour): int
{
    [$h, $m] = array_map('intval', explode(':', $hour));
    return $h * 60 + $m;
}

function send_to_group(string $chatId, string $text, string $command): void
{
    $result = evolution_send_text($chatId, $text);
    record_bot_message(['direction' => 'saida', 'origin' => 'automacao', 'chat_id' => $chatId, 'text' => $text, 'command' => $command, 'delivered' => $result['ok']]);
    if (!$result['ok']) {
        error_log("[controladoria] $command não enviado: {$result['error']}");
    }
}

/** Dispara os envios automáticos que já passaram do horário configurado. Chamado pelo Cron Job. */
function run_due_automations(?DateTimeImmutable $now = null): void
{
    $now ??= utc_now();
    $alerts = get_setting('alertas');
    $bot = get_setting('bot');
    $state = get_setting('automacoes');
    $next = $state;
    $sp = now_in_sao_paulo($now);
    $minutesNow = $sp['hour'] * 60 + $sp['minute'];
    $dailyDue = $state['ultimoAlertaEstoque'] !== $sp['day_key'] && $minutesNow >= hour_to_minutes($alerts['alertaEstoqueHora']);

    if ($dailyDue) {
        purge_old_login_attempts();
        $next['ultimoAlertaEstoque'] = $sp['day_key'];
    }

    if ($bot['ativo'] && $bot['grupoId'] !== '') {
        if ($dailyDue) {
            $videos = scheduled_videos();
            $stock = summarize_stock($videos, $now);
            if ($stock['days_covered'] < $alerts['estoqueMinimoDias']) {
                send_to_group($bot['grupoId'], build_stock_alert($stock, (int) $alerts['estoqueMinimoDias']), 'alerta-estoque');
            }
            if ($state['ultimoAlertaCapa'] !== $sp['day_key']) {
                $urgent = array_values(array_filter($videos, static fn ($video) => $video['thumbnail_status'] !== 'ok' && days_until($video['publish_at'], $now) <= $alerts['capaAvisoDias']));
                if ($urgent) {
                    send_to_group($bot['grupoId'], build_thumb_alert($urgent), 'alerta-capa');
                }
                $next['ultimoAlertaCapa'] = $sp['day_key'];
            }
        }

        if ($alerts['resumoSemanalAtivo'] && $sp['weekday'] === (int) $alerts['resumoSemanalDia']
            && $state['ultimoResumoSemanal'] !== $sp['day_key'] && $minutesNow >= hour_to_minutes($alerts['resumoSemanalHora'])) {
            send_to_group($bot['grupoId'], run_command('resumo'), 'resumo-semanal');
            $next['ultimoResumoSemanal'] = $sp['day_key'];
        }

        if ($alerts['relatorioMensalAtivo'] && $sp['day'] === 1
            && $state['ultimoRelatorioMensal'] !== $sp['month_key'] && $minutesNow >= hour_to_minutes($alerts['relatorioMensalHora'])) {
            send_to_group($bot['grupoId'], build_monthly_report(shift_month($sp['month_key'], -1)), 'relatorio-mensal');
            $next['ultimoRelatorioMensal'] = $sp['month_key'];
        }
    }

    if ($next !== $state) {
        save_setting('automacoes', $next);
    }
}
