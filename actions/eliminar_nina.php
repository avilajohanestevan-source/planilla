<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ninas.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? 'desactivar';

if ($id > 0) {
    $activa = $accion === 'reactivar' ? 1 : 0;
    $stmt = $pdo->prepare('UPDATE ninas SET activa = ? WHERE id = ?');
    $stmt->execute([$activa, $id]);
}

$mensaje = $accion === 'reactivar' ? 'nina_reactivada' : 'nina_desactivada';
header('Location: ../ninas.php?ok=' . $mensaje);
exit;
