<?php

/**
 * Revisa que los números de los pedidos cuadren entre sí y termina con «TODO CUADRA» o con la lista exacta de
 * inconsistencias. Solo por consola:
 *
 *   php bin/verificar_ventas.php
 *
 * Código de salida: 0 = todo cuadra, 1 = hay inconsistencias. (El Administrador también la ve en
 * Reportes → «Verificación de datos».)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/config.php';

spl_autoload_register(function (string $clase): void {
    if (strpos($clase, 'App\\') !== 0) {
        return;
    }
    $ruta = APP_PATH . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (file_exists($ruta)) {
        require $ruta;
    }
});

$resultado = App\Services\VerificacionVentas::ejecutar();

echo "Verificación de pedidos — " . date('Y-m-d H:i:s') . " (hora de Guatemala)\n";
echo "Pedidos revisados: " . $resultado['pedidos'] . "\n\n";
foreach ($resultado['revisiones'] as $nombre => $n) {
    echo ($n === 0 ? '  [OK]    ' : '  [ERROR] ') . $nombre . ($n === 0 ? '' : ': ' . $n) . "\n";
}
echo "\n";

if ($resultado['hallazgos'] === []) {
    echo "TODO CUADRA\n";
    exit(0);
}

echo "INCONSISTENCIAS ENCONTRADAS:\n";
foreach ($resultado['hallazgos'] as $h) {
    echo '  - ' . $h['revision'] . ' | ' . $h['detalle'] . "\n";
}
exit(1);
