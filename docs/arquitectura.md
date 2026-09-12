# Arquitectura del sistema — Cafetería El Charco

Este documento es el análisis técnico previo a escribir código. Cubre
alcance, decisión tecnológica, módulos, flujos, modelo de datos, estructura
de carpetas, seguridad, riesgos y plan por fases. Se basa exclusivamente en
los datos reales del negocio documentados en [negocio.md](negocio.md).

Estado: **Fase 1 aprobada** (ver decisiones confirmadas en §10). No se ha
escrito código de aplicación todavía — eso corresponde a la Fase 2 en
adelante.

---

## 1. Alcance y delimitación

El sistema tiene dos partes que comparten una sola base de datos:

1. **Sitio público**: informativo y de marketing. Muestra el menú, fotos,
   ubicación, redes, horarios, promociones y explica el programa de lealtad.
2. **Panel administrativo** ("tipo WordPress" en el sentido de *experiencia
   de uso*: un no-programador puede administrar el contenido —no se usará el
   software WordPress real, para evitar duplicar el negocio en dos sistemas
   y el riesgo de seguridad de sus plugins).

**Importante — lo que el sistema NO es en esta versión:**
- No es una tienda en línea. No hay carrito, checkout ni pasarela de pago.
  Los clientes compran físicamente en la cafetería.
- La lealtad y las promociones son herramientas que **el personal opera
  desde el panel** (equivalente digital de la tarjetita de sellos), no un
  proceso que el cliente ejecuta solo desde el sitio público.

Esta delimitación es la que mantiene el proyecto simple y defendible: sin
ella, "clientes + lealtad + recompensas" se malinterpreta fácilmente como
e-commerce, que es un problema mucho más grande (inventario, pagos, envíos)
y no es lo que el negocio necesita hoy.

---

## 2. Decisión tecnológica

### 2.1 Restricción real: hosting compartido de Hostinger

Los planes compartidos de Hostinger:
- Dan PHP 8.x y MySQL/MariaDB administrados desde hPanel — sin fricción.
- El acceso SSH y a Composer **no está garantizado en todos los planes**
  (depende del plan contratado; los planes de entrada pueden no traerlo).
- Permiten fijar el *document root* de un dominio a una subcarpeta, y
  permiten tener carpetas fuera de `public_html` en el mismo espacio — eso
  es clave para poder aislar el código de la aplicación del código servido
  públicamente.
- No hay proceso Node/build en el servidor: cualquier paso de compilación
  (Vite, npm) debe hacerse en la máquina local y subir el resultado ya
  construido.

### 2.2 ¿Laravel?

Análisis pedido explícitamente. Laravel es viable técnicamente en Hostinger,
pero para este proyecto específico tiene más costo que beneficio:

| Aspecto | Laravel | Costo/riesgo en Hostinger compartido |
|---|---|---|
| Instalación | requiere Composer (SSH o Composer vía hPanel, no en todos los planes) | riesgo de bloqueo si el plan no lo trae |
| Document root | debe apuntar a `public/` | fácil si el plan lo permite; si no, requiere workaround |
| `storage/` y caché | requiere permisos de escritura específicos y `php artisan` para migraciones/caché | artisan necesita SSH; sin SSH, migraciones manuales |
| Actualizaciones de versión | Laravel exige mantenerse al día por seguridad | carga de mantenimiento extra para un solo desarrollador |
| Curva/beneficio | ORM, colas, eventos, etc. | el proyecto no necesita nada de eso: sin carrito, sin colas, sin APIs externas complejas |

**Conclusión:** Laravel resuelve problemas que este proyecto no tiene, y
añade dependencia de infraestructura (Composer/SSH) que no está garantizada
en el plan de hosting. Para un primer proyecto comercial donde **tú** serás
quien mantenga el sistema, cada pieza de infraestructura extra es riesgo.

### 2.3 Recomendación: MVC ligero en PHP plano, hecho a medida

