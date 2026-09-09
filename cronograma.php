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

// --- Semanas del cronograma que tocan este mes ---
// El Cronograma se maneja por semana (domingo a sábado): cada semana
// comparte el mismo uniforme y las mismas danzoras entre su domingo y su
// jueves. Una semana "toca" el mes elegido si su domingo cae en el mes, o si
// el jueves de esa semana (domingo + 4 días) cae en el mes — así, si el
// domingo es de este mes y el jueves ya es del mes siguiente (o al revés),
// la semana igual se muestra aquí.
$stmt = $pdo->prepare(
    'SELECT cs.id, cs.fecha_domingo, cs.uniforme_id, u.nombre AS uniforme_nombre, u.color AS uniforme_color
     FROM cronograma_semanas cs
     LEFT JOIN uniformes u ON u.id = cs.uniforme_id
     WHERE cs.fecha_domingo BETWEEN ? AND ?
        OR DATE_ADD(cs.fecha_domingo, INTERVAL 4 DAY) BETWEEN ? AND ?
     ORDER BY cs.fecha_domingo ASC'
);
$stmt->execute([$primerDia, $ultimoDia, $primerDia, $ultimoDia]);
$semanas = $stmt->fetchAll();

// --- Danzoras ya asignadas, agrupadas por semana ---
$asignadasPorSemana = [];
if ($semanas) {
    $ids = array_column($semanas, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtA = $pdo->prepare(
        "SELECT csa.semana_id, n.id AS nina_id, n.nombres, n.apellidos
         FROM cronograma_semana_asignaciones csa
         JOIN ninas n ON n.id = csa.nina_id
         WHERE csa.semana_id IN ($marcadores)
         ORDER BY n.nombres, n.apellidos"
    );
    $stmtA->execute($ids);
    foreach ($stmtA->fetchAll() as $a) {
        $asignadasPorSemana[$a['semana_id']][] = $a;
    }
}

// --- Datos para que JavaScript precargue el modal de edición de cada semana ---
$semanasJson = [];
foreach ($semanas as $s) {
    $semanasJson[$s['id']] = [
        'id'          => (int) $s['id'],
        'titulo'      => tituloSemana($s['fecha_domingo']),
        'uniforme_id' => $s['uniforme_id'] ? (int) $s['uniforme_id'] : 0,
        'ninas'       => array_map(fn($a) => (int) $a['nina_id'], $asignadasPorSemana[$s['id']] ?? []),
    ];
}

$nombreArchivoImagen = 'cronograma_' . strtolower(nombreMes($numeroMes)) . '_' . $anioMes;

$mensaje = $_GET['ok'] ?? null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Cronograma</h1>
        <p>Genera las semanas (domingo a sábado) del mes: el domingo y el jueves de cada semana comparten las mismas danzoras y el mismo uniforme.</p>
    </div>
</div>

<?php if ($mensaje === 'cronograma_generado'): ?>
    <div class="alerta alerta-exito">Se generaron las semanas de <?= h(nombreMesAnio($mes)) ?>.</div>
<?php elseif ($mensaje === 'cronograma_semana_guardada'): ?>
    <div class="alerta alerta-exito">El cronograma de esa semana se guardó correctamente.</div>
<?php elseif ($mensaje === 'cronograma_semana_eliminada'): ?>
    <div class="alerta alerta-exito">Se eliminó la semana del cronograma.</div>
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
            <button type="submit" formmethod="post" formaction="actions/generar_cronograma.php" class="btn btn-vino">Generar semanas del mes</button>
        </div>
    </form>
    <p style="margin:14px 0 0;color:var(--texto-suave);font-size:13px;">
        "Generar" crea en la tabla de abajo una fila por cada semana (domingo + jueves) del mes elegido — si el domingo de una semana es de este mes y su jueves ya es del mes siguiente (o al revés), esa semana igual se genera. Las semanas que ya existen no se duplican.
    </p>
</div>

<!-- ------------------------------- Uniformes ---------------------------------- -->
<div class="tarjeta">
    <h2>Uniformes</h2>
    <p style="color:var(--texto-suave);font-size:14px;margin-top:-8px;">
        Crea aquí los uniformes con su color; luego los eliges para cada semana del cronograma.
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
                    <form method="post" action="actions/eliminar_uniforme.php" style="display:inline;" onsubmit="return confirm('¿Eliminar el uniforme «<?= h(addslashes($u['nombre'])) ?>»? Las semanas que lo tenían quedarán sin uniforme definido.');">
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
    <div class="cronograma-cabecera-tarjeta">
        <h2>Cronograma de <?= h(nombreMesAnio($mes)) ?></h2>
        <?php if (!empty($semanas)): ?>
            <button type="button" id="btn-descargar-imagen" class="btn btn-teal btn-sm" onclick="descargarCronogramaImagen()">Descargar imagen</button>
        <?php endif; ?>
    </div>

    <?php if (empty($semanas)): ?>
        <div class="vacio">
            Todavía no hay semanas generadas para este mes.
            <p style="margin-top:10px;">Usa el botón "Generar semanas del mes" de arriba.</p>
        </div>
    <?php else: ?>
        <p class="ayuda-descarga">Usa "Descargar imagen" para generar una imagen lista para enviar al grupo de WhatsApp del ministerio.</p>
        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Semana</th>
                        <th>Danzoras</th>
                        <th>Uniforme</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semanas as $s):
                        $jueves = juevesDeLaSemana($s['fecha_domingo']);
                        $asignadas = $asignadasPorSemana[$s['id']] ?? [];
                    ?>
                        <tr>
                            <td class="nombre-nina">
                                <div class="fecha-semana">
                                    <div class="fecha-semana-dia">
                                        <span class="badge badge-dia-domingo">Domingo</span> <?= h(formatearFechaCorta($s['fecha_domingo'])) ?>
                                    </div>
                                    <div class="fecha-semana-dia">
                                        <span class="badge badge-dia-jueves">Jueves</span> <?= h(formatearFechaCorta($jueves)) ?>
                                    </div>
                                </div>
                            </td>
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
                                <?php if ($s['uniforme_id']): ?>
                                    <span class="uniforme-swatch" style="background:<?= h($s['uniforme_color']) ?>;"></span><?= h($s['uniforme_nombre']) ?>
                                <?php else: ?>
                                    <span style="color:var(--texto-suave);font-size:13px;">Sin definir</span>
                                <?php endif; ?>
                            </td>
                            <td class="acciones-fila">
                                <button type="button" class="btn btn-suave btn-sm" onclick="abrirModalCronograma(<?= (int) $s['id'] ?>)">Editar</button>
                                <form method="post" action="actions/eliminar_cronograma_fecha.php" onsubmit="return confirm('¿Eliminar la semana Domingo <?= h(addslashes(formatearFechaCorta($s['fecha_domingo']))) ?> · Jueves <?= h(addslashes(formatearFechaCorta($jueves))) ?> del cronograma?');" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
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
        <h2 id="modal-cronograma-titulo">Editar semana</h2>
        <form method="post" action="actions/guardar_cronograma_fecha.php" id="form-cronograma">
            <input type="hidden" name="cronograma_semana_id" id="cronograma_semana_id" value="">
            <input type="hidden" name="mes" value="<?= h($mes) ?>">

            <div class="campo">
                <label>¿Quiénes pasan al altar esta semana? (aplica al domingo y al jueves)</label>
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
                <label for="cronograma_uniforme_id">Uniforme (aplica al domingo y al jueves)</label>
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

