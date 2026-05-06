# MEMORIA DEL PROYECTO FINAL DE CICLO

## Cooperativa San Juan Bautista — Plataforma Web Integral para una Almazara

**Ciclo Formativo:**  Desarrollo de Aplicaciones Web (DAW)
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

El proyecto se ha estructurado en torno a cuatro áreas técnicas:

- **Frontend**: HTML5 semántico con Bootstrap 5, CSS personalizado en [assets/css/estilos.css](../assets/css/estilos.css) y [assets/css/admin.css](../assets/css/admin.css), JavaScript vanilla con la API Fetch, gráficas con **Chart.js** y diálogos modales con **SweetAlert2**. Se ha cuidado la accesibilidad (atributos ARIA, *skip-link* WCAG 2.4.1, contraste AA) y la experiencia móvil (PWA con `manifest.json` y *service worker* en [sw.js](../sw.js)).
- **Backend**: PHP 8 con paradigma orientado a procesos y separación por carpetas funcionales (`auth/`, `api/`, `admin/`, `core/`). Acceso a datos mediante **PDO** con *prepared statements* nativos (definidos en [config/db.php](../config/db.php)). Generación de PDFs reutilizables (facturas y albaranes) con la librería **FPDF**, envío real de correo SMTP con **PHPMailer** (incluyendo adjuntos PDF en memoria) e integración con la pasarela de pago **Stripe Checkout**.
- **Base de datos**: MySQL 8 / MariaDB sobre motor **InnoDB**, con `utf8mb4_unicode_ci`, restricciones `CHECK`, columnas generadas (`STORED`), claves foráneas con políticas `CASCADE` / `RESTRICT` / `SET NULL` y vistas SQL (`v_resumen_socios`, `v_historial_entregas`, `v_estadisticas_campana`, `v_campanas_resumen`, `v_noticias_publicas`, `v_stock_estado`). El esquema completo se encuentra en [database/schema/cooperativa_sjb_completo.sql](../database/schema/cooperativa_sjb_completo.sql).
- **Despliegue e infraestructura**: detección automática del entorno (XAMPP local vs Hostinger Premium) en [config/env.php](../config/env.php), gestión de secretos fuera del repositorio en `config/secrets.local.php`, `.htaccess` con HTTPS forzado y bloqueo 403 sobre carpetas internas (`config/`, `vendor/`, `logs/`), y servidor SMTP real (Gmail con App Password) para entrega fiable de correos transaccionales.

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
│                         estadisticas, promocionar_socio, anular_entrega)
├── assets/             → CSS, imágenes de productos e iconografía
├── auth/               → login, logout, registro y recuperación de contraseña
├── config/             → env.php (detección de entorno),
│                         db.php (conexión PDO singleton),
│                         email.php (modo log/SMTP),
│                         stripe_config.php (URLs y claves Stripe),
│                         secrets.local.php (claves reales, fuera de git)
├── core/               → Lógica de negocio crítica
│                         (procesar_compra, crear_sesion_stripe,
│                         generar_factura/albaran, factura_pdf/albaran_pdf
│                         como helpers reutilizables, mailer, notificaciones)
├── database/
│   ├── schema/         → Esquema unificado v3.0 de producción
│   └── migrations/     → Migraciones incrementales numeradas
├── includes/           → Cabecera, navbar (público / socio / admin / operario),
│                         footer reutilizables
├── logs/emails/        → Fallback local: emails serializados como HTML
├── vendor/             → Dependencias (sin Composer)
│   ├── fpdf/           → Librería FPDF para PDFs (vendored)
│   ├── stripe/         → Stripe PHP SDK (vendored)
│   ├── phpmailer/      → PHPMailer para SMTP con adjuntos
│   └── init_libs.php   → Autoloader manual unificado de Stripe + PHPMailer
├── index.php           → Landing page pública
├── tienda.php          → Catálogo + offcanvas del carrito
├── panel_socio.php     → Dashboard del socio
├── calculadora.php     → Calculadora de rendimiento del olivar
├── votaciones.php      → Listado de votaciones para socios
├── mis_entregas.php    → Histórico de entregas y liquidación
├── operario.php        → Panel del operario
├── .htaccess           → HTTPS forzado, bloqueo 403 de carpetas internas
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

