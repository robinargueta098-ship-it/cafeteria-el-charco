-- 019: «Preparar con»: además de las leches, la opción Agua (o cualquier otra) que se ofrece solo en algunos productos.
-- REQUIERE un usuario de MySQL/MariaDB con permiso ALTER (la cuenta elcharco_app no lo tiene):
-- mysql -u root elcharco_cafeteria < 019_preparar_con_agua.sql

-- 1) Cada opción puede ofrecerse en todos los productos que permiten leche (0) o solo en los que se marquen para ella (1).
ALTER TABLE leches
  ADD COLUMN IF NOT EXISTS solo_productos TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = se ofrece solo en los productos que se marquen para esta opción (Leches → opción)' AFTER activo;

-- 2) Nombres completos (en el carrito y en WhatsApp se lee «Leche de almendras» o «Agua»).
UPDATE leches SET nombre = 'Leche entera' WHERE nombre = 'Entera';
UPDATE leches SET nombre = 'Leche deslactosada' WHERE nombre = 'Deslactosada';
UPDATE leches SET nombre = 'Leche de almendras' WHERE nombre = 'Almendras';
UPDATE leches SET nombre = 'Leche descremada' WHERE nombre = 'Descremada';

-- 3) La opción Agua (sin costo), solo en los productos que se marquen.
INSERT INTO leches (nombre, precio_extra, activo, solo_productos, orden)
SELECT 'Agua', 0.00, 1, 1, t.siguiente
FROM (SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM leches) AS t
WHERE NOT EXISTS (SELECT 1 FROM leches WHERE nombre = 'Agua');

-- 4) Qué productos ofrecen cada opción restringida (JSON {"id de la opción": [id de productos]}).
--    Por la descripción del menú: el Americano es «espresso suavizado con agua caliente», así que se ofrece con agua
--    (y deja de ofrecerse con leche).
SET @agua = (SELECT id FROM leches WHERE nombre = 'Agua' LIMIT 1);
SET @americano = (SELECT id FROM productos WHERE nombre = 'Americano' LIMIT 1);

INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion)
VALUES ('leche_opciones_productos',
        CASE WHEN @agua IS NULL OR @americano IS NULL THEN '{}' ELSE CONCAT('{"', @agua, '":[', @americano, ']}') END,
        'Productos en los que se ofrece cada opción de preparación restringida (id de la opción => lista de id de productos, en JSON)');

UPDATE configuracion_sistema
SET valor = REPLACE(REPLACE(REPLACE(valor,
        CONVERT(CONCAT('[', @americano, ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci, '['),
        CONVERT(CONCAT(',', @americano, ',') USING utf8mb4) COLLATE utf8mb4_unicode_ci, ','),
        CONVERT(CONCAT(',', @americano, ']') USING utf8mb4) COLLATE utf8mb4_unicode_ci, ']')
WHERE clave = 'leche_productos' AND @americano IS NOT NULL;
