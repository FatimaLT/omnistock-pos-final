CREATE TABLE IF NOT EXISTS ordenes_compra (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id VARCHAR(100) NOT NULL,
    nombre_producto VARCHAR(255) NOT NULL,
    stock_actual DECIMAL(12,3) NOT NULL,
    stock_minimo INT NOT NULL,
    cantidad_solicitada INT UNSIGNED NOT NULL,
    estado ENUM('pendiente', 'recibida', 'cancelada') NOT NULL DEFAULT 'pendiente',
    solicitada_por BIGINT UNSIGNED NOT NULL,
    solicitada_por_nombre VARCHAR(80) NOT NULL,
    creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recibida_en DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ordenes_compra_producto_estado (producto_id, estado),
    KEY idx_ordenes_compra_fecha (creada_en)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
