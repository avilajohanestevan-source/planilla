<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$paginaActual = 'puntaje';
$tituloPagina = 'Puntaje';

// --- Categorías activas (se muestran todas, una tabla por cada una) ---
$categorias = $pdo->query(
    "SELECT id, nombre FROM categorias WHERE activa = 1 ORDER BY orden, id"
)->fetchAll();

// --- Niñas activas, para el formulario de ajuste manual ---
$ninasActivas = $pdo->query(
    "SELECT id, nombres, apellidos FROM ninas WHERE activa = 1 ORDER BY nombres, apellidos"
)->fetchAll();

// --- Filtro de fechas ---
// Si la página se abre sin parámetros (primera carga, o clic en "Puntaje" del
// menú), el periodo "actual" arranca solo el día siguiente al último cierre
// guardado (o desde el principio si nunca se ha cerrado uno) — así, después
// de cada premiación, el ranking que se ve por defecto vuelve a arrancar en
// cero sin necesidad de tocar nada. Si el formulario de filtro SÍ se envió
// (aunque sea con las fechas en blanco, ej. "Ver todo el historial"), se
// respeta exactamente lo que la usuaria haya puesto.
$ultimoCierre = ultimoCierreHasta($pdo);
$filtroEnviado = isset($_GET['desde']) || isset($_GET['hasta']);

if ($filtroEnviado) {
    $desde = trim($_GET['desde'] ?? '');
    $hasta = trim($_GET['hasta'] ?? '');
    if ($desde !== '' && !DateTime::createFromFormat('Y-m-d', $desde)) {
        $desde = '';
    }
    if ($hasta !== '' && !DateTime::createFromFormat('Y-m-d', $hasta)) {
        $hasta = '';
    }
} else {
    $desde = $ultimoCierre ? diaSiguiente($ultimoCierre) : '';
    $hasta = '';
}

// --- Rankings ---
$rankingGeneral = armarRanking($pdo, null, $desde ?: null, $hasta ?: null);

$rankingsPorCategoria = [];
foreach ($categorias as $cat) {
    $rankingsPorCategoria[] = [
        'categoria' => $cat,
        'ranking'   => armarRanking($pdo, (int) $cat['id'], $desde ?: null, $hasta ?: null),
    ];
}

$medallas = ['🥇', '🥈', '🥉'];

/** Convierte una tabla de ranking en los puntos {nombre, puntaje} que necesita amCharts. */
function datosParaGrafico(array $ranking): array
{
    $datos = [];
    foreach ($ranking as $fila) {
        $datos[] = [
            'nombre'  => $fila['nombres'] . ' ' . $fila['apellidos'],
            'puntaje' => $fila['puntaje_total'],
        ];
    }
    // Se deja en el mismo orden del ranking (mejor puntaje primero): con
    // categoryAxis renderer "inversed: true" el primer dato del arreglo se
    // dibuja arriba, así que el primer lugar queda arriba en el gráfico.
    return $datos;
}

$datosGeneral = datosParaGrafico($rankingGeneral);

$banderasJson = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

// --- Ajustes manuales existentes (para la lista con opción de eliminar) ---
$ajustesManuales = $pdo->query(
    "SELECT am.id, am.conteo_feliz, am.conteo_neutral, am.conteo_triste, am.puntos_extra, am.nota, am.fecha,
            n.nombres, n.apellidos, c.nombre AS categoria_nombre
     FROM ajustes_manuales am
     JOIN ninas n ON n.id = am.nina_id
     JOIN categorias c ON c.id = am.categoria_id
     ORDER BY am.creado_en DESC"
)->fetchAll();

// --- Cierres de periodo ya guardados, con su detalle (para el historial) ---
$cierres = $pdo->query(
    "SELECT id, fecha_desde, fecha_hasta, nota, creado_en FROM puntaje_cierres ORDER BY fecha_hasta DESC, id DESC"
)->fetchAll();
$detallePorCierre = [];
if ($cierres) {
    $stmtDetalle = $pdo->prepare(
        "SELECT nina_nombre_completo, puesto, conteo_feliz, conteo_neutral, conteo_triste, puntos_extra, puntaje_total
         FROM puntaje_cierre_detalle WHERE cierre_id = ? ORDER BY puesto ASC"
    );
    foreach ($cierres as $c) {
        $stmtDetalle->execute([$c['id']]);
        $detallePorCierre[$c['id']] = $stmtDetalle->fetchAll();
    }
}

