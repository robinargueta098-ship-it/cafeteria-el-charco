-- 010: forma de mostrar el horario en el sitio (Configuración → Horario). Solo INSERT; idempotente.
-- 1 = juntar los días seguidos con el mismo horario ("Lunes a Viernes"); 0 = un renglón por día.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('horario_agrupar', '1', 'Horario del sitio: 1 = juntar los días seguidos con el mismo horario (Lunes a Viernes), 0 = un renglón por día');
