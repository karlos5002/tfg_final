-- Gestión explícita de campañas oleícolas: precio liquidación, estado y rango.
-- Convive con la columna generada `entregas.campana` (texto "2025/2026") como
-- referencia legacy; la nueva FK `entregas.id_campana` apunta a la fila real.
-- Idempotente.

USE cooperativa_sjb;

-- 1) Tabla maestra de campañas
CREATE TABLE IF NOT EXISTS campanas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(9) NOT NULL UNIQUE
                                COMMENT 'Código legible: "2025/2026"',
    fecha_inicio    DATE NOT NULL
                                COMMENT 'Inicio del periodo de recepción de aceituna',
    fecha_fin       DATE NOT NULL
                                COMMENT 'Fin del periodo (inclusive)',
    precio_por_kilo DECIMAL(6,4) NOT NULL
                                COMMENT 'Precio de liquidación al socio (€/kg de aceituna)',
    estado          ENUM('activa','cerrada') NOT NULL DEFAULT 'activa'
                                COMMENT 'activa=admite entregas; cerrada=histórico',
    notas           TEXT        COMMENT 'Observaciones internas: rendimiento medio esperado, eventos…',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_camp_fechas CHECK (fecha_fin > fecha_inicio),
    CONSTRAINT chk_camp_precio CHECK (precio_por_kilo > 0)
) ENGINE=InnoDB
  COMMENT='Campañas oleícolas — precio de liquidación y estado';

CREATE INDEX IF NOT EXISTS idx_campanas_estado ON campanas (estado);
CREATE INDEX IF NOT EXISTS idx_campanas_codigo ON campanas (codigo);


-- 2) Sembrar campañas a partir de los textos `campana` ya existentes en entregas.
--    El precio inicial es 0.4500 €/kg (precio orientativo medio de campañas
--    recientes en Extremadura) — el admin puede ajustarlo después.
--    Las fechas se calculan: campaña "AAAA/BBBB" → 1-jul-AAAA a 30-jun-BBBB.
INSERT INTO campanas (codigo, fecha_inicio, fecha_fin, precio_por_kilo, estado, notas)
SELECT
    e.campana                                                        AS codigo,
    STR_TO_DATE(CONCAT(SUBSTRING(e.campana, 1, 4), '-07-01'), '%Y-%m-%d') AS fecha_inicio,
    STR_TO_DATE(CONCAT(SUBSTRING(e.campana, 6, 4), '-06-30'), '%Y-%m-%d') AS fecha_fin,
    0.4500                                                           AS precio_por_kilo,
    'activa'                                                         AS estado,
    'Generada automáticamente por la migración 05_campanas.sql'      AS notas
FROM (SELECT DISTINCT campana FROM entregas WHERE campana IS NOT NULL) e
WHERE NOT EXISTS (SELECT 1 FROM campanas c WHERE c.codigo = e.campana);


-- 3) Añadir FK id_campana a entregas (nullable: hueco para futuras correcciones)
ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS id_campana INT UNSIGNED NULL
        COMMENT 'FK -> campanas.id (autoresuelto por fecha de entrega)' AFTER campana;

-- FK: ON DELETE SET NULL para que cerrar una campaña por error no borre entregas
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entregas'
      AND CONSTRAINT_NAME = 'fk_entregas_campana'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE entregas
        ADD CONSTRAINT fk_entregas_campana
        FOREIGN KEY (id_campana) REFERENCES campanas(id)
        ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "FK fk_entregas_campana ya existe — saltado" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE INDEX IF NOT EXISTS idx_entregas_id_campana ON entregas (id_campana);


-- 4) Backfill: enlazar entregas existentes con su campaña
UPDATE entregas e
JOIN   campanas c ON c.codigo = e.campana
SET    e.id_campana = c.id
WHERE  e.id_campana IS NULL;


-- 5) Vista de resumen por campaña con liquidación calculada
CREATE OR REPLACE VIEW v_campanas_resumen AS
SELECT
    c.id,
    c.codigo,
    c.fecha_inicio,
    c.fecha_fin,
    c.precio_por_kilo,
    c.estado,
    c.notas,
    COUNT(DISTINCT e.id_socio)                              AS socios_participantes,
    COUNT(e.id)                                             AS total_entregas,
    COALESCE(SUM(e.kilos_aceituna), 0)                      AS total_kilos,
    COALESCE(SUM(e.litros_aceite), 0)                       AS total_litros,
    ROUND(COALESCE(AVG(e.rendimiento), 0), 2)               AS rendimiento_medio,
    ROUND(COALESCE(SUM(e.kilos_aceituna), 0)
          * c.precio_por_kilo, 2)                           AS liquidacion_total
FROM campanas c
LEFT JOIN entregas e ON e.id_campana = c.id
GROUP BY c.id;
