<?php
/**
 * Conexión PDO a la base de datos.
 * Este archivo se incluye en todas las páginas que necesitan hablar con MySQL.
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die(
        '<div style="font-family:sans-serif;max-width:600px;margin:60px auto;padding:24px;' .
        'border:1px solid #e2b6c0;background:#fdf2f5;border-radius:10px;color:#6e3244;">' .
        '<h2 style="margin-top:0;">No se pudo conectar a la base de datos</h2>' .
        '<p>Verifica que:</p>' .
        '<ul>' .
        '<li>Apache y MySQL estén iniciados en el panel de XAMPP.</li>' .
        '<li>Hayas importado el archivo <code>database.sql</code> en phpMyAdmin.</li>' .
        '<li>Los datos de conexión en <code>config.php</code> sean correctos.</li>' .
        '</ul>' .
        '<p style="color:#a35a6c;font-size:13px;">Detalle técnico: ' . htmlspecialchars($e->getMessage()) . '</p>' .
        '</div>'
    );
}
