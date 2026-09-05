<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config.php';

$paginaActual = 'inicio';
$tituloPagina = 'Inicio';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <img src="assets/img/logo.png" alt="<?= h(APP_NOMBRE) ?>" class="hero-logo">
    <p class="lead">
        Bienvenido al aplicativo del ministerio. Aquí puedes llenar la planilla de cada
        ensayo de los sábados y llevar el registro de las niñas y sus cargos.
    </p>
</section>

<div class="tarjetas-inicio">
    <a href="planilla.php" class="tarjeta-accion acento-teal">
        <h2>Llenar Planilla</h2>
        <p>Registra versículo, devocional, puntualidad, apuntes, calendario, comportamiento y fallas del ensayo de este sábado.</p>
    </a>

    <a href="ninas.php" class="tarjeta-accion acento-vino">
        <h2>Niñas y Cargos</h2>
        <p>Agrega o edita la información de las niñas y administra los cargos que se les delegan.</p>
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