- **PHP 8 + PDO** (`ATTR_EMULATE_PREPARES => false`, como ya haces en PG2).
- **Arquitectura MVC propia**, sin framework: un *front controller* + router
  simple, Controladores, Modelos (PDO), Vistas (plantillas PHP), y una capa
  de **Servicios** para reglas de negocio (`LealtadService`,
  `PromocionService`, `AuditoriaService`) — así la lógica de negocio no vive
  ni en el controlador ni en el modelo, y es fácil de probar y de explicar.
- **Autoload manual** (PSR-4 simple con `spl_autoload_register`), sin
  Composer obligatorio. Si más adelante se necesita una librería puntual
  (ej. PHPMailer), se puede vendorizar manualmente sin depender de Composer
  en el servidor.
- **Bootstrap 5** para grid/formularios/componentes del panel y del sitio
  público — sin jQuery (Bootstrap 5 no lo requiere), más liviano. Se
  descarga localmente a `assets/vendor/` en vez de usar CDN, para que el
  sitio no dependa de un tercero externo y cargue rápido.
- **Sin ORM.** Con el volumen de datos de una cafetería (decenas de
  productos, cientos de clientes) un ORM no aporta valor; los modelos con
  PDO y consultas preparadas son más simples de razonar y de defender.

Esto es exactamente "arquitectura MVC o equivalente" pedida, cumple
`estabilidad + seguridad + mantenibilidad + hosting compartido`, y dado que
las capas quedan separadas (Controladores/Modelos/Servicios/Vistas), si el
negocio creciera mucho en el futuro (ventas en línea, múltiples sucursales)
la migración a un framework más adelante es un *refactor*, no una reescritura
completa.

### 2.4 Estructura del código: `public/` como raíz web

Recomiendo aislar el código de aplicación de lo que el navegador puede
pedir directamente:

- Solo la carpeta `public/` se expone como *document root* (tanto en
  Hostinger como, idealmente, en un Virtual Host local de XAMPP apuntando a
  `elcharco/public`).
- `app/`, `config/`, `database/` quedan **fuera** de lo que Apache sirve, así
  que aunque alguien adivine una ruta, no hay manera de pedir
  `config/database.php` por URL.

**Alternativa más simple** (si no quieres configurar un Virtual Host local
todavía): mantener todo en la raíz de `elcharco/` como hace PG2 hoy, y
proteger `app/` y `config/` con reglas `.htaccess` (`Deny from all`). Es más
rápido de tener funcionando pero el aislamiento depende de que el
`.htaccess` esté bien configurado y de que Apache lo respete (Hostinger sí
lo respeta por defecto).

**Confirmado:** `public/` como raíz web (ver §10). En la Fase 3 se probó
primero con un *alias* de Apache (`http://localhost/elcharco`) y se
descartó: la app genera enlaces/redirecciones como rutas absolutas de la
raíz del dominio, que bajo un alias de subcarpeta apuntan mal. Se usa en
su lugar un **Virtual Host con dominio propio**
(`http://elcharco.local`, ver README "Cómo probar en local"), que
reproduce fielmente cómo se verá en producción (dominio propio en
Hostinger = `public/`).

---

## 3. Módulos

### 3.1 Sitio público

| Sección | Contenido | Tabla(s) de origen |
|---|---|---|
| Hero / portada | fotos, frase corta, CTA a menú/ubicación | `configuracion_sistema` |
| Sobre nosotros | texto + fotos | `configuracion_sistema` |
| Más vendidos | productos marcados como tal | `productos` (`es_mas_vendido`) |
| Destacados | productos marcados como tal | `productos` (`es_destacado`) |
| Temporada | productos ligados a una temporada activa | `temporadas`, `producto_temporadas` |
| Menú por categorías | todo el menú con fotos y precios | `categorias`, `productos` |
| Galería | fotos del local/ambiente | `galeria` |
| Ubicación | mapa embebido + dirección | `configuracion_sistema` |
| Calificación de Google | insignia/enlace a la reseña de Google | `configuracion_sistema` |
| WhatsApp | botón flotante y enlaces `wa.me` | `configuracion_sistema` |
| Redes sociales | Instagram/TikTok/Facebook | `configuracion_sistema` |
| Horarios | tabla de horario semanal + indicador "abierto ahora" | `horarios_atencion` |
| Lealtad | explicación pública de la regla (10 cafés = 1 gratis) | texto fijo + `configuracion_sistema` (umbral) |
| Promociones | promociones activas hoy | `promociones` |

