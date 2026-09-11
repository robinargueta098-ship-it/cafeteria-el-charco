<?php

/**
 * Front controller único. Toda petición pasa por aquí (ver public/.htaccess).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/config/config.php';

// Autoload manual, sin Composer (docs/arquitectura.md §2.3):
// App\Controllers\Public\HomeController  =>  app/Controllers/Public/HomeController.php
spl_autoload_register(function (string $clase): void {
    $prefijo = 'App\\';

    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $rutaRelativa = substr($clase, strlen($prefijo));
    $rutaArchivo = APP_PATH . '/' . str_replace('\\', '/', $rutaRelativa) . '.php';

    if (file_exists($rutaArchivo)) {
        require $rutaArchivo;
    }
});

use App\Core\ErrorHandler;
use App\Core\Router;
use App\Core\Session;

ErrorHandler::registrar();
Session::iniciar();

$router = new Router();
require APP_PATH . '/Routes/web.php';
require APP_PATH . '/Routes/admin.php';

$router->despachar($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
