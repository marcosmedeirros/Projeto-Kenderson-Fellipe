<?php

defined('CONTROLADORIA') || exit;

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

foreach ([
    'config', 'helpers', 'format', 'security', 'db', 'audit', 'totp', 'auth', 'settings',
    'integrations', 'channel', 'videos', 'storage', 'bot', 'ideas', 'automations', 'demo_seed', 'migrate',
    'icons', 'components', 'routes',
] as $file) {
    require __DIR__ . '/' . $file . '.php';
}

foreach (glob(__DIR__ . '/controllers/*.php') ?: [] as $controller) {
    require $controller;
}

set_exception_handler(function (Throwable $error): void {
    error_log('[controladoria] ' . $error);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $error . PHP_EOL);
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = config('debug') === true ? get_class($error) . ': ' . $error->getMessage() : '';
    echo view('layout/error', ['status' => 500, 'message' => $detail]);
});
