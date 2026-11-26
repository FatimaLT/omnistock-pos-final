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

    $pdo = null;

    try {
        // Intentar conectar a MySQL remoto
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // Fallback automático a SQLite local si la red bloquea la conexión externa
        $sqlitePath = sys_get_temp_dir() . '/omnistock.sqlite';
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    // Inicializador original si existe
    if (file_exists(__DIR__ . '/database_initializer.php')) {
        require_once __DIR__ . '/database_initializer.php';
        try {
            initialize_database_schema($pdo);
        } catch (Exception $ex) {
            // Ignorar si ya está inicializado
        }
    }

    // Asegurar estructura de tablas necesarias según el driver activo
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            rol TEXT NOT NULL DEFAULT 'vendedor'
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            codigo TEXT,
            precio REAL NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0
        )");
    } else {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `rol` VARCHAR(50) NOT NULL DEFAULT 'vendedor'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(255) NOT NULL,
                `codigo` VARCHAR(100),
                `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `stock` INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $ex) {
            // Ignorar si no hay permisos de creación DDL
        }
    }

    // Detectar tabla de usuarios activa
    $tableName = 'usuarios';
    try {
        $pdo->query("SHOW COLUMNS FROM `usuarios`");
    } catch (Exception $e) {
        try {
            $pdo->query("SHOW COLUMNS FROM `users`");
            $tableName = 'users';
        } catch (Exception $e2) {
            $tableName = 'usuarios';
        }
    }

    // Sembrar administrador por defecto si la tabla está vacía
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tableName`");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $adminPass = password_hash('Admin@OmniStock2026', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO `$tableName` (email, password_hash, rol) VALUES ('admin@omnistock.com', ?, 'admin')");
            $insert->execute([$adminPass]);
        }
    } catch (Exception $e) {
        // Ignorar
    }

    // Sembrar productos de prueba si la tabla products está vacía
    try {
        $stmtProd = $pdo->prepare("SELECT COUNT(*) FROM products");
        $stmtProd->execute();
        if ($stmtProd->fetchColumn() == 0) {
            $insertProd = $pdo->prepare("INSERT INTO products (nombre, codigo, precio, stock) VALUES (?, ?, ?, ?)");
            $insertProd->execute(['Producto de Prueba OmniStock', 'OMNI-001', 150.00, 25]);
            $insertProd->execute(['Lector de Código de Barras USB', 'OMNI-002', 450.00, 10]);
            $insertProd->execute(['Impresora de Tickets Térmica', 'OMNI-003', 1200.00, 5]);
        }
    } catch (Exception $e) {
        // Ignorar
    }

    $connection = $pdo;
    return $connection;
}
