<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

class AuthController extends Controller
{
    public function mostrarLogin(): void
    {
        GuestMiddleware::manejar();
        $this->vista('admin/login', ['error' => null]);
    }

    public function iniciarSesion(): void
    {
        CsrfMiddleware::manejar();

        $usuario  = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($usuario === '' || $password === '' || !Auth::intentar($usuario, $password)) {
            $this->vista('admin/login', ['error' => 'Usuario o contraseña incorrectos.']);
            return;
        }

        $this->redirigir('/admin');
    }

    public function cerrarSesion(): void
    {
        CsrfMiddleware::manejar();
        Auth::cerrarSesion();
        $this->redirigir('/admin/login');
    }

    public function panel(): void
    {
        AuthMiddleware::manejar();

        $this->vista('admin/dashboard', [
            'usuario' => Auth::usuario(),
        ]);
    }
}
