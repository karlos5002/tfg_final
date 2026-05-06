<?php
// ============================================================================
// DETECCIÓN DE ENTORNO — local (XAMPP) vs producción (Hostinger)
// ============================================================================
// Este archivo se incluye desde db.php / stripe_config.php / email.php
// para que el mismo código funcione en local y en producción sin tener
// que editar nada al desplegar.
//
// Cómo funciona:
//   1. Detecta si el servidor es local mirando SERVER_NAME / HTTP_HOST.
//      Cualquier variante "localhost", "127.*", ".local", ".test" → local.
//   2. Expone las constantes ES_LOCAL (bool) y APP_URL (string sin / final).
//   3. Si existe config/secrets.local.php (NO subir a git ni a Hostinger),
//      se carga al final para sobrescribir las claves sensibles.
//      En producción, secrets.local.php NO existe → se usan las constantes
//      de producción definidas más abajo en cada archivo.
//
// IMPORTANTE: este archivo NO contiene secretos. Solo lógica de entorno.
// Los secretos van en config/secrets.local.php (local) o se editan
// directamente en los archivos de Hostinger tras la subida.
// ============================================================================

if (defined('ENV_CARGADO')) return;
define('ENV_CARGADO', true);

// ── Detección del nombre de host actual ──
// En CLI (cron, composer) $_SERVER['HTTP_HOST'] no existe → usamos '' como default.
$hostActual = strtolower($_SERVER['HTTP_HOST']
                       ?? $_SERVER['SERVER_NAME']
                       ?? '');

// ── ¿Estamos en local? ──
// Cualquier dominio "localhost", IP local 127.* o sufijo .local/.test cuenta.
$esLocal = (
    $hostActual === ''                          // CLI o tests
 || $hostActual === 'localhost'
 || str_starts_with($hostActual, 'localhost:')
 || str_starts_with($hostActual, '127.')
 || str_starts_with($hostActual, '192.168.')
 || str_ends_with($hostActual, '.local')
 || str_ends_with($hostActual, '.test')
);

define('ES_LOCAL', $esLocal);
define('ES_PRODUCCION', !$esLocal);

// ── URL base de la aplicación (sin barra final) ──
// Se usa en stripe_config.php (success_url / cancel_url) y plantillas de email.
if (ES_LOCAL) {
    // XAMPP: http://localhost/TFG
    define('APP_URL', 'http://localhost/TFG');
} else {
    // Hostinger: https + dominio real. Forzamos https siempre.
    // Si el sitio está accesible en www y sin www, usa el que tu Hostinger
    // marque como canónico — el .htaccess se encarga de redirigir.
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    define('APP_URL', $protocolo . '://' . $hostActual);
}

// ── Carga opcional de secretos locales ──
// En tu máquina, copia config/secrets.example.php a config/secrets.local.php
// y rellena las claves. El archivo está en .gitignore — nunca llega a Hostinger.
$rutaSecretos = __DIR__ . '/secrets.local.php';
if (is_file($rutaSecretos)) {
    require_once $rutaSecretos;
}
