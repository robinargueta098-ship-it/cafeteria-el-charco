<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Cliente;
use App\Models\CuponPromocion;
use App\Models\LealtadMovimiento;
use App\Models\Producto;
use App\Models\Recompensa;
use App\Services\AuditoriaService;
use App\Services\CuponPromocionService;
use App\Services\LealtadService;

class LealtadController extends Controller
{
    public function index(): void
    {
        $this->requerirPermiso('lealtad', 'ver');

        $termino = trim(Entrada::texto($_GET, 'q', ''));

        $this->vista('admin/lealtad/buscar', [
            'seccionActiva' => 'lealtad',
            'clientes'      => $termino !== '' ? (new Cliente())->buscar($termino) : [],
            'termino'       => $termino,
        ]);
    }

    public function mostrarCliente(string $id): void
    {
        $this->requerirPermiso('lealtad', 'ver');

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $this->vista('admin/lealtad/cliente', [
            'seccionActiva' => 'lealtad',
            'cliente'       => $cliente,
            'umbral'        => LealtadService::obtenerUmbral(),
            'historial'     => (new LealtadMovimiento())->historialPorCliente((int) $id),
            'recompensas'   => (new Recompensa())->listarPorCliente((int) $id),
        ]);
    }

    public function registrarConsumo(string $id): void
    {
        $this->requerirPermiso('lealtad', 'editar');

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $lectura = LealtadService::leerCantidad($_POST['cantidad'] ?? '', LealtadService::MAX_CAFES_POR_CONSUMO, 'registrar en un solo consumo');
        if (!$lectura['ok']) {
            Flash::error($lectura['error']);
            $this->redirigir('/admin/lealtad/' . $id);
        }
        $cantidad = $lectura['valor'];
        $motivo = trim(Entrada::texto($_POST, 'motivo', ''));
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarConsumo((int) $id, $cantidad, $usuario['id'], $motivo);

        if ($resultado['ok']) {
            $mensaje = "Se registraron {$cantidad} café(s) para {$cliente['nombre']}.";
            if ($resultado['codigos_generados'] !== []) {
                $mensaje .= ' ' . LealtadService::textoRecompensasGeneradas($resultado['codigos_generados']);
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    public function registrarAjuste(string $id): void
    {
        $this->requerirRol(1); // solo Administrador: el Cajero no corrige saldos

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $tipo = Entrada::texto($_POST, 'tipo', '');
        $lectura = LealtadService::leerCantidad($_POST['cantidad'] ?? '', LealtadService::MAX_CAFES_POR_AJUSTE, 'ajustar de una sola vez');
        if (!$lectura['ok']) {
            Flash::error($lectura['error']);
            $this->redirigir('/admin/lealtad/' . $id);
        }
        $cantidad = $lectura['valor'];
        $motivo = trim(Entrada::texto($_POST, 'motivo', ''));
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarAjuste((int) $id, $cantidad, $tipo, $motivo, $usuario['id']);

        if ($resultado['ok']) {
            $mensaje = 'Ajuste registrado correctamente.';
            if (!empty($resultado['codigos_revocados'])) {
                $mensaje .= ' Se dejó sin efecto el café gratis ' . implode(', ', $resultado['codigos_revocados'])
                    . ' (aún no se había canjeado) para que el saldo cuadre.';
            }
            if ($resultado['codigos_generados'] !== []) {
                $mensaje .= ' ' . LealtadService::textoRecompensasGeneradas($resultado['codigos_generados']);
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    /**
     * Restablece el PIN de la cuenta de un cliente, solo en persona: genera un PIN temporal
     * aleatorio de 4 dígitos y se lo muestra UNA vez a quien lo restablece. Administrador y Cajero.
     *
     * El PIN temporal vive en claro solo en esta petición: se guarda hasheado, la pantalla se
     * entrega sin caché y ni el log ni Auditoría lo reciben (Auditoría solo anota quién, a qué
     * cliente y cuándo). Al cambiar el hash, todas las sesiones abiertas del cliente dejan de valer
     * (ClienteAuth::validarSesion), y el siguiente inicio de sesión lo obliga a crear un PIN nuevo.
     */
    public function restablecerPin(string $id): void
    {
        $this->requerirPermiso('lealtad', 'editar');

        $modelo = new Cliente();
        $cliente = $modelo->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        if (empty($cliente['pin_hash'])) {
            Flash::error('Este cliente todavía no tiene cuenta en el sitio, así que no hay PIN que restablecer.');
            $this->redirigir('/admin/lealtad/' . $id);
        }

        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $modelo->establecerPinTemporal((int) $id, password_hash($pin, PASSWORD_DEFAULT));

        // Sin el PIN: solo la acción. Quién (usuario_id), a qué cliente (registro_id) y cuándo (creado_en) los pone el servicio.
        AuditoriaService::registrar('clientes', (int) $id, 'editar', null, ['accion' => 'restablecer_pin']);

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $this->vista('admin/lealtad/pin_temporal', ['cliente' => $cliente, 'pin' => $pin]);
    }

    /**
     * Pantalla de canje: escáner de QR con cámara y, como respaldo, el campo
     * para escribir el código a mano. Ambos terminan en el mismo formulario.
     */
    public function mostrarCanjear(): void
    {
        $this->requerirPermiso('canjear', 'ver');

        // Resultado de la consulta anterior (se guarda al responder un POST y se muestra UNA
        // sola vez): al recargar, la pantalla vuelve limpia en vez de repetir el envío.
        $estado = $_SESSION['_canje'] ?? null;
        unset($_SESSION['_canje']);
        if (is_array($estado)) {
            $this->vistaCanjear(
                (string) $estado['codigo'], (string) $estado['telefono'], $estado['recompensa'],
                $estado['error'], $estado['clienteEscaneado'], $estado['cupon']
            );
            return;
        }

        $codigo = Recompensa::normalizarCodigo(Entrada::texto($_GET, 'codigo', ''));
        $telefono = Cliente::normalizarTelefono(Entrada::texto($_GET, 'telefono', ''));

        // Venir desde la ficha de un cliente ("Canjear") trae solo el teléfono: se identifica de una vez.
        if ($codigo === '' && $telefono !== '') {
            $this->identificarPorTelefono($telefono);
            return;
        }

        $this->vistaCanjear($codigo, $telefono, null, null);
    }

    /**
     * Alternativa al QR cuando el cliente no trae su teléfono/código a la mano: el cajero
     * escribe el número y se identifica al cliente igual que si hubiera escaneado su QR
     * personal (mismo formulario para sumar cafés, mismas recompensas y cupones por canjear,
     * mismos permisos y auditoría). El código personal se asocia al teléfono en el servidor.
     */
    private function identificarPorTelefono(string $telefono): void
    {
        $cliente = (new Cliente())->buscarPorTelefono($telefono);
        if ($cliente === null) {
            $this->vistaCanjear('', $telefono, null, 'No hay ningún cliente registrado con ese teléfono.');
            return;
        }

        $cliente['codigo_qr'] = (new Cliente())->obtenerCodigoQr((int) $cliente['id']);
        $this->vistaCanjear('', $telefono, null, null, $cliente);
    }

    /**
     * Paso 1: consultar (sin canjear todavía). Sirve igual para el código
     * leído por la cámara que para el escrito a mano: el código identifica
     * al cliente (es único y pertenece a uno solo) y se le muestra al
     * cajero para que confirme. El teléfono es opcional: si lo escribe, el
     * código debe ser de ese cliente.
     */
    public function consultarCodigo(): void
    {
        $this->requerirPermiso('canjear', 'ver');

        $entrada = Entrada::texto($_POST, 'codigo', '');
        $telefono = Cliente::normalizarTelefono(Entrada::texto($_POST, 'telefono', ''));
        $codigo = Cliente::normalizarCodigoQr($entrada); // limpieza genérica: mayúsculas y solo A-Z0-9

        if ($codigo === '') {
            if ($telefono !== '') {
                $this->identificarPorTelefono($telefono);
                return;
            }
            $this->vistaCanjear('', $telefono, null, 'Escanea el QR, escribe el código o busca al cliente por su teléfono.');
            return;
        }

        // Un mismo escáner/campo sirve para TRES tipos de código: el QR personal del
        // cliente (suma un café), el de una recompensa de café gratis (canjea) y,
        // desde §4.20, el de un cupón de promoción (canjea). Los tres son de 8
        // caracteres del mismo alfabeto (docs/arquitectura.md §4.15), así que no hay
        // forma de saber cuál es sin buscar: se prueba primero como código personal —
        // es el uso del día a día, mucho más frecuente que canjear cualquiera de los
        // otros dos — luego como recompensa y, si tampoco, como cupón de promoción.
        $cliente = (new Cliente())->buscarPorCodigoQr($codigo);
        if ($cliente !== null) {
            $this->mostrarClienteEscaneado($cliente, $codigo, $telefono);
            return;
        }

        [$recompensa, $errorRecompensa] = $this->buscarRecompensaConTelefono($codigo, $telefono);
        if ($recompensa !== null || $errorRecompensa !== null) {
            $this->vistaCanjear($codigo, $telefono, $recompensa, $errorRecompensa);
            return;
        }

        [$cupon, $errorCupon] = $this->buscarCuponConTelefono($codigo, $telefono);
        if ($cupon !== null || $errorCupon !== null) {
            $this->vistaCanjear($codigo, $telefono, null, $errorCupon, null, $cupon);
            return;
        }

        // Ninguno de los tres: no hay forma de saber cuál esperaba el cajero.
        $this->vistaCanjear($codigo, $telefono, null, 'No se encontró nada con ese código. Revisa que esté bien escrito.');
    }

    /**
     * QR personal ya encontrado: se muestra a quién pertenece (y si tiene
     * recompensas por canjear) para que el cajero confirme cuántos cafés sumar.
     */
    private function mostrarClienteEscaneado(array $cliente, string $codigoQr, string $telefono): void
    {
        if ($telefono !== '' && $telefono !== Cliente::normalizarTelefono((string) $cliente['telefono'])) {
            $this->vistaCanjear($codigoQr, $telefono, null, 'Ese código no pertenece al cliente con ese teléfono.');
            return;
        }

        $this->vistaCanjear($codigoQr, $telefono, null, null, $cliente);
    }

    /**
     * Suma cafés a partir del QR personal. El cliente se resuelve en el
     * servidor desde el código (nada de un id enviado por el formulario), y
     * el registro pasa por LealtadService como cualquier otro consumo.
     */
    public function sumarConsumoPorQr(): void
    {
        $this->requerirPermiso('canjear', 'editar');

        $codigoQr = Cliente::normalizarCodigoQr(Entrada::texto($_POST, 'codigo_qr', ''));
        $motivo = 'Escaneo de QR personal'; // compra normal: no se le pide motivo al cajero
        $usuario = Auth::usuario();

        $cliente = $codigoQr !== '' ? (new Cliente())->buscarPorCodigoQr($codigoQr) : null;

        if ($cliente === null) {
            $this->vistaCanjear($codigoQr, '', null, 'No se encontró ningún cliente con ese código.');
            return;
        }

        // El tope se valida aquí (servidor), no solo con el atributo max del HTML.
        $lectura = LealtadService::leerCantidad($_POST['cantidad'] ?? '', LealtadService::MAX_CAFES_POR_CONSUMO, 'registrar en un solo consumo');
        if (!$lectura['ok']) {
            $this->vistaCanjear($codigoQr, '', null, $lectura['error'], $cliente);
            return;
        }
        $cantidad = $lectura['valor'];

        $resultado = LealtadService::registrarConsumo((int) $cliente['id'], $cantidad, $usuario['id'], $motivo);

        if (!$resultado['ok']) {
            $this->vistaCanjear($codigoQr, '', null, $resultado['error'], $cliente);
            return;
        }

        $mensaje = "Se sumaron {$cantidad} café(s) a {$cliente['nombre']}.";
        if ($resultado['codigos_generados'] !== []) {
            $mensaje .= ' ¡Completó su café gratis! Ya tiene una recompensa disponible en su cuenta.';
        }
        Flash::exito($mensaje);
        $this->redirigir('/admin/lealtad/canjear');
    }

    /**
     * Paso 2: confirmar el canje. Nada de lo mostrado en el paso 1 se da por
     * bueno: el cliente_id que viaja se vuelve a comparar en el servidor,
     * con la fila bloqueada, contra el dueño real del código (además de que
     * esté disponible y no haya vencido). Una vez canjeado queda marcado
     * con fecha y no puede usarse otra vez.
     */
    public function confirmarCanje(): void
    {
        $this->requerirPermiso('canjear', 'editar');

        $codigo = Recompensa::normalizarCodigo(Entrada::texto($_POST, 'codigo', ''));
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::validarYCanjearRecompensa($codigo, $clienteId, $productoId, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Canje confirmado: {$resultado['producto_nombre']} entregado a {$resultado['cliente_nombre']}.");
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        [$recompensa, ] = $this->buscarRecompensaConTelefono($codigo, '');

        $this->vistaCanjear($codigo, '', $recompensa, $resultado['error']);
    }

    /**
     * Paso 2, para un cupón de promoción (§4.20): a diferencia de una
     * recompensa, no hace falta elegir producto — el "premio" ya lo define
     * la promoción (descuento, 2x1…); el cajero lo aplica en la caja.
     */
    public function confirmarCanjeCupon(): void
    {
        $this->requerirPermiso('canjear', 'editar');

        $codigo = Recompensa::normalizarCodigo(Entrada::texto($_POST, 'codigo', ''));
        $usuario = Auth::usuario();

        $resultado = CuponPromocionService::validarYCanjearCupon($codigo, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Cupón canjeado: {$resultado['promocion_nombre']} para {$resultado['cliente_nombre']}.");
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        [$cupon, ] = $this->buscarCuponConTelefono($codigo, '');

        $this->vistaCanjear($codigo, '', null, $resultado['error'], null, $cupon);
    }

    /**
     * Busca la recompensa por código. Si se dio un teléfono, exige que sea
     * el del dueño del código — si no coincide, el segundo valor trae el
     * error (aunque el código sí exista, no se revela nada más de él).
     *
     * @return array{0:array<string,mixed>|null,1:string|null}
     */
    private function buscarRecompensaConTelefono(string $codigo, string $telefono): array
    {
        $recompensa = (new Recompensa())->buscarPorCodigo($codigo);
        if ($recompensa === null) {
            return [null, null];
        }

        if ($telefono !== '' && $telefono !== Cliente::normalizarTelefono((string) $recompensa['cliente_telefono'])) {
            return [null, 'Ese código no pertenece al cliente con ese teléfono.'];
        }

        return [$recompensa, null];
    }

    /**
     * Igual que buscarRecompensaConTelefono() pero para un cupón de
     * promoción — ver ahí el porqué de la forma del valor de retorno.
     *
     * @return array{0:array<string,mixed>|null,1:string|null}
     */
    private function buscarCuponConTelefono(string $codigo, string $telefono): array
    {
        $cupon = (new CuponPromocion())->buscarPorCodigo($codigo);
        if ($cupon === null) {
            return [null, null];
        }

        if ($telefono !== '' && $telefono !== Cliente::normalizarTelefono((string) $cupon['cliente_telefono'])) {
            return [null, 'Ese código no pertenece al cliente con ese teléfono.'];
        }

        return [$cupon, null];
    }

    /**
     * @param array<string,mixed>|null $clienteEscaneado cliente identificado por su QR personal
     * @param array<string,mixed>|null $cupon cupón de promoción identificado por su código
     */
    private function vistaCanjear(
        string $codigo,
        string $telefono,
        ?array $recompensa,
        ?string $error,
        ?array $clienteEscaneado = null,
        ?array $cupon = null
    ): void {
        // Post/Redirect/Get: tras un envío no se dibuja la pantalla directamente (recargar
        // repetiría la búsqueda y dejaría el cupón o código equivocado en pantalla); se guarda
        // el resultado y se redirige, así recargar o volver atrás deja la pantalla limpia.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $_SESSION['_canje'] = [
                'codigo' => $codigo, 'telefono' => $telefono, 'recompensa' => $recompensa,
                'error' => $error, 'clienteEscaneado' => $clienteEscaneado, 'cupon' => $cupon,
            ];
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        $disponibles = [];
        if ($clienteEscaneado !== null) {
            $disponibles = array_values(array_filter(
                (new Recompensa())->listarPorCliente((int) $clienteEscaneado['id']),
                fn ($r) => $r['estado'] === 'disponible'
            ));
        }

        $this->vista('admin/lealtad/canjear', [
            'seccionActiva'    => 'canjear',
            'telefono'         => $telefono,
            'codigo'           => $codigo,
            'recompensa'       => $recompensa,
            'cupon'            => $cupon,
            'error'            => $error,
            'productos'        => (new Producto())->listarActivos(),
            'clienteEscaneado' => $clienteEscaneado,
            'umbral'           => LealtadService::obtenerUmbral(),
            'disponibles'      => $disponibles,
            'cuponesDisponibles' => $clienteEscaneado !== null
                ? (new CuponPromocion())->listarDisponiblesDeCliente((int) $clienteEscaneado['id'])
                : [],
        ]);
    }
}
