-- =====================================================================
-- Base de datos: planilla_celestial_dance
-- Ministerio de Danza C.D. - Celestial Dance
-- Importa este archivo completo desde phpMyAdmin (pestaña "Importar")
-- o desde la consola de MySQL:  mysql -u root -p < database.sql
-- =====================================================================

-- Asegura que los acentos (á, é, í, ó, ú, ñ) se guarden correctamente
-- sin importar el charset por defecto del cliente que hace la importación.
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS planilla_celestial_dance
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE planilla_celestial_dance;

-- ---------------------------------------------------------------------
-- Niñas del ministerio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ninas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    fecha_cumpleanos DATE NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Cargos que se pueden delegar a las niñas (lista editable)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Relación niña <-> cargo (una niña puede tener varios cargos)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nina_cargo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nina_id INT NOT NULL,
    cargo_id INT NOT NULL,
    fecha_asignacion DATE DEFAULT (CURRENT_DATE),
    UNIQUE KEY unico_nina_cargo (nina_id, cargo_id),
    FOREIGN KEY (nina_id) REFERENCES ninas(id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Categorías de la planilla (las columnas de la hoja de papel)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Ensayos (cada sábado de práctica)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ensayos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    observaciones VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Registros de la planilla: una fila por niña + ensayo + categoría
-- estado: feliz (+1), neutral (0), triste (-1)
-- puntos_extra: valor libre que el encargado escribe manualmente
--               (ej. +20 por completar el devocional los 5 días)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ensayo_id INT NOT NULL,
    nina_id INT NOT NULL,
    categoria_id INT NOT NULL,
    estado ENUM('feliz','neutral','triste') NOT NULL DEFAULT 'neutral',
    puntos_extra INT NOT NULL DEFAULT 0,
    UNIQUE KEY unico_registro (ensayo_id, nina_id, categoria_id),
    FOREIGN KEY (ensayo_id) REFERENCES ensayos(id) ON DELETE CASCADE,
    FOREIGN KEY (nina_id) REFERENCES ninas(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos iniciales: las 8 categorías de la planilla original
-- ---------------------------------------------------------------------
INSERT INTO categorias (nombre, orden) VALUES
    ('Versículo',        1),
    ('Devocional',       2),
    ('Audio Devocional', 3),
    ('Puntualidad',      4),
    ('Apuntes',          5),
    ('Calendario',       6),
    ('Comportamiento',   7),
    ('Fallas',           8);
