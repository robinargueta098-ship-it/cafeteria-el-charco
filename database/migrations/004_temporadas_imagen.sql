-- 004 — Foto opcional para las temporadas (banner de "De temporada" en el sitio
-- público, aunque todavía no tenga productos etiquetados). Aplicar una sola vez.
ALTER TABLE temporadas
  ADD COLUMN imagen VARCHAR(255) NULL COMMENT 'Nombre de archivo en public/uploads/temporadas/; NULL = sin foto' AFTER descripcion;
