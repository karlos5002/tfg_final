<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Gestión de Visitas (admin)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Lista las reservas de visita guiada y permite cambiar su estado:
 *   pendiente → confirmada / cancelada
 *   confirmada → realizada / cancelada
 *
 * Filtros: por estado y por rango de fecha.
 * ============================================================================
 */

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/notificaciones.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

// ─── Procesamiento POST: cambio de estado de una reserva ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido.';
    } else {
        $idVisita = filter_input(INPUT_POST, 'id_visita', FILTER_VALIDATE_INT);
        $accion   = $_POST['accion'] ?? '';
        $nuevo    = match ($accion) {
            'confirmar' => 'confirmada',
            'cancelar'  => 'cancelada',
            'realizar'  => 'realizada',
            default     => null,
        };

        if (!$idVisita || !$nuevo) {
            $mensaje_error = 'Acción no válida.';
        } else {
            try {
                $pdo = getConexion();

                // Recuperamos la fila ANTES de actualizar para tener todos los
                // datos disponibles para la plantilla del email (sin doble query).
                $stmtSel = $pdo->prepare('SELECT * FROM visitas WHERE id = :id');
                $stmtSel->execute([':id' => $idVisita]);
                $visitaActual = $stmtSel->fetch();

                if (!$visitaActual) {
                    $mensaje_error = 'Reserva no encontrada.';
                } else {
                    $stmt = $pdo->prepare('UPDATE visitas SET estado = :e WHERE id = :id');
                    $stmt->execute([':e' => $nuevo, ':id' => $idVisita]);

                    // ─── Envío automático de email según la acción ───
                    $infoEmail = null;
                    if ($nuevo === 'confirmada') {
                        $html = notificacionConfirmacionVisita($visitaActual);
                        $infoEmail = enviarNotificacion(
                            $visitaActual['email'],
                            'Tu visita a la almazara está confirmada',
                            $html
                        );
                    } elseif ($nuevo === 'cancelada') {
                        $html = notificacionCancelacionVisita($visitaActual);
                        $infoEmail = enviarNotificacion(
                            $visitaActual['email'],
                            'Tu reserva ha sido cancelada',
                            $html
                        );
                    }

                    // Mensaje de éxito que refleja si el email salió por SMTP o
                    // se quedó en el log (transparencia para el admin).
                    $msgEstado = match ($nuevo) {
                        'confirmada' => 'Reserva confirmada.',
                        'cancelada'  => 'Reserva cancelada.',
                        'realizada'  => 'Visita marcada como realizada.',
                    };
                    if ($infoEmail) {
                        if ($infoEmail['ok'] && $infoEmail['modo'] === 'smtp') {
                            $msgEstado .= ' Se ha enviado un email a ' . htmlspecialchars($visitaActual['email']) . '.';
                        } elseif ($infoEmail['ok'] && $infoEmail['modo'] === 'log') {
                            $msgEstado .= ' Email guardado en logs/emails/' . htmlspecialchars($infoEmail['ruta_log'])
                                       . ' (configura SMTP en php.ini para envío real).';
                        } else {
                            $msgEstado .= ' ⚠ No se pudo registrar el email: ' . htmlspecialchars($infoEmail['error'] ?? 'error desconocido');
                        }
                    }
                    $mensaje_exito = $msgEstado;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {
                error_log('Error cambiando estado visita: ' . $e->getMessage());
                $mensaje_error = 'No se pudo actualizar la reserva.';
            }
        }
    }
}

// ─── Filtros de la URL ───────────────────────────────────────────────────
$filtroEstado = $_GET['estado']     ?? 'todas';
$filtroDesde  = $_GET['desde']      ?? '';
$estadosVal   = ['todas', 'pendiente', 'confirmada', 'cancelada', 'realizada'];
if (!in_array($filtroEstado, $estadosVal, true)) $filtroEstado = 'todas';

// ─── Consulta con filtros ────────────────────────────────────────────────
try {
    $pdo = getConexion();

    $sql = '
        SELECT v.*, u.nombre AS user_nombre
        FROM visitas v
        LEFT JOIN usuarios u ON u.id = v.id_usuario
        WHERE 1 = 1
    ';
    $params = [];
    if ($filtroEstado !== 'todas') {
        $sql .= ' AND v.estado = :estado';
        $params[':estado'] = $filtroEstado;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroDesde)) {
        $sql .= ' AND v.fecha_visita >= :desde';
        $params[':desde'] = $filtroDesde;
    }
    $sql .= ' ORDER BY v.fecha_visita ASC, v.hora_visita ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visitas = $stmt->fetchAll();

    // KPIs rápidos
    $stats = $pdo->query('
        SELECT
            SUM(estado = "pendiente")  AS pendientes,
            SUM(estado = "confirmada") AS confirmadas,
            SUM(estado = "realizada")  AS realizadas,
            SUM(estado = "cancelada")  AS canceladas,
            COALESCE(SUM(IF(estado IN ("pendiente","confirmada"), num_personas, 0)), 0) AS prox_personas
        FROM visitas
        WHERE fecha_visita >= CURDATE()
    ')->fetch();

} catch (PDOException $e) {
    error_log('Error en admin_visitas: ' . $e->getMessage());
    $visitas = [];
    $stats   = ['pendientes' => 0, 'confirmadas' => 0, 'realizadas' => 0, 'canceladas' => 0, 'prox_personas' => 0];
    $mensaje_error = $mensaje_error ?: 'Error al cargar las reservas.';
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Gestión de Visitas | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-calendar-check-fill" style="color: var(--color-accent-dark);"></i>
                Gestión de <em>Visitas</em>
            </h1>
            <p>Reservas de oleoturismo. Confirma o cancela cada solicitud.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Panel
        </a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-custom alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($mensaje_exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($mensaje_error): ?>
        <div class="alert alert-custom alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-label">Pendientes</div>
                <div class="stat-value"><?= (int) $stats['pendientes'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="bi bi-check2-circle"></i></div>
                <div class="stat-label">Confirmadas</div>
                <div class="stat-value"><?= (int) $stats['confirmadas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-label">Personas próximas</div>
                <div class="stat-value"><?= (int) $stats['prox_personas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-trophy"></i></div>
                <div class="stat-label">Realizadas</div>
                <div class="stat-value"><?= (int) $stats['realizadas'] ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="d-flex gap-2 flex-wrap mb-3 align-items-end">
        <div>
            <label class="admin-label" for="filtro-estado">Estado</label>
            <select name="estado" id="filtro-estado" class="admin-select" onchange="this.form.submit()">
                <?php foreach ($estadosVal as $e): ?>
                    <option value="<?= $e ?>" <?= $filtroEstado === $e ? 'selected' : '' ?>>
                        <?= ucfirst($e) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="admin-label" for="filtro-desde">Desde fecha</label>
            <input type="date" id="filtro-desde" name="desde" class="admin-input"
                   value="<?= htmlspecialchars($filtroDesde) ?>" onchange="this.form.submit()">
        </div>
        <?php if ($filtroEstado !== 'todas' || $filtroDesde !== ''): ?>
            <a href="visitas.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg"></i> Quitar filtros
            </a>
        <?php endif; ?>
    </form>

    <!-- Tabla -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="header-left"><i class="bi bi-table"></i><h2>Reservas</h2></div>
            <span class="badge-count"><?= count($visitas) ?> reserva<?= count($visitas) === 1 ? '' : 's' ?></span>
        </div>

        <div class="table-responsive">
            <?php if (empty($visitas)): ?>
                <div class="table-empty">
                    <i class="bi bi-calendar-x"></i>
                    <p>No hay reservas con esos filtros.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha y turno</th>
                            <th>Visitante</th>
                            <th>Contacto</th>
                            <th class="text-center">Personas</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitas as $v):
                            $codigo  = 'VST-' . str_pad((string) $v['id'], 6, '0', STR_PAD_LEFT);
                            $colores = [
                                'pendiente'  => 'warning',
                                'confirmada' => 'success',
                                'cancelada'  => 'danger',
                                'realizada'  => 'secondary',
                            ];
                            $tiposLabel = [
                                'cata'      => 'Sólo cata',
                                'almazara'  => 'Almazara',
                                'completa'  => 'Completa',
                            ];
                        ?>
                            <tr>
                                <td><code><?= $codigo ?></code></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($v['fecha_visita'])) ?></strong>
                                    <br><small class="text-muted">
                                        <?= date('H:i', strtotime($v['hora_visita'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($v['nombre']) ?>
                                    <?php if ($v['user_nombre']): ?>
                                        <br><small class="text-muted">
                                            <i class="bi bi-person-check"></i> Cuenta vinculada
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($v['comentarios']): ?>
                                        <br><small class="text-muted" title="<?= htmlspecialchars($v['comentarios']) ?>">
                                            <i class="bi bi-chat-dots"></i> Tiene comentarios
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <a href="mailto:<?= htmlspecialchars($v['email']) ?>"><?= htmlspecialchars($v['email']) ?></a>
                                    <?php if ($v['telefono']): ?>
                                        <br><a href="tel:<?= htmlspecialchars($v['telefono']) ?>" class="text-muted">
                                            <?= htmlspecialchars($v['telefono']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold"><?= (int) $v['num_personas'] ?></td>
                                <td class="text-center small">
                                    <?= $tiposLabel[$v['tipo_visita']] ?? $v['tipo_visita'] ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $colores[$v['estado']] ?? 'secondary' ?>">
                                        <?= htmlspecialchars($v['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($v['estado'] === 'pendiente' || $v['estado'] === 'confirmada'): ?>
                                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                                            <?php if ($v['estado'] === 'pendiente'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Confirmar la reserva?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="id_visita" value="<?= (int) $v['id'] ?>">
                                                    <input type="hidden" name="accion" value="confirmar">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Confirmar">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($v['estado'] === 'confirmada'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Marcar la visita como realizada?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="id_visita" value="<?= (int) $v['id'] ?>">
                                                    <input type="hidden" name="accion" value="realizar">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar como realizada">
                                                        <i class="bi bi-trophy"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="id_visita" value="<?= (int) $v['id'] ?>">
                                                <input type="hidden" name="accion" value="cancelar">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div style="height: 3rem;"></div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
