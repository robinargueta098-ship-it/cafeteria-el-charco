<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
use App\Models\PortadaImagen;
use App\Services\AuditoriaService;

/**
 * Imágenes del fondo automático de la portada. Se administran desde la
 * pantalla de Configuración (tarjeta "Imágenes de portada"), pero viven
 * en su propia tabla (`portada_imagenes`) y su propia carpeta
 * (`public/uploads/portada/`), separadas de la Galería.
 *
 * Todas las acciones son POST propios (no dependen del botón "Guardar
 * configuración" del formulario grande) y regresan a la misma tarjeta.
 */
class PortadaController extends Controller
{
    /**
     * Tope de imágenes en el carrusel. El hero descarga cada una durante
     * su ciclo, así que un número ilimitado terminaría pesando en la
     * página; 8 da variedad de sobra.
     */
    public const MAX_IMAGENES = 8;

    /** Tope por envío; 10 × 5 MB = 50 MB, el resto lo limita post_max_size (ver CsrfMiddleware). */
    public const MAX_ARCHIVOS_POR_LOTE = 10;

    private const DESTINO = '/admin/configuracion#portada';

    /** Sube una o varias imágenes; cada una se valida por separado. */
    public function subir(): void
    {
        $this->requerirRol(1);

        $archivos = SubidaArchivo::normalizarMultiples($_FILES['imagenes'] ?? null);
        $modelo = new PortadaImagen();
        $cupo = self::MAX_IMAGENES - $modelo->contar();

        if ($archivos === []) {
            Flash::error('Selecciona al menos una imagen para subir.');
            $this->redirigir(self::DESTINO);
        }

        if ($cupo <= 0) {
            Flash::error('Ya tienes ' . self::MAX_IMAGENES . ' imágenes de portada, que es el máximo. Elimina alguna para poder agregar otra.');
            $this->redirigir(self::DESTINO);
        }

        $limiteEnvio = min(self::MAX_ARCHIVOS_POR_LOTE, $cupo);
        if (count($archivos) > $limiteEnvio) {
            Flash::error("Elegiste " . count($archivos) . " imágenes, pero ahora solo puedes agregar {$limiteEnvio} más (máximo " . self::MAX_IMAGENES . ' en total).');
            $this->redirigir(self::DESTINO);
        }

        $subidas = 0;
        $fallidas = [];

        foreach ($archivos as $archivo) {
            // true = si el servidor tiene GD, además crea las versiones WebP reducidas.
            $resultado = SubidaArchivo::guardarImagen($archivo, UPLOADS_PATH . '/portada', true);

            if (!$resultado['ok']) {
                $fallidas[] = SubidaArchivo::nombreParaMostrar($archivo['name']) . ' (' . rtrim($resultado['error'], '.') . ')';
                continue;
            }

            $id = $modelo->crear($resultado['nombre']);
            AuditoriaService::registrar('portada_imagenes', $id, 'crear', null, ['imagen' => $resultado['nombre']]);
            $subidas++;
        }

        if ($subidas === 0) {
            Flash::error('No se pudo subir: ' . implode('; ', $fallidas) . '.');
            $this->redirigir(self::DESTINO);
        }

        $mensaje = $subidas === 1 ? 'Se agregó 1 imagen a la portada.' : "Se agregaron {$subidas} imágenes a la portada.";
        if ($fallidas !== []) {
            // El mensaje flash es uno solo: el éxito parcial se muestra como éxito,
            // pero con el detalle de lo que falló para que no pase desapercibido.
            $mensaje .= ' No se pudieron subir: ' . implode('; ', $fallidas) . '.';
        }

        Flash::exito($mensaje);
        $this->redirigir(self::DESTINO);
    }

    /** Sube o baja una imagen una posición (flechas ▲▼). */
    public function mover(string $id): void
    {
        $this->requerirRol(1);

        $direccion = (string) ($_POST['direccion'] ?? '');
        if (!in_array($direccion, ['subir', 'bajar'], true)) {
            Flash::error('Movimiento inválido.');
            $this->redirigir(self::DESTINO);
        }

        $resultado = (new PortadaImagen())->mover((int) $id, $direccion);

        if ($resultado['resultado'] === 'no_existe') {
            $this->error404();
        }

        if ($resultado['resultado'] === 'movida') {
            AuditoriaService::registrar(
                'portada_imagenes', (int) $id, 'editar',
                ['posicion' => $resultado['desde']], ['posicion' => $resultado['hasta']]
            );
        }

        $this->redirigir(self::DESTINO);
    }

    /** Elimina la imagen: la fila, el archivo y sus versiones optimizadas. */
    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new PortadaImagen();
        $imagen = $modelo->buscarPorId((int) $id);
        if ($imagen === null) {
            $this->error404();
        }

        $modelo->eliminar((int) $id);
        SubidaArchivo::eliminar(UPLOADS_PATH . '/portada', $imagen['imagen']);
        AuditoriaService::registrar('portada_imagenes', (int) $id, 'eliminar', $imagen, null);

        Flash::exito('Imagen eliminada de la portada.');
        $this->redirigir(self::DESTINO);
    }
}
