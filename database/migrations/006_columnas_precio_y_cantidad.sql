-- 006 — Columnas más amplias para precios y cantidades de lealtad.
-- Aplicar UNA vez con un usuario administrador de MySQL (la cuenta de la aplicación no tiene ALTER),
-- por ejemplo desde phpMyAdmin → base elcharco_cafeteria → pestaña SQL.
--
-- * productos.precio / precio_alt: DECIMAL(6,2) (máx. 9999.99) -> DECIMAL(10,2).
-- * lealtad_movimientos.cantidad: SMALLINT (máx. 32767, truncaba en silencio) -> INT.
-- La aplicación ya valida rangos razonables (precio <= Q500, <= 30 cafés por consumo), así que esto es
-- un margen de seguridad: nada cambia para los datos existentes.
ALTER TABLE productos
  MODIFY precio     DECIMAL(10,2) NOT NULL,
  MODIFY precio_alt DECIMAL(10,2) NULL COMMENT 'Ej. precio "sin café" de un frappé';

ALTER TABLE lealtad_movimientos
  MODIFY cantidad INT NOT NULL COMMENT 'Cambio en el saldo sin convertir: +N consumo/ajuste_positivo/cancelada, -umbral recompensa_generada, -N ajuste_negativo, 0 recompensa_canjeada';
