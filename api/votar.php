<?php
// Registra el voto de un socio en una votación abierta.
// La unicidad "un socio = un voto" la garantiza la PK (id_votacion, id_socio)
// de la tabla votos: aunque alguien manipule el cliente, MySQL devuelve 23000.

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'socio') {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Solo los socios pueden emitir votos.']);
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

$idVotacion = filter_var($payload['id_votacion'] ?? null, FILTER_VALIDATE_INT);
$idOpcion   = filter_var($payload['id_opcion']   ?? null, FILTER_VALIDATE_INT);

if (!$idVotacion || $idVotacion <= 0 || !$idOpcion || $idOpcion <= 0) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => 'Datos del voto inválidos.']);
    exit;
}

$idSocio = (int) ($_SESSION['usuario_id'] ?? 0);

try {
    $pdo = getConexion();

    // La votación tiene que existir, estar 'abierta' y estar dentro de la ventana temporal
    $stmtV = $pdo->prepare('
        SELECT id FROM votaciones
        WHERE id = :id AND estado = "abierta"
          AND NOW() BETWEEN fecha_inicio AND fecha_fin
    ');
    $stmtV->execute([':id' => $idVotacion]);
    if (!$stmtV->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => true, 'mensaje' => 'Esta votación no está abierta o ya ha terminado.']);
        exit;
    }

    // La opción debe pertenecer a esta votación: evita que alguien envíe el
    // id_opcion de otra votación abierta y mezcle resultados
    $stmtO = $pdo->prepare('SELECT id FROM votacion_opciones WHERE id = :op AND id_votacion = :v');
    $stmtO->execute([':op' => $idOpcion, ':v' => $idVotacion]);
    if (!$stmtO->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['error' => true, 'mensaje' => 'Opción no válida para esta votación.']);
        exit;
    }

    try {
        $ins = $pdo->prepare('INSERT INTO votos (id_votacion, id_socio, id_opcion) VALUES (:v, :s, :o)');
        $ins->execute([':v' => $idVotacion, ':s' => $idSocio, ':o' => $idOpcion]);
    } catch (PDOException $e) {
        // 23000 = integrity violation. En este endpoint el caso esperado es
        // duplicado de PK = el socio ya había votado.
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode([
                'error' => true,
                'mensaje' => 'Ya has emitido tu voto en esta votación.',
                'ya_votado' => true,
            ]);
            exit;
        }
        throw $e;
    }

    error_log(sprintf('VOTO | socio_id=%d votación_id=%d opción_id=%d',
        $idSocio, $idVotacion, $idOpcion));

    echo json_encode([
        'error' => false,
        'mensaje' => 'Voto registrado correctamente. Gracias por participar.',
        'id_opcion' => $idOpcion,
    ]);

} catch (PDOException $e) {
    error_log('Error en votar.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno al registrar el voto.']);
}
