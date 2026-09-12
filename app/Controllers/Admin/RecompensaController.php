<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Recompensa;
use App\Services\LealtadService;

class RecompensaController extends Controller
{
    private const ESTADOS_VALIDOS = ['disponible', 'canjeada', 'cancelada', 'vencida'];

    public function index(): void
    {
        $this->requerirRol(1, 2);

        $estado = (string) ($_GET['estado'] ?? '');
        $filtros = in_array($estado, self::ESTADOS_VALIDOS, true) ? ['estado' => $estado] : [];

        $this->vista('admin/lealtad/recompensas', [
            'seccionActiva' => 'recompensas',
            'canjes'        => (new Recompensa())->listarTodas($filtros),
            'estadoFiltro'  => $estado,
        ]);
    }

    public function cancelar(string $id): void
    {
        $this->requerirRol(1, 2);

        $recompensa = (new Recompensa())->buscarPorId((int) $id);
        if ($recompensa === null) {
            $this->error404();
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $usuario = Auth::usuario();

        $resultado = LealtadService::cancelarRecompensa((int) $id, $usuario['id'], $motivo);

        if ($resultado['ok']) {
            Flash::exito('Recompensa cancelada; los cafés se devolvieron al saldo del cliente.');
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/recompensas');
    }
}
