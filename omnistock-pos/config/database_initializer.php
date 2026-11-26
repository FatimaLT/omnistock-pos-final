<?php
declare(strict_types=1);

function omni_table_columns(PDO $pdo, string $table): array
{
    $allowedTables = ['productos', 'usuarios', 'ventas', 'detalle_ventas', 'mensajes_almacen'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Tabla de migración no permitida.');
    }

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll() as $column) {
        $columns[strtolower((string) $column['Field'])] = (string) $column['Field'];
    }

    return $columns;
}

function omni_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!isset(omni_table_columns($pdo, $table)[strtolower($column)])) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

function omni_seed_user(PDO $pdo, string $login, string $displayName, string $role, string $password): void
{
    if (strlen($password) < 12 || strlen($password) > 200) {
        throw new RuntimeException('Las contraseñas iniciales deben tener entre 12 y 200 caracteres.');
    }

    $columns = omni_table_columns($pdo, 'usuarios');
    $nameColumn = null;
    foreach (['nombre', 'nombre_usuario', 'username', 'usuario'] as $candidate) {
        if (isset($columns[$candidate])) {
            $nameColumn = $columns[$candidate];
            break;
        }
    }
    $emailColumn = $columns['email'] ?? $columns['correo'] ?? null;
    $passwordColumn = $columns['password_hash'] ?? $columns['password'] ?? $columns['contrasena'] ?? $columns['contraseña'] ?? null;
    $roleColumn = $columns['rol'] ?? $columns['tipo_usuario'] ?? $columns['role'] ?? null;
    $loginColumn = str_contains($login, '@') && $emailColumn !== null ? $emailColumn : $nameColumn;

    if ($loginColumn === null || $passwordColumn === null || $roleColumn === null) {
        throw new RuntimeException('La tabla usuarios no permite crear cuentas iniciales con un hash seguro.');
    }

    $exists = $pdo->prepare(
        'SELECT 1 FROM `usuarios` WHERE `' . str_replace('`', '``', $loginColumn) . '` = :login LIMIT 1'
    );
    $exists->execute(['login' => $login]);
    if ($exists->fetchColumn() !== false) {
        return;
    }

    $insertData = [
        $loginColumn => $login,
        $passwordColumn => password_hash($password, PASSWORD_DEFAULT),
        $roleColumn => $role,
    ];
    if ($nameColumn !== null && $nameColumn !== $loginColumn) {
        $insertData[$nameColumn] = $displayName;
    }
    if ($emailColumn !== null && $emailColumn !== $loginColumn && filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $insertData[$emailColumn] = $login;
    }

    $insertColumns = array_keys($insertData);
    $placeholders = [];
    foreach ($insertColumns as $index => $_column) {
        $placeholders[] = ':value_' . $index;
    }
    $statement = $pdo->prepare(
        'INSERT INTO `usuarios` (' .
        implode(', ', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $insertColumns)) .
        ') VALUES (' . implode(', ', $placeholders) . ')'
    );
    $statement->execute(array_combine($placeholders, array_values($insertData)));
}

function omni_seed_configured_users(PDO $pdo): void
{
    $adminPassword = getenv('OMNISTOCK_INITIAL_ADMIN_PASSWORD');
    if (is_string($adminPassword) && $adminPassword !== '') {
        $adminLogin = getenv('OMNISTOCK_INITIAL_ADMIN_USERNAME') ?: 'admin@omnistock.com';
        $omniAdminName = getenv('OMNISTOCK_INITIAL_ADMIN_NAME') ?: 'Administrador';
        omni_seed_user($pdo, $adminLogin, $omniAdminName, 'admin', $adminPassword);
    }

    if (getenv('OMNISTOCK_SEED_DEMO_SELLER') === '1') {
        $sellerPassword = getenv('OMNISTOCK_DEFAULT_SELLER_PASSWORD');
        if (!is_string($sellerPassword) || $sellerPassword === '') {
            throw new RuntimeException('Define OMNISTOCK_DEFAULT_SELLER_PASSWORD para habilitar la cuenta de prueba del vendedor.');
        }
        omni_seed_user($pdo, 'vendedor@omnistock.com', 'Vendedor de prueba', 'vendedor', $sellerPassword);
    }

    if (omni_public_demo_enabled()) {
        $demoUsers = [
            [
                'username_env' => 'OMNISTOCK_PUBLIC_DEMO_ADMIN_USERNAME',
                'password_env' => 'OMNISTOCK_PUBLIC_DEMO_ADMIN_PASSWORD',
                'name' => 'Administrador de demostración',
                'role' => 'admin',
            ],
            [
                'username_env' => 'OMNISTOCK_PUBLIC_DEMO_SELLER_USERNAME',
                'password_env' => 'OMNISTOCK_PUBLIC_DEMO_SELLER_PASSWORD',
                'name' => 'Cajero de demostración',
                'role' => 'vendedor',
            ],
        ];

        foreach ($demoUsers as $demoUser) {
            $demoUsername = trim((string) (getenv($demoUser['username_env']) ?: ''));
            $demoPassword = (string) (getenv($demoUser['password_env']) ?: '');
            if ($demoUsername === '' || $demoPassword === '') {
                continue;
            }
            if (strlen($demoUsername) > 80) {
                throw new RuntimeException('Los usuarios de demostración no pueden exceder 80 caracteres.');
            }

            omni_seed_user($pdo, $demoUsername, $demoUser['name'], $demoUser['role'], $demoPassword);
        }
    }
}

