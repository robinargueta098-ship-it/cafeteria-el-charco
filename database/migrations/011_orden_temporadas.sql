-- 011: orden manual de las temporadas en el sitio (Temporadas → ▲▼). Solo INSERT; idempotente.
-- Lista JSON de ids de la primera a la última; vacía = por fecha de inicio. (Las promociones ordenan con `prioridad`.)
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('orden_temporadas', '[]', 'Orden de las temporadas en el sitio: lista JSON de ids (se cambia con ▲▼ en Temporadas)');
