-- Tablón de Noticias + Calendario Agrícola.
-- Idempotente: se puede re-ejecutar sin romper la BD.

USE cooperativa_sjb;

-- ═══════════════════════════════════════════════════════════════════════
-- 1) NOTICIAS — Comunicados / novedades / avisos / eventos
-- ═══════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS noticias (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(180) NOT NULL,
    slug              VARCHAR(200) NOT NULL UNIQUE
                                    COMMENT 'URL amigable: derivado del título',
    resumen           VARCHAR(280)  COMMENT 'Subtítulo / extracto (listados)',
    contenido         TEXT NOT NULL COMMENT 'Cuerpo completo (texto plano + saltos)',
    categoria         ENUM('comunicado','novedad','aviso','evento') NOT NULL DEFAULT 'comunicado'
                                    COMMENT 'comunicado=oficial, novedad=mejora, aviso=urgente, evento=fecha',
    visibilidad       ENUM('publica','socios') NOT NULL DEFAULT 'publica'
                                    COMMENT 'publica=visible sin login; socios=requiere login (socio/admin/operario)',
    destacado         TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
                                    COMMENT '1=fija arriba en listados',
    activo            TINYINT(1) UNSIGNED NOT NULL DEFAULT 1
                                    COMMENT 'soft-delete (0=archivada)',
    id_autor          INT UNSIGNED  COMMENT 'FK -> usuarios.id (nullable si se borra el autor)',
    fecha_publicacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    COMMENT 'Permite programar futuras publicaciones',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_noticias_destacado CHECK (destacado IN (0, 1)),
    CONSTRAINT chk_noticias_activo    CHECK (activo IN (0, 1)),

    CONSTRAINT fk_noticias_autor
        FOREIGN KEY (id_autor) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  COMMENT='Tablón de noticias / comunicados de la cooperativa';

CREATE INDEX IF NOT EXISTS idx_noticias_listado    ON noticias (activo, fecha_publicacion);
CREATE INDEX IF NOT EXISTS idx_noticias_visibilidad ON noticias (visibilidad, activo);
CREATE INDEX IF NOT EXISTS idx_noticias_categoria  ON noticias (categoria);


-- ═══════════════════════════════════════════════════════════════════════
-- 2) CALENDARIO AGRÍCOLA — Tareas por mes
-- ═══════════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS calendario_tareas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mes         TINYINT UNSIGNED NOT NULL  COMMENT '1-12',
    titulo      VARCHAR(120) NOT NULL,
    descripcion TEXT NOT NULL,
    tip         TEXT          COMMENT 'Consejo práctico breve para el socio',
    icono       VARCHAR(60) NOT NULL DEFAULT 'bi-tree'
                              COMMENT 'Icono Bootstrap Icons',
    prioridad   ENUM('alta','media','baja') NOT NULL DEFAULT 'media',
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 1
                              COMMENT 'Orden dentro del mes',
    activo      TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_calendar_mes    CHECK (mes BETWEEN 1 AND 12),
    CONSTRAINT chk_calendar_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB
  COMMENT='Tareas recomendadas para el olivar por mes (calendario agrícola)';

CREATE INDEX IF NOT EXISTS idx_calendar_mes    ON calendario_tareas (mes, orden);
CREATE INDEX IF NOT EXISTS idx_calendar_activo ON calendario_tareas (activo);


