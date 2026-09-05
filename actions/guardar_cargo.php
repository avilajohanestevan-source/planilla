<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ninas.php');
    exit;
}

$nombre      = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

if ($nombre === '') {
    header('Location: ../ninas.php');
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO cargos (nombre, descripcion) VALUES (?, ?)');
    $stmt->execute([$nombre, $descripcion !== '' ? $descripcion : null]);
} catch (PDOException $e) {
    // Nombre de cargo duplicado u otro error: simplemente regresa sin romper la página.
    header('Location: ../ninas.php');
    exit;
}

header('Location: ../ninas.php?ok=cargo_guardado');
exit;
