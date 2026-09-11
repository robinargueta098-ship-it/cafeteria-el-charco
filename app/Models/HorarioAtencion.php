<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class HorarioAtencion
{
    /** @return array<int,array<string,mixed>> Las 7 filas, ordenadas domingo(0)..sábado(6). */
    public function listarTodos(): array
    {
        return Database::conexion()->query('SELECT * FROM horarios_atencion ORDER BY dia_semana ASC')->fetchAll();
    }

    public function actualizarDia(int $diaSemana, ?string $horaApertura, ?string $horaCierre, bool $cerrado): void
    {
        Database::conexion()->prepare(
            'UPDATE horarios_atencion SET hora_apertura = :apertura, hora_cierre = :cierre, cerrado = :cerrado
             WHERE dia_semana = :dia'
        )->execute([
            'apertura' => $cerrado ? null : $horaApertura,
            'cierre'   => $cerrado ? null : $horaCierre,
            'cerrado'  => $cerrado ? 1 : 0,
            'dia'      => $diaSemana,
        ]);
    }
}