<?php if (!empty($semanas)): ?>
<!-- ------------------- Plantilla oculta: imagen para WhatsApp -------------------- -->
<div id="plantilla-whatsapp" class="plantilla-whatsapp" data-nombre-archivo="<?= h($nombreArchivoImagen) ?>">
    <div class="pw-header">
        <img src="assets/img/logo.png" alt="" class="pw-logo">
        <div class="pw-titulos">
            <div class="pw-nombre"><?= h(APP_NOMBRE) ?></div>
            <div class="pw-subtitulo"><?= h(APP_SUBTITULO) ?></div>
        </div>
    </div>
    <div class="pw-mes">Cronograma de <?= h(nombreMesAnio($mes)) ?></div>
    <table class="pw-tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Danzoras</th>
                <th>Uniforme</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semanas as $s):
                $juevesPw = juevesDeLaSemana($s['fecha_domingo']);
                $asignadasPw = $asignadasPorSemana[$s['id']] ?? [];
            ?>
                <tr>
                    <td>
                        <div class="pw-fecha-dia pw-fecha-domingo"><strong>Domingo</strong> <?= h(formatearFechaCorta($s['fecha_domingo'])) ?></div>
                        <div class="pw-fecha-dia pw-fecha-jueves"><strong>Jueves</strong> <?= h(formatearFechaCorta($juevesPw)) ?></div>
                    </td>
                    <td>
                        <?php if (empty($asignadasPw)): ?>
                            <span class="pw-vacio">Sin asignar</span>
                        <?php else: ?>
                            <?php foreach ($asignadasPw as $a): ?>
                                <span class="pw-pill"><?= h($a['nombres']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['uniforme_id']): ?>
                            <span class="pw-swatch" style="background:<?= h($s['uniforme_color']) ?>;"></span><?= h($s['uniforme_nombre']) ?>
                        <?php else: ?>
                            <span class="pw-vacio">Sin definir</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pw-footer"><?= h(APP_NOMBRE) ?> · <?= h(APP_SUBTITULO) ?> — Casa de Dios</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<?php endif; ?>

<script>
    const DATOS_CRONOGRAMA = <?= json_encode($semanasJson, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