**Implementado en la Fase 7** (una sola página con anclas, ver
`app/Views/public/home.php`): todas las secciones de la tabla. El
indicador "abierto ahora" de horarios no se implementó — se muestra la
tabla completa en el pie de página; se puede agregar después si hace
falta.

Config nueva que no estaba en la Fase 2/3 (agregada directo a
`configuracion_sistema`, seguimos antes del lanzamiento): `frase_hero`,
`descripcion_corta`, `hero_imagen`, `facebook_url` — todas editables
desde `/admin/configuracion`, `logo` y `hero_imagen` ahora se suben
como archivo (antes solo existían como texto).

Dos decisiones de la Fase 7 documentadas para quien revise el código
después:
- `App\Core\Vista::pendiente()` marca con `[PENDIENTE: ...]` un dato
  de negocio que falta (dirección, horario, "sobre nosotros", reseña
  de Google, foto de portada) — nunca se inventa. `Vista::
  fotoOMarcador()` es distinto: es el caso normal de un catálogo que
  se va completando con fotos, así que usa un ícono discreto ("📷 Foto
  pendiente") en vez de un aviso en mayúsculas repetido en cada una de
  las 46 tarjetas de producto.
- El campo `google_maps_embed` es el único dato del sitio que se
  imprime **sin** `htmlspecialchars()` (ver `home.php`, sección
  Ubicación): es código `<iframe>` que solo un Administrador
  autenticado puede escribir desde el panel, no contenido enviado por
  un visitante — está documentado con un comentario en el propio
  archivo para que no se confunda con un descuido.

### 3.2 Panel administrativo

**Roles confirmados para v1** (§10): dos roles con permisos distintos.

| Rol | Puede ver/usar | No puede |
|---|---|---|
| **Administrador** | todos los módulos: productos, categorías, galería, temporadas, promociones, clientes, lealtad, recompensas, configuración, usuarios, auditoría | — |
| **Cajero** | Clientes, Lealtad (registrar compra de café), Recompensas (canjear) | Productos, Categorías, Galería, Temporadas, Promociones, Configuración, Usuarios, Auditoría |

El control de acceso se resuelve en el Core (Fase 3) con una función
`check_role()` equivalente a la de PG2 (`includes/auth_check.php`), aplicada
al inicio de cada controlador del panel.

| Módulo | Función |
|---|---|
| Autenticación | login, roles, bloqueo por intentos fallidos |
| Dashboard | resumen: productos activos, promos vigentes, clientes con recompensa disponible |
| Productos | CRUD, foto, precio, categoría, destacado/más vendido, activo/inactivo |
| Categorías | CRUD, orden, nota libre (ej. "Leches disponibles: entera +Q0...") |
| Galería | CRUD de fotos del local |
| Temporadas | CRUD, rango de fechas, asignación de productos |
| Promociones | CRUD con tipo, alcance, fechas, días, horas, combinable con lealtad sí/no |
| Clientes | alta/edición, búsqueda por teléfono |
| Lealtad | registrar compra de café, ver saldo, canjear recompensa |
| Recompensas | historial de canjes (qué café se dio gratis, cuándo, quién lo registró) |
| Configuración | redes sociales, WhatsApp, horarios, mapa, calificación de Google, textos del sitio |
| Usuarios | CRUD de usuarios del panel y su rol |
| Auditoría | bitácora de creación/edición/eliminación en todos los módulos |

---

## 4. Flujos clave

### 4.1 Motor de lealtad (rediseñado — ver también §5.1 y §8)

La regla ("cada N cafés = 1 café gratis") **nunca está escrita como
número fijo en el código**: N siempre se lee de
`configuracion_sistema.umbral_lealtad` (`LealtadService::
obtenerUmbral()`). Cambiarlo desde Configuración cambia el
comportamiento para todos los clientes de inmediato, sin tocar código.

**Registrar consumo:**
1. El cajero busca al cliente por teléfono desde `/admin/lealtad` y
   registra N cafés.
2. El servidor (`LealtadService::registrarConsumo()`, con el cliente
   bloqueado vía `SELECT ... FOR UPDATE` dentro de una transacción)
   inserta un movimiento `consumo` y calcula cuántas veces el nuevo
   saldo cruza el umbral. Ejemplo real probado: partiendo de 0,
   registrar 25 genera automáticamente **2 recompensas** y deja
   **5** acumulados — sin caso especial en el código, es aritmética
   modular contra el umbral configurado (10→1+0, 11→1+1, 20→2+0,
   25→2+5, los cuatro ejemplos dados se verificaron exactos).
3. Cada recompensa generada crea una fila en `recompensas` (código
   único, estado `disponible`, fecha de vencimiento según
   `lealtad_dias_vencimiento_recompensa`) y su propio movimiento
   `recompensa_generada` (cantidad = -umbral) en `lealtad_movimientos`.

**Ajuste manual** (`registrarAjuste()`): motivo obligatorio; un
ajuste positivo puede generar recompensas igual que un consumo; uno
negativo se rechaza si dejaría el saldo por debajo de cero.

**Cancelar una recompensa no canjeada**: solo si su estado es
`disponible`; devuelve exactamente los cafés que costó (se lee del
movimiento que la generó, no del umbral *actual*, por si cambió desde
entonces) y queda su propio movimiento `recompensa_cancelada` —
nunca se pierden cafés en silencio.

### 4.2 Canjear una recompensa (por código)
1. El cliente ve su código (ej. `2FXQ-Z4CR`) en su cuenta (`/cuenta`,
   teléfono + PIN de 4 dígitos) y se lo muestra al cajero.
2. El cajero lo escribe en `/admin/lealtad/canjear` (paso 1: consultar
   — muestra a quién pertenece y su estado, sin canjear todavía).
3. Confirma eligiendo el café a entregar (paso 2:
   `LealtadService::validarYCanjearRecompensa()`), que dentro de una
   transacción hace `SELECT ... FOR UPDATE` sobre la fila de la
   recompensa — si dos cajeros confirman el mismo código casi al mismo
   tiempo, el segundo espera y al continuar ya ve `estado=canjeada`.
   Verifica en orden: existencia, pertenencia (el código ya identifica
   un único cliente), disponibilidad y vencimiento (perezoso: si ya
   pasó la fecha, se marca `vencida` en ese momento y se rechaza).
   Probado: doble intento de canje del mismo código rechazado tanto
   por la interfaz como directamente contra el controlador.
4. Mejora futura documentada: hoy el canje es por **código
   alfanumérico** (decisión explícita — sin librerías/servicios
   externos de generación de QR todavía). Agregar una imagen QR
   escaneable del mismo código es un paso adicional pendiente de
   decidir cómo generarla (librería JS vendorizada localmente vs.
   servicio externo).
5. Mejora futura documentada (pedida explícitamente, no implementada
   aún): un QR para que el propio cliente **acumule** cafés sin pasar
   por el cajero. Por ahora acumular sigue siendo siempre vía cajero +
   búsqueda por teléfono.

### 4.3 Cuenta simple del cliente
Teléfono + PIN de 4 dígitos, sin correo ni verificación
(`App\Core\ClienteAuth`, sesión completamente separada de `Auth` del
panel). Un PIN de 4 dígitos tiene poquísima entropía, así que lleva el
mismo endurecimiento que el login del panel: bloqueo por cuenta tras 5
intentos, límite por IP, comparación de tiempo constante. El registro
puede crear un cliente nuevo o "reclamar" uno que ya existía (creado
por un cajero) activándole el PIN sin tocar su historial ni su nombre.
El panel del cliente (`/cuenta`) siempre parte de la sesión, nunca de
un ID en la URL — no puede ver el progreso de otro cliente.

### 4.4 Promociones — revisión de errores lógicos

Tipos, alcance excluyente (todo/categoría/producto), vigencia
(fechas/días/horas) y `combinable_con_lealtad` (por defecto **no**) ya
eran correctos. Esta revisión encontró y corrigió dos errores reales:

1. **Eliminar una categoría no revisaba si tenía promociones.** La FK
   `promociones.categoria_id` tiene `ON DELETE CASCADE`: sin este
   chequeo, borrar la categoría borraba en silencio cualquier
   promoción ligada a ella, sin pasar por el controlador ni quedar en
   auditoría. Corregido: `CategoriaController::eliminar()` ahora
   también cuenta promociones antes de permitir el borrado.
2. **Una promoción "vigente" no verificaba que su producto/categoría
   objetivo siguiera activo.** Desactivar un producto no ocultaba las
   promociones que lo anunciaban. Corregido en
   `Promocion::listarVigentesAhora()`.

**Limitación conocida, documentada a propósito**: no se soportan
horarios de promoción que cruzan medianoche (ej. 22:00–02:00) — el
horario del negocio no muestra evidencia de operar de madrugada
(docs/negocio.md), así que se prefirió simplicidad sobre soportar un
caso sin evidencia de necesitarse; el formulario avisa este límite en
el mensaje de error en vez de fallar en silencio.

**Sobre "no acumular por defecto"**: el sistema no tiene motor de
cobro/checkout automático (no es una tienda en línea), así que no hay
ningún lugar del código que sume descuentos de dos promociones a la
vez — `prioridad` existe para que el cajero, humano, sepa cuál aplicar
si dos coinciden, y `combinable_con_lealtad` es la única interacción
automática (informativa) con el programa de lealtad.

**Condiciones de carrera**: las promociones son CRUD puro, sin ningún
paso de "canje" ni contador compartido — a diferencia de lealtad, no
hay superficie real de condición de carrera aquí.

### 4.4 Publicar una foto (producto o galería)
- Validar extensión (jpg/png/webp), tipo MIME real (no solo por
  extensión), tamaño máximo, y renombrar el archivo al guardarlo (evita
  sobrescritura y *path traversal*). La carpeta `uploads/` tiene ejecución
  de PHP deshabilitada por `.htaccess`, aunque aquí las fotos son de
  producto/local (no datos sensibles de menores, a diferencia de PG2).

### 4.5 Login del panel
- `password_hash` / `password_verify`, `session_regenerate_id(true)` al
  autenticar, token CSRF en el formulario (validado automáticamente por
  el Router en todo POST, no opcional por controlador), y dos niveles de
  bloqueo temporal tras intentos fallidos: por cuenta (`usuarios.
  intentos_fallidos` / `bloqueado_hasta`) y por IP (tabla
  `intentos_login`, protege contra fuerza bruta repartida entre varias
  cuentas desde el mismo origen). `password_verify()` siempre se ejecuta
  con algún hash — real o ficticio — para que el tiempo de respuesta no
  revele si un usuario existe o no.

---

## 5. Modelo de datos (entidades y relaciones)

```
roles ───< usuarios ───< bitacora_auditoria
                   └───< intentos_login

categorias ───< productos >─── producto_temporadas ───> temporadas
                    │
                    └── (foto propia)

galeria  (independiente, fotos del local)

clientes ───< lealtad_movimientos >─── productos (canje: cuál café)

promociones >─── categorias (alcance opcional)
           >─── productos  (alcance opcional)

configuracion_sistema   (clave/valor: redes, whatsapp, mapa, textos, umbral de lealtad)
horarios_atencion       (horario semanal)
```

### 5.1 Tablas (15 — se agregó `recompensas` al rediseñar lealtad)

- **roles** — id, nombre (seed inicial: `Administrador`, `Cajero`; ver
  permisos por rol en §3.2)
- **usuarios** — id, nombre, usuario, password_hash, rol_id, activo,
  ultimo_acceso, intentos_fallidos, bloqueado_hasta, creado_en
- **intentos_login** — id, usuario_o_ip, exitoso, ip, user_agent, creado_en
- **bitacora_auditoria** — id, tabla_afectada, registro_id, accion,
  usuario_id, valores_antes (json), valores_despues (json), ip, creado_en
- **categorias** — id, nombre, slug, descripcion, notas, orden, activo
- **productos** — id, categoria_id, nombre, descripcion, precio,
  precio_alt (nullable), etiqueta_precio_alt (nullable, ej. "Sin café"),
  imagen, es_destacado, es_mas_vendido, activo, orden, creado_en,
  actualizado_en
- **temporadas** — id, nombre, descripcion, fecha_inicio, fecha_fin, activo
- **producto_temporadas** — producto_id, temporada_id (N:M)
- **galeria** — id, imagen, titulo, descripcion, orden, activo, creado_en
- **promociones** — id, nombre, descripcion, tipo (descuento_porcentaje /
  descuento_monto / dos_por_uno / producto_gratis), valor, categoria_id
  (nullable), producto_id (nullable), fecha_inicio, fecha_fin,
  dias_semana, hora_inicio, hora_fin, combinable_con_lealtad, prioridad,
  activo
- **clientes** — id, nombre, telefono (único), email (nullable),
  cafes_acumulados (saldo SIN convertir en recompensa), notas,
  pin_hash (nullable — null = todavía sin cuenta propia),
  pin_intentos_fallidos, pin_bloqueado_hasta, ultimo_acceso_cliente,
  creado_en, actualizado_en
- **lealtad_movimientos** — id, cliente_id, tipo (consumo /
  ajuste_positivo / ajuste_negativo / recompensa_generada /
  recompensa_canjeada / recompensa_cancelada), cantidad, motivo
  (obligatorio a nivel de aplicación en los ajustes), referencia (ej.
  código de la recompensa relacionada), estado (activo/anulado),
  usuario_id, creado_en
- **recompensas** — id, cliente_id, codigo (único, 8 caracteres sin
  0/O/1/I para evitar confusión al teclear), estado (disponible /
  canjeada / cancelada / vencida), movimiento_generador_id,
  fecha_generada, fecha_vencimiento (nullable), producto_canjeado_id
  (nullable hasta el canje), usuario_canjeo_id, fecha_canjeada,
  movimiento_canje_id, usuario_cancelacion_id, fecha_cancelacion,
  motivo_cancelacion, movimiento_cancelacion_id
- **configuracion_sistema** — clave, valor, descripcion, actualizado_en
- **horarios_atencion** — id, dia_semana, hora_apertura, hora_cierre,
  cerrado

Sobre precios con variantes (ej. frappé con/sin café, leche
entera/deslactosada/almendras): como **no hay checkout**, no se necesita un
motor de modificadores relacional. `precio` + `precio_alt` cubre los casos
de "dos precios por producto" (frappés), y las notas de leche se muestran
como texto informativo en la categoría "Café" (campo `categorias.notas`),
igual que aparece en tu lista de precios real. Si en el futuro se agrega
venta en línea, ahí sí se justificaría una tabla de modificadores — no
antes.

---

## 6. Estructura de carpetas propuesta

```
elcharco/
├── app/
│   ├── Controllers/
│   │   ├── Public/            (Home, Menu, Galeria, Contacto, ...)
│   │   └── Admin/              (Productos, Categorias, Promociones, ...)
│   ├── Models/                 (Producto, Categoria, Cliente, ...)
│   ├── Services/                (LealtadService, PromocionService, AuditoriaService)
│   ├── Core/                    (Router, Controller base, Database, Auth, Csrf)
│   └── Views/
│       ├── public/
│       ├── admin/
│       └── layouts/
├── public/                      (document root)
│   ├── index.php                 (front controller: sitio público + panel)
│   ├── assets/ (css, js, img, vendor/bootstrap...)
│   └── uploads/ (fotos productos y galería; .htaccess sin ejecución PHP)
├── config/
│   ├── config.php
│   └── database.php              (credenciales — fuera de git)
├── database/
│   ├── schema.sql
│   └── migrations/
├── storage/
│   └── logs/                      (errores; nunca mostrados al usuario)
├── docs/
│   ├── negocio.md
│   └── arquitectura.md
├── .gitignore
└── README.md
```

`config/database.php` con las credenciales reales nunca se sube a git — se
crea localmente y en el servidor a partir de una plantilla
`config/database.example.php` que sí queda versionada.

---

## 7. Seguridad (no negociable)

- PDO con sentencias preparadas siempre; nunca concatenar SQL.
- `htmlspecialchars()` en toda salida a HTML.
- `password_hash()` / `password_verify()` para usuarios del panel.
  `password_verify()` se ejecuta siempre (con un hash real o uno
  ficticio) para que el tiempo de respuesta del login no revele si un
  usuario existe o no.
- `session_regenerate_id(true)` al iniciar sesión; al cerrar sesión se
  destruyen los datos en el servidor **y** se expira la cookie en el
  navegador.
- Token CSRF validado automáticamente por el Router en todo POST — no es
  una llamada que cada controlador nuevo deba recordar hacer.
- Cookies de sesión `HttpOnly`, `SameSite=Strict`, `Secure` en producción
  (Hostinger da SSL gratuito).
- Cabeceras HTTP `X-Content-Type-Options: nosniff`, `X-Frame-Options:
  SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` en toda
  respuesta.
- Bloqueo temporal tras intentos de login fallidos, en dos niveles: por
  cuenta (`usuarios.intentos_fallidos`/`bloqueado_hasta`) y por IP (tabla
  `intentos_login`), para cubrir tanto ataques contra una cuenta como
  fuerza bruta repartida entre varias cuentas desde el mismo origen.
- Nunca mostrar `$e->getMessage()` al usuario — log en `storage/logs/`,
  mensaje genérico en pantalla. `ErrorHandler` fija `display_errors`
  según `APP_DEBUG` (nunca confía en el `php.ini` del hosting) y registra
  un manejador de `shutdown` para los errores fatales del motor que no
  pasan por las excepciones normales.
- `Controller::vista()` valida con `realpath()` que la plantilla
  resuelta siga dentro de `app/Views` (defensa en profundidad contra
  inclusión de archivos, aunque hoy el nombre de vista siempre es un
  valor fijo en el código).
- Validación estricta de archivos subidos (extensión + MIME real + tamaño
  + nombre aleatorio) y carpeta `uploads/` sin permiso de ejecutar PHP.
- Credenciales de base de datos fuera de git, usuario de MySQL dedicado sin
  privilegios de root (igual que en PG2).
- `app/`, `config/`, `database/` no accesibles por URL (ver §2.4).
- Clase `Validator` (`app/Core/Validator.php`) para validar datos de
  formularios de forma consistente en las Fases 4-6 (requerido, longitud
  máxima/mínima, numérico, email, valores permitidos).
- `AuditoriaService::registrar()` nunca puede tumbar la operación que
  está auditando: corre en su propio try/catch y usa
  `JSON_INVALID_UTF8_SUBSTITUTE` al codificar los valores antes/después.
  Se encontró en pruebas que sin esto, un dato con una codificación
  inválida producía un error 500 aunque el cambio de negocio ya se
  hubiera guardado correctamente — la auditoría es un registro
  secundario y su fallo debe degradarse (log) sin bloquear al usuario.
- Interpretación de alcance: el `ENUM` de `bitacora_auditoria.accion`
  se amplió a `crear, editar, eliminar, login, consumo, canje`. Las
  palabras "recompensa" y "canje" pedidas para auditoría se tratan como
  el mismo evento (`canje`: se entrega el café gratis) — si en el
  futuro se necesita distinguir "el cliente se volvió elegible" de "se
  canjeó", se agrega un valor de ENUM aparte.

---

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Elegir Laravel y que el plan de Hostinger no traiga SSH/Composer | Se descarta Laravel para v1 (ver §2.2); reevaluar solo si el negocio crece a e-commerce |
| Calificación de Google requiere Google Places API (costo/facturación) | Mostrar valor editable manualmente desde `configuracion_sistema` + enlace directo a la ficha de Google, sin API de pago |
| Un solo desarrollador da mantenimiento | Priorizar stack simple sin framework pesado; código en capas para que sea legible meses después |
| Error humano al registrar consumo/canje de lealtad | Bitácora de auditoría en todos los módulos + roles + reportes de movimientos por cliente |
| Fotos sin optimizar afectan velocidad | Límite de tamaño al subir + redimensionado con GD (disponible en Hostinger) |
| Precios/menú "quemados" en el código | Todo el menú vive en base de datos, editable 100% desde el panel |
| Pérdida de datos | Respaldo automático de Hostinger + exportación periódica de `schema.sql`/dump documentada |
| Que dos promociones se acumulen sin querer | Alcance + prioridad + bandera `combinable_con_lealtad`, resueltos en el servidor (ver §4.3) |

---

## 9. Plan por fases

- **Fase 0 — Preparación del entorno** ✅ completada (carpeta, git, README,
  `negocio.md`).
- **Fase 1 — Arquitectura** ✅ este documento (pendiente tu aprobación).
- **Fase 2 — Base de datos**: `database/schema.sql` completo + diagrama ER
  final con los ajustes que salgan de tu revisión.
- **Fase 3 — Núcleo (Core)**: router, conexión PDO, autenticación de panel,
  layout base, CSRF, manejo de errores/404/403, `.htaccess`.
- **Fase 4 — Panel: catálogo**: categorías, productos, galería, temporadas.
- **Fase 5 — Panel: lealtad y promociones**: clientes, lealtad, recompensas,
  promociones.
- **Fase 6 — Panel: administración**: usuarios, configuración, auditoría.
- **Fase 7 — Sitio público**: todas las secciones listadas en §3.1.
- **Fase 8 — Diseño visual**: paleta beige/café/verde, identidad de la rana,
  responsive real (mobile-first).
- **Fase 9 — Seguridad y pruebas**: revisión de checklist de §7, pruebas
  manuales de los flujos de §4.
- **Fase 10 — Despliegue en Hostinger**: configuración de dominio,
  document root, SSL, base de datos de producción, respaldo inicial.

No se avanza de una fase a la siguiente sin tu confirmación explícita.

---

## 10. Decisiones confirmadas (Fase 1 aprobada)

1. **Stack**: MVC ligero en PHP plano (§2.3). Se descarta Laravel para
   esta versión.
2. **Estructura de carpetas**: `public/` como raíz web (§2.4), con Virtual
   Host local en XAMPP apuntando a `elcharco/public` (se configura en la
   Fase 3).
3. **Calificación de Google**: valor editable manualmente desde
   `configuracion_sistema`, con enlace a la ficha de Google. Sin API de
   pago.
4. **Roles del panel**: dos roles desde el inicio — `Administrador` y
   `Cajero`, con permisos diferenciados (detalle en §3.2).

Con esto queda cerrada la Fase 1. La Fase 2 (diseño completo de
`database/schema.sql`, con las 14 tablas de §5.1 ya ajustadas a estas
decisiones) inicia cuando lo confirmes explícitamente.
