<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Cliente;
use App\Models\LealtadMovimiento;
use App\Models\Producto;
use App\Models\Recompensa;
use App\Services\LealtadService;

class LealtadController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $termino = trim((string) ($_GET['q'] ?? ''));

        $this->vista('admin/lealtad/buscar', [
            'seccionActiva' => 'lealtad',
            'clientes'      => $termino !== '' ? (new Cliente())->buscar($termino) : [],
            'termino'       => $termino,
        ]);
    }

    public function mostrarCliente(string $id): void
    {
        $this->requerirRol(1, 2);

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
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarConsumo((int) $id, $cantidad, $usuario['id']);

        if ($resultado['ok']) {
            $mensaje = "Se registraron {$cantidad} café(s) para {$cliente['nombre']}.";
            if ($resultado['codigos_generados'] !== []) {
                $n = count($resultado['codigos_generados']);
                $codigos = implode(', ', $resultado['codigos_generados']);
                $mensaje .= " ¡Se generó" . ($n > 1 ? "ron {$n} recompensas" : " 1 recompensa") . "! Código(s): {$codigos}.";
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    public function registrarAjuste(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $tipo = (string) ($_POST['tipo'] ?? '');
        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarAjuste((int) $id, $cantidad, $tipo, $motivo, $usuario['id']);

        if ($resultado['ok']) {
            $mensaje = 'Ajuste registrado correctamente.';
            if ($resultado['codigos_generados'] !== []) {
                $mensaje .= ' Se generó recompensa: ' . implode(', ', $resultado['codigos_generados']) . '.';
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    /**
     * Pantalla de canje: escáner de QR con cámara y, como respaldo, el campo
     * para escribir el código a mano. Ambos terminan en el mismo formulario.
     */
    public function mostrarCanjear(): void
    {
        $this->requerirRol(1, 2);

        $this->vistaCanjear(
            Recompensa::normalizarCodigo((string) ($_GET['codigo'] ?? '')),
            Cliente::normalizarTelefono((string) ($_GET['telefono'] ?? '')),
            null,
            null
        );
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
        $this->requerirRol(1, 2);

        $entrada = (string) ($_POST['codigo'] ?? '');
        $telefono = Cliente::normalizarTelefono((string) ($_POST['telefono'] ?? ''));
        $codigo = Cliente::normalizarCodigoQr($entrada); // limpieza genérica: mayúsculas y solo A-Z0-9

        if ($codigo === '') {
            $this->vistaCanjear('', $telefono, null, 'Escanea el QR o escribe el código.');
            return;
        }

        // Un mismo escáner/campo sirve para los dos códigos: el QR personal del cliente
        // (suma un café) y el de una recompensa (canjea el café gratis). Antes se
        // distinguían por su forma ("CL-" + 10 caracteres vs 8); ahora los dos son de 8
        // caracteres del mismo alfabeto (docs/arquitectura.md §4.15), así que no hay
        // forma de saber cuál es sin buscar: se prueba primero como código personal —
        // es el uso del día a día, mucho más frecuente que canjear — y si no existe,
        // como recompensa.
        $cliente = (new Cliente())->buscarPorCodigoQr($codigo);
        if ($cliente !== null) {
            $this->mostrarClienteEscaneado($cliente, $codigo, $telefono);
            return;
        }

        [, $recompensa, $error] = $this->resolverCanje($codigo, $telefono);

        if ($recompensa === null && $error !== null) {
            // Ni cliente ni recompensa: no hay forma de saber cuál de los dos
            // esperaba el cajero, así que el mensaje no asume ninguno de los dos.
            $error = 'No se encontró nada con ese código. Revisa que esté bien escrito.';
        }

        $this->vistaCanjear($codigo, $telefono, $recompensa, $error);
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
        $this->requerirRol(1, 2);

        $codigoQr = Cliente::normalizarCodigoQr((string) ($_POST['codigo_qr'] ?? ''));
        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $usuario = Auth::usuario();

        $cliente = $codigoQr !== '' ? (new Cliente())->buscarPorCodigoQr($codigoQr) : null;

        if ($cliente === null) {
            $this->vistaCanjear($codigoQr, '', null, 'No se encontró ningún cliente con ese código.');
            return;
        }

        $resultado = LealtadService::registrarConsumo((int) $cliente['id'], $cantidad, $usuario['id'], 'Escaneo de QR personal');

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
        $this->requerirRol(1, 2);

        $codigo = Recompensa::normalizarCodigo((string) ($_POST['codigo'] ?? ''));
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::validarYCanjearRecompensa($codigo, $clienteId, $productoId, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Canje confirmado: {$resultado['producto_nombre']} entregado a {$resultado['cliente_nombre']}.");
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        [, $recompensa] = $this->resolverCanje($codigo, '');

        $this->vistaCanjear($codigo, '', $recompensa, $resultado['error']);
    }

    /**
     * Busca la recompensa por código y devuelve, junto con su dueño, el
     * detalle que se le muestra al cajero. Si se dio un teléfono, exige
     * que sea el del dueño del código.
     *
     * @return array{0:array<string,mixed>|null,1:array<string,mixed>|null,2:string|null} [cliente, recompensa, error]
     */
    private function resolverCanje(string $codigo, string $telefono): array
    {
        if ($codigo === '') {
            return [null, null, 'Escanea el QR o escribe el código de la recompensa.'];
        }

        $recompensa = (new Recompensa())->buscarPorCodigo($codigo);
        if ($recompensa === null) {
            return [null, null, 'No se encontró ninguna recompensa con ese código. Revisa que esté bien escrito.'];
        }

        if ($telefono !== '' && $telefono !== Cliente::normalizarTelefono((string) $recompensa['cliente_telefono'])) {
            return [null, null, 'Ese código no pertenece al cliente con ese teléfono.'];
        }

        $cliente = (new Cliente())->buscarPorId((int) $recompensa['cliente_id']);

        return [$cliente, $recompensa, null];
    }

    /** @param array<string,mixed>|null $clienteEscaneado cliente identificado por su QR personal */
    private function vistaCanjear(string $codigo, string $telefono, ?array $recompensa, ?string $error, ?array $clienteEscaneado = null): void
    {
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
            'error'            => $error,
            'productos'        => (new Producto())->listarActivos(),
            'clienteEscaneado' => $clienteEscaneado,
            'umbral'           => LealtadService::obtenerUmbral(),
            'disponibles'      => $disponibles,
        ]);
    }
}
