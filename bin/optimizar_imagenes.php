<?php

/**
 * Genera las versiones optimizadas (WebP reducido) de las fotos de la
 * galería que ya estaban subidas. Las fotos que se suban desde el panel
 * después de tener GD activo las generan solas; esto sirve para las
 * anteriores.
 *
 * Solo por línea de comandos:
 *   php -d extension=gd bin/optimizar_imagenes.php            (solo las que no tienen)
 *   php -d extension=gd bin/optimizar_imagenes.php --forzar   (regenera todas)
 *
 * (Si tu PHP ya tiene GD activado en php.ini, sobra el "-d extension=gd".)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/config.php';

spl_autoload_register(function (string $clase): void {
    if (!str_starts_with($clase, 'App\\')) {
        return;
    }
    $ruta = APP_PATH . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (file_exists($ruta)) {
        require $ruta;
    }
});

use App\Core\ImagenOptimizada;
use App\Models\Galeria;

if (!ImagenOptimizada::disponible()) {
    fwrite(STDERR, "La extensión GD (con soporte WebP) no está disponible.\n"
        . "Ejecuta:  php -d extension=gd bin/optimizar_imagenes.php\n");
    exit(1);
}

$forzar = in_array('--forzar', $argv, true);
$carpeta = UPLOADS_PATH . '/galeria';
$kb = fn (string $ruta): string => number_format(filesize($ruta) / 1024, 0) . ' KB';

$fotos = (new Galeria())->listarTodas();
if ($fotos === []) {
    echo "No hay fotos en la galería.\n";
    exit(0);
}

$totalOriginal = 0;
$totalVersiones = 0;

foreach ($fotos as $foto) {
    $nombre = $foto['imagen'];
    $original = $carpeta . '/' . $nombre;

    if (!is_file($original)) {
        echo "  [!] {$nombre}: el archivo no existe en disco, se omite.\n";
        continue;
    }

    if ($forzar) {
        ImagenOptimizada::eliminarVersiones($carpeta, $nombre);
    }

    $existentes = ImagenOptimizada::versiones($carpeta, $nombre);
    if ($existentes !== []) {
        echo "  [=] {$nombre}: ya tiene versiones (" . implode(', ', array_keys($existentes)) . " px), se omite.\n";
        continue;
    }

    $generadas = ImagenOptimizada::generar($original);

    if ($generadas === []) {
        echo "  [!] {$nombre}: no se generaron versiones (revisa storage/logs).\n";
        continue;
    }

    $detalle = [];
    foreach ($generadas as $ruta) {
        $detalle[] = basename($ruta) . ' (' . $kb($ruta) . ')';
        $totalVersiones += filesize($ruta);
    }
    $totalOriginal += filesize($original);

    echo "  [+] {$nombre} (" . $kb($original) . ") -> " . implode(', ', $detalle) . "\n";
}

if ($totalOriginal > 0) {
    printf(
        "\nOriginales: %s KB   Versiones generadas: %s KB\n",
        number_format($totalOriginal / 1024, 0),
        number_format($totalVersiones / 1024, 0)
    );
}
