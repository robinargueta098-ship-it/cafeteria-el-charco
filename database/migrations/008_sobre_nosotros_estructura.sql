-- 008: "Sobre nosotros" editable por partes (introducción, subtítulo, tarjetas, cierre) y con foto propia.
-- Solo agrega filas a configuracion_sistema (INSERT): no necesita permisos de ALTER. Es idempotente.
-- sobre_nosotros_estructura queda VACÍA a propósito: mientras no se guarde desde el panel, el sitio sigue
-- mostrando el texto anterior (sobre_nosotros_texto) con el formateador de siempre, y el panel lo separa
-- solo en campos al abrirse. El texto anterior nunca se borra ni se modifica.
INSERT IGNORE INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('sobre_nosotros_estructura', '', 'Sobre nosotros: estructura (introducción, subtítulo, tarjetas y cierre) en JSON'),
  ('sobre_nosotros_foto', '', 'Sobre nosotros: foto propia (archivo en uploads/sitio)'),
  ('sobre_nosotros_foto_alt', '', 'Sobre nosotros: texto alternativo de la foto');
