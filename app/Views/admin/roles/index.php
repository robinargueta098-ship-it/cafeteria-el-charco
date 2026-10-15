<?php
/**
 * @var array<int,array{id:int,nombre:string,permisos:array<string,string>}> $roles
 * @var array<string,array{0:string,1:string,2:string}> $modulos
 * @var array<string,string> $niveles
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>

<div class="panel-encabezado">
    <h1>Roles y permisos</h1>
</div>

<p class="campo-ayuda" style="margin-top:0;">
    Decide qué puede hacer cada rol. <strong>Solo ver</strong> permite consultar; <strong>Ver y editar</strong> también permite
    crear, modificar o cambiar estados. El Administrador siempre puede todo. Usuarios, Configuración, Auditoría y esta pantalla
    son siempre del Administrador. Los cambios se aplican de inmediato, sin que la persona tenga que volver a entrar.
</p>

<?php foreach ($roles as $rol): ?>
    <form method="post" action="/admin/roles/<?= (int) $rol['id'] ?>" class="tarjeta tabla-contenedor" style="margin-bottom:20px;">
        <?= Csrf::campo() ?>
        <h2 style="margin-top:0;"><?= $e($rol['nombre']) ?></h2>
        <table class="tabla">
            <thead><tr><th>Módulo</th><th style="width:200px;">Acceso</th><th>Qué permite</th></tr></thead>
            <tbody>
                <?php foreach ($modulos as $clave => $def): ?>
                    <?php $max = $def[1]; ?>
                    <tr>
                        <td><strong><?= $e($def[0]) ?></strong></td>
                        <td>
                            <select name="permiso[<?= $e($clave) ?>]" aria-label="Acceso de <?= $e($rol['nombre']) ?> a <?= $e($def[0]) ?>">
                                <?php foreach ($niveles as $valor => $etiqueta): ?>
                                    <?php if ($valor === 'editar' && $max !== 'editar') { continue; } ?>
                                    <option value="<?= $e($valor) ?>" <?= $rol['permisos'][$clave] === $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><small><?= $e($def[2]) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-bottom:0;"><button type="submit" class="btn">Guardar permisos de <?= $e($rol['nombre']) ?></button></p>
    </form>
<?php endforeach; ?>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
