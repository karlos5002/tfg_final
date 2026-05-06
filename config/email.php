<?php
// ============================================================================
// CONFIGURACIÓN DE ENVÍO DE EMAILS
// ============================================================================
//   - En LOCAL (XAMPP) se usa modo "log": los emails se guardan como HTML en
//     logs/emails/ — útil para depurar plantillas sin SMTP real.
//   - En PRODUCCIÓN (Hostinger) se usa SMTP real con PHPMailer.
//
// HOSTINGER — pasos para activar SMTP:
//   1. hPanel → Emails → Cuentas de email → "Crear cuenta de email".
//      Crea, por ejemplo:  no-responder@tudominio.com
//   2. Una vez creada, hPanel → Emails → "Configurar dispositivos" te muestra:
//        - Servidor saliente:  smtp.hostinger.com
//        - Puerto:             465 (SSL) o 587 (TLS)
//        - Usuario:            la dirección completa (no-responder@tudominio.com)
//        - Contraseña:         la que pusiste al crear la cuenta
//   3. Pega esos valores en SMTP_USER / SMTP_PASS más abajo, o (mejor) en
//      config/secrets.local.php para no comprometerlos en repositorios.
// ============================================================================

require_once __DIR__ . '/env.php';

// ── Datos del remitente (válidos en local y producción) ──
define('EMAIL_FROM_ADDR',   'no-responder@coopsanjuanbautista.es');
define('EMAIL_FROM_NAME',   'Cooperativa San Juan Bautista');
define('EMAIL_REPLY_TO',    'info@coopsanjuanbautista.es');
define('EMAIL_SUBJECT_PFX', '[Cooperativa S.J.B.] ');

// ── Carpeta de logs (fallback / modo demo) ──
define('EMAIL_LOG_DIR', __DIR__ . '/../logs/emails/');

if (ES_LOCAL) {
    // ─── XAMPP local ─── solo guarda los HTML en disco
    define('EMAIL_MODE', 'log');

    // SMTP no se usa en local, pero las constantes deben existir para evitar
    // notices si alguien instancia PHPMailer accidentalmente.
    define('SMTP_HOST',       'smtp.hostinger.com');
    define('SMTP_PORT',       465);
    define('SMTP_SECURE',     'ssl');         // 'ssl' (465) o 'tls' (587)
    define('SMTP_USER',       '');
    define('SMTP_PASS',       '');
    define('SMTP_DEBUG_LEVEL', 0);
} else {
    // ─── Hostinger producción ───
    define('EMAIL_MODE', 'smtp');

    // Si secrets.local.php ya las definió, no las redefinimos.
    if (!defined('SMTP_HOST'))   define('SMTP_HOST',   'smtp.hostinger.com');
    if (!defined('SMTP_PORT'))   define('SMTP_PORT',   465);
    if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl');
    if (!defined('SMTP_USER'))   define('SMTP_USER',   'no-responder@tudominio.com');
    if (!defined('SMTP_PASS'))   define('SMTP_PASS',   'CAMBIAR_POR_PASS_REAL');

    // 0 = silencio · 2 = mostrar diálogo SMTP (solo para depurar problemas)
    if (!defined('SMTP_DEBUG_LEVEL')) define('SMTP_DEBUG_LEVEL', 0);
}
