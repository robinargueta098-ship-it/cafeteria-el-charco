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
`descripcion_corta`, `facebook_url` — todas editables desde
`/admin/configuracion`; `logo` se sube como archivo (antes solo existía
como texto). (`hero_imagen` existió un tiempo y se reemplazó por la
tabla `portada_imagenes`, ver §4.6.)

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

### 4.2b Código de canje: unicidad, pertenencia y uso único

Columna `recompensas.codigo`. Una recompensa = un código = un cliente
(`cliente_id`); un cliente puede tener varias recompensas, cada una con
su propio código.

**Generación** (`Recompensa::nuevoCodigoAleatorio()`): 8 caracteres con
`random_int()` (aleatorio criptográfico) de un alfabeto de 31 símbolos
sin ambigüedades (sin 0/O/1/I/L), sin guion ni prefijo (ej. `G5S3WYHX`):
31⁸ ≈ 8,5×10¹¹ combinaciones, no consecutivos ni deducibles. En 20 000
códigos generados: todos válidos y todos distintos. (Formato acortado en
§4.14 — antes era `XXXX-XXXX`, con guion; los códigos viejos con guion
se siguen aceptando igual.)

**Unicidad, en tres capas:**
1. `UNIQUE` en la columna (la garantía real). La colación
   `utf8mb4_unicode_ci` la hace insensible a mayúsculas: `abcd-efgh` y
   `ABCD-EFGH` cuentan como el mismo código.
2. Comprobación previa (`generarCodigoUnico()` no devuelve uno que ya
   exista) — solo una optimización: comprobar y luego guardar NO es
   atómico, otra petición puede generar el mismo código en medio.
3. Reintento automático ante colisión
   (`LealtadService::crearRecompensaConCodigoUnico()`): si el INSERT
   choca con el `UNIQUE` (error 1062) se deshace solo ese intento con un
   `SAVEPOINT` (así no queda un movimiento huérfano en el historial) y se
   genera otro código, hasta 10 veces; agotados, se deshace toda la
   operación y el usuario ve un error genérico (el detalle va al log).
   Probado forzando 2 colisiones seguidas: la recompensa quedó con el
   3.er código y el historial sin residuos.

**Restricciones de la base (`schema.sql`)**: `UNIQUE` sobre
`movimiento_generador_id` (un movimiento no genera dos recompensas) y
sobre `movimiento_canje_id`; `CHECK` de longitud mínima del código;
`CHECK` de que una recompensa `canjeada` siempre trae fecha, producto,
usuario y movimiento de canje (no existe un canje a medias). Los `CHECK`
los aplica MariaDB ≥ 10.2 y MySQL ≥ 8.0.16 (versiones anteriores de
MySQL los aceptan pero los ignoran).

**Uso único ("usada" + fecha)**: al canjear, `estado='canjeada'` y
`fecha_canjeada`. `marcarCanjeada()` ejecuta
`UPDATE ... WHERE estado='disponible'` y devuelve si tocó una fila, así
que aun sin el candado una recompensa no disponible no se vuelve a
canjear. Probado con 6 procesos canjeando el mismo código a la vez:
exactamente 1 exitoso, 5 rechazados, 1 solo movimiento en el historial.

**Pertenencia al canjear**: el cajero identifica al cliente por
teléfono **y** escribe el código; el servidor resuelve el cliente desde
el teléfono y, con la recompensa ya bloqueada, exige
`recompensa.cliente_id === cliente.id` *antes* de revelar nada de su
estado. Un código ajeno se rechaza con "no pertenece a este cliente" y
queda registrado en el log (posible intento de fraude). El paso de
confirmar repite toda la validación; nada del paso de consulta se da por
bueno. Los códigos se normalizan (`abcd 2345`, `ABCD2345`, `abcd-2345`).

**Barrera opcional en la propia BD**
(`database/opcional_trigger_recompensas.sql`, aparte de `schema.sql`):
trigger `BEFORE UPDATE` que impide, aun con un `UPDATE` manual, cambiar
el código o el dueño, o sacar una recompensa de un estado final
(canjeada/cancelada/vencida) de vuelta a `disponible` (que sería
reutilizarla). Va separado porque algunos hostings compartidos
restringen crear triggers (error 1419): si en Hostinger falla se puede
omitir sin afectar nada, la aplicación se protege sola.

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

### 4.5 Galería (carrusel público + administración)

Tabla `galeria` (`imagen`, `titulo`, `descripcion`, `orden`, `activo`,
`creado_en`). Los nombres difieren de los que se sugirieron como
ejemplo (`ruta_imagen`, `fecha_subida`) pero cumplen exactamente el
mismo papel; no se renombraron para no tocar un esquema que ya
funciona. Solo se guarda el nombre de archivo (aleatorio), nunca el
nombre original, en `public/uploads/galeria/`.

- **Subida**: varias fotos por envío (máx. 10). Cada archivo se valida
  por separado en el servidor (extensión permitida, tipo MIME real con
  `finfo`, `getimagesize()`, ≤ 5 MB); uno inválido no impide que se
  suban las demás, y el mensaje dice cuál falló y por qué. Probado con
  un `.php` renombrado a `.jpg`, un GIF y un archivo de 6 MB: los tres
  rechazados, nada raro en disco. El navegador avisa antes de enviar
  (`admin.js`), pero solo por comodidad: el servidor decide.
- **Lote demasiado grande**: si el envío completo supera
  `post_max_size`, PHP descarta `$_POST` y `$_FILES` y el token CSRF
  parecía "faltar" (403 engañoso). `CsrfMiddleware` ahora lo detecta y
  responde 413 con un mensaje claro.
- **Orden**: flechas ▲▼ (`Galeria::mover()`). Antes de intercambiar
  renumera todas las fotos 1..n dentro de una transacción con las
  filas bloqueadas, así que funciona aunque haya empates o huecos en
  `orden` (fotos antiguas que quedaron todas en 0) y dos clics
  simultáneos no se pisan. Cada movimiento queda en la auditoría.
  Las fotos nuevas se agregan al final.
- **Carrusel público**: contenedor con `scroll-snap` (se desliza sin
  JavaScript); `sitio.js` agrega flechas, puntos y avance automático
  cada 5 s. Se pausa mientras el mouse (solo mouse, no toques) está
  encima o el foco de **teclado** está dentro (el foco que deja un clic
  en una flecha no cuenta: dejaba el carrusel trabado); tras usarlo (flechas,
  puntos, arrastre, teclado, rueda horizontal) espera 8 s sin actividad y
  **vuelve a avanzar solo** (antes quedaba detenido para siempre). La rueda
  vertical, que es bajar por la página, no cuenta. Tampoco avanza con
  la pestaña oculta ni si el usuario pidió "reducir movimiento". 1 foto (+ borde de la siguiente) en
  móvil, 2 en tablet, 3 en escritorio; con pocas fotos se centran y
  desaparecen flechas/puntos. Recalcula con `ResizeObserver` (rotar el
  teléfono, cambiar el zoom).
- **Optimización de imágenes** (`App\Core\ImagenOptimizada`): al
  subir una foto a la galería, si el servidor tiene GD, se generan
  versiones WebP reducidas junto al original
  (`{nombre}-w640.webp`, `-w1280`, `-w1600`; nunca se amplía: una foto de
  1200 px produce 640 y 1200). Se descubren con un `glob`, sin tabla
  extra, y se borran junto con la foto. Aplica la orientación EXIF
  (las fotos de teléfono "de lado") antes de reducir. Nunca lanza: sin
  GD, con una foto corrupta o de más de 25 megapíxeles simplemente no
  hay versiones y se usa el original. Las fotos ya existentes se
  procesan con `php -d extension=gd bin/optimizar_imagenes.php`
  (`--forzar` regenera todas). El panel avisa cuando el servidor no
  tiene GD (el XAMPP local no lo trae activo en Apache; Hostinger
  normalmente sí).

### 4.6 Fondo automático del hero

Las imágenes de la tabla **`portada_imagenes`**, en el orden del panel,
se funden entre sí cada 5 s como fondo de la portada. Solo decorativo:
sin flechas ni puntos, `aria-hidden`, y el texto, los botones y el
overlay no se tocan (verificado contra la versión anterior). La sección
Galería sigue siendo el carrusel manual, sin cambios.

