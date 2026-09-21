<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado — Cafetería El Charco</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Vista::asset('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="contenedor">
    <div class="tarjeta">
        <h1>403 — Acceso denegado</h1>
        <p>No tienes permiso para ver esta página.</p>
        <a class="btn" href="/">Volver al inicio</a>
    </div>
</main>
</body>
</html>
