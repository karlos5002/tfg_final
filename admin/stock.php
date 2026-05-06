<?php
// Gestión de stock: alta de reposiciones, ajustes manuales y umbral por producto.
// Cada operación inserta un registro en movimientos_stock dentro de una
// transacción para mantener stock + log siempre consistentes.

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

// ─── POST: registrar reposición / ajuste / cambio de umbral ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido. Recarga la página.';
    } else {
        $accion      = $_POST['accion']      ?? '';
        $idProducto  = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
        $idUsuario   = (int) ($_SESSION['usuario_id'] ?? 0);

        try {
            $pdo = getConexion();

            if (!$idProducto) {
                throw new InvalidArgumentException('Producto no válido.');
            }

            switch ($accion) {

                case 'reponer':
                    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
                    $motivo   = trim($_POST['motivo'] ?? '');
                    if (!$cantidad || $cantidad <= 0) {
                        throw new InvalidArgumentException('La cantidad de reposición debe ser positiva.');
                    }
                    if ($motivo === '') {
                        $motivo = 'Reposición manual';
                    }

                    $pdo->beginTransaction();

                    // Bloqueamos la fila para evitar carrera si una venta entra a la vez.
                    $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = :id FOR UPDATE');
                    $stmt->execute([':id' => $idProducto]);
                    $stockAntes = $stmt->fetchColumn();
                    if ($stockAntes === false) {
                        throw new RuntimeException('Producto no encontrado.');
                    }
                    $stockAntes = (int) $stockAntes;
                    $stockDespues = $stockAntes + $cantidad;

                    $pdo->prepare('UPDATE productos SET stock = :s WHERE id = :id')
                        ->execute([':s' => $stockDespues, ':id' => $idProducto]);

                    $pdo->prepare('
                        INSERT INTO movimientos_stock
                            (id_producto, tipo, cantidad, stock_antes, stock_despues, motivo, id_usuario)
                        VALUES (:id, "entrada", :cantidad, :sa, :sd, :motivo, :user)
                    ')->execute([
                        ':id' => $idProducto, ':cantidad' => $cantidad,
                        ':sa' => $stockAntes, ':sd' => $stockDespues,
                        ':motivo' => mb_substr($motivo, 0, 180), ':user' => $idUsuario ?: null,
                    ]);

                    $pdo->commit();
                    $mensaje_exito = "Reposición registrada: +{$cantidad} unidades. Stock actual: {$stockDespues}.";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'ajustar':
                    // Ajuste manual: el admin indica el stock REAL contado en almacén
                    // y el sistema calcula la diferencia (puede ser positiva o negativa).
                    $stockReal = filter_input(INPUT_POST, 'stock_real', FILTER_VALIDATE_INT);
                    $motivo    = trim($_POST['motivo'] ?? '');
                    if ($stockReal === false || $stockReal < 0) {
                        throw new InvalidArgumentException('El stock real debe ser un entero ≥ 0.');
                    }
                    if ($motivo === '') {
                        throw new InvalidArgumentException('Indica un motivo del ajuste (ej. "Inventario trimestral", "Mermas").');
                    }

                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = :id FOR UPDATE');
                    $stmt->execute([':id' => $idProducto]);
                    $stockAntes = $stmt->fetchColumn();
                    if ($stockAntes === false) {
                        throw new RuntimeException('Producto no encontrado.');
                    }
                    $stockAntes  = (int) $stockAntes;
                    $diferencia  = $stockReal - $stockAntes;

                    if ($diferencia === 0) {
                        $pdo->rollBack();
                        $mensaje_exito = 'No hace falta ajuste: el stock real coincide con el del sistema.';
                        break;
                    }

                    $pdo->prepare('UPDATE productos SET stock = :s WHERE id = :id')
                        ->execute([':s' => $stockReal, ':id' => $idProducto]);

                    $pdo->prepare('
                        INSERT INTO movimientos_stock
                            (id_producto, tipo, cantidad, stock_antes, stock_despues, motivo, id_usuario)
                        VALUES (:id, "ajuste", :cantidad, :sa, :sd, :motivo, :user)
                    ')->execute([
                        ':id' => $idProducto, ':cantidad' => $diferencia,
                        ':sa' => $stockAntes, ':sd' => $stockReal,
                        ':motivo' => mb_substr($motivo, 0, 180), ':user' => $idUsuario ?: null,
                    ]);

                    $pdo->commit();
                    $signo = $diferencia > 0 ? '+' : '';
                    $mensaje_exito = "Ajuste aplicado ({$signo}{$diferencia} uds). Stock actual: {$stockReal}.";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'umbral':
                    $nuevoUmbral = filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT);
                    if ($nuevoUmbral === false || $nuevoUmbral < 0) {
                        throw new InvalidArgumentException('El umbral debe ser un entero ≥ 0.');
                    }
                    $pdo->prepare('UPDATE productos SET stock_minimo = :u WHERE id = :id')
                        ->execute([':u' => $nuevoUmbral, ':id' => $idProducto]);
                    $mensaje_exito = "Umbral de aviso actualizado a {$nuevoUmbral} unidades.";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;

                default:
                    throw new InvalidArgumentException('Acción desconocida.');
            }

        } catch (InvalidArgumentException | RuntimeException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('admin_stock: ' . $e->getMessage());
            $mensaje_error = 'Error al guardar en la base de datos.';
        }
    }
}

