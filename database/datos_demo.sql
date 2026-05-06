-- ============================================================================
-- COOPERATIVA SAN JUAN BAUTISTA — Datos de demostración para tribunal del PFC
-- ============================================================================
-- Propósito:
--   Sembrar la base de datos con un conjunto de datos realista y coherente
--   para que las tablas y gráficas del panel de administración tengan
--   suficiente volumen y contraste durante la defensa del TFG.
--
-- Contenido:
--   • 10 usuarios (5 socios + 5 clientes)
--   • 5 fincas (1 por socio)
--   • 30 entregas de aceituna (15 en campaña 2024/2025 + 15 en 2025/2026)
--   • 5 pedidos en la tienda online con sus líneas de detalle
--
-- Pre-requisitos (orden de ejecución):
--   1) database/schema/cooperativa_sjb.sql        (esquema base v3.0)
--   2) database/migrations/01_votaciones.sql … 07_fix_encoding.sql
--   3) database/datos_demo.sql                     ← este archivo
--
-- Cómo ejecutar:
--   mysql -u root -p < database/datos_demo.sql
--
-- Contraseña de TODOS los usuarios demo: 1234
--   Hash bcrypt reutilizado del esquema base:
--   $2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K
--
-- Cómo limpiar los datos demo (opcional):
--   Los DNIs de los socios demo siguen el patrón 'XX000000?', y los emails
--   terminan en '@demo.coopsjb.es'. Para eliminarlos en bloque:
--
--     DELETE FROM lineas_pedido WHERE id_pedido IN
--         (SELECT id FROM pedidos WHERE id_usuario IN
--             (SELECT id FROM usuarios WHERE email LIKE '%@demo.coopsjb.es'));
--     DELETE FROM pedidos WHERE id_usuario IN
--         (SELECT id FROM usuarios WHERE email LIKE '%@demo.coopsjb.es');
--     DELETE FROM entregas WHERE id_socio IN
--         (SELECT id FROM usuarios WHERE email LIKE '%@demo.coopsjb.es');
--     DELETE FROM fincas WHERE id_socio IN
--         (SELECT id FROM usuarios WHERE email LIKE '%@demo.coopsjb.es');
--     DELETE FROM usuarios WHERE email LIKE '%@demo.coopsjb.es';
-- ============================================================================

USE cooperativa_sjb;

-- Aceleramos la carga: deshabilitamos verificación de FKs durante el seed
SET FOREIGN_KEY_CHECKS = 1;
SET autocommit = 0;
START TRANSACTION;


-- ─────────────────────────────────────────────────────────────────────────────
-- 1. USUARIOS — 5 socios + 5 clientes (10 en total)
-- ─────────────────────────────────────────────────────────────────────────────
-- Nombres y apellidos típicos de la comarca de la Siberia extremeña y Tierra
-- de Barros. DNIs sintéticos pero formalmente válidos (8 dígitos + letra).
-- Teléfonos con prefijos móviles españoles reales (6XX, 7XX).
-- ─────────────────────────────────────────────────────────────────────────────

