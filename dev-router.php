<?php

// Só para desenvolvimento local, com o servidor embutido do PHP:
//   php -S localhost:8080 dev-router.php
// (faz o papel do .htaccess, que o servidor embutido não lê)

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/assets/') && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
