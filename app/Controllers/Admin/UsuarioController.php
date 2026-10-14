<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use PDOException;

class UsuarioController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/usuarios/index', [
            'seccionActiva' => 'usuarios',
            'usuarios'      => (new Usuario())->listarTodos(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/usuarios/formulario', [
            'seccionActiva' => 'usuarios', 'usuarioEditado' => null, 'errores' => [], 'roles' => (new Rol())->listarTodos(),
        ]);
    }

    public function crear(): void
    {
        $this->requerirRol(1);

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos, true);

        if ($errores === []) {
            try {
                $modelo = new Usuario();
                $id = $modelo->crear($datos['nombre'], $datos['usuario'], password_hash($datos['password'], PASSWORD_DEFAULT), $datos['rol_id'], $datos['activo']);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e; // no era un duplicado: no se disfraza de "ya existe"
                }
                $errores[] = 'Ya existe un usuario con ese nombre de usuario.';
            }
        }

        if ($errores !== []) {
            $this->vista('admin/usuarios/formulario', [
                'seccionActiva' => 'usuarios', 'usuarioEditado' => $datos, 'errores' => $errores, 'roles' => (new Rol())->listarTodos(),
            ]);
            return;
        }

        AuditoriaService::registrar('usuarios', $id, 'crear', null, ['nombre' => $datos['nombre'], 'usuario' => $datos['usuario'], 'rol_id' => $datos['rol_id']]);

        Flash::exito('Usuario creado correctamente.');
        $this->redirigir('/admin/usuarios');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirRol(1);

        $usuarioEditado = (new Usuario())->buscarPorId((int) $id);
        if ($usuarioEditado === null) {
            $this->error404();
        }

        $this->vista('admin/usuarios/formulario', [
            'seccionActiva' => 'usuarios', 'usuarioEditado' => $usuarioEditado, 'errores' => [], 'roles' => (new Rol())->listarTodos(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Usuario();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();

        // Usuario principal protegido: siempre Administrador, activo y con el mismo nombre de usuario, sin importar
        // lo que llegue en el formulario. (Su nombre para mostrar y su contraseña sí se pueden cambiar.)
        if (Usuario::esPrincipal((int) $id)) {
            $datos['rol_id'] = 1;
            $datos['activo'] = true;
            $datos['usuario'] = (string) $existente['usuario'];
        }

        $errores = $this->validar($datos, false);

        // No permitir dejar sin ningún Administrador activo en el sistema.
        $seraAdminActivo = $datos['rol_id'] === 1 && $datos['activo'] === true;
        if (!$seraAdminActivo && (int) $existente['rol_id'] === 1 && (int) $existente['activo'] === 1) {
            if ($modelo->contarAdministradoresActivos((int) $id) === 0) {
                $errores[] = 'No puedes desactivar ni quitarle el rol de Administrador al último administrador activo.';
            }
        }

        if ($errores === []) {
            try {
                $modelo->actualizarDatos((int) $id, $datos['nombre'], $datos['usuario'], $datos['rol_id'], $datos['activo']);
                if ($datos['password'] !== '') {
                    $modelo->actualizarPassword((int) $id, password_hash($datos['password'], PASSWORD_DEFAULT));
                }
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e; // no era un duplicado: no se disfraza de "ya existe"
                }
                $errores[] = 'Ya existe otro usuario con ese nombre de usuario.';
            }
        }

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/usuarios/formulario', [
                'seccionActiva' => 'usuarios', 'usuarioEditado' => $datos, 'errores' => $errores, 'roles' => (new Rol())->listarTodos(),
            ]);
            return;
        }

        AuditoriaService::registrar('usuarios', (int) $id, 'editar',
            ['nombre' => $existente['nombre'], 'usuario' => $existente['usuario'], 'rol_id' => $existente['rol_id'], 'activo' => $existente['activo']],
            ['nombre' => $datos['nombre'], 'usuario' => $datos['usuario'], 'rol_id' => $datos['rol_id'], 'activo' => $datos['activo']]
        );

        Flash::exito('Usuario actualizado correctamente.');
        $this->redirigir('/admin/usuarios');
    }

    /**
     * Elimina un usuario. No se puede eliminar: al usuario principal, a uno mismo, al último administrador activo ni a
     * quien ya tiene historial (lealtad, canjes): en esos casos se desactiva desde «Editar».
     */
    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Usuario();
        $usuario = $modelo->buscarPorId((int) $id);
        if ($usuario === null) {
            $this->error404();
        }

        $actual = Auth::usuario();
        $motivo = null;
        if (Usuario::esPrincipal((int) $id)) {
            $motivo = 'El usuario principal no se puede eliminar.';
        } elseif ($actual !== null && (int) $actual['id'] === (int) $id) {
            $motivo = 'No puedes eliminar tu propio usuario.';
        } elseif ((int) $usuario['rol_id'] === 1 && (int) $usuario['activo'] === 1 && $modelo->contarAdministradoresActivos((int) $id) === 0) {
            $motivo = 'No puedes eliminar al último administrador activo.';
        } elseif (($historial = $modelo->contarHistorial((int) $id)) > 0) {
            $motivo = "No se puede eliminar: tiene {$historial} registro(s) de lealtad o canjes a su nombre. Desactívalo desde «Editar» para que ya no pueda entrar.";
        }

        if ($motivo !== null) {
            Flash::error($motivo);
            $this->redirigir('/admin/usuarios');
        }

        $modelo->eliminar((int) $id);
        AuditoriaService::registrar('usuarios', (int) $id, 'eliminar',
            ['nombre' => $usuario['nombre'], 'usuario' => $usuario['usuario'], 'rol_id' => $usuario['rol_id']], null
        );

        Flash::exito('Usuario eliminado correctamente.');
        $this->redirigir('/admin/usuarios');
    }

    /** @return array{nombre:string,usuario:string,password:string,rol_id:int,activo:bool} */
    private function datosDelFormulario(): array
    {
        return [
            'nombre'   => trim(Entrada::texto($_POST, 'nombre', '')),
            'usuario'  => trim(Entrada::texto($_POST, 'usuario', '')),
            'password' => Entrada::texto($_POST, 'password', ''),
            'rol_id'   => (int) ($_POST['rol_id'] ?? 0),
            'activo'   => isset($_POST['activo']),
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos, bool $requierePassword): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 100)
            ->requerido('usuario', 'Usuario')
            ->longitudMaxima('usuario', 'Usuario', 50);

        $errores = $validador->errores();

        if ($requierePassword && $datos['password'] === '') {
            $errores[] = 'La contraseña es obligatoria para un usuario nuevo.';
        }
        if ($datos['password'] !== '' && strlen($datos['password']) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!(new Rol())->existe($datos['rol_id'])) {
            $errores[] = 'Selecciona un rol válido.';
        }

        // El usuario actual (el que está en sesión) no puede desactivarse a sí mismo.
        $actual = Auth::usuario();
        if ($actual !== null && isset($_POST['_id_editado']) && (int) $_POST['_id_editado'] === $actual['id'] && !$datos['activo']) {
            $errores[] = 'No puedes desactivar tu propia cuenta.';
        }

        return $errores;
    }
}
