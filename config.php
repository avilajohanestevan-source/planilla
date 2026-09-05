<?php
/**
 * Configuración general del aplicativo.
 * Ajusta estos datos si tu instalación de XAMPP usa otro usuario/clave de MySQL.
 */

// --- Conexión a la base de datos (XAMPP por defecto: usuario "root", sin clave) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'planilla_celestial_dance');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Datos del ministerio ---
define('APP_NOMBRE', 'Ministerio De Danza C.D');
define('APP_SUBTITULO', 'Celestial Dance');

// Zona horaria para que las fechas se calculen correctamente
date_default_timezone_set('America/Bogota');
