# Cafetería El Charco — Sistema Web

Sistema web para **Cafetería El Charco**, un negocio real. El proyecto consta
de dos partes:

- **Sitio público**: presentación de la cafetería, menú, promociones, redes
  sociales y contacto.
- **Panel administrativo** (tipo WordPress): permite administrar el contenido
  del sitio (menú, precios, promociones, información del negocio) sin tocar
  código.

Corre en local con **XAMPP** (Apache + MySQL/MariaDB + PHP) y se prueba en
`http://localhost/elcharco`.

## Datos del negocio

La información real del negocio (nombre, redes sociales, contacto, paleta de
colores, moneda, programa de lealtad y menú completo con precios) está
documentada en [docs/negocio.md](docs/negocio.md). Esa información se debe
usar tal cual en el desarrollo — **no se deben inventar datos de ejemplo**
para nombres, precios, productos o contacto.

## Metodología: desarrollo por fases

Este proyecto se construye **por fases**, definidas y aprobadas junto con el
dueño del proyecto conforme avanza el trabajo (por ejemplo: preparación del
entorno, arquitectura, base de datos, sitio público, panel administrativo,
pulido y despliegue).

**Regla obligatoria:** no se debe avanzar a la siguiente fase sin
**confirmación explícita** del dueño del proyecto. Cada fase se entrega,
se revisa y se aprueba antes de iniciar la siguiente. Esto aplica también a
analizar o proponer arquitectura: no se define ni se implementa arquitectura
hasta que así se indique explícitamente.

## Estado actual

- [x] Fase 0 — Preparación del entorno (carpeta del proyecto, repositorio
      Git, README, datos del negocio documentados)
- [x] Fase 1 — Arquitectura **aprobada**, ver [docs/arquitectura.md](docs/arquitectura.md)
- [x] Fase 2 — Base de datos: [database/schema.sql](database/schema.sql) (14
      tablas) + [docs/modelo-datos.md](docs/modelo-datos.md) (diagrama ER)
- [x] Fase 3 — Core: router, conexión PDO, autenticación con bloqueo por
      intentos fallidos, CSRF, sesiones seguras, manejo de errores/404/403,
      estructura completa de carpetas. Probado de extremo a extremo
      (login correcto/incorrecto, bloqueo tras 5 intentos, logout, rutas
      protegidas).
- [x] Fase 3.1 — Revisión de seguridad y endurecimiento: CSRF automático
      en todo POST (Router, no opcional por controlador), bloqueo por IP
      además de por cuenta (tabla `intentos_login`, antes sin usar),
      comparación de tiempo constante en el login (evita enumeración de
      usuarios), manejo real de errores fatales (`register_shutdown_
      function`) con `display_errors` controlado por `APP_DEBUG`, cookie
      de sesión expirada explícitamente en logout, defensa en
      profundidad contra inclusión de archivos en `Controller::vista()`,
      cabeceras de seguridad HTTP, y clase `Validator` reutilizable para
      las Fases 4-6. Ver docs/arquitectura.md §7.
- [x] Fases 4-6 — Panel administrativo completo: Productos, Categorías
      (con protección contra huérfanos), Galería (subida validada),
      Temporadas, Promociones (4 tipos, sin acumulación accidental),
      Clientes, Lealtad (libro mayor transaccional — nunca se edita el
      saldo sin historial), Recompensas, Configuración (incl. horarios),
      Usuarios (con protección del último Administrador activo) y
      Auditoría (con filtros). Sidebar con permisos por rol
      (Administrador/Cajero). Probado de extremo a extremo contra
      `elcharco.local`: CRUD de cada módulo, subida de imagen real vs.
      archivo falso rechazado, huérfanos bloqueados, flujo completo de
      lealtad (acumular → rechazar canje insuficiente → acumular más →
      canjear → verificar sobrante y auditoría), roles restringidos.
      Un bug real encontrado y corregido durante las pruebas: un fallo
      de codificación podía tumbar con error 500 una operación ya
      guardada exitosamente — ver docs/arquitectura.md §7.
- [x] Fase 7 — Sitio público completo (una sola página con anclas):
      hero, más vendidos, destacados, temporada, promociones vigentes,
      menú dinámico por categorías (46 productos reales, agrupados y
      con notas de categoría), sobre nosotros, galería, ubicación +
      mapa, calificación de Google, lealtad, contacto/redes y botón
      flotante de WhatsApp. WhatsApp siempre construido desde el número
      configurado centralmente (`App\Core\WhatsApp`), con un mensaje
      distinto según el contexto (portada, nav, flotante).
      Responsive real (probado en el navegador en 360/375/390/414/768/
      1024/1280/1920px, sin desbordamiento horizontal en ninguno);
      `scroll-margin-top` corregido tras encontrar que el nav fijo
      tapaba los títulos al saltar a un ancla. `loading="lazy"` +
      `decoding="async"` verificados con una foto real subida de
      prueba. Sin Bootstrap/jQuery — CSS y JS propios.
      Lo que el negocio no ha proporcionado (dirección, horario, texto
      "sobre nosotros", reseña de Google, foto de portada, fotos de
      productos/galería) se muestra con marcadores `[PENDIENTE: ...]`
      claramente visibles — nunca inventado.
