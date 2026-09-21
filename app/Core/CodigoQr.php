<?php

declare(strict_types=1);

namespace App\Core;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * Genera la imagen QR de un código de recompensa en el propio servidor con
 * la librería local chillerlan/php-qrcode (vendor/): ningún dato del cliente
 * sale hacia servicios de terceros. La salida es SVG (vectorial, nítido en
 * cualquier pantalla) y no necesita la extensión GD.
 */
class CodigoQr
{
    /**
     * Devuelve un data URI (`data:image/svg+xml;base64,...`) listo para
     * `<img src>`, o null si la librería no está disponible o falla — la
     * pantalla siempre muestra además el código como texto, así que sin QR
     * el canje por código manual sigue funcionando.
     */
    public static function dataUri(string $texto): ?string
    {
        if (!class_exists(QRCode::class)) {
            Logger::error('QR no disponible: falta vendor/ (ejecutar "composer install").');
            return null;
        }

        try {
            $opciones = new QROptions([
                'outputType'   => QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                // Nivel M: tolera ~15 % de daño/reflejo y sigue siendo un QR chico y fácil de leer.
                'eccLevel'     => EccLevel::M,
                'addQuietzone' => true,
                'scale'        => 10,
            ]);

            return (new QRCode($opciones))->render($texto);
        } catch (Throwable $e) {
            Logger::error('No se pudo generar el QR: ' . $e->getMessage());
            return null;
        }
    }
}
