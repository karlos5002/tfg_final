<?php
// Cambia el rol de un usuario. Sólo accesible por admin.
// Entrada JSON: { id_usuario, nuevo_rol, csrf_token }

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => true, 'mensaje' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Aceptamos JSON (fetch normal) o $_POST (fallback de form clásico)
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;

// hash_equals: comparación en tiempo constante, evita timing attacks contra el token
if (!hash_equals($_SESSION['csrf_token'] ?? '', $payload['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
    exit;
}

$id_usuario = filter_var($payload['id_usuario'] ?? null, FILTER_VALIDATE_INT);
$nuevo_rol  = is_string($payload['nuevo_rol'] ?? null) ? $payload['nuevo_rol'] : '';

if (!$id_usuario || $id_usuario <= 0) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => 'ID de usuario inválido.']);
    exit;
}

// Whitelist explícita: MySQL puede silenciar un ENUM inválido según sql_mode
$ROLES_VALIDOS = ['cliente', 'operario', 'socio', 'admin'];
if (!in_array($nuevo_rol, $ROLES_VALIDOS, true)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => 'Rol no válido.']);
    exit;
}

// Anti-lockout: si el único admin se cambiase a sí mismo a cliente, nadie podría
// volver a entrar. Forzamos a que sea otro admin quien lo haga.
if ($id_usuario === (int) ($_SESSION['usuario_id'] ?? 0)) {
    http_response_code(409);
    echo json_encode(['error' => true, 'mensaje' => 'No puedes modificar tu propio rol. Pide a otro administrador que lo haga.']);
    exit;
}

try {
    $pdo = getConexion();

    $chk = $pdo->prepare('SELECT id, rol, dni, apellidos FROM usuarios WHERE id = :id');
    $chk->execute([':id' => $id_usuario]);
    $actual = $chk->fetch();

    if (!$actual) {
        http_response_code(404);
        echo json_encode(['error' => true, 'mensaje' => 'Usuario no encontrado.']);
        exit;
    }

    if ($actual['rol'] === $nuevo_rol) {
        echo json_encode([
            'error' => false,
            'mensaje' => 'El usuario ya tenía ese rol. No se realizaron cambios.',
            'sin_cambio' => true,
            'rol_nuevo' => $nuevo_rol,
            'id_usuario' => $id_usuario,
        ]);
        exit;
    }

    // Roles con ficha (socio/admin/operario) exigen DNI + apellidos: socios
    // por los albaranes a su nombre, admin/operario porque son personal con
    // contrato laboral en la cooperativa.
    if (in_array($nuevo_rol, ['socio', 'admin', 'operario'], true)) {
        $faltan = [];
        if (empty($actual['dni']))       $faltan[] = 'dni';
        if (empty($actual['apellidos'])) $faltan[] = 'apellidos';

        if (!empty($faltan)) {
            http_response_code(422);
            echo json_encode([
                'error' => true,
                'mensaje' => 'Para promocionar a ' . $nuevo_rol . ' el usuario debe tener completos: ' . implode(', ', $faltan) . '.',
                'datos_faltantes' => $faltan,
            ]);
            exit;
        }
    }

    $upd = $pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id');
    $upd->execute([':rol' => $nuevo_rol, ':id' => $id_usuario]);

    error_log(sprintf('ROLE_CHANGE | admin_id=%d cambió usuario_id=%d de %s a %s',
        (int) $_SESSION['usuario_id'], $id_usuario, $actual['rol'], $nuevo_rol));

    echo json_encode([
        'error' => false,
        'mensaje' => 'Rol actualizado correctamente.',
        'rol_nuevo' => $nuevo_rol,
        'rol_previo' => $actual['rol'],
        'id_usuario' => $id_usuario,
    ]);

} catch (PDOException $e) {
    error_log('Error en actualizar_rol.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno al actualizar el rol.']);
}
