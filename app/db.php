<?php

defined('CONTROLADORIA') || exit;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = config('db', []);
    if (!is_array($config) || empty($config['name']) || empty($config['user'])) {
        throw new RuntimeException('Banco não configurado: preencha db.name, db.user e db.pass no arquivo de configuração.');
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'] ?? 'localhost',
        (int) ($config['port'] ?? 3306),
        $config['name']
    );
    $pdo = new PDO($dsn, (string) $config['user'], (string) ($config['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function db_run(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute(array_map(static fn ($value) => is_bool($value) ? (int) $value : $value, array_values($params)));
    return $statement;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function db_exec(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/** Uso interno: nomes de tabela e colunas nunca vêm do usuário. */
function db_insert(string $table, array $data): string
{
    $columns = implode(', ', array_map(static fn ($column) => "`$column`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    db_run("INSERT INTO `$table` ($columns) VALUES ($placeholders)", array_values($data));
    return (string) db()->lastInsertId();
}

/** Uso interno: nomes de tabela e colunas nunca vêm do usuário. */
function db_update(string $table, array $data, string $where, array $whereParams): int
{
    $set = implode(', ', array_map(static fn ($column) => "`$column` = ?", array_keys($data)));
    return db_exec("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams));
}

function db_placeholders(array $values): string
{
    return implode(', ', array_fill(0, max(1, count($values)), '?'));
}
