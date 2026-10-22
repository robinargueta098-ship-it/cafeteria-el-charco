-- 018: leche elegible en el carrito (con precio editable) y envío a domicilio (costo y envío gratis configurables).
-- REQUIERE un usuario de MySQL/MariaDB con permisos CREATE/ALTER/TRIGGER (la cuenta elcharco_app no los tiene):
-- ejecutar como administrador, por ejemplo: mysql -u root elcharco_cafeteria < 018_leches_y_envio.sql
-- (en phpMyAdmin deja el delimitador por defecto: el archivo usa DELIMITER para el disparador).

-- 1) Leches disponibles (se administran en el panel: Leches). Dinero: DECIMAL como en productos.
CREATE TABLE IF NOT EXISTS leches (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(40) NOT NULL,
  precio_extra   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Lo que se suma al precio del producto (Q0.00 = sin costo)',
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  orden          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_leches_nombre (nombre),
  INDEX idx_leches_activo (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO leches (nombre, precio_extra, activo, orden)
SELECT * FROM (
  SELECT 'Entera' AS nombre, 0.00 AS precio_extra, 1 AS activo, 1 AS orden UNION ALL
  SELECT 'Deslactosada', 2.00, 1, 2 UNION ALL
  SELECT 'Almendras', 3.00, 1, 3 UNION ALL
  SELECT 'Descremada', 0.00, 1, 4
) AS semilla
WHERE NOT EXISTS (SELECT 1 FROM leches);

-- 2) Ítems del pedido: la leche elegida (nombre y lo que costó de más, por unidad) y una clave más larga.
ALTER TABLE pedido_items
  MODIFY COLUMN clave VARCHAR(40) NOT NULL COMMENT 'Clave del carrito: 12, 12-alt, 12~c, 12-alt~f~l3, envio',
  ADD COLUMN IF NOT EXISTS leche_snapshot VARCHAR(60) NULL COMMENT 'Leche elegida (nombre al momento del pedido)' AFTER tipo_snapshot,
  ADD COLUMN IF NOT EXISTS leche_extra_centavos BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Extra por unidad por la leche; ya está incluido en el precio unitario' AFTER leche_snapshot;

DROP TRIGGER IF EXISTS trg_items_no_editar;
DELIMITER $$
-- producto_id sí puede cambiar (pasa a NULL solo, por la llave foránea, si se borra el producto).
CREATE TRIGGER trg_items_no_editar BEFORE UPDATE ON pedido_items FOR EACH ROW
BEGIN
  IF NOT (NEW.pedido_id <=> OLD.pedido_id AND NEW.clave <=> OLD.clave AND NEW.nombre_snapshot <=> OLD.nombre_snapshot
          AND NEW.categoria_snapshot <=> OLD.categoria_snapshot AND NEW.tipo_snapshot <=> OLD.tipo_snapshot
          AND NEW.leche_snapshot <=> OLD.leche_snapshot AND NEW.leche_extra_centavos <=> OLD.leche_extra_centavos
          AND NEW.precio_unitario_centavos <=> OLD.precio_unitario_centavos AND NEW.cantidad <=> OLD.cantidad
          AND NEW.subtotal_centavos <=> OLD.subtotal_centavos AND NEW.es_cortesia <=> OLD.es_cortesia) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ítems de un pedido no se pueden modificar';
  END IF;
END$$
DELIMITER ;

-- 3) Ajustes de envío y productos con leche (configuración, editable desde el panel).
--    El envío se cobra como una línea más del pedido («Envío a domicilio»), así el total siempre es la suma de sus líneas.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('envio_activo', '1', 'Cobrar envío a domicilio en los pedidos del carrito: 1 = sí, 0 = no'),
  ('envio_costo', '5.00', 'Costo del envío a domicilio en quetzales'),
  ('envio_gratis_desde', '5', 'Envío gratis a partir de esta cantidad de bebidas (0 = nunca es gratis)');

-- Categorías cuyas unidades cuentan como «bebidas» para el envío gratis (todas menos las de sándwiches).
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion)
SELECT 'envio_categorias',
       COALESCE(CONCAT('[', GROUP_CONCAT(id ORDER BY id SEPARATOR ','), ']'), '[]'),
       'Categorías que cuentan como bebidas para el envío gratis (lista de id en JSON)'
FROM categorias
WHERE nombre NOT LIKE '%ándwich%' AND nombre NOT LIKE '%andwich%';

-- Productos que permiten elegir leche (lista de id en JSON): Café, Bebidas con café y Bebidas sin café, salvo
-- los que no llevan leche (Espresso, Affogato y Carajillo). Se ajusta desde el panel: Leches.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion)
SELECT 'leche_productos',
       COALESCE(CONCAT('[', GROUP_CONCAT(p.id ORDER BY p.id SEPARATOR ','), ']'), '[]'),
       'Productos que permiten elegir leche (lista de id en JSON)'
FROM productos p JOIN categorias c ON c.id = p.categoria_id
WHERE c.nombre IN ('Café', 'Bebidas con café', 'Bebidas sin café')
  AND p.nombre NOT LIKE 'Affogato%' AND p.nombre NOT LIKE 'Carajillo%' AND p.nombre NOT LIKE 'Espresso%';

-- El texto fijo «Leches disponibles: …» de la categoría Café ya no hace falta: el menú lo arma con las leches activas.
UPDATE categorias SET notas = NULL WHERE nombre = 'Café' AND notas LIKE 'Leches disponibles%';
