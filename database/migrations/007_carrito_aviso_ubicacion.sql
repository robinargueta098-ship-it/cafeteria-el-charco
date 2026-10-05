-- 007: texto editable del aviso de ubicación del carrito (Configuración → Carrito de pedidos).
-- Solo agrega una fila (INSERT): no necesita permisos de ALTER. Es idempotente.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('carrito_aviso_ubicacion',
   'Después de enviar tu pedido, mándanos tu ubicación por WhatsApp para poder enviarlo.',
   'Aviso del carrito: pedir la ubicación por WhatsApp después de enviar el pedido');
