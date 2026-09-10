<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$paginaActual = 'planilla';
$tituloPagina = 'Planilla';

// --- Fecha del ensayo a mostrar (por defecto, el sábado más reciente) ---
$fecha = $_GET['fecha'] ?? sabadoPorDefecto();
if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    $fecha = sabadoPorDefecto();
}

// --- Busca el ensayo de esa fecha, o lo crea si aún no existe ---
$stmt = $pdo->prepare('SELECT id FROM ensayos WHERE fecha = ?');
$stmt->execute([$fecha]);
$ensayoId = $stmt->fetchColumn();

if (!$ensayoId) {
    $stmt = $pdo->prepare('INSERT INTO ensayos (fecha) VALUES (?)');
    $stmt->execute([$fecha]);
    $ensayoId = $pdo->lastInsertId();
}

// --- Niñas activas ---
$ninas = $pdo->query(
    "SELECT id, nombres, apellidos, apodo FROM ninas WHERE activa = 1 ORDER BY nombres, apellidos"
)->fetchAll();

// --- Categorías activas, en el orden de la planilla original ---
$categorias = $pdo->query(
    "SELECT id, nombre FROM categorias WHERE activa = 1 ORDER BY orden, id"
)->fetchAll();

// --- Registros ya guardados para este ensayo (para precargar el formulario) ---
$stmt = $pdo->prepare('SELECT nina_id, categoria_id, estado, puntos_extra FROM registros WHERE ensayo_id = ?');
$stmt->execute([$ensayoId]);
$registrosPrevios = [];
foreach ($stmt->fetchAll() as $r) {
    $registrosPrevios[$r['nina_id']][$r['categoria_id']] = $r;
}

$guardadoOk = isset($_GET['guardado']);

