<?php
/**
 * @var array<string,string> $config
 */
$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<header class="contenedor">
    <strong><?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?></strong>
</header>
<main class="contenedor">
