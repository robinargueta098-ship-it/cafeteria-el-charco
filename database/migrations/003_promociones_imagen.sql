-- 003 — Foto opcional para las promociones, mostrada en el sitio público.
-- Aplicar una sola vez sobre una base creada con un schema.sql anterior.
ALTER TABLE promociones
  ADD COLUMN imagen VARCHAR(255) NULL COMMENT 'Nombre de archivo en public/uploads/promociones/; NULL = sin foto' AFTER descripcion;
