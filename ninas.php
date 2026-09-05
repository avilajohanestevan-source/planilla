<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$paginaActual = 'ninas';
$tituloPagina = 'Niñas y Cargos';

// --- Cargos disponibles ---
$cargos = $pdo->query('SELECT id, nombre, descripcion FROM cargos ORDER BY nombre')->fetchAll();

// --- Todas las niñas (activas e inactivas) ---
$ninas = $pdo->query(
    'SELECT id, nombres, apellidos, fecha_nacimiento, fecha_cumpleanos, activa
     FROM ninas ORDER BY activa DESC, nombres, apellidos'
)->fetchAll();

// --- Cargos asignados por niña ---
$cargosPorNina = [];
$rows = $pdo->query(
    'SELECT nc.nina_id, c.id AS cargo_id, c.nombre
     FROM nina_cargo nc JOIN cargos c ON c.id = nc.cargo_id'
)->fetchAll();
foreach ($rows as $r) {
    $cargosPorNina[$r['nina_id']][] = ['id' => $r['cargo_id'], 'nombre' => $r['nombre']];
}

$ninasActivas   = array_filter($ninas, fn($n) => (int) $n['activa'] === 1);
$ninasInactivas = array_filter($ninas, fn($n) => (int) $n['activa'] === 0);

// Datos para que JavaScript precargue el modal de edición
$ninasJson = [];
foreach ($ninas as $n) {
    $ninasJson[$n['id']] = [
        'id' => $n['id'],
        'nombres' => $n['nombres'],
        'apellidos' => $n['apellidos'],
        'fecha_nacimiento' => $n['fecha_nacimiento'],
        'fecha_cumpleanos' => $n['fecha_cumpleanos'],
        'cargos' => array_map(fn($c) => $c['id'], $cargosPorNina[$n['id']] ?? []),
    ];
}

$mensaje = $_GET['ok'] ?? null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Niñas y Cargos</h1>
        <p>Registra la información de cada niña y delega los cargos del ministerio.</p>
    </div>
    <button type="button" class="btn btn-vino" onclick="abrirModalNina()">Nueva niña</button>
</div>

<?php if ($mensaje === 'nina_guardada'): ?>
    <div class="alerta alerta-exito">Los datos de la niña se guardaron correctamente.</div>
<?php elseif ($mensaje === 'nina_desactivada'): ?>
    <div class="alerta alerta-exito">La niña fue desactivada. Ya no aparecerá en la planilla, pero su historial se conserva.</div>
<?php elseif ($mensaje === 'nina_reactivada'): ?>
    <div class="alerta alerta-exito">La niña fue reactivada.</div>
<?php elseif ($mensaje === 'cargo_guardado'): ?>
    <div class="alerta alerta-exito">Cargo guardado correctamente.</div>
<?php elseif ($mensaje === 'cargo_eliminado'): ?>
    <div class="alerta alerta-exito">Cargo eliminado.</div>
<?php endif; ?>

<!-- ---------------------------- Lista de niñas ---------------------------- -->
<div class="tarjeta">
    <h2>Niñas activas (<?= count($ninasActivas) ?>)</h2>

    <?php if (empty($ninasActivas)): ?>
        <div class="vacio">
            Todavía no hay niñas registradas. Usa el botón "Nueva niña" para agregar la primera.
        </div>
    <?php else: ?>
        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Edad</th>
                        <th>Fecha nacimiento</th>
                        <th>Cumpleaños</th>
                        <th>Cargos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ninasActivas as $n): ?>
                        <tr>
                            <td class="nombre-nina"><?= h($n['nombres'] . ' ' . $n['apellidos']) ?></td>
                            <td><?= calcularEdad($n['fecha_nacimiento']) ?> años</td>
                            <td><?= h(formatearFecha($n['fecha_nacimiento'])) ?></td>
                            <td><?= h(formatearFecha($n['fecha_cumpleanos'])) ?></td>
                            <td>
                                <?php if (empty($cargosPorNina[$n['id']])): ?>
                                    <span style="color:var(--texto-suave);font-size:13px;">Sin cargo</span>
                                <?php else: ?>
                                    <?php foreach ($cargosPorNina[$n['id']] as $c): ?>
                                        <span class="badge badge-vino"><?= h($c['nombre']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="acciones-fila">
                                <button type="button" class="btn btn-suave btn-sm" onclick="abrirModalNina(<?= (int) $n['id'] ?>)">Editar</button>
                                <form method="post" action="actions/eliminar_nina.php" onsubmit="return confirm('¿Desactivar a <?= h(addslashes($n['nombres'])) ?>? No aparecerá más en la planilla, pero su historial se conserva.');" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                    <input type="hidden" name="accion" value="desactivar">
                                    <button type="submit" class="btn btn-peligro btn-sm">Desactivar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($ninasInactivas)): ?>
