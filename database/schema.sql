-- =====================================================================
-- Cafetería El Charco — Esquema de base de datos
-- Fase 2 del proyecto (ver docs/arquitectura.md §5 y §9)
--
-- Motor: InnoDB, charset utf8mb4 (compatible con MySQL/MariaDB de
-- Hostinger y con XAMPP en local).
--
-- En Hostinger la base de datos se crea desde hPanel (el usuario de
-- hosting compartido normalmente no puede ejecutar CREATE DATABASE);
-- ahí basta con importar desde "USE ..." hacia abajo. En XAMPP local,
-- este script se puede correr completo desde phpMyAdmin.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS elcharco_cafeteria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE elcharco_cafeteria;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. roles — roles del panel administrativo
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(50) NOT NULL UNIQUE,
  descripcion   VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. usuarios — usuarios del panel administrativo (no son clientes)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id              INT UNSIGNED NOT NULL,
  nombre              VARCHAR(100) NOT NULL,
  usuario             VARCHAR(50) NOT NULL UNIQUE,
  password_hash       VARCHAR(255) NOT NULL,
  activo              TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso       DATETIME NULL,
  intentos_fallidos   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta     DATETIME NULL,
  password_actualizado_en DATETIME NULL,
  creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. intentos_login — control de fuerza bruta (no lleva FK: también
--    registra intentos con usuario inexistente)
-- ---------------------------------------------------------------------
CREATE TABLE intentos_login (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_o_ip    VARCHAR(100) NOT NULL,
  exitoso         TINYINT(1) NOT NULL,
  ip              VARCHAR(45) NOT NULL,
  user_agent      VARCHAR(255) NULL,
  creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intentos_usuario_ip (usuario_o_ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. bitacora_auditoria — trazabilidad de cambios en todos los módulos
-- ---------------------------------------------------------------------
CREATE TABLE bitacora_auditoria (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tabla_afectada    VARCHAR(64) NOT NULL,
  registro_id       BIGINT UNSIGNED NOT NULL,
  accion            ENUM('crear','editar','eliminar','login','consumo','canje','ajuste') NOT NULL,
  usuario_id        INT UNSIGNED NULL,
  valores_antes     JSON NULL,
  valores_despues   JSON NULL,
  ip                VARCHAR(45) NULL,
  creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL,
  INDEX idx_auditoria_tabla_registro (tabla_afectada, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. categorias — categorías del menú
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100) NOT NULL,
  slug          VARCHAR(100) NOT NULL UNIQUE,
  descripcion   VARCHAR(255) NULL,
  notas         VARCHAR(255) NULL COMMENT 'Texto libre visible en el sitio público, ej. leches disponibles',
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. productos — ítems del menú
-- ---------------------------------------------------------------------
CREATE TABLE productos (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id          INT UNSIGNED NOT NULL,
  nombre                VARCHAR(120) NOT NULL,
  descripcion           VARCHAR(255) NULL,
  precio                DECIMAL(10,2) NOT NULL,
  precio_alt            DECIMAL(10,2) NULL COMMENT 'Ej. precio "sin café" de un frappé',
  etiqueta_precio_alt   VARCHAR(40) NULL COMMENT 'Ej. "Sin café"',
  imagen                VARCHAR(255) NULL,
  es_destacado          TINYINT(1) NOT NULL DEFAULT 0,
  es_mas_vendido        TINYINT(1) NOT NULL DEFAULT 0,
  activo                TINYINT(1) NOT NULL DEFAULT 1,
  orden                 SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  INDEX idx_productos_categoria (categoria_id),
  INDEX idx_productos_destacado (es_destacado),
  INDEX idx_productos_mas_vendido (es_mas_vendido),
  INDEX idx_productos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. temporadas
-- ---------------------------------------------------------------------
CREATE TABLE temporadas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(100) NOT NULL,
  descripcion   VARCHAR(255) NULL,
  imagen        VARCHAR(255) NULL COMMENT 'Nombre de archivo en public/uploads/temporadas/; NULL = sin foto (§4.19)',
  fecha_inicio  DATE NOT NULL,
  fecha_fin     DATE NOT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. producto_temporadas — relación N:M producto/temporada
-- ---------------------------------------------------------------------
CREATE TABLE producto_temporadas (
  producto_id   INT UNSIGNED NOT NULL,
  temporada_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (producto_id, temporada_id),
  CONSTRAINT fk_pt_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. galeria — fotos del local/ambiente (independientes de productos)
-- ---------------------------------------------------------------------
CREATE TABLE galeria (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imagen        VARCHAR(255) NOT NULL,
  titulo        VARCHAR(120) NULL,
  descripcion   VARCHAR(255) NULL,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9b. portada_imagenes — fotos del fondo automático de la portada
--     (hero). Deliberadamente SEPARADA de `galeria`: son cosas
--     distintas (la galería es contenido para mirar, esto es un fondo
--     decorativo), se administran en pantallas distintas y borrar una
--     foto de un lado nunca afecta al otro. Solo se guarda el nombre de
--     archivo (aleatorio) dentro de public/uploads/portada/.
-- ---------------------------------------------------------------------
CREATE TABLE portada_imagenes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imagen        VARCHAR(255) NOT NULL,
  orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_portada_imagen (imagen),
  INDEX idx_portada_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. promociones
-- ---------------------------------------------------------------------
CREATE TABLE promociones (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                  VARCHAR(120) NOT NULL,
  descripcion             VARCHAR(255) NULL,
  imagen                  VARCHAR(255) NULL COMMENT 'Nombre de archivo en public/uploads/promociones/; NULL = sin foto (§4.18)',
  tipo                    ENUM('descuento_porcentaje','descuento_monto','dos_por_uno','producto_gratis','personalizada') NOT NULL,
  etiqueta_personalizada  VARCHAR(40) NULL COMMENT 'Texto de la etiqueta cuando tipo = personalizada (ej. 3x1)',
  valor                   DECIMAL(6,2) NULL COMMENT 'Porcentaje o monto según el tipo; NULL para 2x1/producto_gratis',
  categoria_id            INT UNSIGNED NULL COMMENT 'Alcance: categoría completa (NULL = no aplica por categoría)',
  producto_id             INT UNSIGNED NULL COMMENT 'Alcance: producto específico (NULL = no aplica por producto)',
  fecha_inicio            DATE NOT NULL,
  fecha_fin               DATE NOT NULL,
  dias_semana             VARCHAR(20) NULL COMMENT 'CSV de días 0=domingo..6=sábado; NULL = todos los días',
  hora_inicio             TIME NULL COMMENT 'NULL = sin restricción de horario',
  hora_fin                TIME NULL,
  combinable_con_lealtad  TINYINT(1) NOT NULL DEFAULT 0,
  prioridad               SMALLINT NOT NULL DEFAULT 0 COMMENT 'Mayor número = mayor prioridad si dos promociones aplican al mismo producto',
  activo                  TINYINT(1) NOT NULL DEFAULT 1,
  creado_en               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_promo_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
  CONSTRAINT fk_promo_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  INDEX idx_promo_vigencia (activo, fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. clientes — clientes del programa de lealtad
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(120) NOT NULL,
  telefono            VARCHAR(20) NOT NULL UNIQUE,
  email               VARCHAR(150) NULL,
  cafes_acumulados    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Caché del saldo SIN convertir en recompensa; la fuente de verdad es lealtad_movimientos',
  notas               VARCHAR(255) NULL,

  -- Código personal (QR) para sumar cafés: 8 caracteres aleatorios, sin
  -- guion (docs/arquitectura.md §4.15; antes era "CL-" + 10, con guion —
  -- por eso VARCHAR(16) alcanza para los dos formatos), permanente.
  -- UNIQUE = dos clientes nunca comparten código. Se asigna la primera vez
  -- que el cliente abre su cuenta (ver Cliente::obtenerCodigoQr).
  codigo_qr           VARCHAR(16) NULL UNIQUE,

  -- Cuenta simple del cliente (teléfono + PIN de 4 dígitos, sin correo)
  -- para que pueda ver su propio progreso. Null = todavía no se ha
  -- creado una cuenta para este cliente (fue registrado por un cajero).
  pin_hash              VARCHAR(255) NULL,
  pin_intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pin_bloqueado_hasta    DATETIME NULL,
  ultimo_acceso_cliente  DATETIME NULL,

  creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. lealtad_movimientos — libro mayor de TODO cambio de saldo.
--     Nunca se edita ni se borra una fila: una corrección se registra
--     como un movimiento nuevo (ajuste_positivo/negativo) o marcando
--     estado='anulado', jamás modificando cantidad/tipo de una fila ya
--     escrita.
-- ---------------------------------------------------------------------
CREATE TABLE lealtad_movimientos (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id            INT UNSIGNED NOT NULL,
  tipo                  ENUM(
                          'consumo',
                          'ajuste_positivo',
                          'ajuste_negativo',
                          'recompensa_generada',
                          'recompensa_canjeada',
                          'recompensa_cancelada'
                        ) NOT NULL,
  cantidad              INT NOT NULL COMMENT 'Cambio en el saldo sin convertir: +N consumo/ajuste_positivo/cancelada, -umbral recompensa_generada, -N ajuste_negativo, 0 recompensa_canjeada',
  motivo                VARCHAR(255) NULL COMMENT 'Obligatorio a nivel de aplicación para los ajustes manuales',
  referencia            VARCHAR(60) NULL COMMENT 'Ej. el código de la recompensa relacionada',
  estado                ENUM('activo','anulado') NOT NULL DEFAULT 'activo',
  usuario_id            INT UNSIGNED NOT NULL COMMENT 'Quién registró el movimiento',
  creado_en             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lealtad_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_lealtad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_lealtad_cliente (cliente_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12b. recompensas — una fila por cada café gratis GENERADO al cruzar
--      el umbral configurado. Se genera automáticamente en el servidor
--      (nunca la elige el cajero ni el cliente); el producto se elige
--      recién al canjear. codigo es lo que el cliente muestra y el
--      cajero valida (docs/arquitectura.md — "canje por código").
-- ---------------------------------------------------------------------
CREATE TABLE recompensas (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id              INT UNSIGNED NOT NULL,
  codigo                  VARCHAR(12) NOT NULL UNIQUE,
  estado                  ENUM('disponible','canjeada','cancelada','vencida') NOT NULL DEFAULT 'disponible',
  movimiento_generador_id BIGINT UNSIGNED NOT NULL COMMENT 'Fila en lealtad_movimientos (tipo=recompensa_generada) que la creó',
  fecha_generada          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_vencimiento       DATETIME NULL COMMENT 'Calculada al generar según configuracion_sistema; NULL = no vence',

  producto_canjeado_id    INT UNSIGNED NULL COMMENT 'Se define hasta el momento del canje',
  usuario_canjeo_id       INT UNSIGNED NULL,
  fecha_canjeada          DATETIME NULL,
  movimiento_canje_id     BIGINT UNSIGNED NULL COMMENT 'Fila en lealtad_movimientos (tipo=recompensa_canjeada)',

  usuario_cancelacion_id  INT UNSIGNED NULL,
  fecha_cancelacion       DATETIME NULL,
  motivo_cancelacion      VARCHAR(255) NULL,
  movimiento_cancelacion_id BIGINT UNSIGNED NULL COMMENT 'Fila en lealtad_movimientos (tipo=recompensa_cancelada)',

  CONSTRAINT fk_recompensa_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_recompensa_mov_gen FOREIGN KEY (movimiento_generador_id) REFERENCES lealtad_movimientos(id),
  CONSTRAINT fk_recompensa_mov_canje FOREIGN KEY (movimiento_canje_id) REFERENCES lealtad_movimientos(id),
  CONSTRAINT fk_recompensa_mov_cancel FOREIGN KEY (movimiento_cancelacion_id) REFERENCES lealtad_movimientos(id),
  CONSTRAINT fk_recompensa_producto FOREIGN KEY (producto_canjeado_id) REFERENCES productos(id),
  CONSTRAINT fk_recompensa_usuario_canjeo FOREIGN KEY (usuario_canjeo_id) REFERENCES usuarios(id),
  CONSTRAINT fk_recompensa_usuario_cancel FOREIGN KEY (usuario_cancelacion_id) REFERENCES usuarios(id),
  INDEX idx_recompensa_cliente (cliente_id, estado),

  -- Garantías a nivel de base de datos (la aplicación también las
  -- cumple, pero un bug o un UPDATE manual no debe poder romperlas):
  --  * codigo UNIQUE (arriba): dos recompensas nunca comparten código.
  --    La colación _ci lo hace insensible a mayúsculas: 'abcd-efgh' y
  --    'ABCD-EFGH' cuentan como el mismo código.
  --  * un movimiento generador produce a lo sumo UNA recompensa (no hay
  --    doble generación) y un movimiento de canje pertenece a UNA sola.
  UNIQUE KEY uq_recompensa_mov_gen (movimiento_generador_id),
  UNIQUE KEY uq_recompensa_mov_canje (movimiento_canje_id),
  --  * un código nunca puede quedar demasiado corto/vacío.
  CONSTRAINT chk_recompensa_codigo_longitud CHECK (CHAR_LENGTH(codigo) >= 8),
  --  * "usada" siempre lleva fecha, producto entregado, quién la canjeó
  --    y su movimiento: no existe un canje a medias.
  CONSTRAINT chk_recompensa_canje_completo CHECK (
    estado <> 'canjeada'
    OR (fecha_canjeada IS NOT NULL AND producto_canjeado_id IS NOT NULL
        AND usuario_canjeo_id IS NOT NULL AND movimiento_canje_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12c. cupones_promocion — cupón de una promoción marcada "combinable con
--      lealtad" (docs/arquitectura.md §4.20). Concepto separado de
--      `recompensas` (café gratis por acumular): aquí no hay cafés
--      involucrados, el cliente reclama el cupón desde su cuenta y el
--      cajero lo canjea por código/QR igual que una recompensa, pero el
--      "premio" (descuento, 2x1, etc.) es el que ya define la promoción.
-- ---------------------------------------------------------------------
CREATE TABLE cupones_promocion (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                  VARCHAR(12) NOT NULL UNIQUE,
  cliente_id              INT UNSIGNED NOT NULL,
  promocion_id            INT UNSIGNED NOT NULL,
  estado                  ENUM('disponible','canjeado','cancelado','vencido') NOT NULL DEFAULT 'disponible',
  fecha_reclamo           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  usuario_canjeo_id       INT UNSIGNED NULL,
  fecha_canje             DATETIME NULL,

  usuario_cancelacion_id  INT UNSIGNED NULL,
  fecha_cancelacion       DATETIME NULL,
  motivo_cancelacion      VARCHAR(255) NULL,

  CONSTRAINT fk_cupon_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_cupon_promocion FOREIGN KEY (promocion_id) REFERENCES promociones(id),
  CONSTRAINT fk_cupon_usuario_canjeo FOREIGN KEY (usuario_canjeo_id) REFERENCES usuarios(id),
  CONSTRAINT fk_cupon_usuario_cancelacion FOREIGN KEY (usuario_cancelacion_id) REFERENCES usuarios(id),
  INDEX idx_cupon_cliente (cliente_id, estado),

  -- Un cliente reclama el cupón de una misma promoción una sola vez
  -- (RN nueva de §4.20) — no solo lo valida la aplicación, lo garantiza
  -- este UNIQUE: un segundo intento choca aquí aunque hubiera una
  -- condición de carrera.
  UNIQUE KEY uq_cupon_cliente_promocion (cliente_id, promocion_id),
  CONSTRAINT chk_cupon_codigo_longitud CHECK (CHAR_LENGTH(codigo) >= 8),
  CONSTRAINT chk_cupon_canje_completo CHECK (
    estado <> 'canjeado' OR (fecha_canje IS NOT NULL AND usuario_canjeo_id IS NOT NULL)
  ),
  CONSTRAINT chk_cupon_cancelado_completo CHECK (
    estado <> 'cancelado' OR (fecha_cancelacion IS NOT NULL AND usuario_cancelacion_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. configuracion_sistema — clave/valor para datos administrables
--     del sitio (redes sociales, WhatsApp, mapa, textos, umbral de
--     lealtad). La paleta de colores NO vive aquí: es un token de
--     diseño (CSS), no un dato administrable desde el panel.
-- ---------------------------------------------------------------------
CREATE TABLE configuracion_sistema (
  clave           VARCHAR(60) NOT NULL PRIMARY KEY,
  valor           TEXT NULL,
  descripcion     VARCHAR(255) NULL,
  actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. horarios_atencion — horario semanal del negocio
-- ---------------------------------------------------------------------
-- Horario partido: un segundo turno opcional (ej. 9:00-14:00 y 16:00-21:00).
-- hora_apertura/hora_cierre son siempre el turno 1; hora_apertura_2/
-- hora_cierre_2 quedan NULL cuando el día no tiene pausa (docs/arquitectura.md §4.13).
CREATE TABLE horarios_atencion (
  id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dia_semana      TINYINT UNSIGNED NOT NULL UNIQUE COMMENT '0=domingo .. 6=sábado',
  hora_apertura   TIME NULL,
  hora_cierre     TIME NULL,
  hora_apertura_2 TIME NULL COMMENT 'Segundo turno (horario partido), opcional',
  hora_cierre_2   TIME NULL,
  cerrado         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = cerrado o aún sin configurar por el negocio',

  -- El turno 2 es "todo o nada": no puede quedar una hora puesta y la otra vacía.
  CONSTRAINT chk_horario_turno2_completo CHECK ((hora_apertura_2 IS NULL) = (hora_cierre_2 IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATOS SEMILLA
-- Solo se insertan datos reales y confirmados (docs/negocio.md) o
-- estructura vacía a completar desde el panel. No se inventan datos
-- de ejemplo (productos ficticios, horarios, dirección, calificación
-- de Google, etc.).
-- =====================================================================

-- Roles del panel (ver docs/arquitectura.md §3.2 para el detalle de permisos)
INSERT INTO roles (nombre, descripcion) VALUES
  ('Administrador', 'Acceso total a todos los módulos del panel'),
  ('Cajero', 'Pedidos, Clientes, Lealtad, Recompensas y Cupones; Reportes limitados'),
  ('Repartidor', 'Solo ve los pedidos confirmados por entregar y los marca como entregados');

-- Permisos por rol y módulo (se configuran en «Roles y permisos»). El Administrador siempre puede todo.
-- Los valores iniciales reproducen lo que Cajero y Repartidor ya podían hacer (migración 016).
CREATE TABLE rol_permisos (
  rol_id  INT UNSIGNED NOT NULL,
  modulo  VARCHAR(30)  NOT NULL,
  nivel   ENUM('ninguno','ver','editar') NOT NULL DEFAULT 'ninguno',
  PRIMARY KEY (rol_id, modulo),
  CONSTRAINT fk_rol_permisos_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rol_permisos (rol_id, modulo, nivel) VALUES
  (2, 'pedidos', 'editar'), (2, 'reportes', 'ver'), (2, 'clientes', 'editar'), (2, 'lealtad', 'editar'),
  (2, 'canjear', 'editar'), (2, 'recompensas', 'ver'), (2, 'cupones', 'ver'),
  (3, 'pedidos', 'editar');

-- Usuario administrador inicial.
-- Contraseña generada aleatoriamente y NO se guarda en este archivo ni
-- en el repositorio: se entrega una única vez fuera de git y debe
-- cambiarse en el primer inicio de sesión (password_actualizado_en
-- queda en NULL a propósito para poder forzar ese cambio desde el Core).
INSERT INTO usuarios (rol_id, nombre, usuario, password_hash, activo) VALUES
  (1, 'Robin', 'admin', '$2y$10$0MOeOMM/R3b/tSCaDaj9Iu9YeT4KJK5qXGGAngOZN9pCwTlcvMlv6', 1);

-- Categorías del menú real (docs/negocio.md)
INSERT INTO categorias (id, nombre, slug, descripcion, notas, orden) VALUES
  (1, 'Frappés / Batidos', 'frappes-batidos', 'Bebida a base de hielo triturado, leche y endulzantes', 'Con café Q30 · Sin café Q25', 1),
  (2, 'Sodas italianas', 'sodas-italianas', 'Soda neutra con syrops de sabores y hielo', 'Q25 cada una', 2),
  (3, 'Todo Frío', 'todo-frio', 'Otras bebidas frías', NULL, 3),
  (4, 'Smoothies', 'smoothies', 'Puré de fruta triturada con leche o agua y hielo', 'Q25 cada uno', 4),
  (5, 'Lassie', 'lassie', 'Puré de fruta triturada con yogurt griego, jugo de manzana y hielo', 'Q25 cada uno', 5),
  (6, 'Sándwiches', 'sandwiches', NULL, NULL, 6),
  (7, 'Café', 'cafe', NULL, 'Leches disponibles: entera +Q0, deslactosada +Q2, almendras +Q3', 7),
  (8, 'Bebidas con café', 'bebidas-con-cafe', 'Bebidas saborizadas a base de lattes o capuchinos', NULL, 8),
  (9, 'Bebidas sin café', 'bebidas-sin-cafe', NULL, NULL, 9);

-- Productos del menú real (docs/negocio.md). es_destacado y
-- es_mas_vendido quedan en 0: esa clasificación la define el negocio
-- desde el panel, no se inventa aquí. imagen queda en NULL hasta que
-- se suban fotos reales.

-- Frappés / Batidos
INSERT INTO productos (categoria_id, nombre, precio, precio_alt, etiqueta_precio_alt, orden) VALUES
  (1, 'Mantequilla de maní', 30.00, 25.00, 'Sin café', 1),
  (1, 'Galleta oreo', 30.00, 25.00, 'Sin café', 2),
  (1, 'Caramelo', 30.00, 25.00, 'Sin café', 3),
  (1, 'Chocolate', 30.00, 25.00, 'Sin café', 4);

-- Sodas italianas
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (2, 'Manzana verde', 'Fría', 25.00, 1),
  (2, 'Blueberry', 'Fría', 25.00, 2),
  (2, 'Fresa', 'Fría', 25.00, 3),
  (2, 'Lavanda', 'Fría', 25.00, 4),
  (2, 'Mango', 'Fría', 25.00, 5),
  (2, 'Pitaya', 'Fría', 25.00, 6),
  (2, 'Naranja', 'Fría', 25.00, 7);

-- Todo Frío
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (3, 'Leche fría', 'A base de leche y vainilla', 18.00, 1),
  (3, 'Milkshake', 'Bebida cremosa a base de leche y helado de vainilla', 30.00, 2);

-- Smoothies
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (4, 'Piña colada', 'Fría', 25.00, 1),
  (4, 'Fresa', 'Fría', 25.00, 2),
  (4, 'Frambuesa', 'Fría', 25.00, 3),
  (4, 'Mango', 'Fría', 25.00, 4),
  (4, 'Maracuyá', 'Fría', 25.00, 5),
  (4, 'Kiwi', 'Fría', 25.00, 6);

-- Lassie
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (5, 'Fresa', 'Fría', 25.00, 1),
  (5, 'Frambuesa', 'Fría', 25.00, 2),
  (5, 'Mango', 'Fría', 25.00, 3),
  (5, 'Maracuyá', 'Fría', 25.00, 4),
  (5, 'Kiwi', 'Fría', 25.00, 5),
  (5, 'Piña colada', 'Fría', 25.00, 6);

-- Sándwiches
INSERT INTO productos (categoria_id, nombre, precio, orden) VALUES
  (6, 'Jamón y queso', 20.00, 1),
  (6, 'Pollo', 25.00, 2),
  (6, 'Jamón, queso y pollo', 25.00, 3);

-- Café
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (7, 'Espresso (doble)', 'Caliente. 2oz concentradas, grano Caturra de Huehuetenango, tueste medio', 15.00, 1),
  (7, 'Americano', 'Caliente o frío', 15.00, 2),
  (7, 'Latte suave', 'Caliente o frío', 18.00, 3),
  (7, 'Cappuccino tradicional', 'Caliente', 18.00, 4);

-- Bebidas con café
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (8, 'Moka', 'Caliente o frío', 20.00, 1),
  (8, 'Vainilla Latte', 'Caliente o frío', 20.00, 2),
  (8, 'Caramel Macchiato', 'Caliente o frío', 20.00, 3),
  (8, 'Coconut Latte', 'Caliente o frío', 20.00, 4),
  (8, 'Mint Latte', 'Solo caliente', 20.00, 5),
  (8, 'Pistachio Latte', 'Solo caliente', 20.00, 6),
  (8, 'Affogato (1 bola de helado)', 'Frío. Helado de vainilla + 1 shot doble de espresso', 20.00, 7),
  (8, 'Affogato (2 bolas de helado)', 'Frío', 25.00, 8),
  (8, 'Carajillo (Ron Botrán)', 'Frío. Espresso + 1oz de licor + toque de vainilla', 35.00, 9),
  (8, 'Carajillo (Ron Zacapa)', 'Frío', 50.00, 10);

-- Bebidas sin café
INSERT INTO productos (categoria_id, nombre, descripcion, precio, orden) VALUES
  (9, 'Chai Latte', 'Caliente o frío', 20.00, 1),
  (9, 'Taro Latte', 'Caliente o frío', 20.00, 2),
  (9, 'Chocolate', 'Caliente', 20.00, 3),
  (9, 'Chocolate Blanco', 'Caliente', 20.00, 4);

-- Configuración del sitio: solo se cargan los datos reales confirmados
-- en docs/negocio.md. Dirección, mapa y calificación de Google quedan
-- vacíos a propósito porque no se han proporcionado — se completan
-- desde el panel cuando el negocio los tenga listos.
INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('nombre_sitio', 'Cafetería El Charco', 'Nombre del negocio mostrado en el sitio y el panel'),
  ('logo', '', 'Ruta del logo — pendiente de que el negocio proporcione el archivo'),
  ('direccion_texto', '', 'Dirección en texto plano — pendiente de proporcionar'),
  ('whatsapp_numero', '+50233577968', 'Número de WhatsApp para el botón de contacto'),
  ('boton_whatsapp_texto', '', 'Texto del botón de WhatsApp de la portada — vacío usa "Ordena ahora" por defecto'),
  ('whatsapp_flotante_texto', '', 'Texto de la burbuja del botón flotante de WhatsApp — vacío usa una frase por defecto'),
  ('instagram_usuario', '@el_charco_cafeteria', 'Usuario de Instagram'),
  ('tiktok_usuario', '', 'Usuario o enlace de TikTok — pendiente de proporcionar'),
  ('facebook_nombre', 'Cafetería El Charco', 'Nombre de la página de Facebook'),
  ('umbral_lealtad', '10', 'Cantidad de cafés comprados para ganar 1 café gratis'),
  ('google_maps_embed', '', 'Enlace para compartir de Google Maps (botón "Cómo llegar"); el mapa visual se genera solo desde direccion_texto'),
  ('google_calificacion', '', 'Calificación de Google (ej. 4.8) — se actualiza manualmente desde el panel'),
  ('google_calificacion_url', '', 'Enlace a la ficha de Google del negocio'),
  ('sobre_nosotros_texto', '', 'Texto de la sección "Sobre nosotros" — pendiente de redactar'),
  ('facebook_url', '', 'Enlace a la página de Facebook — pendiente de proporcionar'),
  ('frase_hero', '', 'Frase corta de bienvenida en la portada — pendiente de redactar/aprobar'),
  ('descripcion_corta', '', 'Descripción breve de la cafetería en la portada — pendiente de redactar/aprobar'),
  ('lealtad_dias_vencimiento_recompensa', '90', 'Días que dura disponible una recompensa generada antes de vencer. Vacío o 0 = no vence.'),
  ('carrito_aviso_ubicacion', 'Después de enviar tu pedido, mándanos tu ubicación por WhatsApp para poder enviarlo.', 'Aviso del carrito: pedir la ubicación por WhatsApp después de enviar el pedido'),
  ('sobre_nosotros_estructura', '', 'Sobre nosotros: estructura (introducción, subtítulo, tarjetas y cierre) en JSON'),
  ('sobre_nosotros_foto', '', 'Sobre nosotros: foto propia (archivo en uploads/sitio)'),
  ('sobre_nosotros_foto_alt', '', 'Sobre nosotros: texto alternativo de la foto'),
  ('sobre_nosotros_foto_pie', '', 'Sobre nosotros: pie de foto (texto visible debajo de la foto)'),
  ('horario_agrupar', '1', 'Horario del sitio: 1 = juntar los días seguidos con el mismo horario (Lunes a Viernes), 0 = un renglón por día'),
  ('orden_temporadas', '[]', 'Orden de las temporadas en el sitio: lista JSON de ids (se cambia con ▲▼ en Temporadas)');

-- Horario semanal: se crean las 7 filas marcadas como "cerrado/sin
-- configurar" (no se inventan horarios). El negocio los completa desde
-- el panel en la Fase 6.
INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES
  ('pedidos_dias', '0,1,2,3,4,5,6', 'Días en que se reciben pedidos por la página (0=domingo .. 6=sábado, separados por coma)'),
  ('pedidos_pausados', '0', 'Pausa manual de los pedidos por la página: 1 = pausados, 0 = activos'),
  ('pedidos_mensaje', '', 'Mensaje que ve el cliente cuando los pedidos están pausados (vacío = mensaje por defecto)');

INSERT INTO horarios_atencion (dia_semana, cerrado) VALUES
  (0, 1), (1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1);


-- ---------------------------------------------------------------------
-- Pedidos del carrito (checkout por WhatsApp) — migración 013
-- ---------------------------------------------------------------------
-- Dinero en CENTAVOS ENTEROS. Los pedidos no se borran ni se editan en montos/ítems (disparadores).
CREATE TABLE IF NOT EXISTS pedidos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo              CHAR(6) NOT NULL COMMENT 'Código corto que ve el cliente; sin O/0/I/1',
  token_idempotencia  VARCHAR(64) NOT NULL COMMENT 'Lo genera el navegador: el mismo token no crea dos pedidos',
  huella_carrito      CHAR(64) NOT NULL COMMENT 'SHA-256 del contenido: detecta un token reutilizado con otro carrito',
  cliente_nombre      VARCHAR(60) NULL,
  cliente_telefono    VARCHAR(20) NULL,
  cliente_id          INT UNSIGNED NULL,
  direccion           VARCHAR(200) NULL,
  forma_pago          ENUM('contra_entrega','transferencia','tarjeta_fisica') NOT NULL,
  subtotal_centavos   BIGINT UNSIGNED NOT NULL,
  descuento_centavos  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_centavos      BIGINT UNSIGNED NOT NULL,
  estado              ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NOT NULL DEFAULT 'pendiente',
  ip                  VARCHAR(45) NULL,
  creado_en           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entregado_en        DATETIME NULL,
  actualizado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actualizado_por     INT UNSIGNED NULL,
  nota                VARCHAR(300) NULL,
  UNIQUE KEY uq_pedidos_codigo (codigo),
  UNIQUE KEY uq_pedidos_token (token_idempotencia),
  KEY idx_pedidos_creado (creado_en),
  KEY idx_pedidos_entregado (entregado_en),
  KEY idx_pedidos_estado (estado),
  KEY idx_pedidos_cliente (cliente_id),
  KEY idx_pedidos_ip (ip, creado_en),
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT chk_pedidos_total CHECK (total_centavos + descuento_centavos = subtotal_centavos),
  CONSTRAINT chk_pedidos_maximo CHECK (total_centavos <= 500000),
  CONSTRAINT chk_pedidos_entregado CHECK (estado NOT IN ('entregado') OR entregado_en IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_items (
  id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id                 BIGINT UNSIGNED NOT NULL,
  producto_id               INT UNSIGNED NULL COMMENT 'NULL si el producto se borró; el nombre queda en nombre_snapshot',
  clave                     VARCHAR(20) NOT NULL COMMENT 'Clave del carrito: 12, 12-alt, 12~c, 12-alt~f',
  nombre_snapshot           VARCHAR(200) NOT NULL,
  categoria_snapshot        VARCHAR(100) NOT NULL,
  tipo_snapshot             VARCHAR(60) NOT NULL COMMENT 'Versión y temperatura: Normal, Batido, Normal · Caliente…',
  precio_unitario_centavos  BIGINT UNSIGNED NOT NULL,
  cantidad                  SMALLINT UNSIGNED NOT NULL,
  subtotal_centavos         BIGINT UNSIGNED NOT NULL,
  es_cortesia               TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Sale gratis (recompensa/cupón): precio 0 y no cuenta como ingreso',
  KEY idx_items_pedido (pedido_id),
  KEY idx_items_producto (producto_id),
  CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_items_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
  CONSTRAINT chk_items_cantidad CHECK (cantidad BETWEEN 1 AND 20),
  CONSTRAINT chk_items_subtotal CHECK (subtotal_centavos = precio_unitario_centavos * cantidad),
  CONSTRAINT chk_items_cortesia CHECK (es_cortesia = 0 OR precio_unitario_centavos = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_historial (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id        BIGINT UNSIGNED NOT NULL,
  estado_anterior  ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NULL,
  estado_nuevo     ENUM('pendiente','enviado','confirmado','entregado','cancelado','anulado') NOT NULL,
  usuario_id       INT UNSIGNED NULL COMMENT 'NULL = el propio cliente al enviarlo, o usuario eliminado después',
  motivo           VARCHAR(255) NULL,
  fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_historial_pedido (pedido_id, fecha),
  CONSTRAINT fk_historial_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

-- Los pedidos no se borran (ni desde el panel ni desde código).
CREATE TRIGGER trg_pedidos_no_borrar BEFORE DELETE ON pedidos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los pedidos no se pueden eliminar';
END$$

-- Tras crearse, no cambian montos, ítems de identidad, código ni forma de pago (solo estado, entrega y nota).
CREATE TRIGGER trg_pedidos_no_editar_montos BEFORE UPDATE ON pedidos FOR EACH ROW
BEGIN
  IF NOT (NEW.codigo <=> OLD.codigo AND NEW.token_idempotencia <=> OLD.token_idempotencia
          AND NEW.huella_carrito <=> OLD.huella_carrito AND NEW.subtotal_centavos <=> OLD.subtotal_centavos
          AND NEW.descuento_centavos <=> OLD.descuento_centavos AND NEW.total_centavos <=> OLD.total_centavos
          AND NEW.forma_pago <=> OLD.forma_pago AND NEW.creado_en <=> OLD.creado_en) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los montos y datos de un pedido no se pueden modificar';
  END IF;
END$$

CREATE TRIGGER trg_items_no_borrar BEFORE DELETE ON pedido_items FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ítems de un pedido no se pueden eliminar';
END$$

-- producto_id sí puede cambiar (pasa a NULL solo, por la llave foránea, si se borra el producto).
CREATE TRIGGER trg_items_no_editar BEFORE UPDATE ON pedido_items FOR EACH ROW
BEGIN
  IF NOT (NEW.pedido_id <=> OLD.pedido_id AND NEW.clave <=> OLD.clave AND NEW.nombre_snapshot <=> OLD.nombre_snapshot
          AND NEW.categoria_snapshot <=> OLD.categoria_snapshot AND NEW.tipo_snapshot <=> OLD.tipo_snapshot
          AND NEW.precio_unitario_centavos <=> OLD.precio_unitario_centavos AND NEW.cantidad <=> OLD.cantidad
          AND NEW.subtotal_centavos <=> OLD.subtotal_centavos AND NEW.es_cortesia <=> OLD.es_cortesia) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los ítems de un pedido no se pueden modificar';
  END IF;
END$$

DELIMITER ;
