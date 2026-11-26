ALTER TABLE mensajes_almacen
    ADD COLUMN IF NOT EXISTS tipo ENUM('aviso', 'alerta_stock', 'solicitud_reabastecimiento') NOT NULL DEFAULT 'aviso' AFTER receptor_rol,
    ADD COLUMN IF NOT EXISTS producto_id VARCHAR(100) NULL AFTER imagen_adjunta,
    ADD COLUMN IF NOT EXISTS cantidad_sugerida INT UNSIGNED NULL AFTER producto_id,
    ADD COLUMN IF NOT EXISTS cantidad_recibida INT UNSIGNED NULL AFTER cantidad_sugerida,
    ADD COLUMN IF NOT EXISTS estado ENUM('pendiente', 'atendida') NOT NULL DEFAULT 'pendiente' AFTER leido,
    ADD COLUMN IF NOT EXISTS atendida_por INT NULL AFTER estado,
    ADD COLUMN IF NOT EXISTS atendida_en DATETIME NULL AFTER atendida_por;

CREATE TABLE IF NOT EXISTS mensajes_almacen_lecturas (
    mensaje_id INT NOT NULL,
    usuario_id INT NOT NULL,
    leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mensaje_id, usuario_id),
    KEY idx_mensajes_lecturas_usuario (usuario_id, leido_en)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