**Administración** (`/admin/configuracion`, tarjeta "Imágenes de
portada", solo rol Administrador): subir varias a la vez, reordenar con
▲▼ y eliminar. Cada acción es un POST propio que se aplica al instante
y regresa a la misma tarjeta (no depende del botón "Guardar
configuración"; por eso va en una tarjeta aparte: HTML no permite
formularios anidados). `PortadaController` + `PortadaImagen`.

- **Tabla y carpeta propias, separadas de la Galería**:
  `portada_imagenes` (`id`, `imagen`, `orden`, `creado_en`; `imagen` es
  único) y `public/uploads/portada/`. Borrar una foto de un lado nunca
  toca la del otro (probado: con toda la galería desactivada el hero
  sigue con sus imágenes). Se guarda solo el nombre de archivo aleatorio.
  Se llama `imagen` (no `ruta_imagen`) por coherencia con `galeria` y
  `productos`.
- **Validación** igual que en la galería (comparten `SubidaArchivo`):
  extensión permitida, tipo MIME real con `finfo`, `getimagesize()`,
  ≤ 5 MB. Un archivo inválido no bloquea a los demás y el mensaje dice
  cuál falló y por qué. Tope de **8 imágenes** en total
  (`PortadaController::MAX_IMAGENES`) y 10 por envío: el hero descarga
  cada imagen durante su ciclo, así que un número ilimitado terminaría
  pesando en la página. Un envío que excede el cupo restante se rechaza
  completo con un mensaje claro.
- **Orden y borrado**: la regla de orden (renumerar 1..n en una
  transacción con filas bloqueadas) es compartida con la galería en
  `App\Core\OrdenLista`, con lista blanca de tablas (un nombre de tabla
  no se puede parametrizar en SQL). Eliminar borra la fila, el archivo y
  sus versiones WebP. Todo queda en la bitácora de auditoría.
- **Configuración obsoleta**: la clave `hero_imagen` (una sola foto) se
  eliminó de `configuracion_sistema`, del formulario y de `schema.sql`.
  El archivo antiguo (`uploads/sitio/`, 246×497 px) no se borró.

- **Fundido sin parpadeo**: la opacidad 0.55 (la de la foto fija de
  antes) va en el contenedor y las fotos son opacas; la nueva entra
  encima mientras la anterior espera debajo. Con dos fotos
  semitransparentes, a mitad del fundido se vería un bajón oscuro.
- **Carga**: solo la primera foto viene en el HTML (`fetchpriority=high`,
  WebP de ~50–115 KB); las demás no traen `src` y `sitio.js` las baja de
  a una, DESPUÉS del evento `load`, y solo muestra la siguiente cuando ya
  llegó. Medido: al cargar la página se descarga 1 foto; en todo el ciclo
  ~550 KB frente a ~1 235 KB de los originales. `srcset` + `sizes` eligen
  la versión según la pantalla; en teléfonos (`max-width: 767px`) se pide
  la de 640 px (23–46 KB) porque es un fondo tenue bajo un overlay oscuro.
- **Cuida batería y datos**: se pausa con la pestaña oculta y con el
  hero fuera de pantalla; no se mueve (ni descarga las demás) con
  `prefers-reduced-motion` ni con modo ahorro de datos / conexión 2G;
  sin JavaScript se ve la primera foto fija.
- **Respaldos**: sin versiones optimizadas usa los originales; con una
  sola imagen el fondo queda fijo (sin carrusel; el panel lo avisa); sin
  ninguna, el marcador `[PENDIENTE: fotografía de portada]`.
- (La limitación de "panel solo para computadora" quedó resuelta: ver §4.7.)

### 4.7 Panel responsive (celular)

Hasta aquí solo el sitio público se adaptaba al celular; el panel tenía una
barra lateral de ancho fijo. Ahora todo el panel funciona desde 360 px.
El escritorio (≥ 768 px) no cambió: se comparó la geometría de 3 141
elementos en 28 pantallas antes y después → 0 diferencias.

- **Menú**: en pantallas < 768 px la barra lateral es un panel deslizable
  que se abre con un botón de hamburguesa en la barra superior fija.
  Se cierra con ✕, tocando el fondo oscuro, con Escape o al elegir una
  sección; mientras está abierto el foco queda dentro (Tab/Shift+Tab) y la
  página de atrás no se desplaza. Es mejora progresiva: `<html>` recibe la
  clase `js` desde un script en línea, y sin JavaScript el menú simplemente
  se apila arriba (nada queda inaccesible).
- **Tablas**: desplazamiento horizontal dentro de su tarjeta, con la aviso
  "Desliza…" solo cuando de verdad desbordan. Las de Galería e Imágenes de
  portada se convierten en tarjetas apiladas (miniatura, título, estado,
  ▲ ▼ y Eliminar) con botones de 44 px, porque se usan para subir y
  ordenar fotos desde el teléfono. Las tablas cuya primera columna es una
  fecha o código no la parten (`tabla-primera-sin-corte`).
- **Formularios**: una sola columna, campos y botones a todo el ancho,
  objetivos táctiles ≥ 44 px, horarios en dos líneas (día / horas).
- **Caché**: `Vista::asset()` agrega `?v=<fecha de modificación>` a CSS/JS
  en todos los layouts, para que un HTML nuevo nunca se mezcle con un CSS
  viejo guardado en el navegador.
- **Verificación**: 29 pantallas × 360/390/414 px medidas con un script
  (desborde de página, elementos fuera de pantalla, texto recortado,
  solapes, botones < 36 px): antes 15 pantallas desbordaban y había 226
  objetivos pequeños; ahora 0 en todo. No se probó en un teléfono físico.
- **Corrección incidental**: crear un producto sin foto daba error 500
  (`imagen` no llegaba al modelo); ahora se guarda como NULL.

### 4.8 Sesión vencida: adiós al callejón sin salida del 403

**Síntoma**: tras iniciar o cerrar sesión el panel "se trababa" y F5 no
ayudaba. **Causa**: un POST con token CSRF inválido mostraba un 403 cuyo
único enlace llevaba al sitio público; F5 sobre esa página reenvía el mismo
POST, que vuelve a fallar. El token queda inválido cuando la sesión vence
(los archivos de sesión de XAMPP se borraban a los 24 min de inactividad, en
una carpeta compartida con otras aplicaciones PHP y con la cookie genérica
`PHPSESSID`) o cuando una pantalla vieja sobrevive a un cierre de sesión.

- `CsrfMiddleware`: en `/admin/*` un token inválido ya no muestra 403; el POST
  **no se ejecuta** y se redirige con un aviso ("Tu sesión expiró…" al login,
  o "La página estaba desactualizada…" al panel si sigue con sesión).
- `Session`: cookie propia `ELCHARCO_SESION`, carpeta `storage/sessions`
  (fuera del webroot, ignorada por git), vida de 8 h y `use_strict_mode`.
- `Auth`: el token CSRF se rota al iniciar sesión; `verificado()` comprueba
  en cada petición que el usuario siga existiendo y activo (antes una sesión
  abierta seguía valiendo tras desactivar o borrar a la persona) y toma el
  rol de la base.
- Páginas 403/404 dentro de `/admin` ofrecen "Volver al panel".
- Consecuencia única: las sesiones abiertas antes de este cambio se cierran
  una vez (cookie nueva).

### 4.9 Canje por QR (cámara) con respaldo de código manual

El código único de recompensa ya existía (tabla `recompensas`, `codigo`
UNIQUE, 8 caracteres aleatorios, se genera solo al llegar a 10 cafés; ver
§4.4). Lo nuevo es cómo se presenta y se lee:

- **QR del cliente** (`/cuenta`): por cada recompensa disponible se muestra
  un QR y, debajo, el mismo código como texto (para dictarlo). El QR contiene
  únicamente el código. Se genera en el servidor con
  `chillerlan/php-qrcode` 5.0.5 (`App\Core\CodigoQr`, salida SVG, sin GD ni
  servicios externos). `vendor/` va en el repositorio para que Hostinger no
  necesite Composer; si faltara, la pantalla muestra el código como texto y
  el canje manual sigue funcionando.
- **Pantalla de canje** (`/admin/lealtad/canjear`, Administrador y Cajero):
  botón "Escanear código" (html5-qrcode 2.3.8, copia local en
  `public/assets/vendor/`) y, al lado, el campo de código manual. Ambos
  llenan el mismo formulario: buscar el código → mostrar a qué cliente
  pertenece → confirmar. El teléfono es opcional (doble verificación).
- **Seguridad del canje** (sin cambios en `LealtadService`): al confirmar, el
  servidor compara el `cliente_id` recibido con el dueño real del código con
  la fila bloqueada (`FOR UPDATE`); un código ajeno, vencido o ya canjeado se
  rechaza. Al canjear queda `estado = canjeada` con fecha, producto y quién lo
  entregó, y una restricción CHECK de la base impide una "canjeada" incompleta.
- **Cajero**: ve Dashboard, Clientes, Lealtad, Canjear código y Recompensas;
  Usuarios, Configuración, Auditoría y catálogo devuelven 403.
- **Limitación de la cámara**: los navegadores solo dan acceso a la cámara en
  páginas seguras (HTTPS o localhost). En producción (HTTPS) funciona; en
  local, `http://elcharco.local` NO cuenta como seguro: usar
  `http://elcharco.localhost` (agregar `ServerAlias elcharco.localhost` al
  VirtualHost) o el campo manual, que no depende de la cámara.

### 4.10 QR personal para sumar cafés (misma lógica que el canje)

Sumar un café también se hace escaneando, con el mismo escáner y el mismo
campo manual de §4.9. Hay dos códigos (formato acortado después, en
§4.14 y §4.15 — ahí queda cómo se distingue uno del otro al leerlos,
ya que dejaron de tener formas distintas):

| Código | Forma | Vive en | Al leerlo |
|---|---|---|---|
| QR personal del cliente | 8 caracteres | `clientes.codigo_qr` (UNIQUE) | muestra al cliente y **suma cafés** |
| QR de recompensa | 8 caracteres | `recompensas.codigo` (UNIQUE) | muestra al dueño y **canjea** |

- El QR personal es **permanente** y se muestra siempre en `/cuenta`. Es
  aleatorio (`random_int`), no se deriva del teléfono ni del id, y se asigna la
  primera vez que el cliente abre su cuenta (`Cliente::obtenerCodigoQr`). Ante
  una colisión, el UNIQUE obliga a generar otro.
- Sumar (`POST /admin/lealtad/canjear/sumar`): el servidor resuelve al cliente
  desde el código (no acepta un id del formulario) y registra el consumo con
  `LealtadService::registrarConsumo`, o sea con el mismo candado, libro mayor y
  auditoría que el registro manual; si llega a 10 cafés genera la recompensa.
- Si el cliente escaneado ya tiene recompensas disponibles, la pantalla ofrece
  canjearlas desde ahí.
- Esquema: columna nueva en `clientes` (`schema.sql` y
  `database/migrations/001_clientes_codigo_qr.sql`). Sin la columna, `/cuenta`
  sigue funcionando pero sin ese QR.
- Riesgo asumido: el QR personal es un "portador"; quien lo fotografíe podría
  hacer que le sumen cafés a otro. El cajero ve siempre el nombre del cliente
  antes de sumar, y cada suma queda auditada.

### 4.11 "Mi cuenta" explica el 0/10, y cancelar una recompensa regenera el código

> **Actualización:** la acción "Cancelar" descrita aquí se eliminó (ver §4.27); el contador de "Mi cuenta" se
> generalizó a varias recompensas (ver §4.28).

Dos ajustes sobre lo anterior:

- **Contador sin explicación**: al llegar al umbral, el contador vuelve a 0 —
  correcto, pero la pantalla no decía por qué. Ahora, cuando el cliente tiene
  una recompensa disponible, arriba del contador aparece una tarjeta
  destacada "🎉 ¡Tienes un café gratis listo para reclamar!" con su QR y
  código, y el contador explica "Ya canjeaste tu café gratis (arriba)…" en
  vez de solo mostrar "0 / 10" (`app/Views/public/cuenta/panel.php`).
- **BUG corregido — cancelar dejaba al cliente sin código**:
  `LealtadService::cancelarRecompensa()` devolvía los cafés al saldo pero
  nunca comprobaba si ese saldo volvía a alcanzar el umbral. Ahora, dentro de
  la misma transacción (la fila del cliente ya está bloqueada con
  `FOR UPDATE`), llama a `generarRecompensasSiCorresponde()` — la misma
  función que ya usan `registrarConsumo()` y `registrarAjuste()`, no una
  copia — así que si el saldo devuelto vuelve a completar el umbral se
  genera de inmediato un código nuevo, único (mismo UNIQUE + reintento de
  siempre) y distinto del cancelado. El aviso en Recompensas lo menciona
  ("se generó automáticamente un nuevo código: …").
- Probado el ciclo completo por HTTP: cliente llega a 10 → código A
  (`disponible`) → el administrador cancela A con un motivo → A queda
  `cancelada` para siempre (ni `consultar` ni `confirmar` lo aceptan ya) →
  el saldo vuelve a 10 → se generó solo el código B → el Cajero lo encuentra
  escribiéndolo en mayúsculas o minúsculas y sin guion (como leería un
  escáner) y lo canjea → un segundo intento con B lo rechaza por ya usado.
  Repetido una vez más (C → cancelar → D) para confirmar que no es un caso
  aislado.

### 4.12 Íconos oficiales de redes en Contacto

Los emojis (📱📷👍) de WhatsApp/Instagram/Facebook en Contacto
(`app/Views/layouts/footer_publico.php`) se reemplazaron por los logos
oficiales como SVG en línea (trazos de [Simple Icons](https://simpleicons.org),
licencia CC0, copiados una sola vez al código — el sitio no depende de
ningún servidor externo para mostrarlos, ni en producción ni sin
conexión). Cada ícono es un `<svg>` con el color de marca (`#25D366`
WhatsApp, `#E4405F` Instagram, `#1877F2` Facebook), `aria-hidden="true"`
porque el nombre de la red sigue siendo el texto visible/accesible del
enlace, tamaño fijo de 20px (`.icono-red`) para no deformarse en celular.

De paso, verificando cómo se veían, apareció un bug de contraste **ya
existente antes de este cambio** (con los emojis también pasaba, solo que
menos notorio): `.sitio-footer a` pintaba el texto de las píldoras en
beige claro sobre fondo blanco, casi ilegible, porque tenía la misma
especificidad que `.redes-lista a` y venía después en el archivo. Se
corrigió con un selector más específico (`.sitio-footer .redes-lista a`).

### 4.13 Horario partido (segundo turno opcional)

El negocio tiene pausa entre semana (ej. 9:00-14:00 y 16:00-21:00): un solo
rango por día ya no alcanzaba. `horarios_atencion` gana `hora_apertura_2` /
`hora_cierre_2` (`database/migrations/002_horarios_segundo_turno.sql`),
NULL cuando el día no tiene segundo turno. El turno 2 es "todo o nada": un
CHECK en la BD (`chk_horario_turno2_completo`) y la validación del
controlador impiden guardar una sola hora del turno 2 suelta.

- **Panel** (`admin/configuracion/formulario.php`): cada día muestra su fila
  de siempre (casilla "abierto" = lo contrario de "Cerrado", turno 1) y,
  debajo, una segunda fila "+ turno 2" con las mismas clases/estilos
  (`.horario-fila`, `.horario-hora`) — mismo diseño, nada nuevo que
  aprender. Vacía si el día no tiene pausa.
- **Validación nueva** en `ConfiguracionController` (antes no había
  ninguna sobre el horario): formato de hora, apertura antes que cierre,
  turno 2 completo o vacío, turno 2 no empieza antes de que cierre el
  turno 1. Un día "Cerrado" no se valida — sus horas, si quedaron
  escritas, se descartan. Si hay error, el formulario vuelve a mostrar
  exactamente lo que el administrador había escrito (antes, un error en
  la configuración general hacía perder lo tecleado en el horario).
- **Sitio público** (`footer_publico.php`): "9:00 a 14:00 y 16:00 a 21:00"
  cuando hay dos turnos; un solo rango si no. "Cerrado" sin cambios.
- Se cargó el horario real del negocio (dado por el dueño): lunes a
  viernes con los dos turnos, sábado un solo turno (17:00-21:00), domingo
  con los dos turnos. Probado por HTTP contra el formulario real:
  guardado correcto, y cinco variantes inválidas (turno 2 a medias, turno 2
  cruzado con el 1, apertura después del cierre, día abierto sin horas,
  formato inválido) rechazadas sin tocar la base, cada una con su
  mensaje específico.

### 4.14 Código de canje más corto: 8 caracteres, sin guion ni prefijo

El código de recompensa (el que canjea el café gratis) era `XXXX-XXXX`
(8 caracteres + guion, 9 en total). Para que sea más fácil de escribir a
mano cuando falla el escaneo, ahora se genera sin guion: `G5S3WYHX`. El
alfabeto no cambió (`Recompensa::nuevoCodigoAleatorio()` ya excluía
0/O/1/I/L y ya usaba solo mayúsculas), así que esto es un cambio de
formato de presentación, no de aleatoriedad ni de tamaño del espacio de
códigos (sigue siendo 31⁸ ≈ 8,5×10¹¹).

- **Los códigos viejos (con guion) siguen funcionando igual**, sin
  migrar nada: `buscarPorCodigo()`, `buscarPorCodigoConBloqueo()` y
  `codigoExiste()` comparan con `REPLACE(codigo, '-', '')`, así que da
  lo mismo si la fila en la base tiene el guion (código viejo) o no
  (código nuevo) — y da lo mismo si quien lo escribe le pone un guion o
  no. `normalizarCodigo()` ya no reconstruye el guion: solo pasa a
  mayúsculas y quita lo que no sea A-Z0-9.
- El QR sigue siendo el mismo mecanismo (`CodigoQr::dataUri()`); lo
  único que cambia es el texto que codifica.
- El QR personal del cliente (para sumar cafés) tenía otro formato
  distinto en este momento (`CL-` + 10 caracteres) — se acortó aparte,
  después, en §4.15.
- Nota de diseño: `codigoExiste()` (la comprobación previa al generar)
  también ignora guiones, así que un código nuevo nunca se propone si sus
  8 caracteres coinciden con los de uno viejo. La garantía dura, el
  `UNIQUE` de la columna, sigue comparando literal (con guion si lo
  tiene); no distingue formatos nuevo/viejo por sí sola, pero para que
  eso importara haría falta que dos peticiones generaran el mismo
  código en el mismo instante Y que ese código coincidiera con uno de
  los pocos cientos ya usados en la vida del negocio — sobre 8,5×10¹¹
  combinaciones, no se justificó una columna generada aparte solo para
  blindar ese caso.

### 4.15 QR personal del cliente: también acortado a 8 caracteres

Mismo espíritu que §4.14, aplicado al otro código del sistema: el QR
personal del cliente (el que suma cafés al escanearlo, siempre el mismo,
permanente). Antes `CL-` + 10 caracteres (13 en total); ahora 8
caracteres sin guion ni prefijo, mismo alfabeto sin ambigüedades
(`Cliente::obtenerCodigoQr()` / `nuevoCodigoQrAleatorio()`).

- **Diferencia importante con §4.14**: ahí los códigos viejos se
  conservan tal cual para siempre (hay muchos por cliente, es un
  historial). Aquí el cliente solo tiene **un** código vigente a la vez,
  así que "acortar el código actual" significa **reemplazarlo**: el
  código viejo dejó de servir en el momento en que se generó el nuevo. No
  hay concepto de "código personal cancelado" como sí lo hay con las
  recompensas.
- `Cliente::regenerarCodigoQr($id)`: a diferencia de `obtenerCodigoQr()`
  (que conserva el código si ya existía), este siempre asigna uno nuevo,
  aunque ya hubiera uno. Se usó una sola vez, por script, para el cliente
  real de pruebas (id 10): su código pasó de `CL-G5S3WYAHXQ` a
  `SBZ94KRR`. No hay botón en el panel para esto todavía — si en el
  futuro hace falta (ej. el cliente comparte su QR sin querer), se agrega
  una acción en Clientes que llame a este mismo método.
- **Los dos códigos (personal y de recompensa) ahora tienen la misma
  forma** (8 caracteres, mismo alfabeto) — antes el prefijo `CL-` y la
  distinta longitud bastaban para saber cuál era cuál con solo mirarlo.
  La pantalla de escaneo (`LealtadController::consultarCodigo()`) ya no
  puede decidir por la forma del código: ahora prueba primero
  `Cliente::buscarPorCodigoQr()` (sumar, el caso más frecuente del día a
  día) y, si no hay ningún cliente con ese código, `Recompensa::buscarPorCodigo()`
  (canjear). Si ninguno de los dos lo tiene, el mensaje es genérico
  ("No se encontró nada con ese código") porque no hay forma de saber
  cuál de los dos esperaba el cajero.
- `buscarPorCodigoQr()` también compara ignorando guiones
  (`REPLACE(codigo_qr, '-', '')`), así que un cliente que se haya quedado
  con el formato viejo (`CL-XXXXXXXXXX`) se sigue encontrando igual,
  escrito con guion o sin él.

### 4.16 El mismo bug del §4.8, pero en la cuenta del cliente

§4.8 corrigió el callejón sin salida del 403 (token CSRF vencido) para el
panel administrativo. `CsrfMiddleware` solo miraba rutas que empezaran con
`/admin`; la cuenta pública del cliente (`/cuenta`, `/cuenta/login`,
`/cuenta/registro`, `/cuenta/logout`) tiene el mismo patrón de formulario
+ token y se quedó con el bug original — un cliente que entraba o salía
con la sesión vencida, o con la pantalla de login abierta en otra
pestaña desde antes, veía un "403 — Acceso denegado" sin ningún enlace
de vuelta a `/cuenta`, y F5 no ayudaba porque reenviaba el mismo POST.

Mismo arreglo, aplicado a `/cuenta/*`:
- `CsrfMiddleware::tokenInvalido()` redirige con un aviso — a `/cuenta`
  ("La página estaba desactualizada…") si `ClienteAuth::verificado()`, a
  `/cuenta/login` ("Tu sesión expiró…") si no — en vez de mostrar el 403.
- `ClienteAuth::intentar()` y `CuentaController::registrar()` rotan el
  token CSRF al iniciar sesión (el del formulario anónimo no debe seguir
  valiendo ya autenticado), igual que ya hacía `Auth::intentar()`.
- El aviso se muestra en `cuenta/login.php` (reutiliza el `$error` que ya
  tenía) y en `cuenta/panel.php` (nuevo bloque `$flash`, mismo patrón que
  `panel_header.php` del panel administrativo).
- Probado el caso exacto reportado: entrar con un token vencido → aviso
  y login limpio; entrar de verdad y luego pulsar "Cerrar sesión" con un
  token vencido (pantalla vieja) → sigue con la sesión abierta, con el
  aviso, en vez de un 403; cerrar sesión con un token válido → funciona
  igual que siempre.

### 4.17 "Mi cuenta": el contador ya no muestra 0/10 justo al completar la tarjeta

Bug real, no solo de texto: al llegar al umbral, `cafes_acumulados` vuelve
a 0 de inmediato (es correcto — así funciona sumar 25 de una vez y que
genere 2 recompensas dejando 5 acumulados, §4.1). El problema era la
pantalla: en el instante exacto en que el cliente completaba sus 10
cafés, veía "0 / 10" al lado de su QR nuevo, como si no hubiera pasado
nada — y encima el texto decía **"Ya canjeaste tu café gratis"**, en
pasado, cuando en realidad la recompensa seguía sin canjear (estado
`disponible`, no `canjeada`): dos señales contradictorias a la vez.

`app/Views/public/cuenta/panel.php`: cuando hay una recompensa
`disponible` Y el saldo real es 0 (o sea, todavía no empezó a acumular
para la siguiente), el contador muestra la tarjeta **llena**
(`$umbral / $umbral`, barra al 100 %) con "¡Completaste tus 10 cafés!
Tu café gratis está arriba, listo para reclamar." Apenas el cliente
vuelve a comprar y el saldo deja de ser 0 (aunque la recompensa anterior
siga sin canjear), el contador muestra el progreso real hacia la
siguiente, con un texto que ya no dice "canjeaste".

Probado con un cliente de prueba: 0/10 antes de empezar → 10/10 exacto
al completar (con el texto de "completaste") → 3/10 real al sumar 3 más
con la recompensa aún disponible → sigue en 3/10, con el texto normal,
después de canjearla.

### 4.18 Sección "Promociones" en el sitio público

**Diagnóstico (antes de tocar nada)**: el código público ya existía y ya
estaba conectado — no era un caso de "nunca se conectó". `HomeController`
ya llamaba a `Promocion::listarVigentesAhora()` (ya filtraba por
`activo`, por rango de fechas, por día de la semana/hora si aplican, y
por que la categoría/producto al que apunta siga activo) y `home.php` ya
tenía una sección "Promociones de hoy" con esa lista. El verdadero
problema era que la tarjeta casi no mostraba nada — ni el descuento, ni
el 2x1, ni hasta cuándo era válida, ni ninguna foto (la tabla no tenía
columna para eso) — así que aunque una promoción sí apareciera, no se
veía como una promoción real y era fácil no notarla o dudar si de verdad
se estaba mostrando algo.

**No hay dos mecanismos separados** para "promoción normal" y "promoción
de temporada": las dos son filas de la misma tabla `promociones`: una
"de temporada" es simplemente una con `fecha_inicio`/`fecha_fin` cortos
(ej. una semana), y una "normal" tiene un rango más largo. El mismo
`listarVigentesAhora()` ya cubre ambas por igual — no hacía falta
duplicar nada. (Aparte, y sin relación: la tabla `temporadas` es para
**productos** de temporada, una sección distinta que ya existía en el
home — no confundir con esto.)

**Lo que se agregó de verdad:**
- **Foto opcional** en las promociones: columna `promociones.imagen`
  (`database/migrations/003_promociones_imagen.sql`), mismo mecanismo de
  subida que productos (`SubidaArchivo`, sin versiones WebP — no hacía
  falta esa complejidad para esto), en `public/uploads/promociones/`.
  Sin foto, la tarjeta se ve igual, solo sin imagen (nunca el marcador
  "📷 Foto pendiente" de `Vista::fotoOMarcador()` — ese es para productos,
  donde sí se espera una foto tarde o temprano; una promoción de texto,
  como un 2x1, no necesita una).
- **La tarjeta ahora sí comunica la promoción**: una insignia verde (el
  único acento del sitio, clase `.etiqueta-promocion`) con el beneficio
  en texto — "20% de descuento", "Q10 de descuento", "2x1", "Producto
  gratis" — más "Válido hasta 20 de noviembre" (`fecha_fin` en español,
  sin el año si es el año en curso).
- **Ubicación**: en el home, sección `id="promociones"`, entre "Productos
  de temporada" y "Menú" (ya estaba ahí; se mantuvo). Sin ninguna
  promoción vigente en este momento, la sección entera no se imprime
  (ya lo hacía el `if ($promociones !== [])`) — nada de espacio vacío
  ni mensaje de "no hay promociones".
- Reutiliza `.tarjetas-grid`/`.tarjeta-producto` (mismas tarjetas que
  Menú/Destacados/Temporada): 1 columna en celular, 2 desde 480px, 3
  desde 768px, 4 desde 1366px — responsive sin escribir CSS nuevo de
  diseño, solo el color de la insignia.

**Probado** con dos promociones de prueba reales (creadas por el
formulario del panel, con foto subida) y una tercera fuera de rango:
"Zz 15% en Frappés" (normal, del -10 al +60 días — categoría Frappés/
Batidos) y "Zz 2x1 de temporada" (temporada corta, del -2 al +5 días —
todo el menú) aparecieron las dos, con su foto, su insignia y su fecha;
"Zz Futura" (empieza en 30 días) no apareció. Antes de crear ninguna, la
sección no existía en la página. Revisado en escritorio y en celular
(390 px): sin desbordes, una columna, tarjeta completa. Las tres
promociones de prueba y sus fotos ya se borraron.

### 4.19 Temporadas visibles siempre; foto; aviso de lealtad en Mi cuenta

Tres pedidos que resultaron ser sobre **dos módulos distintos** del panel
que el negocio percibe como una sola cosa ("promociones"): `Promociones`
(descuentos/2x1 con fecha — §4.18) y `Temporadas` (agrupar productos por
campaña, ej. "Día de los santos"). Aclarado esto al confirmarlo con el
propio panel en vivo, cada punto resultó ser real pero en el módulo que
en verdad le correspondía:

**1) Por qué las temporadas no se veían — causa raíz real, encontrada con
datos ya existentes, no solo con una prueba armada:** la sección pública
"De temporada" dependía por completo de `$productosTemporada` (los
productos etiquetados con `Producto → pestaña Temporadas`). La temporada
"Día de los santos" ya estaba creada, activa y vigente en este momento,
pero **sin ningún producto etiquetado todavía** — `producto_temporadas`
vacía para ella —, así que la sección entera no imprimía nada: ni su
nombre, ni ninguna foto, como si la temporada no existiera. No era un
error de consulta ni de fechas: la consulta de vigencia
(`Temporada::listarVigentes()`) siempre estuvo bien: el diseño de la
vista simplemente nunca contempló mostrar la temporada por sí sola.
Arreglo: la sección ahora se basa en `$temporadas` (las vigentes en sí);
cada una se muestra como una tarjeta con nombre, descripción, foto y
"Válido hasta", y los productos (si ya hay alguno etiquetado) van debajo
en su grilla de siempre. Confirmado con "Día de los santos" real: apareció
sola, sin foto (no la tenía), apenas se desplegó este cambio.

