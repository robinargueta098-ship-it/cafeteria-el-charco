<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Entrada;
use App\Core\ClienteAuth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Middleware\ClienteAuthMiddleware;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\CuponPromocion;
use App\Models\HorarioAtencion;
use App\Models\Promocion;
use App\Models\Recompensa;
use App\Services\CuponPromocionService;
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
        $telefono = Cliente::normalizarTelefono(Entrada::texto($_POST, 'telefono', ''));
        $nombre = trim(Entrada::texto($_POST, 'nombre', ''));
        $pin = Entrada::texto($_POST, 'pin', '');
        $confirmarPin = Entrada::texto($_POST, 'confirmar_pin', '');

        $validador = new Validator(['telefono' => $telefono, 'nombre' => $nombre]);
        $validador
            ->requerido('telefono', 'Teléfono')
            ->longitudMaxima('telefono', 'Teléfono', 20)
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120);

        $errores = $validador->errores();

        if ($telefono !== '' && !Cliente::telefonoValido($telefono)) {
            $errores[] = Cliente::MENSAJE_TELEFONO;
        }

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

        ClienteAuth::abrirSesion($clienteId, (string) $nombreCliente, $pinHash);

        $this->redirigir('/cuenta');
    }

    public function mostrarLogin(): void
    {
        if (ClienteAuth::verificado()) {
            $this->redirigir('/cuenta');
        }

        // Aviso de una sola lectura (ej. "Tu sesión expiró"), puesto por CsrfMiddleware.
        $this->vistaCuenta('public/cuenta/login', ['error' => Flash::tomar()['mensaje'] ?? null]);
    }

    public function iniciarSesion(): void
    {
        $telefono = Cliente::normalizarTelefono(Entrada::texto($_POST, 'telefono', ''));
        $pin = Entrada::texto($_POST, 'pin', '');

        $resultado = ClienteAuth::intentar($telefono, $pin);

        if (!$resultado['ok']) {
            $this->vistaCuenta('public/cuenta/login', ['error' => $resultado['error']]);
            return;
        }

        // Entró con un PIN temporal (restablecido en persona): antes de ver su cuenta crea uno nuevo.
        $this->redirigir(!empty($resultado['cambiar_pin']) ? '/cuenta/cambiar-pin' : '/cuenta');
    }

    public function mostrarCambiarPin(): void
    {
        if (ClienteAuth::pendienteDeCambiarPin() === null) {
            $this->redirigir('/cuenta/login');
        }

        $this->vistaCuenta('public/cuenta/cambiar_pin', ['error' => null]);
    }

    public function cambiarPin(): void
    {
        $registro = ClienteAuth::pendienteDeCambiarPin();
        if ($registro === null) {
            Flash::error('Esta pantalla ya no es válida. Inicia sesión de nuevo.');
            $this->redirigir('/cuenta/login');
        }

        $pin = Entrada::texto($_POST, 'pin', '');
        $confirmarPin = Entrada::texto($_POST, 'confirmar_pin', '');

        $error = null;
        if (!preg_match('/^\d{4}$/', $pin)) {
            $error = 'El PIN debe ser exactamente de 4 dígitos.';
        } elseif ($pin !== $confirmarPin) {
            $error = 'El PIN y su confirmación no coinciden.';
        } elseif (password_verify($pin, Cliente::hashDelPin($registro))) {
            $error = 'Tu PIN nuevo debe ser distinto al PIN temporal que te dieron.';
        }

        if ($error !== null) {
            $this->vistaCuenta('public/cuenta/cambiar_pin', ['error' => $error]);
            return;
        }

        // El PIN temporal deja de existir: la columna queda con el hash del PIN nuevo (sin marca de temporal).
        $nuevoHash = password_hash($pin, PASSWORD_DEFAULT);
        (new Cliente())->establecerPin((int) $registro['id'], $nuevoHash);
        ClienteAuth::abrirSesion((int) $registro['id'], (string) $registro['nombre'], $nuevoHash);

        Flash::exito('¡Listo! Ya tienes tu PIN nuevo.');
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
        if ($cliente === null) {
            ClienteAuth::expulsar('Tu cuenta ya no está disponible. Si crees que es un error, pregunta en la cafetería.'); // defensa extra: el cliente se borró entre la verificación y aquí
        }
        $umbral = LealtadService::obtenerUmbral();

        // Código personal para sumar cafés. Si falla (ej. falta aplicar la migración
        // 001), la cuenta sigue funcionando sin ese QR en vez de dar un error 500.
        try {
            $codigoQr = $cliente !== null ? (new Cliente())->obtenerCodigoQr($clienteId) : null;
        } catch (\Throwable $e) {
            Logger::error('No se pudo obtener el código QR del cliente ' . $clienteId . ': ' . $e->getMessage());
            $codigoQr = null;
        }

        // Promociones vigentes marcadas "combinable con lealtad" (§4.20): por cada
        // una se busca si este cliente ya reclamó su cupón, para mostrar el botón
        // "Reclamar cupón" o, si ya lo tiene, su código/QR — nunca las dos cosas.
        $promocionesLealtad = (new Promocion())->listarVigentesCombinablesConLealtad();
        $cuponesModelo = new CuponPromocion();
        $cuponesPorPromocion = [];
        foreach ($promocionesLealtad as $promo) {
            $cuponesPorPromocion[(int) $promo['id']] = $cuponesModelo->buscarDeClientePorPromocion($clienteId, (int) $promo['id']);
        }

        $this->vistaCuenta('public/cuenta/panel', [
            'cliente'     => $cliente,
            'codigoQr'    => $codigoQr,
            'umbral'      => $umbral,
            'recompensas' => (new Recompensa())->listarPorCliente($clienteId),
            // Aviso de una sola lectura (ej. tras un "Cerrar sesión" con una pantalla
            // vieja, o al reclamar un cupón), puesto por CsrfMiddleware o por acá mismo.
            'flash'       => Flash::tomar(),
            'promocionesLealtad'  => $promocionesLealtad,
            'cuponesPorPromocion' => $cuponesPorPromocion,
        ]);
    }

    /**
     * El cliente reclama el cupón de una promoción "combinable con lealtad"
     * (§4.20). Una vez por cliente y por promoción — lo exige el UNIQUE de
     * `cupones_promocion`, no solo la comprobación de CuponPromocionService.
     */
    public function reclamarCupon(string $id): void
    {
        ClienteAuthMiddleware::manejar();

        $clienteId = ClienteAuth::cliente()['id'];
        $resultado = CuponPromocionService::reclamar($clienteId, (int) $id);

        if ($resultado['ok']) {
            Flash::exito('¡Listo! Tu cupón ya está disponible abajo, con su código y QR.');
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/cuenta');
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
