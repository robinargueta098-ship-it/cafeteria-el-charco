<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\CuponPromocion;
use App\Services\CuponPromocionService;

/**
 * Historial de cupones de promoción (§4.20) — separado de Recompensas
 * (café gratis por acumular): Administrador y Cajero ven el historial
 * igual que con Recompensas, pero cancelar un cupón es exclusivo de
 * Administrador (requerirRol(1), no (1, 2)) — el Cajero solo canjea,
 * desde "Escanear código".
 */
class CuponPromocionController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $this->vista('admin/lealtad/cupones', [
            'seccionActiva' => 'cupones',
            'cupones'       => (new CuponPromocion())->listarTodos(),
        ]);
    }

    public function cancelar(string $id): void
    {
        $this->requerirRol(1);

        $cupon = (new CuponPromocion())->buscarPorId((int) $id);
        if ($cupon === null) {
            $this->error404();
        }

        $motivo = trim(Entrada::texto($_POST, 'motivo', ''));
        $usuario = Auth::usuario();

        $resultado = CuponPromocionService::cancelar((int) $id, $usuario['id'], $motivo);

        if ($resultado['ok']) {
            Flash::exito('Cupón cancelado.');
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/cupones');
    }
}
