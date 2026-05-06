-- Sistema de stock con trazabilidad: umbral por producto + log de movimientos.
-- Idempotente: se puede re-ejecutar sin romper la BD.

USE cooperativa_sjb;

-- 1) Umbral de aviso configurable por producto (antes era hardcoded < 20)
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS stock_minimo INT UNSIGNED NOT NULL DEFAULT 10
        COMMENT 'Umbral por debajo del cual se muestra "pocas unidades"' AFTER stock;

-- Ajuste de umbrales razonables según el tipo de presentación
UPDATE productos SET stock_minimo = 30 WHERE slug = 'garrafa-aove-5l';
UPDATE productos SET stock_minimo = 20 WHERE slug = 'botella-premium-500ml';
UPDATE productos SET stock_minimo = 15 WHERE slug = 'pack-degustacion-3x250ml';
UPDATE productos SET stock_minimo = 25 WHERE slug = 'garrafa-hojiblanca-2l';
UPDATE productos SET stock_minimo = 20 WHERE slug = 'manzanilla-cacerena-500ml';
UPDATE productos SET stock_minimo = 15 WHERE slug = 'verdial-badajoz-2l';
UPDATE productos SET stock_minimo = 10 WHERE slug = 'cornezuelo-tierra-de-barros-500ml';
UPDATE productos SET stock_minimo =  8 WHERE slug = 'morisca-dop-monterrubio-750ml';
UPDATE productos SET stock_minimo =  6 WHERE slug = 'carrasquena-centenaria-250ml';


-- 2) Tabla de movimientos de stock (auditoría)
CREATE TABLE IF NOT EXISTS movimientos_stock (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto  INT UNSIGNED NOT NULL              COMMENT 'FK -> productos.id',
    tipo         ENUM('entrada','salida','ajuste') NOT NULL
                                                    COMMENT 'entrada=reposición, salida=venta, ajuste=corrección manual',
    cantidad     INT NOT NULL                       COMMENT 'Unidades (positivo entrada/salida; signo libre en ajuste)',
    stock_antes  INT UNSIGNED NOT NULL              COMMENT 'Stock del producto ANTES de aplicar el movimiento',
    stock_despues INT UNSIGNED NOT NULL             COMMENT 'Stock del producto DESPUÉS de aplicar el movimiento',
    motivo       VARCHAR(180)                       COMMENT 'Texto libre: nº albarán proveedor, motivo del ajuste, etc.',
    id_pedido    INT UNSIGNED                       COMMENT 'Pedido asociado si tipo=salida (NULL para entradas/ajustes)',
    id_usuario   INT UNSIGNED                       COMMENT 'Quién registró el movimiento (NULL si es venta automática)',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_movs_cantidad_no_cero CHECK (cantidad <> 0),

    CONSTRAINT fk_movs_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_movs_pedido
        FOREIGN KEY (id_pedido) REFERENCES pedidos(id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT fk_movs_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Historial de movimientos de stock — trazabilidad completa';

CREATE INDEX IF NOT EXISTS idx_movs_producto      ON movimientos_stock (id_producto);
CREATE INDEX IF NOT EXISTS idx_movs_tipo_fecha    ON movimientos_stock (tipo, created_at);
CREATE INDEX IF NOT EXISTS idx_movs_pedido        ON movimientos_stock (id_pedido);


-- 3) Sembrar movimientos iniciales para el stock que ya existe en BD
--    (un único 'entrada' inicial por producto, sólo si no hay movimientos previos)
INSERT INTO movimientos_stock (id_producto, tipo, cantidad, stock_antes, stock_despues, motivo, id_usuario)
SELECT p.id, 'entrada', p.stock, 0, p.stock,
       'Stock inicial — carga del catálogo',
       (SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1)
FROM productos p
WHERE NOT EXISTS (
    SELECT 1 FROM movimientos_stock m WHERE m.id_producto = p.id
);


-- 4) Vista útil para el panel de admin: estado de stock con etiqueta calculada
CREATE OR REPLACE VIEW v_stock_estado AS
SELECT
    p.id,
    p.nombre,
    p.slug,
    p.variedad,
    p.precio,
    p.stock,
    p.stock_minimo,
    p.activo,
    CASE
        WHEN p.stock = 0                       THEN 'agotado'
        WHEN p.stock <= p.stock_minimo         THEN 'bajo'
        WHEN p.stock <= p.stock_minimo * 2     THEN 'medio'
        ELSE 'ok'
    END AS estado_stock,
    (SELECT MAX(created_at) FROM movimientos_stock m
     WHERE m.id_producto = p.id AND m.tipo = 'entrada') AS ultima_reposicion
FROM productos p;
