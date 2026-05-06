<?php
// ============================================================================
// SCRIPT DE DIAGNÓSTICO — borrar después de desplegar
// ============================================================================
// Súbelo a /public_html/ y abre en el navegador:
//   https://lavenderblush-mule-438482.hostingersite.com/test_deploy.php
//
// Te dirá exactamente qué falla. Una vez todo en verde, BÓRRALO.
// ============================================================================

// Mostrar TODOS los errores, sin esconderlos
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/html; charset=UTF-8');

$resultados = [];
$ok = fn(string $titulo, string $valor) => ['titulo' => $titulo, 'estado' => 'ok',   'valor' => $valor];
$ko = fn(string $titulo, string $valor) => ['titulo' => $titulo, 'estado' => 'ko',   'valor' => $valor];
$wn = fn(string $titulo, string $valor) => ['titulo' => $titulo, 'estado' => 'warn', 'valor' => $valor];

// ── 1. Versión PHP ──
$resultados[] = $ok('PHP version', PHP_VERSION);
if (version_compare(PHP_VERSION, '8.0', '<')) {
    $resultados[] = $ko('PHP 8 disponible', 'NO — Hostinger por defecto puede dar PHP 7.x. Cambia a 8.0+ en hPanel → Avanzado → Versión de PHP');
}

// ── 2. Cargar entorno ──
try {
    require_once __DIR__ . '/config/env.php';
    $resultados[] = $ok('config/env.php se carga', 'sí');
    $resultados[] = $ok('Entorno detectado', ES_PRODUCCION ? 'PRODUCCIÓN' : 'LOCAL (¡atención! debería ser PRODUCCIÓN)');
    $resultados[] = $ok('APP_URL', APP_URL);
    if (ES_LOCAL) {
        $resultados[] = $ko('Detección de entorno', 'Detectó LOCAL pero estás en Hostinger — revisa que HTTP_HOST sea correcto');
    }
} catch (\Throwable $e) {
    $resultados[] = $ko('config/env.php', $e->getMessage());
}

// ── 3. secrets.local.php cargado ──
$rutaSecrets = __DIR__ . '/config/secrets.local.php';
if (is_file($rutaSecrets)) {
    $resultados[] = $ok('config/secrets.local.php existe', 'sí');
} else {
    $resultados[] = $ko('config/secrets.local.php existe', 'NO — súbelo a /public_html/config/');
}

// ── 4. Constantes definidas ──
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'EMAIL_MODE'] as $c) {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/config/email.php';
    if (defined($c)) {
        $valor = constant($c);
        // Ocultar passwords
        if (in_array($c, ['DB_PASS', 'SMTP_PASS'])) {
            $valor = strlen($valor) > 0 ? '✓ definida (' . strlen($valor) . ' chars)' : '✗ vacía';
        }
        $resultados[] = $ok("Constante $c", (string) $valor);
    } else {
        $resultados[] = $ko("Constante $c", 'NO definida');
    }
}

// ── 5. Conexión a MySQL ──
try {
    $pdo = getConexion();
    $resultados[] = $ok('Conexión a MySQL', 'OK');

    // Tablas presentes
    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tablas) === 0) {
        $resultados[] = $ko('Tablas en la BD', 'NINGUNA — falta importar database/schema/cooperativa_sjb.sql en phpMyAdmin');
    } else {
        $resultados[] = $ok('Tablas en la BD', count($tablas) . ' tablas: ' . implode(', ', $tablas));

        // Usuarios
        if (in_array('usuarios', $tablas)) {
            $totalUsr   = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $totalAdmin = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
            $resultados[] = $ok('Usuarios en BD', "$totalUsr (de los cuales $totalAdmin admin)");

            if ($totalAdmin === 0) {
                $resultados[] = $ko('Cuenta admin', 'NINGUNA — el SQL importado no incluye admin. Crea uno (ver más abajo).');
            } else {
                $stmt = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'admin'");
                while ($r = $stmt->fetch()) {
                    $resultados[] = $ok("Admin #{$r['id']}", $r['nombre'] . ' · ' . $r['email']);
                }
            }
        }
    }
} catch (\Throwable $e) {
    $resultados[] = $ko('Conexión a MySQL', $e->getMessage());
}

// ── 6. Vendor / SDKs ──
$libsInit = __DIR__ . '/vendor/init_libs.php';
if (is_file($libsInit)) {
    require_once $libsInit;
    $resultados[] = $ok('vendor/init_libs.php', 'sí');
    $resultados[] = class_exists('\\Stripe\\Stripe')                      ? $ok('Stripe SDK',     'cargado') : $ko('Stripe SDK',     'NO cargado');
    $resultados[] = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')     ? $ok('PHPMailer',      'cargado') : $ko('PHPMailer',      'NO cargado');
    $resultados[] = class_exists('FPDF')                                  ? $ok('FPDF',           'cargado') : $wn('FPDF',           'no auto-cargado (se carga al usarlo)');
} else {
    $resultados[] = $ko('vendor/init_libs.php', 'NO existe — sube la carpeta vendor/ entera');
}