// --- Fechas sugeridas para el próximo cierre ---
$sugerenciaDesde = $ultimoCierre ? diaSiguiente($ultimoCierre) : '';
$sugerenciaHasta = (new DateTime('today'))->format('Y-m-d');

$mensaje = $_GET['ok'] ?? null;
$error = $_GET['error'] ?? null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Puntaje</h1>
        <p>Ranking de caritas y puntos: primero en general, y luego por cada categoría de la planilla.</p>
    </div>
</div>

<?php if ($mensaje === 'ajuste_guardado'): ?>
    <div class="alerta alerta-exito">El ajuste manual se guardó y ya está sumando en el ranking.</div>
<?php elseif ($mensaje === 'ajuste_eliminado'): ?>
    <div class="alerta alerta-exito">El ajuste manual se eliminó.</div>
<?php elseif ($mensaje === 'periodo_cerrado'): ?>
    <div class="alerta alerta-exito">El periodo se cerró y quedó guardado en el historial. El ranking de arriba ya vuelve a arrancar desde cero.</div>
<?php elseif ($error === 'ajuste_incompleto'): ?>
    <div class="alerta alerta-error">Elige la niña, la categoría, y al menos una carita o un punto extra para guardar el ajuste.</div>
<?php elseif ($error === 'cierre_fechas_invalidas'): ?>
    <div class="alerta alerta-error">Revisa las fechas del cierre: hace falta un "desde" y un "hasta" válidos, y el "hasta" no puede ser antes del "desde".</div>
<?php elseif ($error === 'cierre_fallo'): ?>
    <div class="alerta alerta-error">No se pudo guardar el cierre de periodo. Intenta de nuevo.</div>
<?php endif; ?>

<div class="tarjeta">
    <h2>Filtro de fechas</h2>
    <form method="get" action="puntaje.php" class="filtro-puntaje">
        <div class="campo">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= h($desde) ?>">
        </div>
        <div class="campo">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= h($hasta) ?>">
        </div>
        <div class="filtro-puntaje-acciones">
            <button type="submit" class="btn btn-vino">Aplicar</button>
            <a href="puntaje.php?desde=&hasta=" class="btn btn-suave btn-sm">Ver todo el historial</a>
            <?php if ($ultimoCierre): ?>
                <a href="puntaje.php" class="btn btn-suave btn-sm">Ver el periodo actual</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="aviso-periodo">
        <?php if (!$filtroEnviado && $ultimoCierre): ?>
            Mostrando el periodo actual: desde el <?= h(formatearFecha($desde)) ?> (día siguiente al último cierre, que llegó hasta el <?= h(formatearFecha($ultimoCierre)) ?>).
        <?php elseif (!$filtroEnviado && !$ultimoCierre): ?>
            Todavía no has cerrado ningún periodo, así que se cuenta todo el historial guardado.
        <?php elseif ($desde === '' && $hasta === ''): ?>
            Mostrando todo el historial guardado, sin límite de fechas.
        <?php else: ?>
            Mostrando el rango de fechas que elegiste arriba.
        <?php endif; ?>
    </p>
</div>

<?php if (empty($rankingGeneral)): ?>
    <div class="tarjeta vacio">
        Aún no hay niñas activas para armar un ranking.
        <p><a href="ninas.php" class="btn btn-vino" style="margin-top:12px;">Registrar la primera niña</a></p>
    </div>
<?php else: ?>

