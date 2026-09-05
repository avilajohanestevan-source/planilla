</main>

<footer class="piefooter">
    <p><?= h(APP_NOMBRE) ?> · <?= h(APP_SUBTITULO) ?> — Casa de Dios</p>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: time() ?>"></script>
</body>
</html>
