<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Auth;
use App\Core\BorradorFormulario;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Middleware\GuestMiddleware;
use App\Services\AuditoriaService;

class AuthController extends Controller
{
    public function mostrarLogin(): void
    {
        GuestMiddleware::manejar();
        // Aviso de una sola lectura (ej. "Tu sesión expiró"), puesto por CsrfMiddleware.
        $this->vista('admin/login', ['error' => Flash::tomar()['mensaje'] ?? null]);
    }

    public function iniciarSesion(): void
    {
        // El token CSRF ya se validó en App\Core\Router para todo POST
        // (docs/arquitectura.md §7) — no hace falta repetirlo aquí.

        $datos = [
            'usuario'  => trim(Entrada::texto($_POST, 'usuario', '')),
            'password' => Entrada::texto($_POST, 'password', ''),
        ];

        $validador = new Validator($datos);
        $validador
            ->requerido('usuario', 'Usuario')
            ->longitudMaxima('usuario', 'Usuario', 50)
            ->requerido('password', 'Contraseña')
            ->longitudMaxima('password', 'Contraseña', 255);

        if (!$validador->esValido() || !Auth::intentar($datos['usuario'], $datos['password'])) {
            // Se anotan los intentos fallidos contra cuentas que existen (no contra nombres inventados por robots).
            if (Auth::$ultimoUsuarioId !== null) {
                AuditoriaService::registrar('sesiones', (int) Auth::$ultimoUsuarioId, 'login', null, [
                    'evento'      => 'fallido',
                    'motivo'      => (string) Auth::$ultimoMotivo,
                    'usuario'     => $datos['usuario'],
                    'dispositivo' => AuditoriaService::dispositivo($_SERVER['HTTP_USER_AGENT'] ?? null),
                ], (int) Auth::$ultimoUsuarioId);
            }
            $this->vista('admin/login', ['error' => 'Usuario o contraseña incorrectos.']);
            return;
        }

        $sesion = Auth::usuario();
        if ($sesion !== null) {
            AuditoriaService::registrar('sesiones', (int) $sesion['id'], 'login', null, [
                'evento'      => 'inicio',
                'usuario'     => $datos['usuario'],
                'dispositivo' => AuditoriaService::dispositivo($_SERVER['HTTP_USER_AGENT'] ?? null),
            ]);
        }

        // Si la sesión venció a mitad de un formulario, se vuelve a esa pantalla (con lo escrito) y no al inicio.
        $this->redirigir(BorradorFormulario::destinoPendiente() ?? '/admin');
    }

    public function cerrarSesion(): void
    {
        $sesion = Auth::usuario();
        if ($sesion !== null) {
            AuditoriaService::registrar('sesiones', (int) $sesion['id'], 'login', null, [
                'evento'      => 'cierre',
                'usuario'     => (string) $sesion['nombre'],
                'dispositivo' => AuditoriaService::dispositivo($_SERVER['HTTP_USER_AGENT'] ?? null),
            ]);
        }
        Auth::cerrarSesion();
        $this->redirigir('/admin/login');
    }
}
