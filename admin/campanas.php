<?php
// Gestión de campañas oleícolas: alta, edición, cierre/reapertura y borrado.
// Bloquea borrar campañas con entregas asociadas (la FK ya las protegería con
// ON DELETE SET NULL, pero preferimos un mensaje claro al admin).

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

// ─── POST handlers ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        try {
            $pdo = getConexion();

            switch ($accion) {

                case 'crear':
                    $codigo  = trim($_POST['codigo'] ?? '');
                    $inicio  = $_POST['fecha_inicio'] ?? '';
                    $fin     = $_POST['fecha_fin']    ?? '';
                    $precio  = filter_input(INPUT_POST, 'precio_por_kilo', FILTER_VALIDATE_FLOAT);
                    $estado  = $_POST['estado'] ?? 'activa';
                    $notas   = trim($_POST['notas'] ?? '');

                    if (!preg_match('/^\d{4}\/\d{4}$/', $codigo)) {
                        throw new InvalidArgumentException('El código debe tener el formato AAAA/BBBB (ej: 2026/2027).');
                    }
                    [$y1, $y2] = explode('/', $codigo);
                    if ((int) $y2 !== (int) $y1 + 1) {
                        throw new InvalidArgumentException('El segundo año debe ser el siguiente al primero (ej: 2026/2027).');
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
                        throw new InvalidArgumentException('Las fechas deben tener formato AAAA-MM-DD.');
                    }
                    // Una campaña que arranque ayer no tiene sentido: las entregas
                    // anteriores a su alta ya tendrían que estar en otra campaña.
                    if (strtotime($inicio) < strtotime(date('Y-m-d'))) {
                        throw new InvalidArgumentException('La fecha de inicio no puede ser anterior a hoy.');
                    }
                    if (strtotime($fin) <= strtotime($inicio)) {
                        throw new InvalidArgumentException('La fecha fin debe ser posterior a la fecha inicio.');
                    }
                    if (!$precio || $precio <= 0) {
                        throw new InvalidArgumentException('El precio por kilo debe ser positivo.');
                    }
                    if (!in_array($estado, ['activa', 'cerrada'], true)) {
                        throw new InvalidArgumentException('Estado no válido.');
                    }

                    $pdo->prepare('
                        INSERT INTO campanas (codigo, fecha_inicio, fecha_fin, precio_por_kilo, estado, notas)
                        VALUES (:c, :fi, :ff, :p, :e, :n)
                    ')->execute([
                        ':c' => $codigo, ':fi' => $inicio, ':ff' => $fin,
                        ':p' => round($precio, 4), ':e' => $estado,
                        ':n' => $notas !== '' ? $notas : null,
                    ]);

                    // Si la nueva campaña abarca entregas existentes sin id_campana
                    // (caso raro), las enlazamos automáticamente.
                    $pdo->prepare('
                        UPDATE entregas SET id_campana = :id
                        WHERE id_campana IS NULL
                          AND fecha_entrega BETWEEN :fi AND :ff
                    ')->execute([':id' => $pdo->lastInsertId(), ':fi' => $inicio, ':ff' => $fin]);

                    $mensaje_exito = "Campaña {$codigo} creada correctamente.";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'editar':
                    $idCamp = filter_input(INPUT_POST, 'id_campana', FILTER_VALIDATE_INT);
                    $precio = filter_input(INPUT_POST, 'precio_por_kilo', FILTER_VALIDATE_FLOAT);
                    $notas  = trim($_POST['notas'] ?? '');
                    if (!$idCamp || !$precio || $precio <= 0) {
                        throw new InvalidArgumentException('Datos de edición no válidos.');
                    }
                    $pdo->prepare('
                        UPDATE campanas SET precio_por_kilo = :p, notas = :n WHERE id = :id
                    ')->execute([':p' => round($precio, 4), ':n' => $notas !== '' ? $notas : null, ':id' => $idCamp]);
                    $mensaje_exito = 'Campaña actualizada.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'cerrar':
                case 'reabrir':
                    $idCamp = filter_input(INPUT_POST, 'id_campana', FILTER_VALIDATE_INT);
                    if (!$idCamp) throw new InvalidArgumentException('ID inválido.');
                    $nuevoEstado = $accion === 'cerrar' ? 'cerrada' : 'activa';
                    $pdo->prepare('UPDATE campanas SET estado = :e WHERE id = :id')
                        ->execute([':e' => $nuevoEstado, ':id' => $idCamp]);
                    $mensaje_exito = $accion === 'cerrar'
                        ? 'Campaña cerrada. Ya no admite entregas nuevas.'
                        : 'Campaña reabierta.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'borrar':
                    $idCamp = filter_input(INPUT_POST, 'id_campana', FILTER_VALIDATE_INT);
                    if (!$idCamp) throw new InvalidArgumentException('ID inválido.');
                    // Bloqueamos borrado si tiene entregas: aunque la FK pondría
                    // id_campana=NULL, perderíamos la trazabilidad histórica.
                    $stmtChk = $pdo->prepare('SELECT COUNT(*) FROM entregas WHERE id_campana = :id');
                    $stmtChk->execute([':id' => $idCamp]);
                    $nEntregas = (int) $stmtChk->fetchColumn();
                    if ($nEntregas > 0) {
                        throw new RuntimeException("No se puede borrar: la campaña tiene {$nEntregas} entrega(s) asociada(s). Ciérrala en su lugar.");
                    }
                    $pdo->prepare('DELETE FROM campanas WHERE id = :id')->execute([':id' => $idCamp]);
                    $mensaje_exito = 'Campaña eliminada.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;

                default:
                    throw new InvalidArgumentException('Acción no reconocida.');
            }

        } catch (InvalidArgumentException | RuntimeException $e) {
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            // Captura específica del UNIQUE de codigo
            if ($e->getCode() === '23000') {
                $mensaje_error = 'Ya existe una campaña con ese código.';
            } else {
                error_log('admin_campanas: ' . $e->getMessage());
                $mensaje_error = 'Error al guardar en la base de datos.';
            }
        }
    }
}

