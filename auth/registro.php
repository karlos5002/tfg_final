<?php
// Alta de un nuevo usuario desde el modal del index. Siempre con rol 'cliente';
// la promoción a 'socio'/'admin' la hace el admin desde admin_usuarios.php.

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$nombre    = trim($_POST['nombre']    ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$telefono  = trim($_POST['telefono']  ?? '');
$password  = $_POST['password']       ?? '';
$password2 = $_POST['password2']      ?? '';

// Helper para abortar con clave de traducción y volver al modal.
// Acepta clave (string) o ['key'=>..., 'args'=>[...]] para mensajes con
// parámetros — el frontend resuelve la traducción al imprimir el flash.
$abortar = function ($payload) {
    $_SESSION['registro_error'] = is_array($payload) ? $payload : ['key' => $payload];
    header('Location: ../index.php#registroModal');
    exit;
};

if (empty($nombre) || strlen($nombre) < 2)                       $abortar('auth.name_too_short');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $abortar('auth.email_invalid');
if (empty($password) || strlen($password) < 4)                   $abortar('auth.password_too_short');
if ($password !== $password2)                                    $abortar('auth.passwords_dont_match');

try {
    $pdo = getConexion();

    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        $abortar('auth.email_taken');
    }

    $stmt = $pdo->prepare('
        INSERT INTO usuarios (nombre, apellidos, email, password, telefono, rol, activo)
        VALUES (:nombre, :apellidos, :email, :password, :telefono, "cliente", 1)
    ');
    $stmt->execute([
        ':nombre'    => $nombre,
        ':apellidos' => $apellidos ?: null,
        ':email'     => $email,
        ':password'  => password_hash($password, PASSWORD_DEFAULT),
        ':telefono'  => $telefono ?: null,
    ]);

    // Auto-login tras registro
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $pdo->lastInsertId();
    $_SESSION['nombre']     = $nombre;
    $_SESSION['apellidos']  = $apellidos;
    $_SESSION['email']      = $email;
    $_SESSION['rol']        = 'cliente';

    // Email de bienvenida — sin PDF, sólo HTML. Si falla no abortamos:
    // el usuario ya está creado y logueado, solo se pierde la notificación.
    try {
        enviarEmail(
            $email,
            '¡Bienvenido a la Cooperativa!',
            emailBienvenidaUsuario(['nombre' => $nombre, 'email' => $email])
        );
    } catch (\Throwable $e) {
        error_log('[registro] No se pudo enviar email de bienvenida a ' . $email . ': ' . $e->getMessage());
    }

    $_SESSION['registro_exito'] = ['key' => 'auth.welcome_new', 'args' => [$nombre]];
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    error_log('Error en registro.php: ' . $e->getMessage());
    $abortar('auth.register_server_error');
}
