<?php
// Panel del operario: registra entregas de aceituna y consulta el histórico.
// No tiene acceso al panel de admin, sólo a la mesa de entregas y al PDF
// del albarán que acaba de generar.

session_start();

// El operario y el admin pueden registrar entregas. Cualquier otro rol fuera.
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['operario', 'admin'], true)) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'nueva_entrega') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Error de seguridad: token CSRF inválido. Recarga la página.';
    } else {
        $id_socio       = filter_input(INPUT_POST, 'id_socio', FILTER_VALIDATE_INT);
        $kilos_aceituna = filter_input(INPUT_POST, 'kilos_aceituna', FILTER_VALIDATE_FLOAT);
        $rendimiento    = filter_input(INPUT_POST, 'rendimiento', FILTER_VALIDATE_FLOAT);
        $fecha_entrega  = $_POST['fecha_entrega'] ?? date('Y-m-d');
        $observaciones  = trim($_POST['observaciones'] ?? '');

        if (!$id_socio || $id_socio <= 0) {
            $mensaje_error = 'Selecciona un socio válido.';
        } elseif (!$kilos_aceituna || $kilos_aceituna <= 0) {
            $mensaje_error = 'Los kilos de aceituna deben ser un número positivo.';
        } elseif (!$rendimiento || $rendimiento <= 0 || $rendimiento > 100) {
            $mensaje_error = 'El rendimiento debe estar entre 0.01% y 100%.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_entrega)) {
            $mensaje_error = 'La fecha no tiene un formato válido.';
        } else {
            try {
                $pdo = getConexion();
                // Anotamos en observaciones quién registró la entrega — útil para
                // saber qué operario atendió al socio si luego hay incidencias.
                $marcaOperario = '[Registrado por ' . ($_SESSION['nombre'] ?? 'operario') . ']';
                $obsFinal = $observaciones === '' ? $marcaOperario : ($observaciones . ' ' . $marcaOperario);

                $stmt = $pdo->prepare('
                    INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones)
                    VALUES (:id_socio, :fecha, :kilos, :rendimiento, :obs)
                ');
                $stmt->execute([
                    ':id_socio'    => $id_socio,
                    ':fecha'       => $fecha_entrega,
                    ':kilos'       => $kilos_aceituna,
                    ':rendimiento' => $rendimiento,
                    ':obs'         => $obsFinal,
                ]);
                $idEntregaNueva = (int) $pdo->lastInsertId();

                // Enlazar a la campaña activa que cubra la fecha. Si no hay
                // campaña activa, queda NULL — el admin lo verá al filtrar.
                $pdo->prepare('
                    UPDATE entregas e
                    JOIN campanas c
                      ON e.fecha_entrega BETWEEN c.fecha_inicio AND c.fecha_fin
                     AND c.estado = "activa"
                    SET e.id_campana = c.id
                    WHERE e.id = :id
                ')->execute([':id' => $idEntregaNueva]);

                $mensaje_exito = 'Entrega registrada correctamente. Ya puedes imprimir el albarán desde la tabla.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {
                error_log('Error al insertar entrega (operario): ' . $e->getMessage());
                $mensaje_error = 'Error al registrar la entrega. Inténtalo de nuevo.';
            }
        }
    }
}

