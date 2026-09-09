-- =====================================================================
-- Migración: módulo de Cronograma (danzoras + uniforme por jueves/domingo)
-- Ministerio de Danza C.D. - Celestial Dance
--
-- Este archivo se ejecuta UNA SOLA VEZ sobre la base de datos que ya
-- tienes instalada (no reemplaza database.sql, lo complementa).
-- Impórtalo igual que hiciste con database.sql: en phpMyAdmin, pestaña
-- "Importar", elige este archivo, o desde la consola:
--     mysql -u root -p planilla_celestial_dance < migracion_cronograma.sql
-- =====================================================================

SET NAMES utf8mb4;
USE planilla_celestial_dance;

-- ---------------------------------------------------------------------
-- Marca en la ficha de cada niña si puede pasar al altar (por defecto
-- todas quedan en "sí" para no perder a nadie de la lista al migrar).
-- ---------------------------------------------------------------------
ALTER TABLE ninas
    ADD COLUMN IF NOT EXISTS puede_altar TINYINT(1) NOT NULL DEFAULT 1 AFTER activa;

-- ---------------------------------------------------------------------
-- Catálogo de uniformes: nombre libre (puede ser una descripción, ej.
-- "Camiseta blanca y jean azul oscuro") + un color para identificarlo
-- de un vistazo en la tabla del cronograma.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS uniformes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    color VARCHAR(7) NOT NULL DEFAULT '#8f4157',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fechas del cronograma (los jueves y domingos se generan solos desde
-- la página, pero la tabla acepta cualquier fecha por si hay un evento
-- especial). Cada fecha tiene a lo sumo un uniforme asignado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cronograma_fechas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    uniforme_id INT NULL,
    notas VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uniforme_id) REFERENCES uniformes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Qué niñas danzan en cada fecha del cronograma.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cronograma_asignaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cronograma_fecha_id INT NOT NULL,
    nina_id INT NOT NULL,
    UNIQUE KEY unico_asignacion (cronograma_fecha_id, nina_id),
    FOREIGN KEY (cronograma_fecha_id) REFERENCES cronograma_fechas(id) ON DELETE CASCADE,
    FOREIGN KEY (nina_id) REFERENCES ninas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
