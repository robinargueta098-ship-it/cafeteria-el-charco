<?php

/**
 * Plantilla de credenciales de base de datos.
 *
 * Copiar este archivo como database.php (en la misma carpeta) y
 * completar con las credenciales reales. database.php NUNCA se sube a
 * git (ver .gitignore). No usar el usuario root de MySQL — usar un
 * usuario dedicado con privilegios mínimos sobre esta base de datos.
 */

return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'elcharco_cafeteria',
    'usuario'  => 'elcharco_app',
    'password' => 'CAMBIAR_ESTA_CONTRASENA',
    'charset'  => 'utf8mb4',
];
