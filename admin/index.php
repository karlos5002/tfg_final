<?php
// Dashboard del admin: registro de entregas, KPIs y enlaces a los demás
// módulos de gestión (usuarios, votaciones, visitas).

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/albaran_pdf.php';
require_once __DIR__ . '/../core/mailer.php';

$mensaje_exito = '';
$mensaje_error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// POST: registrar una nueva entrega de aceituna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = 'Error de seguridad: token CSRF inválido. Recarga la página.';
    } else {

        switch ($_POST['accion']) {

            case 'nueva_entrega':
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
                        $stmt = $pdo->prepare('
                            INSERT INTO entregas (id_socio, fecha_entrega, kilos_aceituna, rendimiento, observaciones)
                            VALUES (:id_socio, :fecha, :kilos, :rendimiento, :obs)
                        ');
                        $stmt->execute([
                            ':id_socio'    => $id_socio,
                            ':fecha'       => $fecha_entrega,
                            ':kilos'       => $kilos_aceituna,
                            ':rendimiento' => $rendimiento,
                            ':obs'         => $observaciones ?: null,
                        ]);
                        $idEntregaNueva = (int) $pdo->lastInsertId();

                        // Resolver id_campana por rango de fechas (única consulta).
                        // Si no hay campaña ACTIVA que cubra la fecha, queda NULL —
                        // el admin lo verá vacío y podrá crear la campaña.
                        $pdo->prepare('
                            UPDATE entregas e
                            JOIN campanas c
                              ON e.fecha_entrega BETWEEN c.fecha_inicio AND c.fecha_fin
                             AND c.estado = "activa"
                            SET e.id_campana = c.id
                            WHERE e.id = :id
                        ')->execute([':id' => $idEntregaNueva]);

                        $mensaje_exito = 'Entrega registrada correctamente.';
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // ── Email al socio con el albarán PDF adjunto ───────
                        // Si falla, no abortamos: la entrega ya está registrada.
                        try {
                            $entrega = cargarDatosAlbaran($pdo, $idEntregaNueva, 0, true);
                            $pdfBin  = construirAlbaranPdf($entrega);

                            $emailSocio = $entrega['socio_email'] ?? null;
                            if ($emailSocio) {
                                $numAlbaran = 'ALB-' . str_pad((string) $idEntregaNueva, 6, '0', STR_PAD_LEFT);

                                enviarEmail(
                                    $emailSocio,
                                    'Entrega registrada · ' . $numAlbaran,
                                    emailEntregaRegistrada(
                                        [
                                            'id'              => (int) $entrega['id'],
                                            'fecha_entrega'   => $entrega['fecha_entrega'],
                                            'kilos_aceituna'  => (float) $entrega['kilos_aceituna'],
                                            'rendimiento'     => (float) $entrega['rendimiento'],
                                            'litros_aceite'   => $entrega['litros_aceite'] ?? null,
                                            'observaciones'   => $entrega['observaciones'] ?? null,
                                            'campana'         => $entrega['campana_codigo'] ?? $entrega['campana'] ?? null,
                                            'precio_por_kilo' => $entrega['precio_por_kilo'] ?? null,
                                        ],
                                        [
                                            'nombre'    => $entrega['socio_nombre'],
                                            'apellidos' => $entrega['socio_apellidos'] ?? '',
                                            'dni'       => $entrega['socio_dni'] ?? '',
                                        ]
                                    ),
                                    [[
                                        'contenido' => $pdfBin,
                                        'nombre'    => $numAlbaran . '.pdf',
                                        'mime'      => 'application/pdf',
                                    ]]
                                );
                            }
                        } catch (\Throwable $e) {
                            error_log('[admin/index] Email albarán fallido: ' . $e->getMessage());
                        }

                    } catch (PDOException $e) {
                        error_log('Error al insertar entrega: ' . $e->getMessage());
                        $mensaje_error = 'Error al registrar la entrega. Inténtalo de nuevo.';
                    }
                }
                break;
        }
    }
}

// Filtros desde admin_usuarios.php (botones "Ver entregas / Ver pedidos") y
// desde admin_campanas.php (botón "Ver entregas de esta campaña").
$filtroSocio   = filter_input(INPUT_GET, 'filtro_socio',   FILTER_VALIDATE_INT);
$filtroCliente = filter_input(INPUT_GET, 'filtro_cliente', FILTER_VALIDATE_INT);
$filtroCampana = filter_input(INPUT_GET, 'campana',        FILTER_VALIDATE_INT);
$usuarioFiltro = null;
$campanaFiltro = null;
$pedidos       = [];

