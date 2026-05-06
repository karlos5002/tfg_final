<?php
// Histórico de entregas del socio en sesión, con filtro por campaña y
// liquidación estimada (kilos × precio_por_kilo).
// Triple barrera: rol === 'socio', WHERE id_socio = sesión, mismo check en el PDF.

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'socio') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$idSocio       = (int) ($_SESSION['usuario_id'] ?? 0);
$filtroCampana = filter_input(INPUT_GET, 'campana', FILTER_VALIDATE_INT);

try {
    $pdo = getConexion();

    // Lista de campañas en las que ESTE socio tiene al menos una entrega.
    // No mostramos campañas en las que no participó: ruido inútil.
    $misCampanas = $pdo->prepare('
        SELECT DISTINCT c.id, c.codigo, c.estado, c.precio_por_kilo
        FROM campanas c
        INNER JOIN entregas e ON e.id_campana = c.id
        WHERE e.id_socio = :id
        ORDER BY c.fecha_inicio DESC
    ');
    $misCampanas->execute([':id' => $idSocio]);
    $misCampanas = $misCampanas->fetchAll();

    $sql = '
        SELECT e.id, e.fecha_entrega, e.campana, e.kilos_aceituna, e.rendimiento,
               e.litros_aceite, e.observaciones, e.created_at,
               e.id_campana, c.codigo AS campana_codigo, c.precio_por_kilo, c.estado AS campana_estado
        FROM entregas e
        LEFT  JOIN campanas c ON c.id = e.id_campana
        WHERE e.id_socio = :id
    ';
    $params = [':id' => $idSocio];
    if ($filtroCampana) {
        $sql .= ' AND e.id_campana = :campana';
        $params[':campana'] = $filtroCampana;
    }
    $sql .= ' ORDER BY e.fecha_entrega DESC, e.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entregas = $stmt->fetchAll();

    $totalLiquidacion = 0.0;
    foreach ($entregas as $e) {
        if ($e['precio_por_kilo'] !== null) {
            $totalLiquidacion += (float) $e['kilos_aceituna'] * (float) $e['precio_por_kilo'];
        }
    }
    $stats = [
        'total'        => count($entregas),
        'kilos'        => array_sum(array_column($entregas, 'kilos_aceituna')),
        'litros'       => array_sum(array_column($entregas, 'litros_aceite')),
        'liquidacion'  => round($totalLiquidacion, 2),
    ];

} catch (PDOException $e) {
    error_log('Error en mis_entregas.php: ' . $e->getMessage());
    $entregas    = [];
    $misCampanas = [];
    $stats       = ['total' => 0, 'kilos' => 0, 'litros' => 0, 'liquidacion' => 0];
    $errorCarga  = 'No se pudieron cargar tus entregas. Inténtalo de nuevo.';
}

$pageTitle = 'Mis Entregas | Cooperativa San Juan Bautista';
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container mis-entregas-main" id="contenido-principal">

    <header class="mis-entregas-header">
        <span class="hero-label"><i class="bi bi-clipboard-data"></i> Histórico personal</span>
        <h1>Mis <em>entregas</em></h1>
        <p>Consulta tus aportaciones de aceituna a la almazara y descarga el albarán de cada una para conservarlo en papel.</p>
    </header>

    <?php if (!empty($errorCarga)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorCarga) ?>
        </div>
    <?php endif; ?>

    <!-- ── Mini-stats personales ── -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="mini-stat">
                <div class="mini-stat-num"><?= (int) $stats['total'] ?></div>
                <div class="mini-stat-lbl">Entregas <?= $filtroCampana ? 'en campaña' : 'totales' ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat">
                <div class="mini-stat-num"><?= number_format($stats['kilos'], 0, ',', '.') ?></div>
                <div class="mini-stat-lbl">Kg aportados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat">
                <div class="mini-stat-num"><?= number_format($stats['litros'], 0, ',', '.') ?></div>
                <div class="mini-stat-lbl">Litros AOVE</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat">
                <div class="mini-stat-num"><?= number_format($stats['liquidacion'], 0, ',', '.') ?> €</div>
                <div class="mini-stat-lbl">Liquidación estimada</div>
            </div>
        </div>
    </div>

    <!-- ── Filtro por campaña ── -->
    <?php if (count($misCampanas) > 0): ?>
        <form method="GET" class="d-flex gap-2 align-items-end mb-4 flex-wrap">
            <div>
                <label class="form-label small text-muted mb-1" for="filtro-campana">
                    <i class="bi bi-calendar-range"></i> Filtrar por campaña
                </label>
                <select name="campana" id="filtro-campana" class="form-select form-select-sm"
                        onchange="this.form.submit()" style="min-width: 240px;">
                    <option value="">— Todas mis campañas —</option>
                    <?php foreach ($misCampanas as $mc): ?>
                        <option value="<?= (int) $mc['id'] ?>" <?= $filtroCampana === (int) $mc['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mc['codigo']) ?>
                            (<?= htmlspecialchars($mc['estado']) ?>,
                            <?= number_format((float) $mc['precio_por_kilo'], 4, ',', '.') ?> €/kg)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtroCampana): ?>
                <a href="mis_entregas.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Quitar filtro
                </a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <!-- ── Tabla de entregas ── -->
    <div class="entregas-card">
        <header class="entregas-card-header">
            <div>
                <i class="bi bi-truck"></i>
                <h2>Histórico de entregas</h2>
            </div>
            <span class="badge-count"><?= count($entregas) ?> registro<?= count($entregas) === 1 ? '' : 's' ?></span>
        </header>

        <div class="table-responsive">
            <?php if (empty($entregas)): ?>
                <div class="entregas-vacio">
                    <i class="bi bi-inbox"></i>
                    <p>Todavía no tienes ninguna entrega registrada.<br>
                       Cuando lleves aceituna a la almazara, el administrador la registrará y aparecerá aquí.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover entregas-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Albarán</th>
                            <th>Fecha entrega</th>
                            <th>Campaña</th>
                            <th class="text-end">Kilos aceituna</th>
                            <th class="text-center">Rendimiento</th>
                            <th class="text-end">Litros AOVE</th>
                            <th class="text-end">Liquidación</th>
                            <th class="text-center">Justificante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregas as $e):
                            $codigo = 'ALB-' . str_pad((string) $e['id'], 6, '0', STR_PAD_LEFT);
                            $rend = (float) $e['rendimiento'];
                            $bClass = $rend >= 22 ? 'badge-alto' : ($rend >= 19 ? 'badge-medio' : 'badge-bajo');
                            $precioE = $e['precio_por_kilo'] !== null ? (float) $e['precio_por_kilo'] : null;
                            $liqE    = $precioE !== null ? round((float) $e['kilos_aceituna'] * $precioE, 2) : null;
                            $estCmp  = $e['campana_estado'] ?? null;
                        ?>
                            <tr>
                                <td><code class="codigo-albaran"><?= $codigo ?></code>
                                    <?php if (!empty($e['observaciones'])): ?>
                                        <br><small class="text-muted" title="<?= htmlspecialchars($e['observaciones']) ?>">
                                            <i class="bi bi-chat-dots"></i> Tiene notas
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($e['fecha_entrega'])) ?></strong>
                                    <br><small class="text-muted">
                                        <i class="bi bi-clock"></i>
                                        <?= date('H:i', strtotime($e['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if (!empty($e['campana_codigo'])): ?>
                                        <span class="badge bg-light text-dark" style="border:1px solid rgba(0,0,0,0.08);">
                                            <i class="bi bi-calendar-range"></i>
                                            <?= htmlspecialchars($e['campana_codigo']) ?>
                                        </span>
                                        <?php if ($estCmp === 'cerrada'): ?>
                                            <br><small class="text-muted"><i class="bi bi-archive"></i> cerrada</small>
                                        <?php endif; ?>
                                    <?php elseif (!empty($e['campana'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars($e['campana']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= number_format($e['kilos_aceituna'], 2, ',', '.') ?> kg
                                </td>
                                <td class="text-center">
                                    <span class="badge-rendimiento <?= $bClass ?>">
                                        <?= number_format($rend, 2, ',', '.') ?>%
                                    </span>
                                </td>
                                <td class="text-end fw-semibold" style="color: var(--color-primary);">
                                    <?= number_format($e['litros_aceite'], 2, ',', '.') ?> L
                                </td>
                                <td class="text-end fw-semibold" style="color: var(--color-accent-dark);"
                                    title="<?= $precioE !== null ? number_format($precioE, 4, ',', '.') . ' €/kg' : 'sin campaña' ?>">
                                    <?= $liqE !== null ? number_format($liqE, 2, ',', '.') . ' €' : '<span class="text-muted small">—</span>' ?>
                                </td>
                                <td class="text-center">
                                    <a href="core/generar_albaran.php?id_entrega=<?= (int) $e['id'] ?>"
                                       target="_blank" rel="noopener"
                                       class="btn-pdf-mini"
                                       title="Descargar albarán <?= $codigo ?>">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <span>PDF</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <p class="entregas-pie text-muted text-center mt-3">
        <i class="bi bi-info-circle"></i>
        El albarán es un documento generado electrónicamente con validez de justificante de depósito.
        El rendimiento graso definitivo se confirma tras la molturación, y la liquidación mostrada
        es <strong>estimada</strong> según el precio €/kg de la campaña — el importe final se ajusta al cierre de campaña.
    </p>

</main>

<?php require_once 'includes/footer.php'; ?>
