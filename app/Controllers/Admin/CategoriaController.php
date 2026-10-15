<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Categoria;
use App\Models\Promocion;
use App\Services\AuditoriaService;

class CategoriaController extends Controller
{
    public function index(): void
    {
        $this->requerirPermiso('categorias', 'ver');

        $this->vista('admin/categorias/index', [
            'seccionActiva' => 'categorias',
            'categorias'    => (new Categoria())->listarTodas(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirPermiso('categorias', 'editar');

        $this->vista('admin/categorias/formulario', [
            'seccionActiva' => 'categorias',
            'categoria'     => null,
            'errores'       => [],
        ]);
    }

    public function crear(): void
    {
        $this->requerirPermiso('categorias', 'editar');

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores !== []) {
            $this->vista('admin/categorias/formulario', [
                'seccionActiva' => 'categorias',
                'categoria'     => $datos,
                'errores'       => $errores,
            ]);
            return;
        }

        $modelo = new Categoria();
        $datos['slug'] = $modelo->generarSlugUnico($datos['nombre']);

        $id = $modelo->crear($datos);
        AuditoriaService::registrar('categorias', $id, 'crear', null, $datos);

        Flash::exito('Categoría creada correctamente.');
        $this->redirigir('/admin/categorias');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirPermiso('categorias', 'editar');

        $categoria = (new Categoria())->buscarPorId((int) $id);
        if ($categoria === null) {
            $this->error404();
            return;
        }

        $this->vista('admin/categorias/formulario', [
            'seccionActiva' => 'categorias',
            'categoria'     => $categoria,
            'errores'       => [],
        ]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirPermiso('categorias', 'editar');

        $modelo = new Categoria();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
            return;
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos, (int) $id);

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/categorias/formulario', [
                'seccionActiva' => 'categorias',
                'categoria'     => $datos,
                'errores'       => $errores,
            ]);
            return;
        }

        $datos['slug'] = $modelo->generarSlugUnico($datos['nombre'], (int) $id);
        $modelo->actualizar((int) $id, $datos);
        AuditoriaService::registrar('categorias', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Categoría actualizada correctamente.');
        $this->redirigir('/admin/categorias');
    }

    public function eliminar(string $id): void
    {
        $this->requerirPermiso('categorias', 'editar');

        $modelo = new Categoria();
        $categoria = $modelo->buscarPorId((int) $id);
        if ($categoria === null) {
            $this->error404();
            return;
        }

        $totalProductos = $modelo->contarProductos((int) $id);
        if ($totalProductos > 0) {
            Flash::error(
                "No se puede eliminar: tiene {$totalProductos} producto(s) asociado(s). "
                . 'Desactívala en vez de eliminarla, o mueve los productos a otra categoría primero.'
            );
            $this->redirigir('/admin/categorias');
            return;
        }

        // La FK de promociones.categoria_id tiene ON DELETE CASCADE: sin
        // este chequeo, borrar la categoría borraría en silencio
        // cualquier promoción ligada a ella (sin pasar por este
        // controlador ni quedar en la bitácora de auditoría).
        $totalPromociones = (new Promocion())->contarPorCategoria((int) $id);
        if ($totalPromociones > 0) {
            Flash::error(
                "No se puede eliminar: {$totalPromociones} promoción(es) usan esta categoría como alcance. "
                . 'Elimina o reasigna esas promociones primero.'
            );
            $this->redirigir('/admin/categorias');
            return;
        }

        $modelo->eliminar((int) $id);
        AuditoriaService::registrar('categorias', (int) $id, 'eliminar', $categoria, null);

        Flash::exito('Categoría eliminada correctamente.');
        $this->redirigir('/admin/categorias');
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'nombre'      => trim(Entrada::texto($_POST, 'nombre', '')),
            'descripcion' => $this->nuloSiVacio(Entrada::texto($_POST, 'descripcion')),
            'notas'       => $this->nuloSiVacio(Entrada::texto($_POST, 'notas')),
            'orden'       => (int) ($_POST['orden'] ?? 0),
            'activo'      => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @param array<string,mixed> $datos @return array<int,string> */
    private function validar(array $datos, ?int $idActual = null): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 100)
            ->longitudMaxima('descripcion', 'Descripción', 255)
            ->longitudMaxima('notas', 'Notas', 255);

        $errores = $validador->errores();

        if ($datos['nombre'] !== '' && (new Categoria())->existeNombre($datos['nombre'], $idActual)) {
            $errores[] = 'Ya existe una categoría con ese nombre (no importan las mayúsculas). Usa otro nombre o edita la existente.';
        }

        if ($datos['orden'] < 0 || $datos['orden'] > 9999) {
            $errores[] = 'El orden debe ser un número entre 0 y 9999.';
        }

        return $errores;
    }

    private function nuloSiVacio($valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }
}
