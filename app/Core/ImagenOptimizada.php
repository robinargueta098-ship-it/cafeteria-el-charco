<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Versiones reducidas (WebP) de las fotos, para que el navegador no
 * descargue una foto de 1600 px en un teléfono de 390 px de ancho.
 *
 * Diseñado para DEGRADAR sin romper nada: si el servidor no tiene la
 * extensión GD (como el XAMPP local, con GD apagado), simplemente no se
 * generan versiones y el sitio sigue usando la foto original.
 *
 * Las versiones se guardan junto al original con el patrón
 * `{nombre}-w{ancho}.webp` (ej. `a1b2c3-w640.webp`), así que se pueden
 * descubrir con un glob y borrar junto con la foto, sin tabla adicional.
 */
class ImagenOptimizada
{
    /** Anchos objetivo; nunca se amplía: si el original es más angosto, se usa su propio ancho. */
    private const ANCHOS_OBJETIVO = [640, 1280, 1600];

    /** Calidad WebP. 72 es prácticamente indistinguible a simple vista y pesa ~1/3 que el JPEG del teléfono. */
    private const CALIDAD_WEBP = 72;

    /** Tope de megapíxeles a decodificar: una foto enorme agotaría la memoria (error fatal, no atrapable). */
    private const MAX_MEGAPIXELES = 25;

    public static function disponible(): bool
    {
        return function_exists('imagewebp')
            && function_exists('imagecreatefromjpeg')
            && function_exists('imagecreatefrompng')
            && function_exists('imagecreatefromwebp')
            && function_exists('imagescale');
    }

    /**
     * Genera las versiones reducidas de $rutaOriginal.
     *
     * Nunca lanza: una foto sin versiones optimizadas sigue funcionando
     * (usa el original), así que un fallo aquí no debe tumbar la subida.
     *
     * @return array<int,string> rutas de los archivos generados
     */
    public static function generar(string $rutaOriginal): array
    {
        if (!self::disponible()) {
            return [];
        }

        try {
            return self::generarSinProteccion($rutaOriginal);
        } catch (Throwable $e) {
            Logger::error('No se pudieron generar versiones optimizadas de ' . basename($rutaOriginal) . ': ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,string> */
    private static function generarSinProteccion(string $rutaOriginal): array
    {
        $info = @getimagesize($rutaOriginal);
        if ($info === false) {
            return [];
        }

        [$ancho, $alto, $tipo] = $info;

        if ($ancho * $alto > self::MAX_MEGAPIXELES * 1_000_000) {
            Logger::error('Foto demasiado grande para optimizar (' . $ancho . 'x' . $alto . '): ' . basename($rutaOriginal));
            return [];
        }

        $imagen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($rutaOriginal),
            IMAGETYPE_PNG  => @imagecreatefrompng($rutaOriginal),
            IMAGETYPE_WEBP => @imagecreatefromwebp($rutaOriginal),
            default        => false,
        };

        if ($imagen === false) {
            return [];
        }

        if ($tipo === IMAGETYPE_JPEG) {
            $imagen = self::aplicarOrientacionExif($imagen, $rutaOriginal);
        }

        // WebP exige color verdadero (un PNG de paleta no vale tal cual) y hay que conservar la transparencia.
        imagepalettetotruecolor($imagen);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        $anchoReal = imagesx($imagen);
        $base = pathinfo($rutaOriginal, PATHINFO_FILENAME);
        $carpeta = dirname($rutaOriginal);

        $anchos = array_unique(array_map(fn (int $t) => min($t, $anchoReal), self::ANCHOS_OBJETIVO));
        sort($anchos);

        $generadas = [];
        foreach ($anchos as $anchoDestino) {
            $reducida = $anchoDestino < $anchoReal
                ? imagescale($imagen, $anchoDestino, -1, IMG_BICUBIC)
                : $imagen;

            if ($reducida === false) {
                continue;
            }

            $destino = $carpeta . DIRECTORY_SEPARATOR . $base . '-w' . $anchoDestino . '.webp';
            if (imagewebp($reducida, $destino, self::CALIDAD_WEBP)) {
                $generadas[] = $destino;
            }

            if ($reducida !== $imagen) {
                imagedestroy($reducida);
            }
        }

        imagedestroy($imagen);

        return $generadas;
    }

    /**
     * Las fotos de teléfono suelen guardarse "de lado" con una etiqueta
     * EXIF que dice cómo girarlas; el navegador la respeta al mostrar el
     * original, pero GD la pierde al reescalar. Se aplica el giro antes
     * de reducir para que la versión optimizada quede igual de derecha.
     */
    private static function aplicarOrientacionExif(\GdImage $imagen, string $ruta): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = (int) ($exif['Orientation'] ?? 1);

        $angulo = match ($orientacion) {
            3 => 180,
            6 => -90, // hay que girarla 90° en sentido horario
            8 => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagen;
        }

        $girada = imagerotate($imagen, $angulo, 0);

        return $girada !== false ? $girada : $imagen;
    }

    /**
     * Versiones que existen en disco para una foto.
     *
     * @return array<int,string> ancho => nombre de archivo, ordenado de menor a mayor
     */
    public static function versiones(string $carpetaFisica, string $nombre): array
    {
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $encontradas = [];

        foreach (glob(rtrim($carpetaFisica, '/\\') . DIRECTORY_SEPARATOR . $base . '-w*.webp') ?: [] as $ruta) {
            if (preg_match('/-w(\d+)\.webp$/', $ruta, $m)) {
                $encontradas[(int) $m[1]] = basename($ruta);
            }
        }

        ksort($encontradas);

        return $encontradas;
    }

    /**
     * Atributos para un <img> responsive de esta foto. Si no hay
     * versiones optimizadas, devuelve solo el original (srcset vacío).
     *
     * @return array{src:string,srcset:string}
     */
    public static function atributos(string $carpetaFisica, string $carpetaUrl, string $nombre): array
    {
        $url = rtrim($carpetaUrl, '/');
        $versiones = self::versiones($carpetaFisica, $nombre);

        if ($versiones === []) {
            return ['src' => $url . '/' . $nombre, 'srcset' => ''];
        }

        $partes = [];
        foreach ($versiones as $ancho => $archivo) {
            $partes[] = $url . '/' . $archivo . ' ' . $ancho . 'w';
        }

        // src = la más grande: es lo que usan los navegadores sin soporte de srcset.
        return ['src' => $url . '/' . end($versiones), 'srcset' => implode(', ', $partes)];
    }

    /** Borra las versiones optimizadas de una foto (se llama al eliminarla o reemplazarla). */
    public static function eliminarVersiones(string $carpetaFisica, ?string $nombre): void
    {
        if ($nombre === null || $nombre === '') {
            return;
        }

        foreach (self::versiones($carpetaFisica, $nombre) as $archivo) {
            @unlink(rtrim($carpetaFisica, '/\\') . DIRECTORY_SEPARATOR . $archivo);
        }
    }
}
