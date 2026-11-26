<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function database_setting(string $name, string $legacyName, string $default): string
{
    foreach ([$name, $legacyName] as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function database_connection(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = database_setting('OMNISTOCK_DB_HOST', 'DB_HOST', 'localhost');
    $port = database_setting('OMNISTOCK_DB_PORT', 'DB_PORT', '3306');
    $database = database_setting('OMNISTOCK_DB_NAME', 'DB_NAME', 'omnistock_db');
    $username = database_setting('OMNISTOCK_DB_USER', 'DB_USER', 'root');
    $password = database_setting('OMNISTOCK_DB_PASSWORD', 'DB_PASSWORD', '');

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    $newConnection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    require_once __DIR__ . '/database_initializer.php';
    initialize_database_schema($newConnection);

    $connection = $newConnection;

    return $connection;
}
