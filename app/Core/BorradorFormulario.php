<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Borrador temporal de un formulario del panel: cuando un envío se rechaza porque la página quedó
 * desactualizada (token vencido) o la sesión expiró a la mitad, en vez de perder lo escrito se guarda
 * aquí (en la sesión, 30 minutos) y se devuelve al usuario a la MISMA pantalla con los campos ya
 * rellenados (admin.js los restaura). Nunca se guardan contraseñas, PIN ni tokens.
 */
class BorradorFormulario
{
    private const CLAVE = '_borrador';
    private const VIDA_SEGUNDOS = 1800;
    private const MAX_VALOR = 5000;
    private const MAX_CAMPOS = 200;

    /** Pantallas donde nunca se guarda nada (credenciales). */
    private const RUTAS_EXCLUIDAS = ['/admin/login', '/admin/logout'];

    /**
     * @param array<string,mixed> $post
     * @return bool true si quedó guardado un borrador
     */
    public static function guardar(string $rutaPost, array $post, string $destino): bool
    {
        if (in_array($rutaPost, self::RUTAS_EXCLUIDAS, true) || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $datos = [];
        foreach ($post as $nombre => $valor) {
            $minuscula = strtolower((string) $nombre);
            if (str_contains($minuscula, 'pass') || str_contains($minuscula, 'pin') || str_contains($minuscula, 'csrf') || str_contains($minuscula, 'token')) {
                continue;
            }
            if (is_string($valor)) {
                $datos[$nombre] = mb_substr($valor, 0, self::MAX_VALOR);
            } elseif (is_array($valor)) {
                $datos[$nombre] = array_values(array_filter(array_map(
                    fn ($v) => is_string($v) ? mb_substr($v, 0, 200) : null,
                    array_slice($valor, 0, 100)
                ), fn ($v) => $v !== null));
            }
            if (count($datos) >= self::MAX_CAMPOS) {
                break;
            }
        }

        if ($datos === []) {
            return false;
        }

        $_SESSION[self::CLAVE] = [
            'ruta'    => $rutaPost,
            'destino' => $destino,
            'datos'   => $datos,
            'hasta'   => time() + self::VIDA_SEGUNDOS,
        ];

        return true;
    }

    /**
     * A dónde regresar al usuario: la página desde la que envió el formulario (Referer, solo si es de este
     * mismo sitio y del panel); si el navegador no la mandó, la misma dirección cuando es un formulario de
     * alta/edición/configuración (esas pantallas se abren por GET en la misma dirección).
     */
    public static function destinoSeguro(string $rutaPost, ?string $referer): string
    {
        if ($referer !== null && $referer !== '') {
            $partes = parse_url($referer);
            $host = strtolower((string) ($partes['host'] ?? ''));
            $miHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
            $path = (string) ($partes['path'] ?? '');

            if ($host === $miHost && ($path === '/admin' || str_starts_with($path, '/admin/')) && !in_array($path, self::RUTAS_EXCLUIDAS, true)) {
                return $path . (isset($partes['query']) && $partes['query'] !== '' ? '?' . $partes['query'] : '');
            }
        }

        if (str_ends_with($rutaPost, '/crear') || str_ends_with($rutaPost, '/editar') || $rutaPost === '/admin/configuracion') {
            return $rutaPost;
        }

        return '/admin';
    }

    /** Pantalla a la que volver tras iniciar sesión de nuevo, si hay un borrador vigente. */
    public static function destinoPendiente(): ?string
    {
        $b = $_SESSION[self::CLAVE] ?? null;
        if (!is_array($b) || ($b['hasta'] ?? 0) < time() || empty($b['destino'])) {
            return null;
        }

        $destino = (string) $b['destino'];

        return ($destino === '/admin' || str_starts_with($destino, '/admin/')) ? $destino : null;
    }

    /**
     * Devuelve (y consume) el borrador si corresponde a la pantalla que se está abriendo ahora.
     *
     * @return array{ruta:string,datos:array<string,mixed>}|null
     */
    public static function tomarParaRuta(string $rutaActual): ?array
    {
        $b = $_SESSION[self::CLAVE] ?? null;
        if (!is_array($b)) {
            return null;
        }
        if (($b['hasta'] ?? 0) < time()) {
            unset($_SESSION[self::CLAVE]);
            return null;
        }

        $rutaDestino = (string) parse_url((string) ($b['destino'] ?? ''), PHP_URL_PATH);
        if (rtrim($rutaDestino, '/') !== rtrim($rutaActual, '/')) {
            return null; // se está viendo otra pantalla: el borrador espera (o vence a los 30 minutos)
        }

        unset($_SESSION[self::CLAVE]);

        return ['ruta' => (string) $b['ruta'], 'datos' => (array) $b['datos']];
    }
}