-- ── SOCIOS (5) ──
INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('10000001A', 'José Antonio', 'Sánchez Carrasco',  'jose.sanchez@demo.coopsjb.es',     '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '611234567', 'socio', 1);
SET @s1 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('20000002B', 'María del Carmen', 'Vargas Romero',  'mcarmen.vargas@demo.coopsjb.es',  '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '622345678', 'socio', 1);
SET @s2 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('30000003C', 'Francisco Javier', 'Moreno Cordero', 'fjavier.moreno@demo.coopsjb.es',  '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '633456789', 'socio', 1);
SET @s3 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('40000004D', 'Juana', 'Núñez Calderón',           'juana.nunez@demo.coopsjb.es',     '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '644567890', 'socio', 1);
SET @s4 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('50000005E', 'Manuel', 'Casillas Jiménez',         'manuel.casillas@demo.coopsjb.es', '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '655678901', 'socio', 1);
SET @s5 := LAST_INSERT_ID();


-- ── CLIENTES (5) ──
INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('60000006F', 'Rosa María', 'Galán Pizarro',         'rosa.galan@demo.coopsjb.es',      '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '666789012', 'cliente', 1);
SET @c1 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('70000007G', 'Diego', 'Pacheco Bermúdez',           'diego.pacheco@demo.coopsjb.es',   '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '677890123', 'cliente', 1);
SET @c2 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('80000008H', 'Carmen', 'Cáceres Mendoza',           'carmen.caceres@demo.coopsjb.es',  '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '688901234', 'cliente', 1);
SET @c3 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('90000009J', 'Ignacio', 'Rodríguez Tena',           'ignacio.rodriguez@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '699012345', 'cliente', 1);
SET @c4 := LAST_INSERT_ID();

INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol, activo) VALUES
('00000010K', 'Pilar', 'Donoso Galindo',             'pilar.donoso@demo.coopsjb.es',    '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K', '600123456', 'cliente', 1);
SET @c5 := LAST_INSERT_ID();


-- ─────────────────────────────────────────────────────────────────────────────
-- 2. FINCAS — 5 fincas (una por socio)
-- ─────────────────────────────────────────────────────────────────────────────
-- Topónimos reales de la comarca: Dehesa, Cerro, Olivar, Vereda, Pizarras…
-- Polígonos y parcelas catastrales sintéticos pero coherentes.
-- Mezcla equilibrada de los 3 tipos de cultivo definidos en el ENUM.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO fincas (id_socio, nombre_paraje, poligono, parcela, hectareas, tipo_cultivo) VALUES
(@s1, 'Dehesa de los Almendros',         7,  312, 12.50, 'secano'),
(@s2, 'El Olivar de Don Alonso',        11,   88,  8.75, 'regadio'),
(@s3, 'Cerro del Águila',                5,  220, 15.00, 'ecologico'),
(@s4, 'Vereda del Pozo',                18,   45,  6.20, 'secano'),
(@s5, 'Las Pizarras del Cura',           9,  150, 10.30, 'regadio');


-- ─────────────────────────────────────────────────────────────────────────────
-- 3. CAMPAÑAS OLEÍCOLAS — Maestro
-- ─────────────────────────────────────────────────────────────────────────────
-- Garantizamos que existen las dos campañas que vamos a usar en las entregas.
-- INSERT IGNORE: si la migración 05_campanas.sql ya las creó (porque el
-- esquema base tenía entregas en 2025/2026), simplemente no se duplican.
-- Precios orientativos del sector extremeño:
--   • 2024/2025: 0,4400 €/kg — campaña ya cerrada para liquidación
--   • 2025/2026: 0,4800 €/kg — campaña activa con precio mejorado
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO campanas (codigo, fecha_inicio, fecha_fin, precio_por_kilo, estado, notas) VALUES
('2024/2025', '2024-07-01', '2025-06-30', 0.4400, 'cerrada',
   'Campaña anterior. Rendimiento medio inferior por climatología adversa (sequía estival).'),
('2025/2026', '2025-07-01', '2026-06-30', 0.4800, 'activa',
   'Campaña actual. Recuperación de producción tras lluvias regulares de primavera.');


-- ─────────────────────────────────────────────────────────────────────────────
-- 4. ENTREGAS — 30 registros distribuidos en 2 campañas
-- ─────────────────────────────────────────────────────────────────────────────
-- Estrategia para la defensa del TFG:
--   • 15 entregas en campaña 2024/2025 (nov 2024 – feb 2025) — total ~32.000 kg
--   • 15 entregas en campaña 2025/2026 (nov 2025 – feb 2026) — total ~37.100 kg
--
-- Esto produce contraste claro en las gráficas:
--   ▲ Aumento de producción interanual (+16 %)
--   ▲ Mejora de rendimiento medio (18,8 % → 20,5 %)
--   ▲ Aceituna mejor pagada (0,44 → 0,48 €/kg)
--
-- Las columnas `campana` (texto) y `litros_aceite` son GENERATED STORED:
-- el motor las calcula automáticamente al insertar la fila.
--
-- El campo `id_campana` se rellenará después con un único UPDATE de backfill.
-- ─────────────────────────────────────────────────────────────────────────────

-- ─── Campaña 2024/2025 (15 entregas) ──────────────────────────────────────
INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones) VALUES
-- José Antonio (s1) — 3 entregas
(@s1, '2024-11-15', 1800.00, 19.50, 'Primera entrega de la campaña, aceituna sana en estado óptimo.'),
(@s1, '2024-12-08', 2100.00, 20.10, 'Recolección de altura, fruto bien maduro.'),
(@s1, '2025-01-25', 1500.00, 18.80, 'Última entrega, aceituna ya muy madura por retrasos.'),
-- María del Carmen (s2) — 3 entregas
(@s2, '2024-11-22', 2500.00, 18.20, 'Olivar de regadío, gran cantidad pero rendimiento moderado.'),
(@s2, '2024-12-19', 2700.00, 19.80, 'Pico de cosecha, fruto en envero.'),
(@s2, '2025-01-15', 2300.00, 18.50, NULL),
-- Francisco Javier (s3) — 3 entregas
(@s3, '2024-11-30', 3500.00, 17.20, 'Aceituna ecológica certificada CAEX, sin tratamientos.'),
(@s3, '2024-12-15', 3200.00, 18.10, 'Segunda entrega de la finca grande.'),
(@s3, '2025-02-08', 3000.00, 17.50, 'Cierre de campaña, retraso por lluvias.'),
-- Juana (s4) — 3 entregas
(@s4, '2024-11-18', 1100.00, 19.00, 'Pequeño productor, parcela de 6 ha.'),
(@s4, '2024-12-29', 1300.00, 19.50, 'Recolección manual con vareo tradicional.'),
(@s4, '2025-02-12', 900.00,  18.20, 'Resto final, fruto muy maduro.'),
-- Manuel (s5) — 3 entregas
(@s5, '2024-12-12', 2000.00, 19.10, 'Aceituna de las Pizarras, primera entrada.'),
(@s5, '2025-01-08', 2200.00, 19.80, 'Carga normal de regadío, calidad buena.'),
(@s5, '2025-02-20', 1900.00, 18.70, 'Última entrega del socio en esta campaña.');


-- ─── Campaña 2025/2026 (15 entregas) ──────────────────────────────────────
INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones) VALUES
-- José Antonio (s1) — 3 entregas
(@s1, '2025-11-12', 2200.00, 21.20, 'Inicio de campaña excelente, fruto temprano de gran calidad.'),
(@s1, '2025-12-15', 2400.00, 22.30, 'Pleno rendimiento, aceituna en envero óptimo.'),
(@s1, '2026-01-30', 1700.00, 20.50, 'Cierre temprano, condiciones meteorológicas favorables.'),
-- María del Carmen (s2) — 3 entregas
(@s2, '2025-11-19', 2900.00, 20.50, 'Mejora notable respecto a la campaña anterior.'),
(@s2, '2025-12-22', 2800.00, 21.10, 'Aceituna verde en pinta, rendimiento alto.'),
(@s2, '2026-02-05', 2200.00, 19.90, 'Última recogida del año, buen estado sanitario.'),
-- Francisco Javier (s3) — 3 entregas
(@s3, '2025-11-25', 4100.00, 19.20, 'Récord de producción en olivar ecológico, año excepcional.'),
(@s3, '2025-12-10', 3800.00, 19.80, 'Segunda entrada de la finca grande, calidad superior.'),
(@s3, '2026-01-15', 3500.00, 18.90, 'Cierre de campaña con resultados muy positivos.'),
-- Juana (s4) — 3 entregas
(@s4, '2025-11-08', 1400.00, 20.10, 'Buen inicio para una explotación pequeña.'),
(@s4, '2025-12-05', 1500.00, 21.00, 'Mayor rendimiento que en años anteriores.'),
(@s4, '2026-02-15', 1200.00, 20.30, 'Cosecha final, fruto en perfecto estado.'),
-- Manuel (s5) — 3 entregas
(@s5, '2025-11-29', 2500.00, 20.80, 'Apertura de campaña en Las Pizarras, buen tonelaje.'),
(@s5, '2025-12-29', 2600.00, 21.50, 'Pico productivo, recolección con vibrador mecánico.'),
(@s5, '2026-01-22', 2300.00, 20.10, 'Cierre de la cosecha en regadío.');


-- Backfill de la FK id_campana en las entregas recién insertadas.
-- Mismo patrón que la migración 05_campanas.sql.
UPDATE entregas e
JOIN   campanas c ON c.codigo = e.campana
SET    e.id_campana = c.id
WHERE  e.id_campana IS NULL;


-- ─────────────────────────────────────────────────────────────────────────────
-- 5. PEDIDOS — 5 ventas online con líneas de detalle
-- ─────────────────────────────────────────────────────────────────────────────
-- Mezcla de estados para enriquecer las gráficas:
--   • 3 pedidos 'pagado'    → contabilizan en total_facturado y donut variedad
--   • 1 pedido  'enviado'   → muestra el embudo logístico
--   • 1 pedido  'pendiente' → demuestra que hay nuevos pedidos sin pagar
--
-- Total facturado (solo 'pagado'): 57,00 + 49,80 + 51,50 = 158,30 €
-- Direcciones de envío de provincias variadas para mostrar alcance nacional.
--
-- Productos referenciados por slug: el slug es UNIQUE en `productos`, por lo
-- que la subconsulta es estable independientemente del orden de inserción.
-- ─────────────────────────────────────────────────────────────────────────────

-- ── PEDIDO 1 — Rosa María Galán (pagado, Madrid) ─────────────────────────
INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad, notas_cliente) VALUES
(@c1, '2026-01-12 10:32:15', 57.00, 'pagado', 'tarjeta', 'Calle Velázquez 124, 3ºB', '28006', 'Madrid', 'Por favor, dejar en conserjería si no estoy en casa.');
SET @ped1 := LAST_INSERT_ID();

INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(@ped1, (SELECT id FROM productos WHERE slug = 'garrafa-aove-5l'),          1, 42.50),
(@ped1, (SELECT id FROM productos WHERE slug = 'manzanilla-cacerena-500ml'), 1, 14.50);


-- ── PEDIDO 2 — Diego Pacheco (pagado, Sevilla) ───────────────────────────
INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad, notas_cliente) VALUES
(@c2, '2026-02-03 17:45:08', 49.80, 'pagado', 'PayPal', 'Avda. de la Constitución 47, 5ºA', '41001', 'Sevilla', NULL);
SET @ped2 := LAST_INSERT_ID();

INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(@ped2, (SELECT id FROM productos WHERE slug = 'pack-degustacion-3x250ml'), 2, 24.90);


-- ── PEDIDO 3 — Carmen Cáceres (pagado, Cáceres) ──────────────────────────
INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad, notas_cliente) VALUES
(@c3, '2026-02-18 09:14:52', 51.50, 'pagado', 'tarjeta', 'Plaza Mayor 8, 1º Dcha.', '10003', 'Cáceres', 'Es un regalo, incluir tarjeta en blanco si es posible.');
SET @ped3 := LAST_INSERT_ID();

INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(@ped3, (SELECT id FROM productos WHERE slug = 'verdial-badajoz-2l'),                1, 22.00),
(@ped3, (SELECT id FROM productos WHERE slug = 'cornezuelo-tierra-de-barros-500ml'), 1, 16.00),
(@ped3, (SELECT id FROM productos WHERE slug = 'carrasquena-centenaria-250ml'),      1, 13.50);


-- ── PEDIDO 4 — Ignacio Rodríguez (enviado, Mérida) ───────────────────────
INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad, notas_cliente) VALUES
(@c4, '2026-03-05 13:22:41', 49.00, 'enviado', 'transferencia', 'Calle Hernán Cortés 22, bajo', '06800', 'Mérida', NULL);
SET @ped4 := LAST_INSERT_ID();

INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(@ped4, (SELECT id FROM productos WHERE slug = 'garrafa-hojiblanca-2l'),    2, 18.50),
(@ped4, (SELECT id FROM productos WHERE slug = 'botella-premium-500ml'),    1, 12.00);


-- ── PEDIDO 5 — Pilar Donoso (pendiente, Badajoz) ─────────────────────────
INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado, metodo_pago, direccion_envio, codigo_postal, localidad, notas_cliente) VALUES
(@c5, '2026-04-21 18:07:33', 47.90, 'pendiente', 'transferencia', 'Paseo de San Francisco 14, 2ºD', '06001', 'Badajoz', 'Pendiente de realizar transferencia bancaria.');
SET @ped5 := LAST_INSERT_ID();

INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(@ped5, (SELECT id FROM productos WHERE slug = 'morisca-dop-monterrubio-750ml'),  1, 18.90),
(@ped5, (SELECT id FROM productos WHERE slug = 'manzanilla-cacerena-500ml'),      2, 14.50);


-- ─────────────────────────────────────────────────────────────────────────────
-- 6. AJUSTE DE STOCK — coherencia con los pedidos creados
-- ─────────────────────────────────────────────────────────────────────────────
-- Decremento manual del stock acorde a las cantidades vendidas en los 5
-- pedidos demo. En el flujo normal de la app esto lo hace procesar_compra.php
-- dentro de una transacción, pero como aquí estamos sembrando filas de pedido
-- directamente en BD, hacemos el ajuste explícito para mantener la coherencia
-- entre el catálogo y las ventas registradas.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE productos SET stock = stock - 1 WHERE slug = 'garrafa-aove-5l';
UPDATE productos SET stock = stock - 3 WHERE slug = 'manzanilla-cacerena-500ml';
UPDATE productos SET stock = stock - 2 WHERE slug = 'pack-degustacion-3x250ml';
UPDATE productos SET stock = stock - 1 WHERE slug = 'verdial-badajoz-2l';
UPDATE productos SET stock = stock - 1 WHERE slug = 'cornezuelo-tierra-de-barros-500ml';
UPDATE productos SET stock = stock - 1 WHERE slug = 'carrasquena-centenaria-250ml';
UPDATE productos SET stock = stock - 2 WHERE slug = 'garrafa-hojiblanca-2l';
UPDATE productos SET stock = stock - 1 WHERE slug = 'botella-premium-500ml';
UPDATE productos SET stock = stock - 1 WHERE slug = 'morisca-dop-monterrubio-750ml';


COMMIT;


-- ============================================================================
-- VERIFICACIÓN POST-CARGA
-- ============================================================================
-- Ejecutar las siguientes consultas para comprobar que los datos demo se han
-- cargado correctamente y que las gráficas tienen contraste suficiente:
--
--   -- Conteo global
--   SELECT 'usuarios' AS tabla, COUNT(*) AS filas FROM usuarios
--   UNION SELECT 'fincas',       COUNT(*) FROM fincas
--   UNION SELECT 'entregas',     COUNT(*) FROM entregas
--   UNION SELECT 'pedidos',      COUNT(*) FROM pedidos
--   UNION SELECT 'lineas_pedido',COUNT(*) FROM lineas_pedido;
--
--   -- Resumen por campaña (vista pre-existente)
--   SELECT * FROM v_estadisticas_campana ORDER BY campana DESC;
--
--   -- Detalle de socios y su producción
--   SELECT * FROM v_resumen_socios WHERE campana IS NOT NULL ORDER BY campana DESC, total_kilos DESC;
--
--   -- Pedidos pagados (alimenta total_facturado del dashboard)
--   SELECT id, total, estado, fecha_pedido FROM pedidos
--   WHERE estado = 'pagado' ORDER BY fecha_pedido DESC;
--
-- Resultado esperado:
--   • 30 entregas con campana auto-calculada en {2024/2025, 2025/2026}
--   • Total kilos campaña 2024/2025 ≈ 31.900 kg
--   • Total kilos campaña 2025/2026 ≈ 37.100 kg  (+16,3 %)
--   • Rendimiento medio 2024/2025 ≈ 18,80 %
--   • Rendimiento medio 2025/2026 ≈ 20,48 %
--   • 3 pedidos 'pagado' = 158,30 € de facturación demo
-- ============================================================================
-- FIN DEL SCRIPT
-- ============================================================================
