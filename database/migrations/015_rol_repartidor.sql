-- Rol Repartidor (id 3): solo ve los pedidos confirmados y los marca como entregados.
-- Idempotente: no hace nada si el rol ya existe.
INSERT INTO roles (nombre, descripcion)
SELECT 'Repartidor', 'Solo ve los pedidos confirmados por entregar y los marca como entregados'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'Repartidor');

-- Descripción del Cajero acorde a lo que realmente puede hacer.
UPDATE roles SET descripcion = 'Pedidos, Clientes, Lealtad, Recompensas y Cupones; Reportes limitados' WHERE nombre = 'Cajero';
