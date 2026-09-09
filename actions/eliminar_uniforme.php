<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$id  = (int) ($_POST['id'] ?? 0);
$mes = $_POST['mes'] ?? '';

if ($id > 0) {
    $pdo->prepare('DELETE FROM uniformes WHERE id = ?')->execute([$id]);
}

$destino = '../cronograma.php?ok=uniforme_eliminado';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $destino .= '&mes=' . urlencode($mes);
}
header('Location: ' . $destino);
exit;