**2) Vínculo con Lealtad — qué se implementó:** la casilla "Se puede
combinar con el programa de lealtad" (`promociones.combinable_con_lealtad`)
**ya existía y ya se guardaba correctamente** — se verificó creando una
promoción marcada (quedó en 1) y editándola sin la casilla (quedó en 0).
El problema no era de guardado: es que ese campo nunca alimentaba nada en
el resto del sistema (no había ni un solo `combinable_con_lealtad` fuera
del propio formulario). No existía ninguna lógica previa más específica
que seguir, así que se implementó lo más simple, tal como se pidió: cada
promoción vigente marcada aparece como un aviso — "🎁 Promoción especial:
[nombre] — [beneficio]. [descripción]. Válida hasta [fecha]." — debajo del
contador de cafés en "Mi cuenta" (`Promocion::listarVigentesCombinablesConLealtad()`,
que reutiliza `listarVigentesAhora()` y solo filtra por ese campo). No
toca `cafes_acumulados`, no genera ni cancela recompensas, no cambia
`LealtadService` — es puramente un aviso; el cajero sigue aplicando el
descuento/2x1 manualmente en la caja, igual que cualquier otra promoción.

**3) Foto en temporadas:** las temporadas nunca tuvieron columna `imagen`
(las promociones sí, desde §4.18). Se agregó igual que en promociones y
productos — misma clase `SubidaArchivo`, sin duplicar su validación —:
columna nueva (`database/migrations/004_temporadas_imagen.sql`), subida
en `TemporadaController`, campo en el formulario, miniatura en el listado,
y la foto se muestra en el banner de "De temporada" del sitio público.

