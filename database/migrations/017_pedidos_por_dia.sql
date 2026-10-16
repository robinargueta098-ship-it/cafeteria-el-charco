-- Días en que se reciben pedidos por la página y pausa manual (Configuración → Carrito de pedidos).
-- Idempotente: no pisa valores ya configurados.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('pedidos_dias', '0,1,2,3,4,5,6', 'Días en que se reciben pedidos por la página (0=domingo .. 6=sábado, separados por coma)'),
  ('pedidos_pausados', '0', 'Pausa manual de los pedidos por la página: 1 = pausados, 0 = activos'),
  ('pedidos_mensaje', '', 'Mensaje que ve el cliente cuando los pedidos están pausados (vacío = mensaje por defecto)');
