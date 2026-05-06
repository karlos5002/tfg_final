<?php
// Crea una reserva de visita guiada. Endpoint público (no requiere login).
// Reglas: lunes cerrado · 4 turnos/día · 24h antelación mínima · cupo 10/turno.
// El cupo se comprueba dentro de transacción con SELECT...FOR UPDATE para evitar
// que dos reservas concurrentes sumen más del máximo.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => true, 'mensaje' => 'Método no permitido.']);
    exit;
}

session_start();
require_once __DIR__ . '/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;

// El token se genera al cargar la home; aquí lo creamos como fallback si la
// sesión todavía no lo tenía (visitante muy reciente).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!hash_equals($_SESSION['csrf_token'], $payload['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
    exit;
}

$nombre      = trim((string) ($payload['nombre']       ?? ''));
$email       = trim((string) ($payload['email']        ?? ''));
$telefono    = trim((string) ($payload['telefono']     ?? ''));
$fechaVisita = trim((string) ($payload['fecha_visita'] ?? ''));
$horaVisita  = trim((string) ($payload['hora_visita']  ?? ''));
$numPersonas = filter_var($payload['num_personas'] ?? null, FILTER_VALIDATE_INT);
$tipoVisita  = trim((string) ($payload['tipo_visita']  ?? ''));
$comentarios = trim((string) ($payload['comentarios']  ?? ''));

$tiposValidos  = ['cata', 'almazara', 'completa'];
$horasValidas  = ['10:00', '12:00', '17:00', '19:00'];
$cupoPorTurno  = 10;
$minHorasAntel = 24;

$errores = [];

if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) $errores[] = 'Nombre inválido (entre 2 y 100 caracteres).';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errores[] = 'Email no válido.';
if ($telefono !== '' && !preg_match('/^[0-9 +\-]{6,20}$/', $telefono)) {
    $errores[] = 'Teléfono no válido.';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVisita)) {
    $errores[] = 'Fecha inválida.';
} else {
    $tsVisita = strtotime($fechaVisita . ' ' . $horaVisita);
    if ($tsVisita === false) {
        $errores[] = 'Fecha/hora ininteligibles.';
    } else {
        if ($tsVisita - time() < $minHorasAntel * 3600) {
            $errores[] = 'Las reservas requieren al menos ' . $minHorasAntel . ' h de antelación.';
        }
        if (date('N', $tsVisita) === '1') {     // 1 = lunes
            $errores[] = 'Los lunes la almazara permanece cerrada al público.';
        }
    }
}

if (!in_array($horaVisita, $horasValidas, true))             $errores[] = 'El turno seleccionado no es válido.';
if (!$numPersonas || $numPersonas < 1 || $numPersonas > $cupoPorTurno) {
    $errores[] = 'El número de personas debe estar entre 1 y ' . $cupoPorTurno . '.';
}
if (!in_array($tipoVisita, $tiposValidos, true))             $errores[] = 'Tipo de visita no válido.';
if (mb_strlen($comentarios) > 500)                            $errores[] = 'Los comentarios no pueden exceder 500 caracteres.';

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'mensaje' => implode(' ', $errores), 'errores' => $errores]);
    exit;
}

try {
    $pdo = getConexion();
    $pdo->beginTransaction();

    // FOR UPDATE bloquea las filas del turno hasta el commit: dos peticiones
    // concurrentes no pueden ambas leer cupo libre y ambas insertar
    $stmtCupo = $pdo->prepare('
        SELECT COALESCE(SUM(num_personas), 0) AS ocupadas
        FROM visitas
        WHERE fecha_visita = :fecha AND hora_visita = :hora
          AND estado IN ("pendiente", "confirmada")
        FOR UPDATE
    ');
    $stmtCupo->execute([':fecha' => $fechaVisita, ':hora' => $horaVisita . ':00']);
    $ocupadas = (int) $stmtCupo->fetchColumn();

    if ($ocupadas + $numPersonas > $cupoPorTurno) {
        $pdo->rollBack();
        $libres = max(0, $cupoPorTurno - $ocupadas);
        http_response_code(409);
        echo json_encode([
            'error'   => true,
            'mensaje' => sprintf(
                'Ese turno está casi lleno. Sólo quedan %d plaza%s — prueba otro turno o reduce el grupo.',
                $libres, $libres === 1 ? '' : 's'
            ),
            'plazas_disponibles' => $libres,
        ]);
        exit;
    }

    // Si el visitante está logueado, vinculamos la reserva con su usuario
    $idUsuario = $_SESSION['usuario_id'] ?? null;

    $stmtIns = $pdo->prepare('
        INSERT INTO visitas
            (nombre, email, telefono, fecha_visita, hora_visita,
             num_personas, tipo_visita, comentarios, estado, id_usuario)
        VALUES
            (:nombre, :email, :telefono, :fv, :hv,
             :np, :tv, :coms, "pendiente", :iu)
    ');
    $stmtIns->execute([
        ':nombre'   => $nombre,
        ':email'    => $email,
        ':telefono' => $telefono ?: null,
        ':fv'       => $fechaVisita,
        ':hv'       => $horaVisita . ':00',
        ':np'       => $numPersonas,
        ':tv'       => $tipoVisita,
        ':coms'     => $comentarios ?: null,
        ':iu'       => $idUsuario,
    ]);

    $idReserva = (int) $pdo->lastInsertId();
    $pdo->commit();

    $codigo = 'VST-' . str_pad((string) $idReserva, 6, '0', STR_PAD_LEFT);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode([
        'error'      => false,
        'mensaje'    => 'Reserva registrada correctamente. Te confirmaremos por email en menos de 24 h.',
        'id_reserva' => $idReserva,
        'codigo'     => $codigo,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Error en reservar_visita.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno al guardar la reserva. Inténtalo en unos minutos.']);
}
