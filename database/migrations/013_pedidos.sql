-- 013: pedidos del carrito (checkout por WhatsApp) guardados en la base de datos.
-- REQUIERE un usuario de MySQL con permisos CREATE/TRIGGER (la cuenta elcharco_app no los tiene): ejecutar
-- en phpMyAdmin como administrador, o por consola: mysql -u root elcharco_cafeteria < 013_pedidos.sql
-- (en phpMyAdmin, deja el delimitador por defecto: el archivo usa DELIMITER para los disparadores).
--
-- DINERO: siempre en CENTAVOS ENTEROS (BIGINT UNSIGNED), nunca DECIMAL/FLOAT.
-- Fechas: hora de Guatemala (la conexión fija time_zone = -06:00).
-- Los pedidos NO se borran ni se editan en ítems/montos: lo garantizan los disparadores de abajo (además de la app).

CREATE TABLE IF NOT EXISTS pedidos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo              CHAR(6) NOT NULL COMMENT 'Código corto que ve el cliente; sin O/0/I/1',
  token_idempotencia  VARCHAR(64) NOT NULL COMMENT 'Lo genera el navegador: el mismo token no crea dos pedidos',
  huella_carrito      CHAR(64) NOT NULL COMMENT 'SHA-256 del contenido: detecta un token reutilizado con otro carrito',
  cliente_nombre      VARCHAR(60) NULL,
  cliente_telefono    VARCHAR(20) NULL,
  cliente_id          INT UNSIGNED NULL,
  direccion           VARCHAR(200) NULL,
  forma_pago          ENUM('contra_entrega','transferencia','tarjeta_fisica') NOT NULL,
  subtotal_centavos   BIGINT UNSIGNED NOT NULL,
  descuento_centavos  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_centavos      BIGINT UNSIGNED NOT NULL,
  estado              ENUM('enviado','confirmado','entregado','cancelado','anulado') NOT NULL DEFAULT 'enviado',
  ip                  VARCHAR(45) NULL,
  creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entregado_en        DATETIME NULL,
  actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actualizado_por     INT UNSIGNED NULL,
  nota                VARCHAR(300) NULL,
  UNIQUE KEY uq_pedidos_codigo (codigo),
  UNIQUE KEY uq_pedidos_token (token_idempotencia),
  KEY idx_pedidos_creado (creado_en),
  KEY idx_pedidos_entregado (entregado_en),
  KEY idx_pedidos_estado (estado),
  KEY idx_pedidos_cliente (cliente_id),
  KEY idx_pedidos_ip (ip, creado_en),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT chk_pedidos_total CHECK (total_centavos + descuento_centavos = subtotal_centavos),
  CONSTRAINT chk_pedidos_maximo CHECK (total_centavos <= 500000),
  CONSTRAINT chk_pedidos_entregado CHECK (estado NOT IN ('entregado') OR entregado_en IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_items (
  id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id                 BIGINT UNSIGNED NOT NULL,
  producto_id               INT UNSIGNED NULL COMMENT 'NULL si el producto se borró; el nombre queda en nombre_snapshot',
  clave                     VARCHAR(20) NOT NULL COMMENT 'Clave del carrito: 12, 12-alt, 12~c, 12-alt~f',
  nombre_snapshot           VARCHAR(200) NOT NULL,
  categoria_snapshot        VARCHAR(100) NOT NULL,
  tipo_snapshot             VARCHAR(60) NOT NULL COMMENT 'Versión y temperatura: Normal, Batido, Normal · Caliente…',
  precio_unitario_centavos  BIGINT UNSIGNED NOT NULL,
  cantidad                  SMALLINT UNSIGNED NOT NULL,
  subtotal_centavos         BIGINT UNSIGNED NOT NULL,
  es_cortesia               TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sale gratis (recompensa/cupón): precio 0 y no cuenta como ingreso',
  KEY idx_items_pedido (pedido_id),
  KEY idx_items_producto (producto_id),
  CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_items_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
  CONSTRAINT chk_items_cantidad CHECK (cantidad BETWEEN 1 AND 20),
  CONSTRAINT chk_items_subtotal CHECK (subtotal_centavos = precio_unitario_centavos * cantidad),
  CONSTRAINT chk_items_cortesia CHECK (es_cortesia = 0 OR precio_unitario_centavos = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_historial (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  estado_anterior  ENUM('enviado','confirmado','entregado','cancelado','anulado') NULL,
  estado_nuevo     ENUM('enviado','confirmado','entregado','cancelado','anulado') NOT NULL,
  usuario_id       INT UNSIGNED NULL COMMENT 'NULL = el propio cliente al enviarlo, o usuario eliminado después',
  motivo           VARCHAR(255) NULL,
  fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_historial_pedido (pedido_id, fecha),
  CONSTRAINT fk_historial_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

-- Los pedidos no se borran (ni desde el panel ni desde código).
CREATE TRIGGER trg_pedidos_no_borrar BEFORE DELETE ON pedidos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pedidos no se pueden eliminar';
END$$

-- Tras crearse, no cambian montos, ítems de identidad, código ni forma de pago (solo estado, entrega y nota).
CREATE TRIGGER trg_pedidos_no_editar_montos BEFORE UPDATE ON pedidos FOR EACH ROW
BEGIN
  IF NOT (NEW.codigo <=> OLD.codigo AND NEW.token_idempotencia <=> OLD.token_idempotencia
          AND NEW.huella_carrito <=> OLD.huella_carrito AND NEW.subtotal_centavos <=> OLD.subtotal_centavos
          AND NEW.descuento_centavos <=> OLD.descuento_centavos AND NEW.total_centavos <=> OLD.total_centavos
          AND NEW.forma_pago <=> OLD.forma_pago AND NEW.creado_en <=> OLD.creado_en) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los montos y datos de un pedido no se pueden modificar';
  END IF;
END$$

CREATE TRIGGER trg_items_no_borrar BEFORE DELETE ON pedido_items FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ítems de un pedido no se pueden eliminar';
END$$

-- producto_id sí puede cambiar (pasa a NULL solo, por la llave foránea, si se borra el producto).
CREATE TRIGGER trg_items_no_editar BEFORE UPDATE ON pedido_items FOR EACH ROW
BEGIN
  IF NOT (NEW.pedido_id <=> OLD.pedido_id AND NEW.clave <=> OLD.clave AND NEW.nombre_snapshot <=> OLD.nombre_snapshot
          AND NEW.categoria_snapshot <=> OLD.categoria_snapshot AND NEW.tipo_snapshot <=> OLD.tipo_snapshot
          AND NEW.precio_unitario_centavos <=> OLD.precio_unitario_centavos AND NEW.cantidad <=> OLD.cantidad
          AND NEW.subtotal_centavos <=> OLD.subtotal_centavos AND NEW.es_cortesia <=> OLD.es_cortesia) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ítems de un pedido no se pueden modificar';
  END IF;
END$$

DELIMITER ;
