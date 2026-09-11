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

- [x] Preparación del entorno (carpeta del proyecto, repositorio Git, README,
      datos del negocio documentados)
- [ ] Definición de arquitectura (pendiente de aprobación para iniciar)
- [ ] Resto de fases: por definir

## Stack (previsto, sujeto a confirmación)

- PHP sobre XAMPP (Apache + MySQL/MariaDB)
- Sin código de aplicación todavía — este commit solo prepara el entorno de
  trabajo.
