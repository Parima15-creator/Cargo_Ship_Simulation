<?php

/**
 * Lazily creates the application's PDO database connection.
 *
 * Credentials come from server environment variables so secrets do not enter
 * public assets. A static connection is reused during one request, exceptions
 * are enabled for explicit error handling, associative rows are returned, and
 * native prepared statements protect user-controlled SQL parameters.
 */
declare(strict_types=1);

// Open one configured MySQL connection per PHP request and reuse it.
function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = getenv('CARGO_DB_HOST') ?: '127.0.0.1';
    $name = getenv('CARGO_DB_NAME') ?: 'cargo_optimizer';
    $user = getenv('CARGO_DB_USER') ?: 'root';
    $pass = getenv('CARGO_DB_PASS') ?: '';
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
