<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivos demasiado pesados — Cafetería El Charco</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Vista::asset('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="contenedor">
    <div class="tarjeta">
        <h1>Los archivos son demasiado pesados</h1>
        <p>
            Lo que intentaste enviar supera el tamaño total permitido por el servidor.
            Sube menos fotos a la vez (cada una debe pesar menos de 5 MB) e inténtalo de nuevo.
        </p>
        <a class="btn" href="javascript:history.back()">Volver</a>
    </div>
</main>
</body>
</html>
