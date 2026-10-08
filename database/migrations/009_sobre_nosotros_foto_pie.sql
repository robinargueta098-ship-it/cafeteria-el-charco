-- 009: pie de foto visible de "Sobre nosotros" (Configuración). Solo INSERT; idempotente.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('sobre_nosotros_foto_pie', '', 'Sobre nosotros: pie de foto (texto visible debajo de la foto)');
