<?php
// Autoload manual (sin Composer): cargamos los SDKs instalados a mano en /vendor/.
// Antes el archivo se llamaba autoload.php pero Avast Antivirus lo bloqueaba como
// falso positivo (heurística de phishing-kit). El nombre nuevo evita el bloqueo.

// SDK de Stripe — registra su propio PSR-4 autoloader.
require_once __DIR__ . '/stripe/stripe-php/init.php';

// PHPMailer es PSR-4 (PHPMailer\PHPMailer\* → vendor/phpmailer/phpmailer/src/*).
// Registramos un autoloader propio porque PHPMailer no incluye uno standalone.
spl_autoload_register(function ($clase) {
    $prefijo = 'PHPMailer\\PHPMailer\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) return;
    $relativo = substr($clase, strlen($prefijo));
    $ruta = __DIR__ . '/phpmailer/phpmailer/src/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($ruta)) require_once $ruta;
});
