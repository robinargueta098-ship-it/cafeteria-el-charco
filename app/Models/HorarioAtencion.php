<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Horario semanal del negocio, con horario partido: cada día admite un
 * segundo turno opcional (ej. 9:00-14:00 y 16:00-21:00). El turno 2 va
 * completo (apertura y cierre) o vacío del todo — nunca a medias
 * (docs/arquitectura.md §4.13; reforzado también por un CHECK en la BD).
 */
class HorarioAtencion
{
    /** @return array<int,array<string,mixed>> Las 7 filas, ordenadas domingo(0)..sábado(6). */
    public function listarTodos(): array
    {
        return Database::conexion()->query('SELECT * FROM horarios_atencion ORDER BY dia_semana ASC')->fetchAll();
    }

    public function actualizarDia(
        int $diaSemana,
        ?string $horaApertura,
        ?string $horaCierre,
        ?string $horaApertura2,
        ?string $horaCierre2,
        bool $cerrado
    ): void {
        Database::conexion()->prepare(
            'UPDATE horarios_atencion
             SET hora_apertura = :apertura, hora_cierre = :cierre,
                 hora_apertura_2 = :apertura2, hora_cierre_2 = :cierre2,
                 cerrado = :cerrado
             WHERE dia_semana = :dia'
        )->execute([
            'apertura'  => $cerrado ? null : $horaApertura,
            'cierre'    => $cerrado ? null : $horaCierre,
            'apertura2' => $cerrado ? null : $horaApertura2,
            'cierre2'   => $cerrado ? null : $horaCierre2,
            'cerrado'   => $cerrado ? 1 : 0,
            'dia'       => $diaSemana,
        ]);
    }
}