**Probado** con una temporada de prueba (con foto, sin productos
etiquetados — a propósito, para repetir el caso de "Día de los santos")
y una promoción de prueba (con foto, marcada combinable con lealtad):
las dos aparecieron en el sitio público con su foto; la promoción
apareció también como aviso en "Mi cuenta" de un cliente de prueba, junto
con una promoción **real** que el propio negocio ya tenía marcada
combinable con lealtad ("2 ma 1") — confirma que el aviso ya funciona
con datos reales, no solo con los de prueba. Revisado también en
celular (390 px), sin desbordes. Los datos de prueba ya se borraron; los
reales (la temporada "Día de los santos" y la promoción "2 ma 1") se
dejaron intactos.

### 4.20 Cupón real para las promociones vinculadas a lealtad

El aviso de §4.19 era solo texto. Ahora una promoción "combinable con
lealtad" se puede **reclamar** como un cupón real, de un solo uso, con su
propio código de 8 caracteres y QR — el mismo mecanismo de canje que ya
usa el cajero para las recompensas de café gratis, pero es un concepto
aparte: no hay cafés acumulados de por medio, tabla propia
(`cupones_promocion`, §5.1), y el "premio" ya lo define la promoción
(descuento, 2x1…) — el cajero lo aplica en la caja al confirmar el canje,
no hay que elegir ningún producto.

- **Home → Lealtad**: una tarjeta de promoción marcada "combinable con
  lealtad" es un enlace a `/cuenta` (no a la sección `#lealtad`, que es
  la de bienvenida para quien todavía no tiene cuenta). Sin sesión,
  `ClienteAuthMiddleware` ya manda primero a `/cuenta/login` (con enlace
  a crear cuenta); al iniciar sesión, `CuentaController::iniciarSesion()`
  ya redirige a `/cuenta` — así que "primero pedir sesión, después
  Lealtad" salió de reutilizar exactamente lo que ya existía, sin
  ninguna redirección nueva que mantener.
- **Reclamar** (`CuponPromocionService::reclamar()`, cliente): genera el
  código con `Recompensa::nuevoCodigoAleatorio()` — el generador se
  reutiliza tal cual, solo cambia contra qué tabla se comprueba la
  unicidad — con el mismo reintento ante colisión (SAVEPOINT) que ya usa
  `LealtadService::crearRecompensaConCodigoUnico()`. Una vez por cliente
  y por promoción: lo garantiza el `UNIQUE (cliente_id, promocion_id)` de
  la tabla, no solo la comprobación previa — ante dos clics a la vez, el
  segundo choca ahí y se le muestra el cupón que el primero ya creó, no
  un error.
- **Mi cuenta**: por cada promoción vigente combinable, si el cliente ya
  tiene cupón se muestra su QR/código (igual que una recompensa) o, si
  ya lo canjeó/canceló, un aviso; si no lo tiene, un botón "Reclamar
  cupón".
- **Cajero — "Escanear código"**: ahora reconoce automáticamente **tres**
  tipos de código con el mismo campo/escáner (antes dos, §4.15): QR
  personal (sumar), recompensa (canjear café gratis) y, desde ahora,
  cupón de promoción (canjear). Se prueban en ese orden; el primero que
  exista gana. `LealtadController::confirmarCanjeCupon()` es la acción
  nueva; `CuponPromocionService::validarYCanjearCupon()` sigue exactamente
  el mismo orden de validación que `validarYCanjearRecompensa()`
  (existencia → estado → vencimiento) y el mismo candado (`FOR UPDATE`).
- **Vencimiento automático**: perezoso, igual que las recompensas — no
  hay una tarea programada marcando cupones vencidos por su cuenta; se
  revisa (contra `promociones.fecha_fin`, no una fecha propia del cupón)
  justo al intentar canjear, y ahí se marca `vencido` si ya pasó.
- **Admin**: `/admin/cupones`, historial separado del de Recompensas
  (mismo patrón de vista). Cancelar un cupón es **exclusivo de
  Administrador** (`requerirRol(1)`, no `(1, 2)` como el resto de
  Lealtad) — no devuelve nada (no hay cafés que devolver). Borrar una
  promoción con cupones ya reclamados se bloquea, igual que Temporadas
  con sus productos asociados.
- **Probado** el ciclo completo por HTTP con una promoción de prueba:
  clic en el home (enlace a `/cuenta`) → reclamar → código de 8
  caracteres sin 0/O/1/I/L → reclamar de nuevo lo rechaza (sigue habiendo
  un solo cupón) → el Cajero lo encuentra escribiéndolo en minúsculas (se
  reconoce como cupón, no como recompensa) → lo canjea → un segundo
  intento lo rechaza → aparece en `/admin/cupones` con el nombre de quien
  lo canjeó → el Cajero recibe 403 al intentar cancelar. Aparte: un
  cupón cancelado por el administrador no se puede canjear; un cupón
  disponible cuya promoción ya venció se marca `vencido` al intentar
  canjearlo y también se rechaza; una promoción con un cupón reclamado no
  se puede eliminar. Mientras tanto, el propio usuario real ya había
  reclamado un cupón de la promoción real "2 ma 1" desde su cuenta —
  quedó intacto, sin tocar.

### 4.21 El sitio público deja de verse plano: animaciones, WhatsApp real, TikTok y mapa automático

El sitio público no tenía ninguna transición ni animación (todo aparecía
de golpe), los botones de WhatsApp usaban el verde institucional del
sitio (no el de la marca) con un emoji en vez de su logo, no existía
forma de enlazar TikTok pese a que el negocio sí tiene cuenta, y el
campo de "Google Maps" tenía guardado el enlace corto de compartir
(`https://maps.app.goo.gl/...`) — que como código embed no es válido, así
que el mapa no cargaba nada.

- **Revelado al hacer scroll** (`inicializarRevelado()`,
  `public/assets/js/sitio.js`): `IntersectionObserver` nativo, sin
  librería — el mismo patrón que ya usaba `inicializarHeroFondo()` para
  pausar el fondo del hero fuera de pantalla. Las seis secciones (Menú,
  Nosotros, Galería, Promociones, Ubicación, Contacto) se localizan por
  su `id` ya existente; la clase `.por-revelar` la agrega el propio
  JavaScript, nunca el HTML, así que si JavaScript no llega a correr
  ningún contenido queda oculto (mejora progresiva). Cada sección se deja
  de observar (`unobserve`) en cuanto aparece una vez. Respeta
  `prefers-reduced-motion` (no se anima nada) y anima solo `opacity` y
  `transform` — sin reflow, sin pedir datos de red nuevos, así que no
  añade peso ni bloquea la carga.
- **Botones de WhatsApp** (`Vista::iconoWhatsapp()`, reutilizado en
  nav, portada y footer): verde oficial de la marca `#25D366` (hover
  `#1EBE57`), ya no el `--color-verde` institucional, con el logo real en
  SVG en línea (trazo de Simple Icons, mismo origen que los íconos de
  redes del §4.12 — sin depender de internet). El botón de la portada
  cambia su texto de "Escribir por WhatsApp" a un mensaje editable desde
  Configuración (`boton_whatsapp_texto`, por defecto "Ordena ahora" si se
  deja vacío); el de la barra de navegación se queda solo con el ícono
  entre 1024px y 1200px, donde los siete enlaces ya van justos. "Ver
  menú" se restyled para hacer pareja visual con el de WhatsApp (mismo
  padding/radio, fondo claro con borde en vez de un botón sin énfasis).
- **TikTok en Contacto**: el campo `instagram_usuario` del panel decía
  "Instagram / TikTok" pero no había ningún enlace de TikTok en ningún
  lado — se separa en dos campos reales: `instagram_usuario` (como
  estaba, ahora bien rotulado) y `tiktok_usuario` nuevo, con el mismo
  logo-en-SVG-en-línea que el resto de redes (`footer_publico.php`). El
  campo acepta usuario (`@algo`) o enlace completo, igual de flexible que
  Facebook.
- **Mapa de Ubicación, sin código embed que pegar**: el campo
  `google_maps_embed` cambia de sentido — ya no se imprime como HTML sin
  escapar (era el único campo del sitio con esa excepción); ahora guarda
  el enlace de **compartir** de Google Maps y se usa, siempre escapado,
  en un botón "Cómo llegar" (`target="_blank"`) para que el cliente trace
  su propia ruta. El mapa visual se genera solo, a partir de
  `direccion_texto` ya existente, con el formato sin API key
  `https://www.google.com/maps?q=DIRECCIÓN_CODIFICADA&output=embed`
  dentro de un `<iframe>` — nada que el administrador tenga que copiar o
  pegar a mano.
