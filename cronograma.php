<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$paginaActual = 'cronograma';
$tituloPagina = 'Cronograma';

// --- Mes que se está viendo (por defecto, el mes actual) ---
$mes = $_GET['mes'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
[$anioMes, $numeroMes] = array_map('intval', explode('-', $mes));

$primerDia = sprintf('%04d-%02d-01', $anioMes, $numeroMes);
$ultimoDiaObj = new DateTime($primerDia);
$ultimoDiaObj->modify('last day of this month');
$ultimoDia = $ultimoDiaObj->format('Y-m-d');

// --- Uniformes disponibles ---
$uniformes = $pdo->query('SELECT id, nombre, color FROM uniformes ORDER BY nombre')->fetchAll();

// --- Niñas que hoy pueden pasar al altar (candidatas para el cronograma) ---
$ninasElegibles = $pdo->query(
    'SELECT id, nombres, apellidos FROM ninas WHERE activa = 1 AND puede_altar = 1 ORDER BY nombres, apellidos'
)->fetchAll();

// --- Fechas del cronograma dentro del mes elegido ---
$stmt = $pdo->prepare(
    'SELECT cf.id, cf.fecha, cf.uniforme_id, u.nombre AS uniforme_nombre, u.color AS uniforme_color
     FROM cronograma_fechas cf
     LEFT JOIN uniformes u ON u.id = cf.uniforme_id
     WHERE cf.fecha BETWEEN ? AND ?
     ORDER BY cf.fecha ASC'
);
$stmt->execute([$primerDia, $ultimoDia]);
$fechas = $stmt->fetchAll();

// --- Danzoras ya asignadas, agrupadas por fecha ---
$asignadasPorFecha = [];
if ($fechas) {
    $ids = array_column($fechas, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtA = $pdo->prepare(
        "SELECT ca.cronograma_fecha_id, n.id AS nina_id, n.nombres, n.apellidos
         FROM cronograma_asignaciones ca
         JOIN ninas n ON n.id = ca.nina_id
         WHERE ca.cronograma_fecha_id IN ($marcadores)
         ORDER BY n.nombres, n.apellidos"
    );
    $stmtA->execute($ids);
    foreach ($stmtA->fetchAll() as $a) {
        $asignadasPorFecha[$a['cronograma_fecha_id']][] = $a;
    }
}

// --- Datos para que JavaScript precargue el modal de edición de cada fecha ---
$fechasJson = [];
foreach ($fechas as $f) {
    $fechasJson[$f['id']] = [
        'id'           => (int) $f['id'],
        'fecha_bonita' => formatearFechaBonita($f['fecha']),
        'uniforme_id'  => $f['uniforme_id'] ? (int) $f['uniforme_id'] : 0,
        'ninas'        => array_map(fn($a) => (int) $a['nina_id'], $asignadasPorFecha[$f['id']] ?? []),
    ];
}

$mensaje = $_GET['ok'] ?? null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Cronograma</h1>
        <p>Genera los jueves y domingos del mes, elige quiénes pasan al altar cada fecha y con qué uniforme.</p>
    </div>
</div>

<?php if ($mensaje === 'cronograma_generado'): ?>
    <div class="alerta alerta-exito">Se generaron los jueves y domingos de <?= h(nombreMesAnio($mes)) ?>.</div>
<?php elseif ($mensaje === 'cronograma_fecha_guardada'): ?>
    <div class="alerta alerta-exito">El cronograma de esa fecha se guardó correctamente.</div>
<?php elseif ($mensaje === 'cronograma_fecha_eliminada'): ?>
    <div class="alerta alerta-exito">Se eliminó la fecha del cronograma.</div>
<?php elseif ($mensaje === 'uniforme_guardado'): ?>
    <div class="alerta alerta-exito">Uniforme guardado correctamente.</div>
<?php elseif ($mensaje === 'uniforme_eliminado'): ?>
    <div class="alerta alerta-exito">Uniforme eliminado.</div>
<?php endif; ?>

<!-- ---------------------------- Selección de mes ---------------------------- -->
<div class="tarjeta">
    <h2>Mes</h2>
    <form class="filtro-puntaje">
        <div class="campo">
            <label for="mes">Elige el mes</label>
            <input type="month" id="mes" name="mes" value="<?= h($mes) ?>">
        </div>
        <div class="filtro-puntaje-acciones">
            <button type="submit" formmethod="get" formaction="cronograma.php" class="btn btn-suave">Ver este mes</button>
            <button type="submit" formmethod="post" formaction="actions/generar_cronograma.php" class="btn btn-vino">Generar jueves y domingos</button>
        </div>
    </form>
    <p style="margin:14px 0 0;color:var(--texto-suave);font-size:13px;">
        "Generar" crea en la tabla de abajo todos los jueves y domingos del mes elegido (los que ya existen no se duplican).
    </p>
</div>

<!-- ------------------------------- Uniformes ---------------------------------- -->
<div class="tarjeta">
    <h2>Uniformes</h2>
    <p style="color:var(--texto-suave);font-size:14px;margin-top:-8px;">
        Crea aquí los uniformes con su color; luego los eliges para cada fecha del cronograma.
    </p>

    <form method="post" action="actions/guardar_uniforme.php" class="fila-campos" style="align-items:flex-end;margin-bottom:18px;">
        <input type="hidden" name="mes" value="<?= h($mes) ?>">
        <div class="campo">
            <label for="uniforme_nombre">Nombre o descripción del uniforme</label>
            <input type="text" id="uniforme_nombre" name="nombre" placeholder="Ej. Camiseta blanca y jean azul oscuro" required maxlength="150">
        </div>
        <div class="campo" style="max-width:110px;">
            <label for="uniforme_color">Color</label>
            <input type="color" id="uniforme_color" name="color" value="#8f4157" class="color-input">
        </div>
        <div class="campo" style="flex:0;">
            <button type="submit" class="btn btn-teal">Agregar uniforme</button>
        </div>
    </form>

    <?php if (empty($uniformes)): ?>
        <p style="color:var(--texto-suave);">Aún no has creado ningún uniforme.</p>
    <?php else: ?>
        <div>
            <?php foreach ($uniformes as $u): ?>
                <span class="badge" style="font-size:13px;padding:6px 8px 6px 10px;">
                    <span class="uniforme-swatch" style="background:<?= h($u['color']) ?>;"></span><?= h($u['nombre']) ?>
                    <form method="post" action="actions/eliminar_uniforme.php" style="display:inline;" onsubmit="return confirm('¿Eliminar el uniforme «<?= h(addslashes($u['nombre'])) ?>»? Las fechas que lo tenían quedarán sin uniforme definido.');">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <input type="hidden" name="mes" value="<?= h($mes) ?>">
                        <button type="submit" style="border:none;background:none;color:var(--triste);cursor:pointer;font-weight:700;margin-left:4px;">×</button>
                    </form>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ------------------------------- Tabla del cronograma ------------------------- -->
<div class="tarjeta">
    <h2>Cronograma de <?= h(nombreMesAnio($mes)) ?></h2>

    <?php if (empty($fechas)): ?>
        <div class="vacio">
            Todavía no hay fechas generadas para este mes.
            <p style="margin-top:10px;">Usa el botón "Generar jueves y domingos" de arriba.</p>
        </div>
    <?php else: ?>
        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Danzoras</th>
                        <th>Uniforme</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fechas as $f):
                        $diaSemana = nombreDiaSemanaDesdeFecha($f['fecha']);
                        $claseDia = $diaSemana === 'Domingo' ? 'badge-dia-domingo' : ($diaSemana === 'Jueves' ? 'badge-dia-jueves' : 'badge');
                        $asignadas = $asignadasPorFecha[$f['id']] ?? [];
                    ?>
                        <tr>
                            <td class="nombre-nina"><?= h(formatearFechaBonita($f['fecha'])) ?></td>
                            <td><span class="badge <?= $claseDia ?>"><?= h($diaSemana) ?></span></td>
                            <td>
                                <?php if (empty($asignadas)): ?>
                                    <span style="color:var(--texto-suave);font-size:13px;">Sin asignar</span>
                                <?php else: ?>
                                    <div class="danzoras-lista">
                                        <?php foreach ($asignadas as $a): ?>
                                            <span class="badge badge-vino"><?= h($a['nombres']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($f['uniforme_id']): ?>
                                    <span class="uniforme-swatch" style="background:<?= h($f['uniforme_color']) ?>;"></span><?= h($f['uniforme_nombre']) ?>
                                <?php else: ?>
                                    <span style="color:var(--texto-suave);font-size:13px;">Sin definir</span>
                                <?php endif; ?>
                            </td>
                            <td class="acciones-fila">
                                <button type="button" class="btn btn-suave btn-sm" onclick="abrirModalCronograma(<?= (int) $f['id'] ?>)">Editar</button>
                                <form method="post" action="actions/eliminar_cronograma_fecha.php" onsubmit="return confirm('¿Eliminar <?= h(addslashes(formatearFechaBonita($f['fecha']))) ?> del cronograma?');" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                    <input type="hidden" name="mes" value="<?= h($mes) ?>">
                                    <button type="submit" class="btn btn-peligro btn-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ------------------------------ Modal cronograma -------------------------------- -->
<div class="modal-fondo" id="modal-cronograma">
    <div class="modal-caja">
        <button type="button" class="modal-cerrar" onclick="cerrarModalCronograma()">&times;</button>
        <h2 id="modal-cronograma-titulo">Editar fecha</h2>
        <form method="post" action="actions/guardar_cronograma_fecha.php" id="form-cronograma">
            <input type="hidden" name="cronograma_fecha_id" id="cronograma_fecha_id" value="">
            <input type="hidden" name="mes" value="<?= h($mes) ?>">

            <div class="campo">
                <label>¿Quiénes pasan al altar?</label>
                <div class="checkbox-cargos">
                    <?php if (empty($ninasElegibles)): ?>
                        <span style="color:var(--texto-suave);font-size:13px;">
                            No hay niñas marcadas como "puede pasar al altar" en Niñas y Cargos.
                        </span>
                    <?php endif; ?>
                    <?php foreach ($ninasElegibles as $n): ?>
                        <label>
                            <input type="checkbox" name="ninas[]" value="<?= (int) $n['id'] ?>" class="chk-danzora">
                            <?= h($n['nombres'] . ' ' . $n['apellidos']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="campo">
                <label for="cronograma_uniforme_id">Uniforme</label>
                <div class="uniforme-select-fila">
                    <span class="uniforme-swatch" id="uniforme-preview-swatch"></span>
                    <select id="cronograma_uniforme_id" name="uniforme_id" onchange="actualizarSwatchUniforme()">
                        <option value="0" data-color="transparent">Sin definir</option>
                        <?php foreach ($uniformes as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" data-color="<?= h($u['color']) ?>"><?= h($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-suave" onclick="cerrarModalCronograma()">Cancelar</button>
                <button type="submit" class="btn btn-vino">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    const DATOS_CRONOGRAMA = <?= json_encode($fechasJson, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
