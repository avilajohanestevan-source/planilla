<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$cronogramaFechaId = (int) ($_POST['cronograma_fecha_id'] ?? 0);
$ninas              = $_POST['ninas'] ?? [];
$uniformeId         = (int) ($_POST['uniforme_id'] ?? 0);
$mes                = $_POST['mes'] ?? '';

if ($cronogramaFechaId <= 0) {
    header('Location: ../cronograma.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE cronograma_fechas SET uniforme_id = ? WHERE id = ?');
    $stmt->execute([$uniformeId > 0 ? $uniformeId : null, $cronogramaFechaId]);

    $pdo->prepare('DELETE FROM cronograma_asignaciones WHERE cronograma_fecha_id = ?')->execute([$cronogramaFechaId]);
    if (!empty($ninas)) {
        $insAsignacion = $pdo->prepare('INSERT INTO cronograma_asignaciones (cronograma_fecha_id, nina_id) VALUES (?, ?)');
        foreach ($ninas as $ninaId) {
            $insAsignacion->execute([$cronogramaFechaId, (int) $ninaId]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Ocurrió un error guardando el cronograma: ' . htmlspecialchars($e->getMessage()));
}

$destino = '../cronograma.php?ok=cronograma_fecha_guardada';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $destino .= '&mes=' . urlencode($mes);
}
header('Location: ' . $destino);
exit;
