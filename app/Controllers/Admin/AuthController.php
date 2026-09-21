<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
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
            'usuario'  => trim((string) ($_POST['usuario'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];

        $validador = new Validator($datos);
        $validador
            ->requerido('usuario', 'Usuario')
            ->longitudMaxima('usuario', 'Usuario', 50)
            ->requerido('password', 'Contraseña')
            ->longitudMaxima('password', 'Contraseña', 255);

        if (!$validador->esValido() || !Auth::intentar($datos['usuario'], $datos['password'])) {
            $this->vista('admin/login', ['error' => 'Usuario o contraseña incorrectos.']);
            return;
        }

        $usuario = Auth::usuario();
        AuditoriaService::registrar('usuarios', $usuario['id'], 'login', null, ['usuario' => $usuario['nombre']]);

        $this->redirigir('/admin');
    }

    public function cerrarSesion(): void
    {
        Auth::cerrarSesion();
        $this->redirigir('/admin/login');
    }
}
