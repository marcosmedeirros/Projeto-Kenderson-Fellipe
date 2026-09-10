<?php

defined('CONTROLADORIA') || exit;

/**
 * As configurações (senhas do banco, chaves) ficam FORA do Git. Ordem de busca:
 *   1. caminho na variável de ambiente CONTROLADORIA_CONFIG
 *   2. um nível acima da pasta pública: ../controladoria-config.php (recomendado na Hostinger)
 *   3. config.local.php na raiz do projeto (desenvolvimento; bloqueado para acesso pela web)
 */
function config_path(): ?string
{
    $candidates = [
        (string) getenv('CONTROLADORIA_CONFIG'),
        dirname(ROOT_DIR) . '/controladoria-config.php',
        ROOT_DIR . '/config.local.php',
    ];
    foreach ($candidates as $path) {
        if ($path !== '' && @is_file($path)) {
            return $path;
        }
    }
    return null;
}

/** config('db.host'), config('admin'), config() ... */
function config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $path = config_path();
        $loaded = $path !== null ? require $path : [];
        $config = is_array($loaded) ? $loaded : [];
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function config_loaded(): bool
{
    return config() !== [];
}
