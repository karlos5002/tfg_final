-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: cooperativa_sjb
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `calendario_tareas`
--

DROP TABLE IF EXISTS `calendario_tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendario_tareas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mes` tinyint(3) unsigned NOT NULL COMMENT '1-12',
  `titulo` varchar(120) NOT NULL,
  `descripcion` text NOT NULL,
  `tip` text DEFAULT NULL COMMENT 'Consejo pr├íctico breve para el socio',
  `icono` varchar(60) NOT NULL DEFAULT 'bi-tree' COMMENT 'Icono Bootstrap Icons',
  `prioridad` enum('alta','media','baja') NOT NULL DEFAULT 'media',
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Orden dentro del mes',
  `activo` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calendar_mes` (`mes`,`orden`),
  KEY `idx_calendar_activo` (`activo`),
  CONSTRAINT `chk_calendar_mes` CHECK (`mes` between 1 and 12),
  CONSTRAINT `chk_calendar_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tareas recomendadas para el olivar por mes (calendario agr├¡cola)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendario_tareas`
--

LOCK TABLES `calendario_tareas` WRITE;
/*!40000 ALTER TABLE `calendario_tareas` DISABLE KEYS */;
INSERT INTO `calendario_tareas` VALUES (1,1,'Poda preliminar de saneamiento','Eliminar ramas secas, rotas y mal orientadas. Despeje del centro del árbol para preparar la poda principal.','Aprovecha días sin heladas y sin lluvia. Quema o tritura los restos: no acumular sin tratar (foco de cochinilla).','bi-scissors','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(2,1,'Abonado de fondo','Aplicación de abono orgánico (estiércol compostado) o mineral de fondo en árboles jóvenes.','En secano, espera a las lluvias para que el abono se infiltre. Mejor enterrarlo con un pase ligero.','bi-droplet-half','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(3,2,'Poda principal del olivar','Mes clave: poda de producción y formación. Elimina chupones, cruces y la madera vieja improductiva.','Mantén la \"copa de luz\": el sol debe llegar hasta el centro. Un olivo bien podado da más fruto y menos vecero.','bi-scissors','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(4,2,'Tratamiento con cobre post-poda','Aplicación preventiva de productos cúpricos sobre los cortes de poda para prevenir tuberculosis y repilo.','Trata cuanto antes después de podar — los cortes son puerta de entrada para hongos y bacterias.','bi-shield-check','alta',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(5,3,'Fin de poda y triturado de restos','Última semana para podar antes de la brotación. Triturar la leña fina como mulch o quemarla controlada.','Si tritutas y dejas en suelo, aportas materia orgánica. Si quemas, consulta normativa local.','bi-fire','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(6,3,'Abonado de cobertera','Aporte de nitrógeno-fósforo-potasio al inicio de la brotación primaveral.','Con un análisis foliar previo afinas la dosis y ahorras fertilizante. Mejor que ir \"a ojo\".','bi-flower2','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(7,4,'Tratamiento preventivo Prays oleae','Vigilancia y trampeo de la 1ª generación de la polilla del olivo (Prays). Tratamiento si se supera el umbral.','Coloca trampas con feromonas en marzo. Si capturas >15 polillas/trampa/semana, valora actuar.','bi-bug','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(8,4,'Aplicación foliar de calcio y boro','Refuerzo nutricional foliar antes de la floración para mejorar cuajado.','Mejor a primera hora de la mañana o última de la tarde — evitar horas de máximo sol.','bi-leaf','baja',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(9,5,'Floración y cuajado del fruto','Vigilancia del proceso de floración. Periodo crítico — un viento fuerte o una helada tardía pueden arruinar la cosecha.','No apliques fitosanitarios durante la floración: matarías polinizadores. Espera al cuajado.','bi-flower1','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(10,5,'Trampas mosca del olivo','Instalación de trampas (cromotrópicas o tipo OLIPE) para monitorizar la mosca del olivo (Bactrocera oleae).','Una trampa cada 10 olivos da una buena lectura de la presión. Revísalas semanalmente.','bi-pin-map','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(11,6,'Riego de apoyo en cuajado','En regadío, momento clave: estrés hídrico ahora = aceitunas pequeñas. Mantén la humedad del suelo.','Riega por la noche o de madrugada. Reduce la evaporación hasta un 30%.','bi-droplet-fill','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(12,6,'Lucha integrada Prays','Tratamiento contra la 2ª generación (carpófaga) de Prays oleae si las trampas lo justifican.','Bacillus thuringiensis es selectivo — no afecta a abejas ni a fauna útil.','bi-shield','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(13,7,'Endurecimiento del hueso','El fruto detiene su crecimiento aparente y se forma el hueso. No interpretes la pausa como problema.','Es la fase más sensible al estrés hídrico. Si puedes regar, hazlo.','bi-thermometer-sun','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(14,7,'Tratamientos contra mosca del olivo','Pico de vuelo de Bactrocera oleae. Tratamiento curativo si se supera el umbral del 5% de fruto picado.','Tratamientos por parcheo (sólo bordes y árboles más cargados) ahorran producto y respetan fauna útil.','bi-bug-fill','alta',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(15,8,'Vigilar estrés hídrico','Mes más caluroso. En secano, posible defoliación parcial — el olivo se autorregula.','Si ves hojas mustias y se enrollan, el árbol \"está pidiendo agua\". En secano, paciencia.','bi-sun-fill','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(16,8,'Preparar maquinaria de recolección','Revisión de vibradores, mantos y maquinaria. Engrase, cuchillas, presiones de neumáticos.','Un día de revisión en agosto te ahorra una semana de averías en plena cosecha.','bi-tools','baja',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(17,9,'Último riego antes de cosecha','En regadío, último aporte fuerte antes del cierre de campaña. Detener riego ~2 semanas antes de cosechar.','Una aceituna sobreirrigada da menos rendimiento graso. Cierra el grifo a tiempo.','bi-droplet','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(18,9,'Coordinación con almazara','Reservar turnos de molturación. Confirmar con la cooperativa el calendario de entregas.','Las aceitunas de un mismo lote conviene molturarlas en menos de 24h. Planifica recogida + entrega.','bi-clipboard-check','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(19,10,'Inicio recolección variedades tempranas','Comienza la recolección de variedades tempranas (Manzanilla, Verdial). AOVE de cosecha temprana = más amargor y picor.','La aceituna recolectada en envero da los aceites más premiados — pero también el menor rendimiento graso.','bi-basket','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(20,10,'Apertura oficial de campaña','Apertura de la almazara para recibir aceituna. Comprobación del precio de liquidación de la nueva campaña.','Los primeros días de campaña la cooperativa no va a tope: aprovecha si tienes poca cantidad.','bi-door-open','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(21,11,'Plena recolección','Recolección de las variedades principales (Picual, Hojiblanca, Cornicabra). Ritmo intenso en almazara.','Recolecta y transporta el mismo día. La aceituna fermenta rápido en remolque cerrado.','bi-basket-fill','alta',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(22,11,'Análisis de rendimiento graso','Cada entrega genera un análisis del % de rendimiento graso, que define los litros estimados de AOVE.','Conserva todos los albaranes — son tu trazabilidad fiscal y tu prueba si hay discrepancias en la liquidación.','bi-clipboard-data','media',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(23,12,'Cierre de la recolección','Última semana de recolección. Cierre de la campaña en la almazara. Liquidación final de socios.','Si quedan aceitunas en el árbol, déjalas — alimentan a los pájaros y reducen la \"vereda\" para el año siguiente.','bi-archive','media',1,1,'2026-05-01 13:35:44','2026-05-01 13:35:44'),(24,12,'Reposo vegetativo','El olivo entra en reposo. Mes de balance: análisis de la campaña, planificación inversiones.','Aprovecha para revisar lindes, cerramientos y caminos. La maquinaria descansa pero el agricultor planifica.','bi-bookmark-check','baja',2,1,'2026-05-01 13:35:44','2026-05-01 13:35:44');
/*!40000 ALTER TABLE `calendario_tareas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campanas`
--

DROP TABLE IF EXISTS `campanas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campanas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(9) NOT NULL COMMENT 'C├│digo legible: "2025/2026"',
  `fecha_inicio` date NOT NULL COMMENT 'Inicio del periodo de recepci├│n de aceituna',
  `fecha_fin` date NOT NULL COMMENT 'Fin del periodo (inclusive)',
  `precio_por_kilo` decimal(6,4) NOT NULL COMMENT 'Precio de liquidaci├│n al socio (Ôé¼/kg de aceituna)',
  `estado` enum('activa','cerrada') NOT NULL DEFAULT 'activa' COMMENT 'activa=admite entregas; cerrada=hist├│rico',
  `notas` text DEFAULT NULL COMMENT 'Observaciones internas: rendimiento medio esperado, eventosÔÇª',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_campanas_estado` (`estado`),
  KEY `idx_campanas_codigo` (`codigo`),
  CONSTRAINT `chk_camp_fechas` CHECK (`fecha_fin` > `fecha_inicio`),
  CONSTRAINT `chk_camp_precio` CHECK (`precio_por_kilo` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Campa├▒as ole├¡colas ÔÇö precio de liquidaci├│n y estado';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campanas`
