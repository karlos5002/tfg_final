-- =============================================================================
-- Cooperativa San Juan Bautista — Reservas de visita guiada (oleoturismo)
-- =============================================================================
-- Las visitas a la almazara se ofrecen en 4 turnos diarios (mañana y tarde),
-- excepto lunes (descanso). Cada turno tiene un cupo de 10 personas para
-- garantizar la calidad de la experiencia y respetar la zona de elaboración.
--
-- La capacidad por turno se verifica en la API DENTRO DE UNA TRANSACCIÓN para
-- evitar race conditions: dos reservas simultáneas que sumen más del cupo.
-- =============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS visitas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Datos del visitante (puede no estar registrado en el sistema)
    nombre          VARCHAR(100) NOT NULL,
    email           VARCHAR(180) NOT NULL,
    telefono        VARCHAR(20),

    -- Datos de la reserva
    fecha_visita    DATE         NOT NULL,
    hora_visita     TIME         NOT NULL,
    num_personas    TINYINT UNSIGNED NOT NULL,
    tipo_visita     ENUM('cata','almazara','completa') NOT NULL DEFAULT 'completa'
                    COMMENT 'cata=solo cata · almazara=solo recorrido · completa=ambas',
    comentarios     TEXT,

    estado          ENUM('pendiente','confirmada','cancelada','realizada')
                    NOT NULL DEFAULT 'pendiente'
                    COMMENT 'Ciclo de vida: el admin confirma o cancela; "realizada" tras la visita',

    -- Si el visitante está logueado, vincular con usuarios (opcional)
    id_usuario      INT UNSIGNED NULL,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Restricciones de integridad ──
    CONSTRAINT chk_visitas_personas CHECK (num_personas BETWEEN 1 AND 10),

    CONSTRAINT fk_visita_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id) ON DELETE SET NULL,

    -- ── Índices de rendimiento ──
    INDEX idx_visitas_fecha   (fecha_visita, hora_visita),
    INDEX idx_visitas_estado  (estado),
    INDEX idx_visitas_email   (email)
) ENGINE=InnoDB
  COMMENT='Reservas de visita guiada a la almazara (oleoturismo)';


-- ─── Datos de ejemplo: 3 reservas con distintos estados ─────────────────
INSERT INTO visitas (nombre, email, telefono, fecha_visita, hora_visita,
                     num_personas, tipo_visita, comentarios, estado) VALUES
    ('Carmen Rodríguez',
     'carmen.rodriguez@example.com',
     '612 345 678',
     DATE_ADD(CURDATE(), INTERVAL 3 DAY),
     '12:00:00',
     4,
     'completa',
     'Venimos en familia con dos niños de 8 y 11 años. ¿Hay alguna parte de la visita inadecuada para ellos?',
     'confirmada'),

    ('Javier Mendoza',
     'jmendoza@example.com',
     '655 112 233',
     DATE_ADD(CURDATE(), INTERVAL 7 DAY),
     '17:00:00',
     2,
     'cata',
     NULL,
     'pendiente'),

    ('Bodega "El Roble" S.L.',
     'eventos@bodegaelroble.es',
     '924 555 100',
     DATE_SUB(CURDATE(), INTERVAL 14 DAY),
     '10:00:00',
     8,
     'completa',
     'Visita corporativa para nuestro equipo de catadores.',
     'realizada');

COMMIT;
