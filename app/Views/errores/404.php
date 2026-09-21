<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada — Cafetería El Charco</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(\App\Core\Vista::asset('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="contenedor">
    <div class="tarjeta">
        <h1>404 — Página no encontrada</h1>
        <p>La página que buscas no existe o fue movida.</p>
        <?php $enPanel = str_starts_with((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/admin'); ?>
        <a class="btn" href="<?= $enPanel ? '/admin' : '/' ?>"><?= $enPanel ? 'Volver al panel' : 'Volver al inicio' ?></a>
    </div>
</main>
</body>
</html>