- [x] Rediseño de Lealtad + revisión de Promociones: recompensas ahora
      son su propia entidad (tabla `recompensas`) generadas
      automáticamente por el servidor al cruzar el umbral configurado
      (nunca un número fijo en el código) — probado exacto contra los
      4 ejemplos dados (10→1+0, 11→1+1, 20→2+0, 25→2+5). Canje por
      código único con `SELECT ... FOR UPDATE` (doble canje probado y
      rechazado, incluso saltándose la interfaz). Ajustes manuales con
      motivo obligatorio. Cancelar una recompensa no canjeada devuelve
      los cafés. Cuenta simple del cliente (teléfono + PIN de 4
      dígitos, `/cuenta`) con el mismo endurecimiento que el login del
      panel (bloqueo por cuenta + por IP, probado). Promociones: se
      encontraron y corrigieron 2 errores lógicos reales (borrar una
      categoría podía borrar en silencio sus promociones; una
      promoción "vigente" no revisaba si su producto/categoría seguía
      activo). De regalo, un bug preexistente de Fase 4-6 también
      encontrado y corregido: desactivar un producto daba error 500
      (PDO rechazaba parámetros de más al reusar la fila completa en un
      UPDATE). Ver docs/arquitectura.md §4.1-4.4 y §5.1. **Pendiente de
      tu revisión**
- [x] Galería con carrusel: el sitio público muestra las fotos en un
      carrusel responsive (deslizable con el dedo, flechas, puntos,
      avance automático que se detiene al interactuar y respeta
      "reducir movimiento"); sin librerías. En el panel, subida de
      **varias fotos a la vez** (cada una validada por tipo real y
      ≤5 MB, hasta 10 por envío), orden con flechas ▲▼ y eliminación
      que borra también el archivo. Probado en 360/375/390/414/768/
      1024/1366/1920 px. Ver docs/arquitectura.md §4.5. **Pendiente
      de que subas al menos 6 fotos reales** (hoy hay 1).
- [ ] Resto de fases (diseño visual/pulido, despliegue): ver el
      plan en docs/arquitectura.md §9

## Cómo probar en local

Este proyecto usa un **Virtual Host con dominio propio**
(`http://elcharco.local`) apuntando directamente a `elcharco/public`,
configurado en `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.

Se probó primero con un *alias* de subcarpeta (`http://localhost/elcharco`)
y se descartó: la aplicación genera enlaces, formularios y redirecciones
como rutas absolutas de la raíz del dominio (`/admin/login`,
`/assets/css/main.css`), que es exactamente cómo funcionará en producción
(Hostinger, dominio propio = `public/`). Bajo un alias de subcarpeta esas
rutas absolutas apuntan mal (`http://localhost/admin/login` en vez de
`http://localhost/elcharco/admin/login`), rompiendo login y assets. Un
Virtual Host con nombre propio reproduce fielmente la raíz de dominio de
producción, así que no hay ese desfase.

Pasos para levantar el entorno en una máquina nueva:

1. Agregar `127.0.0.1 elcharco.local` al archivo hosts de Windows
   (`C:\Windows\System32\drivers\etc\hosts`) — requiere permisos de
   administrador.
2. Verificar que `C:\xampp\apache\conf\extra\httpd-vhosts.conf` tenga el
   bloque `<VirtualHost *:80>` con `ServerName elcharco.local` y
   `DocumentRoot` apuntando a `elcharco/public` (y que el primer
   VirtualHost del archivo preserve `http://localhost` con
   `DocumentRoot "C:/xampp/htdocs"`, para no romper otros proyectos).
3. Reiniciar Apache desde el Panel de Control de XAMPP.
4. Copiar `config/database.example.php` como `config/database.php` y
   completar con las credenciales del usuario dedicado `elcharco_app`
   (no root).
5. Abrir `http://elcharco.local/` (sitio público) y
   `http://elcharco.local/admin/login` (panel).

## Stack (confirmado en docs/arquitectura.md §10)

- PHP 8 + PDO, arquitectura MVC ligera hecha a medida (sin framework, sin
  Composer obligatorio) — análisis completo de por qué no Laravel en
  hosting compartido en docs/arquitectura.md §2.
- MySQL/MariaDB, Bootstrap 5 vendorizado localmente.
- `public/` como raíz web, aislando `app/`, `config/` y `database/`.
- Panel con dos roles: Administrador y Cajero.
- Sin código de aplicación todavía — la Fase 2 (base de datos) es el
  siguiente paso.
