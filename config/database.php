<?php
declare(strict_types=1);

// Local MySQL defaults (XAMPP)
const DB_HOST = '127.0.0.1';
const DB_NAME = 'timetable_generator';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Create one reusable PDO connection.
 * Automatically detects PostgreSQL (Render.com) or MySQL (local XAMPP).
 */
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl) {
        // PostgreSQL connection (Render.com deployment)
        $parts = parse_url($databaseUrl);
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $database = ltrim($parts['path'] ?? '/timetable_generator', '/');
        $username = $parts['user'] ?? '';
        $password = $parts['pass'] ?? '';

        $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database;
        $connection = new PDO($dsn, $username, $password, $options);
    } else {
        // MySQL connection (local XAMPP / development)
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $connection = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $connection;
}

/**
 * Detect which database driver is active.
 */
function db_driver(): string
{
    return getenv('DATABASE_URL') ? 'pgsql' : 'mysql';
}