// ─── Carga de datos ──────────────────────────────────────────────────────
try {
    $pdo = getConexion();

    // Productos con su estado calculado (los más críticos primero)
    $productos = $pdo->query('
        SELECT id, nombre, slug, variedad, precio, stock, stock_minimo, activo, estado_stock, ultima_reposicion
        FROM v_stock_estado
        ORDER BY FIELD(estado_stock, "agotado","bajo","medio","ok"), nombre
    ')->fetchAll();

    // KPIs rápidos
    $stats = $pdo->query('
        SELECT
            SUM(stock = 0)                                              AS agotados,
            SUM(stock > 0 AND stock <= stock_minimo)                    AS bajos,
            SUM(stock > stock_minimo AND stock <= stock_minimo * 2)     AS medios,
            SUM(stock > stock_minimo * 2)                               AS sanos,
            SUM(stock)                                                  AS total_unidades
        FROM productos WHERE activo = 1
    ')->fetch();

    // Histórico de movimientos (últimos 50)
    $movimientos = $pdo->query('
        SELECT m.id, m.tipo, m.cantidad, m.stock_antes, m.stock_despues,
               m.motivo, m.id_pedido, m.created_at,
               p.nombre AS producto, p.id AS id_producto,
               u.nombre AS usuario_nombre
        FROM movimientos_stock m
        INNER JOIN productos p ON p.id = m.id_producto
        LEFT  JOIN usuarios  u ON u.id = m.id_usuario
        ORDER BY m.created_at DESC
        LIMIT 50
    ')->fetchAll();

} catch (PDOException $e) {
    error_log('admin_stock GET: ' . $e->getMessage());
    $productos     = [];
    $movimientos   = [];
    $stats         = ['agotados' => 0, 'bajos' => 0, 'medios' => 0, 'sanos' => 0, 'total_unidades' => 0];
    $mensaje_error = $mensaje_error ?: 'Error al cargar los datos.';
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Gestión de Stock | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-boxes" style="color: var(--color-accent-dark);"></i>
                Gestión de <em>Stock</em>
            </h1>
            <p>Reposiciones, ajustes de inventario y umbrales de aviso por producto.</p>
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
                <div class="stat-icon" style="background:#FCE6E4; color:#C0392B;"><i class="bi bi-x-octagon-fill"></i></div>
                <div class="stat-label">Agotados</div>
                <div class="stat-value"><?= (int) $stats['agotados'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="stat-label">Stock bajo</div>
                <div class="stat-value"><?= (int) $stats['bajos'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-label">Stock sano</div>
                <div class="stat-value"><?= (int) ($stats['sanos'] + $stats['medios']) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-boxes"></i></div>
                <div class="stat-label">Unidades totales</div>
                <div class="stat-value"><?= number_format((int) $stats['total_unidades'], 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Tabla de productos -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <div class="header-left"><i class="bi bi-table"></i><h2>Inventario actual</h2></div>
            <span class="badge-count"><?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?></span>
        </div>
        <div class="table-responsive">
            <?php if (empty($productos)): ?>
                <div class="table-empty"><i class="bi bi-inbox"></i><p>No hay productos.</p></div>
            <?php else: ?>
                <table class="table table-hover table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Variedad</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Umbral</th>
                            <th>Última reposición</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p):
                            $colores = [
                                'agotado' => 'danger',
                                'bajo'    => 'warning',
                                'medio'   => 'info',
                                'ok'      => 'success',
                            ];
                            $etiquetas = [
                                'agotado' => 'Agotado',
                                'bajo'    => 'Stock bajo',
                                'medio'   => 'Aceptable',
                                'ok'      => 'Sano',
                            ];
                        ?>
                            <tr<?= $p['estado_stock'] === 'agotado' ? ' style="background:rgba(192,57,43,0.04);"' : '' ?>>
                                <td>
                                    <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                                    <?php if (!$p['activo']): ?>
                                        <br><small class="text-muted"><i class="bi bi-eye-slash"></i> oculto en tienda</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['variedad']) ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $colores[$p['estado_stock']] ?? 'secondary' ?>">
                                        <?= $etiquetas[$p['estado_stock']] ?? $p['estado_stock'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold" style="font-size:1.05rem;">
                                    <?= (int) $p['stock'] ?>
                                </td>
                                <td class="text-end text-muted small">
                                    <?= (int) $p['stock_minimo'] ?>
                                </td>
                                <td class="small text-muted">
                                    <?php if ($p['ultima_reposicion']): ?>
                                        <i class="bi bi-clock-history"></i>
                                        <?= date('d/m/Y H:i', strtotime($p['ultima_reposicion'])) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-success btn-reponer"
                                            data-id="<?= (int) $p['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-stock="<?= (int) $p['stock'] ?>"
                                            title="Registrar reposición">
                                            <i class="bi bi-plus-lg"></i> Reponer
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-ajustar"
                                            data-id="<?= (int) $p['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-stock="<?= (int) $p['stock'] ?>"
                                            title="Ajuste manual de inventario">
                                            <i class="bi bi-sliders"></i> Ajustar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-umbral"
                                            data-id="<?= (int) $p['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-umbral="<?= (int) $p['stock_minimo'] ?>"
                                            title="Cambiar umbral de aviso">
                                            <i class="bi bi-bell"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial de movimientos -->
    <div class="table-card mb-4">
        <div class="table-card-header">
            <div class="header-left"><i class="bi bi-list-ul"></i><h2>Últimos movimientos</h2></div>
            <span class="badge-count"><?= count($movimientos) ?></span>
        </div>
        <div class="table-responsive">
            <?php if (empty($movimientos)): ?>
                <div class="table-empty"><i class="bi bi-clock-history"></i><p>Sin movimientos registrados.</p></div>
            <?php else: ?>
                <table class="table table-hover table-admin mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-end">Δ</th>
                            <th class="text-end">Antes</th>
                            <th class="text-end">Después</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $m):
                            $tipoColor = [
                                'entrada' => 'success',
                                'salida'  => 'primary',
                                'ajuste'  => 'warning',
                            ];
                            $signo = $m['cantidad'] > 0 ? '+' : '';
                        ?>
                            <tr>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                <td><?= htmlspecialchars($m['producto']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $tipoColor[$m['tipo']] ?? 'secondary' ?>">
                                        <?= htmlspecialchars($m['tipo']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold"
                                    style="color: <?= $m['cantidad'] > 0 ? '#2D8B4E' : '#C0392B' ?>;">
                                    <?= $signo ?><?= (int) $m['cantidad'] ?>
                                </td>
                                <td class="text-end text-muted small"><?= (int) $m['stock_antes'] ?></td>
                                <td class="text-end fw-semibold"><?= (int) $m['stock_despues'] ?></td>
                                <td class="small">
                                    <?= htmlspecialchars($m['motivo'] ?? '—') ?>
                                    <?php if ($m['id_pedido']): ?>
                                        <a href="../core/generar_factura.php?id_pedido=<?= (int) $m['id_pedido'] ?>"
                                           target="_blank" rel="noopener"
                                           class="text-decoration-none small ms-1"
                                           title="Ver factura del pedido">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $m['usuario_nombre'] ? htmlspecialchars($m['usuario_nombre']) : '<em>auto</em>' ?>
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


<!-- ── Modal: Reponer ───────────────────────────────────────────────── -->
<div class="modal fade" id="modalReponer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" value="reponer">
      <input type="hidden" name="id_producto" id="rep-id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-lg text-success"></i> Reponer stock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3"><strong id="rep-nombre">—</strong>
            <small class="text-muted d-block">Stock actual: <span id="rep-actual">0</span> unidades</small></p>
        <div class="mb-3">
            <label class="admin-label" for="rep-cantidad">Unidades a añadir</label>
            <input type="number" class="admin-input" id="rep-cantidad" name="cantidad" min="1" step="1" required>
        </div>
        <div class="mb-2">
            <label class="admin-label" for="rep-motivo">Motivo / Referencia</label>
            <input type="text" class="admin-input" id="rep-motivo" name="motivo" maxlength="180"
                   placeholder="Ej: Albarán proveedor #4521 — embotellado abril">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Registrar reposición</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal: Ajustar ────────────────────────────────────────────────── -->
<div class="modal fade" id="modalAjustar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" value="ajustar">
      <input type="hidden" name="id_producto" id="aj-id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-sliders"></i> Ajuste manual de inventario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3"><strong id="aj-nombre">—</strong>
            <small class="text-muted d-block">Stock según sistema: <span id="aj-actual">0</span> unidades</small></p>
        <div class="mb-3">
            <label class="admin-label" for="aj-real">Stock real contado en almacén</label>
            <input type="number" class="admin-input" id="aj-real" name="stock_real" min="0" step="1" required>
            <small class="text-muted">El sistema calculará la diferencia y dejará constancia en el log.</small>
        </div>
        <div class="mb-2">
            <label class="admin-label" for="aj-motivo">Motivo del ajuste</label>
            <input type="text" class="admin-input" id="aj-motivo" name="motivo" maxlength="180" required
                   placeholder="Ej: Inventario trimestral, mermas por rotura">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg"></i> Aplicar ajuste</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal: Umbral ────────────────────────────────────────────────── -->
<div class="modal fade" id="modalUmbral" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" value="umbral">
      <input type="hidden" name="id_producto" id="um-id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-bell"></i> Umbral de aviso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3"><strong id="um-nombre">—</strong></p>
        <div class="mb-2">
            <label class="admin-label" for="um-valor">Avisar cuando el stock baje de</label>
            <input type="number" class="admin-input" id="um-valor" name="stock_minimo" min="0" step="1" required>
            <small class="text-muted">En la tienda aparecerá "¡Sólo X unidades!" cuando el stock sea ≤ este valor.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar umbral</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
    const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

    // Reponer
    document.querySelectorAll('.btn-reponer').forEach(btn => {
        btn.addEventListener('click', () => {
            setVal('rep-id', btn.dataset.id);
            setTxt('rep-nombre', btn.dataset.nombre);
            setTxt('rep-actual', btn.dataset.stock);
            setVal('rep-cantidad', '');
            setVal('rep-motivo', '');
            new bootstrap.Modal(document.getElementById('modalReponer')).show();
        });
    });

    // Ajustar
    document.querySelectorAll('.btn-ajustar').forEach(btn => {
        btn.addEventListener('click', () => {
            setVal('aj-id', btn.dataset.id);
            setTxt('aj-nombre', btn.dataset.nombre);
            setTxt('aj-actual', btn.dataset.stock);
            setVal('aj-real', btn.dataset.stock);
            setVal('aj-motivo', '');
            new bootstrap.Modal(document.getElementById('modalAjustar')).show();
        });
    });

    // Umbral
    document.querySelectorAll('.btn-umbral').forEach(btn => {
        btn.addEventListener('click', () => {
            setVal('um-id', btn.dataset.id);
            setTxt('um-nombre', btn.dataset.nombre);
            setVal('um-valor', btn.dataset.umbral);
            new bootstrap.Modal(document.getElementById('modalUmbral')).show();
        });
    });
})();
</script>
</body>
</html>