- **Nuevas claves de `configuracion_sistema`** (`boton_whatsapp_texto`,
  `tiktok_usuario`): como `actualizarMultiples()` solo hace `UPDATE` (no
  `INSERT ... ON DUPLICATE KEY`), no basta con agregarlas a
  `ConfiguracionController::CLAVES_EDITABLES` — hace falta la fila
  seed. Se agregaron al `INSERT` de `database/schema.sql` (para una
  instalación nueva) y, en la base de datos de desarrollo ya existente,
  con un `INSERT` directo de la misma fila: sin esto, guardar el
  formulario con esos campos vacíos habría parecido funcionar sin guardar
  nada.
- **Probado**: recorrido visual completo en escritorio y en 375px
  (menú hamburguesa incluido) — los dos botones de WhatsApp con logo y
  verde de marca, "Ver menú" a juego, el mapa cargando la zona real de
  Santa Cruz del Quiché desde `direccion_texto`, "Cómo llegar" apuntando
  al enlace real guardado, y el ícono de TikTok junto a WhatsApp/
  Instagram/Facebook (con un valor de prueba, revertido a vacío después
  de verificarlo — el administrador real aún no ha cargado su usuario de
  TikTok). Sin errores en consola. Pendiente de probar por HTTP el
  guardado del formulario de Configuración con los dos campos nuevos: no
  se pudo iniciar sesión de administrador en esta sesión de trabajo por no
  contar con la contraseña.

### 4.22 Corrige la sección de Menú invisible (§4.21)

`inicializarRevelado()` usaba `threshold: 0.15`, que exige que se vea el
15% de la **altura propia** de la sección observada antes de agregar la
clase `.revelado`. `#menu` mide unos 8800px (todo el catálogo, varias
pantallas de alto): ese 15% nunca llegaba a cumplirse con un scroll
normal, así que la sección se quedaba con `opacity: 0` para siempre — el
usuario reportó "no se ve el menú". Las demás secciones, más cortas, sí
funcionaban. Se bajó el umbral a `threshold: 0.01` (basta con que asome
el borde de la sección, sin importar cuánto mida); probado haciendo
scroll completo por las seis secciones sin que ninguna quede oculta.

### 4.23 Corrige el padding del botón de WhatsApp de la barra de navegación (§4.21)

`.sitio-nav-enlaces a` (da estilo a todos los enlaces del menú) trae
`padding: 4px 0` en escritorio y `10px 0` en móvil, y por tener más
especificidad que `.btn-whatsapp` ganaba, dejando el botón con 0px de
relleno horizontal — el ícono y el texto quedaban pegados al borde,
aunque el espaciado entre ellos y el centrado vertical ya estaban bien.
`.sitio-nav-enlaces .btn-whatsapp { padding: 8px 16px; }` (dos clases)
le gana esa pelea de especificidad; la regla del estado "solo ícono"
(1024–1200px, §4.21) sigue después en el archivo y sigue ganando ahí por
orden de aparición, así que ese caso no cambia. Probado en 375px, 1100px
(solo ícono) y 1400px (botón completo).

### 4.24 Botón flotante con el logo real y una burbuja que invita a escribir

El botón flotante de WhatsApp (el que sigue al visitante por toda la
página) seguía usando el emoji 💬 — quedó fuera del alcance de §4.21 por
error, ya que ahí solo se pidió cambiar los dos botones con texto (nav y
portada). Además no había nada que llamara la atención hacia él.

- **Logo real**: mismo `Vista::iconoWhatsapp()` que los otros dos
  botones (nav/portada), a 28px dentro del círculo de 56px.
- **Burbuja**: un enlace (no un globo decorativo — toda la burbuja abre
  WhatsApp, no solo el botón redondo) con una frase corta pensada para
  invitar a escribir, editable desde Configuración
  (`whatsapp_flotante_texto`, nueva clave); vacío usa el valor por
  defecto "¿Tienes alguna duda? ¡Escríbenos! 👋". Aparece con una
  animación de entrada (`@keyframes`, un segundo después de cargar la
  página, para no competir con el resto del contenido) que respeta
  `prefers-reduced-motion`; se oculta bajo 360px de ancho, donde botón +
  burbuja ya no caben junto al margen de 20px.
- **Nueva clave de `configuracion_sistema`**: mismo caso que
  `boton_whatsapp_texto`/`tiktok_usuario` en §4.21 —
  `actualizarMultiples()` solo hace `UPDATE`, así que se sembró la fila
  en `database/schema.sql` y en la base de datos de desarrollo.
- **Probado**: escritorio y 375px, con un valor de prueba en la base de
  datos (revertido a vacío después) para confirmar que el campo se
  refleja en el sitio y enlaza al número de WhatsApp correcto. Sin
  errores de consola.

### 4.25 El formulario de Configuración no guardaba nada: el horario reenviaba segundos que la validación rechazaba

El administrador reportó tres síntomas a la vez en Configuración: un
aviso técnico sobre la extensión GD visible para cualquiera, un error
rojo de "hora con formato inválido" en los 7 días del horario sin haber
tocado nada, y los campos de texto de WhatsApp (§4.21, §4.24) que
"decían que guardaron" pero nunca cambiaban en el sitio público.

- **Causa raíz de los tres**, en realidad una sola: `horarios_atencion`
  guarda columnas `TIME`, que PDO/MySQL devuelven como `"09:00:00"` (con
  segundos). El formulario imprimía ese valor tal cual en el `value` de
  cada `<input type="time">`. Un navegador que recibe un `value` con
  segundos en ese atributo los conserva internamente y los **reenvía tal
  cual al guardar** (`"09:00:00"`, no `"09:00"`) — confirmado
  reproduciéndolo en un `<input>` de prueba aislado. La validación del
  servidor (`validarHorario()`) solo acepta `HH:MM` exacto, así que
  **todo envío del formulario grande** (sin importar qué campo se haya
  cambiado) disparaba el error de formato en los 7 días, y
  `ConfiguracionController::actualizar()` corta antes de llamar
  `actualizarMultiples()` en cuanto hay un solo error — por eso ningún
  campo se guardaba nunca, incluidos los de WhatsApp, aunque el horario
  real ya almacenado coincidía exactamente con el que el negocio dio
  (lunes a viernes 9:00–14:00 y 16:00–21:00, sábado 17:00–21:00, domingo
  9:00–14:00 y 17:00–21:00): no hizo falta corregir datos, solo el
  recorte a `HH:MM`, tanto al imprimir el `value` (formulario.php) como,
  defensivamente, al leer `$_POST` (`leerHorarioDesdePost()`).
- El aviso de GD se sigue mostrando, pero solo con `APP_DEBUG` (falso en
  producción, `config/config.php`) — nunca lo ve el administrador real.
- **Probado por reflexión** (sin sesión de administrador disponible):
  se invocaron directamente `leerHorarioDesdePost()`, `validarHorario()`
  y `validar()` de `ConfiguracionController` con un `$_POST` simulado
  igual al que el navegador ya arreglado enviaría — cero errores, contra
  7/7 errores al simular el envío viejo (con segundos), reproduciendo el
  bug reportado exactamente. Aparte, se escribió un valor de prueba
  directamente con `Configuracion::actualizarMultiples()` (el mismo
  método que usa el controlador) para los dos campos de WhatsApp y se
  confirmó que aparecían en la página principal; se revirtió a vacío
  después.

### 4.26 El sitio no cargaba en PHP 8.0.30: "Composer detected issues in your platform"

En una máquina con PHP 8.0.30 el sitio mostraba: *"Your Composer
dependencies require a PHP version >= 8.1.0. You are running 8.0.30."*
(`vendor/composer/platform_check.php`, generado por Composer).

- **Causa raíz**: `composer.json` solo pedía `chillerlan/php-qrcode: ^5.0`
  (RF-06/RF-02, QR de recompensas y cupones, §4.9). Esa librería en sí
  acepta PHP `^7.4 || ^8.0`, pero depende de
  `chillerlan/php-settings-container` con el rango
  `^2.1.6 || ^3.2.1`; al no fijar una versión propia, Composer resolvió
  la más nueva dentro de ese rango, `3.3.0`, que exige PHP `^8.1` — ni el
  código del proyecto ni el de `php-qrcode` usan nada de 8.1, era
  puramente esta dependencia transitiva.
- **No había sintaxis de PHP 8.1 en el código propio**: se revisó
  `app/` completo buscando `readonly`, `enum`, tipo de retorno `never`,
  `array_is_list()` y los enums nativos (`BackedEnum`/`UnitEnum`) — cero
  coincidencias. El proyecto nunca necesitó 8.1.
- **Arreglo**: se fijó `chillerlan/php-settings-container` a la rama
  `^2.1.6` (la otra mitad del rango que acepta `php-qrcode`; la rama
  `3.2.x` se probó primero pero su último tag, pese a ser un parche
  "3.2.1", también quedó con `php: ^8.1` en el repositorio real —
  aunque el índice resumido de Packagist no lo reflejaba, así que no
  bastaba con mirar ese resumen). Se agregó además `"php": "^7.4 || ^8.0"`
  explícito en `composer.json`, para que el propio proyecto declare su
  piso mínimo real en vez de heredarlo en silencio de lo que Composer
  resuelva. Con `composer update`, `platform_check.php` quedó pidiendo
  `PHP_VERSION_ID >= 70400` (7.4.0), no 80100.
- **Sin pérdida de funcionalidad**: se generó un QR de prueba
  (`CodigoQr::dataUri()`) con la librería ya downgradeada — SVG válido,
  20 590 caracteres en base64, igual que antes.
- **Piso de PHP del proyecto: 7.4 / 8.0+** (no exclusivamente 8.0 — el
  rango declarado, `^7.4 || ^8.0`, es el mismo que ya traía
  `chillerlan/php-qrcode` por su cuenta). Compatible con XAMPP local
  (PHP 8.2.12 en esta máquina de desarrollo) y con la máquina de PHP
  8.0.30 que reportó el error. **Pendiente de confirmar con el
  administrador la versión de PHP en Hostinger (producción)** — no hay
  forma de saberlo desde este entorno; si ahí corre 8.1+ igual funciona
  sin cambios (el rango solo pone un piso, no un techo).

---

### 4.27 Lealtad: se quita Cancelar/Anular; un solo mecanismo de corrección (Ajuste manual)

**Por qué.** "Cancelar" una recompensa (§4.11) y el estado `anulado` de un movimiento eran dos
caminos de corrección sin una regla de negocio coherente, y el primero permitía regenerar códigos.
Queda **un solo** mecanismo: el Ajuste manual.

- Se eliminó la ruta `POST /admin/recompensas/{id}/cancelar`, `RecompensaController::cancelar`,
  `LealtadService::cancelarRecompensa`, `Recompensa::marcarCancelada` y `buscarPorIdConBloqueo`, y
  los botones de la ficha del cliente y de la lista de Recompensas. (Los valores `cancelada` /
  `anulado` siguen existiendo en `schema.sql` para no tocar datos históricos; ya nada los asigna
  salvo el caso de abajo.)
- **Ajuste manual: solo Administrador** (`registrarAjuste` usa `requerirRol(1)`; el Cajero ni ve la
  tarjeta) y **motivo obligatorio**.
- **Registrar consumo desde la ficha del cliente** exige motivo (en `LealtadService::registrarConsumo`).
  El escáner (§4.29) no lo pide: guarda "Escaneo de QR personal".
- **Ajuste negativo con saldo insuficiente.** Con 20 cafés el saldo es 0 y hay 2 recompensas
  disponibles; "restar 1" fallaba. Ahora `registrarAjuste` deshace las recompensas **disponibles**
  que haga falta (las más recientes primero): por cada una inserta un movimiento
  `recompensa_cancelada` (+umbral) y la marca `cancelada`, y luego aplica el ajuste
  (20 → restar 1 → 9/10 con 1 recompensa). Si esas recompensas ya se canjearon, se rechaza.
  Todo ocurre dentro de la transacción de `conCandado()`.
