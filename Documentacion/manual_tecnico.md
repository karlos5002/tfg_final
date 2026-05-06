# MANUAL TÉCNICO

## Cooperativa San Juan Bautista — Plataforma Web

**Anexo del Proyecto Final de Ciclo**
**Destinatario:** Administrador de Sistemas / Responsable de TI

---

## Índice

1. [Requisitos del entorno](#1-requisitos-del-entorno)
2. [Estructura de archivos del proyecto](#2-estructura-de-archivos-del-proyecto)
3. [Configuración de la base de datos](#3-configuración-de-la-base-de-datos)
4. [Despliegue de la aplicación](#4-despliegue-de-la-aplicación)
5. [Configuración del servicio de correo](#5-configuración-del-servicio-de-correo)
6. [Verificación del despliegue](#6-verificación-del-despliegue)
7. [Mantenimiento y operaciones](#7-mantenimiento-y-operaciones)
8. [Resolución de problemas](#8-resolución-de-problemas)

---

## 1. Requisitos del entorno

### 1.1. Software del servidor

| Componente | Versión mínima | Versión recomendada | Notas |
|------------|---------------|---------------------|-------|
| **PHP** | 8.0 | 8.2 LTS | Tipado estricto en algunos archivos (`function getConexion(): PDO`) |
| **MySQL / MariaDB** | MySQL 8.0 / MariaDB 10.5 | MySQL 8.0+ | Se requieren columnas `GENERATED ALWAYS AS ... STORED` |
| **Servidor web** | Apache 2.4 | Apache 2.4 con `mod_rewrite` | Nginx también es viable con la configuración `try_files` apropiada |
| **Sistema operativo** | — | Linux (Debian/Ubuntu) o Windows Server | Probado en XAMPP 8.2 sobre Windows |

### 1.2. Extensiones PHP requeridas

Las siguientes extensiones deben estar habilitadas en `php.ini`:

- `pdo` y `pdo_mysql` — Acceso a base de datos.
- `mbstring` — Conversión UTF-8 ↔ Latin-1 para PDFs.
- `openssl` — Hashing de contraseñas con bcrypt y conexiones HTTPS salientes (Open-Meteo).
- `session` — Gestión de sesiones (carrito, login, CSRF).
- `json` — Endpoints API REST y comunicación con el frontend.
- `fileinfo` — Validación de imágenes subidas.
- `curl` — Consumo del API meteorológico Open-Meteo en `panel_socio.php`.

Para verificar las extensiones disponibles:

```bash
php -m | grep -E 'pdo|mbstring|openssl|json|curl'
```

### 1.3. Recursos hardware orientativos

| Concepto | Mínimo | Recomendado |
|----------|--------|-------------|
| CPU | 1 vCPU | 2 vCPU |
| RAM | 1 GB | 2 GB |
| Almacenamiento | 5 GB | 20 GB (incluye logs, PDFs, copias de seguridad) |
| Conectividad | 100 Mbps | 1 Gbps si se sirven imágenes a alta resolución |

### 1.4. Dependencias externas

El proyecto **no utiliza Composer**. Las dependencias se incluyen físicamente en el repositorio:

- **FPDF** (1.86 o superior): librería para generación de PDFs, ubicada en [vendor/fpdf/](../vendor/fpdf/). Ya se distribuye con el código.
- **Bootstrap 5**, **Bootstrap Icons**, **Chart.js**, **SweetAlert2**: se cargan vía CDN (`cdn.jsdelivr.net`) desde [includes/header.php](../includes/header.php). No hay instalación local.
- **Open-Meteo** (`api.open-meteo.com`): API meteorológica gratuita y sin clave consultada por el panel del socio.

---

## 2. Estructura de archivos del proyecto

```
TFG/
├── admin/              Páginas del panel de administración
├── api/                Endpoints JSON (carrito, votar, reservar_visita, etc.)
├── assets/
│   ├── css/            estilos.css y admin.css
│   ├── img/            Imágenes de productos y olivar
│   └── js/             (vacío — JS embebido en cada página)
├── auth/               login.php, logout.php, registro.php, recuperar_password.php
├── config/
│   ├── db.php          Conexión PDO singleton (CONFIGURAR AQUÍ)
│   └── email.php       Parámetros SMTP / mailer
├── core/               Lógica de negocio (procesar_compra, generar_factura,
│                       generar_albaran, mailer, notificaciones)
├── database/
│   ├── schema/
│   │   └── cooperativa_sjb.sql       Esquema unificado v3.0 (PRODUCCIÓN)
│   ├── migrations/                    Migraciones incrementales
│   │   ├── 01_votaciones.sql
│   │   ├── 02_visitas.sql
│   │   ├── 03_stock.sql
│   │   ├── 04_operario.sql
│   │   ├── 05_campanas.sql
│   │   ├── 06_noticias_calendario.sql
│   │   └── 07_fix_encoding.sql
│   └── _legacy_v1.sql                 Esquema histórico (no aplicar)
├── includes/           Cabecera, navbars (público / socio / admin / operario), footer
├── logs/               Log de correos enviados (debe ser writable)
├── scratch/            Scripts auxiliares de desarrollo (NO DESPLEGAR EN PROD)
├── vendor/fpdf/        Librería FPDF
├── index.php           Landing page
├── tienda.php          Catálogo y carrito
├── panel_socio.php     Dashboard del socio
├── calculadora.php     Calculadora de rendimiento del olivar
├── votaciones.php      Listado de votaciones
├── mis_entregas.php    Histórico de entregas del socio
├── operario.php        Panel del operario
├── exito.php           Pantalla post-compra
├── manifest.json       Manifiesto PWA
└── sw.js               Service worker
```

Carpetas que **no** deben copiarse al servidor de producción:

- `scratch/`: scripts de prueba y *debug* (`check_db.php`, `test_login.php`, `dump_productos.php`).
- `database/_legacy_v1.sql`: esquema antiguo, ya consolidado en `schema/`.

---

## 3. Configuración de la base de datos

### 3.1. Parámetros de conexión

La configuración se centraliza en [config/db.php](../config/db.php):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cooperativa_sjb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

> **IMPORTANTE — En producción:**
> 1. Crear un usuario MySQL dedicado **sin privilegios de superusuario**:
>    ```sql
>    CREATE USER 'sjb_app'@'localhost' IDENTIFIED BY '<PASSWORD_FUERTE>';
>    GRANT SELECT, INSERT, UPDATE, DELETE ON cooperativa_sjb.* TO 'sjb_app'@'localhost';
>    FLUSH PRIVILEGES;
>    ```
> 2. Sustituir `DB_USER` y `DB_PASS` por las credenciales del usuario `sjb_app`.
> 3. Asegurarse de que `config/db.php` no es accesible vía HTTP. Si se usa Apache, añadir en `.htaccess`:
>    ```
>    <Files "db.php">
>        Require all denied
>    </Files>
>    ```

La función `getConexion()` implementa el patrón *singleton*: instancia el objeto `PDO` una sola vez por petición HTTP y lo reutiliza en todos los `require_once` posteriores.

### 3.2. Atributos PDO aplicados

```php
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION   // try/catch obligatorio
PDO::ATTR_EMULATE_PREPARES   => false                     // prepared nativos, blinda SQLi
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC          // arrays asociativos
PDO::ATTR_TIMEOUT            => 5                          // 5 segundos máximo
```

### 3.3. Importación del esquema

El proyecto se distribuye con **un único script unificado** (versión 3.0) que crea la base de datos desde cero, define todas las tablas, índices, vistas y datos de prueba.

#### Opción A — Importación desde la línea de comandos (recomendado)

```bash
mysql -u root -p < database/schema/cooperativa_sjb.sql
```

El script ya incluye `DROP DATABASE IF EXISTS cooperativa_sjb;` y `CREATE DATABASE cooperativa_sjb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` al principio, por lo que puede ejecutarse incluso si la BD ya existe.

#### Opción B — Importación desde phpMyAdmin

1. Acceder a `http://<servidor>/phpmyadmin`.
2. Pestaña **Importar**.
3. Pulsar **Examinar** y seleccionar [database/schema/cooperativa_sjb.sql](../database/schema/cooperativa_sjb.sql).
4. Verificar que el formato detectado es **SQL** y la codificación **utf-8**.
5. Pulsar **Continuar**.

#### Opción C — Migraciones incrementales (servidor con datos existentes)

Si ya hay una versión anterior en producción y se desea migrar progresivamente:

```bash
mysql -u root -p cooperativa_sjb < database/migrations/01_votaciones.sql
mysql -u root -p cooperativa_sjb < database/migrations/02_visitas.sql
mysql -u root -p cooperativa_sjb < database/migrations/03_stock.sql
mysql -u root -p cooperativa_sjb < database/migrations/04_operario.sql
mysql -u root -p cooperativa_sjb < database/migrations/05_campanas.sql
mysql -u root -p cooperativa_sjb < database/migrations/06_noticias_calendario.sql
mysql -u root -p cooperativa_sjb < database/migrations/07_fix_encoding.sql
```

Las migraciones se aplican en orden numérico estricto.

### 3.4. Esquema resumido

| Tabla | Filas core | Descripción |
|-------|------------|-------------|
| `usuarios` | id, dni, nombre, apellidos, email, password, rol (`admin`/`socio`/`operario`/`cliente`), activo | Cuentas del sistema |
| `fincas` | id, id_socio, nombre_paraje, polígono, parcela, hectareas, tipo_cultivo | Trazabilidad catastral |
| `entregas` | id, id_socio, fecha_entrega, kilos_aceituna, rendimiento, **campana** (generada), **litros_aceite** (generada) | Aportaciones a la almazara |
| `productos` | id, nombre, slug, variedad, precio, stock, imagen, activo | Catálogo de la tienda |
| `pedidos` | id, id_usuario, fecha_pedido, total, estado, dirección de envío | Cabecera de compra |
| `lineas_pedido` | id_pedido, id_producto, cantidad, precio_unitario, **subtotal** (generada) | Detalle de cada pedido |
| `direcciones_usuario` | id, id_usuario, alias, dirección, CP, localidad, provincia | Direcciones guardadas |
| `campanas` | id, código, fecha_inicio, fecha_fin, precio_por_kilo, estado | Campañas oleícolas |

### 3.5. Vistas SQL provistas

```sql
SELECT * FROM v_resumen_socios WHERE campana = '2025/2026';
SELECT * FROM v_historial_entregas ORDER BY fecha_entrega DESC;
SELECT * FROM v_estadisticas_campana ORDER BY campana DESC;
```

### 3.6. Eliminación de datos de prueba en producción

El script de esquema incluye **datos seed** para desarrollo (5 usuarios con contraseña `1234`, 6 fincas, 6 entregas, 9 productos y 2 pedidos de ejemplo). En producción deben eliminarse:

```sql
DELETE FROM lineas_pedido;
DELETE FROM pedidos;
DELETE FROM entregas;
DELETE FROM fincas;
DELETE FROM productos;
DELETE FROM usuarios;
```

A continuación crear el primer administrador real con una contraseña fuerte:

```sql
INSERT INTO usuarios (dni, nombre, apellidos, email, password, rol)
VALUES (
  '12345678Z', 'Nombre', 'Apellidos',
  'admin@coopsanjuanbautista.es',
  '<HASH_BCRYPT_GENERADO_CON_PHP>',
  'admin'
);
```

Para generar el hash bcrypt:

```bash
php -r "echo password_hash('PasswordFuerte123!', PASSWORD_DEFAULT) . PHP_EOL;"
```

---

## 4. Despliegue de la aplicación

### 4.1. Despliegue en servidor LAMP (Linux + Apache + MySQL + PHP)

#### Paso 1 — Copiar los archivos al servidor

```bash
# Vía SCP desde la máquina local
scp -r TFG/ usuario@servidor:/var/www/

# O vía git si el repositorio está en un sistema de control de versiones
ssh usuario@servidor
cd /var/www
git clone <url-del-repositorio> coopsanjuanbautista
```

#### Paso 2 — Ajustar permisos

```bash
sudo chown -R www-data:www-data /var/www/coopsanjuanbautista
sudo find /var/www/coopsanjuanbautista -type d -exec chmod 755 {} \;
sudo find /var/www/coopsanjuanbautista -type f -exec chmod 644 {} \;
sudo chmod 775 /var/www/coopsanjuanbautista/logs
sudo chmod 775 /var/www/coopsanjuanbautista/logs/emails
```

#### Paso 3 — Configurar el VirtualHost de Apache

```apache
<VirtualHost *:80>
    ServerName coopsanjuanbautista.es
    ServerAlias www.coopsanjuanbautista.es
    DocumentRoot /var/www/coopsanjuanbautista

    <Directory /var/www/coopsanjuanbautista>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Bloquear acceso directo a la configuración y a los scripts de scratch
    <Directory /var/www/coopsanjuanbautista/config>
        Require all denied
    </Directory>
    <Directory /var/www/coopsanjuanbautista/scratch>
        Require all denied
    </Directory>
    <Directory /var/www/coopsanjuanbautista/logs>
        Require all denied
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/sjb-error.log
    CustomLog ${APACHE_LOG_DIR}/sjb-access.log combined
</VirtualHost>
```

Habilitar el sitio y recargar Apache:

```bash
sudo a2ensite coopsanjuanbautista.conf
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

#### Paso 4 — Configurar HTTPS con Let's Encrypt

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d coopsanjuanbautista.es -d www.coopsanjuanbautista.es
```

Certbot añadirá automáticamente la redirección 301 de HTTP a HTTPS y configurará la renovación automática.

### 4.2. Despliegue en XAMPP (entorno de desarrollo / Windows)

1. Copiar la carpeta `TFG/` a `C:\xampp\htdocs\`.
2. Iniciar Apache y MySQL desde el panel de control de XAMPP.
3. Importar el esquema desde `http://localhost/phpmyadmin` (ver sección 3.3, Opción B).
4. Acceder a `http://localhost/TFG/`.

> En XAMPP, las credenciales por defecto del archivo `config/db.php` (`root` sin contraseña) ya son válidas. **Nunca usar esta configuración en producción.**

### 4.3. Configuración recomendada de PHP en producción

En `php.ini`:

```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
session.cookie_httponly = 1
session.cookie_secure = 1          ; Solo HTTPS
session.use_strict_mode = 1
session.cookie_samesite = "Lax"
expose_php = Off
upload_max_filesize = 8M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
```

---

## 5. Configuración del servicio de correo

El proyecto envía correos en tres flujos: confirmación de reserva de visita ([api/reservar_visita.php](../api/reservar_visita.php)), notificaciones del operario y recuperación de contraseña ([auth/recuperar_password.php](../auth/recuperar_password.php)). La configuración se encuentra en [config/email.php](../config/email.php) y la lógica de envío en [core/mailer.php](../core/mailer.php).

Para entornos de desarrollo, los correos se almacenan como archivos `.eml` en `logs/emails/` para inspección visual sin necesidad de un servidor SMTP real.

Para producción, configurar un servidor SMTP (por ejemplo, el de la propia cooperativa o un proveedor como SendGrid, Mailjet o SMTP2GO) en `config/email.php` con las credenciales correspondientes.

---

## 6. Verificación del despliegue

Tras el despliegue, ejecutar la siguiente lista de comprobación:

| Prueba | Comando / URL | Resultado esperado |
|--------|---------------|-------------------|
| Conexión a la BD | `http://servidor/` | Carga el index sin errores PDO |
| Vistas SQL | `SELECT * FROM v_resumen_socios LIMIT 1;` | Devuelve filas o vacío sin error |
| Login admin | `http://servidor/auth/login.php` | Redirige a `admin/index.php` |
| API carrito | DevTools → Network al añadir un producto | HTTP 200, JSON `{"ok":true,...}` |
| Generación de PDF | Compra → "Descargar factura" | PDF se descarga correctamente |
| PWA | Lighthouse en Chrome DevTools | Manifiesto válido, SW registrado |
| HTTPS | `https://servidor/` | Candado verde, sin *mixed content* |

---

## 7. Mantenimiento y operaciones

### 7.1. Copias de seguridad

Programar un cron diario para volcado de la base de datos:

```bash
# /etc/cron.daily/sjb-backup
#!/bin/bash
BACKUP_DIR="/var/backups/sjb"
DATE=$(date +%Y%m%d-%H%M%S)
mkdir -p "$BACKUP_DIR"
mysqldump -u sjb_app -p'<PASSWORD>' \
  --single-transaction --routines --triggers \
  cooperativa_sjb | gzip > "$BACKUP_DIR/cooperativa_sjb_$DATE.sql.gz"
# Conservar 30 días
find "$BACKUP_DIR" -name 'cooperativa_sjb_*.sql.gz' -mtime +30 -delete
```

Hacer ejecutable: `sudo chmod +x /etc/cron.daily/sjb-backup`.

### 7.2. Rotación de logs

```
# /etc/logrotate.d/sjb
/var/log/apache2/sjb-*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
}
```

### 7.3. Actualización del catálogo

Las imágenes de productos deben colocarse en [assets/img/](../assets/img/) con nombre coincidente con el campo `productos.imagen`. Resolución recomendada: 800×800 px, formato PNG con fondo transparente o JPEG optimizado.

---

## 8. Resolución de problemas

| Síntoma | Causa probable | Solución |
|---------|---------------|----------|
| Error 500 al abrir cualquier página | Extensión PDO no cargada o credenciales BD incorrectas | Revisar `error_log` de Apache; comprobar `config/db.php` |
| Caracteres `?` en facturas PDF | Texto UTF-8 no convertido antes de pasar a FPDF | Verificar que `utf8_decode()` se aplica en `Cell()`/`MultiCell()` |
| "Token CSRF inválido" en formularios | Cookie de sesión expirada o navegador sin cookies | Recargar la página; revisar `session.gc_maxlifetime` |
| Carrito vacío tras login | Cookies de sesión bloqueadas | Habilitar cookies; revisar `session.cookie_samesite` |
| Stock en negativo | Campo `stock` modificado fuera de la app | Restaurar desde backup; investigar; nunca tocar `productos.stock` directamente: usar `admin/stock.php` |
| El service worker no se registra | Sitio servido por HTTP en lugar de HTTPS | Forzar HTTPS (los SW solo funcionan sobre HTTPS, salvo en `localhost`) |
| Reservas de visita en lunes aceptadas | Validación cliente saltada | El servidor también debe rechazarlas; revisar [api/reservar_visita.php](../api/reservar_visita.php) |

---

*Anexo técnico al PFC. Documento dirigido al administrador del sistema responsable del despliegue, mantenimiento y resolución de incidencias en producción.*
