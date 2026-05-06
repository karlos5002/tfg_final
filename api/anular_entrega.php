<?php
// ============================================================================
// ANULAR ENTREGA — soft-delete con motivo
// ============================================================================
// Solo el admin puede anular. Marcamos la fila como anulada=1, registramos
// motivo + fecha + id_admin, y enviamos email al socio para que sepa que
// su albarán deja de tener validez. Nunca borramos físicamente la fila:
// las entregas son documentos contables y el albarán PDF ya está en el
// buzón del socio.
//
// Se llama por POST con JSON:
//   { csrf_token: "...", id_entrega: 123, motivo: "Error en kilos" }
// ============================================================================

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
require_once __DIR__ . '/../core/mailer.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;

if (!hash_equals($_SESSION['csrf_token'] ?? '', $payload['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
    exit;
}

$id_entrega = filter_var($payload['id_entrega'] ?? null, FILTER_VALIDATE_INT);
$motivo     = trim((string) ($payload['motivo'] ?? ''));

$errores = [];
if (!$id_entrega || $id_entrega <= 0)        $errores[] = 'ID de entrega inválido.';
if (mb_strlen($motivo) < 5)                  $errores[] = 'El motivo es obligatorio (mínimo 5 caracteres).';
if (mb_strlen($motivo) > 255)                $errores[] = 'El motivo es demasiado largo (máx 255 caracteres).';

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => implode(' ', $errores), 'errores' => $errores]);
    exit;
}

$idAdmin = (int) ($_SESSION['usuario_id'] ?? 0);

try {
    $pdo = getConexion();

    // Cargar la entrega con datos del socio para el email + verificar estado.
    $stmt = $pdo->prepare('
        SELECT e.id, e.fecha_entrega, e.kilos_aceituna, e.rendimiento, e.anulada,
               u.id AS socio_id, u.nombre AS socio_nombre, u.email AS socio_email
        FROM entregas e
        INNER JOIN usuarios u ON u.id = e.id_socio
        WHERE e.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id_entrega]);
    $entrega = $stmt->fetch();

    if (!$entrega) {
        http_response_code(404);
        echo json_encode(['error' => true, 'mensaje' => 'Entrega no encontrada.']);
        exit;
    }
    if ((int) $entrega['anulada'] === 1) {
        http_response_code(409);
        echo json_encode(['error' => true, 'mensaje' => 'Esta entrega ya estaba anulada.']);
        exit;
    }

    // Anular en BD
    $upd = $pdo->prepare('
        UPDATE entregas
        SET anulada          = 1,
            motivo_anulacion = :motivo,
            fecha_anulacion  = NOW(),
            id_admin_anula   = :id_admin
        WHERE id = :id AND anulada = 0
    ');
    $upd->execute([
        ':motivo'   => $motivo,
        ':id_admin' => $idAdmin,
        ':id'       => $id_entrega,
    ]);

    if ($upd->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['error' => true, 'mensaje' => 'No se pudo anular (puede que otra sesión la anulara antes).']);
        exit;
    }

    error_log(sprintf('ANULACION | admin_id=%d anuló entrega_id=%d socio_id=%d motivo="%s"',
        $idAdmin, $id_entrega, (int) $entrega['socio_id'], $motivo));

    // Email al socio (no abortamos la operación si falla — el soft-delete ya está hecho).
    if (!empty($entrega['socio_email'])) {
        try {
            $codigoAlb = 'ALB-' . str_pad((string) $entrega['id'], 6, '0', STR_PAD_LEFT);
            enviarEmail(
                $entrega['socio_email'],
                'Entrega anulada · ' . $codigoAlb,
                emailEntregaAnulada(
                    [
                        'id'              => (int) $entrega['id'],
                        'fecha_entrega'   => $entrega['fecha_entrega'],
                        'kilos_aceituna'  => (float) $entrega['kilos_aceituna'],
                        'rendimiento'     => (float) $entrega['rendimiento'],
                    ],
                    ['nombre' => $entrega['socio_nombre']],
                    $motivo
                )
            );
        } catch (\Throwable $e) {
            error_log('[anular_entrega] Email al socio falló: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'error'      => false,
        'mensaje'    => 'Entrega anulada correctamente.',
        'id_entrega' => $id_entrega,
    ]);

} catch (PDOException $e) {
    error_log('Error en anular_entrega.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno al anular la entrega.']);
}
