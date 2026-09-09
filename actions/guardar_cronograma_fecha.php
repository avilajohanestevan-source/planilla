<?php
/**
 * Guarda el uniforme y las danzoras de UNA SEMANA del Cronograma (domingo a
 * sábado). Ese mismo dato aplica al domingo y al jueves de la semana — ya no
 * se guarda por fecha individual (ver Fase 5 en la doc de arquitectura).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$semanaId   = (int) ($_POST['cronograma_semana_id'] ?? 0);
$ninas      = $_POST['ninas'] ?? [];
$uniformeId = (int) ($_POST['uniforme_id'] ?? 0);
$mes        = $_POST['mes'] ?? '';

if ($semanaId <= 0) {
    header('Location: ../cronograma.php');
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE cronograma_semanas SET uniforme_id = ? WHERE id = ?');
    $stmt->execute([$uniformeId > 0 ? $uniformeId : null, $semanaId]);

    $pdo->prepare('DELETE FROM cronograma_semana_asignaciones WHERE semana_id = ?')->execute([$semanaId]);
    if (!empty($ninas)) {
        $insAsignacion = $pdo->prepare('INSERT INTO cronograma_semana_asignaciones (semana_id, nina_id) VALUES (?, ?)');
        foreach ($ninas as $ninaId) {
            $insAsignacion->execute([$semanaId, (int) $ninaId]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Ocurrió un error guardando el cronograma: ' . htmlspecialchars($e->getMessage()));
}

$destino = '../cronograma.php?ok=cronograma_semana_guardada';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $destino .= '&mes=' . urlencode($mes);
}
header('Location: ' . $destino);
exit;