- **Auditoría** (`/admin/auditoria`, solo Administrador): `consumo` y `ajuste` guardan cantidad,
  motivo y recompensas deshechas; la lista muestra el **nombre del cliente** (JOIN con `clientes`
  cuando el módulo es `lealtad_movimientos`), el detalle y la acción "Ajuste manual" tiene filtro.

### 4.28 Contador de la tarjeta: 10/10, 20/20, 30/30 con "café(s) gratis disponible(s)"

Al completar el umbral el saldo real vuelve a 0 aunque las recompensas sigan sin canjear; mostrar
"0 / 10" en ese momento confundía. `Cliente::progresoTarjeta()` decide qué mostrar, con la misma
regla en el panel (búsqueda de Lealtad, lista de Clientes, ficha, escáner) y en "Mi cuenta":

| Situación | Se muestra |
|---|---|
| Saldo 0 y N recompensas disponibles | `N×umbral / N×umbral` (10/10, 20/20, 30/30) + etiqueta "N café(s) gratis disponible(s)" |
| Saldo parcial (ej. 25 cafés = 2 recompensas + 5) | `5 / 10` + etiqueta "2 cafés gratis disponibles" |
| Sin recompensas | saldo real |

`Cliente::buscar/buscarPorId/buscarPorCodigoQr/buscarPorTelefono` traen `recompensas_disponibles`
con una subconsulta (`SELECT_CON_RECOMPENSAS`). El saldo guardado **no** cambia.

### 4.29 Escáner del cajero: teléfono, recarga limpia y cámara en local

- **Búsqueda por teléfono.** Sin código, el teléfono identifica al cliente como si hubiera
  escaneado su QR personal (`LealtadController::identificarPorTelefono`): suma cafés y lista sus
  recompensas y cupones por canjear (`CuponPromocion::listarDisponiblesDeCliente`). Mismos permisos
  y auditoría. El botón "Canjear" de la ficha usa esa misma vía.
- **Post/Redirect/Get.** Tras cada POST, `vistaCanjear()` guarda el resultado en
  `$_SESSION['_canje']` y redirige; `mostrarCanjear()` lo consume **una sola vez**. Recargar o volver
  atrás deja la pantalla limpia (antes repetía la búsqueda y dejaba el cupón equivocado). Cada
  resultado tiene "✕ No es este / limpiar pantalla".
- **Cámara en desarrollo.** `getUserMedia` exige HTTPS o `localhost`. `elcharco.local` no cuenta,
  pero **cualquier `*.localhost` sí**: el Virtual Host (`httpd-vhosts.conf`) tiene
  `ServerAlias elcharco.localhost`. Para probar el escáner en local se entra por
  `http://elcharco.localhost/admin`; en producción (HTTPS) el navegador pide el permiso solo.
  `public/index.php` envía `Permissions-Policy: camera=(self), microphone=(), geolocation=()`.

### 4.30 Teléfono de exactamente 8 dígitos y eliminar clientes

- `Cliente::telefonoValido()` (`^\d{8}$`) se valida en el **servidor** en el registro de la cuenta
  (`CuentaController::registrar`) y en el alta/edición del panel (`ClienteController::validar`).
  Los `<input>` llevan `maxlength=8`, `inputmode=numeric`, `pattern` y `data-solo-digitos`
  (admin.js / sitio.js quitan lo que no sea número al escribir o pegar).
- **Eliminar cliente** (`POST /admin/clientes/{id}/eliminar`, solo Administrador, con confirmación
  en la lista y en la ficha): `Cliente::eliminarConHistorial()` borra cupones, recompensas,
  movimientos y el cliente en **una transacción** (en ese orden, por las llaves foráneas). Queda en
  Auditoría con los datos previos y el conteo de lo borrado, **sin el hash del PIN**.

### 4.31 Sitio público: descripciones, animaciones de bienvenida, logo e "Inicio"

- **Descripciones de producto** más grandes (1.02 rem) y con aparición animada
  (`inicializarRevelaDescripciones`). Los textos salen del menú físico por categoría y sabor
  ("Puré de fresa triturada con yogurt griego, jugo de manzana y hielo", "Soda neutra con syrop de
  lavanda y hielo"…); lo que el menú físico no describe (sándwiches, chocolates) se dejó vacío.
- **Entrada suave** (`.por-revelar` / `.revelado`, IntersectionObserver): todas las secciones y las
  tarjetas de las primeras secciones suben al aparecer; las fotos de las tarjetas suben **cuando ya
  cargaron** (`inicializarRevelaFotos`, clase `.img-por-cargar`). El hero anima título, texto y botones
  con CSS puro. Todo respeta `prefers-reduced-motion` y, sin JavaScript, el contenido queda visible.
- **Logo** de 60 px (48 px en móvil) por requerimiento del cliente.
- **"Inicio"** (logo, título, enlace) lleva al tope: `#inicio { scroll-margin-top: 400px }` recorta el
  destino a 0 (antes la barra fija tapaba la parte alta del hero).

### 4.32 Menú en el teléfono: fila deslizable por categoría + barra de pestañas fija

Solo bajo 768 px (en escritorio el menú es la cuadrícula de siempre).

- Cada categoría es una **fila horizontal** con `scroll-snap` (sin barra visible; la tarjeta
  siguiente asoma). Las categorías siguen una debajo de otra.
- La barra de categorías se convierte en **pestañas fijas** bajo el encabezado
  (`inicializarMenuPestanas`): tocar una baja suave hasta esa categoría, **al desplazarse se marca
  sola la que se está viendo** (scrollspy) y la barra se centra en ella. La activa va en verde. Las
  categorías salen del HTML del servidor (BD), nada hardcodeado. Se enciende/apaga también al
  cambiar el tamaño de la ventana (`matchMedia` + `resize`).
- Se descartó un filtro que ocultara las demás categorías: se prefirió ver todo y navegar.

### 4.33 Ubicación: el mapa se centra donde apunta el enlace de Google Maps

- La búsqueda por texto de la dirección caía en la zona 4 de la Ciudad de Guatemala. Ahora
  `GoogleMaps::datosDesdeEnlace()` abre el enlace "Compartir" que pega el Administrador (solo
  `https` y dominios de Google) y guarda **coordenadas** (`mapa_coordenadas`), **id de la ficha**
  (`mapa_lugar_id`) y **nombre** (`mapa_lugar_nombre`) en `configuracion_sistema`.
- El mapa usa el formato oficial de "Insertar un mapa" con la ficha del lugar (nombre, calificación,
  rutas), igual a lo que abre "Cómo llegar". Al guardar un enlace nuevo en Configuración todo se
  actualiza solo (salvo que se escriban coordenadas a mano; entonces es un pin simple). Si el enlace
  no se puede leer, se conservan los datos anteriores y se avisa.
- Botón "Cómo llegar" rediseñado con la paleta del sitio (café/beige, pin en verde).

### 4.34 Carrito de compras con pedido por WhatsApp

Sin pasarela de pago. Archivos: `app/Views/public/_carrito.php` (panel), `public/assets/js/carrito.js`,
estilos al final de `sitio.css`, botón en `layouts/header_publico.php` (solo si la vista define
`$mostrarCarrito`), botones "Agregar" en `$tarjetaProducto` de `home.php`.

- **Tarjeta:** selector de cantidad (− 1 +) y un solo "+ Agregar". El catálogo (nombre completo,
  precio, versiones y temperatura) sale de los atributos `data-*` del botón, así que **siempre
  coincide con el menú**; lo guardado en el navegador solo trae claves y cantidades.
- **Nombres completos** (`Vista::variantesPedido`): frappé/batido ("Frappé de Galleta oreo" /
  "Batido de Galleta oreo", derivado de la categoría "A / B" y del segundo precio); el resto,
  "Fresa (Smoothies)", "Fresa (Lassie)". La versión (Frappé/Batido) y la temperatura se eligen
  dentro del carrito o en la tarjeta (§4.36).
- **Panel:** cantidades, quitar, subtotales y total; "Tus datos": **nombre y dirección
  obligatorios**, detalles extra opcionales; **forma de pago** informativa (Pago contra entrega,
  Transferencia, Tarjeta física). Si falta algo, el botón de enviar dice qué y marca el campo.
  La lista se desplaza dentro del panel y el total + "Enviar" quedan fijos abajo (probado de 360×640
  a 1280×600 y teléfono de lado).
- **Mensaje:** `wa.me/<número configurado>?text=…` con una línea por producto, total y forma de
  pago en *negrita* (sintaxis de WhatsApp), nombre, dirección y detalles. Se quitan `* _ ~ \``
  de lo que escribe el cliente.
- **Después de enviar** (el sitio no puede saber si el mensaje salió): el panel pregunta
  "¿Ya enviaste tu pedido?" (también al volver a la pestaña). "Sí" vacía el carrito, el pago y los
  detalles y conserva nombre y dirección; "No" lo deja intacto. Un carrito olvidado **12 horas** se
  vacía solo.

### 4.35 Horario de atención: un solo formato y aviso con la próxima apertura

- `HorarioAtencion::textoDia()` arma el texto de un día ("9:00 a 14:00 y 16:00 a 21:00"); lo usan el
  pie de página y el carrito. `HorarioAtencion::paraCarrito()` entrega los turnos en minutos.
- El carrito compara con la hora **de la zona del negocio** (`APP_TIMEZONE`), no con la del teléfono,
  e incluye el segundo turno. Fuera de horario muestra "En este momento no estamos atendiendo, pero
  tu pedido sí se puede enviar", **cuándo vuelven a atender** (hoy / mañana (día) / el <día>, mirando
  hasta 7 días) y el horario de hoy. **El horario nunca bloquea el envío**; el mensaje de WhatsApp lleva
  una línea "fuera del horario".
- Se lee de la BD en cada carga: lo que cambie el Administrador en Configuración se refleja solo.

### 4.36 Temperatura de las bebidas (caliente / frío)

`TemperaturaProductos` guarda `{id_producto: ambas|caliente|frio}` como JSON en
`configuracion_sistema.temperaturas_productos` y se edita en **Productos → Temperatura**.
**No es una columna de `productos`** porque la cuenta de la aplicación (`elcharco_app`) solo tiene
SELECT/INSERT/UPDATE/DELETE (sin `ALTER`); si más adelante se prefiere columna, bastaría una migración
y leerla en vez del JSON.

- `ambas`: la tarjeta muestra "Caliente | Frío" y **exige elegir** antes de agregar; también se puede
  cambiar en el carrito (si ya existe la otra, las cantidades se suman).
- `caliente` / `frio`: entra con esa temperatura. El mensaje lleva "Moka - Caliente", "Affogato - Frío".
- Cargado según el menú físico (Americano, Latte suave, lattes saborizados, Chai y Taro: ambas;
  Espresso, Cappuccino, Mint, Pistachio y chocolates: caliente; Affogato y Carajillo: frío).

### 4.37 Dashboard: tarjetas que llevan a su sección

Clientes → `/admin/clientes`; Con recompensa disponible → `/admin/recompensas?estado=disponible`;
Productos activos → `/admin/productos`; Promociones vigentes → `/admin/promociones`. El Cajero solo ve
las dos primeras (las otras son de Administrador). Flecha en la esquina en lugar de texto.

### 4.38 Pendientes y datos por revisar (a octubre de 2026)

- Fotos reales de productos, galería (mínimo 6) y portada: siguen marcadas como pendientes.
- El producto "Mantequilla de maní" tiene precio Q22 en el sistema; el menú físico dice Q30 (con café)
  / Q25 (sin café).
- Existe una categoría "Categoria de prueba" que conviene borrar.
- Las descripciones y la temperatura de los productos se cargaron desde fotos del menú físico: revisar
  contra la carta vigente.
- Respaldo: el proyecto vive solo en esta computadora; falta un remoto de Git o copia externa.
- ~~Aplicar `006_columnas_precio_y_cantidad.sql`~~ — aplicada el 13/10/2026 (ver §4.46).
- Reiniciar MySQL para que tome efecto el `sql_mode` estricto de `my.ini` (la aplicación ya lo fuerza por conexión, ver §4.39).

