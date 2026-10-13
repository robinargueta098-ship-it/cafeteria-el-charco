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
     * Filas para mostrar el horario en el sitio. Con $agrupar, los días SEGUIDOS (de lunes a domingo) que tienen
     * exactamente el mismo horario se juntan en una sola fila: "Lunes a Viernes", "Sábado y Domingo". Sin agrupar,
     * una fila por día en el orden de siempre (domingo primero). Un día distinto en medio corta el grupo.
     *
     * @param array<int,array<string,mixed>> $filas resultado de listarTodos()
     * @param array<int,string> $nombresDias 0 = domingo … 6 = sábado
     * @return array<int,array{dias:string,texto:string}>
     */
    public static function filasParaMostrar(array $filas, array $nombresDias, bool $agrupar): array
    {
        $porDia = [];
        foreach ($filas as $f) {
            $porDia[(int) $f['dia_semana']] = $f;
        }

        if (!$agrupar) {
            $salida = [];
            foreach ($filas as $f) {
                $salida[] = ['dias' => $nombresDias[(int) $f['dia_semana']] ?? '', 'texto' => self::textoDia($f)];
            }

            return $salida;
        }

        $grupos = [];   // cada grupo: ['texto' => ..., 'dias' => [numeros en orden lunes → domingo]]
        foreach ([1, 2, 3, 4, 5, 6, 0] as $dia) {
            if (!isset($porDia[$dia])) {
                continue;
            }
            $texto = self::textoDia($porDia[$dia]);
            $ultimo = count($grupos) - 1;
            if ($ultimo >= 0 && $grupos[$ultimo]['texto'] === $texto) {
                $grupos[$ultimo]['dias'][] = $dia;
            } else {
                $grupos[] = ['texto' => $texto, 'dias' => [$dia]];
            }
        }

        $salida = [];
        foreach ($grupos as $g) {
            $nombres = array_map(fn (int $d): string => $nombresDias[$d] ?? '', $g['dias']);
            $n = count($nombres);
            $etiqueta = $n === 1 ? $nombres[0] : ($n === 2 ? $nombres[0] . ' y ' . $nombres[1] : $nombres[0] . ' a ' . $nombres[$n - 1]);
            $salida[] = ['dias' => $etiqueta, 'texto' => $g['texto']];
        }

        return $salida;
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
