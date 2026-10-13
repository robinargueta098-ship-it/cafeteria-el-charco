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
     * Filas para mostrar el horario en el sitio. Con $agrupar, los días que tienen exactamente el mismo
     * horario se juntan en una sola fila: "Lunes a Viernes", "Sábado y Domingo", y si no son seguidos "Lunes a Viernes y
     * Domingo". Sin agrupar, una fila por día en el orden de siempre (domingo primero).
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

        // Un grupo por cada horario distinto (en el orden en que aparece, de lunes a domingo). Los días que
        // comparten horario se juntan aunque no sean seguidos: "Lunes a Viernes y Domingo".
        $orden = [1, 2, 3, 4, 5, 6, 0];
        $grupos = [];   // texto => posiciones (en $orden) de los días con ese horario
        foreach ($orden as $posicion => $dia) {
            if (isset($porDia[$dia])) {
                $grupos[self::textoDia($porDia[$dia])][] = $posicion;
            }
        }

        $salida = [];
        foreach ($grupos as $texto => $posiciones) {
            // Se parten en tramos de días seguidos: [0,1,2,3,4] y [6] → "Lunes a Viernes" y "Domingo".
            $tramos = [];
            foreach ($posiciones as $pos) {
                $ultimo = count($tramos) - 1;
                if ($ultimo >= 0 && $tramos[$ultimo][count($tramos[$ultimo]) - 1] === $pos - 1) {
                    $tramos[$ultimo][] = $pos;
                } else {
                    $tramos[] = [$pos];
                }
            }

            $partes = [];
            foreach ($tramos as $tramo) {
                $primero = $nombresDias[$orden[$tramo[0]]] ?? '';
                $ultimoNombre = $nombresDias[$orden[$tramo[count($tramo) - 1]]] ?? '';
                $partes[] = count($tramo) === 1 ? $primero : (count($tramo) === 2 ? $primero . ' y ' . $ultimoNombre : $primero . ' a ' . $ultimoNombre);
            }
            $n = count($partes);
            $etiqueta = $n === 1 ? $partes[0] : implode(', ', array_slice($partes, 0, -1)) . ' y ' . $partes[$n - 1];

            $salida[] = ['dias' => $etiqueta, 'texto' => (string) $texto];
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