try {
    $pdo = getConexion();

    $socios = $pdo->query('
        SELECT id, nombre, apellidos, dni
        FROM usuarios
        WHERE rol = "socio" AND activo = 1
        ORDER BY apellidos, nombre
    ')->fetchAll();

    // Campaña activa: el operario debe ver a qué campaña irán las entregas
    // de hoy y a qué precio se liquidarán. Si no hay activa, mostraremos un
    // banner pidiendo al admin que abra una.
    $campanaActiva = $pdo->query('
        SELECT id, codigo, fecha_inicio, fecha_fin, precio_por_kilo
        FROM campanas
        WHERE estado = "activa"
          AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
        ORDER BY fecha_inicio DESC
        LIMIT 1
    ')->fetch() ?: null;

    // Últimas 50 entregas, ya con la campaña enlazada para mostrarla en la tabla.
    $entregas = $pdo->query('
        SELECT e.id, e.fecha_entrega, e.kilos_aceituna, e.rendimiento,
               e.litros_aceite, e.observaciones, e.created_at,
               u.nombre, u.apellidos, u.dni,
               c.codigo AS campana_codigo, c.precio_por_kilo
        FROM entregas e
        INNER JOIN usuarios u ON e.id_socio = u.id
        LEFT  JOIN campanas c ON c.id = e.id_campana
        ORDER BY e.created_at DESC
        LIMIT 50
    ')->fetchAll();

    // KPI: kg recibidos hoy y nº de entregas hoy
    $stats = $pdo->query('
        SELECT
            COUNT(*)                                   AS entregas_hoy,
            COALESCE(SUM(kilos_aceituna), 0)           AS kilos_hoy,
            COALESCE(SUM(litros_aceite), 0)            AS litros_hoy
        FROM entregas
        WHERE fecha_entrega = CURDATE()
    ')->fetch();

} catch (PDOException $e) {
    error_log('Error en operario.php: ' . $e->getMessage());
    $socios        = [];
    $entregas      = [];
    $campanaActiva = null;
    $stats         = ['entregas_hoy' => 0, 'kilos_hoy' => 0, 'litros_hoy' => 0];
    $mensaje_error = $mensaje_error ?: 'Error al cargar los datos.';
}

$esAdminPanel = true;
$pageTitle    = 'Mesa de Entregas | Operario';
$adminCssVer  = @filemtime(__DIR__ . '/assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="assets/css/admin.css?v=' . $adminCssVer . '">';
require_once 'includes/header.php';
?>

<?php require_once 'includes/operario_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header">
        <h1>
            <i class="bi bi-truck" style="color: var(--color-accent-dark);" aria-hidden="true"></i>
            Mesa de <em>Entregas</em>
        </h1>
        <p>Registra cada aporte de aceituna que llegue a la almazara. El sistema calcula los litros estimados automáticamente.</p>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <?= htmlspecialchars($mensaje_exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- Banner de campaña activa: contexto siempre visible para el operario -->
    <?php if ($campanaActiva): ?>
        <div class="alert alert-custom alert-success d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
            <div>
                <i class="bi bi-calendar-range"></i>
                Campaña activa: <strong><?= htmlspecialchars($campanaActiva['codigo']) ?></strong>
                <span class="text-muted small">
                    (<?= date('d/m/Y', strtotime($campanaActiva['fecha_inicio'])) ?>
                    → <?= date('d/m/Y', strtotime($campanaActiva['fecha_fin'])) ?>)
                </span>
            </div>
            <span class="badge bg-warning text-dark">
                <i class="bi bi-currency-euro"></i>
                Precio liquidación: <?= number_format((float) $campanaActiva['precio_por_kilo'], 4, ',', '.') ?> €/kg
            </span>
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>No hay campaña activa</strong> que cubra la fecha de hoy. Las entregas se registrarán
                pero quedarán sin enlazar hasta que un administrador abra la campaña correspondiente.
            </div>
        </div>
    <?php endif; ?>

    <!-- KPIs del día -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="bi bi-clipboard-check"></i></div>
                <div class="stat-label">Entregas hoy</div>
                <div class="stat-value"><?= (int) $stats['entregas_hoy'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-box-seam"></i></div>
                <div class="stat-label">Kilos hoy</div>
                <div class="stat-value">
                    <?= number_format((float) $stats['kilos_hoy'], 0, ',', '.') ?>
                    <span class="stat-unit">kg</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-droplet-fill"></i></div>
                <div class="stat-label">Litros AOVE estimados hoy</div>
                <div class="stat-value">
                    <?= number_format((float) $stats['litros_hoy'], 0, ',', '.') ?>
                    <span class="stat-unit">L</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Formulario -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>
                    <h2>Nueva Entrega</h2>
                </div>
                <div class="form-card-body">
                    <form method="POST" action="operario.php" id="form-nueva-entrega" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="nueva_entrega">

                        <div class="mb-3">
                            <label for="select-socio" class="admin-label">
                                <i class="bi bi-person" aria-hidden="true"></i> Socio
                            </label>
                            <select class="admin-select" id="select-socio" name="id_socio" required>
                                <option value="">— Selecciona un socio —</option>
                                <?php foreach ($socios as $socio): ?>
                                    <option value="<?= $socio['id'] ?>">
                                        <?= htmlspecialchars($socio['apellidos'] . ', ' . $socio['nombre']) ?>
                                        — DNI: <?= htmlspecialchars($socio['dni']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="input-kilos" class="admin-label">
                                <i class="bi bi-box-seam" aria-hidden="true"></i> Kilos de aceituna
                            </label>
                            <input type="number" class="admin-input" id="input-kilos"
                                name="kilos_aceituna" min="0.01" step="0.01"
                                placeholder="Ej: 1500.00" required>
                        </div>

                        <div class="mb-3">
                            <label for="input-rendimiento" class="admin-label">
                                <i class="bi bi-percent" aria-hidden="true"></i> Rendimiento graso (%)
                            </label>
                            <input type="number" class="admin-input" id="input-rendimiento"
                                name="rendimiento" min="0.01" max="100" step="0.01"
                                value="21.00" placeholder="Ej: 21.00" required>
                        </div>

                        <div class="mb-3">
                            <label for="input-fecha" class="admin-label">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha de entrega
                            </label>
                            <input type="date" class="admin-input" id="input-fecha"
                                name="fecha_entrega" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="input-obs" class="admin-label">
                                <i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones
                                <small style="font-weight:400; text-transform:none; color:var(--color-text-muted);">(opcional)</small>
                            </label>
                            <textarea class="admin-textarea" id="input-obs" name="observaciones"
                                placeholder="Variedad, parcela, observaciones..." rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn-registrar" id="btn-registrar-entrega">
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                            Registrar entrega
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="header-left">
                        <i class="bi bi-table" aria-hidden="true"></i>
                        <h2>Últimas entregas</h2>
                    </div>
                    <span class="badge-count">
                        <?= count($entregas) ?> registro<?= count($entregas) !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <div class="table-responsive">
                    <?php if (empty($entregas)): ?>
                        <div class="table-empty">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <p>Aún no se han registrado entregas.</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-hover table-admin mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Campaña</th>
                                    <th>Socio</th>
                                    <th class="text-end">Kilos</th>
                                    <th class="text-center">Rend.</th>
                                    <th class="text-end">Liquidación</th>
                                    <th class="text-center">Albarán</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entregas as $e):
                                    $rend = (float) $e['rendimiento'];
                                    $badgeClass = $rend >= 22 ? 'badge-alto' : ($rend >= 19 ? 'badge-medio' : 'badge-bajo');
                                    $codigoAlb  = 'ALB-' . str_pad((string) $e['id'], 6, '0', STR_PAD_LEFT);
                                    $precioE = $e['precio_por_kilo'] !== null ? (float) $e['precio_por_kilo'] : null;
                                    $liqE    = $precioE !== null ? round((float) $e['kilos_aceituna'] * $precioE, 2) : null;
                                ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-calendar3 text-muted" style="font-size:0.75rem;" aria-hidden="true"></i>
                                            <?= date('d/m/Y', strtotime($e['fecha_entrega'])) ?>
                                            <br><small class="text-muted"><?= date('H:i', strtotime($e['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($e['campana_codigo'])): ?>
                                                <span class="badge bg-light text-dark" style="border:1px solid rgba(0,0,0,0.08);">
                                                    <i class="bi bi-calendar-range"></i>
                                                    <?= htmlspecialchars($e['campana_codigo']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars(trim(($e['apellidos'] ?? '') !== ''
                                                ? $e['apellidos'] . ', ' . $e['nombre']
                                                : $e['nombre'])) ?></strong>
                                            <?php if (!empty($e['dni'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($e['dni']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= number_format($e['kilos_aceituna'], 2, ',', '.') ?> kg
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-rendimiento <?= $badgeClass ?>">
                                                <?= number_format($rend, 2, ',', '.') ?>%
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold" style="color: var(--color-accent-dark);"
                                            title="<?= $precioE !== null ? number_format($precioE, 4, ',', '.') . ' €/kg' : 'sin campaña' ?>">
                                            <?= $liqE !== null ? number_format($liqE, 2, ',', '.') . ' €' : '<span class="text-muted small">—</span>' ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="core/generar_albaran.php?id_entrega=<?= (int) $e['id'] ?>"
                                               target="_blank" rel="noopener"
                                               class="btn btn-sm btn-outline-secondary"
                                               title="Imprimir albarán <?= $codigoAlb ?>">
                                                <i class="bi bi-printer"></i>
                                            </a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validación cliente — la real ocurre en servidor.
(() => {
    const form = document.getElementById('form-nueva-entrega');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        const socio = document.getElementById('select-socio');
        const kilos = document.getElementById('input-kilos');
        const rend  = document.getElementById('input-rendimiento');
        let ok = true;
        if (!socio.value) { socio.style.borderColor = '#C0392B'; ok = false; } else socio.style.borderColor = '';
        if (!kilos.value || parseFloat(kilos.value) <= 0) { kilos.style.borderColor = '#C0392B'; ok = false; } else kilos.style.borderColor = '';
        if (!rend.value || parseFloat(rend.value) <= 0 || parseFloat(rend.value) > 100) { rend.style.borderColor = '#C0392B'; ok = false; } else rend.style.borderColor = '';
        if (!ok) e.preventDefault();
    });
})();
</script>
</body>
</html>
