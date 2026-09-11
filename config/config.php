<?php

/**
 * Configuración general de la aplicación.
 * No contiene credenciales — eso vive en config/database.php (fuera de git).
 */

declare(strict_types=1);

define('APP_ENV', 'local');   // 'local' en XAMPP, 'production' en Hostinger
define('APP_DEBUG', true);    // true muestra errores detallados; false en producción
define('APP_URL', 'http://localhost/elcharco');
define('APP_TIMEZONE', 'America/Guatemala');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', BASE_PATH . '/public/uploads');
define('UPLOADS_URL', '/uploads');

date_default_timezone_set(APP_TIMEZONE);
