<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Resultados de Votación (admin)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Detalle de una votación concreta: distribución de votos, % por opción,
 * gráfico de barras (Chart.js) y verificación del quórum.
 *
 * Acciones permitidas al admin desde aquí:
 *   - Pasar de "borrador" a "abierta" (publicar).
 *   - Cerrar manualmente una votación abierta (forzar fin).
 *
 * Privacidad: aunque el admin sí ve los resultados en cualquier estado,
 * los socios sólo los ven al cerrar la votación (controlado en votaciones.php).
 *
 * ============================================================================
 */

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$idVotacion = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idVotacion) {
    header('Location: votaciones.php');
    exit;
}

$mensaje_exito = '';
$mensaje_error = '';

// ─── Acciones POST: publicar o cerrar ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';
        $nuevoEstado = match ($accion) {
            'publicar' => 'abierta',
            'cerrar'   => 'cerrada',
            default    => null,
        };
        if ($nuevoEstado) {
            try {
                $pdo = getConexion();
                $stmt = $pdo->prepare('UPDATE votaciones SET estado = :e WHERE id = :id');
                $stmt->execute([':e' => $nuevoEstado, ':id' => $idVotacion]);
                $mensaje_exito = $nuevoEstado === 'abierta'
                    ? 'Votación publicada. Los socios ya pueden votar.'
                    : 'Votación cerrada. Los resultados son visibles para los socios.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                error_log('Error cambiando estado: ' . $e->getMessage());
                $mensaje_error = 'No se pudo actualizar el estado.';
            }
        }
    }
}

