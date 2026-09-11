<?php
/**
 * @var array{id:int,nombre:string,rol_id:int} $usuario
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/header_admin.php';
?>

<div class="tarjeta">
    <h1>Bienvenido, <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p>
        Panel en construcción — Fase 3 del proyecto (núcleo del sistema).
        Los módulos de productos, categorías, promociones, clientes,
        lealtad, configuración, usuarios y auditoría se construyen en
        las Fases 4-6 (ver docs/arquitectura.md §9).
    </p>

    <form method="post" action="/admin/logout">
        <?= Csrf::campo() ?>
        <button type="submit" class="btn">Cerrar sesión</button>
    </form>
</div>

<?php
require APP_PATH . '/Views/layouts/footer_admin.php';
