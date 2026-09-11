<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;

/**
 * Router mínimo: mapea método+ruta a [Controlador, método]. Soporta
 * parámetros de ruta con la sintaxis {param}. Nada de "magia":
 * las rutas se declaran explícitamente en app/Routes/*.php.
 *
 * Todo POST pasa automáticamente por CsrfMiddleware antes de ejecutar
 * la acción: la protección CSRF es el comportamiento por defecto, no
 * algo que cada controlador nuevo tenga que recordar llamar.
 */
class Router
{
    /** @var array<int, array{metodo:string, ruta:string, accion:array}> */
    private array $rutas = [];

    public function get(string $ruta, array $accion): void
    {
        $this->agregar('GET', $ruta, $accion);
    }

    public function post(string $ruta, array $accion): void
    {
        $this->agregar('POST', $ruta, $accion);
    }

    private function agregar(string $metodo, string $ruta, array $accion): void
    {
        $this->rutas[] = ['metodo' => $metodo, 'ruta' => $ruta, 'accion' => $accion];
    }

    public function despachar(string $metodo, string $uriCompleta): void
    {
        $uri = (string) parse_url($uriCompleta, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->rutas as $ruta) {
            if ($ruta['metodo'] !== $metodo) {
                continue;
            }

            $patron = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $ruta['ruta']);
            $patron = '#^' . $patron . '$#';

            if (preg_match($patron, $uri, $coincidencias)) {
                array_shift($coincidencias);

                if ($metodo === 'POST') {
                    CsrfMiddleware::manejar();
                }

                $this->ejecutar($ruta['accion'], $coincidencias);
                return;
            }
        }

        http_response_code(404);
        require APP_PATH . '/Views/errores/404.php';
    }

    /** @param array<int,string> $parametros */
    private function ejecutar(array $accion, array $parametros): void
    {
        [$controlador, $metodo] = $accion;
        $instancia = new $controlador();
        $instancia->$metodo(...$parametros);
    }
}
