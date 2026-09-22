-- 005 — Cupones de promociones vinculadas a lealtad (docs/arquitectura.md §4.20).
-- Aplicar una sola vez sobre una base creada con un schema.sql anterior.
-- Tabla nueva, no toca ninguna existente.
CREATE TABLE cupones_promocion (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                  VARCHAR(12) NOT NULL UNIQUE,
  cliente_id              INT UNSIGNED NOT NULL,
  promocion_id            INT UNSIGNED NOT NULL,
  estado                  ENUM('disponible','canjeado','cancelado','vencido') NOT NULL DEFAULT 'disponible',
  fecha_reclamo           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  usuario_canjeo_id       INT UNSIGNED NULL,
  fecha_canje             DATETIME NULL,

  usuario_cancelacion_id  INT UNSIGNED NULL,
  fecha_cancelacion       DATETIME NULL,
  motivo_cancelacion      VARCHAR(255) NULL,

  CONSTRAINT fk_cupon_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_cupon_promocion FOREIGN KEY (promocion_id) REFERENCES promociones(id),
  CONSTRAINT fk_cupon_usuario_canjeo FOREIGN KEY (usuario_canjeo_id) REFERENCES usuarios(id),
  CONSTRAINT fk_cupon_usuario_cancelacion FOREIGN KEY (usuario_cancelacion_id) REFERENCES usuarios(id),
  INDEX idx_cupon_cliente (cliente_id, estado),

  UNIQUE KEY uq_cupon_cliente_promocion (cliente_id, promocion_id),
  CONSTRAINT chk_cupon_codigo_longitud CHECK (CHAR_LENGTH(codigo) >= 8),
  CONSTRAINT chk_cupon_canje_completo CHECK (
    estado <> 'canjeado' OR (fecha_canje IS NOT NULL AND usuario_canjeo_id IS NOT NULL)
  ),
  CONSTRAINT chk_cupon_cancelado_completo CHECK (
    estado <> 'cancelado' OR (fecha_cancelacion IS NOT NULL AND usuario_cancelacion_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