#### 2.4.5. Generación de PDFs reutilizables

Los albaranes de entrega y las facturas de pedido se generan en tiempo real con **FPDF**, una librería ligera que no requiere Composer. La factura aplica el tipo reducido del IVA (10 %, según el art. 91 de la Ley 37/1992 para productos alimenticios) y respeta la paleta corporativa de la cooperativa (verde oliva `#2C4C3B`, dorado `#D4AF37`).

La lógica FPDF se ha extraído a dos *helpers* puros — [core/factura_pdf.php](../core/factura_pdf.php) y [core/albaran_pdf.php](../core/albaran_pdf.php) — que devuelven el PDF como **string binario** (`$pdf->Output('S')`) en lugar de enviarlo directamente al navegador. Esto permite reutilizar la misma generación tanto desde el endpoint de descarga ([core/generar_factura.php](../core/generar_factura.php), [core/generar_albaran.php](../core/generar_albaran.php)) como desde el envío automático por correo, **sin escribir nunca un fichero temporal en disco**.

El control de acceso se aplica en el propio script: si el solicitante no es admin, se añade dinámicamente la condición `AND p.id_usuario = :id_usuario` al SQL, garantizando que un usuario no pueda descargar la factura de otro mediante manipulación de la URL.

#### 2.4.6. Envío de correo SMTP con PHPMailer y plantillas reutilizables

La aplicación envía correos transaccionales en cuatro flujos clave:

| Disparador | Plantilla | Adjunto |
|------------|-----------|---------|
| Registro de un nuevo usuario | `emailBienvenidaUsuario()` | — |
| Pago confirmado en Stripe | `emailConfirmacionCompra()` | Factura PDF (`FAC-XXXXXX.pdf`) |
| Admin registra una entrega de aceituna | `emailEntregaRegistrada()` | Albarán PDF (`ALB-XXXXXX.pdf`) |
| Admin anula una entrega | `emailEntregaAnulada()` | — |

Toda la lógica de envío vive en [core/mailer.php](../core/mailer.php) y expone una única función pública `enviarEmail($to, $subject, $html, $attachments = [])`. Internamente:

- En **producción** (Hostinger) usa **PHPMailer** vía SMTP autenticado contra `smtp.gmail.com:587` con `STARTTLS` y App Password de 16 caracteres (la contraseña real se almacena en `config/secrets.local.php`, fuera del repositorio).
- En **local** (XAMPP) cae automáticamente al **modo log**, serializando cada correo como HTML en `logs/emails/` para facilitar la depuración de plantillas sin necesidad de SMTP.

Para evitar duplicación, todas las plantillas comparten una **función envoltorio** `emailWrapper($titulo, $subtitulo, $cuerpoHtml, $variant)` que genera la cabecera (gradiente verde/dorado o rojizo según el tipo de notificación) y el pie corporativo. Las plantillas se construyen con tablas HTML y estilos *inline*, único formato que renderiza correctamente en clientes como Outlook, Gmail y Apple Mail.

Los adjuntos se pasan **en memoria binaria** (`addStringAttachment()`) directamente desde la salida de los *helpers* PDF, sin ficheros temporales. Si SMTP falla en producción (saturación del servidor, *quota* alcanzada, etc.), el sistema **no aborta la operación principal** — la factura se inserta en BD, la entrega se registra, el usuario se da de alta — y el error se loguea en `error_log` para revisión posterior.

#### 2.4.7. Anulación de entregas como soft-delete con auditoría

