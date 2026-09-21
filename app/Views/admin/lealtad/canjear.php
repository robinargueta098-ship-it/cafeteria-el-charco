<?php
/**
 * @var string $telefono
 * @var string $codigo
 * @var array<string,mixed>|null $recompensa
 * @var string|null $error
 * @var array<int,array<string,mixed>> $productos
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>Canjear recompensa</h1>
<p>
    Escribe el teléfono del cliente y el código que muestra en su cuenta (<?= APP_URL ?>/cuenta).
    El canje solo procede si el código realmente es de ese cliente.
</p>

<?php if ($error !== null): ?>
    <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="tarjeta" style="max-width:480px;margin-bottom:24px;">
    <form method="post" action="/admin/lealtad/canjear/consultar">
        <?= Csrf::campo() ?>
        <div class="campo">
            <label for="telefono">Teléfono del cliente</label>
            <input type="tel" id="telefono" name="telefono" maxlength="20" required <?= $telefono === '' ? 'autofocus' : '' ?>
                   value="<?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="codigo">Código de la recompensa</label>
            <input type="text" id="codigo" name="codigo" maxlength="20" required <?= $telefono !== '' ? 'autofocus' : '' ?>
                   autocomplete="off" autocapitalize="characters"
                   style="text-transform:uppercase;font-size:1.3rem;text-align:center;"
                   value="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn">Consultar</button>
    </form>
</div>

<?php if ($recompensa !== null): ?>
    <div class="tarjeta" style="max-width:480px;">
        <h2 style="margin-top:0;">Recompensa encontrada</h2>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($recompensa['cliente_nombre'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($recompensa['cliente_telefono'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p><strong>Estado:</strong> <?= htmlspecialchars($recompensa['estado'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Generada:</strong> <?= htmlspecialchars(substr($recompensa['fecha_generada'], 0, 16), ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($recompensa['fecha_vencimiento'] !== null): ?>
            <p><strong>Vence:</strong> <?= htmlspecialchars(substr($recompensa['fecha_vencimiento'], 0, 10), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($recompensa['estado'] === 'disponible'): ?>
            <form method="post" action="/admin/lealtad/canjear/confirmar">
                <?= Csrf::campo() ?>
                <input type="hidden" name="codigo" value="<?= htmlspecialchars($recompensa['codigo'], ENT_QUOTES, 'UTF-8') ?>">
                <?php /* El teléfono viaja de nuevo: el servidor vuelve a resolver al cliente y a verificar la pertenencia. */ ?>
                <input type="hidden" name="telefono" value="<?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?>">
                <div class="campo">
                    <label for="producto_id">Café gratis a entregar</label>
                    <select id="producto_id" name="producto_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-verde"
                        onclick="return confirm('¿Confirmar el canje de esta recompensa? No se puede deshacer.');">
                    Confirmar canje
                </button>
            </form>
        <?php else: ?>
            <p class="campo-ayuda">
                Esta recompensa ya no se puede canjear (estado: <?= htmlspecialchars($recompensa['estado'], ENT_QUOTES, 'UTF-8') ?><?= $recompensa['fecha_canjeada'] !== null ? ', usada el ' . htmlspecialchars(substr($recompensa['fecha_canjeada'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?>).
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
