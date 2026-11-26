CREATE TABLE IF NOT EXISTS mensajes_almacen (
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
    KEY idx_mensajes_receptor_leido_fecha (receptor_rol, leido, fecha_envio),
    KEY idx_mensajes_receptor (receptor_rol, receptor_id, fecha_envio),
    KEY idx_mensajes_respuesta (respuesta_a),
    KEY idx_mensajes_fecha (fecha_envio)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes_almacen_lecturas (
    mensaje_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    leido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mensaje_id, usuario_id),
    CONSTRAINT fk_mensajes_lecturas_mensaje
        FOREIGN KEY (mensaje_id) REFERENCES mensajes_almacen (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mensajes_lecturas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_mensajes_lecturas_usuario (usuario_id, leido_en)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
