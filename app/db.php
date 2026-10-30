<?php
declare(strict_types=1);

/**
 * Shared PDO connection. MySQL when DB_HOST is configured, SQLite otherwise.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config('db');
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        if ($cfg['driver'] === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']);
            if ($cfg['name'] !== '') {
                $dsn .= ';dbname=' . $cfg['name'];
            }
            try {
                $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
            } catch (PDOException $e) {
                // Auto-provision the schema on first boot if it does not exist yet.
                if ($cfg['name'] === '' || !str_contains($e->getMessage(), 'Unknown database')) {
                    throw $e;
                }
                $bootstrap = new PDO(
                    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']),
                    $cfg['user'],
                    $cfg['pass'],
                    $options
                );
                $bootstrap->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $cfg['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $bootstrap = null;
                $dsn .= ';dbname=' . $cfg['name'];
                $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
            }
            $pdo->exec("SET time_zone = '+00:00'");
        } else {
            $path = $cfg['sqlite'];
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Database connection failed.\n\n" . $e->getMessage() . "\n\n";
        echo "Set DB_HOST, DB_NAME, DB_USER and DB_PASS to point at MySQL.\n";
        exit;
    }

    return $pdo;
}

function db_driver(): string
{
    return config('db')['driver'];
}

function db_is_mysql(): bool
{
    return db_driver() === 'mysql';
}
