<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Temporada;
use App\Services\AuditoriaService;

class TemporadaController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/temporadas/index', [
            'seccionActiva' => 'temporadas',
            'temporadas'    => (new Temporada())->listarTodas(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1);
        $this->vista('admin/temporadas/formulario', [
            'seccionActiva' => 'temporadas', 'temporada' => null, 'errores' => [],
        ]);
    }

    public function crear(): void
    {
        $this->requerirRol(1);

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores !== []) {
            $this->vista('admin/temporadas/formulario', [
                'seccionActiva' => 'temporadas', 'temporada' => $datos, 'errores' => $errores,
            ]);
            return;
        }

        $id = (new Temporada())->crear($datos);
        AuditoriaService::registrar('temporadas', $id, 'crear', null, $datos);

        Flash::exito('Temporada creada correctamente.');
        $this->redirigir('/admin/temporadas');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirRol(1);

        $temporada = (new Temporada())->buscarPorId((int) $id);
        if ($temporada === null) {
            $this->error404();
        }

        $this->vista('admin/temporadas/formulario', [
            'seccionActiva' => 'temporadas', 'temporada' => $temporada, 'errores' => [],
        ]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Temporada();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/temporadas/formulario', [
                'seccionActiva' => 'temporadas', 'temporada' => $datos, 'errores' => $errores,
            ]);
            return;
        }

        $modelo->actualizar((int) $id, $datos);
        AuditoriaService::registrar('temporadas', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Temporada actualizada correctamente.');
        $this->redirigir('/admin/temporadas');
    }

    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Temporada();
        $temporada = $modelo->buscarPorId((int) $id);
        if ($temporada === null) {
            $this->error404();
        }

        $asociados = $modelo->contarProductosAsociados((int) $id);
        if ($asociados > 0) {
            Flash::error("No se puede eliminar: {$asociados} producto(s) están asignados a esta temporada. Quítalos primero o desactívala.");
            $this->redirigir('/admin/temporadas');
            return;
        }

        $modelo->eliminar((int) $id);
        AuditoriaService::registrar('temporadas', (int) $id, 'eliminar', $temporada, null);

        Flash::exito('Temporada eliminada correctamente.');
        $this->redirigir('/admin/temporadas');
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'nombre'       => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion'  => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'fecha_inicio' => (string) ($_POST['fecha_inicio'] ?? ''),
            'fecha_fin'    => (string) ($_POST['fecha_fin'] ?? ''),
            'activo'       => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 100)
            ->requerido('fecha_inicio', 'Fecha de inicio')
            ->requerido('fecha_fin', 'Fecha de fin');

        $errores = $validador->errores();

        if ($datos['fecha_inicio'] !== '' && $datos['fecha_fin'] !== '' && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        return $errores;
    }
}
