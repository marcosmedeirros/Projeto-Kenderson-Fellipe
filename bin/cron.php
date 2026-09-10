<?php

declare(strict_types=1);

// Alertas automáticos (estoque, capas, resumo semanal e relatório mensal).
// Na Hostinger: Avançado → Cron Jobs → tipo PHP, a cada 5 minutos, apontando para este arquivo.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('CONTROLADORIA', true);
define('ROOT_DIR', dirname(__DIR__));

require ROOT_DIR . '/app/bootstrap.php';

ensure_database_ready();
run_due_automations();

echo '[' . gmdate('c') . "] automações verificadas\n";
