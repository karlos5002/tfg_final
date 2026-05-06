-- ============================================================================
-- COOPERATIVA SAN JUAN BAUTISTA — Script de creación de Base de Datos
-- ============================================================================
-- 
-- Ejecutar este script en phpMyAdmin o desde la línea de comandos MySQL:
--   mysql -u root < database.sql
--
-- Estructura:
--   1. Base de datos: cooperativa_sjb
--   2. Tabla: usuarios (socios y administradores)
--   3. Tabla: entregas (entregas de aceituna con cálculo automático de litros)
--   4. Datos de prueba: 1 admin + 3 socios + entregas de ejemplo
--
-- Decisiones de diseño:
--   - DECIMAL para kilos/rendimiento/litros (precisión financiera, no FLOAT)
--   - ENUM para roles (solo 'socio' o 'admin')
--   - ON DELETE CASCADE en entregas (si se borra un socio, sus entregas también)
--   - GENERATED COLUMN para litros_aceite (se calcula automáticamente)
--   - Índices en campos de búsqueda frecuente (dni, email, id_socio)
-- ============================================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS cooperativa_sjb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cooperativa_sjb;


-- ─────────────────────────────────────────────────────────────────────────────
-- TABLA: usuarios
-- ─────────────────────────────────────────────────────────────────────────────
-- Almacena tanto socios como administradores (discriminados por el campo 'rol').
-- Las contraseñas se almacenan con password_hash() de PHP (bcrypt).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100)    NOT NULL,
    apellidos   VARCHAR(150)    NOT NULL,
    dni         VARCHAR(15)     NOT NULL UNIQUE,
    email       VARCHAR(200)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt generado con password_hash()',
    telefono    VARCHAR(20)     DEFAULT NULL,
    rol         ENUM('socio', 'admin') NOT NULL DEFAULT 'socio',
    activo      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dni (dni),
    INDEX idx_email (email),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
-- TABLA: entregas
-- ─────────────────────────────────────────────────────────────────────────────
-- Registra cada entrega de aceituna que un socio trae a la almazara.
--
-- Columna calculada (GENERATED):
--   litros_aceite = (kilos_aceituna * rendimiento / 100) / 0.916
--   
--   Donde:
--     - rendimiento es el % de rendimiento graso (ej: 21.00 = 21%)
--     - 0.916 es la densidad media del aceite de oliva en kg/L
--   
--   Esto significa que la columna se actualiza automáticamente
--   cuando se insertan o modifican kilos/rendimiento. No hace falta
--   calcularla en PHP.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS entregas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_socio        INT UNSIGNED    NOT NULL,
    fecha_entrega   DATE            NOT NULL DEFAULT (CURDATE()),
    kilos_aceituna  DECIMAL(10,2)   NOT NULL,
    rendimiento     DECIMAL(5,2)    NOT NULL
        COMMENT 'Porcentaje de rendimiento graso (ej: 21.00)',
    litros_aceite   DECIMAL(10,2)   AS (
        ROUND((kilos_aceituna * rendimiento / 100) / 0.916, 2)
    ) STORED COMMENT 'Calculado: (kg * rend% / 100) / 0.916',
    observaciones   TEXT            DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    -- Clave foránea: cada entrega pertenece a un socio
    CONSTRAINT fk_entrega_socio
        FOREIGN KEY (id_socio) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    -- Restricciones de validación
    CONSTRAINT chk_kilos CHECK (kilos_aceituna > 0),
    CONSTRAINT chk_rendimiento CHECK (rendimiento > 0 AND rendimiento <= 100),

    INDEX idx_socio (id_socio),
    INDEX idx_fecha (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
-- DATOS DE PRUEBA
-- ─────────────────────────────────────────────────────────────────────────────
-- Contraseñas: todas son "1234" hasheadas con password_hash('1234', PASSWORD_DEFAULT)
-- En producción NUNCA se usarían contraseñas tan simples.
--
-- Hash generado con PHP 8.x:
--   password_hash('1234', PASSWORD_DEFAULT)
-- ─────────────────────────────────────────────────────────────────────────────

-- Admin principal
INSERT INTO usuarios (nombre, apellidos, dni, email, password, telefono, rol) VALUES
('Administrador', 'Cooperativa', '00000000A', 'admin@coopsanjuanbautista.es',
 '$2y$10$sJyKQbCGUuk2ysl.jUApA.gkilcd730g4Sin9pgrPSQ6aXdv4bVjK',
 '924000001', 'admin');

-- Socios de prueba
INSERT INTO usuarios (nombre, apellidos, dni, email, password, telefono, rol) VALUES
('Juan', 'García López', '12345678A', 'juan.garcia@email.com',
 '$2y$10$sJyKQbCGUuk2ysl.jUApA.gkilcd730g4Sin9pgrPSQ6aXdv4bVjK',
 '924111111', 'socio'),
('María', 'Fernández Ruiz', '23456789B', 'maria.fernandez@email.com',
 '$2y$10$sJyKQbCGUuk2ysl.jUApA.gkilcd730g4Sin9pgrPSQ6aXdv4bVjK',
 '924222222', 'socio'),
('Pedro', 'Martínez Sánchez', '34567890C', 'pedro.martinez@email.com',
 '$2y$10$sJyKQbCGUuk2ysl.jUApA.gkilcd730g4Sin9pgrPSQ6aXdv4bVjK',
 '924333333', 'socio');

-- Entregas de prueba
INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones) VALUES
(2, '2025-11-15', 1500.00, 21.50, 'Primera entrega de la campaña 2025/26'),
(3, '2025-11-18', 2200.00, 20.80, 'Aceituna variedad Picual'),
(4, '2025-11-20', 800.50,  22.10, 'Parcela Los Cerros'),
(2, '2025-12-01', 1800.00, 21.00, 'Segunda entrega'),
(3, '2025-12-05', 3100.00, 19.50, 'Recogida mecanizada');