Las entregas son documentos contables: cada una genera un albarán PDF que el socio recibe por correo y queda almacenado en su buzón. **Borrar físicamente la fila** ante un error de tecleo (kilos mal introducidos, socio equivocado) rompería la trazabilidad con la campaña, los movimientos de stock y la liquidación.

Por ello se ha implementado un patrón **soft-delete** mediante cuatro columnas añadidas a la tabla `entregas` (migración [database/migrations/2026_05_anular_entregas.sql](../database/migrations/2026_05_anular_entregas.sql)):

```sql
anulada           TINYINT(1) NOT NULL DEFAULT 0,
motivo_anulacion  VARCHAR(255),
fecha_anulacion   TIMESTAMP NULL,
id_admin_anula    INT UNSIGNED REFERENCES usuarios(id) ON DELETE SET NULL
```

El admin invoca el endpoint [api/anular_entrega.php](../api/anular_entrega.php) (POST con CSRF + motivo obligatorio) y el sistema:

1. Marca la fila con `anulada = 1`, fecha actual y el `id` del admin responsable.
2. Excluye la entrega de los `SUM()` de KPIs y liquidaciones en el panel admin y en el panel del socio (`WHERE anulada = 0`).
3. Bloquea la descarga del albarán PDF correspondiente devolviendo **HTTP 410 Gone** desde [core/generar_albaran.php](../core/generar_albaran.php).
4. Notifica al socio por correo con la plantilla `emailEntregaAnulada()` indicando explícitamente que el albarán anterior **ya no tiene validez**.
5. Registra el evento en `error_log` para auditoría (`ANULACION | admin_id=X anuló entrega_id=Y socio_id=Z motivo="..."`).

En la interfaz, las entregas anuladas siguen apareciendo en el histórico tachadas y con un *badge* rojo "ANULADA". El admin puede consultarlas pero no descargar el albarán; el socio ve la entrada igualmente, lo que aporta transparencia al proceso.

#### 2.4.8. Detección automática de entorno y despliegue en Hostinger

El proyecto está pensado para correr **sin modificación de código** tanto en XAMPP local como en Hostinger Premium. La pieza central es [config/env.php](../config/env.php), que se carga al inicio de toda petición y publica dos constantes globales:

```php
define('ES_LOCAL', /* heurística sobre $_SERVER['HTTP_HOST'] */);
define('ES_PRODUCCION', !ES_LOCAL);
define('APP_URL', ES_LOCAL ? 'http://localhost/TFG' : 'https://' . $_SERVER['HTTP_HOST']);
```

A partir de ahí, los archivos de configuración (`db.php`, `email.php`, `stripe_config.php`) eligen sus valores en función de `ES_PRODUCCION`. Las claves sensibles (contraseña MySQL, App Password de Gmail, claves Stripe `sk_*`) se almacenan en `config/secrets.local.php`, un archivo **excluido del repositorio** (`.gitignore`) que se sube manualmente al servidor por FTP. Internamente el archivo está envuelto en `if (ES_PRODUCCION) { ... }`, por lo que puede convivir en local sin pisar las credenciales de XAMPP.

El paquete de despliegue se completa con:

