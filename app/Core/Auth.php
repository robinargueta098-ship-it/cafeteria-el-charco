<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

/**
 * Autenticación del panel administrativo. Reglas de docs/arquitectura.md §7:
 * password_hash/password_verify, session_regenerate_id al iniciar sesión,
 * y bloqueo temporal tras intentos fallidos.
 */
class Auth
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MINUTOS = 15;

    public static function intentar(string $usuario, string $password): bool
    {
        $modelo = new Usuario();
        $registro = $modelo->buscarPorUsuario($usuario);

        if (!$registro || !(int) $registro['activo']) {
            return false;
        }

        if (!empty($registro['bloqueado_hasta']) && strtotime($registro['bloqueado_hasta']) > time()) {
            return false;
        }

        if (!password_verify($password, $registro['password_hash'])) {
            $modelo->registrarIntentoFallido((int) $registro['id'], self::MAX_INTENTOS, self::BLOQUEO_MINUTOS);
            return false;
        }

        Session::regenerar();
        $_SESSION['usuario_id']     = (int) $registro['id'];
        $_SESSION['usuario_nombre'] = $registro['nombre'];
        $_SESSION['usuario_rol']    = (int) $registro['rol_id'];

        $modelo->registrarAccesoExitoso((int) $registro['id']);

        return true;
    }

    public static function verificado(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    /** @return array{id:int,nombre:string,rol_id:int}|null */
    public static function usuario(): ?array
    {
        if (!self::verificado()) {
            return null;
        }

        return [
            'id'     => (int) $_SESSION['usuario_id'],
            'nombre' => (string) $_SESSION['usuario_nombre'],
            'rol_id' => (int) $_SESSION['usuario_rol'],
        ];
    }

    public static function cerrarSesion(): void
    {
        Session::destruir();
    }
}
