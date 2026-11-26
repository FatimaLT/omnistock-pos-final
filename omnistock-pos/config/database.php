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
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
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
        // SQLite local fallback
        $sqlitePath = sys_get_temp_dir() . '/omnistock.sqlite';
        $newConnection = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        require_once __DIR__ . '/database_initializer.php';
        initialize_database_schema($newConnection);

        // Crear usuario demo automático si no existe
        $adminUser = getenv('OMNISTOCK_PUBLIC_DEMO_ADMIN_USERNAME') ?: 'admin@omnistock.com';
        $adminPass = password_hash(getenv('OMNISTOCK_PUBLIC_DEMO_ADMIN_PASSWORD') ?: 'Admin@OmniStock2026', PASSWORD_DEFAULT);

        $stmt = $newConnection->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$adminUser]);
        if ($stmt->fetchColumn() == 0) {
            $insert = $newConnection->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
            $insert->execute([$adminUser, $adminPass]);
        }

        $connection = $newConnection;
        return $connection;
    }
}
