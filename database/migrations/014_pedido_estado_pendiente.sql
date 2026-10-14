-- 014: estado «pendiente» de los pedidos (el cliente tocó «Enviar por WhatsApp» pero todavía no confirmó que sí lo envió).
-- REQUIERE un usuario de MySQL con permiso ALTER (la cuenta elcharco_app no lo tiene): ejecutar como administrador.
--
-- Flujo: el pedido se guarda como `pendiente` al tocar el botón; pasa a `enviado` cuando el cliente pulsa
-- «Sí, ya lo envié» en el carrito. Mientras esté `pendiente` no se muestra en la lista normal de Pedidos ni cuenta en
-- los reportes; el personal puede verlo con el filtro «Sin confirmar» (y confirmarlo si le llegó el WhatsApp con el código).

ALTER TABLE pedidos
  MODIFY COLUMN estado ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NOT NULL DEFAULT 'pendiente';

ALTER TABLE pedido_historial
  MODIFY COLUMN estado_anterior ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NULL,
  MODIFY COLUMN estado_nuevo    ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NOT NULL;