--

LOCK TABLES `campanas` WRITE;
/*!40000 ALTER TABLE `campanas` DISABLE KEYS */;
INSERT INTO `campanas` VALUES (1,'2025/2026','2025-07-01','2026-06-30',0.4500,'activa','Generada automáticamente por la migración 05_campanas.sql','2026-04-30 08:27:27','2026-05-01 13:35:44'),(2,'2024/2025','2024-07-01','2025-06-30',0.4400,'cerrada','Campaña anterior. Rendimiento medio inferior por climatología adversa (sequía estival).','2026-05-02 13:44:45','2026-05-02 13:44:45');
/*!40000 ALTER TABLE `campanas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `direcciones_usuario`
--

DROP TABLE IF EXISTS `direcciones_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `direcciones_usuario` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL COMMENT 'FK → usuarios.id',
  `alias` varchar(50) NOT NULL DEFAULT 'Principal' COMMENT 'Nombre identificativo: Casa, Oficina...',
  `direccion` varchar(255) NOT NULL COMMENT 'Calle, número, piso, puerta',
  `codigo_postal` varchar(10) NOT NULL COMMENT 'Código postal',
  `localidad` varchar(80) NOT NULL COMMENT 'Localidad',
  `provincia` varchar(50) NOT NULL COMMENT 'Provincia',
  `es_predeterminada` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '1=dirección principal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_direcciones_usuario` (`id_usuario`),
  CONSTRAINT `fk_direcciones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dir_predeterminada` CHECK (`es_predeterminada` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Direcciones de envío guardadas por los usuarios';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `direcciones_usuario`
--

LOCK TABLES `direcciones_usuario` WRITE;
/*!40000 ALTER TABLE `direcciones_usuario` DISABLE KEYS */;
/*!40000 ALTER TABLE `direcciones_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entregas`
--

DROP TABLE IF EXISTS `entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entregas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_socio` int(10) unsigned NOT NULL COMMENT 'FK → usuarios.id',
  `fecha_entrega` date NOT NULL COMMENT 'Fecha en que se realizó la entrega',
  `campana` varchar(9) GENERATED ALWAYS AS (case when month(`fecha_entrega`) >= 7 then concat(year(`fecha_entrega`),'/',year(`fecha_entrega`) + 1) else concat(year(`fecha_entrega`) - 1,'/',year(`fecha_entrega`)) end) STORED COMMENT 'Campaña oleícola calculada (jul-jun)',
  `id_campana` int(10) unsigned DEFAULT NULL COMMENT 'FK -> campanas.id (autoresuelto por fecha de entrega)',
  `kilos_aceituna` decimal(10,2) NOT NULL COMMENT 'Kilos brutos de aceituna entregados',
  `rendimiento` decimal(5,2) NOT NULL COMMENT 'Porcentaje de rendimiento graso (%)',
  `litros_aceite` decimal(10,2) GENERATED ALWAYS AS (round(`kilos_aceituna` * `rendimiento` / 100 / 0.916,2)) STORED COMMENT 'Litros estimados (fórmula densidad)',
  `observaciones` text DEFAULT NULL COMMENT 'Notas opcionales sobre la entrega',
  `albaran_pdf` varchar(255) DEFAULT NULL COMMENT 'Ruta al PDF del albarán generado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entregas_socio` (`id_socio`),
  KEY `idx_entregas_fecha` (`fecha_entrega`),
  KEY `idx_entregas_campana` (`campana`),
  KEY `idx_entregas_socio_campana` (`id_socio`,`campana`),
  KEY `idx_entregas_id_campana` (`id_campana`),
  CONSTRAINT `fk_entregas_campana` FOREIGN KEY (`id_campana`) REFERENCES `campanas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_entregas_socio` FOREIGN KEY (`id_socio`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_entregas_kilos` CHECK (`kilos_aceituna` > 0),
  CONSTRAINT `chk_entregas_rendimiento` CHECK (`rendimiento` > 0 and `rendimiento` <= 100)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entregas de aceituna en la almazara — core del ERP';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entregas`
--

LOCK TABLES `entregas` WRITE;
/*!40000 ALTER TABLE `entregas` DISABLE KEYS */;
INSERT INTO `entregas` VALUES (1,1,'2025-11-15','2025/2026',1,1500.00,20.50,335.70,'Primera entrega — aceituna temprana, buen estado',NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(2,1,'2025-12-03','2025/2026',1,2200.00,22.10,530.79,'Segunda entrega — maduración óptima',NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(3,2,'2025-11-20','2025/2026',1,3200.00,19.80,691.70,'Aceituna ecológica certificada',NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(4,2,'2025-12-10','2025/2026',1,1800.00,21.00,412.66,NULL,NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(5,3,'2025-11-25','2025/2026',1,4100.00,18.50,828.06,'Entrega parcial — queda mitad de la cosecha',NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(6,3,'2025-12-15','2025/2026',1,3500.00,20.00,764.19,'Entrega final de la campaña',NULL,'2026-04-25 12:02:23','2026-04-30 08:27:27'),(7,1,'2026-04-25','2025/2026',1,2000.00,21.00,458.52,NULL,NULL,'2026-04-25 13:50:12','2026-04-30 08:27:27'),(8,6,'2026-04-27','2025/2026',1,20000.00,21.00,4585.15,NULL,NULL,'2026-04-27 17:36:12','2026-04-30 08:27:27'),(9,6,'2026-04-28','2025/2026',1,1000.00,21.00,229.26,NULL,NULL,'2026-04-27 17:59:47','2026-04-30 08:27:27'),(10,6,'2026-04-28','2025/2026',1,2000.00,21.00,458.52,NULL,NULL,'2026-04-28 17:58:58','2026-04-30 08:27:27'),(11,2,'2026-04-30','2025/2026',1,50000.00,21.00,11462.88,'[Registrado por Pepe]',NULL,'2026-04-30 08:23:23','2026-04-30 08:27:27'),(12,6,'2026-05-01','2025/2026',1,2222.00,21.00,509.41,'[Registrado por Carlos]',NULL,'2026-05-01 13:59:08','2026-05-01 13:59:08'),(13,9,'2024-11-15','2024/2025',2,1800.00,19.50,383.19,'Primera entrega de la campaña, aceituna sana en estado óptimo.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(14,9,'2024-12-08','2024/2025',2,2100.00,20.10,460.81,'Recolección de altura, fruto bien maduro.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(15,9,'2025-01-25','2024/2025',2,1500.00,18.80,307.86,'Última entrega, aceituna ya muy madura por retrasos.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(16,10,'2024-11-22','2024/2025',2,2500.00,18.20,496.72,'Olivar de regadío, gran cantidad pero rendimiento moderado.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(17,10,'2024-12-19','2024/2025',2,2700.00,19.80,583.62,'Pico de cosecha, fruto en envero.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(18,10,'2025-01-15','2024/2025',2,2300.00,18.50,464.52,NULL,NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(19,11,'2024-11-30','2024/2025',2,3500.00,17.20,657.21,'Aceituna ecológica certificada CAEX, sin tratamientos.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(20,11,'2024-12-15','2024/2025',2,3200.00,18.10,632.31,'Segunda entrega de la finca grande.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(21,11,'2025-02-08','2024/2025',2,3000.00,17.50,573.14,'Cierre de campaña, retraso por lluvias.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(22,12,'2024-11-18','2024/2025',2,1100.00,19.00,228.17,'Pequeño productor, parcela de 6 ha.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(23,12,'2024-12-29','2024/2025',2,1300.00,19.50,276.75,'Recolección manual con vareo tradicional.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(24,12,'2025-02-12','2024/2025',2,900.00,18.20,178.82,'Resto final, fruto muy maduro.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(25,13,'2024-12-12','2024/2025',2,2000.00,19.10,417.03,'Aceituna de las Pizarras, primera entrada.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(26,13,'2025-01-08','2024/2025',2,2200.00,19.80,475.55,'Carga normal de regadío, calidad buena.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(27,13,'2025-02-20','2024/2025',2,1900.00,18.70,387.88,'Última entrega del socio en esta campaña.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(28,9,'2025-11-12','2025/2026',1,2200.00,21.20,509.17,'Inicio de campaña excelente, fruto temprano de gran calidad.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(29,9,'2025-12-15','2025/2026',1,2400.00,22.30,584.28,'Pleno rendimiento, aceituna en envero óptimo.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(30,9,'2026-01-30','2025/2026',1,1700.00,20.50,380.46,'Cierre temprano, condiciones meteorológicas favorables.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(31,10,'2025-11-19','2025/2026',1,2900.00,20.50,649.02,'Mejora notable respecto a la campaña anterior.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(32,10,'2025-12-22','2025/2026',1,2800.00,21.10,644.98,'Aceituna verde en pinta, rendimiento alto.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(33,10,'2026-02-05','2025/2026',1,2200.00,19.90,477.95,'Última recogida del año, buen estado sanitario.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(34,11,'2025-11-25','2025/2026',1,4100.00,19.20,859.39,'Récord de producción en olivar ecológico, año excepcional.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(35,11,'2025-12-10','2025/2026',1,3800.00,19.80,821.40,'Segunda entrada de la finca grande, calidad superior.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(36,11,'2026-01-15','2025/2026',1,3500.00,18.90,722.16,'Cierre de campaña con resultados muy positivos.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(37,12,'2025-11-08','2025/2026',1,1400.00,20.10,307.21,'Buen inicio para una explotación pequeña.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(38,12,'2025-12-05','2025/2026',1,1500.00,21.00,343.89,'Mayor rendimiento que en años anteriores.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(39,12,'2026-02-15','2025/2026',1,1200.00,20.30,265.94,'Cosecha final, fruto en perfecto estado.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(40,13,'2025-11-29','2025/2026',1,2500.00,20.80,567.69,'Apertura de campaña en Las Pizarras, buen tonelaje.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(41,13,'2025-12-29','2025/2026',1,2600.00,21.50,610.26,'Pico productivo, recolección con vibrador mecánico.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(42,13,'2026-01-22','2025/2026',1,2300.00,20.10,504.69,'Cierre de la cosecha en regadío.',NULL,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(43,6,'2026-05-02','2025/2026',1,222.00,21.00,50.90,'[Registrado por Carlos]',NULL,'2026-05-02 13:54:59','2026-05-02 13:54:59'),(44,6,'2026-05-04','2025/2026',1,1.00,21.00,0.23,'l [Registrado por Carlos]',NULL,'2026-05-04 17:32:50','2026-05-04 17:32:50');
/*!40000 ALTER TABLE `entregas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fincas`
--

DROP TABLE IF EXISTS `fincas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fincas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_socio` int(10) unsigned NOT NULL COMMENT 'FK → usuarios.id (debe ser socio)',
  `nombre_paraje` varchar(100) NOT NULL COMMENT 'Nombre del paraje o finca',
  `poligono` smallint(5) unsigned DEFAULT NULL COMMENT 'Polígono catastral',
  `parcela` smallint(5) unsigned DEFAULT NULL COMMENT 'Parcela catastral',
  `hectareas` decimal(6,2) NOT NULL COMMENT 'Superficie en hectáreas',
  `tipo_cultivo` enum('secano','regadio','ecologico') NOT NULL DEFAULT 'secano' COMMENT 'Tipo de cultivo del olivar',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fincas_socio` (`id_socio`),
  KEY `idx_fincas_cultivo` (`tipo_cultivo`),
  CONSTRAINT `fk_fincas_socio` FOREIGN KEY (`id_socio`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_fincas_hectareas` CHECK (`hectareas` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fincas y parcelas de los socios — trazabilidad catastral';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fincas`
--

LOCK TABLES `fincas` WRITE;
/*!40000 ALTER TABLE `fincas` DISABLE KEYS */;
INSERT INTO `fincas` VALUES (1,1,'El Chaparral',14,250,5.50,'secano','2026-04-25 12:02:23','2026-04-25 12:02:23'),(2,1,'La Vega',2,15,2.00,'regadio','2026-04-25 12:02:23','2026-04-25 12:02:23'),(3,2,'Los Olivos Altos',8,112,10.00,'ecologico','2026-04-25 12:02:23','2026-04-25 12:02:23'),(4,2,'Cerro de la Cruz',8,115,3.50,'secano','2026-04-25 12:02:23','2026-04-25 12:02:23'),(5,3,'El Molinillo',20,42,7.25,'secano','2026-04-25 12:02:23','2026-04-25 12:02:23'),(6,3,'Huerta Grande',3,88,4.00,'regadio','2026-04-25 12:02:23','2026-04-25 12:02:23'),(7,9,'Dehesa de los Almendros',7,312,12.50,'secano','2026-05-02 13:44:45','2026-05-02 13:44:45'),(8,10,'El Olivar de Don Alonso',11,88,8.75,'regadio','2026-05-02 13:44:45','2026-05-02 13:44:45'),(9,11,'Cerro del Águila',5,220,15.00,'ecologico','2026-05-02 13:44:45','2026-05-02 13:44:45'),(10,12,'Vereda del Pozo',18,45,6.20,'secano','2026-05-02 13:44:45','2026-05-02 13:44:45'),(11,13,'Las Pizarras del Cura',9,150,10.30,'regadio','2026-05-02 13:44:45','2026-05-02 13:44:45');
/*!40000 ALTER TABLE `fincas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lineas_pedido`
--

DROP TABLE IF EXISTS `lineas_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lineas_pedido` (
  `id_pedido` int(10) unsigned NOT NULL COMMENT 'FK → pedidos.id',
  `id_producto` int(10) unsigned NOT NULL COMMENT 'FK → productos.id',
  `cantidad` smallint(5) unsigned NOT NULL COMMENT 'Unidades compradas',
  `precio_unitario` decimal(8,2) NOT NULL COMMENT 'Precio en el momento de la compra (€)',
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED COMMENT 'Subtotal de esta línea (auto-calculado)',
  PRIMARY KEY (`id_pedido`,`id_producto`),
  KEY `idx_lineas_producto` (`id_producto`),
  CONSTRAINT `fk_lineas_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lineas_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_lineas_cantidad` CHECK (`cantidad` > 0),
  CONSTRAINT `chk_lineas_precio` CHECK (`precio_unitario` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Líneas (detalle) de cada pedido — patrón Master-Detail';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lineas_pedido`
--

LOCK TABLES `lineas_pedido` WRITE;
/*!40000 ALTER TABLE `lineas_pedido` DISABLE KEYS */;
INSERT INTO `lineas_pedido` VALUES (1,1,1,42.50,42.50),(1,2,1,12.00,12.00),(2,3,1,24.90,24.90),(3,2,1,12.00,12.00),(3,4,1,18.50,18.50),(4,4,1,18.50,18.50),(5,2,6,12.00,72.00),(6,1,1,42.50,42.50),(7,3,1,24.90,24.90),(8,2,1,12.00,12.00),(9,2,120,12.00,1440.00),(10,1,1,42.50,42.50),(10,5,1,14.50,14.50),(11,3,2,24.90,49.80),(12,6,1,22.00,22.00),(12,7,1,16.00,16.00),(12,9,1,13.50,13.50),(13,2,1,12.00,12.00),(13,4,2,18.50,37.00),(14,5,2,14.50,29.00),(14,8,1,18.90,18.90),(15,2,23,12.00,276.00),(16,5,1,14.50,14.50),(17,4,1,18.50,18.50);
/*!40000 ALTER TABLE `lineas_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_stock`
--

DROP TABLE IF EXISTS `movimientos_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_producto` int(10) unsigned NOT NULL COMMENT 'FK -> productos.id',
  `tipo` enum('entrada','salida','ajuste') NOT NULL COMMENT 'entrada=reposici├│n, salida=venta, ajuste=correcci├│n manual',
  `cantidad` int(11) NOT NULL COMMENT 'Unidades (positivo entrada/salida; signo libre en ajuste)',
  `stock_antes` int(10) unsigned NOT NULL COMMENT 'Stock del producto ANTES de aplicar el movimiento',
  `stock_despues` int(10) unsigned NOT NULL COMMENT 'Stock del producto DESPU├ëS de aplicar el movimiento',
  `motivo` varchar(180) DEFAULT NULL COMMENT 'Texto libre: n┬║ albar├ín proveedor, motivo del ajuste, etc.',
  `id_pedido` int(10) unsigned DEFAULT NULL COMMENT 'Pedido asociado si tipo=salida (NULL para entradas/ajustes)',
  `id_usuario` int(10) unsigned DEFAULT NULL COMMENT 'Qui├®n registr├│ el movimiento (NULL si es venta autom├ítica)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_movs_usuario` (`id_usuario`),
  KEY `idx_movs_producto` (`id_producto`),
  KEY `idx_movs_tipo_fecha` (`tipo`,`created_at`),
  KEY `idx_movs_pedido` (`id_pedido`),
  CONSTRAINT `fk_movs_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_movs_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_movs_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_movs_cantidad_no_cero` CHECK (`cantidad` <> 0)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de movimientos de stock ÔÇö trazabilidad completa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos_stock`
--

LOCK TABLES `movimientos_stock` WRITE;
/*!40000 ALTER TABLE `movimientos_stock` DISABLE KEYS */;
INSERT INTO `movimientos_stock` VALUES (1,1,'entrada',499,0,499,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(2,2,'entrada',143,0,143,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(3,3,'entrada',79,0,79,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(4,4,'entrada',198,0,198,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(5,5,'entrada',200,0,200,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(6,6,'entrada',120,0,120,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(7,7,'entrada',90,0,90,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(8,8,'entrada',75,0,75,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(9,9,'entrada',60,0,60,'Stock inicial — carga del catálogo',NULL,4,'2026-04-30 07:31:21'),(16,2,'salida',1,143,142,'Venta — pedido #8',8,6,'2026-04-30 08:18:16'),(17,2,'salida',120,142,22,'Venta — pedido #9',9,6,'2026-04-30 08:20:05'),(18,2,'entrada',2,21,23,'Reposición manual',NULL,4,'2026-05-04 17:28:04'),(19,2,'salida',23,23,0,'Venta — pedido #15',15,6,'2026-05-04 17:35:15'),(20,5,'salida',1,197,196,'Venta — pedido #16',16,6,'2026-05-06 16:41:46'),(21,4,'salida',1,196,195,'Venta — pedido #17',17,6,'2026-05-06 16:48:37');
/*!40000 ALTER TABLE `movimientos_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `noticias`
--

DROP TABLE IF EXISTS `noticias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `noticias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL COMMENT 'URL amigable: derivado del t├¡tulo',
  `resumen` varchar(280) DEFAULT NULL COMMENT 'Subt├¡tulo / extracto (listados)',
  `contenido` text NOT NULL COMMENT 'Cuerpo completo (texto plano + saltos)',
  `categoria` enum('comunicado','novedad','aviso','evento') NOT NULL DEFAULT 'comunicado' COMMENT 'comunicado=oficial, novedad=mejora, aviso=urgente, evento=fecha',
  `visibilidad` enum('publica','socios') NOT NULL DEFAULT 'publica' COMMENT 'publica=visible sin login; socios=requiere login (socio/admin/operario)',
  `destacado` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '1=fija arriba en listados',
  `activo` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT 'soft-delete (0=archivada)',
  `id_autor` int(10) unsigned DEFAULT NULL COMMENT 'FK -> usuarios.id (nullable si se borra el autor)',
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Permite programar futuras publicaciones',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_noticias_autor` (`id_autor`),
  KEY `idx_noticias_listado` (`activo`,`fecha_publicacion`),
  KEY `idx_noticias_visibilidad` (`visibilidad`,`activo`),
  KEY `idx_noticias_categoria` (`categoria`),
  CONSTRAINT `fk_noticias_autor` FOREIGN KEY (`id_autor`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_noticias_destacado` CHECK (`destacado` in (0,1)),
  CONSTRAINT `chk_noticias_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabl├│n de noticias / comunicados de la cooperativa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `noticias`
--

LOCK TABLES `noticias` WRITE;
/*!40000 ALTER TABLE `noticias` DISABLE KEYS */;
INSERT INTO `noticias` VALUES (1,'Apertura de la campaña 2025/2026','apertura-campana-2025-2026','Ya está disponible el calendario de entregas para la nueva campaña olivarera. Precio inicial fijado en 0,4500 €/kg.','La almazara abre oficialmente sus puertas para la recepción de aceituna correspondiente a la campaña 2025/2026.\n\nEste año arrancamos con un precio de liquidación inicial de 0,4500 €/kg, sujeto a revisión al cierre de la campaña en función del precio final del aceite en el mercado.\n\nHorario de recepción:\n- Lunes a viernes: 9:00 a 14:00 y 16:00 a 20:00\n- Sábados: 9:00 a 13:00\n\nRecuerda traer tu DNI o carné de socio cooperativista. Los albaranes se generan automáticamente y puedes descargarlos desde la sección \"Mis Entregas\" de tu panel.','comunicado','publica',1,1,4,'2026-04-26 13:35:44','2026-05-01 13:35:44','2026-05-01 13:35:44'),(2,'Asamblea ordinaria de socios','asamblea-ordinaria-socios','Convocatoria de la asamblea ordinaria anual el próximo viernes. Orden del día: balance de campaña y liquidación.','Se convoca a todos los socios cooperativistas a la asamblea ordinaria anual.\n\nORDEN DEL DÍA:\n1. Lectura y aprobación del acta anterior.\n2. Presentación del balance de la campaña 2024/2025.\n3. Aprobación de la liquidación final.\n4. Propuesta de inversión en nueva centrifugadora.\n5. Ruegos y preguntas.\n\nLa asistencia es muy importante: las decisiones se toman con quórum del 30% según los estatutos. Si no puedes asistir, recuerda que puedes delegar tu voto en otro socio.','evento','socios',1,1,4,'2026-04-29 13:35:44','2026-05-01 13:35:44','2026-05-01 13:35:44'),(3,'Nuevo módulo de gestión de stock disponible','nuevo-modulo-stock','Hemos publicado un nuevo módulo en la web para gestionar el inventario de la tienda online con trazabilidad completa.','Como parte de la mejora continua de nuestra plataforma, hemos lanzado un nuevo módulo de gestión de stock para la tienda online.\n\nEl sistema permite:\n- Ver el inventario en tiempo real con alertas de stock bajo.\n- Registrar reposiciones y ajustes manuales con historial completo.\n- Trazabilidad de cada movimiento: entradas, salidas y ajustes.\n\nEsta mejora se traduce en menos roturas de stock y mejor experiencia para nuestros clientes online. Cualquier sugerencia, por favor háznosla llegar.','novedad','publica',0,1,4,'2026-04-21 13:35:44','2026-05-01 13:35:44','2026-05-01 13:35:44');
/*!40000 ALTER TABLE `noticias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedidos`
--

DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedidos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL COMMENT 'FK → usuarios.id (comprador)',
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Momento exacto del pedido',
  `total` decimal(10,2) NOT NULL COMMENT 'Importe total del pedido (€)',
  `estado` enum('pendiente','pagado','procesando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente' COMMENT 'Estado del ciclo de vida del pedido',
  `metodo_pago` varchar(50) DEFAULT NULL COMMENT 'Método: PayPal, tarjeta, transferencia...',
  `direccion_envio` varchar(255) DEFAULT NULL COMMENT 'Dirección completa de entrega',
  `codigo_postal` varchar(10) DEFAULT NULL COMMENT 'CP de entrega',
  `localidad` varchar(80) DEFAULT NULL COMMENT 'Localidad de entrega',
  `notas_cliente` text DEFAULT NULL COMMENT 'Observaciones del cliente',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_usuario` (`id_usuario`),
  KEY `idx_pedidos_estado` (`estado`),
  KEY `idx_pedidos_fecha` (`fecha_pedido`),
  CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_pedidos_total` CHECK (`total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera de pedidos de la tienda online';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedidos`
--

LOCK TABLES `pedidos` WRITE;
/*!40000 ALTER TABLE `pedidos` DISABLE KEYS */;
INSERT INTO `pedidos` VALUES (1,5,'2026-04-25 12:02:23',54.50,'pagado','tarjeta','Calle Mayor 15, 2ºA','23001','Jaén',NULL,'2026-04-25 12:02:23'),(2,5,'2026-04-25 12:02:23',24.90,'pendiente','transferencia','Calle Mayor 15, 2ºA','23001','Jaén',NULL,'2026-04-25 12:02:23'),(3,6,'2026-04-25 13:51:14',30.50,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-25 13:51:14'),(4,6,'2026-04-27 17:51:39',18.50,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-27 17:51:39'),(5,6,'2026-04-27 18:09:01',72.00,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-27 18:09:01'),(6,6,'2026-04-28 17:44:49',42.50,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-28 17:44:49'),(7,7,'2026-04-28 17:57:11',24.90,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-28 17:57:11'),(8,6,'2026-04-30 08:18:16',12.00,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-30 08:18:16'),(9,6,'2026-04-30 08:20:05',1440.00,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-04-30 08:20:05'),(10,14,'2026-01-12 09:32:15',57.00,'pagado','tarjeta','Calle Velázquez 124, 3ºB','28006','Madrid','Por favor, dejar en conserjería si no estoy en casa.','2026-05-02 13:44:45'),(11,15,'2026-02-03 16:45:08',49.80,'pagado','PayPal','Avda. de la Constitución 47, 5ºA','41001','Sevilla',NULL,'2026-05-02 13:44:45'),(12,16,'2026-02-18 08:14:52',51.50,'pagado','tarjeta','Plaza Mayor 8, 1º Dcha.','10003','Cáceres','Es un regalo, incluir tarjeta en blanco si es posible.','2026-05-02 13:44:45'),(13,17,'2026-03-05 12:22:41',49.00,'enviado','transferencia','Calle Hernán Cortés 22, bajo','06800','Mérida',NULL,'2026-05-02 13:44:45'),(14,18,'2026-04-21 16:07:33',47.90,'pendiente','transferencia','Paseo de San Francisco 14, 2ºD','06001','Badajoz','Pendiente de realizar transferencia bancaria.','2026-05-02 13:44:45'),(15,6,'2026-05-04 17:35:15',276.00,'pagado','Pago en cooperativa',NULL,NULL,NULL,NULL,'2026-05-04 17:35:15'),(16,6,'2026-05-06 16:41:46',14.50,'pagado','Stripe (tarjeta)',NULL,NULL,NULL,NULL,'2026-05-06 16:41:46'),(17,6,'2026-05-06 16:48:37',18.50,'pagado','Stripe (tarjeta)',NULL,NULL,NULL,NULL,'2026-05-06 16:48:37');
/*!40000 ALTER TABLE `pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre del producto',
  `slug` varchar(120) DEFAULT NULL COMMENT 'URL amigable para SEO',
  `variedad` enum('Picual','Arbequina','Hojiblanca','Coupage','Manzanilla','Verdial','Cornezuelo','Morisca','Carrasqueña') NOT NULL COMMENT 'Variedad de aceituna (incluye autóctonas de Extremadura)',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción para la ficha de producto',
  `precio` decimal(8,2) NOT NULL COMMENT 'Precio unitario con IVA (€)',
  `stock` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Unidades disponibles',
  `stock_minimo` int(10) unsigned NOT NULL DEFAULT 10 COMMENT 'Umbral por debajo del cual se muestra "pocas unidades"',
  `imagen` varchar(255) NOT NULL DEFAULT 'default_aceite.jpg' COMMENT 'Nombre del archivo de imagen',
  `activo` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1=visible, 0=oculto en tienda',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_productos_variedad` (`variedad`),
  KEY `idx_productos_activo` (`activo`),
  KEY `idx_productos_precio` (`precio`),
  CONSTRAINT `chk_productos_precio` CHECK (`precio` > 0),
  CONSTRAINT `chk_productos_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de productos de la tienda online';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Garrafa AOVE 5L','garrafa-aove-5l','Picual','Aceite de Oliva Virgen Extra. Extracción en frío, primera prensa. Sabor intenso y afrutado con notas de almendra verde.',42.50,498,30,'aceite-picual.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45'),(2,'Botella Cristal Premium 500ml','botella-premium-500ml','Arbequina','Aceite suave y delicado. Ideal para crudo, ensaladas y repostería fina. Botella de vidrio oscuro.',12.00,0,20,'aceite-arbequina.png',1,'2026-04-25 12:02:23','2026-05-04 17:35:15'),(3,'Pack Degustación 3x250ml','pack-degustacion-3x250ml','Coupage','Selección de tres variedades en botellas de 250ml. Perfecto para regalo o para descubrir sabores.',24.90,77,15,'aceite-coupage.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45'),(4,'Garrafa Hojiblanca 2L','garrafa-hojiblanca-2l','Hojiblanca','Aceite de oliva con característico sabor dulce y ligero picor. Perfecto para cocinar y freír.',18.50,195,25,'aceite-hojiblanca.png',1,'2026-04-25 12:02:23','2026-05-06 16:48:37'),(5,'Manzanilla Cacereña 500ml','manzanilla-cacerena-500ml','Manzanilla','AOVE de Manzanilla Cacereña, la variedad reina del olivar de Cáceres. Afrutado intenso con notas de hierba fresca, plátano verde y un final almendrado. Recolección temprana.',14.50,196,20,'aceite-manzanilla.png',1,'2026-04-25 12:02:23','2026-05-06 16:41:46'),(6,'Verdial de Badajoz 2L','verdial-badajoz-2l','Verdial','Verdial de Badajoz, cultivar tradicional de La Serena. Aceite dulce y aromático, baja amargura, sin apenas picante. Ideal para uso diario y para los paladares más suaves.',22.00,119,15,'aceite-verdial.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45'),(7,'Cornezuelo Tierra de Barros 500ml','cornezuelo-tierra-de-barros-500ml','Cornezuelo','Aceite elaborado con la rústica variedad Cornezuelo, autóctona de Tierra de Barros. Bouquet aromático intenso, ligero picor en garganta, notas vegetales y de tomatera.',16.00,89,10,'aceite-cornezuelo.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45'),(8,'Morisca DOP Monterrubio 750ml','morisca-dop-monterrubio-750ml','Morisca','Morisca extremeña amparada por la D.O.P. Aceite de Monterrubio. Equilibrio perfecto entre amargor y picante, con notas frutales, hierba recién cortada y un toque a hoja de olivo.',18.90,74,8,'aceite-morisca.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45'),(9,'Carrasqueña Centenaria 250ml','carrasquena-centenaria-250ml','Carrasqueña','AOVE prensado en frío de olivos centenarios de La Vera. Variedad Carrasqueña, rústica y aromática. Notas a almendra verde, manzana y hierbas del campo. Edición limitada de cosecha temprana.',13.50,59,6,'aceite-carrasquena.png',1,'2026-04-25 12:02:23','2026-05-02 13:44:45');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dni` varchar(15) DEFAULT NULL COMMENT 'NIF, NIE o CIF del usuario',
  `nombre` varchar(60) NOT NULL COMMENT 'Nombre de pila',
  `apellidos` varchar(100) DEFAULT NULL COMMENT 'Apellidos (puede ser NULL para empresas)',
  `email` varchar(180) NOT NULL COMMENT 'Email de acceso — usado como login',
  `password` varchar(255) NOT NULL COMMENT 'Hash bcrypt generado con password_hash()',
  `telefono` varchar(15) DEFAULT NULL COMMENT 'Teléfono de contacto (formato libre)',
  `rol` enum('admin','operario','socio','cliente') NOT NULL DEFAULT 'cliente' COMMENT 'admin|operario|socio|cliente ÔÇö nivel de acceso',
  `activo` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1=activo, 0=desactivado (soft-delete)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha de alta automática',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última modificación (auto-actualizado)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `dni` (`dni`),
  KEY `idx_usuarios_rol` (`rol`),
  KEY `idx_usuarios_email` (`email`),
  KEY `idx_usuarios_dni` (`dni`),
  KEY `idx_usuarios_activo_rol` (`activo`,`rol`),
  CONSTRAINT `chk_usuarios_activo` CHECK (`activo` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema: socios, clientes y administradores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'11111111A','Paco','García López','paco@email.com','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600111222','socio',1,'2026-04-25 12:02:23','2026-04-25 12:02:23'),(2,'22222222B','María','Fernández Ruiz','maria@email.com','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600333444','socio',1,'2026-04-25 12:02:23','2026-04-25 12:02:23'),(3,'33333333C','Antonio','Martínez López','antonio@email.com','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600555666','socio',1,'2026-04-25 12:02:23','2026-04-25 12:02:23'),(4,'00000000X','Admin','Principal','admin@almazara.es','$2y$10$3Jfpz5yQe/tem/1V5XFfm.zKx89VA5yYrPX5bvBpn4JbnEdpEOvgi','600000000','admin',1,'2026-04-25 12:02:23','2026-04-28 08:28:03'),(5,'44444444D','Laura','Sánchez Molina','laura@email.com','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600777888','cliente',1,'2026-04-25 12:02:23','2026-04-28 18:00:24'),(6,'99999999R','Juan','Carrasco Ruiz','jccanoc01@educarex.es','$2y$10$scFSykZbZwliEicZz9Yreeo0T8.MVGnnz3C1EHApzoE7OylSW9cHe','683642407','socio',1,'2026-04-25 13:51:06','2026-04-28 08:28:39'),(7,'20542271L','Carlos','carrasc','jccanoc09@gmail.com','$2y$10$zcw9hRAAe2tUTBC3T9S3W.AuIPxgPapMZTuoRyfaVppRaWLrG.Dfy','623632214','operario',1,'2026-04-28 17:56:50','2026-05-01 13:58:44'),(8,'55555555E','Pepe','Romero Núñez','operario@almazara.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600999000','operario',1,'2026-04-30 07:49:36','2026-05-01 13:55:31'),(9,'10000001A','José Antonio','Sánchez Carrasco','jose.sanchez@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','611234567','socio',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(10,'20000002B','María del Carmen','Vargas Romero','mcarmen.vargas@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','622345678','socio',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(11,'30000003C','Francisco Javier','Moreno Cordero','fjavier.moreno@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','633456789','socio',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(12,'40000004D','Juana','Núñez Calderón','juana.nunez@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','644567890','socio',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(13,'50000005E','Manuel','Casillas Jiménez','manuel.casillas@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','655678901','socio',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(14,'60000006F','Rosa María','Galán Pizarro','rosa.galan@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','666789012','cliente',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(15,'70000007G','Diego','Pacheco Bermúdez','diego.pacheco@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','677890123','cliente',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(16,'80000008H','Carmen','Cáceres Mendoza','carmen.caceres@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','688901234','cliente',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(17,'90000009J','Ignacio','Rodríguez Tena','ignacio.rodriguez@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','699012345','cliente',1,'2026-05-02 13:44:45','2026-05-02 13:44:45'),(18,'00000010K','Pilar','Donoso Galindo','pilar.donoso@demo.coopsjb.es','$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K','600123456','cliente',1,'2026-05-02 13:44:45','2026-05-02 13:44:45');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_campanas_resumen`
--

DROP TABLE IF EXISTS `v_campanas_resumen`;
/*!50001 DROP VIEW IF EXISTS `v_campanas_resumen`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_campanas_resumen` AS SELECT
 1 AS `id`,
  1 AS `codigo`,
  1 AS `fecha_inicio`,
  1 AS `fecha_fin`,
  1 AS `precio_por_kilo`,
  1 AS `estado`,
  1 AS `notas`,
  1 AS `socios_participantes`,
  1 AS `total_entregas`,
  1 AS `total_kilos`,
  1 AS `total_litros`,
  1 AS `rendimiento_medio`,
  1 AS `liquidacion_total` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_estadisticas_campana`
--

DROP TABLE IF EXISTS `v_estadisticas_campana`;
/*!50001 DROP VIEW IF EXISTS `v_estadisticas_campana`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_estadisticas_campana` AS SELECT
 1 AS `campana`,
  1 AS `socios_participantes`,
  1 AS `total_entregas`,
  1 AS `total_kilos`,
  1 AS `total_litros`,
  1 AS `rendimiento_medio`,
  1 AS `primera_entrega`,
  1 AS `ultima_entrega` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_historial_entregas`
--

DROP TABLE IF EXISTS `v_historial_entregas`;
/*!50001 DROP VIEW IF EXISTS `v_historial_entregas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_historial_entregas` AS SELECT
 1 AS `id_entrega`,
  1 AS `fecha_entrega`,
  1 AS `campana`,
  1 AS `kilos_aceituna`,
  1 AS `rendimiento`,
  1 AS `litros_aceite`,
  1 AS `observaciones`,
  1 AS `created_at`,
  1 AS `id_socio`,
  1 AS `dni`,
  1 AS `nombre`,
  1 AS `apellidos`,
  1 AS `nombre_completo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_noticias_publicas`
--

DROP TABLE IF EXISTS `v_noticias_publicas`;
/*!50001 DROP VIEW IF EXISTS `v_noticias_publicas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_noticias_publicas` AS SELECT
 1 AS `id`,
  1 AS `titulo`,
  1 AS `slug`,
  1 AS `resumen`,
  1 AS `contenido`,
  1 AS `categoria`,
  1 AS `visibilidad`,
  1 AS `destacado`,
  1 AS `fecha_publicacion`,
  1 AS `created_at`,
  1 AS `id_autor`,
  1 AS `autor_nombre` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_resumen_socios`
--

DROP TABLE IF EXISTS `v_resumen_socios`;
/*!50001 DROP VIEW IF EXISTS `v_resumen_socios`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_resumen_socios` AS SELECT
 1 AS `id_socio`,
  1 AS `dni`,
  1 AS `nombre_completo`,
  1 AS `email`,
  1 AS `telefono`,
  1 AS `activo`,
  1 AS `total_fincas`,
  1 AS `total_hectareas`,
  1 AS `campana`,
  1 AS `total_entregas`,
  1 AS `total_kilos`,
  1 AS `total_litros`,
  1 AS `rendimiento_medio` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_stock_estado`
--

DROP TABLE IF EXISTS `v_stock_estado`;
/*!50001 DROP VIEW IF EXISTS `v_stock_estado`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_stock_estado` AS SELECT
 1 AS `id`,
  1 AS `nombre`,
  1 AS `slug`,
  1 AS `variedad`,
  1 AS `precio`,
  1 AS `stock`,
  1 AS `stock_minimo`,
  1 AS `activo`,
  1 AS `estado_stock`,
  1 AS `ultima_reposicion` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `visitas`
--

DROP TABLE IF EXISTS `visitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(180) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_visita` date NOT NULL,
  `hora_visita` time NOT NULL,
  `num_personas` tinyint(3) unsigned NOT NULL,
  `tipo_visita` enum('cata','almazara','completa') NOT NULL DEFAULT 'completa' COMMENT 'cata=solo cata · almazara=solo recorrido · completa=ambas',
  `comentarios` text DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cancelada','realizada') NOT NULL DEFAULT 'pendiente' COMMENT 'Ciclo de vida: el admin confirma o cancela; "realizada" tras la visita',
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_visita_usuario` (`id_usuario`),
  KEY `idx_visitas_fecha` (`fecha_visita`,`hora_visita`),
  KEY `idx_visitas_estado` (`estado`),
  KEY `idx_visitas_email` (`email`),
  CONSTRAINT `fk_visita_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_visitas_personas` CHECK (`num_personas` between 1 and 10)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reservas de visita guiada a la almazara (oleoturismo)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitas`
--

LOCK TABLES `visitas` WRITE;
/*!40000 ALTER TABLE `visitas` DISABLE KEYS */;
INSERT INTO `visitas` VALUES (1,'Carmen Rodríguez','carmen.rodriguez@example.com','612 345 678','2026-04-28','12:00:00',4,'completa','Venimos en familia con dos niños de 8 y 11 años. ¿Hay alguna parte de la visita inadecuada para ellos?','realizada',NULL,'2026-04-25 21:07:33','2026-04-27 18:06:22'),(2,'Javier Mendoza','jmendoza@example.com','655 112 233','2026-05-02','17:00:00',2,'cata',NULL,'cancelada',NULL,'2026-04-25 21:07:33','2026-04-28 17:42:12'),(3,'Bodega \"El Roble\" S.L.','eventos@bodegaelroble.es','924 555 100','2026-04-11','10:00:00',8,'completa','Visita corporativa para nuestro equipo de catadores.','realizada',NULL,'2026-04-25 21:07:33','2026-04-25 21:07:33'),(4,'Juan','jccanoc01@educarex.es',NULL,'2026-04-29','19:00:00',2,'cata',NULL,'realizada',NULL,'2026-04-25 21:14:01','2026-04-25 21:23:03'),(5,'Juan','jccanoc01@educarex.es',NULL,'2026-04-29','12:00:00',2,'almazara',NULL,'cancelada',NULL,'2026-04-25 21:22:48','2026-04-28 17:41:31'),(6,'Juan','jccanoc09@gmail.com','632321213','2026-04-30','12:00:00',4,'cata','hola','confirmada',NULL,'2026-04-28 17:54:51','2026-04-28 18:04:39'),(7,'Juan','jccanoc09@gmail.com','63656565','2026-05-22','10:00:00',2,'completa',NULL,'confirmada',NULL,'2026-05-04 17:18:05','2026-05-04 17:30:51');
/*!40000 ALTER TABLE `visitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votacion_opciones`
--

DROP TABLE IF EXISTS `votacion_opciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votacion_opciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_votacion` int(10) unsigned NOT NULL,
  `texto` varchar(120) NOT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_opciones_votacion` (`id_votacion`,`orden`),
  CONSTRAINT `fk_opcion_votacion` FOREIGN KEY (`id_votacion`) REFERENCES `votaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opciones que el socio puede elegir en cada votación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votacion_opciones`
--

LOCK TABLES `votacion_opciones` WRITE;
/*!40000 ALTER TABLE `votacion_opciones` DISABLE KEYS */;
INSERT INTO `votacion_opciones` VALUES (1,1,'Sí, aprobar la inversión',1),(2,1,'No, mantener la almazara actual',2),(3,1,'Abstención',3),(4,2,'Cambiar al proveedor ecológico',1),(5,2,'Mantener proveedor actual',2),(6,3,'carlos',1),(7,3,'ivan',2),(8,4,'antonio',1),(9,4,'carlos',2),(10,4,'toni',3),(11,5,'s',1),(12,5,'a',2);
/*!40000 ALTER TABLE `votacion_opciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votaciones`
--

DROP TABLE IF EXISTS `votaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `estado` enum('borrador','abierta','cerrada') NOT NULL DEFAULT 'borrador' COMMENT 'borrador=admin la prepara · abierta=socios pueden votar · cerrada=resultados visibles',
  `quorum_minimo` tinyint(3) unsigned NOT NULL DEFAULT 30 COMMENT 'Porcentaje de socios necesario para que la decisión sea válida',
  `id_admin_creador` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_votacion_admin` (`id_admin_creador`),
  KEY `idx_votacion_estado` (`estado`),
  KEY `idx_votacion_fechas` (`fecha_inicio`,`fecha_fin`),
  CONSTRAINT `fk_votacion_admin` FOREIGN KEY (`id_admin_creador`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_votacion_fechas` CHECK (`fecha_fin` > `fecha_inicio`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Votaciones cooperativas (asambleas, decisiones colectivas)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votaciones`
--

LOCK TABLES `votaciones` WRITE;
/*!40000 ALTER TABLE `votaciones` DISABLE KEYS */;
INSERT INTO `votaciones` VALUES (1,'Inversión en nueva almazara','Propuesta para adquirir una almazara de última generación con capacidad de 1500 kg/h y separadoras inox. Inversión estimada: 250.000 €, financiada al 60 % con cooperativa y 40 % con préstamo ICO. ¿Aprobamos la inversión?','2026-04-24 15:58:32','2026-05-09 15:58:32','abierta',30,4,'2026-04-25 13:58:32'),(2,'Cambio de proveedor de envases','Migrar del proveedor actual a uno con certificación ecológica europea. El coste sube un 8 % pero mejora la imagen de marca y nos abre acceso a la línea bio.','2026-03-26 15:58:32','2026-04-24 15:58:32','cerrada',30,4,'2026-04-25 13:58:32'),(3,'Nuevo presidente','Votaciones para elegir a un nuevo presidente','2026-04-25 23:02:00','2026-05-03 23:02:00','abierta',30,4,'2026-04-25 21:03:08'),(4,'Nuevo secretario','jhapihfauhfa','2026-04-28 20:02:00','2026-05-07 20:02:00','cerrada',30,4,'2026-04-28 18:03:25'),(5,'Nuevo presidente','sss','2026-05-04 19:29:00','2026-05-11 19:29:00','abierta',30,4,'2026-05-04 17:30:15');
/*!40000 ALTER TABLE `votaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votos`
--

DROP TABLE IF EXISTS `votos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votos` (
  `id_votacion` int(10) unsigned NOT NULL,
  `id_socio` int(10) unsigned NOT NULL,
  `id_opcion` int(10) unsigned NOT NULL,
  `fecha_voto` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_votacion`,`id_socio`),
  KEY `fk_voto_socio` (`id_socio`),
  KEY `idx_voto_opcion` (`id_opcion`),
  CONSTRAINT `fk_voto_opcion` FOREIGN KEY (`id_opcion`) REFERENCES `votacion_opciones` (`id`),
  CONSTRAINT `fk_voto_socio` FOREIGN KEY (`id_socio`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_voto_votacion` FOREIGN KEY (`id_votacion`) REFERENCES `votaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Voto emitido. PK compuesta = un socio sólo puede votar una vez';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votos`
--

LOCK TABLES `votos` WRITE;
/*!40000 ALTER TABLE `votos` DISABLE KEYS */;
INSERT INTO `votos` VALUES (1,6,1,'2026-04-25 21:03:26'),(2,1,4,'2026-04-25 13:58:32'),(2,2,4,'2026-04-25 13:58:32'),(2,3,5,'2026-04-25 13:58:32'),(2,5,4,'2026-04-25 13:58:32'),(3,6,7,'2026-04-25 21:03:31'),(4,6,9,'2026-04-28 18:06:28'),(5,6,11,'2026-05-04 17:33:55');
/*!40000 ALTER TABLE `votos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'cooperativa_sjb'
--

--
-- Dumping routines for database 'cooperativa_sjb'
--

--
-- Final view structure for view `v_campanas_resumen`
--

/*!50001 DROP VIEW IF EXISTS `v_campanas_resumen`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_campanas_resumen` AS select `c`.`id` AS `id`,`c`.`codigo` AS `codigo`,`c`.`fecha_inicio` AS `fecha_inicio`,`c`.`fecha_fin` AS `fecha_fin`,`c`.`precio_por_kilo` AS `precio_por_kilo`,`c`.`estado` AS `estado`,`c`.`notas` AS `notas`,count(distinct `e`.`id_socio`) AS `socios_participantes`,count(`e`.`id`) AS `total_entregas`,coalesce(sum(`e`.`kilos_aceituna`),0) AS `total_kilos`,coalesce(sum(`e`.`litros_aceite`),0) AS `total_litros`,round(coalesce(avg(`e`.`rendimiento`),0),2) AS `rendimiento_medio`,round(coalesce(sum(`e`.`kilos_aceituna`),0) * `c`.`precio_por_kilo`,2) AS `liquidacion_total` from (`campanas` `c` left join `entregas` `e` on(`e`.`id_campana` = `c`.`id`)) group by `c`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_estadisticas_campana`
--

/*!50001 DROP VIEW IF EXISTS `v_estadisticas_campana`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_estadisticas_campana` AS select `e`.`campana` AS `campana`,count(distinct `e`.`id_socio`) AS `socios_participantes`,count(`e`.`id`) AS `total_entregas`,coalesce(sum(`e`.`kilos_aceituna`),0) AS `total_kilos`,coalesce(sum(`e`.`litros_aceite`),0) AS `total_litros`,round(avg(`e`.`rendimiento`),2) AS `rendimiento_medio`,min(`e`.`fecha_entrega`) AS `primera_entrega`,max(`e`.`fecha_entrega`) AS `ultima_entrega` from `entregas` `e` group by `e`.`campana` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_historial_entregas`
--

/*!50001 DROP VIEW IF EXISTS `v_historial_entregas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_historial_entregas` AS select `e`.`id` AS `id_entrega`,`e`.`fecha_entrega` AS `fecha_entrega`,`e`.`campana` AS `campana`,`e`.`kilos_aceituna` AS `kilos_aceituna`,`e`.`rendimiento` AS `rendimiento`,`e`.`litros_aceite` AS `litros_aceite`,`e`.`observaciones` AS `observaciones`,`e`.`created_at` AS `created_at`,`u`.`id` AS `id_socio`,`u`.`dni` AS `dni`,`u`.`nombre` AS `nombre`,`u`.`apellidos` AS `apellidos`,concat(`u`.`nombre`,' ',coalesce(`u`.`apellidos`,'')) AS `nombre_completo` from (`entregas` `e` join `usuarios` `u` on(`e`.`id_socio` = `u`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_noticias_publicas`
--

/*!50001 DROP VIEW IF EXISTS `v_noticias_publicas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_noticias_publicas` AS select `n`.`id` AS `id`,`n`.`titulo` AS `titulo`,`n`.`slug` AS `slug`,`n`.`resumen` AS `resumen`,`n`.`contenido` AS `contenido`,`n`.`categoria` AS `categoria`,`n`.`visibilidad` AS `visibilidad`,`n`.`destacado` AS `destacado`,`n`.`fecha_publicacion` AS `fecha_publicacion`,`n`.`created_at` AS `created_at`,`n`.`id_autor` AS `id_autor`,coalesce(concat(`u`.`nombre`,' ',coalesce(`u`.`apellidos`,'')),'Cooperativa') AS `autor_nombre` from (`noticias` `n` left join `usuarios` `u` on(`u`.`id` = `n`.`id_autor`)) where `n`.`activo` = 1 and `n`.`fecha_publicacion` <= current_timestamp() */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resumen_socios`
--

/*!50001 DROP VIEW IF EXISTS `v_resumen_socios`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_resumen_socios` AS select `u`.`id` AS `id_socio`,`u`.`dni` AS `dni`,concat(`u`.`nombre`,' ',coalesce(`u`.`apellidos`,'')) AS `nombre_completo`,`u`.`email` AS `email`,`u`.`telefono` AS `telefono`,`u`.`activo` AS `activo`,(select count(0) from `fincas` `f` where `f`.`id_socio` = `u`.`id`) AS `total_fincas`,(select coalesce(sum(`f`.`hectareas`),0) from `fincas` `f` where `f`.`id_socio` = `u`.`id`) AS `total_hectareas`,`e`.`campana` AS `campana`,count(`e`.`id`) AS `total_entregas`,coalesce(sum(`e`.`kilos_aceituna`),0) AS `total_kilos`,coalesce(sum(`e`.`litros_aceite`),0) AS `total_litros`,round(avg(`e`.`rendimiento`),2) AS `rendimiento_medio` from (`usuarios` `u` left join `entregas` `e` on(`e`.`id_socio` = `u`.`id`)) where `u`.`rol` = 'socio' group by `u`.`id`,`e`.`campana` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_stock_estado`
--

/*!50001 DROP VIEW IF EXISTS `v_stock_estado`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `v_stock_estado` AS select `p`.`id` AS `id`,`p`.`nombre` AS `nombre`,`p`.`slug` AS `slug`,`p`.`variedad` AS `variedad`,`p`.`precio` AS `precio`,`p`.`stock` AS `stock`,`p`.`stock_minimo` AS `stock_minimo`,`p`.`activo` AS `activo`,case when `p`.`stock` = 0 then 'agotado' when `p`.`stock` <= `p`.`stock_minimo` then 'bajo' when `p`.`stock` <= `p`.`stock_minimo` * 2 then 'medio' else 'ok' end AS `estado_stock`,(select max(`m`.`created_at`) from `movimientos_stock` `m` where `m`.`id_producto` = `p`.`id` and `m`.`tipo` = 'entrada') AS `ultima_reposicion` from `productos` `p` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-06 20:44:28
