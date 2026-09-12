<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\ClienteAuth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\ClienteAuthMiddleware;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\HorarioAtencion;
use App\Models\Recompensa;
use App\Services\LealtadService;

/**
 * Cuenta simple del cliente (teléfono + PIN de 4 dígitos, sin correo
 * ni verificación) — solo para que vea su propio progreso de lealtad.
 * No cambia la regla de negocio ni permite operar saldo: eso lo sigue
 * haciendo el cajero desde el panel (docs/arquitectura.md).
 */
class CuentaController extends Controller
{
    public function mostrarRegistro(): void
    {
        if (ClienteAuth::verificado()) {
            $this->redirigir('/cuenta');
        }

        $this->vistaCuenta('public/cuenta/registro', ['error' => null, 'valores' => []]);
    }

    public function registrar(): void
    {
        $telefono = Cliente::normalizarTelefono((string) ($_POST['telefono'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $pin = (string) ($_POST['pin'] ?? '');
        $confirmarPin = (string) ($_POST['confirmar_pin'] ?? '');

        $validador = new Validator(['telefono' => $telefono, 'nombre' => $nombre]);
        $validador
            ->requerido('telefono', 'Teléfono')
            ->longitudMaxima('telefono', 'Teléfono', 20)
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120);

        $errores = $validador->errores();

        if (!preg_match('/^\d{4}$/', $pin)) {
            $errores[] = 'El PIN debe ser exactamente de 4 dígitos.';
        } elseif ($pin !== $confirmarPin) {
            $errores[] = 'El PIN y su confirmación no coinciden.';
        }

        if ($errores !== []) {
            $this->vistaCuenta('public/cuenta/registro', [
                'error'   => implode(' ', $errores),
                'valores' => ['telefono' => $telefono, 'nombre' => $nombre],
            ]);
            return;
        }

        $modelo = new Cliente();
        $existente = $telefono !== '' ? $modelo->buscarPorTelefono($telefono) : null;
        $pinHash = password_hash($pin, PASSWORD_DEFAULT);

        if ($existente !== null && !empty($existente['pin_hash'])) {
            $this->vistaCuenta('public/cuenta/registro', [
                'error'   => 'Ya existe una cuenta con ese teléfono. Inicia sesión en vez de registrarte de nuevo.',
                'valores' => ['telefono' => $telefono, 'nombre' => $nombre],
            ]);
            return;
        }

        if ($existente !== null) {
            // Ya era cliente (lo registró un cajero) pero sin cuenta todavía: se activa el acceso, sin tocar su historial.
            $modelo->establecerPin((int) $existente['id'], $pinHash);
            $clienteId = (int) $existente['id'];
            $nombreCliente = $existente['nombre'];
        } else {
            $clienteId = $modelo->crear(['nombre' => $nombre, 'telefono' => $telefono, 'email' => null, 'notas' => null]);
            $modelo->establecerPin($clienteId, $pinHash);
            $nombreCliente = $nombre;
        }

        Session::regenerar();
        $_SESSION['cliente_id'] = $clienteId;
        $_SESSION['cliente_nombre'] = $nombreCliente;
        $modelo->registrarAccesoClienteExitoso($clienteId);

        $this->redirigir('/cuenta');
    }

    public function mostrarLogin(): void
    {
        if (ClienteAuth::verificado()) {
            $this->redirigir('/cuenta');
        }

        $this->vistaCuenta('public/cuenta/login', ['error' => null]);
    }

    public function iniciarSesion(): void
    {
        $telefono = Cliente::normalizarTelefono((string) ($_POST['telefono'] ?? ''));
        $pin = (string) ($_POST['pin'] ?? '');

        $resultado = ClienteAuth::intentar($telefono, $pin);

        if (!$resultado['ok']) {
            $this->vistaCuenta('public/cuenta/login', ['error' => $resultado['error']]);
            return;
        }

        $this->redirigir('/cuenta');
    }

    public function cerrarSesion(): void
    {
        ClienteAuth::cerrarSesion();
        $this->redirigir('/cuenta/login');
    }

    public function panel(): void
    {
        ClienteAuthMiddleware::manejar();

        // Siempre a partir de la sesión, nunca de un id en la URL —
        // así un cliente nunca puede ver el progreso de otro.
        $clienteId = ClienteAuth::cliente()['id'];

        $cliente = (new Cliente())->buscarPorId($clienteId);
        $umbral = LealtadService::obtenerUmbral();

        $this->vistaCuenta('public/cuenta/panel', [
            'cliente'     => $cliente,
            'umbral'      => $umbral,
            'recompensas' => (new Recompensa())->listarPorCliente($clienteId),
        ]);
    }

    /**
     * Las vistas de cuenta reutilizan el layout público (header/footer
     * con nav, WhatsApp y redes), que espera $config y $horarios.
     */
    private function vistaCuenta(string $vista, array $datos): void
    {
        $this->vista($vista, $datos + [
            'config'   => (new Configuracion())->obtenerTodas(),
            'horarios' => (new HorarioAtencion())->listarTodos(),
        ]);
    }
}
