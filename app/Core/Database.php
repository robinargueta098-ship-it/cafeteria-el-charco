<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión única (singleton) a la base de datos vía PDO.
 * Sentencias preparadas siempre; nunca concatenar SQL (ver docs/arquitectura.md §7).
 */
class Database
{
    private static ?PDO $instancia = null;

    public static function conexion(): PDO
    {
        if (self::$instancia === null) {
            $config = require BASE_PATH . '/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instancia = new PDO($dsn, $config['usuario'], $config['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                ]);
            } catch (PDOException $e) {
                Logger::error('Conexión a base de datos falló: ' . $e->getMessage());
                // Nunca mostrar el detalle de la excepción al usuario final.
                throw new RuntimeException('No se pudo conectar a la base de datos.');
            }
        }

        return self::$instancia;
    }
}