function initialize_database_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS omnistock_schema_migrations (
            version INT UNSIGNED NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $lockName = 'omnistock_schema_init_' . substr(hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
    $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $lock->execute(['lock_name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo obtener el bloqueo para inicializar el esquema de OmniStock.');
    }

    try {
        $migration = $pdo->prepare('SELECT 1 FROM omnistock_schema_migrations WHERE version = 1');
        $migration->execute();
        if ($migration->fetchColumn() === false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS usuarios (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    nombre_usuario VARCHAR(80) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    tipo_usuario ENUM('vendedor', 'admin') NOT NULL DEFAULT 'vendedor',
                    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_usuarios_nombre_usuario (nombre_usuario)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS ventas (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    vendedor_id BIGINT UNSIGNED NOT NULL,
                    vendedor_nombre VARCHAR(80) NOT NULL,
                    subtotal DECIMAL(12,2) NOT NULL,
                    iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    iva DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    total DECIMAL(12,2) NOT NULL,
                    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL DEFAULT 'efectivo',
                    efectivo_recibido DECIMAL(12,2) NULL,
                    cambio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    referencia_transferencia VARCHAR(50) NULL,
                    fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ventas_vendedor_fecha (vendedor_id, fecha_venta)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS detalle_ventas (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    venta_id BIGINT UNSIGNED NOT NULL,
                    producto_id VARCHAR(100) NOT NULL,
                    nombre_producto VARCHAR(255) NOT NULL,
                    precio_unitario DECIMAL(12,2) NOT NULL,
                    cantidad INT UNSIGNED NOT NULL,
                    subtotal_linea DECIMAL(12,2) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_detalle_ventas_venta (venta_id),
                    KEY idx_detalle_ventas_producto (producto_id)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS mensajes_almacen (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    emisor_id BIGINT UNSIGNED NOT NULL,
                    receptor_rol ENUM('vendedor', 'admin') NOT NULL,
                    receptor_id BIGINT UNSIGNED NULL,
                    respuesta_a BIGINT UNSIGNED NULL,
                    tipo ENUM('aviso', 'alerta_stock', 'solicitud_reabastecimiento') NOT NULL DEFAULT 'aviso',
                    asunto VARCHAR(150) NOT NULL,
                    mensaje TEXT NOT NULL,
                    imagen_adjunta VARCHAR(255) NULL,
                    producto_id VARCHAR(100) NULL,
                    cantidad_sugerida INT UNSIGNED NULL,
                    cantidad_recibida INT UNSIGNED NULL,
                    leido BOOLEAN NOT NULL DEFAULT FALSE,
                    estado ENUM('pendiente', 'atendida') NOT NULL DEFAULT 'pendiente',
                    atendida_por BIGINT UNSIGNED NULL,
                    atendida_en DATETIME NULL,
                    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mensajes_receptor (receptor_rol, receptor_id, fecha_envio),
                    KEY idx_mensajes_respuesta (respuesta_a),
                    KEY idx_mensajes_fecha (fecha_envio)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS mensajes_almacen_lecturas (
                    mensaje_id BIGINT UNSIGNED NOT NULL,
                    usuario_id BIGINT UNSIGNED NOT NULL,
                    leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (mensaje_id, usuario_id),
                    KEY idx_mensajes_lecturas_usuario (usuario_id, leido_en)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );

            foreach ([
                'vendedor_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
                'vendedor_nombre' => "VARCHAR(80) NOT NULL DEFAULT ''",
                'subtotal' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'iva_porcentaje' => 'DECIMAL(5,2) NOT NULL DEFAULT 0.00',
                'iva' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'total' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'metodo_pago' => "ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL DEFAULT 'efectivo'",
                'efectivo_recibido' => 'DECIMAL(12,2) NULL',
                'cambio' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'referencia_transferencia' => 'VARCHAR(50) NULL',
                'fecha_venta' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ] as $column => $definition) {
                omni_ensure_column($pdo, 'ventas', $column, $definition);
            }

            foreach ([
                'venta_id' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
                'producto_id' => "VARCHAR(100) NOT NULL DEFAULT ''",
                'nombre_producto' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'precio_unitario' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'cantidad' => 'INT UNSIGNED NOT NULL DEFAULT 1',
                'subtotal_linea' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            ] as $column => $definition) {
                omni_ensure_column($pdo, 'detalle_ventas', $column, $definition);
            }

            foreach ([
                'receptor_id' => 'BIGINT UNSIGNED NULL',
                'respuesta_a' => 'BIGINT UNSIGNED NULL',
                'tipo' => "ENUM('aviso', 'alerta_stock', 'solicitud_reabastecimiento') NOT NULL DEFAULT 'aviso'",
                'asunto' => "VARCHAR(150) NOT NULL DEFAULT ''",
                'mensaje' => 'TEXT NULL',
                'imagen_adjunta' => 'VARCHAR(255) NULL',
                'producto_id' => 'VARCHAR(100) NULL',
                'cantidad_sugerida' => 'INT UNSIGNED NULL',
                'cantidad_recibida' => 'INT UNSIGNED NULL',
                'leido' => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'estado' => "ENUM('pendiente', 'atendida') NOT NULL DEFAULT 'pendiente'",
                'atendida_por' => 'BIGINT UNSIGNED NULL',
                'atendida_en' => 'DATETIME NULL',
                'fecha_envio' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ] as $column => $definition) {
                omni_ensure_column($pdo, 'mensajes_almacen', $column, $definition);
            }

            $pdo->exec('INSERT INTO omnistock_schema_migrations (version) VALUES (1)');
        }

        $catalogMigration = $pdo->prepare('SELECT 1 FROM omnistock_schema_migrations WHERE version = 2');
        $catalogMigration->execute();
        if ($catalogMigration->fetchColumn() === false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS productos (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    nombre VARCHAR(180) NOT NULL,
                    descripcion TEXT NULL,
                    precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    stock INT UNSIGNED NOT NULL DEFAULT 0,
                    stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
                    imagen_url VARCHAR(255) NULL,
                    categoria VARCHAR(120) NOT NULL DEFAULT 'General',
                    codigo VARCHAR(100) NULL,
                    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_productos_codigo (codigo)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec('INSERT INTO omnistock_schema_migrations (version) VALUES (2)');
        }

        $reviewMigration = $pdo->prepare('SELECT 1 FROM omnistock_schema_migrations WHERE version = 3');
        $reviewMigration->execute();
        if ($reviewMigration->fetchColumn() === false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS resenas (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    usuario_id BIGINT UNSIGNED NOT NULL,
                    autor VARCHAR(80) NOT NULL,
                    calificacion TINYINT UNSIGNED NOT NULL,
                    comentario VARCHAR(1000) NOT NULL,
                    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_resenas_usuario (usuario_id),
                    KEY idx_resenas_fecha (creada_en)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec('INSERT INTO omnistock_schema_migrations (version) VALUES (3)');
        }

        $currencyMigration = $pdo->prepare('SELECT 1 FROM omnistock_schema_migrations WHERE version = 4');
        $currencyMigration->execute();
        if ($currencyMigration->fetchColumn() === false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS tipos_cambio (
                    codigo CHAR(3) NOT NULL,
                    mxn_por_unidad DECIMAL(12,6) NOT NULL,
                    actualizado_por BIGINT UNSIGNED NULL,
                    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (codigo)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "INSERT IGNORE INTO tipos_cambio (codigo, mxn_por_unidad)
                 VALUES ('USD', 17.200000), ('EUR', 18.600000), ('GBP', 22.000000)"
            );
            $pdo->exec('INSERT INTO omnistock_schema_migrations (version) VALUES (4)');
        }

        $replenishmentMigration = $pdo->prepare('SELECT 1 FROM omnistock_schema_migrations WHERE version = 5');
        $replenishmentMigration->execute();
        if ($replenishmentMigration->fetchColumn() === false) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS ordenes_compra (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    producto_id VARCHAR(100) NOT NULL,
                    nombre_producto VARCHAR(255) NOT NULL,
                    stock_actual DECIMAL(12,3) NOT NULL,
                    stock_minimo INT UNSIGNED NOT NULL,
                    cantidad_solicitada INT UNSIGNED NOT NULL,
                    solicitada_por BIGINT UNSIGNED NOT NULL,
                    solicitada_por_nombre VARCHAR(80) NOT NULL,
                    estado ENUM('pendiente', 'atendida') NOT NULL DEFAULT 'pendiente',
                    solicitada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ordenes_compra_producto_estado (producto_id, estado, id)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $pdo->exec('INSERT INTO omnistock_schema_migrations (version) VALUES (5)');
        }

        omni_seed_configured_users($pdo);
        $initialized = true;
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $release->execute(['lock_name' => $lockName]);
    }
}
