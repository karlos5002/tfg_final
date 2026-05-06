<?php
/**
 * ============================================================================
 * Sistema de internacionalización ligero
 * ============================================================================
 * Carga el diccionario del idioma activo (lang/es.php o lang/en.php) y expone
 * tres helpers globales:
 *
 *   t($clave, $default = null)  → traducción de una clave (string)
 *   tf($clave, ...$args)        → traducción con sprintf (placeholders %s, %d)
 *   lang_actual()               → código del idioma activo ('es' | 'en')
 *   lang_url($codigo)           → URL actual con ?lang=$codigo (preserva GET)
 *
 * Orden de detección del idioma:
 *   1. ?lang=xx en la URL  → cambio explícito + persistencia (sesión + cookie)
 *   2. $_SESSION['lang']    → preferencia del usuario en esta sesión
 *   3. $_COOKIE['sjb_lang'] → preferencia recordada entre sesiones (1 año)
 *   4. I18N_LANG_DEFAULT    → 'es' (idioma de fallback)
 *
 * Si la clave no existe en el diccionario, t() devuelve la propia clave: así
 * los huecos de traducción son visibles a simple vista en la página.
 * ============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('I18N_LANGS_DISPONIBLES')) {
    define('I18N_LANGS_DISPONIBLES', ['es', 'en']);
    define('I18N_LANG_DEFAULT',      'es');
    define('I18N_COOKIE_NAME',       'sjb_lang');
    define('I18N_COOKIE_DURATION',   60 * 60 * 24 * 365); // 1 año
}

// 1) Cambio explícito vía ?lang=xx — persistimos en sesión y cookie
if (isset($_GET['lang']) && in_array($_GET['lang'], I18N_LANGS_DISPONIBLES, true)) {
    $_SESSION['lang'] = $_GET['lang'];
    // path=/ para que la cookie viaje en todas las URLs del sitio
    setcookie(I18N_COOKIE_NAME, $_GET['lang'],
              time() + I18N_COOKIE_DURATION, '/');
    $_COOKIE[I18N_COOKIE_NAME] = $_GET['lang']; // disponible ya en esta petición
}

// 2/3/4) Resolver idioma actual
$_idioma = $_SESSION['lang']
        ?? $_COOKIE[I18N_COOKIE_NAME]
        ?? I18N_LANG_DEFAULT;

if (!in_array($_idioma, I18N_LANGS_DISPONIBLES, true)) {
    $_idioma = I18N_LANG_DEFAULT;
}

// Cargar diccionario. Si falta el archivo, dejamos array vacío y t() devolverá
// las claves crudas — útil para detectar visualmente fallos de despliegue.
$_dictPath = dirname(__DIR__) . '/lang/' . $_idioma . '.php';
$GLOBALS['_lang']        = file_exists($_dictPath) ? (require $_dictPath) : [];
$GLOBALS['_lang_actual'] = $_idioma;

unset($_idioma, $_dictPath);


/**
 * Traduce una clave del diccionario actual.
 *
 * @param string      $clave   Clave de traducción (ej. 'nav.inicio')
 * @param string|null $default Texto a devolver si la clave no existe.
 *                              Por defecto, la propia clave (ayuda a depurar).
 */
function t(string $clave, ?string $default = null): string
{
    return $GLOBALS['_lang'][$clave] ?? ($default ?? $clave);
}

/**
 * Traducción con sprintf — útil para cadenas con placeholders.
 * Ejemplo: tf('nav.salir_title', $nombreUsuario)
 */
function tf(string $clave, ...$args): string
{
    $plantilla = $GLOBALS['_lang'][$clave] ?? $clave;
    return $args ? vsprintf($plantilla, $args) : $plantilla;
}

/**
 * Devuelve el código del idioma actualmente activo ('es' | 'en').
 */
function lang_actual(): string
{
    return $GLOBALS['_lang_actual'] ?? I18N_LANG_DEFAULT;
}

/**
 * URL actual con ?lang=$codigo — preserva el resto de parámetros GET.
 * Pensada para los <a> del selector de idioma del navbar.
 */
function lang_url(string $codigo): string
{
    $params = $_GET;
    $params['lang'] = $codigo;
    $base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $base . '?' . http_build_query($params);
}

/**
 * Locale BCP-47 del idioma activo. Útil para Intl en JavaScript
 * (toLocaleString) y para `<html lang="...">`.
 */
function lang_locale(): string
{
    return lang_actual() === 'en' ? 'en-GB' : 'es-ES';
}

/**
 * Formatea una fecha respetando el idioma activo.
 * En ES: "15/03/2026" — en EN: "15 Mar 2026" (sin ambigüedad mes/día).
 *
 * @param string|int $fecha Cadena parseable por strtotime o timestamp.
 * @param bool $conHora     Si es true añade la hora en formato local.
 */
function fmt_fecha($fecha, bool $conHora = false): string
{
    $ts = is_int($fecha) ? $fecha : strtotime((string) $fecha);
    if ($ts === false) return (string) $fecha;

    if (lang_actual() === 'en') {
        return $conHora ? date('j M Y, H:i', $ts) : date('j M Y', $ts);
    }
    return $conHora ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

/**
 * Formatea un importe en euros respetando la convención del idioma:
 *   ES → "1.234,56 €"   ·   EN → "€1,234.56"
 */
function fmt_precio($importe): string
{
    $valor = (float) $importe;
    if (lang_actual() === 'en') {
        return '€' . number_format($valor, 2, '.', ',');
    }
    return number_format($valor, 2, ',', '.') . ' €';
}

/**
 * Lee y consume un flash message de sesión.
 *
 * El handler que lo escribió pudo guardar:
 *   • Una cadena ya traducida (legacy)              — se devuelve tal cual.
 *   • Un array ['key'=>'auth.x', 'args'=>[$nombre]] — se traduce ahora.
 *
 * Esta indirección permite que un usuario que cambia de idioma entre el POST
 * y el siguiente GET vea el mensaje en el idioma actual.
 */
function flash_get(string $sessionKey): string
{
    if (!isset($_SESSION[$sessionKey])) return '';
    $val = $_SESSION[$sessionKey];
    unset($_SESSION[$sessionKey]);

    if (is_array($val) && isset($val['key'])) {
        $args = $val['args'] ?? [];
        return $args ? tf($val['key'], ...$args) : t($val['key']);
    }
    return is_string($val) ? $val : '';
}