- Un **`.htaccess`** que fuerza HTTPS sólo en producción (la condición `RewriteCond %{HTTP_HOST} !^localhost` evita romper el desarrollo), bloquea con `403 Forbidden` el acceso público a `config/`, `vendor/`, `database/` y `logs/`, configura cabeceras de seguridad (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) y aplica caché agresiva a estáticos manteniendo el *service worker* sin caché.
- Una **carpeta `vendor/` versionada** con FPDF, Stripe SDK y PHPMailer ya descargados, junto a un autoloader manual unificado [vendor/init_libs.php](../vendor/init_libs.php) que registra el namespace `PHPMailer\PHPMailer\` mediante `spl_autoload_register()`. Esto evita la dependencia de Composer en el servidor, opción habitual en hostings compartidos donde no siempre hay acceso SSH.
- Un **dump SQL saneado** ([database/schema/cooperativa_sjb_completo.sql](../database/schema/cooperativa_sjb_completo.sql)) sin cláusulas `DEFINER=root@localhost` que romperían la importación en MariaDB de Hostinger, donde no existe el usuario `root`.

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

### 3.5. Conflicto de *collations* entre PDO y MariaDB en producción

**Problema:** El primer despliegue en Hostinger fallaba con `SQLSTATE[HY000]: 1267 Illegal mix of collations (utf8mb4_general_ci, COERCIBLE) and (utf8mb4_unicode_ci, COERCIBLE) for operation 'nullif'` cada vez que el admin intentaba promocionar a un socio. La columna `usuarios.telefono` está declarada como `utf8mb4_unicode_ci` (collation por defecto del esquema), pero PDO conectaba con `utf8mb4_general_ci` (default de PHP). En la consulta `UPDATE ... SET telefono = COALESCE(NULLIF(:tel, ""), telefono)` MariaDB no podía decidir qué *collation* usar al comparar el literal vacío con la columna. En XAMPP local (MySQL 5.7) era un *warning* tolerado; en MariaDB 10.5+ es un error fatal.

**Solución adoptada:** Forzar la *collation* de la conexión PDO al mismo valor que las tablas mediante `MYSQL_ATTR_INIT_COMMAND` en [config/db.php](../config/db.php):

```php
PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
```

Este *fix* arregla el problema de raíz para **toda la aplicación**, no solo para la promoción: cualquier otra consulta que mezcle literales con columnas (búsquedas, `INSERT` con valores por defecto) deja de fallar.

### 3.6. Falsos positivos del antivirus durante el desarrollo

**Problema:** Avast Antivirus marcaba sistemáticamente como *threat* el archivo `vendor/autoload.php` y la función PHP `mail()` empleada por el helper original de correo, eliminándolos automáticamente y dejando la aplicación rota tras cada `Compress-Archive` o reinstalación. La heurística del antivirus identificaba el patrón "PHP autoload + plantillas HTML con marca corporativa" como típico de *phishing-kits*.

**Solución adoptada:**
- Renombrar `vendor/autoload.php` a [vendor/init_libs.php](../vendor/init_libs.php), un nombre que no aparece en las *blacklists* heurísticas. Se actualizaron las tres referencias que lo cargaban ([core/mailer.php](../core/mailer.php), [core/procesar_compra.php](../core/procesar_compra.php), [core/crear_sesion_stripe.php](../core/crear_sesion_stripe.php)).
- Reemplazar el envío manual con `mail()` del antiguo `core/mailer.php` por **PHPMailer** sobre SMTP, que también pasa los *checks* de Avast por usar autenticación contra un servidor real.
- En el desarrollo se documentó la incidencia para que el siguiente revisor del código entienda el porqué del nombre poco convencional.

### 3.7. Menú móvil inoperativo en páginas sin footer

**Problema:** Páginas como `calculadora.php`, `exito.php` y `votaciones.php` no incluyen `includes/footer.php` por simplicidad (no necesitan el offcanvas del carrito ni el modal de login). El JavaScript que abre y cierra el menú hamburguesa móvil vivía dentro del bloque global de `footer.php`, con lo que en esas tres páginas el botón ☰ era un elemento decorativo: pulsarlo no producía efecto alguno.

**Solución adoptada:** Trasladar el *handler* del *toggle* a [includes/navbar.php](../includes/navbar.php), de forma que toda página que use el navbar — independientemente de si carga el footer — tenga el menú móvil funcional. Se añadió un *flag* `dataset.toggleBound` para que, si una página carga ambos archivos, el listener no se duplique:

```javascript
if (!menuToggle.dataset.toggleBound) {
    menuToggle.dataset.toggleBound = '1';
    menuToggle.addEventListener('click', /* ... */);
}
```

Adicionalmente se eliminó la duplicación de Bootstrap JS en `calculadora.php` (el grid CSS no requiere el bundle de scripts) y se ajustó el espaciado de los botones CTA "Tienda" y "Carrito" del navbar mediante un selector `.nav-cta + .nav-cta` para evitar que aparezcan pegados visualmente.

---

## 4. Posibles mejoras

### 4.1. Migración a un framework MVC formal (Laravel o Symfony)

La aplicación actual sigue un patrón procedural con buena separación por carpetas, pero carece de *routing* declarativo, *templating* (Blade/Twig), inyección de dependencias y un ORM como Eloquent o Doctrine. Migrar a **Laravel 11** simplificaría el mantenimiento, las migraciones de base de datos serían versionables y la cobertura de tests automatizados sería mucho más sencilla con PHPUnit y Pest. La inversión inicial es alta, pero se rentabiliza a medio plazo.

### 4.2. Factura electrónica Facturae 3.2.2

La integración con **Stripe Checkout** ya está operativa: los pedidos pasan al estado `pagado` únicamente cuando el `payment_status` devuelto por Stripe es `'paid'`, y al confirmar el pago se envía al cliente la factura PDF como adjunto SMTP. La siguiente evolución natural es generar la **factura electrónica en formato Facturae 3.2.2** (XML firmado con certificado digital del FNMT), obligatoria para clientes B2B según la Ley Crea y Crece. El flujo actual de generación de PDFs reutilizables ya separa "datos" de "presentación", lo que facilitaría añadir un *renderer* alternativo que produzca XML en lugar (o además) del PDF actual.

### 4.3. API REST documentada con OpenAPI y autenticación por tokens

Los endpoints `api/` actuales devuelven JSON pero están acoplados a la sesión PHP (`session_start()` + cookies). Externalizarlos como una **API REST stateless con JWT** permitiría desarrollar una app móvil nativa para los socios (registro de entregas en campo, consulta de liquidaciones desde el tractor) sin reescribir la lógica de negocio. La documentación con **OpenAPI 3.0** y un *swagger-ui* facilitaría la integración con sistemas externos como el SIGPAC o el portal de la D.O.P.

### 4.4. Webhooks de Stripe para pagos asíncronos

La integración actual con Stripe verifica el pago en el `success_url` (`procesar_compra.php?session_id=...`). Esto cubre el 99 % de los casos, pero un cliente que cierre el navegador justo después de pagar quedaría sin la confirmación en BD aunque Stripe sí cobró. Registrar un **webhook** de Stripe (`checkout.session.completed`) en un endpoint público firmado con el `signing_secret` permitiría procesar la compra incluso sin que el cliente regrese a la web — escenario habitual en pagos por SCA con redirección bancaria. La estructura de transacción ACID actual ya está preparada: bastaría con encapsular la lógica de inserción en una función reutilizable e invocarla desde ambas rutas.

### 4.5. Cuenta de email corporativa con dominio propio

El despliegue actual envía correos desde una cuenta **Gmail** (`cooperativasjb.envios@gmail.com`) con App Password. Funciona perfectamente y los mensajes llegan a la bandeja principal, pero el remitente que ve el cliente lleva el sufijo *via gmail.com*, menos profesional que un dominio corporativo. Cuando la cooperativa adquiera un dominio propio (`coopsanjuanbautista.es`) y lo registre en Hostinger, bastará con crear la cuenta `no-responder@coopsanjuanbautista.es`, pegar las nuevas credenciales SMTP en `config/secrets.local.php` y configurar los registros **SPF**, **DKIM** y **DMARC** en el DNS para una entregabilidad óptima — todo el código existente seguirá funcionando sin cambios.

---

## 5. Resultados

### 5.1. Objetivos funcionales alcanzados

| Bloque | Funcionalidad | Estado |
|--------|---------------|--------|
| Público | Landing con hero, sección "Esencia", catálogo destacado, proceso, mapa | ✅ |
| Público | PWA instalable (manifest + service worker) | ✅ |
| Cliente | Registro, login, recuperación de contraseña por email | ✅ |
| Cliente | **Email de bienvenida HTML al registrarse** | ✅ |
| Cliente | Tienda con catálogo, carrito persistente en sesión | ✅ |
| Cliente | **Pago real con Stripe Checkout (claves test/live por entorno)** | ✅ |
| Cliente | **Email de confirmación de compra con factura PDF adjunta** | ✅ |
| Cliente | Descarga de factura PDF desde el panel | ✅ |
| Cliente | Reserva de visita guiada con confirmación por email | ✅ |
| Socio | Panel personalizado con widget meteorológico (Open-Meteo) | ✅ |
| Socio | Calculadora de rendimiento del olivar | ✅ |
| Socio | Histórico de entregas con filtro por campaña y liquidación estimada | ✅ |
| Socio | Descarga de albarán PDF de cada entrega | ✅ |
| Socio | **Recibe email automático con albarán PDF al registrarse una entrega** | ✅ |
| Socio | **Notificación por email si su entrega es anulada (con motivo)** | ✅ |
| Socio | Voto electrónico en asambleas (un socio = un voto, garantía PK) | ✅ |
| Socio | Calendario agrícola con tareas mensuales | ✅ |
| Admin | Dashboard con KPIs y gráficas Chart.js | ✅ |
| Admin | Gestión completa de usuarios, roles, campañas, stock, votaciones, visitas, noticias | ✅ |
| Admin | **Anulación de entregas con motivo (soft-delete + auditoría)** | ✅ |
| Operario | Panel de operario para tareas de almazara | ✅ |
| Despliegue | **Producción funcional en Hostinger Premium con HTTPS y SMTP real** | ✅ |

### 5.2. Objetivos técnicos alcanzados

- **Seguridad**: contraseñas con bcrypt, *prepared statements* nativos, tokens CSRF, `session_regenerate_id`, protección contra *session fixation*, *timing-attack* mitigado con `hash_equals()`, validación de roles en triple barrera, secretos fuera del repositorio, bloqueo `403 Forbidden` de carpetas internas vía `.htaccess`, HTTPS forzado en producción.
- **Integridad de datos**: 15 tablas relacionales con `FOREIGN KEY`, restricciones `CHECK`, ENUMs, índices estratégicos y políticas `CASCADE`/`RESTRICT` razonadas. Soft-delete con auditoría (`anulada`, `motivo_anulacion`, `fecha_anulacion`, `id_admin_anula`) en lugar de borrado físico para documentos contables.
- **Rendimiento**: vistas SQL precalculadas, columnas generadas `STORED` indexables, conexión PDO en *singleton* con *collation* explícita (`utf8mb4_unicode_ci`), `loading="lazy"` en imágenes, caché agresiva del *service worker* y compresión gzip vía `.htaccess`.
- **Accesibilidad**: contraste WCAG AA, *skip-link*, atributos ARIA en elementos interactivos, foco visible, etiquetas asociadas a inputs.
- **Mantenibilidad**: separación por carpetas funcionales, comentarios en cabeceras de archivo explicando el "por qué", esquema SQL autodocumentado, **detección automática de entorno** que permite el mismo código en local y producción.
- **Comunicaciones**: envío real de correo SMTP autenticado con PHPMailer en producción, *fallback* a log en local, plantillas HTML reutilizables (`emailWrapper`) compatibles con Outlook/Gmail/Apple Mail, adjuntos PDF en memoria sin escritura a disco.

### 5.3. Indicadores cuantitativos

- **Líneas de PHP**: ~6.500 (estimación) repartidas entre 60+ archivos, incluyendo configuración de entorno y *helpers* de PDFs/correo.
- **Tablas de base de datos**: 15 entidades (usuarios, fincas, entregas, productos, pedidos, lineas_pedido, direcciones_usuario, campanas, movimientos_stock, noticias, visitas, votaciones, votacion_opciones, votos, calendario_tareas) + 6 vistas SQL.
- **Vistas SQL**: 6 (`v_resumen_socios`, `v_historial_entregas`, `v_estadisticas_campana`, `v_campanas_resumen`, `v_noticias_publicas`, `v_stock_estado`).
- **Endpoints API**: 7 (`carrito`, `votar`, `reservar_visita`, `actualizar_rol`, `estadisticas`, `promocionar_socio`, `anular_entrega`).
- **Migraciones SQL**: 8 incrementales (votaciones, visitas, stock, operario, campanas, noticias/calendario, fix de encoding, anulación de entregas).
- **Plantillas de email**: 6 (`emailWrapper` reutilizable + 5 específicas: bienvenida, compra, entrega registrada, entrega anulada, confirmación/cancelación de visita).
- **Dependencias *vendored***: 3 (FPDF para PDFs, Stripe PHP SDK, PHPMailer 6.9.3 para SMTP).

---

## 6. Conclusión

El presente Proyecto Final de Ciclo ha permitido aplicar de forma integrada los conocimientos adquiridos a lo largo del Ciclo Formativo —HTML/CSS/JavaScript, PHP, MySQL, seguridad web, accesibilidad, despliegue real en hosting profesional— en un caso de uso con valor para la entidad cliente.

El resultado es una plataforma web funcional, **desplegada en producción sobre Hostinger Premium con HTTPS y SMTP real**, que cubre las tres dimensiones de la cooperativa: **operativa interna** (entregas, campañas, stock, albaranes con soft-delete auditable), **gobernanza democrática** (votaciones electrónicas vinculantes con un socio = un voto garantizado a nivel de motor SQL) y **escaparate comercial** (tienda online con pasarela Stripe Checkout, factura PDF enviada como adjunto SMTP, reservas de visitas y catálogo SEO-friendly).

A lo largo del desarrollo se han tomado decisiones técnicas razonadas, documentadas en los propios comentarios del código fuente: uso de transacciones para operaciones multi-tabla, vistas SQL para abstraer JOINs, columnas generadas `STORED` para fórmulas físicas (densidad del aceite, campaña oleícola), control granular de roles con triple barrera de seguridad, *anti-lockout* explícito, soft-delete con auditoría para documentos contables, y separación estricta entre código (versionado en git) y secretos (`secrets.local.php` excluido del repositorio).

El proyecto ha permitido enfrentarse a problemas reales de programación y administración de sistemas — concurrencia en el descuento de stock, codificación de caracteres en PDFs, refactorización masiva de rutas tras la reorganización por carpetas, gestión segura de roles, conflictos de *collation* entre PDO y MariaDB en el paso a producción, falsos positivos del antivirus, configuración SMTP con App Passwords de Google, sanitización de dumps SQL para hostings compartidos — y resolverlos con criterios profesionales aplicables al mundo laboral.

Las **mejoras futuras** identificadas (migración a Laravel, factura electrónica Facturae 3.2.2, API REST con JWT, *webhooks* de Stripe, dominio corporativo propio con SPF/DKIM/DMARC) trazan un camino evolutivo coherente que la cooperativa podrá emprender en función de su crecimiento, sin que ello invalide el trabajo realizado: la base de datos, las decisiones de seguridad y la lógica de negocio son patrimonio reutilizable más allá del *stack* tecnológico concreto.

En definitiva, el alumno considera cumplidos los objetivos planteados en el anteproyecto y entrega una aplicación **que ya está en explotación**: accesible públicamente por HTTPS, con base de datos MySQL real, envío de correos SMTP autenticado y pasarela de pago operativa, lista para que la Cooperativa San Juan Bautista la utilice tan pronto como migre del subdominio temporal de Hostinger a su propio dominio comercial.

---

*Documento elaborado como parte de la Memoria del PFC. Las referencias `[ruta]` enlazan con archivos del propio repositorio del proyecto.*
