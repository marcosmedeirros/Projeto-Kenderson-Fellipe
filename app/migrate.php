<?php

defined('CONTROLADORIA') || exit;

/**
 * Cria e atualiza as tabelas sozinho. Cada arquivo database/NNN_nome.sql roda uma única vez.
 * Depois de criar as tabelas, cadastra o primeiro administrador e os dados de exemplo.
 */
function ensure_database_ready(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $files = glob(ROOT_DIR . '/database/*.sql') ?: [];
    sort($files);
    $latest = $files ? (int) basename(end($files)) : 0;

    $pdo = db();
    try {
        $current = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
    } catch (PDOException $error) {
        $current = 0;
    }
    if ($current >= $latest) {
        return;
    }

    $pdo->query("SELECT GET_LOCK('controladoria_migracao', 30)")->fetchColumn();
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INT NOT NULL PRIMARY KEY, applied_at DATETIME(3) NOT NULL)
                     ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $current = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();

        foreach ($files as $file) {
            $version = (int) basename($file);
            if ($version <= $current) {
                continue;
            }
            $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }
            db_run('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', [$version, to_db(utc_now())]);
        }

        bootstrap_admin();
        if (config('demo_data') === true) {
            seed_demo_if_empty();
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('controladoria_migracao')")->fetchColumn();
    }
}

/** Cria o primeiro administrador a partir do arquivo de configuração, se não houver usuários. */
function bootstrap_admin(): void
{
    if ((int) db_value('SELECT COUNT(*) FROM users') > 0) {
        return;
    }
    $email = mb_strtolower(trim((string) config('admin.email', '')));
    $password = (string) config('admin.password', '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || validate_new_password($password) !== null) {
        error_log('[controladoria] Nenhum usuário: preencha admin.email e admin.password (10+ caracteres, letras e números) na configuração.');
        return;
    }
    db_insert('users', [
        'id' => uuid_v4(),
        'name' => trim((string) config('admin.name', 'Administrador')) ?: 'Administrador',
        'email' => $email,
        'password_hash' => hash_password($password),
        'role' => 'admin',
        'active' => 1,
        'must_change_password' => 1,
        'totp_enabled' => 0,
        'created_at' => to_db(utc_now()),
    ]);
}
