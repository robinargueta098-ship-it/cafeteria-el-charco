<?php
/**
 * PIN temporal recién generado. Se muestra UNA sola vez, en la respuesta directa al POST: no se
 * guarda en sesión, ni en la base (solo su hash), ni en logs ni en Auditoría.
 *
 * @var array<string,mixed> $cliente
 * @var string $pin
 */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>PIN temporal de <?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>

<div class="tarjeta" style="max-width:520px;text-align:center;">
    <p>Díselo al cliente <strong>en persona</strong>. Con este PIN entra una sola vez y el sitio le pedirá crear uno nuevo.</p>
    <p style="font-size:3rem;font-weight:700;letter-spacing:0.4em;margin:12px 0;padding-left:0.4em;font-family:monospace;" id="pin-temporal"><?= htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="alerta alerta-error" style="text-align:left;">
        Este PIN no se vuelve a mostrar. Si lo pierdes, restablece el PIN otra vez. Ya se cerraron todas las sesiones abiertas de este cliente.
    </div>
    <p style="margin-top:16px;"><a class="btn" href="/admin/lealtad/<?= (int) $cliente['id'] ?>">Listo, volver a la ficha</a></p>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
