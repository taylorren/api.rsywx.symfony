<?php

/**
 * Minimal forward-only migration runner.
 *
 * Applies every migrations/*.sql file (sorted by filename) whose filename is
 * not yet recorded in the schema_migrations table. Safe to run repeatedly;
 * write migrations idempotently where possible.
 *
 * Usage:  php bin/migrate.php
 */

require __DIR__ . '/../config/env.php'; // loads vendor/autoload + .env

$host = $_ENV['DB_HOST'] ?? 'localhost';
$name = $_ENV['DB_NAME'] ?? 'rsywx';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset={$charset}",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (' .
    '  filename VARCHAR(255) NOT NULL PRIMARY KEY,' .
    '  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files, SORT_STRING);

$ran = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $applied, true)) {
        echo "skip    {$filename}\n";
        continue;
    }

    echo "apply   {$filename}\n";
    $sql = (string) file_get_contents($file);

    foreach (splitSqlStatements($sql) as $statement) {
        $pdo->exec($statement);
    }

    $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
    $stmt->execute([$filename]);
    $ran++;
}

echo $ran === 0 ? "Nothing to migrate.\n" : "Applied {$ran} migration(s).\n";

/**
 * Split a SQL file into individual statements.
 * Strips full-line "--" comments; statements are terminated by ";".
 *
 * @return string[]
 */
function splitSqlStatements(string $sql): array
{
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $lines[] = $line;
    }

    $statements = [];
    foreach (explode(';', implode("\n", $lines)) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk !== '') {
            $statements[] = $chunk;
        }
    }

    return $statements;
}
