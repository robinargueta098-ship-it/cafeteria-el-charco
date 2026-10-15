<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Permisos;
use App\Models\Rol;
use App\Services\AuditoriaService;
use Throwable;

/**
 * «Roles y permisos» (solo Administrador): decide qué módulos ve o edita cada rol (Cajero, Repartidor…).
 * El Administrador no se configura: siempre puede todo. Usuarios, Configuración, Auditoría y esta pantalla no se delegan.
 */
class RolController extends Controller
{
    private const NIVELES = ['ninguno' => 'Sin acceso', 'ver' => 'Solo ver', 'editar' => 'Ver y editar'];

    public function index(): void
    {
        $this->requerirRol(1);

        $roles = [];
        foreach ((new Rol())->listarTodos() as $rol) {
            if ((int) $rol['id'] === Permisos::ROL_ADMINISTRADOR) {
                continue;
            }
            $roles[] = ['id' => (int) $rol['id'], 'nombre' => (string) $rol['nombre'], 'permisos' => Permisos::delRol((int) $rol['id'])];
        }

        $this->vista('admin/roles/index', [
            'seccionActiva' => 'roles',
            'roles'         => $roles,
            'modulos'       => Permisos::MODULOS,
            'niveles'       => self::NIVELES,
        ]);
    }

    public function guardar(string $id): void
    {
        $this->requerirRol(1);

        $rolId = (int) $id;
        $existente = null;
        foreach ((new Rol())->listarTodos() as $rol) {
            if ((int) $rol['id'] === $rolId) {
                $existente = $rol;
            }
        }
        if ($existente === null || $rolId === Permisos::ROL_ADMINISTRADOR) {
            $this->error404();
        }

        $enviados = isset($_POST['permiso']) && is_array($_POST['permiso']) ? $_POST['permiso'] : [];
        $antes = Permisos::delRol($rolId);

        try {
            $despues = Permisos::guardar($rolId, array_map('strval', array_filter($enviados, 'is_scalar')));
        } catch (Throwable $e) {
            Flash::error('No se pudieron guardar los permisos. Inténtalo de nuevo.');
            $this->redirigir('/admin/roles');
        }

        $cambios = [];
        foreach (Permisos::MODULOS as $modulo => $def) {
            if (($antes[$modulo] ?? 'ninguno') !== $despues[$modulo]) {
                $cambios[] = $def[0] . ': ' . self::NIVELES[$antes[$modulo] ?? 'ninguno'] . ' → ' . self::NIVELES[$despues[$modulo]];
            }
        }

        if ($cambios === []) {
            Flash::exito('No hubo cambios en los permisos de ' . $existente['nombre'] . '.');
        } else {
            AuditoriaService::registrar('roles', $rolId, 'editar', null, [
                '_objeto' => (string) $existente['nombre'],
                'detalle' => 'Cambió los permisos del rol «' . $existente['nombre'] . '» — ' . implode('; ', $cambios),
            ]);
            Flash::exito('Permisos de ' . $existente['nombre'] . ' actualizados. Se aplican de inmediato.');
        }

        $this->redirigir('/admin/roles');
    }
}