// ─── Carga ───────────────────────────────────────────────────────────────
try {
    $pdo = getConexion();
    $campanas = $pdo->query('SELECT * FROM v_campanas_resumen ORDER BY fecha_inicio DESC')->fetchAll();

    $stats = $pdo->query('
        SELECT
            SUM(estado = "activa")  AS activas,
            SUM(estado = "cerrada") AS cerradas,
            COUNT(*)                AS total
        FROM campanas
    ')->fetch();

    // Liquidación pendiente total = suma de campañas ACTIVAS
    $stmtLiq = $pdo->query('
        SELECT COALESCE(SUM(c.precio_por_kilo * COALESCE(SUM_kilos.s, 0)), 0) AS pendiente
        FROM campanas c
        LEFT JOIN (
            SELECT id_campana, SUM(kilos_aceituna) AS s
            FROM entregas WHERE id_campana IS NOT NULL
            GROUP BY id_campana
        ) AS SUM_kilos ON SUM_kilos.id_campana = c.id
        WHERE c.estado = "activa"
    ');
    $liquidacionPendiente = (float) $stmtLiq->fetchColumn();

} catch (PDOException $e) {
    error_log('admin_campanas GET: ' . $e->getMessage());
    $campanas = [];
    $stats = ['activas' => 0, 'cerradas' => 0, 'total' => 0];
    $liquidacionPendiente = 0;
    $mensaje_error = $mensaje_error ?: 'Error al cargar las campañas.';
}

// Sugerencia automática para el form de "nueva campaña"
$ultimaFin   = $campanas[0]['fecha_fin'] ?? null;
$sugInicio   = $ultimaFin ? date('Y-07-01', strtotime($ultimaFin . ' +1 day')) : date('Y-07-01');
$sugFin      = date('Y-06-30', strtotime($sugInicio . ' +1 year'));
$y1          = (int) date('Y', strtotime($sugInicio));
$sugCodigo   = $y1 . '/' . ($y1 + 1);

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Gestión de Campañas | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>

<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-calendar-range" style="color: var(--color-accent-dark);"></i>
                Gestión de <em>Campañas</em>
            </h1>
            <p>Define el periodo, el precio de liquidación y el estado de cada campaña oleícola.</p>
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
                <div class="stat-icon icon-green"><i class="bi bi-play-fill"></i></div>
                <div class="stat-label">Activas</div>
                <div class="stat-value"><?= (int) $stats['activas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-archive-fill"></i></div>
                <div class="stat-label">Cerradas</div>
                <div class="stat-value"><?= (int) $stats['cerradas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="bi bi-collection-fill"></i></div>
                <div class="stat-label">Total campañas</div>
                <div class="stat-value"><?= (int) $stats['total'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-currency-euro"></i></div>
                <div class="stat-label">Liquidación pendiente</div>
                <div class="stat-value">
                    <?= number_format($liquidacionPendiente, 0, ',', '.') ?>
                    <span class="stat-unit">€</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Form alta -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-plus-circle-fill"></i><h2>Nueva Campaña</h2>
                </div>
                <div class="form-card-body">
                    <form method="POST" action="campanas.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">
                            <label class="admin-label" for="cmp-codigo">
                                <i class="bi bi-tag"></i> Código (AAAA/BBBB)
                            </label>
                            <input type="text" class="admin-input" id="cmp-codigo" name="codigo"
                                value="<?= htmlspecialchars($sugCodigo) ?>"
                                pattern="\d{4}/\d{4}" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <?php
                            // min HTML5 — guardrail visual: el navegador bloquea fechas
                            // anteriores a hoy. La validación real vive en el handler POST.
                            $minHoy = date('Y-m-d');
                            ?>
                            <div class="col-6">
                                <label class="admin-label" for="cmp-inicio">Inicio</label>
                                <input type="date" class="admin-input" id="cmp-inicio" name="fecha_inicio"
                                    value="<?= htmlspecialchars($sugInicio) ?>"
                                    min="<?= $minHoy ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="admin-label" for="cmp-fin">Fin</label>
                                <input type="date" class="admin-input" id="cmp-fin" name="fecha_fin"
                                    value="<?= htmlspecialchars($sugFin) ?>"
                                    min="<?= $minHoy ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="cmp-precio">
                                <i class="bi bi-currency-euro"></i> Precio €/kg
                            </label>
                            <input type="number" class="admin-input" id="cmp-precio" name="precio_por_kilo"
                                min="0.0001" max="9.9999" step="0.0001" value="0.4500" required>
                            <small class="text-muted">4 decimales: ej. 0.4500 €/kg</small>
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="cmp-estado">Estado</label>
                            <select class="admin-select" id="cmp-estado" name="estado">
                                <option value="activa">Activa</option>
                                <option value="cerrada">Cerrada</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="cmp-notas">Notas</label>
                            <textarea class="admin-textarea" id="cmp-notas" name="notas" rows="2"
                                placeholder="Observaciones internas (opcional)"></textarea>
                        </div>

                        <button type="submit" class="btn-registrar">
                            <i class="bi bi-check-lg"></i> Crear campaña
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="header-left"><i class="bi bi-table"></i><h2>Histórico de Campañas</h2></div>
                    <span class="badge-count"><?= count($campanas) ?></span>
                </div>
                <div class="table-responsive">
                    <?php if (empty($campanas)): ?>
                        <div class="table-empty"><i class="bi bi-inbox"></i><p>No hay campañas registradas.</p></div>
                    <?php else: ?>
                        <table class="table table-hover table-admin mb-0">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Periodo</th>
                                    <th class="text-end">€/kg</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-end">Entregas</th>
                                    <th class="text-end">Kg</th>
                                    <th class="text-end">Liquidación</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campanas as $c):
                                    $colorEstado = $c['estado'] === 'activa' ? 'success' : 'secondary';
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['codigo']) ?></strong></td>
                                        <td class="small text-muted">
                                            <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?>
                                            → <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= number_format((float) $c['precio_por_kilo'], 4, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $colorEstado ?>">
                                                <?= htmlspecialchars($c['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= (int) $c['total_entregas'] ?></td>
                                        <td class="text-end small text-muted">
                                            <?= number_format((float) $c['total_kilos'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end fw-semibold" style="color: var(--color-accent-dark);">
                                            <?= number_format((float) $c['liquidacion_total'], 0, ',', '.') ?> €
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-cmp"
                                                    data-id="<?= (int) $c['id'] ?>"
                                                    data-codigo="<?= htmlspecialchars($c['codigo']) ?>"
                                                    data-precio="<?= htmlspecialchars($c['precio_por_kilo']) ?>"
                                                    data-notas="<?= htmlspecialchars($c['notas'] ?? '') ?>"
                                                    title="Editar precio y notas">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="index.php?campana=<?= (int) $c['id'] ?>"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Ver entregas de esta campaña">
                                                    <i class="bi bi-funnel"></i>
                                                </a>
                                                <?php if ($c['estado'] === 'activa'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Cerrar la campaña <?= htmlspecialchars($c['codigo']) ?>? Dejará de admitir entregas nuevas.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="accion" value="cerrar">
                                                        <input type="hidden" name="id_campana" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Cerrar campaña">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Reabrir la campaña <?= htmlspecialchars($c['codigo']) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="accion" value="reabrir">
                                                        <input type="hidden" name="id_campana" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Reabrir">
                                                            <i class="bi bi-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ((int) $c['total_entregas'] === 0): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar definitivamente la campaña <?= htmlspecialchars($c['codigo']) ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="accion" value="borrar">
                                                        <input type="hidden" name="id_campana" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div style="height: 3rem;"></div>
</main>


<!-- Modal: editar precio + notas -->
<div class="modal fade" id="modalEditarCmp" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" value="editar">
      <input type="hidden" name="id_campana" id="ed-id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar campaña <span id="ed-codigo">—</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Sólo se permite ajustar el precio de liquidación y las notas. Para cambiar el periodo, crea una campaña nueva.</p>
        <div class="mb-3">
            <label class="admin-label" for="ed-precio">Precio €/kg</label>
            <input type="number" class="admin-input" id="ed-precio" name="precio_por_kilo"
                min="0.0001" max="9.9999" step="0.0001" required>
        </div>
        <div class="mb-2">
            <label class="admin-label" for="ed-notas">Notas</label>
            <textarea class="admin-textarea" id="ed-notas" name="notas" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const v = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
    const t = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
    document.querySelectorAll('.btn-editar-cmp').forEach(btn => {
        btn.addEventListener('click', () => {
            v('ed-id', btn.dataset.id);
            t('ed-codigo', btn.dataset.codigo);
            v('ed-precio', parseFloat(btn.dataset.precio).toFixed(4));
            v('ed-notas', btn.dataset.notas);
            new bootstrap.Modal(document.getElementById('modalEditarCmp')).show();
        });
    });
})();
</script>
</body>
</html>
