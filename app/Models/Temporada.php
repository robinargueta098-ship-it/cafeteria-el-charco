<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Temporada
{
    /** Clave de configuración con el orden elegido en el panel: lista JSON de ids, de la primera a la última. */
    public const CLAVE_ORDEN = 'orden_temporadas';

    /**
     * Todas las temporadas, en el orden en que se muestran en el sitio (el elegido en el panel con ▲▼; las que
     * todavía no tienen lugar elegido van al final, de la más antigua a la más nueva).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarTodas(): array
    {
        return $this->ordenar(
            Database::conexion()->query('SELECT * FROM temporadas ORDER BY fecha_inicio ASC, id ASC')->fetchAll()
        );
    }

    /** @return array<int,int> ids en el orden elegido (puede incluir ids de temporadas ya borradas: se ignoran). */
    private function ordenGuardado(): array
    {
        $config = (new Configuracion())->obtenerTodas();
        $lista = json_decode((string) ($config[self::CLAVE_ORDEN] ?? ''), true);

        return is_array($lista) ? array_values(array_map('intval', $lista)) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $filas
     * @return array<int,array<string,mixed>>
     */
    private function ordenar(array $filas): array
    {
        $posiciones = array_flip($this->ordenGuardado());
        $sinLugar = count($posiciones) + 1000;

        // usort no es estable en PHP < 8.0; en 8+ sí. Se desempata con la posición original por si acaso.
        $indexadas = array_map(fn ($f, $i) => [$f, $i], $filas, array_keys($filas));
        usort($indexadas, function (array $a, array $b) use ($posiciones, $sinLugar): int {
            $pa = $posiciones[(int) $a[0]['id']] ?? $sinLugar;
            $pb = $posiciones[(int) $b[0]['id']] ?? $sinLugar;

            return [$pa, $a[1]] <=> [$pb, $b[1]];
        });

        return array_map(fn ($par) => $par[0], $indexadas);
    }

    /**
     * Sube o baja una temporada una posición. Guarda el orden completo, así funciona aunque nunca se haya ordenado.
     *
     * @return array{resultado:string,desde?:int,hasta?:int} 'movida' | 'extremo' | 'no_existe'
     */
    public function mover(int $id, string $direccion): array
    {
        $ids = array_map(fn ($f) => (int) $f['id'], $this->listarTodas());

        $posicion = array_search($id, $ids, true);
        if ($posicion === false) {
            return ['resultado' => 'no_existe'];
        }

        $destino = $direccion === 'subir' ? $posicion - 1 : $posicion + 1;
        if ($destino < 0 || $destino >= count($ids)) {
            return ['resultado' => 'extremo'];
        }

        [$ids[$posicion], $ids[$destino]] = [$ids[$destino], $ids[$posicion]];

        $config = new Configuracion();
        $config->asegurar(self::CLAVE_ORDEN, '[]', 'Orden de las temporadas en el sitio: lista JSON de ids (se cambia con ▲▼ en Temporadas)');
        $config->actualizar(self::CLAVE_ORDEN, (string) json_encode($ids));

        return ['resultado' => 'movida', 'desde' => $posicion + 1, 'hasta' => $destino + 1];
    }

    /** @return array<int,array<string,mixed>> Vigentes hoy y activas (para el sitio público, Fase 7). */
    public function listarVigentes(): array
    {
        return $this->ordenar(Database::conexion()->query(
            'SELECT * FROM temporadas
             WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
             ORDER BY fecha_inicio ASC, id ASC'
        )->fetchAll());
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM temporadas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO temporadas (nombre, descripcion, imagen, fecha_inicio, fecha_fin, activo)
             VALUES (:nombre, :descripcion, :imagen, :fecha_inicio, :fecha_fin, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE temporadas
             SET nombre = :nombre, descripcion = :descripcion, imagen = :imagen,
                 fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM temporadas WHERE id = :id')->execute(['id' => $id]);
    }

    public function contarProductosAsociados(int $id): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM producto_temporadas WHERE temporada_id = :id'
        );
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}
