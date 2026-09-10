<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../puntaje.php');
    exit;
}

$fechaDesde = trim($_POST['fecha_desde'] ?? '');
$fechaHasta = trim($_POST['fecha_hasta'] ?? '');
$nota = trim($_POST['nota'] ?? '');
if ($nota === '') {
    $nota = null;
}

$desdeValida = $fechaDesde !== '' && DateTime::createFromFormat('Y-m-d', $fechaDesde) !== false;
$hastaValida = $fechaHasta !== '' && DateTime::createFromFormat('Y-m-d', $fechaHasta) !== false;

if (!$desdeValida || !$hastaValida || $fechaHasta < $fechaDesde) {
    header('Location: ../puntaje.php?error=cierre_fechas_invalidas');
    exit;
}

// Foto del ranking general de ese rango exacto — no toca registros ni ajustes_manuales.
$ranking = armarRanking($pdo, null, $fechaDesde, $fechaHasta);

$pdo->beginTransaction();
try {
    $stmtCierre = $pdo->prepare(
        'INSERT INTO puntaje_cierres (fecha_desde, fecha_hasta, nota) VALUES (?, ?, ?)'
    );
    $stmtCierre->execute([$fechaDesde, $fechaHasta, $nota]);
    $cierreId = (int) $pdo->lastInsertId();

    $stmtDetalle = $pdo->prepare(
        'INSERT INTO puntaje_cierre_detalle
            (cierre_id, nina_id, nina_nombre_completo, puesto, conteo_feliz, conteo_neutral, conteo_triste, puntos_extra, puntaje_total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($ranking as $i => $fila) {
        $stmtDetalle->execute([
            $cierreId,
            (int) $fila['id'],
            trim($fila['nombres'] . ' ' . $fila['apellidos']),
            $i + 1,
            (int) $fila['conteo_feliz'],
            (int) $fila['conteo_neutral'],
            (int) $fila['conteo_triste'],
            (int) $fila['puntos_extra'],
            (int) $fila['puntaje_total'],
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: ../puntaje.php?error=cierre_fallo');
    exit;
}

header('Location: ../puntaje.php?ok=periodo_cerrado');
exit;
