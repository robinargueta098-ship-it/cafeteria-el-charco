<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Saca las coordenadas exactas del enlace "Compartir" de Google Maps (el que
 * pega el Administrador en Configuración), para que el mapa de la página
 * quede centrado en el MISMO punto que ese enlace aunque el local cambie de
 * dirección: basta con pegar el enlace nuevo.
 *
 * Solo se siguen enlaces de Google Maps (https) — el Administrador es quien
 * lo escribe, pero así un enlace cualquiera no puede hacer que el servidor
 * visite direcciones arbitrarias.
 */
class GoogleMaps
{
    private const HOSTS_PERMITIDOS = ['maps.app.goo.gl', 'goo.gl', 'www.google.com', 'google.com', 'maps.google.com'];

    /**
     * @return string|null "latitud,longitud" (ej. "15.0176489,-91.1525818"), o null si no se pudo
     */
    public static function coordenadasDesdeEnlace(string $enlace): ?string
    {
        $enlace = trim($enlace);
        if ($enlace === '' || !self::esEnlacePermitido($enlace) || !function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($enlace);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ElCharcoSitio/1.0)',
        ]);
        $cuerpo = curl_exec($curl);
        $urlFinal = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        curl_close($curl);

        if (!self::esEnlacePermitido($urlFinal)) {
            return null;
        }

        // 1) El pin del lugar en la URL final: !3d<lat>!4d<lng> (es el punto exacto del negocio).
        if (preg_match('/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/', urldecode($urlFinal), $m)) {
            return $m[1] . ',' . $m[2];
        }

        // 2) El centro de la vista: @<lat>,<lng>
        if (preg_match('/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/', $urlFinal, $m)) {
            return $m[1] . ',' . $m[2];
        }

        // 3) Último recurso: la imagen de vista previa de la propia página.
        if (is_string($cuerpo) && preg_match('/center=(-?\d{1,3}\.\d+)(?:%2C|,)(-?\d{1,3}\.\d+)/', $cuerpo, $m)) {
            return $m[1] . ',' . $m[2];
        }

        return null;
    }

    private static function esEnlacePermitido(string $url): bool
    {
        $partes = parse_url($url);
        if ($partes === false || ($partes['scheme'] ?? '') !== 'https') {
            return false;
        }

        return in_array(strtolower((string) ($partes['host'] ?? '')), self::HOSTS_PERMITIDOS, true);
    }
}
