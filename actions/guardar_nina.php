<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../ninas.php');
    exit;
}

$id               = (int) ($_POST['id'] ?? 0);
$nombres          = trim($_POST['nombres'] ?? '');
$apellidos        = trim($_POST['apellidos'] ?? '');
$fechaNacimiento  = $_POST['fecha_nacimiento'] ?? '';
$fechaCumpleanos  = $_POST['fecha_cumpleanos'] ?? '';
$cargos           = $_POST['cargos'] ?? [];

if ($nombres === '' || $apellidos === '' || !DateTime::createFromFormat('Y-m-d', $fechaNacimiento)) {
    die('Faltan datos obligatorios (nombres, apellidos y fecha de nacimiento). <a href="../ninas.php">Volver</a>');
}

$fechaCumpleanos = $fechaCumpleanos !== '' ? $fechaCumpleanos : null;

$pdo->beginTransaction();
try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE ninas SET nombres = ?, apellidos = ?, fecha_nacimiento = ?, fecha_cumpleanos = ? WHERE id = ?'
        );
        $stmt->execute([$nombres, $apellidos, $fechaNacimiento, $fechaCumpleanos, $id]);
        $ninaId = $id;
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO ninas (nombres, apellidos, fecha_nacimiento, fecha_cumpleanos) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombres, $apellidos, $fechaNacimiento, $fechaCumpleanos]);
        $ninaId = $pdo->lastInsertId();
    }

    // Actualiza los cargos asignados (borra y vuelve a insertar los seleccionados)
    $pdo->prepare('DELETE FROM nina_cargo WHERE nina_id = ?')->execute([$ninaId]);
    if (!empty($cargos)) {
        $insCargo = $pdo->prepare('INSERT INTO nina_cargo (nina_id, cargo_id) VALUES (?, ?)');
        foreach ($cargos as $cargoId) {
            $insCargo->execute([$ninaId, (int) $cargoId]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Ocurrió un error guardando la niña: ' . htmlspecialchars($e->getMessage()));
}

header('Location: ../ninas.php?ok=nina_guardada');
exit;
