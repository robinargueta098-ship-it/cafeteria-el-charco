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
      las Fases 4-6. Ver docs/arquitectura.md §7. **Pendiente de tu
      revisión** antes de iniciar la Fase 4 (panel: catálogo)
- [ ] Resto de fases: ver el plan en docs/arquitectura.md §9

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
