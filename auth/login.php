<?php
// Procesa el POST del modal de login y arranca la sesión según rol.

session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';   // sin filter: no romper caracteres válidos

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = ['key' => 'auth.fields_required'];
    header('Location: ../index.php#loginModal');
    exit;
}

try {
    $pdo = getConexion();

    $stmt = $pdo->prepare('
        SELECT id, nombre, apellidos, email, password, rol, activo
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password'])) {

        if (!$usuario['activo']) {
            $_SESSION['login_error'] = ['key' => 'auth.account_disabled'];
            header('Location: ../index.php');
            exit;
        }

        // Nuevo session id tras el login → mitiga session fixation
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['apellidos']  = $usuario['apellidos'];
        $_SESSION['email']      = $usuario['email'];
        $_SESSION['rol']        = $usuario['rol'];

        // Cada rol tiene su landing page propia
        if ($usuario['rol'] === 'admin') {
            header('Location: ../admin/index.php');
        } elseif ($usuario['rol'] === 'operario') {
            header('Location: ../operario.php');
        } elseif ($usuario['rol'] === 'socio') {
            header('Location: ../panel_socio.php');
        } else {
            $_SESSION['login_exito'] = ['key' => 'auth.welcome_back', 'args' => [$usuario['nombre']]];
            header('Location: ../index.php');
        }
        exit;
    }

    // Mensaje genérico: no revelamos si fue email o password lo que falló
    $_SESSION['login_error'] = ['key' => 'auth.bad_credentials'];
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    error_log('Error de login: ' . $e->getMessage());
    $_SESSION['login_error'] = ['key' => 'auth.server_error'];
    header('Location: ../index.php');
    exit;
}
