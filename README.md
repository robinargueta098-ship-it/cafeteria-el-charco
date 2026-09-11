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
      protegidas). **Pendiente de tu revisión** antes de iniciar la Fase 4
      (panel: catálogo)
- [ ] Resto de fases: ver el plan en docs/arquitectura.md §9

## Cómo probar en local

1. Este proyecto usa un alias de Apache (`http://localhost/elcharco` →
   `elcharco/public`) configurado en
   `C:\xampp\apache\conf\extra\httpd-xampp.conf`. **Reinicia Apache desde
   el Panel de Control de XAMPP** (Stop/Start) para que tome el cambio.
2. Copia `config/database.example.php` como `config/database.php` (ya
   existe en este equipo con las credenciales del usuario dedicado
   `elcharco_app`) si trabajas desde otra máquina.
3. Abre `http://localhost/elcharco` (sitio público) y
   `http://localhost/elcharco/admin/login` (panel).

## Stack (confirmado en docs/arquitectura.md §10)

- PHP 8 + PDO, arquitectura MVC ligera hecha a medida (sin framework, sin
  Composer obligatorio) — análisis completo de por qué no Laravel en
  hosting compartido en docs/arquitectura.md §2.
- MySQL/MariaDB, Bootstrap 5 vendorizado localmente.
- `public/` como raíz web, aislando `app/`, `config/` y `database/`.
- Panel con dos roles: Administrador y Cajero.
- Sin código de aplicación todavía — la Fase 2 (base de datos) es el
  siguiente paso.