<div class="tarjeta">
    <h3>Niñas inactivas (<?= count($ninasInactivas) ?>)</h3>
    <div class="tabla-wrap">
        <table>
            <thead>
                <tr><th>Nombre</th><th>Edad</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ninasInactivas as $n): ?>
                    <tr>
                        <td class="nombre-nina"><?= h($n['nombres'] . ' ' . $n['apellidos']) ?></td>
                        <td><?= calcularEdad($n['fecha_nacimiento']) ?> años</td>
                        <td>
                            <form method="post" action="actions/eliminar_nina.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <input type="hidden" name="accion" value="reactivar">
                                <button type="submit" class="btn btn-teal btn-sm">Reactivar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ------------------------------- Cargos ---------------------------------- -->
<div class="tarjeta">
    <h2>Cargos del ministerio</h2>
    <p style="color:var(--texto-suave);font-size:14px;margin-top:-8px;">
        Crea aquí los cargos disponibles; luego los asignas a cada niña desde su ficha.
    </p>

    <form method="post" action="actions/guardar_cargo.php" class="fila-campos" style="align-items:flex-end;margin-bottom:18px;">
        <div class="campo">
            <label for="cargo_nombre">Nombre del cargo</label>
            <input type="text" id="cargo_nombre" name="nombre" placeholder="Ej. Líder de danza" required maxlength="100">
        </div>
        <div class="campo">
            <label for="cargo_descripcion">Descripción (opcional)</label>
            <input type="text" id="cargo_descripcion" name="descripcion" placeholder="Ej. Coordina los ensayos" maxlength="255">
        </div>
        <div class="campo" style="flex:0;">
            <button type="submit" class="btn btn-teal">Agregar cargo</button>
        </div>
    </form>

    <?php if (empty($cargos)): ?>
        <p style="color:var(--texto-suave);">Aún no has creado ningún cargo.</p>
    <?php else: ?>
        <div>
            <?php foreach ($cargos as $c): ?>
                <span class="badge" style="font-size:13px;padding:6px 8px 6px 14px;">
                    <?= h($c['nombre']) ?>
                    <form method="post" action="actions/eliminar_cargo.php" style="display:inline;" onsubmit="return confirm('¿Eliminar el cargo «<?= h(addslashes($c['nombre'])) ?>»? Se quitará de las niñas que lo tengan asignado.');">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" style="border:none;background:none;color:var(--triste);cursor:pointer;font-weight:700;margin-left:4px;">×</button>
                    </form>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ------------------------------ Modal niña -------------------------------- -->
<div class="modal-fondo" id="modal-nina">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" onclick="cerrarModalNina()">&times;</button>
        <h2 id="modal-nina-titulo">Nueva niña</h2>
        <form method="post" action="actions/guardar_nina.php" id="form-nina">
            <input type="hidden" name="id" id="nina_id" value="">

            <div class="fila-campos">
                <div class="campo">
                    <label for="nina_nombres">Nombres</label>
                    <input type="text" id="nina_nombres" name="nombres" required maxlength="100">
                </div>
                <div class="campo">
                    <label for="nina_apellidos">Apellidos</label>
                    <input type="text" id="nina_apellidos" name="apellidos" required maxlength="100">
                </div>
            </div>

            <div class="fila-campos">
                <div class="campo">
                    <label for="nina_fecha_nacimiento">Fecha de nacimiento</label>
                    <input type="date" id="nina_fecha_nacimiento" name="fecha_nacimiento" required onchange="mostrarEdadCalculada()">
                    <div class="ayuda-edad" id="edad-calculada"></div>
                </div>
                <div class="campo">
                    <label for="nina_fecha_cumpleanos">Fecha de cumpleaños</label>
                    <input type="date" id="nina_fecha_cumpleanos" name="fecha_cumpleanos">
                </div>
            </div>

            <div class="campo">
                <label>Cargos asignados</label>
                <div class="checkbox-cargos">
                    <?php if (empty($cargos)): ?>
                        <span style="color:var(--texto-suave);font-size:13px;">Primero crea un cargo abajo en la sección "Cargos del ministerio".</span>
                    <?php endif; ?>
                    <?php foreach ($cargos as $c): ?>
                        <label>
                            <input type="checkbox" name="cargos[]" value="<?= (int) $c['id'] ?>" class="chk-cargo">
                            <?= h($c['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-suave" onclick="cerrarModalNina()">Cancelar</button>
                <button type="submit" class="btn btn-vino">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    const DATOS_NINAS = <?= json_encode($ninasJson, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
