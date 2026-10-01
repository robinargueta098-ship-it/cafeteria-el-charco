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

    /**
     * Texto de un día: "9:00 a 14:00" o, con horario partido, "9:00 a 14:00 y 16:00 a 21:00";
     * "Cerrado" si ese día no se abre. Lo usan el pie de página y el carrito (un solo formato).
     *
     * @param array<string,mixed> $fila fila de horarios_atencion
     */
    public static function textoDia(array $fila): string
    {
        if ((int) $fila['cerrado'] === 1) {
            return 'Cerrado';
        }

        $rango = fn (string $a, string $c): string => substr($a, 0, 5) . ' a ' . substr($c, 0, 5);
        $texto = $rango((string) $fila['hora_apertura'], (string) $fila['hora_cierre']);
        if (!empty($fila['hora_apertura_2']) && !empty($fila['hora_cierre_2'])) {
            $texto .= ' y ' . $rango((string) $fila['hora_apertura_2'], (string) $fila['hora_cierre_2']);
        }

        return $texto;
    }

    /**
     * Horario en el formato que lee el carrito (JavaScript) para avisar si ahora se está
     * atendiendo: una entrada por día de la semana (0 = domingo) con sus turnos en minutos
     * desde la medianoche. Se lee de la base en cada carga: si el Administrador cambia el
     * horario en Configuración, el carrito lo refleja sin tocar código.
     *
     * @param array<int,array<string,mixed>> $filas resultado de listarTodos()
     * @return array<int,array{dia:int,texto:string,turnos:array<int,array{0:int,1:int}>}>
     */
    public static function paraCarrito(array $filas): array
    {
        $minutos = fn (string $hora): int => ((int) substr($hora, 0, 2)) * 60 + (int) substr($hora, 3, 2);
        $dias = [];

        foreach ($filas as $f) {
            $turnos = [];
            if ((int) $f['cerrado'] === 0) {
                $turnos[] = [$minutos((string) $f['hora_apertura']), $minutos((string) $f['hora_cierre'])];
                if (!empty($f['hora_apertura_2']) && !empty($f['hora_cierre_2'])) {
                    $turnos[] = [$minutos((string) $f['hora_apertura_2']), $minutos((string) $f['hora_cierre_2'])];
                }
            }
            $dias[] = ['dia' => (int) $f['dia_semana'], 'texto' => self::textoDia($f), 'turnos' => $turnos];
        }

        return $dias;
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
