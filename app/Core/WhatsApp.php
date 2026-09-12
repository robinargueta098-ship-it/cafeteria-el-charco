<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Construye enlaces wa.me a partir del número configurado centralmente
 * (configuracion_sistema.whatsapp_numero) — nunca un número quemado en
 * el HTML, para que cambiarlo desde el panel lo actualice en todo el
 * sitio.
 */
class WhatsApp
{
    public static function enlace(string $numero, string $mensaje = ''): string
    {
        $soloDigitos = preg_replace('/[^0-9]/', '', $numero) ?? '';
        $url = 'https://wa.me/' . $soloDigitos;

        if ($mensaje !== '') {
            $url .= '?text=' . rawurlencode($mensaje);
        }

        return $url;
    }
}
