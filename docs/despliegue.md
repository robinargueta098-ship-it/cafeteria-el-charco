# Despliegue de Cafetería El Charco en el servidor (VPS)

Pasos para quien administra el servidor. **Nada de lo que está aquí contiene contraseñas.** Los archivos con la base de
datos y las fotos NO están en este repositorio: el desarrollador los entrega aparte (llevan las cuentas del panel).

## Requisitos

- Apache con `mod_rewrite` (o Nginx equivalente), **PHP 7.4 u 8.x** con las extensiones `pdo_mysql`, `mbstring`, `zip` y `gd`.
- MariaDB 10.4 o superior (o MySQL 8). La cuenta de la base debe poder crear tablas, columnas y disparadores (TRIGGER).
- HTTPS activo (en producción la cookie de sesión es `Secure`).
- Composer **no hace falta**: la carpeta `vendor/` ya está en el repositorio.

## 1. Código

```bash
git clone https://github.com/robinargueta098-ship-it/cafeteria-el-charco.git
cd cafeteria-el-charco
git checkout cambios-octubre        # la rama con lo último
```

El sitio debe servirse desde la carpeta **`public/`** (document root).

## 2. Configuración (los dos archivos se editan en el servidor; no se suben al repositorio)

- `config/config.php`: `APP_ENV = 'production'`, `APP_DEBUG = false`, `APP_URL = 'https://TU-DOMINIO'`.
- `config/database.php`: copiar `config/database.example.php` y escribir host, base, usuario y contraseña del servidor.

## 3. Base de datos

Base nueva y vacía:

```bash
mysql -u USUARIO -p NOMBRE_BD < schema_para_servidor.sql        # estructura (entregado aparte)
mysql -u USUARIO -p --default-character-set=utf8mb4 NOMBRE_BD < contenido_y_cuentas.sql   # contenido y cuentas (aparte)
```

`database/schema.sql` del repositorio es la misma estructura, pero trae `CREATE DATABASE` y `USE elcharco_cafeteria`;
`schema_para_servidor.sql` ya viene sin esas dos líneas.

Si la base **ya existía** con una versión anterior, no se importa el esquema otra vez: se aplican, en orden y con un
usuario administrador de la base, las migraciones que falten de `database/migrations/` (las más recientes son
`013` pedidos, `014` estado pendiente, `015` rol repartidor, `016` permisos por rol, `017` pedidos por día,
`018` leches y envío, `019` preparar con agua).

## 4. Fotos y permisos

- Descomprimir `uploads.zip` (entregado aparte) dentro de `public/` para que quede `public/uploads/...`.
- Permisos de escritura para el usuario del servidor web en `storage/` (logs y sesiones) y `public/uploads/`:

```bash
chown -R www-data:www-data storage public/uploads
chmod -R u+rwX,g+rwX storage public/uploads
```

## 5. Revisión

- `https://TU-DOMINIO/` carga con fotos, menú y horario.
- `https://TU-DOMINIO/admin/login` entra con las cuentas importadas.
- Un pedido de prueba completo desde el celular (se cancela después en Pedidos con el motivo «prueba»).
- Borrar del servidor `contenido_y_cuentas.sql` al terminar (lleva contraseñas cifradas).

Más detalle técnico de cada módulo: `docs/arquitectura.md`.
