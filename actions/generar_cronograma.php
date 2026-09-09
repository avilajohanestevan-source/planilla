<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cronograma.php');
    exit;
}

$mes = $_POST['mes'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    header('Location: ../cronograma.php');
    exit;
}

[$anio, $numeroMes] = array_map('intval', explode('-', $mes));
if ($numeroMes < 1 || $numeroMes > 12) {
    header('Location: ../cronograma.php');
    exit;
}

// Recorre cada día del mes elegido y guarda los que caen jueves (4) o domingo (7).
$primerDia = new DateTime(sprintf('%04d-%02d-01', $anio, $numeroMes));
$diasDelMes = (int) $primerDia->format('t');

$insertar = $pdo->prepare('INSERT IGNORE INTO cronograma_fechas (fecha) VALUES (?)');
for ($dia = 1; $dia <= $diasDelMes; $dia++) {
    $fecha = new DateTime(sprintf('%04d-%02d-%02d', $anio, $numeroMes, $dia));
    $diaSemanaIso = (int) $fecha->format('N'); // 1=lunes ... 4=jueves ... 7=domingo
    if ($diaSemanaIso === 4 || $diaSemanaIso === 7) {
        $insertar->execute([$fecha->format('Y-m-d')]);
    }
}

header('Location: ../cronograma.php?mes=' . urlencode($mes) . '&ok=cronograma_generado');
exit;
