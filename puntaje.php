<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$paginaActual = 'puntaje';
$tituloPagina = 'Puntaje';

// --- Filtro de fechas (opcional): sin fechas se cuenta todo el historial ---
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
if ($desde !== '' && !DateTime::createFromFormat('Y-m-d', $desde)) {
    $desde = '';
}
if ($hasta !== '' && !DateTime::createFromFormat('Y-m-d', $hasta)) {
    $hasta = '';
}

$condicionesFecha = [];
$parametrosFecha = [];
if ($desde !== '') {
    $condicionesFecha[] = 'e.fecha >= ?';
    $parametrosFecha[] = $desde;
}
if ($hasta !== '') {
    $condicionesFecha[] = 'e.fecha <= ?';
    $parametrosFecha[] = $hasta;
}

// --- Categorías activas, para el selector del ranking por categoría ---
$categorias = $pdo->query(
    "SELECT id, nombre FROM categorias WHERE activa = 1 ORDER BY orden, id"
)->fetchAll();

$categoriaId = isset($_GET['categoria_id']) ? (int) $_GET['categoria_id'] : 0;
$categoriaValida = false;
foreach ($categorias as $c) {
    if ((int) $c['id'] === $categoriaId) {
        $categoriaValida = true;
        break;
    }
}
if (!$categoriaValida) {
    $categoriaId = (int) ($categorias[0]['id'] ?? 0);
}
$categoriaNombre = '';
foreach ($categorias as $c) {
    if ((int) $c['id'] === $categoriaId) {
        $categoriaNombre = $c['nombre'];
        break;
    }
}

/**
 * Arma el ranking de niñas activas para un conjunto de registros ya filtrado
 * (por fecha y, opcionalmente, por categoría). Las niñas sin registros en el
 * rango elegido aparecen igual, con todo en cero, para no "esconder" a nadie.
 *
 * @param string $condicionRegistros Cláusula WHERE completa (o '') que filtra
 *                                    la subconsulta de registros.
 * @param array  $parametrosRegistros Parámetros posicionales de esa cláusula.
 */
function armarRanking(PDO $pdo, string $condicionRegistros, array $parametrosRegistros): array
{
    $sql = "
        SELECT
            n.id,
            n.nombres,
            n.apellidos,
            COALESCE(SUM(CASE rf.estado WHEN 'feliz' THEN 1 WHEN 'triste' THEN -1 ELSE 0 END), 0) AS puntos_estado,
            COALESCE(SUM(rf.puntos_extra), 0) AS puntos_extra,
            COALESCE(SUM(CASE WHEN rf.estado = 'feliz' THEN 1 ELSE 0 END), 0) AS conteo_feliz,
            COALESCE(SUM(CASE WHEN rf.estado = 'neutral' THEN 1 ELSE 0 END), 0) AS conteo_neutral,
            COALESCE(SUM(CASE WHEN rf.estado = 'triste' THEN 1 ELSE 0 END), 0) AS conteo_triste,
            COALESCE(SUM(CASE rf.estado WHEN 'feliz' THEN 1 WHEN 'triste' THEN -1 ELSE 0 END), 0)
                + COALESCE(SUM(rf.puntos_extra), 0) AS puntaje_total
        FROM ninas n
        LEFT JOIN (
            SELECT r.nina_id, r.estado, r.puntos_extra
            FROM registros r
            JOIN ensayos e ON e.id = r.ensayo_id
            $condicionRegistros
        ) rf ON rf.nina_id = n.id
        WHERE n.activa = 1
        GROUP BY n.id, n.nombres, n.apellidos
        ORDER BY puntaje_total DESC, n.nombres ASC, n.apellidos ASC
    ";
    // Nota: MySQL/MariaDB no permite reutilizar dos alias agregados dentro de
    // una expresión en ORDER BY (error 1247, 'reference to group function');
    // por eso el total se calcula una sola vez como su propio alias
    // (puntaje_total) y el ORDER BY lo referencia directo, sin combinarlo.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametrosRegistros);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila['puntaje_total'] = (int) $fila['puntaje_total'];
    }
    unset($fila);
    return $filas;
}

$whereFechaSub = $condicionesFecha ? ('WHERE ' . implode(' AND ', $condicionesFecha)) : '';
$rankingGeneral = armarRanking($pdo, $whereFechaSub, $parametrosFecha);

$condicionCategoriaSub = 'WHERE r.categoria_id = ?' . ($condicionesFecha ? (' AND ' . implode(' AND ', $condicionesFecha)) : '');
$parametrosCategoria = array_merge([$categoriaId], $parametrosFecha);
$rankingCategoria = $categoriaId ? armarRanking($pdo, $condicionCategoriaSub, $parametrosCategoria) : [];

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

$datosGeneral   = datosParaGrafico($rankingGeneral);
$datosCategoria = datosParaGrafico($rankingCategoria);

$banderasJson = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

require_once __DIR__ . '/includes/header.php';
?>

<div class="encabezado-pagina">
    <div>
        <h1>Puntaje</h1>
        <p>Ranking de caritas y puntos: primero en general, y luego por cada categoría de la planilla.</p>
    </div>
</div>

<div class="tarjeta">
    <h2>Filtros</h2>
    <form method="get" action="puntaje.php" class="filtro-puntaje">
        <div class="campo">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= h($desde) ?>">
        </div>
        <div class="campo">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= h($hasta) ?>">
        </div>
        <?php if ($categorias): ?>
        <div class="campo">
            <label for="categoria_id">Categoría del segundo ranking</label>
            <select id="categoria_id" name="categoria_id">
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $categoriaId ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filtro-puntaje-acciones">
            <button type="submit" class="btn btn-vino">Aplicar</button>
            <?php if ($desde !== '' || $hasta !== ''): ?>
                <a href="puntaje.php<?= $categoriaId ? ('?categoria_id=' . (int) $categoriaId) : '' ?>" class="btn btn-suave btn-sm">Quitar fechas</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($desde === '' && $hasta === ''): ?>
        <p style="margin:14px 0 0;color:var(--texto-suave);font-size:13px;">Sin fechas elegidas se cuenta todo el historial guardado.</p>
    <?php endif; ?>
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

<?php if ($categorias): ?>
<div class="tarjeta">
    <h2>Ranking por categoría — <?= h($categoriaNombre) ?></h2>
    <?php if (empty($rankingCategoria)): ?>
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
                <?php foreach ($rankingCategoria as $i => $fila):
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
    <div id="grafico-categoria" class="chart-caja chart-caja-chica"></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script>
(function () {
    if (typeof am5 === 'undefined') { return; }

    var datosGeneral   = <?= json_encode($datosGeneral, $banderasJson) ?>;
    var datosCategoria = <?= json_encode($datosCategoria, $banderasJson) ?>;

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
    graficoRanking('grafico-categoria', datosCategoria);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