<div class="tarjeta">
    <h2>Ranking general — quién tiene más caritas</h2>
    <div class="tabla-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Niña</th>
                    <th class="celda-categoria">😊</th>
                    <th class="celda-categoria">😐</th>
                    <th class="celda-categoria">😞</th>
                    <th class="celda-categoria">Extra</th>
                    <th class="celda-categoria">Puntaje total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankingGeneral as $i => $fila):
                    $clasePuesto = $i === 0 ? 'fila-oro' : ($i === 1 ? 'fila-plata' : ($i === 2 ? 'fila-bronce' : ''));
                    $puntaje = $fila['puntaje_total'];
                    $claseTotal = $puntaje > 0 ? 'puntaje-positivo' : ($puntaje < 0 ? 'puntaje-negativo' : 'puntaje-cero');
                    $extra = (int) $fila['puntos_extra'];
                    $extraTexto = $extra > 0 ? ('+' . $extra) : (string) $extra;
                ?>
                    <tr class="<?= $clasePuesto ?>">
                        <td class="puesto"><?= $medallas[$i] ?? ($i + 1) ?></td>
                        <td class="nombre-nina"><?= h($fila['nombres'] . ' ' . $fila['apellidos']) ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_feliz'] ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_neutral'] ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_triste'] ?></td>
                        <td class="celda-categoria"><?= h($extraTexto) ?></td>
                        <td class="celda-categoria puntaje-total <?= $claseTotal ?>"><?= $puntaje ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="grafico-general" class="chart-caja"></div>
</div>

<?php foreach ($rankingsPorCategoria as $bloque):
    $cat = $bloque['categoria'];
    $ranking = $bloque['ranking'];
?>
<div class="tarjeta">
    <h2>Ranking por categoría — <?= h($cat['nombre']) ?></h2>
    <?php if (empty($ranking)): ?>
        <p style="color:var(--texto-suave);">No hay registros en esta categoría para el rango elegido.</p>
    <?php else: ?>
    <div class="tabla-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Niña</th>
                    <th class="celda-categoria">😊</th>
                    <th class="celda-categoria">😐</th>
                    <th class="celda-categoria">😞</th>
                    <th class="celda-categoria">Extra</th>
                    <th class="celda-categoria">Puntaje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $i => $fila):
                    $clasePuesto = $i === 0 ? 'fila-oro' : ($i === 1 ? 'fila-plata' : ($i === 2 ? 'fila-bronce' : ''));
                    $puntaje = $fila['puntaje_total'];
                    $claseTotal = $puntaje > 0 ? 'puntaje-positivo' : ($puntaje < 0 ? 'puntaje-negativo' : 'puntaje-cero');
                    $extra = (int) $fila['puntos_extra'];
                    $extraTexto = $extra > 0 ? ('+' . $extra) : (string) $extra;
                ?>
                    <tr class="<?= $clasePuesto ?>">
                        <td class="puesto"><?= $medallas[$i] ?? ($i + 1) ?></td>
                        <td class="nombre-nina"><?= h($fila['nombres'] . ' ' . $fila['apellidos']) ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_feliz'] ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_neutral'] ?></td>
                        <td class="celda-categoria"><?= (int) $fila['conteo_triste'] ?></td>
                        <td class="celda-categoria"><?= h($extraTexto) ?></td>
                        <td class="celda-categoria puntaje-total <?= $claseTotal ?>"><?= $puntaje ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- ------------------------------ Ajuste manual de caritas ------------------------------ -->
