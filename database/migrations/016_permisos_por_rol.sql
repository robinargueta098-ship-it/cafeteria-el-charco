-- Permisos por rol y módulo (el Administrador los configura en «Roles y permisos»).
-- nivel: ninguno < ver < editar. El Administrador (rol 1) siempre puede todo y no tiene filas aquí.
-- Los valores iniciales reproducen lo que Cajero y Repartidor ya podían hacer.

CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_id  INT UNSIGNED NOT NULL,
  modulo  VARCHAR(30)  NOT NULL,
  nivel   ENUM('ninguno','ver','editar') NOT NULL DEFAULT 'ninguno',
  PRIMARY KEY (rol_id, modulo),
  CONSTRAINT fk_rol_permisos_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rol_permisos (rol_id, modulo, nivel)
SELECT r.id, m.modulo, m.nivel
FROM roles r
JOIN (
  SELECT 'Cajero' AS rol, 'pedidos' AS modulo, 'editar' AS nivel UNION ALL
  SELECT 'Cajero', 'reportes', 'ver' UNION ALL
  SELECT 'Cajero', 'clientes', 'editar' UNION ALL
  SELECT 'Cajero', 'lealtad', 'editar' UNION ALL
  SELECT 'Cajero', 'canjear', 'editar' UNION ALL
  SELECT 'Cajero', 'recompensas', 'ver' UNION ALL
  SELECT 'Cajero', 'cupones', 'ver' UNION ALL
  SELECT 'Repartidor', 'pedidos', 'editar'
) m ON m.rol = r.nombre;
