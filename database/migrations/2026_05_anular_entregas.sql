-- ============================================================================
-- MIGRACIÓN: soft-delete (anulación) de entregas
-- ============================================================================
-- Las entregas son documentos contables y emiten un albarán PDF que el socio
-- recibe por email. Por eso NUNCA borramos físicamente la fila — la marcamos
-- como ANULADA con motivo, fecha y admin responsable, así queda trazabilidad
-- y auditoría completa. Las entregas anuladas dejan de contar en KPIs y
-- liquidaciones, pero siguen visibles en el histórico tachadas.
-- ============================================================================

ALTER TABLE entregas
    ADD COLUMN anulada           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
        COMMENT '1 = entrega anulada (soft-delete). 0 = válida.'
        AFTER observaciones,
    ADD COLUMN motivo_anulacion  VARCHAR(255) DEFAULT NULL
        COMMENT 'Motivo libre que introduce el admin al anular.'
        AFTER anulada,
    ADD COLUMN fecha_anulacion   TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Cuándo se anuló — NULL si nunca se anuló.'
        AFTER motivo_anulacion,
    ADD COLUMN id_admin_anula    INT(10) UNSIGNED DEFAULT NULL
        COMMENT 'Admin que ejecutó la anulación. NULL si nunca anulada.'
        AFTER fecha_anulacion,
    ADD CONSTRAINT chk_entregas_anulada CHECK (anulada IN (0, 1)),
    ADD KEY idx_entregas_anulada (anulada),
    ADD CONSTRAINT fk_entregas_admin_anula
        FOREIGN KEY (id_admin_anula) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