<div class="tarjeta">
    <h2>Ajuste manual de caritas</h2>
    <p style="color:var(--texto-suave);margin-top:-6px;">Para meter de una sola vez las caritas que ya tenías anotadas en papel: elige la niña y la categoría, y escribe cuántas ya tiene acumuladas. Se suman al ranking igual que si fueran registros normales.</p>
    <?php if (empty($ninasActivas) || empty($categorias)): ?>
        <p style="color:var(--texto-suave);">Hace falta al menos una niña activa y una categoría para poder registrar un ajuste.</p>
    <?php else: ?>
    <form method="post" action="actions/guardar_ajuste_manual.php">
        <div class="fila-campos">
            <div class="campo">
                <label for="ajuste-nina">Niña</label>
                <select id="ajuste-nina" name="nina_id" required>
                    <option value="">Elige una niña</option>
                    <?php foreach ($ninasActivas as $n): ?>
                        <option value="<?= (int) $n['id'] ?>"><?= h($n['nombres'] . ' ' . $n['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="ajuste-categoria">Categoría</label>
                <select id="ajuste-categoria" name="categoria_id" required>
                    <option value="">Elige una categoría</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= h($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="fila-campos fila-campos-caritas">
            <div class="campo">
                <label for="ajuste-feliz">😊 Feliz</label>
                <input type="number" id="ajuste-feliz" name="conteo_feliz" min="0" value="0">
            </div>
            <div class="campo">
                <label for="ajuste-neutral">😐 Neutral</label>
                <input type="number" id="ajuste-neutral" name="conteo_neutral" min="0" value="0">
            </div>
            <div class="campo">
                <label for="ajuste-triste">😞 Triste</label>
                <input type="number" id="ajuste-triste" name="conteo_triste" min="0" value="0">
            </div>
            <div class="campo">
                <label for="ajuste-extra">Puntos extra</label>
                <input type="number" id="ajuste-extra" name="puntos_extra" value="0">
            </div>
        </div>
        <div class="campo">
            <label for="ajuste-nota">Nota (opcional)</label>
            <input type="text" id="ajuste-nota" name="nota" maxlength="255" placeholder="Ej. Traído de la planilla en papel de agosto">
        </div>
        <button type="submit" class="btn btn-vino">Guardar ajuste</button>
    </form>
    <?php endif; ?>

    <?php if ($ajustesManuales): ?>
    <div class="tabla-wrap" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Niña</th>
                    <th>Categoría</th>
                    <th class="celda-categoria">😊</th>
                    <th class="celda-categoria">😐</th>
                    <th class="celda-categoria">😞</th>
                    <th class="celda-categoria">Extra</th>
                    <th>Nota</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ajustesManuales as $a): ?>
                    <tr>
                        <td class="nombre-nina"><?= h($a['nombres'] . ' ' . $a['apellidos']) ?></td>
                        <td><?= h($a['categoria_nombre']) ?></td>
                        <td class="celda-categoria"><?= (int) $a['conteo_feliz'] ?></td>
                        <td class="celda-categoria"><?= (int) $a['conteo_neutral'] ?></td>
                        <td class="celda-categoria"><?= (int) $a['conteo_triste'] ?></td>
                        <td class="celda-categoria"><?= (int) $a['puntos_extra'] ?></td>
                        <td style="color:var(--texto-suave);font-size:13px;"><?= h($a['nota'] ?? '—') ?></td>
                        <td style="white-space:nowrap;"><?= h(formatearFecha($a['fecha'])) ?></td>
                        <td>
                            <form method="post" action="actions/eliminar_ajuste_manual.php" onsubmit="return confirm('¿Eliminar este ajuste manual?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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

<!-- ------------------------------ Cierre de periodo ------------------------------ -->
<div class="tarjeta">
    <h2>Cerrar periodo (premiación)</h2>
    <p style="color:var(--texto-suave);margin-top:-6px;">Guarda una foto del ranking general del rango que elijas — sin borrar ni tocar los registros ni los ajustes manuales — y hace que el ranking de arriba vuelva a arrancar desde cero a partir del día siguiente.</p>
    <form method="post" action="actions/cerrar_periodo_puntaje.php" onsubmit="return confirm('¿Cerrar este periodo? Se guardará el ranking general de ese rango en el historial. No se borra ningún dato.');">
        <div class="fila-campos">
            <div class="campo">
                <label for="cierre-desde">Desde</label>
                <input type="date" id="cierre-desde" name="fecha_desde" value="<?= h($sugerenciaDesde) ?>" required>
            </div>
            <div class="campo">
                <label for="cierre-hasta">Hasta</label>
                <input type="date" id="cierre-hasta" name="fecha_hasta" value="<?= h($sugerenciaHasta) ?>" required>
            </div>
            <div class="campo">
                <label for="cierre-nota">Nota (opcional)</label>
                <input type="text" id="cierre-nota" name="nota" maxlength="255" placeholder="Ej. Premiación de septiembre 2026">
            </div>
        </div>
        <button type="submit" class="btn btn-vino">Cerrar periodo y guardar en el historial</button>
    </form>

    <?php if ($cierres): ?>
    <h3 style="margin-top:24px;">Cierres anteriores</h3>
    <?php foreach ($cierres as $c): ?>
        <details class="cierre-historial">
            <summary>
                <?= h(formatearFecha($c['fecha_desde'])) ?> — <?= h(formatearFecha($c['fecha_hasta'])) ?>
                <?php if ($c['nota']): ?> · <?= h($c['nota']) ?><?php endif; ?>
            </summary>
            <div class="tabla-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Niña</th>
                            <th class="celda-categoria">😊</th>
                            <th class="celda-categoria">😐</th>
                            <th class="celda-categoria">😞</th>
                            <th class="celda-categoria">Extra</th>
                            <th class="celda-categoria">Puntaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detallePorCierre[$c['id']] as $fila):
                            $puntaje = (int) $fila['puntaje_total'];
                            $claseTotal = $puntaje > 0 ? 'puntaje-positivo' : ($puntaje < 0 ? 'puntaje-negativo' : 'puntaje-cero');
                            $extra = (int) $fila['puntos_extra'];
                            $extraTexto = $extra > 0 ? ('+' . $extra) : (string) $extra;
                            $puesto = (int) $fila['puesto'];
                            $clasePuesto = $puesto === 1 ? 'fila-oro' : ($puesto === 2 ? 'fila-plata' : ($puesto === 3 ? 'fila-bronce' : ''));
                        ?>
                            <tr class="<?= $clasePuesto ?>">
                                <td class="puesto"><?= $medallas[$puesto - 1] ?? $puesto ?></td>
                                <td class="nombre-nina"><?= h($fila['nina_nombre_completo']) ?></td>
                                <td class="celda-categoria"><?= (int) $fila['conteo_feliz'] ?></td>
                                <td class="celda-categoria"><?= (int) $fila['conteo_neutral'] ?></td>
                                <td class="celda-categoria"><?= (int) $fila['conteo_triste'] ?></td>
                                <td class="celda-categoria"><?= h($extraTexto) ?></td>
                                <td class="celda-categoria puntaje-total <?= $claseTotal ?>"><?= $puntaje ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script>
(function () {
    if (typeof am5 === 'undefined') { return; }

    var datosGeneral = <?= json_encode($datosGeneral, $banderasJson) ?>;

    function graficoRanking(idContenedor, datos) {
        var el = document.getElementById(idContenedor);
        if (!el || !datos || !datos.length) {
            return;
        }

        var root = am5.Root.new(idContenedor);
        root.setThemes([am5themes_Animated.new(root)]);

        var chart = root.container.children.push(am5xy.XYChart.new(root, {
            panX: false,
            panY: false,
            wheelX: 'none',
            wheelY: 'none',
            layout: root.verticalLayout
        }));

        var yRenderer = am5xy.AxisRendererY.new(root, { inversed: true, minGridDistance: 6 });
        yRenderer.grid.template.set('visible', false);

        var yAxis = chart.yAxes.push(am5xy.CategoryAxis.new(root, {
            categoryField: 'nombre',
            renderer: yRenderer
        }));
        yAxis.data.setAll(datos);

        var minimo = Math.min.apply(null, datos.map(function (d) { return d.puntaje; }));
        var configEjeX = { renderer: am5xy.AxisRendererX.new(root, {}) };
        if (minimo >= 0) {
            configEjeX.min = 0;
        }
        var xAxis = chart.xAxes.push(am5xy.ValueAxis.new(root, configEjeX));

        var series = chart.series.push(am5xy.ColumnSeries.new(root, {
            xAxis: xAxis,
            yAxis: yAxis,
            valueXField: 'puntaje',
            categoryYField: 'nombre'
        }));

        series.columns.template.setAll({
            cornerRadiusTR: 6,
            cornerRadiusBR: 6,
            strokeOpacity: 0,
            tooltipText: '{categoryY}: {valueX} puntos'
        });

        series.columns.template.adapters.add('fill', function (fill, target) {
            var ctx = target.dataItem && target.dataItem.dataContext;
            if (!ctx) {
                return fill;
            }
            if (ctx.puntaje < 0) {
                return am5.color(0xc94f4f);
            }
            if (ctx.puntaje === 0) {
                return am5.color(0xc9a227);
            }
            return am5.color(0x1b9593);
        });

        series.data.setAll(datos);
        series.appear(800);
        chart.appear(800, 100);
    }

    graficoRanking('grafico-general', datosGeneral);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
