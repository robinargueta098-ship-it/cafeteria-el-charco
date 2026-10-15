<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\ClienteAuth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Entrada;
use App\Core\Logger;
use App\Models\Cliente;
use App\Services\PedidoService;
use Throwable;

/**
 * Recibe el carrito del sitio público y lo guarda como pedido (PedidoService). Responde JSON: el navegador solo
 * abre WhatsApp cuando el pedido ya quedó guardado, con el código y los totales que calculó el servidor.
 */
class PedidoController extends Controller
{
    /** Token CSRF vigente: si el cliente inició sesión en el panel desde el mismo navegador, el de la página abierta quedó viejo. */
    public function token(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode(['csrf' => Csrf::token()]);
    }

    /** «Sí, ya lo envié»: el pedido pasa de `pendiente` a `enviado`. */
    public function confirmar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $resultado = PedidoService::confirmarEnvio(Entrada::texto($_POST, 'token', ''));
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function crear(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $items = json_decode(Entrada::texto($_POST, 'items', '[]'), true);

            $totalCliente = Entrada::texto($_POST, 'total_cliente', '');
            $entrada = [
                'token'         => Entrada::texto($_POST, 'token', ''),
                'items'         => is_array($items) ? $items : null,
                'forma_pago'    => Entrada::texto($_POST, 'forma_pago', ''),
                'nombre'        => Entrada::texto($_POST, 'nombre', ''),
                'direccion'     => Entrada::texto($_POST, 'direccion', ''),
                'detalles'      => Entrada::texto($_POST, 'detalles', ''),
                'total_cliente' => preg_match('/^\d{1,9}$/', $totalCliente) ? (int) $totalCliente : null,
                'cliente_id'    => null,
                'telefono'      => null,
            ];

            // Con sesión de lealtad, el pedido se vincula a esa cuenta (sin pedir ningún dato extra).
            if (ClienteAuth::verificado()) {
                $cliente = ClienteAuth::cliente();
                $registro = $cliente !== null ? (new Cliente())->buscarPorId($cliente['id']) : null;
                if ($registro !== null) {
                    $entrada['cliente_id'] = (int) $registro['id'];
                    $entrada['telefono'] = (string) $registro['telefono'];
                }
            }

            $resultado = PedidoService::crear($entrada, (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'), isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null);

            if (empty($resultado['ok'])) {
                $codigo = isset($resultado['error']) ? $resultado['error'] : '';
                http_response_code($codigo === 'limite' ? 429 : ($codigo === 'servidor' ? 500 : 200));
            }
        } catch (Throwable $e) {
            Logger::error('Error en /pedido: ' . $e->getMessage());
            http_response_code(500);
            $resultado = [
                'ok'      => false,
                'error'   => 'servidor',
                'mensaje' => 'No pudimos guardar tu pedido. Tu carrito sigue aquí; inténtalo de nuevo en un momento.',
            ];
        }

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }
}
