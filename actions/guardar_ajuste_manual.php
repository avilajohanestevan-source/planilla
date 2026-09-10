<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../puntaje.php');
    exit;
}

$ninaId = (int) ($_POST['nina_id'] ?? 0);
$categoriaId = (int) ($_POST['categoria_id'] ?? 0);
$feliz = max(0, (int) ($_POST['conteo_feliz'] ?? 0));
$neutral = max(0, (int) ($_POST['conteo_neutral'] ?? 0));
$triste = max(0, (int) ($_POST['conteo_triste'] ?? 0));
$extra = (int) ($_POST['puntos_extra'] ?? 0);
$nota = trim($_POST['nota'] ?? '');
if ($nota === '') {
    $nota = null;
}

// Si no hay nada que sumar, no tiene sentido guardar un ajuste vacío.
if ($ninaId <= 0 || $categoriaId <= 0 || ($feliz === 0 && $neutral === 0 && $triste === 0 && $extra === 0)) {
    header('Location: ../puntaje.php?error=ajuste_incompleto');
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO ajustes_manuales (nina_id, categoria_id, fecha, conteo_feliz, conteo_neutral, conteo_triste, puntos_extra, nota)
     VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)'
);
$stmt->execute([$ninaId, $categoriaId, $feliz, $neutral, $triste, $extra, $nota]);

header('Location: ../puntaje.php?ok=ajuste_guardado');
exit;
