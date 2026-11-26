CREATE TABLE IF NOT EXISTS ventas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vendedor_id BIGINT UNSIGNED NOT NULL,
    vendedor_nombre VARCHAR(80) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    iva DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL,
    efectivo_recibido DECIMAL(12,2) NULL,
    cambio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    referencia_transferencia VARCHAR(50) NULL,
    fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ventas_vendedor_fecha (vendedor_id, fecha_venta)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalle_ventas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venta_id BIGINT UNSIGNED NOT NULL,
    producto_id VARCHAR(100) NOT NULL,
    nombre_producto VARCHAR(255) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    subtotal_linea DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_detalle_ventas_venta (venta_id),
    KEY idx_detalle_ventas_producto (producto_id),
    CONSTRAINT fk_detalle_ventas_venta
        FOREIGN KEY (venta_id) REFERENCES ventas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
