<?php
// ============================================================================
// PLANTILLA DE SECRETOS — copia este archivo a "secrets.local.php"
// ============================================================================
// Cómo usar:
//   1. cp config/secrets.example.php config/secrets.local.php
//   2. Edita secrets.local.php con tus claves reales.
//   3. NO subas secrets.local.php a git ni a Hostinger (ya está en .gitignore).
//
// El archivo se carga automáticamente desde config/env.php al inicio de cada
// petición, ANTES que db.php / stripe_config.php / email.php — así sus
// `if (!defined(...))` ven las constantes ya definidas y no las pisan.
//
// En Hostinger puedes seguir dos estrategias:
//   A) Subir directamente este archivo (renombrado a secrets.local.php) por
//      FTP — es la opción más cómoda y tu repositorio queda limpio.
//   B) Editar las constantes de producción dentro de db.php / email.php /
//      stripe_config.php directamente en el editor de archivos de Hostinger.
// ============================================================================


// ╔════════════════════════════════════════════════════════════════════════╗
// ║  BASE DE DATOS                                                         ║
// ╚════════════════════════════════════════════════════════════════════════╝
// hPanel → Bases de datos → Listas de bases de datos MySQL
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'u123456789_cooperativa');
// define('DB_USER', 'u123456789_admin');
// define('DB_PASS', 'mi-contraseña-fuerte');


// ╔════════════════════════════════════════════════════════════════════════╗
// ║  STRIPE                                                                ║
// ╚════════════════════════════════════════════════════════════════════════╝
// Dashboard Stripe → Developers → API keys
// Para producción usa pk_live_... / sk_live_... (solo cuando salgas de tests).
// define('STRIPE_PUBLISHABLE_KEY', 'pk_live_xxxxxxxxxxxxxxxxx');
// define('STRIPE_SECRET_KEY',     'sk_live_xxxxxxxxxxxxxxxxx');


// ╔════════════════════════════════════════════════════════════════════════╗
// ║  SMTP (envío de emails)                                                ║
// ╚════════════════════════════════════════════════════════════════════════╝
// hPanel → Emails → Cuentas de email → "Configurar dispositivos"
// define('SMTP_HOST',   'smtp.hostinger.com');
// define('SMTP_PORT',   465);                       // 465 = SSL · 587 = TLS
// define('SMTP_SECURE', 'ssl');                     // 'ssl' o 'tls'
// define('SMTP_USER',   'no-responder@tudominio.com');
// define('SMTP_PASS',   'la-contraseña-de-la-cuenta-de-email');
