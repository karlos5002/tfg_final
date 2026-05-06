-- ============================================================================
-- COOPERATIVA SAN JUAN BAUTISTA — Script de Base de Datos (Producción)
-- ============================================================================
-- TFG — Desarrollo de Aplicaciones Web
-- Versión: 3.0 (Unificada — catálogo completo + charset limpio)
-- Motor: InnoDB (soporte transaccional + integridad referencial)
-- Charset: utf8mb4 (Unicode completo, emojis incluidos)
-- Collation: utf8mb4_unicode_ci (ordenación lingüística correcta en español)
--
-- Decisiones técnicas implementadas:
--   • Auditoría temporal: created_at + updated_at en tablas mutables
--   • Columnas generadas (STORED): litros_aceite calculados por el motor SQL
--   • Índices estratégicos: aceleran JOINs, búsquedas y filtros frecuentes
--   • CHECK constraints: validaciones a nivel de motor (defensa en profundidad)
--   • Vistas materializables: simplifican consultas complejas desde PHP
--   • Tipos de datos ajustados: tamaños mínimos necesarios (eficiencia I/O)
--   • Soft-delete pattern: campo 'activo' evita borrados destructivos
-- ============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. CONFIGURACIÓN DE CHARSET
-- ─────────────────────────────────────────────────────────────────────────────
-- NOTA: En hosting compartido (Hostinger) la base de datos ya está creada
-- y seleccionada. Las sentencias DROP/CREATE DATABASE no son necesarias.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = utf8mb4_unicode_ci;


-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. TABLA: usuarios
-- ═══════════════════════════════════════════════════════════════════════════════
-- Almacena socios, clientes de tienda online y administradores.
-- El campo 'rol' determina los permisos de acceso en la aplicación PHP.
--
-- Seguridad:
--   • password: VARCHAR(255) para bcrypt ($2y$) — nunca texto plano
--   • email + dni: UNIQUE — previene duplicados a nivel de motor
--   • activo: soft-delete pattern (desactivar sin borrar datos)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dni         VARCHAR(15) UNIQUE              COMMENT 'NIF, NIE o CIF del usuario',
    nombre      VARCHAR(60) NOT NULL            COMMENT 'Nombre de pila',
    apellidos   VARCHAR(100)                    COMMENT 'Apellidos (puede ser NULL para empresas)',
    email       VARCHAR(180) UNIQUE NOT NULL    COMMENT 'Email de acceso — usado como login',
    password    VARCHAR(255) NOT NULL           COMMENT 'Hash bcrypt generado con password_hash()',
    telefono    VARCHAR(15)                     COMMENT 'Teléfono de contacto (formato libre)',
    rol         ENUM('admin','socio','cliente') NOT NULL DEFAULT 'cliente'
                                                COMMENT 'Rol de acceso: admin|socio|cliente',
    activo      TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
                                                COMMENT '1=activo, 0=desactivado (soft-delete)',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                COMMENT 'Fecha de alta automática',
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                                COMMENT 'Última modificación (auto-actualizado)',

    -- ── Restricciones de integridad ──
    CONSTRAINT chk_usuarios_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB
  COMMENT='Usuarios del sistema: socios, clientes y administradores';

