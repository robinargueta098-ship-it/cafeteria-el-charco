<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
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

        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/temporadas');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $datos['imagen'] = $subida['nombre'];
            }
        }

        if ($errores !== []) {
            $this->vista('admin/temporadas/formulario', [
                'seccionActiva' => 'temporadas', 'temporada' => array_merge($datos, ['id' => null]), 'errores' => $errores,
            ]);
            return;
        }

        // Sin foto subida, la clave no existe todavía: el INSERT la necesita
        // (con PDO nativo un parámetro sin valor es "Invalid parameter number" → 500).
        $datos['imagen'] ??= null;

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
        $errores = $this->validar($datos, (string) $existente['fecha_inicio']);

        $imagenNueva = null;
        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/temporadas');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $imagenNueva = $subida['nombre'];
            }
        }

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/temporadas/formulario', [
                'seccionActiva' => 'temporadas', 'temporada' => $datos, 'errores' => $errores,
            ]);
            return;
        }

        // Conserva la imagen anterior si no se subió una nueva.
        $datos['imagen'] = $imagenNueva ?? $existente['imagen'];

        $modelo->actualizar((int) $id, $datos);

        if ($imagenNueva !== null && $existente['imagen'] !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/temporadas', $existente['imagen']);
        }

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

        if ($temporada['imagen'] !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/temporadas', $temporada['imagen']);
        }

        AuditoriaService::registrar('temporadas', (int) $id, 'eliminar', $temporada, null);

        Flash::exito('Temporada eliminada correctamente.');
        $this->redirigir('/admin/temporadas');
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'nombre'       => trim(Entrada::texto($_POST, 'nombre', '')),
            'descripcion'  => trim(Entrada::texto($_POST, 'descripcion', '')) ?: null,
            'fecha_inicio' => Entrada::texto($_POST, 'fecha_inicio', ''),
            'fecha_fin'    => Entrada::texto($_POST, 'fecha_fin', ''),
            'activo'       => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    /** @param string|null $inicioExistente fecha de inicio ya guardada (al editar) */
    private function validar(array $datos, ?string $inicioExistente = null): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 100)
            ->longitudMaxima('descripcion', 'Descripción', 255)
            ->requerido('fecha_inicio', 'Fecha de inicio')
            ->fecha('fecha_inicio', 'Fecha de inicio')
            ->requerido('fecha_fin', 'Fecha de fin')
            ->fecha('fecha_fin', 'Fecha de fin');

        $errores = $validador->errores();

        $inicioValido = Validator::esFechaValida($datos['fecha_inicio']);
        $finValido = Validator::esFechaValida($datos['fecha_fin']);

        if ($inicioValido && $finValido && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        // Temporada nueva: no puede empezar en el pasado. Al editar una que ya empezó, su inicio no cambia.
        if ($inicioValido && $datos['fecha_inicio'] < date('Y-m-d') && $datos['fecha_inicio'] !== $inicioExistente) {
            $errores[] = 'La fecha de inicio no puede ser anterior a hoy.';
        }

        return $errores;
    }
}