// Categorías en las que se permite el "añadido" de puntos extra manuales.
$categoriasConExtra = ['Calendario', 'Devocional', 'Audio Devocional', 'Comportamiento', 'Fallas'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Planilla del ensayo</h1>
        <p>Los ensayos son todos los sábados. Elige la fecha para ver o llenar esa planilla.</p>
    </div>
</div>

<?php if ($guardadoOk): ?>
    <div class="alerta alerta-exito">La planilla del <?= h(formatearFecha($fecha)) ?> se guardó correctamente.</div>
<?php endif; ?>

<div class="tarjeta">
    <form method="get" class="planilla-toolbar" id="form-fecha">
        <div class="campo">
            <label for="fecha">Fecha del ensayo (sábado)</label>
            <input type="date" id="fecha" name="fecha" value="<?= h($fecha) ?>">
            <div id="aviso-sabado" style="display:none;color:#c9a227;font-size:12px;font-weight:600;margin-top:4px;">
                Esa fecha no cae en sábado. Puedes continuar si es un ensayo especial.
            </div>
        </div>
        <button type="submit" class="btn btn-suave">Ver esta fecha</button>
    </form>
</div>

<?php if (empty($ninas)): ?>
    <div class="tarjeta vacio">
        Aún no has registrado niñas.
        <p><a href="ninas.php" class="btn btn-vino" style="margin-top:12px;">Registrar la primera niña</a></p>
    </div>
<?php else: ?>

<form method="post" action="actions/guardar_planilla.php">
    <input type="hidden" name="ensayo_id" value="<?= (int) $ensayoId ?>">
    <input type="hidden" name="fecha" value="<?= h($fecha) ?>">

    <div class="leyenda-caritas">
        <span>Feliz = +1</span>
        <span>Neutral = 0</span>
        <span>Triste = −1</span>
        <span>En Calendario, Devocional, Audio Devocional, Comportamiento y Fallas: doble clic en la carita feliz o triste agrega puntos extra (opcional).</span>
    </div>

    <div class="tabla-wrap">
        <table id="tabla-planilla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <?php foreach ($categorias as $cat): ?>
                        <th class="celda-categoria"><?= h($cat['nombre']) ?></th>
                    <?php endforeach; ?>
                    <th class="celda-categoria">Total del día</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ninas as $nina):
                    $nombreCompletoNina = $nina['nombres'] . ' ' . $nina['apellidos'];
                    $nombreEnPlanilla = !empty($nina['apodo']) ? $nina['apodo'] : $nombreCompletoNina;
                ?>
                    <tr data-fila-nina>
                        <td class="nombre-nina" title="<?= h($nombreCompletoNina) ?>"><?= h($nombreEnPlanilla) ?></td>
                        <?php foreach ($categorias as $cat):
                            $prev = $registrosPrevios[$nina['id']][$cat['id']] ?? null;
                            $estadoActual = $prev['estado'] ?? 'neutral';
                            $extraActual  = (int) ($prev['puntos_extra'] ?? 0);
                            $campo = 'r_' . $nina['id'] . '_' . $cat['id'];
                            $tieneExtra = in_array($cat['nombre'], $categoriasConExtra, true);
                            $extraClase = $extraActual > 0 ? 'extra-positivo' : ($extraActual < 0 ? 'extra-negativo' : '');
                            $extraTexto = $extraActual !== 0 ? (($extraActual > 0 ? '+' : '') . $extraActual) : '';
                        ?>
                            <td class="celda-categoria">
                                <div class="celda-calificacion" data-celda data-tiene-extra="<?= $tieneExtra ? '1' : '0' ?>">
                                    <div class="caritas-grupo" data-grupo-carita <?= ($tieneExtra && $extraActual !== 0) ? 'hidden' : '' ?>>
                                        <?php foreach (['feliz' => '😊', 'neutral' => '😐', 'triste' => '😞'] as $valor => $emoji):
                                            $conDoble = $tieneExtra && $valor !== 'neutral';
                                            $titulo = $conDoble ? ucfirst($valor) . ' (doble clic para puntos extra)' : ucfirst($valor);
                                        ?>
                                            <input type="radio" id="<?= $campo ?>_<?= $valor ?>"
                                                   name="estado[<?= (int) $nina['id'] ?>][<?= (int) $cat['id'] ?>]"
                                                   value="<?= $valor ?>" <?= $estadoActual === $valor ? 'checked' : '' ?>
                                                   data-puntos="<?= $valor === 'feliz' ? 1 : ($valor === 'triste' ? -1 : 0) ?>">
                                            <label for="<?= $campo ?>_<?= $valor ?>" title="<?= h($titulo) ?>"
                                                   <?= $conDoble ? 'data-extra-doble="' . $valor . '"' : '' ?>><?= $emoji ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($tieneExtra): ?>
                                        <input type="number" name="extra[<?= (int) $nina['id'] ?>][<?= (int) $cat['id'] ?>]"
                                               value="<?= $extraActual ?>" data-extra hidden>
                                        <button type="button" class="extra-valor <?= $extraClase ?>" data-extra-valor
                                                title="Doble clic para editar los puntos extra" <?= $extraActual === 0 ? 'hidden' : '' ?>><?= h($extraTexto) ?></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                        <td class="celda-categoria"><strong data-total-nina>0</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:22px;">
        <button type="submit" class="btn btn-vino">Guardar planilla</button>
    </div>
</form>

<!-- ---------------------------- Modal de puntos extra ---------------------------- -->
<div class="modal-fondo" id="modal-extra">
    <div class="modal-caja modal-caja-chica">
        <button type="button" class="modal-cerrar" onclick="cerrarModalExtra()">&times;</button>
        <h2 id="modal-extra-titulo">Puntos extra</h2>
        <p class="modal-extra-ayuda" id="modal-extra-ayuda"></p>
        <div class="campo">
            <label for="modal-extra-valor">¿Cuántos puntos?</label>
            <input type="number" id="modal-extra-valor" min="1" inputmode="numeric" placeholder="Ej. 10">
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn btn-suave" id="modal-extra-quitar" onclick="quitarExtraModal()" hidden>Quitar</button>
            <button type="button" class="btn btn-suave" onclick="cerrarModalExtra()">Cancelar</button>
            <button type="button" class="btn btn-vino" onclick="guardarExtraModal()">Guardar</button>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
