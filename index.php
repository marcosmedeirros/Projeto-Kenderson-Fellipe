<?php

declare(strict_types=1);

// Controladoria do Canal: ponto de entrada de todas as páginas.

define('CONTROLADORIA', true);
define('ROOT_DIR', __DIR__);

require ROOT_DIR . '/app/bootstrap.php';

dispatch();
