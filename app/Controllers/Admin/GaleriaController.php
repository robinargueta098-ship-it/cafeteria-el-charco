<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\ImagenOptimizada;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Galeria;
use App\Services\AuditoriaService;

class GaleriaController extends Controller
{
    /**
     * Tope de fotos por envío. Con 5 MB por foto, 10 son 50 MB como
     * máximo: el resto del límite lo pone post_max_size del servidor
     * (ver App\Middleware\CsrfMiddleware, que avisa si se excede).
     */
    private const MAX_ARCHIVOS_POR_LOTE = 10;

    public function index(): void
    {
        $this->requerirRol(1);

        $modelo = new Galeria();

        $this->vista('admin/galeria/index', [
            'seccionActiva' => 'galeria',
            'fotos'         => $modelo->listarTodas(),
            'totalActivas'  => $modelo->contarActivas(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1);
        $this->vistaSubir([]);
    }

    /** Sube una o varias fotos de una vez; cada una se valida por separado. */
    public function crear(): void
    {
        $this->requerirRol(1);

        $archivos = SubidaArchivo::normalizarMultiples($_FILES['imagenes'] ?? null);

        if ($archivos === []) {
            $this->vistaSubir(['Selecciona al menos una foto para subir.']);
            return;
        }

        if (count($archivos) > self::MAX_ARCHIVOS_POR_LOTE) {
            $this->vistaSubir(['Puedes subir hasta ' . self::MAX_ARCHIVOS_POR_LOTE . ' fotos a la vez.']);
            return;
        }

        $modelo = new Galeria();
        $subidas = 0;
        $fallidas = [];

        foreach ($archivos as $archivo) {
            $resultado = SubidaArchivo::guardarImagen($archivo, UPLOADS_PATH . '/galeria', true);

            if (!$resultado['ok']) {
                $fallidas[] = SubidaArchivo::nombreParaMostrar($archivo['name']) . ' (' . rtrim($resultado['error'], '.') . ')';
                continue;
            }

            $datos = [
                'imagen'      => $resultado['nombre'],
                'titulo'      => null,
                'descripcion' => null,
                'orden'       => $modelo->siguienteOrden(),
                'activo'      => 1,
            ];

            $id = $modelo->crear($datos);
            AuditoriaService::registrar('galeria', $id, 'crear', null, $datos);
            $subidas++;
        }

        if ($subidas === 0) {
            $this->vistaSubir(array_map(fn (string $f) => "No se pudo subir: {$f}.", $fallidas));
            return;
        }

        $mensaje = $subidas === 1 ? 'Se agregó 1 foto a la galería.' : "Se agregaron {$subidas} fotos a la galería.";
        if ($fallidas !== []) {
            $mensaje .= ' No se pudieron subir: ' . implode('; ', $fallidas) . '.';
        }

        // El mensaje flash es uno solo: si hubo éxito parcial se muestra como éxito
        // pero con el detalle de lo que falló, para que no pase desapercibido.
        Flash::exito($mensaje);
        $this->redirigir('/admin/galeria');
    }

    /** Sube o baja una foto una posición (flechas ▲▼ del listado). */
    public function mover(string $id): void
    {
        $this->requerirRol(1);

        $direccion = Entrada::texto($_POST, 'direccion', '');
        if (!in_array($direccion, ['subir', 'bajar'], true)) {
            Flash::error('Movimiento inválido.');
            $this->redirigir('/admin/galeria');
        }

        $resultado = (new Galeria())->mover((int) $id, $direccion);

        if ($resultado['resultado'] === 'no_existe') {
            $this->error404();
        }

        if ($resultado['resultado'] === 'movida') {
            AuditoriaService::registrar(
                'galeria', (int) $id, 'editar',
                ['posicion' => $resultado['desde']], ['posicion' => $resultado['hasta']]
            );
        }

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
        // El orden ya no se escribe a mano (se cambia con las flechas del
        // listado): se conserva el actual.
        $datos['orden'] = (int) $existente['orden'];
        $imagenNueva = null;

        if (($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/galeria', true);
            if (!$subida['ok']) {
                $datos['id'] = $id;
                $datos['imagen'] = $existente['imagen'];
                $this->vista('admin/galeria/formulario', ['seccionActiva' => 'galeria', 'foto' => $datos, 'errores' => [$subida['error']]]);
                return;
            }
            $imagenNueva = $subida['nombre'];
        }

        $errores = $this->validar($datos);
        if ($errores !== []) {
            if ($imagenNueva !== null) {
                SubidaArchivo::eliminar(UPLOADS_PATH . '/galeria', $imagenNueva);
            }
            $datos['id'] = $id;
            $datos['imagen'] = $existente['imagen'];
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

    /** @param array<int,string> $errores */
    private function vistaSubir(array $errores): void
    {
        $this->vista('admin/galeria/subir', [
            'seccionActiva' => 'galeria',
            'errores'       => $errores,
            'maxArchivos'   => self::MAX_ARCHIVOS_POR_LOTE,
            'maxBytes'      => SubidaArchivo::TAMANO_MAXIMO_BYTES,
            'optimiza'      => ImagenOptimizada::disponible(),
        ]);
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'titulo'      => trim(Entrada::texto($_POST, 'titulo', '')) ?: null,
            'descripcion' => trim(Entrada::texto($_POST, 'descripcion', '')) ?: null,
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
