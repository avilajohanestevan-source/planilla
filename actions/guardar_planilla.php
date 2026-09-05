<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../planilla.php');
    exit;
}

$ensayoId = (int) ($_POST['ensayo_id'] ?? 0);
$fecha    = $_POST['fecha'] ?? sabadoPorDefecto();
$estados  = $_POST['estado'] ?? [];
$extras   = $_POST['extra'] ?? [];

if ($ensayoId <= 0) {
    header('Location: ../planilla.php');
    exit;
}

$sql = "INSERT INTO registros (ensayo_id, nina_id, categoria_id, estado, puntos_extra)
        VALUES (:ensayo_id, :nina_id, :categoria_id, :estado, :puntos_extra)
        ON DUPLICATE KEY UPDATE estado = VALUES(estado), puntos_extra = VALUES(puntos_extra)";
$stmt = $pdo->prepare($sql);

$pdo->beginTransaction();
try {
    foreach ($estados as $ninaId => $porCategoria) {
        foreach ($porCategoria as $categoriaId => $estado) {
            if (!in_array($estado, ['feliz', 'neutral', 'triste'], true)) {
                $estado = 'neutral';
            }
            $extra = (int) ($extras[$ninaId][$categoriaId] ?? 0);

            $stmt->execute([
                ':ensayo_id'    => $ensayoId,
                ':nina_id'      => (int) $ninaId,
                ':categoria_id' => (int) $categoriaId,
                ':estado'       => $estado,
                ':puntos_extra' => $extra,
            ]);
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Ocurrió un error guardando la planilla: ' . htmlspecialchars($e->getMessage()));
}

header('Location: ../planilla.php?fecha=' . urlencode($fecha) . '&guardado=1');
exit;
