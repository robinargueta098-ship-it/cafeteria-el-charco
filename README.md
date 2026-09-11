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
- [x] Fase 1 — Arquitectura entregada en [docs/arquitectura.md](docs/arquitectura.md),
      **pendiente de tu aprobación** antes de iniciar la Fase 2 (base de datos)
- [ ] Resto de fases: ver el plan en docs/arquitectura.md §9

## Stack (propuesto en docs/arquitectura.md, sujeto a confirmación)

- PHP 8 + PDO, arquitectura MVC ligera hecha a medida (sin framework, sin
  Composer obligatorio) — análisis completo de por qué no Laravel en
  hosting compartido en docs/arquitectura.md §2.
- MySQL/MariaDB, Bootstrap 5 vendorizado localmente.
- Sin código de aplicación todavía — este commit solo agrega el análisis de
  arquitectura.
