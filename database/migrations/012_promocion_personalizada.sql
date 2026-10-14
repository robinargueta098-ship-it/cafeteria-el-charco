-- 012: tipo de promoción "personalizada" (texto libre). REQUIERE un usuario de MySQL con permiso ALTER
-- (la cuenta elcharco_app de la aplicación no lo tiene): ejecutar en phpMyAdmin como administrador.
--
-- Permite crear promociones como "3x1", "Segundo a mitad de precio" o "Combo desayuno": el administrador
-- escribe el texto de la etiqueta y eso es lo que ve el cliente. Hasta que se ejecute, el formulario no
-- ofrece ese tipo y todo sigue funcionando igual.
--
-- No es idempotente (ADD COLUMN falla si ya existe): ejecutarla una sola vez.
ALTER TABLE promociones
  MODIFY COLUMN tipo ENUM('descuento_porcentaje','descuento_monto','dos_por_uno','producto_gratis','personalizada') NOT NULL,
  ADD COLUMN etiqueta_personalizada VARCHAR(40) NULL AFTER valor;
