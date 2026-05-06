-- =============================================================================
-- Cooperativa San Juan Bautista — Sistema de Votaciones
-- =============================================================================
-- Modelo de datos para asambleas/votaciones cooperativas. La integridad de
-- "un socio = un voto" se garantiza a NIVEL DE BASE DE DATOS con una clave
-- primaria compuesta en `votos`, no sólo a nivel de aplicación.
--
-- Ejecutar con --default-character-set=utf8mb4
-- =============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ─── Tabla principal ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS votaciones (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(150) NOT NULL,
    descripcion       TEXT,
    fecha_inicio      DATETIME NOT NULL,
    fecha_fin         DATETIME NOT NULL,
    estado            ENUM('borrador','abierta','cerrada') NOT NULL DEFAULT 'borrador'
                      COMMENT 'borrador=admin la prepara · abierta=socios pueden votar · cerrada=resultados visibles',
    quorum_minimo     TINYINT UNSIGNED NOT NULL DEFAULT 30
                      COMMENT 'Porcentaje de socios necesario para que la decisión sea válida',
    id_admin_creador  INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_votacion_fechas CHECK (fecha_fin > fecha_inicio),
    CONSTRAINT fk_votacion_admin   FOREIGN KEY (id_admin_creador) REFERENCES usuarios(id),
    INDEX idx_votacion_estado (estado),
    INDEX idx_votacion_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB
  COMMENT='Votaciones cooperativas (asambleas, decisiones colectivas)';


-- ─── Opciones de cada votación ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS votacion_opciones (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_votacion  INT UNSIGNED NOT NULL,
    texto        VARCHAR(120) NOT NULL,
    orden        TINYINT UNSIGNED NOT NULL DEFAULT 0,

    CONSTRAINT fk_opcion_votacion FOREIGN KEY (id_votacion) REFERENCES votaciones(id) ON DELETE CASCADE,
    INDEX idx_opciones_votacion (id_votacion, orden)
) ENGINE=InnoDB
  COMMENT='Opciones que el socio puede elegir en cada votación';


-- ─── Registro de votos ──────────────────────────────────────────────────
-- La PK compuesta (id_votacion, id_socio) hace IMPOSIBLE que un mismo socio
-- vote dos veces a la misma votación: MySQL rechaza el segundo INSERT con
-- error de duplicado (SQLSTATE 23000). Es la garantía más fuerte posible.
CREATE TABLE IF NOT EXISTS votos (
    id_votacion  INT UNSIGNED NOT NULL,
    id_socio     INT UNSIGNED NOT NULL,
    id_opcion    INT UNSIGNED NOT NULL,
    fecha_voto   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_votacion, id_socio),
    CONSTRAINT fk_voto_votacion FOREIGN KEY (id_votacion) REFERENCES votaciones(id)         ON DELETE CASCADE,
    CONSTRAINT fk_voto_socio    FOREIGN KEY (id_socio)    REFERENCES usuarios(id),
    CONSTRAINT fk_voto_opcion   FOREIGN KEY (id_opcion)   REFERENCES votacion_opciones(id),
    INDEX idx_voto_opcion (id_opcion)
) ENGINE=InnoDB
  COMMENT='Voto emitido. PK compuesta = un socio sólo puede votar una vez';


-- ═════════════════════════════════════════════════════════════════════════
-- DATOS DE EJEMPLO (1 votación abierta + 1 cerrada con votos para
-- demostrar el cálculo de quórum del 30 %)
-- ═════════════════════════════════════════════════════════════════════════

-- Votación 1: abierta — los socios pueden votar
INSERT INTO votaciones (titulo, descripcion, fecha_inicio, fecha_fin, estado, id_admin_creador) VALUES
    ('Inversión en nueva almazara',
     'Propuesta para adquirir una almazara de última generación con capacidad de 1500 kg/h y separadoras inox. Inversión estimada: 250.000 €, financiada al 60 % con cooperativa y 40 % con préstamo ICO. ¿Aprobamos la inversión?',
     DATE_SUB(NOW(), INTERVAL 1 DAY),
     DATE_ADD(NOW(), INTERVAL 14 DAY),
     'abierta',
     4);
SET @v1 := LAST_INSERT_ID();

INSERT INTO votacion_opciones (id_votacion, texto, orden) VALUES
    (@v1, 'Sí, aprobar la inversión',           1),
    (@v1, 'No, mantener la almazara actual',    2),
    (@v1, 'Abstención',                         3);


-- Votación 2: cerrada — para demostrar resultados con quórum válido
INSERT INTO votaciones (titulo, descripcion, fecha_inicio, fecha_fin, estado, id_admin_creador) VALUES
    ('Cambio de proveedor de envases',
     'Migrar del proveedor actual a uno con certificación ecológica europea. El coste sube un 8 % pero mejora la imagen de marca y nos abre acceso a la línea bio.',
     DATE_SUB(NOW(), INTERVAL 30 DAY),
     DATE_SUB(NOW(), INTERVAL 1 DAY),
     'cerrada',
     4);
SET @v2 := LAST_INSERT_ID();

INSERT INTO votacion_opciones (id_votacion, texto, orden) VALUES
    (@v2, 'Cambiar al proveedor ecológico',  1),
    (@v2, 'Mantener proveedor actual',       2);

-- Capturamos los IDs reales de las opciones para insertar votos sin asumir AUTO_INCREMENT
SET @op_eco        := (SELECT id FROM votacion_opciones WHERE id_votacion = @v2 AND orden = 1);
SET @op_actual     := (SELECT id FROM votacion_opciones WHERE id_votacion = @v2 AND orden = 2);

-- Socios disponibles: id 1 (Paco), 2 (María), 3 (Antonio), 5 (Laura), 6 (Juan) → 5 socios
-- Insertamos 4 votos = 80 % de participación → muy por encima del 30 % de quórum
INSERT INTO votos (id_votacion, id_socio, id_opcion) VALUES
    (@v2, 1, @op_eco),       -- Paco
    (@v2, 2, @op_eco),       -- María
    (@v2, 3, @op_actual),    -- Antonio
    (@v2, 5, @op_eco);       -- Laura

COMMIT;
