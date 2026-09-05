<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ninas.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $pdo->prepare('DELETE FROM cargos WHERE id = ?')->execute([$id]);
}

header('Location: ../ninas.php?ok=cargo_eliminado');
exit;
