<?php
declare(strict_types=1);

/**
 * Database initialization script for Render.com deployment.
 * Reads DATABASE_URL, creates schema, and seeds sample data.
 */

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    echo "No DATABASE_URL set. Skipping initialization (local MySQL mode).\n";
    exit(0);
}

echo "Initializing PostgreSQL database...\n";

// Parse connection URL
$parts = parse_url($databaseUrl);
$host = $parts['host'] ?? '';
$port = $parts['port'] ?? 5432;
$database = ltrim($parts['path'] ?? '', '/');
$username = $parts['user'] ?? '';
$password = $parts['pass'] ?? '';

// Neon.tech (and most managed Postgres providers) require SSL.
// Honor the sslmode query parameter if present; otherwise require
// SSL automatically for Neon hosts.
$query = [];
parse_str($parts['query'] ?? '', $query);
$sslmode = $query['sslmode'] ?? null;
if ($sslmode === null && str_contains($host, 'neon.tech')) {
    $sslmode = 'require';
}
$dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database;
if ($sslmode !== null) {
    $dsn .= ';sslmode=' . $sslmode;
}

if (!$host || !$database) {
    echo "ERROR: Could not parse DATABASE_URL properly.\n";
    exit(1);
}

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if already initialized
    $check = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name='admins'");
    $tableCount = (int)$check->fetchColumn();

    if ($tableCount > 0) {
        echo "Database already initialized. Skipping setup.\n";
        exit(0);
    }

    // Apply schema
    echo "Creating schema...\n";
    $schema = file_get_contents(__DIR__ . '/database/schema_postgresql.sql');
    if ($schema === false) {
        throw new RuntimeException('Could not read schema file.');
    }

    // Split by semicolon and execute each statement
    // This handles multi-statement SQL files
    $statements = array_filter(array_map('trim', explode(';', $schema)), fn($s) => $s !== '');
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "Schema warning: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "Schema created.\n";

    // Apply sample data
    echo "Seeding sample data...\n";
    $sample = file_get_contents(__DIR__ . '/database/sample_data_postgresql.sql');
    if ($sample === false) {
        throw new RuntimeException('Could not read sample data file.');
    }

    $statements = array_filter(array_map('trim', explode(';', $sample)), fn($s) => $s !== '');
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            echo "Seed warning: " . $e->getMessage() . "\n";
        }
    }
    echo "Sample data inserted.\n";

    echo "Database initialization complete!\n";
    exit(0);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
