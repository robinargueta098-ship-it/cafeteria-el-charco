<?php

/**
 * Configuración general de la aplicación.
 * No contiene credenciales — eso vive en config/database.php (fuera de git).
 */

declare(strict_types=1);

define('APP_ENV', 'local');   // 'local' en XAMPP, 'production' en Hostinger
define('APP_DEBUG', true);    // true muestra errores detallados; false en producción
define('APP_URL', 'http://elcharco.local');
define('APP_TIMEZONE', 'America/Guatemala');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', BASE_PATH . '/public/uploads');
define('UPLOADS_URL', '/uploads');

date_default_timezone_set(APP_TIMEZONE);

// Compatibilidad con PHP 7.4 (Hostinger puede usarlo): estas tres funciones nacieron en PHP 8.0.
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