-- ═══════════════════════════════════════════════════════════════════════
-- 3) SEEDS — Calendario completo por meses
-- ═══════════════════════════════════════════════════════════════════════
-- Sólo se siembra si la tabla está vacía: idempotente.
INSERT INTO calendario_tareas (mes, titulo, descripcion, tip, icono, prioridad, orden)
SELECT * FROM (
    SELECT  1                       AS mes,
            'Poda preliminar de saneamiento' AS titulo,
            'Eliminar ramas secas, rotas y mal orientadas. Despeje del centro del árbol para preparar la poda principal.' AS descripcion,
            'Aprovecha días sin heladas y sin lluvia. Quema o tritura los restos: no acumular sin tratar (foco de cochinilla).' AS tip,
            'bi-scissors'           AS icono,
            'media'                 AS prioridad,
            1                       AS orden
    UNION ALL SELECT  1, 'Abonado de fondo',
            'Aplicación de abono orgánico (estiércol compostado) o mineral de fondo en árboles jóvenes.',
            'En secano, espera a las lluvias para que el abono se infiltre. Mejor enterrarlo con un pase ligero.',
            'bi-droplet-half', 'media', 2

    UNION ALL SELECT  2, 'Poda principal del olivar',
            'Mes clave: poda de producción y formación. Elimina chupones, cruces y la madera vieja improductiva.',
            'Mantén la "copa de luz": el sol debe llegar hasta el centro. Un olivo bien podado da más fruto y menos vecero.',
            'bi-scissors', 'alta', 1
    UNION ALL SELECT  2, 'Tratamiento con cobre post-poda',
            'Aplicación preventiva de productos cúpricos sobre los cortes de poda para prevenir tuberculosis y repilo.',
            'Trata cuanto antes después de podar — los cortes son puerta de entrada para hongos y bacterias.',
            'bi-shield-check', 'alta', 2

    UNION ALL SELECT  3, 'Fin de poda y triturado de restos',
            'Última semana para podar antes de la brotación. Triturar la leña fina como mulch o quemarla controlada.',
            'Si tritutas y dejas en suelo, aportas materia orgánica. Si quemas, consulta normativa local.',
            'bi-fire', 'media', 1
    UNION ALL SELECT  3, 'Abonado de cobertera',
            'Aporte de nitrógeno-fósforo-potasio al inicio de la brotación primaveral.',
            'Con un análisis foliar previo afinas la dosis y ahorras fertilizante. Mejor que ir "a ojo".',
            'bi-flower2', 'media', 2

    UNION ALL SELECT  4, 'Tratamiento preventivo Prays oleae',
            'Vigilancia y trampeo de la 1ª generación de la polilla del olivo (Prays). Tratamiento si se supera el umbral.',
            'Coloca trampas con feromonas en marzo. Si capturas >15 polillas/trampa/semana, valora actuar.',
            'bi-bug', 'alta', 1
    UNION ALL SELECT  4, 'Aplicación foliar de calcio y boro',
            'Refuerzo nutricional foliar antes de la floración para mejorar cuajado.',
            'Mejor a primera hora de la mañana o última de la tarde — evitar horas de máximo sol.',
            'bi-leaf', 'baja', 2

    UNION ALL SELECT  5, 'Floración y cuajado del fruto',
            'Vigilancia del proceso de floración. Periodo crítico — un viento fuerte o una helada tardía pueden arruinar la cosecha.',
            'No apliques fitosanitarios durante la floración: matarías polinizadores. Espera al cuajado.',
            'bi-flower1', 'alta', 1
    UNION ALL SELECT  5, 'Trampas mosca del olivo',
            'Instalación de trampas (cromotrópicas o tipo OLIPE) para monitorizar la mosca del olivo (Bactrocera oleae).',
            'Una trampa cada 10 olivos da una buena lectura de la presión. Revísalas semanalmente.',
            'bi-pin-map', 'media', 2

    UNION ALL SELECT  6, 'Riego de apoyo en cuajado',
            'En regadío, momento clave: estrés hídrico ahora = aceitunas pequeñas. Mantén la humedad del suelo.',
            'Riega por la noche o de madrugada. Reduce la evaporación hasta un 30%.',
            'bi-droplet-fill', 'alta', 1
    UNION ALL SELECT  6, 'Lucha integrada Prays',
            'Tratamiento contra la 2ª generación (carpófaga) de Prays oleae si las trampas lo justifican.',
            'Bacillus thuringiensis es selectivo — no afecta a abejas ni a fauna útil.',
            'bi-shield', 'media', 2

    UNION ALL SELECT  7, 'Endurecimiento del hueso',
            'El fruto detiene su crecimiento aparente y se forma el hueso. No interpretes la pausa como problema.',
            'Es la fase más sensible al estrés hídrico. Si puedes regar, hazlo.',
            'bi-thermometer-sun', 'media', 1
    UNION ALL SELECT  7, 'Tratamientos contra mosca del olivo',
            'Pico de vuelo de Bactrocera oleae. Tratamiento curativo si se supera el umbral del 5% de fruto picado.',
            'Tratamientos por parcheo (sólo bordes y árboles más cargados) ahorran producto y respetan fauna útil.',
            'bi-bug-fill', 'alta', 2

    UNION ALL SELECT  8, 'Vigilar estrés hídrico',
            'Mes más caluroso. En secano, posible defoliación parcial — el olivo se autorregula.',
            'Si ves hojas mustias y se enrollan, el árbol "está pidiendo agua". En secano, paciencia.',
            'bi-sun-fill', 'media', 1
    UNION ALL SELECT  8, 'Preparar maquinaria de recolección',
            'Revisión de vibradores, mantos y maquinaria. Engrase, cuchillas, presiones de neumáticos.',
            'Un día de revisión en agosto te ahorra una semana de averías en plena cosecha.',
            'bi-tools', 'baja', 2

    UNION ALL SELECT  9, 'Último riego antes de cosecha',
            'En regadío, último aporte fuerte antes del cierre de campaña. Detener riego ~2 semanas antes de cosechar.',
            'Una aceituna sobreirrigada da menos rendimiento graso. Cierra el grifo a tiempo.',
            'bi-droplet', 'media', 1
    UNION ALL SELECT  9, 'Coordinación con almazara',
            'Reservar turnos de molturación. Confirmar con la cooperativa el calendario de entregas.',
            'Las aceitunas de un mismo lote conviene molturarlas en menos de 24h. Planifica recogida + entrega.',
            'bi-clipboard-check', 'media', 2

    UNION ALL SELECT 10, 'Inicio recolección variedades tempranas',
            'Comienza la recolección de variedades tempranas (Manzanilla, Verdial). AOVE de cosecha temprana = más amargor y picor.',
            'La aceituna recolectada en envero da los aceites más premiados — pero también el menor rendimiento graso.',
            'bi-basket', 'alta', 1
    UNION ALL SELECT 10, 'Apertura oficial de campaña',
            'Apertura de la almazara para recibir aceituna. Comprobación del precio de liquidación de la nueva campaña.',
            'Los primeros días de campaña la cooperativa no va a tope: aprovecha si tienes poca cantidad.',
            'bi-door-open', 'media', 2

    UNION ALL SELECT 11, 'Plena recolección',
            'Recolección de las variedades principales (Picual, Hojiblanca, Cornicabra). Ritmo intenso en almazara.',
            'Recolecta y transporta el mismo día. La aceituna fermenta rápido en remolque cerrado.',
            'bi-basket-fill', 'alta', 1
    UNION ALL SELECT 11, 'Análisis de rendimiento graso',
            'Cada entrega genera un análisis del % de rendimiento graso, que define los litros estimados de AOVE.',
            'Conserva todos los albaranes — son tu trazabilidad fiscal y tu prueba si hay discrepancias en la liquidación.',
            'bi-clipboard-data', 'media', 2

    UNION ALL SELECT 12, 'Cierre de la recolección',
            'Última semana de recolección. Cierre de la campaña en la almazara. Liquidación final de socios.',
            'Si quedan aceitunas en el árbol, déjalas — alimentan a los pájaros y reducen la "vereda" para el año siguiente.',
            'bi-archive', 'media', 1
    UNION ALL SELECT 12, 'Reposo vegetativo',
            'El olivo entra en reposo. Mes de balance: análisis de la campaña, planificación inversiones.',
            'Aprovecha para revisar lindes, cerramientos y caminos. La maquinaria descansa pero el agricultor planifica.',
            'bi-bookmark-check', 'baja', 2
) AS s
WHERE NOT EXISTS (SELECT 1 FROM calendario_tareas);


