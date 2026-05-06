<?php
// Completa la ficha (DNI, apellidos, teléfono) y cambia el rol en una sola
// transacción. Lo usa admin_usuarios.php cuando un usuario sin DNI se quiere
// promocionar a socio/admin: en lugar de bloquear, abre un modal para rellenar
// los datos y hacer ambos UPDATE atómicos.

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => true, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;

if (!hash_equals($_SESSION['csrf_token'] ?? '', $payload['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
    exit;
}

$id_usuario = filter_var($payload['id_usuario'] ?? null, FILTER_VALIDATE_INT);
$nuevo_rol  = is_string($payload['nuevo_rol'] ?? null) ? $payload['nuevo_rol'] : '';
$dni        = strtoupper(trim((string) ($payload['dni'] ?? '')));
$apellidos  = trim((string) ($payload['apellidos'] ?? ''));
$telefono   = trim((string) ($payload['telefono']  ?? ''));

$ROLES_VALIDOS = ['cliente', 'operario', 'socio', 'admin'];
$errores = [];

if (!$id_usuario || $id_usuario <= 0)             $errores[] = 'ID de usuario inválido.';
if (!in_array($nuevo_rol, $ROLES_VALIDOS, true))  $errores[] = 'Rol no válido.';

// DNI/NIE/CIF: aceptamos los tres para cubrir socios persona física y empresas
if (!preg_match('/^\d{8}[A-HJ-NP-TV-Z]$/', $dni)
 && !preg_match('/^[XYZ]\d{7}[A-HJ-NP-TV-Z]$/', $dni)
 && !preg_match('/^[ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J]$/', $dni)) {
    $errores[] = 'DNI/NIE/CIF con formato inválido (ej: 12345678A).';
}

if (mb_strlen($apellidos) < 2 || mb_strlen($apellidos) > 100) {
    $errores[] = 'Los apellidos deben tener entre 2 y 100 caracteres.';
}
if ($telefono !== '' && !preg_match('/^[0-9 +\-]{6,20}$/', $telefono)) {
    $errores[] = 'Teléfono con formato inválido.';
}

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => implode(' ', $errores), 'errores' => $errores]);
    exit;
}

if ($id_usuario === (int) ($_SESSION['usuario_id'] ?? 0)) {
    http_response_code(409);
    echo json_encode(['error' => true, 'mensaje' => 'No puedes modificar tu propio rol.']);
    exit;
}

try {
    $pdo = getConexion();

    // Comprobamos antes que el DNI no choque con otro usuario para devolver
    // un mensaje claro en vez de un 23000 críptico
    $chkDni = $pdo->prepare('SELECT id FROM usuarios WHERE dni = :dni AND id <> :id LIMIT 1');
    $chkDni->execute([':dni' => $dni, ':id' => $id_usuario]);
    if ($chkDni->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => true, 'mensaje' => 'Ya existe otro usuario con ese DNI. Verifica antes de continuar.']);
        exit;
    }

    $chkUsr = $pdo->prepare('SELECT id, rol FROM usuarios WHERE id = :id');
    $chkUsr->execute([':id' => $id_usuario]);
    $actual = $chkUsr->fetch();
    if (!$actual) {
        http_response_code(404);
        echo json_encode(['error' => true, 'mensaje' => 'Usuario no encontrado.']);
        exit;
    }

    // Datos + rol = unidad atómica. Si fallase el segundo UPDATE, el primero se revierte.
    $pdo->beginTransaction();

    $stmtDatos = $pdo->prepare('
        UPDATE usuarios
        SET dni       = :dni,
            apellidos = :ape,
            telefono  = COALESCE(NULLIF(:tel, ""), telefono)
        WHERE id = :id
    ');
    $stmtDatos->execute([':dni' => $dni, ':ape' => $apellidos, ':tel' => $telefono, ':id' => $id_usuario]);

    $stmtRol = $pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id');
    $stmtRol->execute([':rol' => $nuevo_rol, ':id' => $id_usuario]);

    $pdo->commit();

    error_log(sprintf('PROMOCION | admin_id=%d completó datos y promovió usuario_id=%d (%s -> %s) | dni=%s',
        (int) $_SESSION['usuario_id'], $id_usuario, $actual['rol'], $nuevo_rol, $dni));

    echo json_encode([
        'error'      => false,
        'mensaje'    => 'Usuario actualizado y promocionado correctamente.',
        'rol_nuevo'  => $nuevo_rol,
        'id_usuario' => $id_usuario,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Error en promocionar_socio.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno al actualizar el usuario.']);
}
