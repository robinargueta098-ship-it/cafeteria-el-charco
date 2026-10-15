<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\BitacoraAuditoria;

class AuditoriaController extends Controller
{
    private const TABLAS_CONOCIDAS = [
        'categorias', 'productos', 'galeria', 'temporadas', 'promociones',
        'clientes', 'lealtad_movimientos', 'configuracion_sistema', 'usuarios', 'pedidos', 'roles', 'sesiones'];
    private const ACCIONES_CONOCIDAS = ['crear', 'editar', 'eliminar', 'login', 'consumo', 'ajuste', 'canje'];

    public function index(): void
    {
        $this->requerirRol(1);

        $filtros = [
            'tabla'  => in_array($_GET['tabla'] ?? '', self::TABLAS_CONOCIDAS, true) ? $_GET['tabla'] : '',
            'accion' => in_array($_GET['accion'] ?? '', self::ACCIONES_CONOCIDAS, true) ? $_GET['accion'] : '',
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $resultado = (new BitacoraAuditoria())->listar($filtros, $pagina);

        $this->vista('admin/auditoria/index', [
            'seccionActiva'     => 'auditoria',
            'registros'         => $resultado['registros'],
            'pagina'            => $resultado['pagina'],
            'totalPaginas'      => $resultado['totalPaginas'],
            'filtros'           => $filtros,
            'tablasConocidas'   => self::TABLAS_CONOCIDAS,
            'accionesConocidas' => self::ACCIONES_CONOCIDAS,
        ]);
    }
}