-- ═══════════════════════════════════════════════════════════════════════
-- 4) SEEDS — Noticias de ejemplo
-- ═══════════════════════════════════════════════════════════════════════
INSERT INTO noticias (titulo, slug, resumen, contenido, categoria, visibilidad, destacado, id_autor, fecha_publicacion)
SELECT * FROM (
    SELECT 'Apertura de la campaña 2025/2026'                                        AS titulo,
           'apertura-campana-2025-2026'                                              AS slug,
           'Ya está disponible el calendario de entregas para la nueva campaña olivarera. Precio inicial fijado en 0,4500 €/kg.' AS resumen,
           'La almazara abre oficialmente sus puertas para la recepción de aceituna correspondiente a la campaña 2025/2026.\n\nEste año arrancamos con un precio de liquidación inicial de 0,4500 €/kg, sujeto a revisión al cierre de la campaña en función del precio final del aceite en el mercado.\n\nHorario de recepción:\n- Lunes a viernes: 9:00 a 14:00 y 16:00 a 20:00\n- Sábados: 9:00 a 13:00\n\nRecuerda traer tu DNI o carné de socio cooperativista. Los albaranes se generan automáticamente y puedes descargarlos desde la sección "Mis Entregas" de tu panel.' AS contenido,
           'comunicado'                                                              AS categoria,
           'publica'                                                                 AS visibilidad,
           1                                                                         AS destacado,
           4                                                                         AS id_autor,
           DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 DAY)                               AS fecha_publicacion
    UNION ALL SELECT
           'Asamblea ordinaria de socios',
           'asamblea-ordinaria-socios',
           'Convocatoria de la asamblea ordinaria anual el próximo viernes. Orden del día: balance de campaña y liquidación.',
           'Se convoca a todos los socios cooperativistas a la asamblea ordinaria anual.\n\nORDEN DEL DÍA:\n1. Lectura y aprobación del acta anterior.\n2. Presentación del balance de la campaña 2024/2025.\n3. Aprobación de la liquidación final.\n4. Propuesta de inversión en nueva centrifugadora.\n5. Ruegos y preguntas.\n\nLa asistencia es muy importante: las decisiones se toman con quórum del 30% según los estatutos. Si no puedes asistir, recuerda que puedes delegar tu voto en otro socio.',
           'evento', 'socios', 1, 4,
           DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 DAY)
    UNION ALL SELECT
           'Nuevo módulo de gestión de stock disponible',
           'nuevo-modulo-stock',
           'Hemos publicado un nuevo módulo en la web para gestionar el inventario de la tienda online con trazabilidad completa.',
           'Como parte de la mejora continua de nuestra plataforma, hemos lanzado un nuevo módulo de gestión de stock para la tienda online.\n\nEl sistema permite:\n- Ver el inventario en tiempo real con alertas de stock bajo.\n- Registrar reposiciones y ajustes manuales con historial completo.\n- Trazabilidad de cada movimiento: entradas, salidas y ajustes.\n\nEsta mejora se traduce en menos roturas de stock y mejor experiencia para nuestros clientes online. Cualquier sugerencia, por favor háznosla llegar.',
           'novedad', 'publica', 0, 4,
           DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 DAY)
) AS s
WHERE NOT EXISTS (SELECT 1 FROM noticias);


-- ═══════════════════════════════════════════════════════════════════════
-- 5) Vista útil para listados públicos: noticia + nombre del autor
-- ═══════════════════════════════════════════════════════════════════════
CREATE OR REPLACE VIEW v_noticias_publicas AS
SELECT
    n.id, n.titulo, n.slug, n.resumen, n.contenido,
    n.categoria, n.visibilidad, n.destacado,
    n.fecha_publicacion, n.created_at,
    n.id_autor,
    COALESCE(CONCAT(u.nombre, ' ', COALESCE(u.apellidos,'')), 'Cooperativa') AS autor_nombre
FROM noticias n
LEFT JOIN usuarios u ON u.id = n.id_autor
WHERE n.activo = 1
  AND n.fecha_publicacion <= NOW();
