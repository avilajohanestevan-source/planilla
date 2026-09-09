<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$color  = trim($_POST['color'] ?? '');
$mes    = $_POST['mes'] ?? '';

$destino = '../cronograma.php';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $destino .= '?mes=' . urlencode($mes);
}

if ($nombre === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    header('Location: ' . $destino);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO uniformes (nombre, color) VALUES (?, ?)');
    $stmt->execute([$nombre, $color]);
} catch (PDOException $e) {
    // Nombre de uniforme duplicado u otro error: regresa sin romper la página.
    header('Location: ' . $destino);
    exit;
}

$destino .= (str_contains($destino, '?') ? '&' : '?') . 'ok=uniforme_guardado';
header('Location: ' . $destino);
exit;