try {
    $pdo = getConexion();

    if ($filtroSocio || $filtroCliente) {
        $idFiltro = $filtroSocio ?: $filtroCliente;
        $stmtUF = $pdo->prepare('SELECT id, nombre, apellidos, dni, email, rol FROM usuarios WHERE id = :id');
        $stmtUF->execute([':id' => $idFiltro]);
        $usuarioFiltro = $stmtUF->fetch() ?: null;
    }

    if ($filtroCampana) {
        $stmtCF = $pdo->prepare('SELECT id, codigo, fecha_inicio, fecha_fin, precio_por_kilo, estado FROM campanas WHERE id = :id');
        $stmtCF->execute([':id' => $filtroCampana]);
        $campanaFiltro = $stmtCF->fetch() ?: null;
    }

    // Socios activos para el <select> del formulario
    $stmtSocios = $pdo->query('
        SELECT id, nombre, apellidos, dni
        FROM usuarios
        WHERE rol = "socio" AND activo = 1
        ORDER BY apellidos, nombre
    ');
    $socios = $stmtSocios->fetchAll();

    // Lista de campañas para el dropdown (todas: el admin filtra activas y cerradas)
    $listaCampanas = $pdo->query('SELECT id, codigo, estado, precio_por_kilo FROM campanas ORDER BY fecha_inicio DESC')->fetchAll();
    $campanaActiva = $pdo->query('SELECT id, codigo, precio_por_kilo FROM campanas WHERE estado = "activa" ORDER BY fecha_inicio DESC LIMIT 1')->fetch() ?: null;

    // Construcción dinámica del WHERE de entregas: combina socio + campaña.
    // Las entregas anuladas SÍ se traen (para que el admin las vea tachadas
    // y pueda revisar el histórico), pero quedan fuera de los totales más abajo.
    $sqlBase = '
        SELECT e.id, e.fecha_entrega, e.kilos_aceituna, e.rendimiento,
               e.litros_aceite, e.observaciones, e.created_at,
               e.id_campana,
               e.anulada, e.motivo_anulacion, e.fecha_anulacion,
               u.nombre, u.apellidos, u.dni,
               c.codigo AS campana_codigo, c.precio_por_kilo,
               ua.nombre AS admin_anula_nombre
        FROM entregas e
        INNER JOIN usuarios u ON e.id_socio = u.id
        LEFT  JOIN campanas c  ON c.id  = e.id_campana
        LEFT  JOIN usuarios ua ON ua.id = e.id_admin_anula
        WHERE 1 = 1
    ';
    $params = [];
    if ($filtroSocio)   { $sqlBase .= ' AND e.id_socio   = :id_socio';   $params[':id_socio']   = $filtroSocio; }
    if ($filtroCampana) { $sqlBase .= ' AND e.id_campana = :id_campana'; $params[':id_campana'] = $filtroCampana; }
    $sqlBase .= ' ORDER BY e.fecha_entrega DESC, e.created_at DESC';

    $stmtEntregas = $pdo->prepare($sqlBase);
    $stmtEntregas->execute($params);
    $entregas = $stmtEntregas->fetchAll();

    // Pedidos del cliente filtrado: cabecera + recuento de líneas en una query
    if ($filtroCliente) {
        $stmtPed = $pdo->prepare('
            SELECT p.id, p.fecha_pedido, p.total, p.estado, p.metodo_pago,
                   COUNT(lp.id_producto) AS num_lineas,
                   COALESCE(SUM(lp.cantidad), 0) AS total_unidades
            FROM pedidos p
            LEFT JOIN lineas_pedido lp ON lp.id_pedido = p.id
            WHERE p.id_usuario = :id
            GROUP BY p.id
            ORDER BY p.fecha_pedido DESC
        ');
        $stmtPed->execute([':id' => $filtroCliente]);
        $pedidos = $stmtPed->fetchAll();
    }

    // Stats acotadas a los mismos filtros activos (socio y/o campaña).
    // Excluimos entregas anuladas: una entrega cancelada no debe inflar los
    // KPIs ni la liquidación total, igual que un asiento contable rectificado.
    $sqlStats = '
        SELECT COUNT(*) as total_entregas,
               COALESCE(SUM(kilos_aceituna), 0) as total_kilos,
               COALESCE(SUM(litros_aceite), 0) as total_litros,
               COUNT(DISTINCT id_socio) as socios_activos
        FROM entregas WHERE anulada = 0
    ';
    $paramsStats = [];
    if ($filtroSocio)   { $sqlStats .= ' AND id_socio   = :id_socio';   $paramsStats[':id_socio']   = $filtroSocio; }
    if ($filtroCampana) { $sqlStats .= ' AND id_campana = :id_campana'; $paramsStats[':id_campana'] = $filtroCampana; }
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($paramsStats);
    $stats = $stmtStats->fetch();

} catch (PDOException $e) {
    error_log('Error en consultas admin: ' . $e->getMessage());
    $socios = [];
    $entregas = [];
    $stats = ['total_entregas' => 0, 'total_kilos' => 0, 'total_litros' => 0, 'socios_activos' => 0];
    $mensaje_error = 'Error al cargar los datos. Verifica la conexión a la base de datos.';
}
?>
<?php
$esAdminPanel = true;
$relRoot      = '../';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>

    <?php require_once '../includes/admin_navbar.php'; ?>


    <main class="container" id="contenido-principal">

        <!-- ── Cabecera de página ── -->
        <div class="page-header">
            <h1>
                <i class="bi bi-speedometer2" style="color: var(--color-accent-dark);" aria-hidden="true"></i>
                Panel de Control de la <em>Almazara</em>
            </h1>
            <p>Gestiona las entregas de aceituna y controla la producción de la cooperativa.</p>
        </div>


        <!-- ── Mensajes de estado (éxito / error) ── -->
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

        <!-- ── Banner de filtro activo (entregas/pedidos de un usuario concreto) ── -->
        <?php if ($usuarioFiltro && ($filtroSocio || $filtroCliente)): ?>
            <?php
                $tipoFiltro = $filtroSocio ? 'entregas del socio' : 'pedidos del cliente';
                $nombreFiltro = trim(($usuarioFiltro['apellidos'] ?? '') !== ''
                    ? $usuarioFiltro['apellidos'] . ', ' . $usuarioFiltro['nombre']
                    : $usuarioFiltro['nombre']);
            ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
                <div>
                    <i class="bi bi-funnel-fill" aria-hidden="true"></i>
                    Mostrando <strong><?= $tipoFiltro ?></strong>:
                    <strong><?= htmlspecialchars($nombreFiltro) ?></strong>
                    <span class="text-muted small">(<?= htmlspecialchars($usuarioFiltro['email']) ?>)</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="usuarios.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-people-fill" aria-hidden="true"></i> Volver a Usuarios
                    </a>
                    <a href="index.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-lg" aria-hidden="true"></i> Quitar filtro
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Banner de filtro por campaña ── -->
        <?php if ($campanaFiltro): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
                <div>
                    <i class="bi bi-calendar-range"></i>
                    Mostrando entregas de la campaña
                    <strong><?= htmlspecialchars($campanaFiltro['codigo']) ?></strong>
                    <span class="badge bg-<?= $campanaFiltro['estado'] === 'activa' ? 'success' : 'secondary' ?>">
                        <?= htmlspecialchars($campanaFiltro['estado']) ?>
                    </span>
                    <span class="text-muted small">
                        (<?= number_format((float) $campanaFiltro['precio_por_kilo'], 4, ',', '.') ?> €/kg)
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <a href="campanas.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-calendar-range"></i> Ver Campañas
                    </a>
                    <?php
                        // Mantenemos otros filtros si los hay; sólo quitamos campana=
                        $qsSinCampana = http_build_query(array_filter([
                            'filtro_socio'   => $filtroSocio   ?: null,
                            'filtro_cliente' => $filtroCliente ?: null,
                        ]));
                    ?>
                    <a href="index.php<?= $qsSinCampana ? '?' . $qsSinCampana : '' ?>" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-lg"></i> Quitar filtro
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Dropdown filtro de campaña (siempre visible si hay >1 campañas) ── -->
        <?php if (!$filtroCliente && count($listaCampanas) > 0): ?>
            <form method="GET" class="d-flex gap-2 align-items-end mb-3 flex-wrap">
                <?php if ($filtroSocio): ?>
                    <input type="hidden" name="filtro_socio" value="<?= (int) $filtroSocio ?>">
                <?php endif; ?>
                <div>
                    <label class="admin-label" for="filtro-campana">
                        <i class="bi bi-calendar-range"></i> Filtrar por campaña
                    </label>
                    <select name="campana" id="filtro-campana" class="admin-select" onchange="this.form.submit()">
                        <option value="">— Todas las campañas —</option>
                        <?php foreach ($listaCampanas as $lc): ?>
                            <option value="<?= (int) $lc['id'] ?>" <?= $filtroCampana === (int) $lc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lc['codigo']) ?>
                                (<?= htmlspecialchars($lc['estado']) ?>,
                                <?= number_format((float) $lc['precio_por_kilo'], 4, ',', '.') ?> €/kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>


        <div class="row g-3 mb-3">
            <!-- Ingresos Totales -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-emerald">
                        <i class="bi bi-currency-euro" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Ingresos Totales</div>
                    <div class="stat-value" id="stat-ingresos">
                        <span class="stat-unit" style="font-size:0.8rem;">cargando…</span>
                    </div>
                    <div class="stat-trend trend-success">
                        <i class="bi bi-bag-check" aria-hidden="true"></i> Pedidos pagados
                    </div>
                </div>
            </div>

            <!-- Total Pedidos -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-amber">
                        <i class="bi bi-cart3" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Total Pedidos</div>
                    <div class="stat-value" id="stat-pedidos">
                        <span class="stat-unit" style="font-size:0.8rem;">cargando…</span>
                    </div>
                    <div class="stat-trend trend-info">
                        <i class="bi bi-shop" aria-hidden="true"></i> Tienda online
                    </div>
                </div>
            </div>

            <!-- Total entregas -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-green">
                        <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Total entregas</div>
                    <div class="stat-value">
                        <?= number_format($stats['total_entregas'], 0, ',', '.') ?>
                    </div>
                </div>
            </div>

            <!-- Total kilos -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-gold">
                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Kilos de aceituna</div>
                    <div class="stat-value">
                        <?= number_format($stats['total_kilos'], 0, ',', '.') ?>
                        <span class="stat-unit">kg</span>
                    </div>
                </div>
            </div>

            <!-- Total litros -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-gold">
                        <i class="bi bi-droplet-fill" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Litros de AOVE</div>
                    <div class="stat-value">
                        <?= number_format($stats['total_litros'], 0, ',', '.') ?>
                        <span class="stat-unit">L</span>
                    </div>
                </div>
            </div>

            <!-- Socios activos -->
            <div class="col-6 col-xl-2 col-lg-4">
                <div class="stat-card">
                    <div class="stat-icon icon-blue">
                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                    </div>
                    <div class="stat-label">Socios con entregas</div>
                    <div class="stat-value">
                        <?= number_format($stats['socios_activos'], 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>


        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3 col-xl">
                <a href="noticias.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #C25E0F 0%, #8a4108 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(194,94,15,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-megaphone-fill fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Noticias</div>
                        <div style="font-size: .8rem; opacity: .85;">Tablón de comunicados</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="calendario.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #6B4220 0%, #4A2C12 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(107,66,32,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-calendar3 fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Calendario</div>
                        <div style="font-size: .8rem; opacity: .85;">Tareas del olivar</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="campanas.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #4A5D3B 0%, #33422A 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(74,93,59,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-calendar-range fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Campañas</div>
                        <div style="font-size: .8rem; opacity: .85;">Precios y periodos</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="stock.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #8B5A2B 0%, #6B4220 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(139,90,43,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-boxes fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Gestión de Stock</div>
                        <div style="font-size: .8rem; opacity: .85;">Reposiciones e inventario</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="usuarios.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #2C4C3B 0%, #1E3529 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(44,76,59,0.25)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-people-fill fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Gestión de Usuarios</div>
                        <div style="font-size: .8rem; opacity: .85;">Modificar roles y permisos</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="votaciones.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #B8962E 0%, #8a6c1d 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(184,150,46,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(255,255,255,0.18);">
                        <i class="bi bi-megaphone-fill fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Votaciones</div>
                        <div style="font-size: .8rem; opacity: .85;">Convocar asambleas y ver resultados</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>

            <div class="col-md-6 col-lg-3 col-xl">
                <a href="visitas.php"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none shadow-sm h-100"
                   style="background: linear-gradient(135deg, #5A7A5F 0%, #3A6150 100%); color: #fff; transition: transform .2s, box-shadow .2s;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(90,122,95,0.30)';"
                   onmouseout="this.style.transform=''; this.style.boxShadow='';">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width: 52px; height: 52px; background: rgba(212,175,55,0.22);">
                        <i class="bi bi-calendar-check-fill fs-4" aria-hidden="true"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size: 1rem;">Visitas guiadas</div>
                        <div style="font-size: .8rem; opacity: .85;">Reservas de oleoturismo</div>
                    </div>
                    <i class="bi bi-arrow-right-circle fs-4" aria-hidden="true"></i>
                </a>
            </div>
        </div>


        <?php if (!$filtroCliente): /* En modo "ver pedidos del cliente" ocultamos analítica y entregas */ ?>
        <!-- ═══ SECCIÓN DE GRÁFICOS INTERACTIVOS (Chart.js) ═══ -->
        <div class="section-divider">
            <span><i class="bi bi-graph-up" aria-hidden="true"></i> Analítica Visual</span>
        </div>

        <div class="row g-4 charts-section">
            <!-- ─── Gráfico 1: Ventas por Variedad (Doughnut) ─── -->
            <div class="col-md-5">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <i class="bi bi-pie-chart-fill" aria-hidden="true"></i>
                        <h3>Ventas por Variedad</h3>
                    </div>
                    <div class="chart-card-body">
                        <!-- Estado de carga inicial (se reemplaza con el gráfico) -->
                        <div class="chart-loading" id="loading-ventas">
                            <div class="spinner"></div>
                            <span>Cargando datos…</span>
                        </div>
                        <canvas id="graficoVentas" style="display:none;"></canvas>
                    </div>
                </div>
            </div>

            <!-- ─── Gráfico 2: Kilos por Mes (Barras) ─── -->
            <div class="col-md-7">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <i class="bi bi-bar-chart-fill" aria-hidden="true"></i>
                        <h3>Kilos de Aceituna por Mes</h3>
                    </div>
                    <div class="chart-card-body">
                        <!-- Estado de carga inicial -->
                        <div class="chart-loading" id="loading-entregas">
                            <div class="spinner"></div>
                            <span>Cargando datos…</span>
                        </div>
                        <canvas id="graficoEntregas" style="display:none;"></canvas>
                    </div>
                </div>
            </div>
        </div>


        <div class="section-divider">
            <span><i class="bi bi-truck" aria-hidden="true"></i> Gestión de Entregas</span>
        </div>

        <!-- Layout: formulario a la izquierda, tabla a la derecha -->
        <div class="row g-4">

            <!-- ─── COLUMNA IZQUIERDA: Formulario de nueva entrega ─── -->
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="form-card-header">
                        <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>
                        <h2>Registrar Nueva Entrega</h2>
                    </div>
                    <div class="form-card-body">
                        <form method="POST" action="index.php" id="form-nueva-entrega" novalidate>
                            <!-- Token CSRF oculto -->
                            <input type="hidden" name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" value="nueva_entrega">

                            <!-- Select: Socio -->
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

                            <!-- Input: Kilos de aceituna -->
                            <div class="mb-3">
                                <label for="input-kilos" class="admin-label">
                                    <i class="bi bi-box-seam" aria-hidden="true"></i> Kilos de aceituna
                                </label>
                                <input type="number" class="admin-input" id="input-kilos"
                                    name="kilos_aceituna" min="0.01" step="0.01"
                                    placeholder="Ej: 1500.00" required>
                            </div>

                            <!-- Input: Rendimiento -->
                            <div class="mb-3">
                                <label for="input-rendimiento" class="admin-label">
                                    <i class="bi bi-percent" aria-hidden="true"></i> Rendimiento graso (%)
                                </label>
                                <input type="number" class="admin-input" id="input-rendimiento"
                                    name="rendimiento" min="0.01" max="100" step="0.01"
                                    value="21.00" placeholder="Ej: 21.00" required>
                            </div>

                            <!-- Input: Fecha -->
                            <div class="mb-3">
                                <label for="input-fecha" class="admin-label">
                                    <i class="bi bi-calendar-event" aria-hidden="true"></i> Fecha de entrega
                                </label>
                                <input type="date" class="admin-input" id="input-fecha"
                                    name="fecha_entrega" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <!-- Textarea: Observaciones -->
                            <div class="mb-3">
                                <label for="input-obs" class="admin-label">
                                    <i class="bi bi-chat-left-text" aria-hidden="true"></i> Observaciones
                                    <small style="font-weight:400; text-transform:none; color:var(--color-text-muted);">(opcional)</small>
                                </label>
                                <textarea class="admin-textarea" id="input-obs" name="observaciones"
                                    placeholder="Variedad, parcela, observaciones..." rows="2"></textarea>
                            </div>

                            <!-- Botón enviar -->
                            <button type="submit" class="btn-registrar" id="btn-registrar-entrega">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                Registrar entrega
                            </button>
                        </form>
                    </div>
                </div>
            </div>


            <!-- ─── COLUMNA DERECHA: Tabla de entregas ─── -->
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="table-card-header">
                        <div class="header-left">
                            <i class="bi bi-table" aria-hidden="true"></i>
                            <h2>Historial de Entregas</h2>
                        </div>
                        <span class="badge-count">
                            <?= count($entregas) ?> registro<?= count($entregas) !== 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <?php if (empty($entregas)): ?>
                            <!-- Estado vacío -->
                            <div class="table-empty">
                                <i class="bi bi-inbox" aria-hidden="true"></i>
                                <p>No hay entregas registradas todavía.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-hover table-admin mb-0" id="tabla-entregas">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Fecha entrega</th>
                                        <th scope="col">Campaña</th>
                                        <th scope="col">Socio</th>
                                        <th scope="col" class="text-end">Kilos</th>
                                        <th scope="col" class="text-center">Rendimiento</th>
                                        <th scope="col" class="text-end">Litros AOVE</th>
                                        <th scope="col" class="text-end">Liquidación</th>
                                        <th scope="col" class="text-center">Albarán</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalLiquidacion = 0.0;
                                    foreach ($entregas as $i => $e):
                                        $rend = (float) $e['rendimiento'];
                                        if ($rend >= 22)      { $badgeClass = 'badge-alto'; }
                                        elseif ($rend >= 19)  { $badgeClass = 'badge-medio'; }
                                        else                  { $badgeClass = 'badge-bajo'; }
                                        $codigoAlb = 'ALB-' . str_pad((string) $e['id'], 6, '0', STR_PAD_LEFT);
                                        $estaAnulada = ((int) ($e['anulada'] ?? 0)) === 1;
                                        // Liquidación = kilos × precio_por_kilo (NULL si no hay campaña enlazada).
                                        // Las entregas anuladas no contribuyen al total de liquidación.
                                        $precioE = $e['precio_por_kilo'] !== null ? (float) $e['precio_por_kilo'] : null;
                                        $liqE    = $precioE !== null ? round((float) $e['kilos_aceituna'] * $precioE, 2) : null;
                                        if (!$estaAnulada && $liqE !== null) $totalLiquidacion += $liqE;
                                    ?>
                                        <tr class="<?= $estaAnulada ? 'fila-anulada' : '' ?>"
                                            <?= $estaAnulada ? 'title="Entrega anulada — Motivo: ' . htmlspecialchars($e['motivo_anulacion'] ?? '') . '"' : '' ?>>
                                            <td class="text-muted"><?= $i + 1 ?></td>
                                            <td>
                                                <i class="bi bi-calendar3 text-muted" style="font-size:0.75rem;" aria-hidden="true"></i>
                                                <?= date('d/m/Y', strtotime($e['fecha_entrega'])) ?>
                                                <br><small class="text-muted">
                                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                                    <?= date('H:i', strtotime($e['created_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if (!empty($e['campana_codigo'])): ?>
                                                    <a href="?campana=<?= (int) $e['id_campana'] ?>"
                                                       class="badge bg-light text-dark text-decoration-none"
                                                       style="border:1px solid rgba(0,0,0,0.08);">
                                                        <i class="bi bi-calendar-range"></i>
                                                        <?= htmlspecialchars($e['campana_codigo']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-dash"></i> sin campaña</span>
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
                                                    <?= number_format($e['rendimiento'], 2, ',', '.') ?>%
                                                </span>
                                            </td>
                                            <td class="text-end fw-semibold" style="color: var(--color-primary);">
                                                <?= number_format($e['litros_aceite'], 2, ',', '.') ?> L
                                            </td>
                                            <td class="text-end fw-semibold" style="color: var(--color-accent-dark);"
                                                title="Kilos × <?= $precioE !== null ? number_format($precioE, 4, ',', '.') . ' €/kg' : 'sin precio' ?>">
                                                <?= $liqE !== null ? number_format($liqE, 2, ',', '.') . ' €' : '<span class="text-muted small">—</span>' ?>
                                            </td>
                                            <td class="text-center" style="white-space:nowrap;">
                                                <?php if ($estaAnulada): ?>
                                                    <span class="badge bg-danger" title="Anulada por <?= htmlspecialchars($e['admin_anula_nombre'] ?? 'admin') ?> el <?= date('d/m/Y', strtotime($e['fecha_anulacion'])) ?>">
                                                        <i class="bi bi-x-octagon" aria-hidden="true"></i> ANULADA
                                                    </span>
                                                <?php else: ?>
                                                    <a href="../core/generar_albaran.php?id_entrega=<?= (int) $e['id'] ?>"
                                                       target="_blank" rel="noopener"
                                                       class="btn btn-sm btn-outline-secondary"
                                                       title="Descargar albarán <?= $codigoAlb ?>">
                                                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger btn-anular-entrega"
                                                            data-id-entrega="<?= (int) $e['id'] ?>"
                                                            data-codigo="<?= $codigoAlb ?>"
                                                            data-socio="<?= htmlspecialchars(trim(($e['apellidos'] ?? '') . ', ' . $e['nombre'])) ?>"
                                                            data-kilos="<?= number_format($e['kilos_aceituna'], 2, ',', '.') ?>"
                                                            title="Anular entrega <?= $codigoAlb ?>">
                                                        <i class="bi bi-x-octagon" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--color-bg-alt); font-weight: 700;">
                                        <td colspan="4" class="text-end text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.06em; color: var(--color-text-muted);">
                                            Totales <?= $filtroCampana ? 'de la campaña' : 'mostrados' ?>
                                        </td>
                                        <td class="text-end" style="color: var(--color-text);">
                                            <?= number_format($stats['total_kilos'], 2, ',', '.') ?> kg
                                        </td>
                                        <td></td>
                                        <td class="text-end" style="color: var(--color-primary);">
                                            <?= number_format($stats['total_litros'], 2, ',', '.') ?> L
                                        </td>
                                        <td class="text-end" style="color: var(--color-accent-dark);">
                                            <?= number_format($totalLiquidacion, 2, ',', '.') ?> €
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; /* fin del bloque "no estoy filtrando un cliente" */ ?>


        <?php if ($filtroCliente): ?>
             VISTA: PEDIDOS DEL CLIENTE FILTRADO
        <div class="section-divider">
            <span><i class="bi bi-bag-check" aria-hidden="true"></i> Pedidos del cliente</span>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <div class="header-left">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <h2>Histórico de pedidos</h2>
                </div>
                <span class="badge-count">
                    <?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <div class="table-responsive">
                <?php if (empty($pedidos)): ?>
                    <div class="table-empty">
                        <i class="bi bi-bag-x" aria-hidden="true"></i>
                        <p>Este cliente todavía no ha realizado pedidos.</p>
                    </div>
                <?php else: ?>
                    <table class="table table-hover table-admin mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Productos</th>
                                <th class="text-center">Unidades</th>
                                <th class="text-end">Total</th>
                                <th>Método de pago</th>
                                <th class="text-center">Factura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $i => $p): ?>
                                <?php
                                    $estadoColors = [
                                        'pendiente'  => 'secondary',
                                        'pagado'     => 'success',
                                        'procesando' => 'info',
                                        'enviado'    => 'primary',
                                        'entregado'  => 'success',
                                        'cancelado'  => 'danger',
                                    ];
                                    $colorEstado = $estadoColors[$p['estado']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td>
                                        <i class="bi bi-calendar3 text-muted" style="font-size:0.75rem;" aria-hidden="true"></i>
                                        <?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $colorEstado ?>"><?= htmlspecialchars($p['estado']) ?></span>
                                    </td>
                                    <td class="text-center"><?= (int) $p['num_lineas'] ?></td>
                                    <td class="text-center"><?= (int) $p['total_unidades'] ?></td>
                                    <td class="text-end fw-semibold" style="color: var(--color-accent-dark);">
                                        <?= number_format((float) $p['total'], 2, ',', '.') ?> €
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($p['metodo_pago'] ?? '—') ?></td>
                                    <td class="text-center">
                                        <a href="../core/generar_factura.php?id_pedido=<?= (int) $p['id'] ?>"
                                           target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Abrir factura PDF en pestaña nueva">
                                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Margen inferior -->
        <div style="height: 3rem;"></div>

    </main>


    <!-- ─── Modal de anulación de entrega ─── -->
    <div class="modal fade" id="modalAnularEntrega" tabindex="-1" aria-labelledby="modalAnularLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="background: linear-gradient(135deg, #8B5A2B, #6B4220); color: #fff;">
            <h5 class="modal-title" id="modalAnularLabel">
              <i class="bi bi-x-octagon" aria-hidden="true"></i> Anular entrega
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert" style="font-size:0.9rem;">
              <i class="bi bi-info-circle-fill" aria-hidden="true" style="font-size:1.2rem;"></i>
              <div>
                La entrega <strong id="anular-codigo">—</strong> de <strong id="anular-socio">—</strong>
                (<span id="anular-kilos">—</span> kg) quedará marcada como <strong>anulada</strong>:
                no contará en KPIs ni liquidaciones, pero permanece en el histórico.
                Se notificará al socio por email.
              </div>
            </div>
            <label for="motivo-anulacion" class="form-label fw-semibold">
              Motivo de la anulación <span class="text-danger">*</span>
            </label>
            <textarea class="form-control" id="motivo-anulacion" rows="3"
                      placeholder="Ej. Error en kilos: se introdujeron 850 en lugar de 580."
                      maxlength="255" required></textarea>
            <div class="form-text">Mínimo 5 caracteres. El socio verá este motivo en su email.</div>
            <div id="anular-feedback" class="mt-2"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btn-confirmar-anular">
              <i class="bi bi-x-octagon" aria-hidden="true"></i> Anular entrega
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Estilos: fila tachada cuando entrega anulada -->
    <style>
        .table-admin tbody tr.fila-anulada {
            opacity: 0.55;
            background: #fdf3f0 !important;
        }
        .table-admin tbody tr.fila-anulada td {
            text-decoration: line-through;
            text-decoration-color: rgba(192, 57, 43, 0.55);
        }
        /* La columna de la etiqueta "ANULADA" no se tacha (se vería raro). */
        .table-admin tbody tr.fila-anulada td:last-child {
            text-decoration: none;
        }
    </style>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <!-- Chart.js: el dashboard usa `new Chart(...)`. Sin este script los canvas
         se quedaban en blanco con el spinner de "Cargando…" indefinidamente. -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    /**
     * ========================================================================
     * DASHBOARD INTERACTIVO — Fetch + Chart.js
     * ========================================================================
     * Flujo de datos:
     *   1. El navegador hace una petición GET asíncrona a api_estadisticas.php
     *   2. La API consulta la BD y devuelve un JSON con toda la información
     *   3. Con ese JSON, actualizamos tarjetas de resumen y dibujamos gráficos
     *
     * Ventaja del enfoque asíncrono (Fetch):
     *   - La página se carga instantáneamente (HTML renderizado por PHP)
     *   - Los gráficos se cargan en segundo plano sin bloquear la interfaz
     *   - Se puede refrescar los datos sin recargar toda la página
     * ========================================================================
     */
    document.addEventListener('DOMContentLoaded', () => {

        // ─── PALETA DE COLORES CORPORATIVA (tonos verdes y dorados) ───
        const COLORES = {
            verde:        'rgba(44, 76, 59, 0.85)',
            verdeClaro:   'rgba(45, 139, 78, 0.75)',
            dorado:       'rgba(212, 175, 55, 0.85)',
            doradoClaro:  'rgba(228, 198, 90, 0.75)',
            bronce:       'rgba(184, 150, 46, 0.80)',
            azul:         'rgba(41, 128, 185, 0.75)',
            // Bordes (más opacos)
            bordVerde:    'rgba(44, 76, 59, 1)',
            bordDorado:   'rgba(212, 175, 55, 1)',
            bordVerdeCl:  'rgba(45, 139, 78, 1)',
            bordDoradoCl: 'rgba(228, 198, 90, 1)',
            bordBronce:   'rgba(184, 150, 46, 1)',
            bordAzul:     'rgba(41, 128, 185, 1)',
        };

        // Colores para el gráfico Doughnut (uno por variedad)
        const PALETTE_BG = [
            COLORES.verde,
            COLORES.dorado,
            COLORES.verdeClaro,
            COLORES.doradoClaro,
            COLORES.bronce,
            COLORES.azul
        ];
        const PALETTE_BORDER = [
            COLORES.bordVerde,
            COLORES.bordDorado,
            COLORES.bordVerdeCl,
            COLORES.bordDoradoCl,
            COLORES.bordBronce,
            COLORES.bordAzul
        ];


        // PETICIÓN FETCH A LA API DE ESTADÍSTICAS
        // fetch() devuelve una Promise. Encadenamos .then() para:
        //   1. Convertir la respuesta HTTP en un objeto JSON
        //   2. Procesar los datos y dibujar los gráficos
        // Si algo falla, .catch() maneja el error mostrando un mensaje.
        fetch('../core/api_estadisticas.php')
            .then(response => {
                // Verificar que la respuesta HTTP fue exitosa (200-299)
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                // Parsear el cuerpo de la respuesta como JSON
                return response.json();
            })
            .then(data => {
                // ─── Verificar que la API no devolvió un error lógico ───
                if (data.error) {
                    throw new Error(data.mensaje || 'Error desconocido de la API');
                }

                // ─── Actualizar las tarjetas de resumen financiero ───
                actualizarTarjetas(data.resumen);

                // ─── Dibujar gráfico Doughnut (Ventas por Variedad) ───
                dibujarGraficoVentas(data.ventas_variedad);

                // ─── Dibujar gráfico de Barras (Kilos por Mes) ───
                dibujarGraficoEntregas(data.kilos_por_mes);
            })
            .catch(error => {
                // ─── Manejo de errores (red, API, parsing…) ───
                console.error('Error al cargar estadísticas:', error);
                mostrarErrorGrafico('loading-ventas', 'graficoVentas');
                mostrarErrorGrafico('loading-entregas', 'graficoEntregas');
            });


        // FUNCIÓN: Actualizar tarjetas de resumen
        function actualizarTarjetas(resumen) {
            const elIngresos = document.getElementById('stat-ingresos');
            const elPedidos  = document.getElementById('stat-pedidos');

            if (elIngresos) {
                // Formatear como moneda española: 1.234,50€
                const monto = new Intl.NumberFormat('es-ES', {
                    style: 'currency',
                    currency: 'EUR',
                    minimumFractionDigits: 2
                }).format(resumen.total_facturado);
                elIngresos.innerHTML = monto;
            }

            if (elPedidos) {
                elPedidos.innerHTML = resumen.total_pedidos.toLocaleString('es-ES');
            }
        }


        // FUNCIÓN: Dibujar gráfico Doughnut (Ventas por Variedad)
        // Muestra el porcentaje de unidades vendidas de cada variedad
        // de aceite (Picual, Arbequina, Hojiblanca, Coupage).
        function dibujarGraficoVentas(ventasVariedad) {
            // Ocultar spinner y mostrar canvas
            document.getElementById('loading-ventas').style.display = 'none';
            const canvas = document.getElementById('graficoVentas');
            canvas.style.display = 'block';

            // Si no hay datos, mostrar mensaje
            if (!ventasVariedad || ventasVariedad.length === 0) {
                canvas.parentElement.innerHTML = `
                    <div class="chart-error">
                        <i class="bi bi-pie-chart" aria-hidden="true"></i>
                        <p>No hay ventas registradas todavía.</p>
                    </div>`;
                return;
            }

            // Extraer etiquetas y datos del JSON recibido
            const etiquetas = ventasVariedad.map(v => v.variedad);
            const datos     = ventasVariedad.map(v => parseInt(v.total_vendido));

            // Crear el gráfico con Chart.js
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: etiquetas,
                    datasets: [{
                        label: 'Unidades vendidas',
                        data: datos,
                        backgroundColor: PALETTE_BG.slice(0, etiquetas.length),
                        borderColor: PALETTE_BORDER.slice(0, etiquetas.length),
                        borderWidth: 2,
                        hoverBorderWidth: 3,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '55%',   // Grosor del donut
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyleWidth: 12,
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12,
                                    weight: 500
                                },
                                color: '#4A4A4A'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 53, 41, 0.95)',
                            titleFont: { family: "'Inter', sans-serif", weight: 600 },
                            bodyFont:  { family: "'Inter', sans-serif" },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                // Mostrar porcentaje en el tooltip
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = ((context.parsed / total) * 100).toFixed(1);
                                    return ` ${context.label}: ${context.parsed} uds (${pct}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }


        // FUNCIÓN: Dibujar gráfico de Barras (Kilos por Mes)
        // Muestra la evolución temporal de kilos de aceituna recibidos
        // en la almazara, agrupados por mes.
        function dibujarGraficoEntregas(kilosPorMes) {
            // Ocultar spinner y mostrar canvas
            document.getElementById('loading-entregas').style.display = 'none';
            const canvas = document.getElementById('graficoEntregas');
            canvas.style.display = 'block';

            // Si no hay datos, mostrar mensaje
            if (!kilosPorMes || kilosPorMes.length === 0) {
                canvas.parentElement.innerHTML = `
                    <div class="chart-error">
                        <i class="bi bi-bar-chart" aria-hidden="true"></i>
                        <p>No hay entregas registradas todavía.</p>
                    </div>`;
                return;
            }

            // Extraer etiquetas (mes/año) y datos (kilos) del JSON
            const etiquetas = kilosPorMes.map(k => k.etiqueta);
            const datos     = kilosPorMes.map(k => parseFloat(k.total_kilos));

            // Crear gradiente de fondo para las barras
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(44, 76, 59, 0.85)');
            gradient.addColorStop(1, 'rgba(44, 76, 59, 0.35)');

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: etiquetas,
                    datasets: [{
                        label: 'Kilos de aceituna',
                        data: datos,
                        backgroundColor: gradient,
                        borderColor: COLORES.bordVerde,
                        borderWidth: 2,
                        borderRadius: 6,        // Bordes redondeados en las barras
                        borderSkipped: false,
                        hoverBackgroundColor: COLORES.dorado,
                        hoverBorderColor: COLORES.bordDorado
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false  // Ocultar leyenda (solo un dataset)
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 53, 41, 0.95)',
                            titleFont: { family: "'Inter', sans-serif", weight: 600 },
                            bodyFont:  { family: "'Inter', sans-serif" },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const kilos = context.parsed.y.toLocaleString('es-ES');
                                    return ` ${kilos} kg de aceituna`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(44, 76, 59, 0.06)',
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11
                                },
                                color: '#7A7A7A',
                                callback: function(value) {
                                    return value.toLocaleString('es-ES') + ' kg';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 11,
                                    weight: 500
                                },
                                color: '#4A4A4A'
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }


        // FUNCIÓN: Mostrar error en un gráfico
        function mostrarErrorGrafico(loadingId, canvasId) {
            const loading = document.getElementById(loadingId);
            if (loading) {
                loading.innerHTML = `
                    <div class="chart-error">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <p>No se pudieron cargar los datos.</p>
                    </div>`;
            }
        }


        // VALIDACIÓN DEL FORMULARIO (lado cliente)
        // La validación real ocurre en el servidor (PHP).
        const form = document.getElementById('form-nueva-entrega');

        if (form) {
            form.addEventListener('submit', (e) => {
                const socio = document.getElementById('select-socio');
                const kilos = document.getElementById('input-kilos');
                const rend  = document.getElementById('input-rendimiento');

                let valid = true;

                // Validar que se seleccionó un socio
                if (!socio.value) {
                    socio.style.borderColor = '#C0392B';
                    valid = false;
                } else {
                    socio.style.borderColor = '';
                }

                // Validar kilos > 0
                if (!kilos.value || parseFloat(kilos.value) <= 0) {
                    kilos.style.borderColor = '#C0392B';
                    valid = false;
                } else {
                    kilos.style.borderColor = '';
                }

                // Validar rendimiento entre 0 y 100
                if (!rend.value || parseFloat(rend.value) <= 0 || parseFloat(rend.value) > 100) {
                    rend.style.borderColor = '#C0392B';
                    valid = false;
                } else {
                    rend.style.borderColor = '';
                }

                if (!valid) {
                    e.preventDefault();
                }
            });
        }


        // ── Anulación de entregas (soft-delete) ─────────────────────────────
        const modalEl       = document.getElementById('modalAnularEntrega');
        const btnConfirmar  = document.getElementById('btn-confirmar-anular');
        const txtMotivo     = document.getElementById('motivo-anulacion');
        const fbAnular      = document.getElementById('anular-feedback');
        let entregaSeleccionada = null;
        const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';

        if (modalEl && typeof bootstrap !== 'undefined') {
            const modalAnular = new bootstrap.Modal(modalEl);

            // Abrir modal al pulsar cualquier botón "Anular" de la tabla
            document.querySelectorAll('.btn-anular-entrega').forEach(btn => {
                btn.addEventListener('click', () => {
                    entregaSeleccionada = btn.dataset.idEntrega;
                    document.getElementById('anular-codigo').textContent = btn.dataset.codigo || '—';
                    document.getElementById('anular-socio').textContent  = btn.dataset.socio  || '—';
                    document.getElementById('anular-kilos').textContent  = btn.dataset.kilos  || '—';
                    txtMotivo.value = '';
                    fbAnular.innerHTML = '';
                    modalAnular.show();
                });
            });

            // Confirmar anulación
            btnConfirmar.addEventListener('click', async () => {
                const motivo = txtMotivo.value.trim();
                if (motivo.length < 5) {
                    fbAnular.innerHTML = '<div class="alert alert-danger py-2 mb-0">El motivo es obligatorio (mínimo 5 caracteres).</div>';
                    return;
                }

                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Anulando…';

                try {
                    const r = await fetch('../api/anular_entrega.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            csrf_token: csrfToken,
                            id_entrega: parseInt(entregaSeleccionada, 10),
                            motivo: motivo,
                        }),
                    });
                    const data = await r.json();
                    if (!r.ok || data.error) {
                        fbAnular.innerHTML = '<div class="alert alert-danger py-2 mb-0">' +
                            (data.mensaje || 'Error al anular.') + '</div>';
                    } else {
                        fbAnular.innerHTML = '<div class="alert alert-success py-2 mb-0">' +
                            (data.mensaje || 'Anulada.') + ' Recargando…</div>';
                        setTimeout(() => location.reload(), 800);
                    }
                } catch (err) {
                    fbAnular.innerHTML = '<div class="alert alert-danger py-2 mb-0">Error de red: ' + err.message + '</div>';
                } finally {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="bi bi-x-octagon"></i> Anular entrega';
                }
            });
        }
    });
    </script>

</body>
</html>
