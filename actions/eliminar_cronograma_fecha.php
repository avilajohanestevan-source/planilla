<?php
/**
 * Elimina UNA SEMANA completa del Cronograma (domingo + jueves de esa
 * semana), no una fecha individual (ver Fase 5 en la doc de arquitectura).
 * El ON DELETE CASCADE de cronograma_semana_asignaciones se encarga de
 * borrar también las danzoras asignadas a esa semana.
 */
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$id  = (int) ($_POST['id'] ?? 0);
$mes = $_POST['mes'] ?? '';

if ($id > 0) {
    $pdo->prepare('DELETE FROM cronograma_semanas WHERE id = ?')->execute([$id]);
}

$destino = '../cronograma.php?ok=cronograma_semana_eliminada';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $destino .= '&mes=' . urlencode($mes);
}
header('Location: ' . $destino);
exit;