-- Índices de rendimiento para usuarios
CREATE INDEX idx_usuarios_rol        ON usuarios (rol);
CREATE INDEX idx_usuarios_email      ON usuarios (email);
CREATE INDEX idx_usuarios_dni        ON usuarios (dni);
CREATE INDEX idx_usuarios_activo_rol ON usuarios (activo, rol);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. TABLA: fincas
-- ═══════════════════════════════════════════════════════════════════════════════
-- Trazabilidad catastral: cada finca pertenece a un socio.
-- Los campos polígono/parcela permiten la geolocalización catastral exacta.
--
-- Decisión: hectareas como DECIMAL(6,2) permite hasta 9999.99 ha (suficiente).
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE fincas (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_socio     INT UNSIGNED NOT NULL        COMMENT 'FK → usuarios.id (debe ser socio)',
    nombre_paraje VARCHAR(100) NOT NULL       COMMENT 'Nombre del paraje o finca',
    poligono     SMALLINT UNSIGNED            COMMENT 'Polígono catastral',
    parcela      SMALLINT UNSIGNED            COMMENT 'Parcela catastral',
    hectareas    DECIMAL(6,2) NOT NULL        COMMENT 'Superficie en hectáreas',
    tipo_cultivo ENUM('secano','regadio','ecologico') NOT NULL DEFAULT 'secano'
                                              COMMENT 'Tipo de cultivo del olivar',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Restricciones ──
    CONSTRAINT chk_fincas_hectareas CHECK (hectareas > 0),

    -- ── Claves foráneas ──
    CONSTRAINT fk_fincas_socio
        FOREIGN KEY (id_socio) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Fincas y parcelas de los socios — trazabilidad catastral';

-- Índices para fincas
CREATE INDEX idx_fincas_socio       ON fincas (id_socio);
CREATE INDEX idx_fincas_cultivo     ON fincas (tipo_cultivo);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. TABLA: entregas
-- ═══════════════════════════════════════════════════════════════════════════════
-- Registro de aportaciones de aceituna a la almazara.
--
-- Columna generada (STORED):
--   litros_aceite = (kilos × rendimiento / 100) / 0.916
--   → 0.916 kg/L es la densidad media del aceite de oliva a 20°C
--   → Se almacena físicamente (STORED) para poder indexarla y usarla en JOINs
--
-- Campaña:
--   Se genera automáticamente a partir de la fecha de entrega.
--   La campaña oleícola va de octubre (año N) a junio (año N+1).
--   Ej: una entrega del 15/11/2025 → campaña '2025/2026'
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE entregas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_socio        INT UNSIGNED NOT NULL        COMMENT 'FK → usuarios.id',
    fecha_entrega   DATE NOT NULL                COMMENT 'Fecha en que se realizó la entrega',

    -- Campaña generada automáticamente a partir de la fecha
    campana         VARCHAR(9) GENERATED ALWAYS AS (
                        CASE
                            WHEN MONTH(fecha_entrega) >= 7
                            THEN CONCAT(YEAR(fecha_entrega), '/', YEAR(fecha_entrega) + 1)
                            ELSE CONCAT(YEAR(fecha_entrega) - 1, '/', YEAR(fecha_entrega))
                        END
                    ) STORED                     COMMENT 'Campaña oleícola calculada (jul-jun)',

    kilos_aceituna  DECIMAL(10,2) NOT NULL       COMMENT 'Kilos brutos de aceituna entregados',
    rendimiento     DECIMAL(5,2) NOT NULL        COMMENT 'Porcentaje de rendimiento graso (%)',

    -- Litros de aceite: fórmula física (columna generada + almacenada)
    litros_aceite   DECIMAL(10,2) GENERATED ALWAYS AS (
                        ROUND(((kilos_aceituna * rendimiento) / 100) / 0.916, 2)
                    ) STORED                     COMMENT 'Litros estimados (fórmula densidad)',

    observaciones   TEXT                         COMMENT 'Notas opcionales sobre la entrega',
    albaran_pdf     VARCHAR(255)                 COMMENT 'Ruta al PDF del albarán generado',

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Restricciones de negocio ──
    CONSTRAINT chk_entregas_kilos       CHECK (kilos_aceituna > 0),
    CONSTRAINT chk_entregas_rendimiento CHECK (rendimiento > 0 AND rendimiento <= 100),

    -- ── Claves foráneas ──
    CONSTRAINT fk_entregas_socio
        FOREIGN KEY (id_socio) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Entregas de aceituna en la almazara — core del ERP';

-- Índices para entregas (los más críticos del sistema)
CREATE INDEX idx_entregas_socio         ON entregas (id_socio);
CREATE INDEX idx_entregas_fecha         ON entregas (fecha_entrega);
CREATE INDEX idx_entregas_campana       ON entregas (campana);
CREATE INDEX idx_entregas_socio_campana ON entregas (id_socio, campana);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. TABLA: productos
-- ═══════════════════════════════════════════════════════════════════════════════
-- Catálogo de la tienda online de aceite de oliva.
--
-- Decisiones:
--   • precio DECIMAL(8,2): hasta 999.999,99€ (más que suficiente)
--   • stock INT UNSIGNED: no puede ser negativo
--   • activo: soft-delete para ocultar productos sin borrar datos
--   • slug: URL amigable para SEO (ej: garrafa-aove-5l)
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE productos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL           COMMENT 'Nombre del producto',
    slug        VARCHAR(120) UNIQUE             COMMENT 'URL amigable para SEO',
    variedad    ENUM('Picual','Arbequina','Hojiblanca','Coupage',
                     'Manzanilla','Verdial','Cornezuelo','Morisca','Carrasqueña') NOT NULL
                                                COMMENT 'Variedad de aceituna (incluye autóctonas de Extremadura)',
    descripcion TEXT                            COMMENT 'Descripción para la ficha de producto',
    precio      DECIMAL(8,2) NOT NULL           COMMENT 'Precio unitario con IVA (€)',
    stock       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unidades disponibles',
    imagen      VARCHAR(255) NOT NULL DEFAULT 'default_aceite.jpg'
                                                COMMENT 'Nombre del archivo de imagen',
    activo      TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
                                                COMMENT '1=visible, 0=oculto en tienda',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Restricciones ──
    CONSTRAINT chk_productos_precio CHECK (precio > 0),
    CONSTRAINT chk_productos_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB
  COMMENT='Catálogo de productos de la tienda online';

-- Índices para productos
CREATE INDEX idx_productos_variedad    ON productos (variedad);
CREATE INDEX idx_productos_activo      ON productos (activo);
CREATE INDEX idx_productos_precio      ON productos (precio);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. TABLA: pedidos
-- ═══════════════════════════════════════════════════════════════════════════════
-- Cabecera de compras de la tienda online.
--
-- Decisiones:
--   • estado como ENUM: valores controlados por el motor
--   • total DECIMAL(10,2): hasta 99.999.999,99€
--   • dirección de envío: necesaria para gestión logística
--   • ON DELETE RESTRICT: no se pueden borrar usuarios con pedidos
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE pedidos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT UNSIGNED NOT NULL           COMMENT 'FK → usuarios.id (comprador)',
    fecha_pedido    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    COMMENT 'Momento exacto del pedido',
    total           DECIMAL(10,2) NOT NULL           COMMENT 'Importe total del pedido (€)',
    estado          ENUM('pendiente','pagado','procesando','enviado','entregado','cancelado')
                    NOT NULL DEFAULT 'pendiente'     COMMENT 'Estado del ciclo de vida del pedido',
    metodo_pago     VARCHAR(50)                      COMMENT 'Método: PayPal, tarjeta, transferencia...',

    -- Dirección de envío (snapshot en el momento del pedido)
    direccion_envio VARCHAR(255)                     COMMENT 'Dirección completa de entrega',
    codigo_postal   VARCHAR(10)                      COMMENT 'CP de entrega',
    localidad       VARCHAR(80)                      COMMENT 'Localidad de entrega',

    notas_cliente   TEXT                             COMMENT 'Observaciones del cliente',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- ── Restricciones ──
    CONSTRAINT chk_pedidos_total CHECK (total >= 0),

    -- ── Claves foráneas ──
    CONSTRAINT fk_pedidos_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Cabecera de pedidos de la tienda online';

-- Índices para pedidos
CREATE INDEX idx_pedidos_usuario     ON pedidos (id_usuario);
CREATE INDEX idx_pedidos_estado      ON pedidos (estado);
CREATE INDEX idx_pedidos_fecha       ON pedidos (fecha_pedido);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. TABLA: lineas_pedido
-- ═══════════════════════════════════════════════════════════════════════════════
-- Detalle (líneas) de cada pedido — patrón Master-Detail clásico.
--
-- Decisiones:
--   • PK compuesta (id_pedido, id_producto): un producto solo aparece una vez
--     por pedido; si quiere más cantidad, se usa el campo 'cantidad'
--   • precio_unitario: snapshot histórico — si el producto sube de precio,
--     los pedidos antiguos conservan el precio original
--   • subtotal generado: evita inconsistencias de cálculo
--   • ON DELETE RESTRICT en productos: impide borrar un producto vendido
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE lineas_pedido (
    id_pedido       INT UNSIGNED NOT NULL        COMMENT 'FK → pedidos.id',
    id_producto     INT UNSIGNED NOT NULL        COMMENT 'FK → productos.id',
    cantidad        SMALLINT UNSIGNED NOT NULL   COMMENT 'Unidades compradas',
    precio_unitario DECIMAL(8,2) NOT NULL        COMMENT 'Precio en el momento de la compra (€)',

    -- Subtotal calculado automáticamente
    subtotal        DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED
                                                COMMENT 'Subtotal de esta línea (auto-calculado)',

    -- ── Clave primaria compuesta ──
    PRIMARY KEY (id_pedido, id_producto),

    -- ── Restricciones ──
    CONSTRAINT chk_lineas_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_lineas_precio   CHECK (precio_unitario > 0),

    -- ── Claves foráneas ──
    CONSTRAINT fk_lineas_pedido
        FOREIGN KEY (id_pedido) REFERENCES pedidos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_lineas_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Líneas (detalle) de cada pedido — patrón Master-Detail';

-- Índice para acceder rápido por producto
CREATE INDEX idx_lineas_producto ON lineas_pedido (id_producto);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 8. TABLA: direcciones_usuario
-- ═══════════════════════════════════════════════════════════════════════════════
-- Direcciones guardadas por los usuarios para futuros envíos.
-- Un usuario puede tener varias y marcar una como predeterminada.
-- ═══════════════════════════════════════════════════════════════════════════════
CREATE TABLE direcciones_usuario (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT UNSIGNED NOT NULL        COMMENT 'FK → usuarios.id',
    alias           VARCHAR(50) NOT NULL DEFAULT 'Principal'
                                                 COMMENT 'Nombre identificativo: Casa, Oficina...',
    direccion       VARCHAR(255) NOT NULL        COMMENT 'Calle, número, piso, puerta',
    codigo_postal   VARCHAR(10) NOT NULL         COMMENT 'Código postal',
    localidad       VARCHAR(80) NOT NULL         COMMENT 'Localidad',
    provincia       VARCHAR(50) NOT NULL         COMMENT 'Provincia',
    es_predeterminada TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
                                                 COMMENT '1=dirección principal',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_dir_predeterminada CHECK (es_predeterminada IN (0, 1)),

    CONSTRAINT fk_direcciones_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Direcciones de envío guardadas por los usuarios';

CREATE INDEX idx_direcciones_usuario ON direcciones_usuario (id_usuario);


-- ═══════════════════════════════════════════════════════════════════════════════
-- 9. VISTAS (Views) — Consultas de alto valor precalculadas
-- ═══════════════════════════════════════════════════════════════════════════════
-- Las vistas abstraen la complejidad del SQL y hacen que el código PHP
-- sea más limpio y mantenible. Se consultan como tablas normales:
--   SELECT * FROM v_resumen_socios WHERE campana = '2025/2026'
-- ═══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- VISTA 1: v_resumen_socios
-- ─────────────────────────────────────────────────────────────────────────────
-- Cruza usuarios + fincas + entregas para obtener un resumen completo
-- de cada socio por campaña: cuántas fincas tiene, hectáreas totales,
-- kilos entregados y litros de aceite producidos.
--
-- Uso en PHP:
--   $stmt = $pdo->query("SELECT * FROM v_resumen_socios WHERE campana = '2025/2026'");
-- ─────────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_resumen_socios AS
SELECT
    u.id                                            AS id_socio,
    u.dni,
    CONCAT(u.nombre, ' ', COALESCE(u.apellidos,'')) AS nombre_completo,
    u.email,
    u.telefono,
    u.activo,

    -- Datos de fincas (subquery correlacionada para evitar duplicados)
    (SELECT COUNT(*)
     FROM fincas f WHERE f.id_socio = u.id)         AS total_fincas,
    (SELECT COALESCE(SUM(f.hectareas), 0)
     FROM fincas f WHERE f.id_socio = u.id)         AS total_hectareas,

    -- Datos de entregas por campaña
    e.campana,
    COUNT(e.id)                                     AS total_entregas,
    COALESCE(SUM(e.kilos_aceituna), 0)              AS total_kilos,
    COALESCE(SUM(e.litros_aceite), 0)               AS total_litros,
    ROUND(AVG(e.rendimiento), 2)                    AS rendimiento_medio

FROM usuarios u
LEFT JOIN entregas e ON e.id_socio = u.id
WHERE u.rol = 'socio'
GROUP BY u.id, e.campana;


-- ─────────────────────────────────────────────────────────────────────────────
-- VISTA 2: v_historial_entregas
-- ─────────────────────────────────────────────────────────────────────────────
-- Detalle de cada entrega con los datos del socio y la finca.
-- Sustituye los JOINs que actualmente haces en admin.php.
--
-- Uso en PHP:
--   $stmt = $pdo->query("SELECT * FROM v_historial_entregas ORDER BY fecha_entrega DESC");
-- ─────────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_historial_entregas AS
SELECT
    e.id                                            AS id_entrega,
    e.fecha_entrega,
    e.campana,
    e.kilos_aceituna,
    e.rendimiento,
    e.litros_aceite,
    e.observaciones,
    e.created_at,

    -- Datos del socio
    u.id                                            AS id_socio,
    u.dni,
    u.nombre,
    u.apellidos,
    CONCAT(u.nombre, ' ', COALESCE(u.apellidos,'')) AS nombre_completo

FROM entregas e
INNER JOIN usuarios u ON e.id_socio = u.id;


-- ─────────────────────────────────────────────────────────────────────────────
-- VISTA 3: v_estadisticas_campana
-- ─────────────────────────────────────────────────────────────────────────────
-- Estadísticas globales agregadas por campaña oleícola.
-- Perfecta para gráficas comparativas entre campañas.
--
-- Uso en PHP:
--   $stmt = $pdo->query("SELECT * FROM v_estadisticas_campana ORDER BY campana DESC");
-- ─────────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_estadisticas_campana AS
SELECT
    e.campana,
    COUNT(DISTINCT e.id_socio)                      AS socios_participantes,
    COUNT(e.id)                                     AS total_entregas,
    COALESCE(SUM(e.kilos_aceituna), 0)              AS total_kilos,
    COALESCE(SUM(e.litros_aceite), 0)               AS total_litros,
    ROUND(AVG(e.rendimiento), 2)                    AS rendimiento_medio,
    MIN(e.fecha_entrega)                            AS primera_entrega,
    MAX(e.fecha_entrega)                            AS ultima_entrega
FROM entregas e
GROUP BY e.campana;


-- ═══════════════════════════════════════════════════════════════════════════════
-- 10. DATOS DE PRUEBA (SEED DATA)
-- ═══════════════════════════════════════════════════════════════════════════════
-- Contraseña para todos los usuarios: 1234
-- Hash bcrypt generado con password_hash('1234', PASSWORD_DEFAULT)
--
-- IMPORTANTE: En producción, estos datos deben eliminarse.
-- Este bloque solo existe para desarrollo y pruebas.
-- ═══════════════════════════════════════════════════════════════════════════════

-- ── Usuarios ──
-- Contraseña para todos: 1234
-- Hash generado con: password_hash('1234', PASSWORD_DEFAULT)
INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol) VALUES
('11111111A', 'Paco',    'García López',    'paco@email.com',     '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600111222', 'socio'),
('22222222B', 'María',   'Fernández Ruiz',  'maria@email.com',    '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600333444', 'socio'),
('33333333C', 'Antonio', 'Martínez López',  'antonio@email.com',  '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600555666', 'socio'),
('00000000X', 'Admin',   'Principal',       'admin@almazara.es',  '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600000000', 'admin'),
('44444444D', 'Laura',   'Sánchez Molina',  'laura@email.com',    '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600777888', 'cliente');

-- ── Fincas ──
INSERT INTO fincas (id_socio, nombre_paraje, poligono, parcela, hectareas, tipo_cultivo) VALUES
(1, 'El Chaparral',     14,  250,  5.50, 'secano'),
(1, 'La Vega',           2,   15,  2.00, 'regadio'),
(2, 'Los Olivos Altos',  8,  112, 10.00, 'ecologico'),
(2, 'Cerro de la Cruz',  8,  115,  3.50, 'secano'),
(3, 'El Molinillo',     20,   42,  7.25, 'secano'),
(3, 'Huerta Grande',     3,   88,  4.00, 'regadio');

-- ── Entregas (campaña 2025/2026: entregas de noviembre y diciembre 2025) ──
INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones) VALUES
(1, '2025-11-15', 1500.00, 20.50, 'Primera entrega — aceituna temprana, buen estado'),
(1, '2025-12-03', 2200.00, 22.10, 'Segunda entrega — maduración óptima'),
(2, '2025-11-20', 3200.00, 19.80, 'Aceituna ecológica certificada'),
(2, '2025-12-10', 1800.00, 21.00, NULL),
(3, '2025-11-25', 4100.00, 18.50, 'Entrega parcial — queda mitad de la cosecha'),
(3, '2025-12-15', 3500.00, 20.00, 'Entrega final de la campaña');

-- ── Productos (catálogo completo: 4 clásicas + 5 autóctonas extremeñas) ──

INSERT INTO productos (nombre, slug, variedad, descripcion, precio, stock, imagen) VALUES
('Garrafa AOVE 5L',                   'garrafa-aove-5l',                    'Picual',       'Aceite de Oliva Virgen Extra. Extracción en frío, primera prensa. Sabor intenso y afrutado con notas de almendra verde.',                                                                                 42.50, 500, 'aceite-picual.png'),
('Botella Cristal Premium 500ml',     'botella-premium-500ml',              'Arbequina',    'Aceite suave y delicado. Ideal para crudo, ensaladas y repostería fina. Botella de vidrio oscuro.',                                                                                                       12.00, 150, 'aceite-arbequina.png'),
('Pack Degustación 3x250ml',          'pack-degustacion-3x250ml',           'Coupage',      'Selección de tres variedades en botellas de 250ml. Perfecto para regalo o para descubrir sabores.',                                                                                                        24.90,  80, 'aceite-coupage.png'),
('Garrafa Hojiblanca 2L',             'garrafa-hojiblanca-2l',              'Hojiblanca',   'Aceite de oliva con característico sabor dulce y ligero picor. Perfecto para cocinar y freír.',                                                                                                            18.50, 200, 'aceite-hojiblanca.png'),
('Manzanilla Cacereña 500ml',         'manzanilla-cacerena-500ml',          'Manzanilla',   'AOVE de Manzanilla Cacereña, la variedad reina del olivar de Cáceres. Afrutado intenso con notas de hierba fresca, plátano verde y un final almendrado. Recolección temprana.',                             14.50, 200, 'aceite-manzanilla.png'),
('Verdial de Badajoz 2L',             'verdial-badajoz-2l',                 'Verdial',      'Verdial de Badajoz, cultivar tradicional de La Serena. Aceite dulce y aromático, baja amargura, sin apenas picante. Ideal para uso diario y para los paladares más suaves.',                               22.00, 120, 'aceite-verdial.png'),
('Cornezuelo Tierra de Barros 500ml', 'cornezuelo-tierra-de-barros-500ml',  'Cornezuelo',   'Aceite elaborado con la rústica variedad Cornezuelo, autóctona de Tierra de Barros. Bouquet aromático intenso, ligero picor en garganta, notas vegetales y de tomatera.',                                  16.00,  90, 'aceite-cornezuelo.png'),
('Morisca DOP Monterrubio 750ml',     'morisca-dop-monterrubio-750ml',      'Morisca',      'Morisca extremeña amparada por la D.O.P. Aceite de Monterrubio. Equilibrio perfecto entre amargor y picante, con notas frutales, hierba recién cortada y un toque a hoja de olivo.',                      18.90,  75, 'aceite-morisca.png'),
('Carrasqueña Centenaria 250ml',      'carrasquena-centenaria-250ml',       'Carrasqueña',  'AOVE prensado en frío de olivos centenarios de La Vera. Variedad Carrasqueña, rústica y aromática. Notas a almendra verde, manzana y hierbas del campo. Edición limitada de cosecha temprana.',            13.50,  60, 'aceite-carrasquena.png');

-- ── Pedidos de ejemplo ──
INSERT INTO pedidos (id_usuario, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad) VALUES
(5, 54.50, 'pagado',    'tarjeta',       'Calle Mayor 15, 2ºA',  '23001', 'Jaén'),
(5, 24.90, 'pendiente', 'transferencia', 'Calle Mayor 15, 2ºA',  '23001', 'Jaén');

-- ── Líneas de pedido ──
INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(1, 1, 1, 42.50),  -- Garrafa AOVE 5L
(1, 2, 1, 12.00),  -- Botella Premium 500ml
(2, 3, 1, 24.90);  -- Pack Degustación


-- ═══════════════════════════════════════════════════════════════════════════════
-- FIN DEL SCRIPT
-- ═══════════════════════════════════════════════════════════════════════════════
-- Verificación rápida: ejecuta las siguientes consultas para comprobar que
-- todo funciona correctamente:
--
--   SELECT * FROM v_resumen_socios;
--   SELECT * FROM v_historial_entregas ORDER BY fecha_entrega DESC;
--   SELECT * FROM v_estadisticas_campana;
-- ═══════════════════════════════════════════════════════════════════════════════
