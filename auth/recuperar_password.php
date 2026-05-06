<?php
// Restablecimiento simplificado: el usuario introduce email + nueva contraseña.
// En producción se haría con token por email; aquí queda como autorrescate.

session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password  = $_POST['password']  ?? '';
$password2 = $_POST['password2'] ?? '';

$abortar = function ($payload) {
    $_SESSION['recuperar_error'] = is_array($payload) ? $payload : ['key' => $payload];
    header('Location: ../index.php#recuperarModal');
    exit;
};

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $abortar('auth.email_invalid');
if (empty($password) || strlen($password) < 4)                   $abortar('auth.recover_password_short');
if ($password !== $password2)                                    $abortar('auth.passwords_dont_match');

try {
    $pdo = getConexion();

    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1');
    $chk->execute([':email' => $email]);
    $usuario = $chk->fetch();

    if (!$usuario) {
        $abortar('auth.account_not_found');
    }

    $upd = $pdo->prepare('UPDATE usuarios SET password = :p WHERE id = :id');
    $upd->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $usuario['id']]);

    $_SESSION['login_exito'] = ['key' => 'auth.password_updated'];
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    error_log('Error en recuperar_password.php: ' . $e->getMessage());
    $abortar('auth.server_error');
}
