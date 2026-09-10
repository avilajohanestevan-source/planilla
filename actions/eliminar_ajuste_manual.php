<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../puntaje.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare('DELETE FROM ajustes_manuales WHERE id = ?')->execute([$id]);
}

header('Location: ../puntaje.php?ok=ajuste_eliminado');
exit;
