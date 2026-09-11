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
  accion            ENUM('crear','editar','eliminar') NOT NULL,
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
  precio                DECIMAL(6,2) NOT NULL,
  precio_alt            DECIMAL(6,2) NULL COMMENT 'Ej. precio "sin café" de un frappé',
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
-- 10. promociones
-- ---------------------------------------------------------------------
CREATE TABLE promociones (
  id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                  VARCHAR(120) NOT NULL,
  descripcion             VARCHAR(255) NULL,
  tipo                    ENUM('descuento_porcentaje','descuento_monto','dos_por_uno','producto_gratis') NOT NULL,
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
  cafes_acumulados    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Caché del saldo; la fuente de verdad es lealtad_movimientos',
  notas               VARCHAR(255) NULL,
  creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. lealtad_movimientos — libro mayor de compras/canjes/ajustes
-- ---------------------------------------------------------------------
CREATE TABLE lealtad_movimientos (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id            INT UNSIGNED NOT NULL,
  tipo                  ENUM('compra','canje','ajuste') NOT NULL,
  cantidad              SMALLINT NOT NULL COMMENT 'Positivo en compra/ajuste, negativo en canje (ej. -10)',
  producto_canjeado_id  INT UNSIGNED NULL COMMENT 'Solo aplica cuando tipo=canje: qué café se entregó gratis',
  usuario_id            INT UNSIGNED NOT NULL COMMENT 'Quién registró el movimiento',
  nota                  VARCHAR(255) NULL,
  creado_en             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lealtad_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_lealtad_producto FOREIGN KEY (producto_canjeado_id) REFERENCES productos(id),
  CONSTRAINT fk_lealtad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_lealtad_cliente (cliente_id, creado_en)
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
CREATE TABLE horarios_atencion (
  id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dia_semana      TINYINT UNSIGNED NOT NULL UNIQUE COMMENT '0=domingo .. 6=sábado',
  hora_apertura   TIME NULL,
  hora_cierre     TIME NULL,
  cerrado         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = cerrado o aún sin configurar por el negocio'
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
  ('Cajero', 'Solo Clientes, Lealtad y Recompensas');

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
  ('instagram_usuario', '@el_charco_cafeteria', 'Usuario de Instagram/TikTok'),
  ('facebook_nombre', 'Cafetería El Charco', 'Nombre de la página de Facebook'),
  ('umbral_lealtad', '10', 'Cantidad de cafés comprados para ganar 1 café gratis'),
  ('google_maps_embed', '', 'Código embed de Google Maps — pendiente de proporcionar'),
  ('google_calificacion', '', 'Calificación de Google (ej. 4.8) — se actualiza manualmente desde el panel'),
  ('google_calificacion_url', '', 'Enlace a la ficha de Google del negocio'),
  ('sobre_nosotros_texto', '', 'Texto de la sección "Sobre nosotros" — pendiente de redactar');

-- Horario semanal: se crean las 7 filas marcadas como "cerrado/sin
-- configurar" (no se inventan horarios). El negocio los completa desde
-- el panel en la Fase 6.
INSERT INTO horarios_atencion (dia_semana, cerrado) VALUES
  (0, 1), (1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1);
