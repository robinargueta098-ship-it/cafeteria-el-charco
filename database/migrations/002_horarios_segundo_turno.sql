-- 002 — Horario partido: segundo turno opcional por día.
-- Aplicar una sola vez sobre una base creada con un schema.sql anterior.
-- Los turnos existentes (hora_apertura/hora_cierre) no se tocan; las
-- columnas nuevas quedan NULL hasta que se guarde el segundo turno desde
-- Configuración → Horario de atención.
ALTER TABLE horarios_atencion
  ADD COLUMN hora_apertura_2 TIME NULL COMMENT 'Segundo turno (horario partido), opcional' AFTER hora_cierre,
  ADD COLUMN hora_cierre_2   TIME NULL AFTER hora_apertura_2,
  ADD CONSTRAINT chk_horario_turno2_completo CHECK ((hora_apertura_2 IS NULL) = (hora_cierre_2 IS NULL));
