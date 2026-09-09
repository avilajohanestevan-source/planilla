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

// El Cronograma se maneja por semana (domingo a sábado): cada semana se
// identifica por su domingo, y comparte danzoras y uniforme con el jueves de
// esa misma semana (domingo + 4 días).
//
// Para no dejar semanas "cortadas" al borde del mes, recorremos desde 6 días
// antes del primer día del mes (por si esa semana anterior "se asoma" al mes
// por su jueves) hasta el último día del mes, y creamos la semana si su
// domingo cae en el mes elegido, o si el jueves de esa semana cae en el mes
// elegido (aunque el domingo sea del mes anterior).
$primerDia = new DateTime(sprintf('%04d-%02d-01', $anio, $numeroMes));
$ultimoDia = (clone $primerDia)->modify('last day of this month');

$cursor = (clone $primerDia)->modify('-6 days');
$limite = clone $ultimoDia;

$insertar = $pdo->prepare('INSERT IGNORE INTO cronograma_semanas (fecha_domingo) VALUES (?)');

while ($cursor <= $limite) {
    if ((int) $cursor->format('N') === 7) { // 7 = domingo
        $domingo = clone $cursor;
        $jueves = (clone $domingo)->modify('+4 days');

        $domingoEnMes = $domingo >= $primerDia && $domingo <= $ultimoDia;
        $juevesEnMes  = $jueves >= $primerDia && $jueves <= $ultimoDia;

        if ($domingoEnMes || $juevesEnMes) {
            $insertar->execute([$domingo->format('Y-m-d')]);
        }
    }
    $cursor->modify('+1 day');
}

header('Location: ../cronograma.php?mes=' . urlencode($mes) . '&ok=cronograma_generado');
exit;
