<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class BitacoraAuditoria
{
    private const POR_PAGINA = 30;

    /**
     * @param array{tabla?:string,accion?:string} $filtros
     * @return array{registros:array<int,array<string,mixed>>,total:int,pagina:int,totalPaginas:int}
     */
    public function listar(array $filtros, int $pagina): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['tabla'])) {
            $condiciones[] = 'b.tabla_afectada = :tabla';
            $parametros['tabla'] = $filtros['tabla'];
        }
        if (!empty($filtros['accion'])) {
            $condiciones[] = 'b.accion = :accion';
            $parametros['accion'] = $filtros['accion'];
        }

        $where = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $stmtTotal = Database::conexion()->prepare("SELECT COUNT(*) FROM bitacora_auditoria b {$where}");
        $stmtTotal->execute($parametros);
        $total = (int) $stmtTotal->fetchColumn();

        $totalPaginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = max(1, min($pagina, $totalPaginas));
        $offset = ($pagina - 1) * self::POR_PAGINA;

        $stmt = Database::conexion()->prepare(
            "SELECT b.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre
             FROM bitacora_auditoria b
             LEFT JOIN usuarios u ON u.id = b.usuario_id
             LEFT JOIN clientes c ON b.tabla_afectada = 'lealtad_movimientos' AND c.id = b.registro_id
             {$where}
             ORDER BY b.creado_en DESC
             LIMIT " . self::POR_PAGINA . " OFFSET {$offset}"
        );
        $stmt->execute($parametros);

        return [
            'registros'    => $stmt->fetchAll(),
            'total'        => $total,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
        ];
    }
}
