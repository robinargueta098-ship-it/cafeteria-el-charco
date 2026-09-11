<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Galeria;
use App\Services\AuditoriaService;

class GaleriaController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/galeria/index', [
            'seccionActiva' => 'galeria',
            'fotos'         => (new Galeria())->listarTodas(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1);
        $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => null, 'errores' => []]);
    }

    public function crear(): void
    {
        $this->requerirRol(1);

        $datos = $this->datosDelFormulario();
        $errores = [];

        if (($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errores[] = 'Selecciona una foto para subir.';
        } else {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/galeria');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $datos['imagen'] = $subida['nombre'];
            }
        }

        $errores = array_merge($errores, $this->validar($datos));

        if ($errores !== []) {
            $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => $datos, 'errores' => $errores]);
            return;
        }

        $id = (new Galeria())->crear($datos);
        AuditoriaService::registrar('galeria', $id, 'crear', null, $datos);

        Flash::exito('Foto agregada a la galería.');
        $this->redirigir('/admin/galeria');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirRol(1);

        $foto = (new Galeria())->buscarPorId((int) $id);
        if ($foto === null) {
            $this->error404();
        }

        $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => $foto, 'errores' => []]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Galeria();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();
        $imagenNueva = null;

        if (($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/galeria');
            if (!$subida['ok']) {
                $datos['id'] = $id;
                $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => $datos, 'errores' => [$subida['error']]]);
                return;
            }
            $imagenNueva = $subida['nombre'];
        }

        $errores = $this->validar($datos);
        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => $datos, 'errores' => $errores]);
            return;
        }

        $datos['imagen'] = $imagenNueva ?? $existente['imagen'];
        $modelo->actualizar((int) $id, $datos);

        if ($imagenNueva !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/galeria', $existente['imagen']);
        }

        AuditoriaService::registrar('galeria', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Foto actualizada correctamente.');
        $this->redirigir('/admin/galeria');
    }

    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Galeria();
        $foto = $modelo->buscarPorId((int) $id);
        if ($foto === null) {
            $this->error404();
        }

        $modelo->eliminar((int) $id);
        SubidaArchivo::eliminar(UPLOADS_PATH . '/galeria', $foto['imagen']);
        AuditoriaService::registrar('galeria', (int) $id, 'eliminar', $foto, null);

        Flash::exito('Foto eliminada correctamente.');
        $this->redirigir('/admin/galeria');
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'titulo'      => trim((string) ($_POST['titulo'] ?? '')) ?: null,
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'orden'       => (int) ($_POST['orden'] ?? 0),
            'activo'      => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador->longitudMaxima('titulo', 'Título', 120)->longitudMaxima('descripcion', 'Descripción', 255);

        return $validador->errores();
    }
}
