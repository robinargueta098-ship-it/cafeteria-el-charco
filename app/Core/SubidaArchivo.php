<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validación y guardado seguro de imágenes subidas desde el panel
 * (productos y galería). Reglas de docs/arquitectura.md §7 y §9:
 * whitelist de extensión, tipo MIME real (no el que declara el
 * navegador), verificación de que sea una imagen de verdad, tamaño
 * máximo y nombre aleatorio al guardar (nunca el nombre original).
 */
class SubidaArchivo
{
    private const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    private const MIME_PERMITIDOS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /** Público para que el formulario del panel muestre y valide el mismo límite en el navegador. */
    public const TAMANO_MAXIMO_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * @param array $archivo una entrada de $_FILES (ej. $_FILES['imagen'])
     * @param bool $generarVersiones si es true y el servidor tiene GD, además crea versiones
     *             WebP reducidas (ver ImagenOptimizada) para servirlas según el tamaño de pantalla
     * @return array{ok:bool,error?:string,nombre?:string} nombre = nombre de archivo ya guardado en $carpetaDestino
     */
    public static function guardarImagen(array $archivo, string $carpetaDestino, bool $generarVersiones = false): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No se seleccionó ningún archivo.'];
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo.'];
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            // Defensa contra manipulación directa de la petición (que
            // tmp_name no sea realmente un archivo subido por PHP).
            return ['ok' => false, 'error' => 'Archivo inválido.'];
        }

        if ($archivo['size'] <= 0 || $archivo['size'] > self::TAMANO_MAXIMO_BYTES) {
            return ['ok' => false, 'error' => 'La imagen debe pesar menos de 5 MB.'];
        }

        $extension = strtolower((string) pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return ['ok' => false, 'error' => 'Formato no permitido (solo JPG, PNG o WEBP).'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        if (!isset(self::MIME_PERMITIDOS[$mimeReal]) || !in_array($extension, self::MIME_PERMITIDOS[$mimeReal], true)) {
            // El contenido real del archivo no coincide con lo que dice
            // la extensión (ej. un .php renombrado a .jpg).
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
        }

        // Verificación adicional: que el motor de imágenes de PHP
        // realmente pueda interpretarlo como imagen (defensa extra
        // contra archivos "polyglot").
        if (@getimagesize($archivo['tmp_name']) === false) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
        }

        $nombreGuardado = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaDestino = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombreGuardado;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            Logger::error("No se pudo mover el archivo subido a {$rutaDestino}");
            return ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
        }

        if ($generarVersiones) {
            // Nunca lanza ni hace fallar la subida: sin versiones se usa el original.
            ImagenOptimizada::generar($rutaDestino);
        }

        return ['ok' => true, 'nombre' => $nombreGuardado];
    }

    /**
     * PHP entrega varios archivos como name[]/tmp_name[]/... (un array
     * de columnas). Lo convierte en una lista de archivos individuales
     * con la misma forma que un $_FILES de un solo archivo, y descarta
     * los campos vacíos (ningún archivo elegido).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function normalizarMultiples($entrada): array
    {
        if (!is_array($entrada) || !isset($entrada['name']) || !is_array($entrada['name'])) {
            return [];
        }

        $archivos = [];
        foreach (array_keys($entrada['name']) as $i) {
            if (($entrada['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $archivos[] = [
                'name'     => (string) $entrada['name'][$i],
                'type'     => (string) ($entrada['type'][$i] ?? ''),
                'tmp_name' => (string) ($entrada['tmp_name'][$i] ?? ''),
                'error'    => (int) $entrada['error'][$i],
                'size'     => (int) ($entrada['size'][$i] ?? 0),
            ];
        }

        return $archivos;
    }

    /** El nombre lo elige quien sube el archivo: se recorta antes de mostrarlo en un mensaje. */
    public static function nombreParaMostrar(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));

        return mb_strlen($nombre) > 40 ? mb_substr($nombre, 0, 37) . '…' : $nombre;
    }

    public static function eliminar(string $carpeta, ?string $nombreArchivo): void
    {
        if ($nombreArchivo === null || $nombreArchivo === '') {
            return;
        }

        // basename() evita que un nombre con "../" salga de la carpeta.
        $nombreArchivo = basename($nombreArchivo);
        $ruta = rtrim($carpeta, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (is_file($ruta)) {
            @unlink($ruta);
        }

        // Si la foto tenía versiones optimizadas, se van con ella.
        ImagenOptimizada::eliminarVersiones($carpeta, $nombreArchivo);
    }
}