// ─── Lectura de la votación + resultados ─────────────────────────────────
try {
    $pdo = getConexion();

    $stmtV = $pdo->prepare('SELECT * FROM votaciones WHERE id = :id');
    $stmtV->execute([':id' => $idVotacion]);
    $votacion = $stmtV->fetch();

    if (!$votacion) {
        header('Location: votaciones.php');
        exit;
    }

    // Resultados por opción
    $stmtR = $pdo->prepare('
        SELECT vo.id, vo.texto, COUNT(v.id_opcion) AS num_votos
        FROM votacion_opciones vo
        LEFT JOIN votos v ON v.id_opcion = vo.id
        WHERE vo.id_votacion = :id
        GROUP BY vo.id, vo.texto, vo.orden
        ORDER BY vo.orden
    ');
    $stmtR->execute([':id' => $idVotacion]);
    $resultados = $stmtR->fetchAll();

    $totalVotos  = array_sum(array_column($resultados, 'num_votos'));
    $totalSocios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE rol="socio" AND activo=1')->fetchColumn();
    $participacionPct = $totalSocios > 0 ? round($totalVotos * 100 / $totalSocios, 1) : 0;
    $quorumOk    = $participacionPct >= $votacion['quorum_minimo'];

    // Lista de socios que ya han votado (para el admin: trazabilidad anonimizada)
    // Mostramos solo INICIALES + FECHA del voto, no la opción elegida.
    $stmtList = $pdo->prepare('
        SELECT u.nombre, u.apellidos, v.fecha_voto
        FROM votos v
        INNER JOIN usuarios u ON u.id = v.id_socio
        WHERE v.id_votacion = :id
        ORDER BY v.fecha_voto DESC
    ');
    $stmtList->execute([':id' => $idVotacion]);
    $sociosVotantes = $stmtList->fetchAll();

} catch (PDOException $e) {
    error_log('Error en admin_votacion_resultados: ' . $e->getMessage());
    header('Location: votaciones.php');
    exit;
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Resultados: ' . $votacion['titulo'];
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-bar-chart-fill" style="color: var(--color-accent-dark);"></i>
                Resultados: <em><?= htmlspecialchars($votacion['titulo']) ?></em>
            </h1>
            <p class="text-muted mb-0">
                <?= date('d/m/Y H:i', strtotime($votacion['fecha_inicio'])) ?> →
                <?= date('d/m/Y H:i', strtotime($votacion['fecha_fin'])) ?>
                · estado: <strong><?= htmlspecialchars($votacion['estado']) ?></strong>
            </p>
        </div>
        <a href="votaciones.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Todas las votaciones
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

    <?php if ($votacion['descripcion']): ?>
        <div class="alert alert-light border" style="white-space: pre-line;">
            <?= htmlspecialchars($votacion['descripcion']) ?>
        </div>
    <?php endif; ?>

    <!-- ── Acciones del admin (publicar/cerrar) ── -->
    <?php if ($votacion['estado'] !== 'cerrada'): ?>
        <div class="d-flex gap-2 mb-3">
            <?php if ($votacion['estado'] === 'borrador'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="publicar">
                    <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('¿Publicar la votación? Los socios podrán votar inmediatamente.')">
                        <i class="bi bi-megaphone-fill"></i> Publicar (abrir)
                    </button>
                </form>
            <?php elseif ($votacion['estado'] === 'abierta'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="cerrar">
                    <button type="submit" class="btn btn-warning btn-sm"
                            onclick="return confirm('¿Cerrar la votación ahora? Esta acción no se puede deshacer y los resultados pasarán a ser públicos.')">
                        <i class="bi bi-lock-fill"></i> Cerrar votación
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- ── Medidor de quórum + KPIs ── -->
    <div class="row g-3 mb-3">

        <div class="col-md-4">
            <div class="stat-card quorum-card <?= $quorumOk ? 'quorum-ok' : 'quorum-ko' ?>">
                <div class="stat-label">Quórum</div>
                <div class="quorum-meter">
                    <div class="quorum-meter-track">
                        <div class="quorum-meter-fill" style="width: <?= min($participacionPct, 100) ?>%"></div>
                        <div class="quorum-meter-mark" style="left: <?= $votacion['quorum_minimo'] ?>%"
                             title="Mínimo necesario: <?= $votacion['quorum_minimo'] ?>%"></div>
                    </div>
                    <div class="quorum-pct"><?= $participacionPct ?>%</div>
                </div>
                <div class="quorum-status">
                    <?= $quorumOk
                        ? '<i class="bi bi-check-circle-fill"></i> Decisión válida'
                        : '<i class="bi bi-exclamation-circle-fill"></i> Sin quórum (mínimo ' . $votacion['quorum_minimo'] . '%)' ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-people-fill"></i></div>
                <div class="stat-label">Votos emitidos</div>
                <div class="stat-value"><?= $totalVotos ?> <span class="stat-unit">/ <?= $totalSocios ?> socios</span></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-list-check"></i></div>
                <div class="stat-label">Opciones</div>
                <div class="stat-value"><?= count($resultados) ?></div>
            </div>
        </div>
    </div>


    <!-- ── Gráfico + tabla de resultados ── -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="chart-card">
                <div class="chart-card-header">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <h3>Distribución de votos</h3>
                </div>
                <div class="chart-card-body">
                    <?php if ($totalVotos > 0): ?>
                        <canvas id="grafico-votos"
                                data-labels='<?= json_encode(array_column($resultados, 'texto'), JSON_UNESCAPED_UNICODE) ?>'
                                data-values='<?= json_encode(array_map('intval', array_column($resultados, 'num_votos'))) ?>'></canvas>
                    <?php else: ?>
                        <div class="chart-error">
                            <i class="bi bi-inbox"></i>
                            <p>Sin votos todavía. El gráfico aparecerá cuando los socios empiecen a votar.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="header-left"><i class="bi bi-table"></i><h2>Detalle por opción</h2></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-admin mb-0">
                        <thead><tr><th>Opción</th><th class="text-end">Votos</th><th class="text-end">%</th></tr></thead>
                        <tbody>
                            <?php foreach ($resultados as $r):
                                $pctOpcion = $totalVotos > 0
                                    ? round($r['num_votos'] * 100 / $totalVotos, 1)
                                    : 0;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['texto']) ?></td>
                                    <td class="text-end fw-semibold"><?= (int) $r['num_votos'] ?></td>
                                    <td class="text-end text-muted"><?= $pctOpcion ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Trazabilidad anonimizada -->
            <?php if (!empty($sociosVotantes)): ?>
                <div class="table-card mt-3">
                    <div class="table-card-header">
                        <div class="header-left"><i class="bi bi-person-check-fill"></i><h2>Han votado</h2></div>
                        <span class="badge-count"><?= count($sociosVotantes) ?></span>
                    </div>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($sociosVotantes as $sv): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(($sv['apellidos'] ? $sv['apellidos'] . ', ' : '') . $sv['nombre']) ?></td>
                                        <td class="text-end text-muted small"><?= date('d/m/Y H:i', strtotime($sv['fecha_voto'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-2 small text-muted" style="border-top: 1px solid rgba(0,0,0,.05);">
                        <i class="bi bi-info-circle"></i> Sólo se muestra <em>quién</em> ha votado, nunca <em>qué</em> ha votado (secreto del voto).
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="height: 3rem;"></div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    const canvas = document.getElementById('grafico-votos');
    if (!canvas || typeof Chart === 'undefined') return;

    const labels = JSON.parse(canvas.dataset.labels);
    const values = JSON.parse(canvas.dataset.values);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Votos',
                data: values,
                backgroundColor: 'rgba(44, 76, 59, 0.85)',
                borderColor:     'rgba(44, 76, 59, 1)',
                borderWidth: 2,
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(212, 175, 55, 0.85)',
            }]
        },
        options: {
            indexAxis: 'y',          // barras horizontales (mejor con etiquetas largas)
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 53, 41, 0.95)',
                    padding: 12,
                    cornerRadius: 8,
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
                y: { ticks: { font: { weight: 500 } } }
            },
            animation: { duration: 800, easing: 'easeOutQuart' }
        }
    });
})();
</script>

</body>
</html>
