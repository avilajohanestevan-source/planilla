<?php
/**
 * Encabezado común. Cada página debe definir $paginaActual antes de incluir
 * este archivo ('inicio' | 'planilla' | 'puntaje' | 'ninas') y opcionalmente $tituloPagina.
 */
if (!isset($paginaActual)) {
    $paginaActual = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tituloPagina) ? h($tituloPagina) . ' · ' : '' ?><?= h(APP_NOMBRE) ?></title>
<link rel="icon" type="image/png" href="assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body>

<main class="contenedor">
    <nav class="nav-simple">
        <a href="index.php" class="<?= $paginaActual === 'inicio' ? 'activo' : '' ?>">Inicio</a>
        <a href="planilla.php" class="<?= $paginaActual === 'planilla' ? 'activo' : '' ?>">Planilla</a>
        <a href="puntaje.php" class="<?= $paginaActual === 'puntaje' ? 'activo' : '' ?>">Puntaje</a>
        <a href="ninas.php" class="<?= $paginaActual === 'ninas' ? 'activo' : '' ?>">Niñas y Cargos</a>
    </nav>