### 4.39 Ronda de corrección de bugs previa al despliegue (QA, 2 de octubre de 2026)

Revisión completa (sitio público + panel) con usuarios de prueba temporales, que se borraron al terminar.
Se encontraron 10 problemas, más uno extra descubierto durante las pruebas; se corrigieron en cuatro commits:
**Bloque 1 `909b375`**, **Bloque 2 `425740f`**, **Bloque 3 `1d00b69`** y **Configuración `47ae530`**.

**Bloque 1 — críticos (`909b375`)**

- **Precios** (`Validator::precio()`; `ProductoController`, `PromocionController`): solo números con hasta
  2 decimales, sin notación científica ni comas, entre Q0.01 y `Producto::PRECIO_MAXIMO` (Q500). Aplica al
  precio, al precio alterno y al valor de los descuentos (el porcentual, máximo 100). Antes MariaDB redondeaba
  o recortaba en silencio (`99999999` → `9999.99`, `12.34567` → `12.35`, `1e3` aceptado como 1000).
- **Cantidades de lealtad** (`LealtadService::leerCantidad()`): entero (rechaza `2.5` en vez de truncarlo),
  máximo `MAX_CAFES_POR_CONSUMO` = 30 por consumo y `MAX_CAFES_POR_AJUSTE` = 200 por ajuste. Se valida en el
  servidor (ficha, escáner y ajuste); los `max` del HTML son solo ayuda visual. Antes un consumo de 40000 se
  guardaba como 32767 (límite del `SMALLINT`) mientras el saldo se calculaba con 40000, y el historial quedaba
  descuadrado (suma −7233 contra saldo 0). No había registros reales afectados.
- **Fechas** (`Validator::fecha()`; promociones y temporadas): calendario real (rechaza `2026-02-30`, `ayer`
  y `0000-00-00`) y el inicio no puede ser anterior a hoy. Al **editar** una que ya empezó, si su inicio no
  cambia no se exige que sea futuro (si no, no se podría ni renombrar una promoción vigente).
- **Modo SQL estricto**: `Database.php` ejecuta `SET SESSION sql_mode='STRICT_TRANS_TABLES,…'` en cada
  conexión (desarrollo y producción se comportan igual, aunque el servidor esté configurado distinto) y se
  puso también en `C:\xamppn\mysql\bin\my.ini` (requiere reiniciar MySQL). Con el modo estricto salieron
  validaciones que faltaban y que daban error 500: etiqueta del precio alterno (40), descripciones de
  promociones y temporadas (255), correo (150) y notas (255) de clientes, motivo de lealtad (255), `orden`
  (0–9999), `prioridad` (−100–100), umbral de lealtad (entero 1–100), coordenadas y enlace del mapa. Además,
  `ClienteController` y `UsuarioController` mostraban **cualquier** error de BD como "ya existe ese teléfono/
  usuario"; ahora solo el error 1062 (llave duplicada) lo es y los demás no se disfrazan.
- **Columnas** (`database/migrations/006_columnas_precio_y_cantidad.sql`, `schema.sql` ya actualizado):
  `productos.precio/precio_alt` → `DECIMAL(10,2)` y `lealtad_movimientos.cantidad` → `INT`. **Pendiente de
  aplicar** con un usuario administrador de MySQL (la cuenta `elcharco_app` no tiene `ALTER`); mientras tanto la
  validación impide valores que no caben en las columnas actuales.
- **Tarjetas de producto** (`sitio.css`): la fila "cantidad + Agregar" ya no se sale de la tarjeta (en 320 px y
  a 768 px afectaba a 52 de 53). Carrusel móvil al 82 % del ancho, la fila baja el botón si no cabe
  (`flex-wrap`) y la cuadrícula pasa a 3 columnas desde 900 px (2 columnas de 480 a 899 px).

**Bloque 2 — importantes (`425740f`)**

- **Cuenta fantasma** (`ClienteAuthMiddleware`, `ClienteAuth::clienteExiste()`/`expulsarPorCuentaInexistente()`,
  `CuentaController::panel`): si el Administrador elimina a un cliente que tiene la sesión abierta, se cierra
  su sesión y va al login con "Tu cuenta ya no está disponible". Antes `/cuenta` mostraba una cuenta vacía y
  escribía 3 warnings por visita (`panel.php:18`, `Cliente.php:29`, `panel.php:46`).
- **Borrador de formularios** (`App\Core\BorradorFormulario`, `CsrfMiddleware`, `AuthController`,
  `panel_footer.php`, `admin.js::restaurarBorrador()`): cuando un envío del panel se rechaza por **token
  vencido** o por **sesión expirada**, lo escrito se guarda 30 minutos en la sesión y el usuario vuelve a la
  **misma pantalla** (el `Referer` si es de este sitio y del panel; si no, la propia pantalla en formularios de
  alta/edición/configuración) con los campos rellenados. Si la sesión expiró, tras volver a iniciar sesión
  regresa a ese formulario y no al Dashboard. El borrador se entrega una sola vez; `admin.js` solo rellena el
  formulario cuyo `action` coincide con el envío rechazado (la ficha de Lealtad tiene dos formularios con
  campos del mismo nombre). Nunca se guardan contraseñas, PIN ni tokens, y el login no genera borrador. Los
  archivos no se pueden conservar: el aviso pide elegirlos otra vez.

**Bloque 3 — menores (`1d00b69`)**

- **`App\Core\Entrada::texto()`**: lee un parámetro como texto; si llega como arreglo (`?q[]=a`) se trata como
  entrada inválida (valor por defecto) y cada formulario lo rechaza con su mensaje, en vez de "Array to string
  conversion". 55 usos en 13 controladores (también el horario de Configuración, que llega anidado).
- **Redacción**: `LealtadService::textoRecompensasGeneradas()` → "¡Se generó 1 recompensa! Código: X." o "¡Se
  generaron N recompensas! Códigos: …" (antes "Se generóron"), también en los ajustes.
- **Categorías únicas**: `Categoria::existeNombre()` (sin distinguir mayúsculas ni acentos: colación `_ci`) al
  crear y al editar; guardar una categoría con su propio nombre sí se permite.
- **Botones táctiles de al menos 44 px**: menú hamburguesa, − / + de las tarjetas y del carrito,
  Frappé/Batido, Caliente/Frío, quitar y vaciar. La fila cantidad + Agregar queda en una línea desde 360 px
  (`flex: 1 1 98px`) y baja el botón en pantallas más angostas.
- **Anclas de categoría** (`#cat-cafe`, `#menu`…): `sitio.css` usa `scroll-margin-top: calc(var(--alto-nav) +
  var(--alto-pestanas) + 8px)` (valores de respaldo en `:root`) y `sitio.js` mide las alturas reales de las
  barras fijas (`inicializarAnclas()`, `medirBarras()`). El navegador salta antes de que carguen imágenes y
  fuentes y el destino quedaba desfasado ("a veces el desplazamiento no ocurre del todo"): al terminar de cargar
  se vuelve a alinear una vez (solo si la persona no se ha movido) y la pestaña activa se pone al día. Los 8 px
  son la misma holgura del scrollspy, para que el título del destino no quede marcado como la categoría anterior.

**Configuración — un envío incompleto ya no borra los ajustes (`47ae530`)**

Bug descubierto durante las pruebas: `ConfiguracionController::actualizar()` tomaba cada ajuste que no venía
en el envío como "vacío" y lo guardaba así (nombre, WhatsApp, dirección, mapa, redes y los 7 horarios).
Ahora solo se actualiza **lo que realmente llega**:

- Un ajuste de texto ausente del envío (`array_key_exists`) no se toca; un día de horario ausente tampoco
  (`leerHorarioDesdePost()` solo devuelve los días presentes; un día enviado sin la casilla "abierto" cuenta
  como cerrado, que es como lo manda el formulario real).
- Un envío sin ningún dato se rechaza ("no se cambió nada"); con **cualquier** error de validación no se guarda
  nada, ni lo válido del mismo envío. Nombre del sitio y umbral de lealtad no pueden ir vacíos; un campo con
  formato inválido (arreglo) rechaza el envío completo.
- Al rechazar, la pantalla vuelve a mostrar lo guardado (más lo recién escrito), no campos en blanco.
- Si el envío no traía algunos ajustes, el aviso de éxito los lista ("se dejaron como estaban").
- Se probó con un formulario al que se le quitaron 12 campos y todo el horario, con envío vacío, campos
  obligatorios vacíos, arreglos, horario parcial y el formulario completo: la configuración y los horarios
  quedaron idénticos (huella comparada antes y después). Se hizo un respaldo previo de ambas tablas.

**Cómo se probó la ronda**: scripts HTTP contra el sitio local (sesión, CSRF, formularios y subidas de archivo)
y mediciones en el navegador a 320–1440 px. Hubo un incidente: una prueba de Configuración con un formulario
incompleto vació los ajustes (era el bug de arriba); se restauraron al instante desde el respaldo completo hecho
antes de empezar y se corrigió de raíz en `47ae530`.

### 4.40 Pedido por WhatsApp: aviso de ubicación (`73080b0`)

- Antes del botón de enviar, el carrito muestra un aviso (por defecto: «Después de enviar tu pedido,
  mándanos tu ubicación por WhatsApp para poder enviarlo.») y una casilla obligatoria **Entendido**.
  Mientras no esté marcada el botón queda deshabilitado (`aria-disabled`, aspecto apagado, y el clic
  explica por qué). La casilla **no se guarda**: se reinicia al enviar, al confirmar «ya lo envié» y al
  vaciar el carrito. El horario sigue siendo solo informativo y nunca bloquea el envío.
- El mensaje de WhatsApp termina con la línea «Te enviaré mi ubicación a continuación.».
- El texto del aviso se edita en Configuración → «Carrito de pedidos» (clave `carrito_aviso_ubicacion`,
  máximo 300 caracteres; vacío = texto por defecto, `Configuracion::AVISO_UBICACION_DEFECTO`). Si la fila no
  existe se crea al guardar. `database/migrations/007_carrito_aviso_ubicacion.sql` solo hace un `INSERT IGNORE`
  (no necesita `ALTER`) y ya está aplicada en esta base.

### 4.41 PIN del cliente: restablecer en persona y bloqueo por intentos (`edf136b`)

**Restablecer PIN** (`LealtadController::restablecerPin`, ruta `POST /admin/lealtad/{id}/restablecer-pin`,
Administrador y Cajero). Solo en persona: no hay ninguna ruta pública de recuperación, ni correo ni WhatsApp.

- Genera un PIN temporal aleatorio de 4 dígitos (`random_int`) y lo muestra **una sola vez**, en la respuesta
  directa al POST con `Cache-Control: no-store`. No se guarda en sesión ni en base en claro (solo el hash), y ni el
  log ni Auditoría lo reciben. La ficha trae el recordatorio «Confirma la identidad del cliente (nombre y
  teléfono) antes de restablecer.» y un `confirm()`. Un cliente sin cuenta (sin PIN) no se puede restablecer.
- **Cómo se marca como temporal sin agregar columnas** (la cuenta `elcharco_app` no tiene `ALTER`): el hash va en
  la misma columna `pin_hash` con el prefijo `temporal:` (`Cliente::pinEsTemporal/hashDelPin`). Al fijar el PIN
  nuevo la columna vuelve a un hash normal y el temporal deja de existir. Si más adelante se quiere una columna
  `pin_temporal` propia, basta migrar esos valores.
- **Cierre de sesiones**: al entrar, la sesión del cliente guarda una huella del `pin_hash` vigente
  (`Cliente::huellaPin`, `$_SESSION['cliente_huella']`). `ClienteAuth::validarSesion()` (en
  `ClienteAuthMiddleware`) la compara en cada página: si el PIN cambió, esa sesión se cierra con el aviso «Por
  seguridad cerramos tu sesión porque tu PIN cambió». Las sesiones abiertas antes de este cambio no traen huella y
  se cierran una sola vez.