// ── 7. SMTP — solo conecta, no envía nada ──
if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && defined('SMTP_HOST')) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'tls')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Timeout = 10;
        if ($mail->smtpConnect()) {
            $resultados[] = $ok('SMTP Gmail (conexión + auth)', 'OK');
            $mail->smtpClose();
        } else {
            $resultados[] = $ko('SMTP Gmail', 'no se pudo conectar');
        }
    } catch (\Throwable $e) {
        $resultados[] = $ko('SMTP Gmail', $e->getMessage());
    }
}

// ── 8. Permisos de logs/emails ──
$logDir = __DIR__ . '/logs/emails';
if (is_dir($logDir)) {
    $resultados[] = is_writable($logDir)
        ? $ok('logs/emails escribible', 'sí')
        : $wn('logs/emails escribible', 'NO — no es crítico, en producción se usa SMTP');
} else {
    $resultados[] = $wn('logs/emails', 'no existe (ok si tu carpeta logs/ no se subió)');
}

// ── 9. .htaccess activo ──
$htaccess = __DIR__ . '/.htaccess';
$resultados[] = is_file($htaccess) ? $ok('.htaccess presente', 'sí') : $ko('.htaccess presente', 'NO — falta el .htaccess');

// ── 10. Sesión PHP ──
session_start();
$resultados[] = isset($_SESSION) ? $ok('Sesiones PHP', 'funcionan') : $ko('Sesiones PHP', 'NO funcionan');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Diagnóstico de despliegue</title>
<style>
body { font-family: ui-monospace, Consolas, monospace; background:#1e1e1e; color:#ddd; padding:30px; max-width:1100px; margin:auto; }
h1   { color:#D4AF37; font-family:Georgia,serif; }
table{ width:100%; border-collapse:collapse; margin:18px 0; }
td   { padding:9px 12px; border-bottom:1px solid #333; vertical-align:top; }
td:first-child{ width:30%; color:#bbb; }
.ok   td:nth-child(2):before { content:"✅ "; }
.ko   td:nth-child(2):before { content:"❌ "; }
.warn td:nth-child(2):before { content:"⚠️ "; }
.ok   { background:#0d2818; }
.ko   { background:#3a1414; }
.warn { background:#332a11; }
.ko td:nth-child(2)   { color:#ffb4b4; font-weight:600; }
.ok td:nth-child(2)   { color:#9be29b; }
.warn td:nth-child(2) { color:#ffd56b; }
.banner { padding:18px; border-radius:8px; margin-bottom:18px; font-weight:600; text-align:center; font-size:18px; }
.banner.ok  { background:#0d2818; color:#9be29b; }
.banner.ko  { background:#3a1414; color:#ffb4b4; }
small { color:#888; }
pre { background:#000; padding:10px; border-radius:6px; overflow:auto; }
</style>
</head>
<body>
<h1>🔍 Diagnóstico de despliegue · Cooperativa SJB</h1>
<p><small>Fecha: <?= date('d/m/Y H:i:s') ?> · Host: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '?') ?></small></p>

<?php
$totalKo = count(array_filter($resultados, fn($r) => $r['estado'] === 'ko'));
$totalWn = count(array_filter($resultados, fn($r) => $r['estado'] === 'warn'));
?>

<div class="banner <?= $totalKo === 0 ? 'ok' : 'ko' ?>">
<?php if ($totalKo === 0): ?>
    ✅ Todo en verde — borra este archivo (test_deploy.php) por seguridad.
<?php else: ?>
    ❌ Hay <?= $totalKo ?> error(es) crítico(s)<?= $totalWn ? " y $totalWn aviso(s)" : '' ?>. Mira la tabla ↓
<?php endif; ?>
</div>

<table>
<?php foreach ($resultados as $r): ?>
    <tr class="<?= $r['estado'] ?>">
        <td><?= htmlspecialchars($r['titulo']) ?></td>
        <td><?= nl2br(htmlspecialchars($r['valor'])) ?></td>
    </tr>
<?php endforeach; ?>
</table>

<?php
// Si no hay admin, ofrecer crear uno
if (!empty($pdo) && in_array('usuarios', $tablas ?? []) && ($totalAdmin ?? 1) === 0):
?>
<div style="background:#332a11;padding:18px;border-radius:8px;margin-top:20px;">
<strong style="color:#D4AF37;">⚙️ Crear cuenta admin de emergencia</strong><br>
<small>Llama a este archivo con <code>?create_admin=1&email=tu@email.com&pass=Loquequieras</code> para crear un admin con esos datos.</small>
<?php
if (isset($_GET['create_admin'], $_GET['email'], $_GET['pass'])
    && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellidos, email, password, rol, activo)
                               VALUES (:n, :a, :e, :p, 'admin', 1)
                               ON DUPLICATE KEY UPDATE password = :p, rol = 'admin'");
        $stmt->execute([
            ':n' => 'Admin',
            ':a' => 'Cooperativa',
            ':e' => $_GET['email'],
            ':p' => password_hash($_GET['pass'], PASSWORD_DEFAULT),
        ]);
        echo "<p style='color:#9be29b'>✅ Admin creado/actualizado: " . htmlspecialchars($_GET['email']) . " · contraseña: " . htmlspecialchars($_GET['pass']) . "</p>";
    } catch (\Throwable $e) {
        echo "<p style='color:#ffb4b4'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>
</div>
<?php endif; ?>

<p style="margin-top:30px;"><small>⚠️ Borra este archivo (<code>test_deploy.php</code>) cuando termines el despliegue. Expone información sensible.</small></p>
</body>
</html>
