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
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        $sqlitePath = sys_get_temp_dir() . '/omnistock.sqlite';
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    if (file_exists(__DIR__ . '/database_initializer.php')) {
        require_once __DIR__ . '/database_initializer.php';
        try {
            initialize_database_schema($pdo);
        } catch (Exception $ex) {}
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, rol TEXT NOT NULL DEFAULT 'vendedor')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, codigo TEXT, precio REAL NOT NULL DEFAULT 0, stock INTEGER NOT NULL DEFAULT 0)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS productos (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, codigo TEXT, precio REAL NOT NULL DEFAULT 0, stock INTEGER NOT NULL DEFAULT 0)");
    } else {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (`id` INT AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(100) NOT NULL UNIQUE, `password_hash` VARCHAR(255) NOT NULL, `rol` VARCHAR(50) NOT NULL DEFAULT 'vendedor') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (`id` INT AUTO_INCREMENT PRIMARY KEY, `nombre` VARCHAR(255) NOT NULL, `codigo` VARCHAR(100), `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00, `stock` INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `productos` (`id` INT AUTO_INCREMENT PRIMARY KEY, `nombre` VARCHAR(255) NOT NULL, `codigo` VARCHAR(100), `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00, `stock` INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $ex) {}
    }

    // Sembrar datos en ambas tablas de productos para asegurar compatibilidad
    foreach (['products', 'productos'] as $tbl) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl`");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $insert = $pdo->prepare("INSERT INTO `$tbl` (nombre, codigo, precio, stock) VALUES (?, ?, ?, ?)");
                $insert->execute(['Producto de Prueba OmniStock', 'OMNI-001', 150.00, 25]);
                $insert->execute(['Lector de Código de Barras USB', 'OMNI-002', 450.00, 10]);
                $insert->execute(['Impresora de Tickets Térmica', 'OMNI-003', 1200.00, 5]);
            }
        } catch (Exception $e) {}
    }

    $connection = $pdo;
    return $connection;
}
