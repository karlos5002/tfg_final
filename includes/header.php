<?php
// Cabecera HTML común. Lee título y meta-descripción de variables que la
// página padre puede sobrescribir antes del include.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Motor i18n: carga el diccionario del idioma activo y define t(), tf(),
// lang_actual() y lang_url(). Debe llamarse ANTES de los defaults de título
// y meta-description para poder traducirlos.
require_once __DIR__ . '/i18n.php';

$pageTitle       = $pageTitle       ?? t('meta.default_title');
$pageDescription = $pageDescription ?? t('meta.default_desc');

// Las páginas en subcarpetas (admin/) deben apuntar a assets/, manifest, etc.
// con un prefijo "../". Si la página padre no lo define, asumimos raíz.
$relRoot         = $relRoot         ?? '';

$estaLogueado = isset($_SESSION['usuario_id']);
$totalCarrito = 0;
if (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $totalCarrito += $item['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(lang_actual()) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="keywords"
        content="aceite de oliva virgen extra, AOVE, almazara, Badajoz, Herrera del Duque, cooperativa, aceite premium, San Juan Bautista, aceite ecológico, aceite gourmet, Monterrubio">
    <meta name="author" content="Cooperativa San Juan Bautista">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title"       content="Cooperativa San Juan Bautista | AOVE Premium">
    <meta property="og:description" content="Descubre nuestro aceite de oliva virgen extra de cosecha propia. Tradición y calidad desde Herrera del Duque, Badajoz.">
    <meta property="og:type"        content="website">
    <meta property="og:locale"      content="<?= lang_actual() === 'en' ? 'en_GB' : 'es_ES' ?>">
    <meta property="og:url"         content="https://www.coopsanjuanbautista.es">

    <!-- Favicon SVG con emoji de oliva como fallback portable -->
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🫒</text></svg>">

    <!-- PWA -->
    <link rel="manifest" href="<?= $relRoot ?>manifest.json">
    <meta name="theme-color" content="#2C4C3B">
    <meta name="apple-mobile-web-app-capable"          content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title"            content="Almazara">
    <link rel="apple-touch-icon" href="<?= $relRoot ?>icon-192.png">

    <!-- Tipografías: Playfair (titulares) + Inter (cuerpo) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <?php
        // Cache-busting con filemtime: la URL cambia sólo cuando el CSS cambia.
        // Con time() invalidaríamos la caché en cada petición (no queremos eso).
        $cssVer = @filemtime(__DIR__ . '/../assets/css/estilos.css') ?: '1';
    ?>
    <?php if (empty($esAdminPanel)): ?>
    <link rel="stylesheet" href="<?= $relRoot ?>assets/css/estilos.css?v=<?= $cssVer ?>">
    <?php endif; ?>
    <?php if (isset($extraHead)) { echo $extraHead; } ?>
</head>

<body>
