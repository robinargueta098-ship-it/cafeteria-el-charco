-- =====================================================================
-- OPCIONAL — Inmutabilidad de recompensas a nivel de base de datos.
--
-- La aplicación ya impide reutilizar un código canjeado o cambiarle el
-- dueño (LealtadService, con transacción + SELECT ... FOR UPDATE + un
-- UPDATE que exige estado='disponible'). Este trigger es una segunda
-- barrera para el caso en que alguien modifique la tabla a mano
-- (phpMyAdmin, un script, un bug futuro): un CHECK no puede comparar
-- contra el valor anterior, un trigger sí.
--
-- Está APARTE de schema.sql a propósito: en algunos hostings compartidos
-- crear triggers exige privilegios que el usuario no tiene (error 1419
-- si el log binario está activo). Si en Hostinger falla, se puede omitir
-- sin afectar el funcionamiento: la aplicación se protege sola.
--
-- Ejecutar con un usuario con privilegio TRIGGER (en local, root):
--   mysql -u root elcharco_cafeteria < database/opcional_trigger_recompensas.sql
-- =====================================================================

DROP TRIGGER IF EXISTS trg_recompensas_inmutables;

DELIMITER $$

CREATE TRIGGER trg_recompensas_inmutables
BEFORE UPDATE ON recompensas
FOR EACH ROW
BEGIN
  -- Un código pertenece a un solo cliente para siempre.
  IF NEW.codigo <> OLD.codigo OR NEW.cliente_id <> OLD.cliente_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El codigo y el cliente de una recompensa no se pueden modificar.';
  END IF;

  -- canjeada / cancelada / vencida son estados finales: no hay vuelta
  -- a "disponible" (que sería reutilizar el código).
  IF OLD.estado <> 'disponible' AND NEW.estado <> OLD.estado THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Una recompensa que ya no esta disponible no puede cambiar de estado.';
  END IF;
END$$

DELIMITER ;