- **Cambio obligatorio**: iniciar sesión con el temporal no abre la cuenta; deja un estado pendiente
  (`$_SESSION['cliente_cambio_pin']`, también con huella) y redirige a `/cuenta/cambiar-pin`: 4 dígitos, con
  confirmación y distinto del temporal. Si el PIN se restablece otra vez o ya se cambió, la pantalla pendiente
  deja de valer. Un temporal ya cambiado no vuelve a entrar.
- **Auditoría**: fila `tabla_afectada = 'clientes'`, `accion = 'editar'`, `valores_despues = {"accion":
  "restablecer_pin"}`; quién = `usuario_id`, a qué cliente = `registro_id`, cuándo = `creado_en`. (El `ENUM` de
  `accion` no permite un valor nuevo sin `ALTER`.) La pantalla de Auditoría lo muestra como «Restableció el PIN…»
  y ahora también resuelve el nombre del cliente para `clientes`.
- `/cuenta/login` dice «¿Olvidaste tu PIN? Acércate a la cafetería y te ayudamos a restablecerlo.» sin enlaces, pero
  **solo después de más de 5 fallidos seguidos** (`$_SESSION['cliente_login_ayuda']`, se borra al entrar bien).
  Mientras hay bloqueo, el aviso descuenta los segundos en vivo (JS con el reloj, no resta de uno en uno), deshabilita
  «Entrar» y al llegar a 0 muestra «Ya puedes intentar de nuevo.».

**Bloqueo por intentos fallidos** (`ClienteAuth::segundosDeBloqueo`, `IntentoLogin::rachaDeFallos`). Ya existía un
bloqueo de 15 minutos por cuenta tras 5 fallos; se reemplazó por uno escalonado por **teléfono + IP**:

- 5 fallidos seguidos → 20 s; otros 5 (y los siguientes) → 60 s (el máximo). Se deduce del historial de
  `intentos_login`, sin columnas nuevas. Mientras dura el bloqueo los intentos se rechazan sin registrarse (ni
  siquiera con el PIN correcto), así que el conteo no se desplaza.
- La racha se reinicia al entrar bien o tras 30 minutos sin fallidos. El mensaje muestra los segundos reales que
  faltan. Un teléfono que no existe se bloquea igual (no revela si hay cuenta).
- Se conserva el límite general existente de 20 fallidos por IP en 15 minutos (protege contra rotar teléfonos);
  puede devolver «Espera unos minutos» después de unas 4 rondas de bloqueo desde la misma IP.

### 4.42 «Sobre nosotros» editable por partes, con foto propia

Antes la sección se guardaba como **un solo texto** (`sobre_nosotros_texto`) que un analizador (`TextoLargo`, commit
`ed1ef40`) interpretaba al mostrarlo, y la foto salía sola de la galería. Ahora el panel controla cada parte.

- **Diagnóstico previo**: no existía ningún campo de foto para esta sección. La única subida de Configuración era el
  logo (`SubidaArchivo::guardarImagen` → `uploads/sitio`); la portada tiene su propio módulo. Se reutilizó ese mismo
  servicio, carpeta, validaciones (extensión, MIME real, `getimagesize`, 5 MB, nombre aleatorio) y mensajes.
- **Datos** (`database/migrations/008_sobre_nosotros_estructura.sql`, ya en `schema.sql`; solo `INSERT`, sin `ALTER`):
  `sobre_nosotros_estructura` (JSON: `intro`, `subtitulo`, `tarjetas[{titulo,descripcion}]`, `cierre`),
  `sobre_nosotros_foto` (archivo en `uploads/sitio`) y `sobre_nosotros_foto_alt`. Las claves se crean solas al guardar si
  falta la migración (`Configuracion::asegurar`).
- **Sin pérdida de texto**: `sobre_nosotros_texto` **no se borra ni se modifica nunca**. Mientras `sobre_nosotros_estructura`
  esté vacía, el sitio sigue mostrando ese texto con el formateador de siempre (fallback), y el panel lo separa solo en
  campos con el mismo analizador (`TextoLargo::estructurar`, la misma lógica que `formatear`). No se guarda nada hasta
  que el Administrador pulse «Guardar configuración». Si el texto anterior no cabe en la estructura (varios subtítulos,
  más de 6 elementos…), queda completo en «Introducción». Con la estructura ya guardada, un guardado vacío manda: no
  reaparece el texto anterior.
- **Código**: `App\Core\SobreNosotros` (leer/guardar/validar la estructura y elegir la foto) y
  `TextoLargo::formatearEstructura()`, que produce el **mismo HTML** que `formatear()` (se comprobó: guardar sin cambios
  deja la sección pública idéntica, ignorando espacios). En la estructura el título de cada tarjeta y el subtítulo se
  guardan **sin los dos puntos**; se agregan al mostrar.
- **Panel** (Configuración → «Sobre nosotros»): Introducción, Subtítulo, hasta 6 tarjetas (título + descripción;
  ▲ ▼ Quitar y «+ Agregar tarjeta»; título vacío = se descarta), Frase de cierre, Foto (miniatura o aviso «Sin foto
  propia», subir/reemplazar, casilla «Quitar la foto», pie de foto). Botones de 44 px en móvil. (Se probó una vista previa en vivo dentro del panel y se quitó: ocupaba
  demasiado espacio.) El estilo de la sección vive en `sobre-nosotros.css`. El texto alternativo de la foto ya no se edita: se usa siempre el nombre del sitio (la clave `sobre_nosotros_foto_alt`
  queda sin uso).
- **Pie de foto** (`sobre_nosotros_foto_pie`, migración `009`): texto visible debajo de la foto (`figcaption`); distinto del alt, que no se ve.
  Con foto al lado, el párrafo de introducción se agranda un poco, se centra en vertical y lleva una línea verde de acento
  para que la sección se vea más llena.
- **Foto**: prioridad 1) la propia, 2) la primera de la galería, 3) ninguna (el texto ocupa todo el ancho, sin hueco).
  Al reemplazar o quitar se borra el archivo anterior de `uploads/sitio` (solo si otro ajuste no apunta al mismo
  archivo); si el guardado falla no queda la foto nueva huérfana.
- **Seguridad y compatibilidad**: solo Administrador. Lectura con `Entrada`/`is_string`, longitudes con `Validator`
  (introducción 2000, subtítulo 80, título 80, descripción 500, cierre 250, alt 150), todo se escapa al mostrar y no
  se permite HTML. Los campos de tarjeta son **planos** (`nosotros_titulo_1…6`, `nosotros_desc_1…6`), no arreglos:
  así `BorradorFormulario` los conserva completos si el token vence y `admin.js` los restaura por nombre; la foto no se
  puede recuperar (el aviso pide volver a elegirla). Actualización parcial (`47ae530`): solo cambia lo que llega; un
  envío sin campos de la sección no la toca, y guardar solo texto no borra la foto.
- **Auditoría**: además del registro general de Configuración, quedan «Texto de «Sobre nosotros» editado» y «Foto de
  «Sobre nosotros»: subida / reemplazada / quitada» (visibles en la pantalla de Auditoría).

### 4.43 Horario agrupado en el sitio

En el pie del sitio, el horario puede mostrarse **agrupado**: los días con exactamente el mismo horario se juntan en una
fila («Lunes a Viernes», «Sábado y Domingo»); si no son seguidos, el tramo se separa: «Lunes a Viernes y Domingo».
`HorarioAtencion::filasParaMostrar()` arma las filas. Se elige en Configuración → Horario (clave `horario_agrupar`,
`1` agrupar —por defecto—, `0` un renglón por día como antes; migración `010`). Solo afecta cómo se **ve**: el horario
de cada día se guarda igual y el carrito (aviso de abierto/cerrado) sigue leyendo día por día. Una jornada corrida
se logra poniendo solo el primer turno (ej. 9:00 a 21:00) y dejando vacío el segundo.

### 4.44 Promociones y temporadas en el teléfono

- **Teléfono (< 768 px)**: las promociones, y las temporadas cuando hay 2 o más, van en una fila que se desliza de lado
  con imán (`scroll-snap`); la siguiente tarjeta asoma por la derecha y `sitio.js` (`inicializarDeslizables`) agrega
  puntos (uno por tarjeta, tocables). Una sola temporada sigue siendo un banner centrado.
- **Escritorio**: las promociones siguen en cuadrícula; 2 o más temporadas van en **2 columnas** (antes apiladas) y una
  sola queda como banner. Los puntos solo existen cuando la lista realmente se desliza.

### 4.45 Orden manual de promociones y temporadas

Listados de **Promociones** y **Temporadas** con flechas ▲ ▼; el orden del listado es el orden en el sitio (escritorio y
teléfono). Sin cambios de esquema (la cuenta de la aplicación no tiene `ALTER`):

- **Promociones**: el orden es la `prioridad` que ya existía (mayor = más arriba). `Promocion::mover()` renumera toda
  la lista (n … 1, tope 100) e intercambia con el vecino. Una promoción más arriba también gana si dos aplican al mismo
  producto. El campo «Prioridad» del formulario sigue existiendo.
- **Temporadas**: el orden se guarda como lista JSON de ids en `configuracion_sistema.orden_temporadas` (migración
  `011`). Las temporadas sin lugar elegido van al final por fecha de inicio. `Temporada::mover()` guarda el orden completo.
- Cada movimiento queda en Auditoría («posición» antes y después).

### 4.46 Promoción «otra» (texto libre)

Las promociones son un anuncio: el tipo solo decide la etiqueta que ve el cliente (no calculan precios). Para no tener que
programar cada idea (3x1, combo…), el tipo `personalizada` deja que el administrador escriba el texto de la etiqueta
(`etiqueta_personalizada`, máx. 40; lo muestra `Vista::etiquetaPromocion`). Requiere la migración `012` (ALTER del `ENUM`
y una columna), que **debe ejecutar un usuario de MySQL con permiso ALTER**. Mientras no se ejecute,
`Promocion::soportaPersonalizada()` es falso: el formulario no ofrece el tipo y guardar funciona como antes.
Las migraciones `006` y `012` se aplicaron en esta base el 13 de octubre de 2026 (con copia previa en
`C:/xamppn/respaldos/bd_completa_antes_de_migraciones_006_012_2026-10-13.sql`).

### 4.47 Auditoría: solo lo importante

`AuditoriaService::registrar()` ya no guarda cada clic:
- **No se guarda**: los inicios de sesión (los intentos fallidos siguen en `intentos_login`), los movimientos ▲▼ de orden y
  las ediciones que no cambiaron nada (se guardó el formulario sin tocar nada).
- **En las ediciones se guarda solo lo que cambió** (antes y después de cada campo), no la fila completa; la pantalla de
  Auditoría lo resume como «Cambió: precio, descripción».
- **Sí se guarda siempre**: crear y eliminar cualquier cosa, consumos/ajustes/canjes de lealtad, restablecer PIN,
  usuarios, y los cambios de configuración (solo las claves que cambiaron).
- Los registros anteriores a este cambio no se tocaron.

### 4.48 Usuario principal protegido

El usuario con `id = 1` (`Usuario::ID_PRINCIPAL`, el administrador del negocio) no se puede desactivar, cambiarle el rol ni
cambiarle el nombre de usuario: `UsuarioController::actualizar()` fuerza esos tres valores aunque lleguen otros en la
petición, y el formulario los muestra bloqueados con un aviso. Su nombre para mostrar y su contraseña sí se pueden
cambiar. El panel no tiene ninguna ruta para eliminar usuarios (solo desactivar a los demás), y ya existía la regla de no
dejar al sistema sin un administrador activo.

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

### 5.1 Tablas (16 — se agregó `cupones_promocion` en §4.20)

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
- **cupones_promocion** — id, codigo (único, 8 caracteres, mismo formato
  que `recompensas.codigo` pero tabla aparte, §4.20), cliente_id,
  promocion_id, estado (disponible / canjeado / cancelado / vencido),
  fecha_reclamo, usuario_canjeo_id, fecha_canje, usuario_cancelacion_id,
  fecha_cancelacion, motivo_cancelacion — `UNIQUE (cliente_id,
  promocion_id)`: un cupón por cliente y por promoción
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
