# MEMORIA DEL PROYECTO FINAL DE CICLO

## Cooperativa San Juan Bautista — Plataforma Web Integral para una Almazara

**Ciclo Formativo:** Administración de Sistemas Informáticos en Red (ASIR) / Desarrollo de Aplicaciones Web (DAW)
**Familia Profesional:** Informática y Comunicaciones
**Curso académico:** 2025/2026

---

## Índice

1. [Introducción](#1-introducción)
2. [Desarrollo del proyecto](#2-desarrollo-del-proyecto)
3. [Dificultades encontradas y posibles soluciones](#3-dificultades-encontradas-y-posibles-soluciones)
4. [Posibles mejoras](#4-posibles-mejoras)
5. [Resultados](#5-resultados)
6. [Conclusión](#6-conclusión)

---

## 1. Introducción

### 1.1. Descripción de la entidad

La **Cooperativa San Juan Bautista** es una almazara situada en Herrera del Duque (Badajoz), en la comarca de la Siberia extremeña, fundada en 1952. Su actividad económica principal consiste en la producción y comercialización de Aceite de Oliva Virgen Extra (AOVE) a partir de las cosechas de sus socios cooperativistas, gestionando la trazabilidad desde la finca catastral hasta la venta final al consumidor.

La cooperativa trabaja con variedades clásicas (Picual, Arbequina, Hojiblanca, Coupage) y autóctonas extremeñas (Manzanilla Cacereña, Verdial de Badajoz, Cornezuelo, Morisca y Carrasqueña), incluyendo aceites amparados por la **D.O.P. Aceite de Monterrubio**.

### 1.2. Justificación del proyecto

El proyecto nace de la necesidad real, manifestada por la cooperativa, de **digitalizar tres flujos de trabajo** que tradicionalmente se han gestionado en papel o en hojas de cálculo aisladas:

- **Gestión interna de la almazara**: registro de entregas de aceituna por parte de los socios, control de campañas oleícolas, generación de albaranes y trazabilidad por finca.
- **Gobernanza cooperativista**: votaciones electrónicas vinculantes y publicación de noticias internas.
- **Comercialización online**: catálogo público de productos, tienda con carrito persistente, generación automática de facturas y reservas de visitas guiadas a la almazara.

Se ha optado por una solución web unificada en lugar de tres sistemas independientes para garantizar **una única fuente de verdad** sobre los datos de socios, productos y entregas, facilitando la generación de estadísticas globales para la junta rectora.

### 1.3. Áreas de trabajo

El proyecto se ha estructurado en torno a tres áreas técnicas:

- **Frontend**: HTML5 semántico con Bootstrap 5, CSS personalizado en [assets/css/estilos.css](../assets/css/estilos.css) y [assets/css/admin.css](../assets/css/admin.css), JavaScript vanilla con la API Fetch, gráficas con **Chart.js** y diálogos modales con **SweetAlert2**. Se ha cuidado la accesibilidad (atributos ARIA, *skip-link* WCAG 2.4.1, contraste AA) y la experiencia móvil (PWA con `manifest.json` y *service worker* en [sw.js](../sw.js)).
- **Backend**: PHP 8 con paradigma orientado a procesos y separación por carpetas funcionales (`auth/`, `api/`, `admin/`, `core/`). Acceso a datos mediante **PDO** con *prepared statements* nativos (definidos en [config/db.php](../config/db.php)). Generación de PDFs (facturas y albaranes) con la librería **FPDF** ubicada en [vendor/fpdf/](../vendor/fpdf/).
- **Base de datos**: MySQL 8 / MariaDB sobre motor **InnoDB**, con `utf8mb4_unicode_ci`, restricciones `CHECK`, columnas generadas (`STORED`), claves foráneas con políticas `CASCADE` / `RESTRICT` / `SET NULL` y vistas SQL (`v_resumen_socios`, `v_historial_entregas`, `v_estadisticas_campana`). El esquema completo se encuentra en [database/schema/cooperativa_sjb.sql](../database/schema/cooperativa_sjb.sql).

---

## 2. Desarrollo del proyecto

### 2.1. Arquitectura general

La aplicación sigue un **patrón MVC simplificado** propio de PHP procedural, donde:

- **Modelo**: encapsulado en consultas PDO contra la base de datos `cooperativa_sjb`, con la lógica de validación e integridad delegada al motor (constraints, ENUMs, columnas generadas).
- **Vista**: archivos `.php` con HTML embebido en la raíz del proyecto (`tienda.php`, `panel_socio.php`, `mis_entregas.php`, `votaciones.php`, `calculadora.php`) y en el subdirectorio `admin/`.
- **Controlador**: scripts en `auth/`, `api/` y `core/` que reciben peticiones POST (formularios o JSON), validan, ejecutan la lógica de negocio y redirigen o devuelven JSON.

### 2.2. Estructura de carpetas

```
TFG/
├── admin/              → Panel de administración (campañas, stock, usuarios,
│                         votaciones, calendario, visitas, noticias)
├── api/                → Endpoints AJAX que devuelven JSON
│                         (carrito, votar, reservar_visita, actualizar_rol,
│                         estadisticas, promocionar_socio)
├── assets/             → CSS, imágenes de productos e iconografía
├── auth/               → login, logout, registro y recuperación de contraseña
├── config/             → db.php (conexión PDO singleton) y email.php
├── core/               → Lógica de negocio crítica
│                         (procesar_compra, generar_factura, generar_albaran,
│                         mailer, notificaciones)
├── database/
│   ├── schema/         → Esquema unificado v3.0 de producción
│   └── migrations/     → Migraciones incrementales numeradas
├── includes/           → Cabecera, navbar (público / socio / admin / operario),
│                         footer reutilizables
├── logs/               → Logs de correos enviados
├── vendor/fpdf/        → Librería FPDF (sin Composer)
├── index.php           → Landing page pública
├── tienda.php          → Catálogo + offcanvas del carrito
├── panel_socio.php     → Dashboard del socio
├── calculadora.php     → Calculadora de rendimiento del olivar
├── votaciones.php      → Listado de votaciones para socios
├── mis_entregas.php    → Histórico de entregas y liquidación
├── operario.php        → Panel del operario
└── manifest.json + sw.js → Configuración PWA
```

### 2.3. Capa de acceso a datos (PDO)

La conexión se centraliza en un *singleton* en [config/db.php](../config/db.php), instanciando `PDO` una única vez por petición HTTP con los siguientes atributos:

- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`: las consultas fallidas lanzan `PDOException` y permiten *try/catch* limpios.
- `PDO::ATTR_EMULATE_PREPARES => false`: fuerza *prepared statements* nativos en MySQL, lo que blinda el sistema frente a inyección SQL incluso en *queries* poco convencionales.
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`: simplifica la lectura en PHP (`$row['nombre']` en lugar de doble índice).
- `PDO::ATTR_TIMEOUT => 5`: evita peticiones colgadas si MySQL no responde.

Todas las consultas que reciben datos de usuario emplean *placeholders nominales* (`:id`, `:email`), nunca interpolación de cadenas.

### 2.4. Partes técnicamente relevantes

#### 2.4.1. Transacciones de compra

El proceso de compra, implementado en [core/procesar_compra.php](../core/procesar_compra.php), constituye uno de los puntos críticos del proyecto. Se ejecuta dentro de una **transacción ACID** mediante `beginTransaction()` / `commit()` / `rollBack()` y abarca cuatro operaciones que deben ser atómicas:

1. Validación servidor-side de los precios contra la BD (nunca se confía en `$_SESSION['carrito']`, ya que un atacante podría haberla manipulado).
2. Inserción de la cabecera del pedido en `pedidos`.
3. Inserción de las líneas en `lineas_pedido` (cuyo `subtotal` se calcula como **columna generada `STORED`**).
4. Decremento de stock en `productos` con la cláusula defensiva `WHERE stock >= :cantidad_min`, y registro de cada salida en `movimientos_stock` para mantener una pista de auditoría.

Si en cualquier punto la sentencia `UPDATE` devuelve `rowCount() === 0` (indicio de que otro pedido concurrente se llevó las últimas unidades), se lanza una excepción que dispara `rollBack()` y la base de datos queda en su estado original. El vaciado del carrito de sesión se realiza **después** del `commit` para no perder los datos en caso de fallo.

#### 2.4.2. Vistas SQL

Para evitar duplicar JOINs complejos en distintas pantallas del back-office, se han definido tres vistas en el esquema (líneas 333-430 de [cooperativa_sjb.sql](../database/schema/cooperativa_sjb.sql)):

- `v_resumen_socios`: cruza `usuarios`, `fincas` y `entregas` para devolver, por cada socio y campaña, el total de fincas, hectáreas, kilos entregados, litros producidos y rendimiento medio.
- `v_historial_entregas`: detalle plano de entregas con datos de socio (DNI, nombre completo) ya unidos.
- `v_estadisticas_campana`: agregaciones globales por campaña oleícola para alimentar las gráficas comparativas del dashboard.

Estas vistas se consultan como tablas normales y simplifican notablemente el código PHP.

#### 2.4.3. Columnas generadas

La tabla `entregas` define dos columnas calculadas por el motor:

- `campana VARCHAR(9) GENERATED ALWAYS AS (...) STORED`: deriva la campaña oleícola (`2025/2026`) a partir de la fecha de entrega aplicando la regla "si el mes ≥ 7, campaña = año/(año+1); en otro caso (año−1)/año".
- `litros_aceite DECIMAL(10,2) GENERATED ALWAYS AS (ROUND(((kilos_aceituna * rendimiento) / 100) / 0.916, 2)) STORED`: aplica la fórmula física estándar del sector (densidad media del aceite de oliva: 0,916 kg/L a 20 °C).

La estrategia `STORED` permite indexar estas columnas y emplearlas en `JOINs` y `WHERE`, evitando recálculos repetidos en cada consulta.

#### 2.4.4. Sistema de roles y seguridad

La aplicación distingue cuatro roles (`cliente`, `socio`, `operario`, `admin`) declarados como `ENUM` en `usuarios.rol`. Cada controlador valida el rol mediante triple barrera:

1. Comprobación de `$_SESSION['rol']` al inicio del script (si no coincide → `header('Location: ...')`).
2. Cláusula `WHERE id_socio = :id` o `WHERE id_usuario = :id` en las consultas, de modo que un usuario que tantee IDs ajenos reciba "no encontrado" en lugar de datos privados.
3. Validación adicional en la API (re-verificación de rol, *whitelist* explícita en lugar de confiar en el ENUM de MySQL).

La autenticación emplea `password_hash()` (bcrypt, `$2y$10$...`) y `password_verify()`. Tras un login exitoso se invoca `session_regenerate_id(true)` para mitigar **session fixation**. Todos los formularios sensibles incorporan un **token CSRF** generado con `random_bytes(32)` y comparado con `hash_equals()` (comparación en tiempo constante frente a *timing attacks*).

#### 2.4.5. Generación de PDFs

Los albaranes de entrega ([core/generar_albaran.php](../core/generar_albaran.php)) y las facturas de pedido ([core/generar_factura.php](../core/generar_factura.php)) se generan en tiempo real con **FPDF**, una librería ligera que no requiere Composer. La factura aplica el tipo reducido del IVA (10 %, según el art. 91 de la Ley 37/1992 para productos alimenticios) y respeta la paleta corporativa de la cooperativa (verde oliva `#2C4C3B`, dorado `#D4AF37`).

El control de acceso se aplica en el propio script: si el solicitante no es admin, se añade dinámicamente la condición `AND p.id_usuario = :id_usuario` al SQL, garantizando que un usuario no pueda descargar la factura de otro mediante manipulación de la URL.

---

## 3. Dificultades encontradas y posibles soluciones

### 3.1. Refactorización de rutas tras la reorganización por carpetas

**Problema:** Inicialmente todos los archivos PHP se ubicaban en la raíz del proyecto. Al madurar la aplicación se decidió segmentar el código en carpetas funcionales (`auth/`, `api/`, `admin/`, `core/`) para mejorar la mantenibilidad. Esto rompió todas las rutas relativas (`require_once 'db.php'`, `header('Location: index.php')`, `<form action="login.php">`, referencias CSS, etc.) y generó *fatal errors* al mover los includes.

**Solución adoptada:**
- Sustituir las rutas relativas por **rutas absolutas basadas en `__DIR__`** en todos los `require_once`. Por ejemplo: `require_once __DIR__ . '/../config/db.php';` en [auth/login.php](../auth/login.php).
- Para los `header('Location: ...')` se utilizan rutas relativas con `..` desde la subcarpeta hacia la raíz (`header('Location: ../index.php')`).
- En los `includes/` (`header.php`, `navbar.php`) se introdujo la variable `$relRoot` que la página padre puede sobrescribir antes del include para apuntar a `assets/`, `manifest.json` y similares con el prefijo correcto desde subdirectorios.

### 3.2. Control granular de roles de usuario

**Problema:** El sistema arrancó con dos roles (`cliente`, `admin`). Posteriormente se incorporaron `socio` y `operario`, lo que obligó a revisar todos los puntos donde se tomaban decisiones por rol. Una promoción accidental de un cliente a admin (un clic equivocado en un `<select>`) podía conceder acceso total a la base de datos, y un único administrador que se cambiase a sí mismo a `cliente` provocaría un *lockout* irrecuperable.

**Solución adoptada:**
- Flujo de **confirmación explícita** en [admin/usuarios.php](../admin/usuarios.php) con modal SweetAlert2 que describe el impacto del cambio en lenguaje natural antes de enviar la petición.
- *Whitelist* server-side en [api/actualizar_rol.php](../api/actualizar_rol.php) (`$ROLES_VALIDOS = ['cliente','operario','socio','admin']`), independiente del `ENUM` de MySQL para no depender del `sql_mode`.
- **Anti-lockout**: el endpoint rechaza con HTTP 409 cualquier intento de un admin de modificar su propio rol (`if ($id_usuario === $_SESSION['usuario_id'])`).
- **Validación de datos previos**: para promocionar a `socio`, `operario` o `admin` se exige tener cumplimentados DNI y apellidos (necesarios para albaranes y contrato laboral). Si faltan, se devuelve HTTP 422 con la lista de campos pendientes.
- Registro en `error_log` de cada cambio de rol para trazabilidad (`ROLE_CHANGE | admin_id=X cambió usuario_id=Y de socio a admin`).

### 3.3. Generación de PDFs con caracteres no-ASCII

**Problema:** FPDF, en su configuración por defecto, sólo admite la codificación Latin-1 (ISO-8859-1) en sus fuentes integradas. Los nombres de socios extremeños con caracteres como `ñ`, `á`, `ó` o el símbolo `€` aparecían como interrogantes invertidos o cuadrados en los albaranes y facturas. La base de datos, en cambio, está en `utf8mb4`.

**Solución adoptada:** Conversión explícita de todas las cadenas que se pasan a `Cell()`, `MultiCell()` y `Write()` mediante `utf8_decode()` (o `mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8')` para `€` y caracteres fuera del subconjunto Latin-1). Esta conversión solo se aplica en la capa de presentación PDF; la base de datos y la web siguen operando íntegramente en UTF-8.

### 3.4. Concurrencia en el descuento de stock

**Problema:** Dos clientes que pulsen "Comprar" simultáneamente sobre la última unidad de un producto podían crear ambos un pedido satisfactoriamente, dejando el stock en valores negativos.

**Solución adoptada:** Dentro de la transacción de [core/procesar_compra.php](../core/procesar_compra.php), el `UPDATE` de stock incluye la condición defensiva `WHERE id = :id AND stock >= :cantidad_min`. Si entre el `SELECT` previo y el `UPDATE` otro pedido se ha llevado las unidades, `rowCount()` devuelve 0, se lanza una `Exception` y se hace `rollBack()` de toda la operación, incluyendo cabecera, líneas y movimientos de stock ya insertados.

---

## 4. Posibles mejoras

### 4.1. Migración a un framework MVC formal (Laravel o Symfony)

La aplicación actual sigue un patrón procedural con buena separación por carpetas, pero carece de *routing* declarativo, *templating* (Blade/Twig), inyección de dependencias y un ORM como Eloquent o Doctrine. Migrar a **Laravel 11** simplificaría el mantenimiento, las migraciones de base de datos serían versionables y la cobertura de tests automatizados sería mucho más sencilla con PHPUnit y Pest. La inversión inicial es alta, pero se rentabiliza a medio plazo.

### 4.2. Pasarela de pago real e integración con factura electrónica

Actualmente, los pedidos pasan al estado `pagado` directamente al confirmar la compra, sin pasarela real (decisión consciente para el alcance del TFG). Una mejora natural sería integrar **Redsys** (la pasarela utilizada por la mayoría de bancos españoles) o **Stripe** para tarjetas internacionales, junto con la generación de **factura electrónica en formato Facturae 3.2.2** (XML firmado), obligatoria para clientes B2B según la Ley Crea y Crece.

### 4.3. API REST documentada con OpenAPI y autenticación por tokens

Los endpoints `api/` actuales devuelven JSON pero están acoplados a la sesión PHP (`session_start()` + cookies). Externalizarlos como una **API REST stateless con JWT** permitiría desarrollar una app móvil nativa para los socios (registro de entregas en campo, consulta de liquidaciones desde el tractor) sin reescribir la lógica de negocio. La documentación con **OpenAPI 3.0** y un *swagger-ui* facilitaría la integración con sistemas externos como el SIGPAC o el portal de la D.O.P.

---

## 5. Resultados

### 5.1. Objetivos funcionales alcanzados

| Bloque | Funcionalidad | Estado |
|--------|---------------|--------|
| Público | Landing con hero, sección "Esencia", catálogo destacado, proceso, mapa | ✅ |
| Público | PWA instalable (manifest + service worker) | ✅ |
| Cliente | Registro, login, recuperación de contraseña por email | ✅ |
| Cliente | Tienda con catálogo, carrito persistente en sesión, compra | ✅ |
| Cliente | Descarga de factura PDF | ✅ |
| Cliente | Reserva de visita guiada con confirmación por email | ✅ |
| Socio | Panel personalizado con widget meteorológico (Open-Meteo) | ✅ |
| Socio | Calculadora de rendimiento del olivar | ✅ |
| Socio | Histórico de entregas con filtro por campaña y liquidación estimada | ✅ |
| Socio | Descarga de albarán PDF de cada entrega | ✅ |
| Socio | Voto electrónico en asambleas (un socio = un voto, garantía PK) | ✅ |
| Socio | Calendario agrícola con tareas mensuales | ✅ |
| Admin | Dashboard con KPIs y gráficas Chart.js | ✅ |
| Admin | Gestión completa de usuarios, roles, campañas, stock, votaciones, visitas, noticias | ✅ |
| Operario | Panel de operario para tareas de almazara | ✅ |

### 5.2. Objetivos técnicos alcanzados

- **Seguridad**: contraseñas con bcrypt, *prepared statements* nativos, tokens CSRF, `session_regenerate_id`, protección contra *session fixation*, *timing-attack* mitigado con `hash_equals()`, validación de roles en triple barrera.
- **Integridad de datos**: 8 tablas relacionales con `FOREIGN KEY`, restricciones `CHECK`, ENUMs, índices estratégicos y políticas `CASCADE`/`RESTRICT` razonadas.
- **Rendimiento**: vistas SQL precalculadas, columnas generadas `STORED` indexables, conexión PDO en *singleton*, `loading="lazy"` en imágenes, caché agresiva del *service worker*.
- **Accesibilidad**: contraste WCAG AA, *skip-link*, atributos ARIA en elementos interactivos, foco visible, etiquetas asociadas a inputs.
- **Mantenibilidad**: separación por carpetas funcionales, comentarios en cabeceras de archivo explicando el "por qué", esquema SQL autodocumentado.

### 5.3. Indicadores cuantitativos

- **Líneas de PHP**: ~5.500 (estimación) repartidas entre 50+ archivos.
- **Tablas de base de datos**: 8 entidades core (usuarios, fincas, entregas, productos, pedidos, lineas_pedido, direcciones_usuario, campanas) + tablas auxiliares de votaciones, visitas, stock, noticias y calendario.
- **Vistas SQL**: 3 (`v_resumen_socios`, `v_historial_entregas`, `v_estadisticas_campana`) más vistas adicionales para el catálogo de noticias.
- **Endpoints API**: 6 (`carrito`, `votar`, `reservar_visita`, `actualizar_rol`, `estadisticas`, `promocionar_socio`).
- **Migraciones SQL**: 7 incrementales (votaciones, visitas, stock, operario, campanas, noticias/calendario, fix de encoding).

---

## 6. Conclusión

El presente Proyecto Final de Ciclo ha permitido aplicar de forma integrada los conocimientos adquiridos a lo largo del Ciclo Formativo —HTML/CSS/JavaScript, PHP, MySQL, seguridad web, accesibilidad, despliegue— en un caso de uso real con valor para la entidad cliente.

El resultado es una plataforma web funcional que cubre las tres dimensiones de la cooperativa: **operativa interna** (entregas, campañas, stock, albaranes), **gobernanza democrática** (votaciones electrónicas vinculantes con un socio = un voto garantizado a nivel de motor SQL) y **escaparate comercial** (tienda online con tienda, factura electrónica, reservas de visitas y catálogo SEO-friendly).

A lo largo del desarrollo se han tomado decisiones técnicas razonadas, documentadas en los propios comentarios del código fuente: uso de transacciones para operaciones multi-tabla, vistas SQL para abstraer JOINs, columnas generadas `STORED` para fórmulas físicas (densidad del aceite, campaña oleícola), control granular de roles con triple barrera de seguridad y *anti-lockout* explícito.

El proyecto ha permitido enfrentarse a problemas reales de programación web —concurrencia en el descuento de stock, codificación de caracteres en PDFs, refactorización masiva de rutas tras la reorganización por carpetas, gestión segura de roles— y resolverlos con criterios profesionales aplicables al mundo laboral.

Las **mejoras futuras** identificadas (migración a Laravel, pasarela de pago real con Redsys, API REST con JWT) trazan un camino evolutivo coherente que la cooperativa podrá emprender en función de su crecimiento, sin que ello invalide el trabajo realizado: la base de datos, las decisiones de seguridad y la lógica de negocio son patrimonio reutilizable más allá del *stack* tecnológico concreto.

En definitiva, el alumno considera cumplidos los objetivos planteados en el anteproyecto y entrega una aplicación que, con los ajustes propios del paso a producción (eliminación de datos seed, configuración HTTPS, copias de seguridad programadas), puede ser puesta en explotación real por la Cooperativa San Juan Bautista.

---

*Documento elaborado como parte de la Memoria del PFC. Las referencias `[ruta]` enlazan con archivos del propio repositorio del proyecto.*
