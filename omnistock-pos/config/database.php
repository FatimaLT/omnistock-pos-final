<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function database_connection(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('OMNISTOCK_DB_HOST') ?: 'localhost';
    $port = getenv('OMNISTOCK_DB_PORT') ?: '3306';
    $database = getenv('OMNISTOCK_DB_NAME') ?: 'omnistock_db';
    $username = getenv('OMNISTOCK_DB_USER') ?: 'root';
    $password = getenv('OMNISTOCK_DB_PASSWORD') ?: '';

    try {
        // Intentar conectar a la base de datos remota
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
    } catch (PDOException $e) {
        // Si InfinityFree bloquea la conexión externa, usar base de datos local SQLite en Render
        $sqlitePath = sys_get_temp_dir() . '/omnistock.sqlite';
        $dsn = 'sqlite:' . $sqlitePath;

        $newConnection = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        require_once __DIR__ . '/database_initializer.php';
        initialize_database_schema($newConnection);

        $connection = $newConnection;
        return $connection;
    }
}
