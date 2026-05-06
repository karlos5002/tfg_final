<?php
// CRUD del calendario agrícola: tareas recomendadas por mes con consejo y prioridad.
// Las tareas inactivas no aparecen en la vista pública, sólo en este panel.

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const MESES = [
    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
    7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
];

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        try {
            $pdo = getConexion();

            switch ($accion) {

                case 'crear':
                case 'editar':
                    $idTarea     = $accion === 'editar'
                        ? filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT)
                        : null;
                    $mes         = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
                    $titulo      = trim($_POST['titulo'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '');
                    $tip         = trim($_POST['tip'] ?? '');
                    $icono       = trim($_POST['icono'] ?? 'bi-tree');
                    $prioridad   = $_POST['prioridad'] ?? 'media';
                    $orden       = filter_input(INPUT_POST, 'orden', FILTER_VALIDATE_INT) ?: 1;

                    if (!$mes || $mes < 1 || $mes > 12) throw new InvalidArgumentException('Mes no válido (1-12).');
                    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
                        throw new InvalidArgumentException('El título debe tener entre 3 y 120 caracteres.');
                    }
                    if ($descripcion === '') throw new InvalidArgumentException('La descripción es obligatoria.');
                    if (!preg_match('/^bi-[a-z0-9-]+$/', $icono)) {
                        throw new InvalidArgumentException('El icono debe ser un identificador de Bootstrap Icons (ej: bi-tree).');
                    }
                    if (!in_array($prioridad, ['alta','media','baja'], true)) {
                        throw new InvalidArgumentException('Prioridad no válida.');
                    }
                    if ($orden < 1 || $orden > 99) throw new InvalidArgumentException('El orden debe estar entre 1 y 99.');

                    if ($accion === 'crear') {
                        $pdo->prepare('
                            INSERT INTO calendario_tareas (mes, titulo, descripcion, tip, icono, prioridad, orden)
                            VALUES (:m, :t, :d, :tip, :i, :p, :o)
                        ')->execute([
                            ':m' => $mes, ':t' => $titulo, ':d' => $descripcion,
                            ':tip' => $tip !== '' ? $tip : null,
                            ':i' => $icono, ':p' => $prioridad, ':o' => $orden,
                        ]);
                        $mensaje_exito = 'Tarea creada en ' . MESES[$mes] . '.';
                    } else {
                        if (!$idTarea) throw new InvalidArgumentException('ID inválido.');
                        $pdo->prepare('
                            UPDATE calendario_tareas
                            SET mes = :m, titulo = :t, descripcion = :d, tip = :tip,
                                icono = :i, prioridad = :p, orden = :o
                            WHERE id = :id
                        ')->execute([
                            ':m' => $mes, ':t' => $titulo, ':d' => $descripcion,
                            ':tip' => $tip !== '' ? $tip : null,
                            ':i' => $icono, ':p' => $prioridad, ':o' => $orden,
                            ':id' => $idTarea,
                        ]);
                        $mensaje_exito = 'Tarea actualizada.';
                    }
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'archivar':
                case 'restaurar':
                    $idTarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
                    if (!$idTarea) throw new InvalidArgumentException('ID inválido.');
                    $nuevo = $accion === 'archivar' ? 0 : 1;
                    $pdo->prepare('UPDATE calendario_tareas SET activo = :a WHERE id = :id')
                        ->execute([':a' => $nuevo, ':id' => $idTarea]);
                    $mensaje_exito = $accion === 'archivar' ? 'Tarea ocultada.' : 'Tarea visible de nuevo.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'borrar':
                    $idTarea = filter_input(INPUT_POST, 'id_tarea', FILTER_VALIDATE_INT);
                    if (!$idTarea) throw new InvalidArgumentException('ID inválido.');
                    $pdo->prepare('DELETE FROM calendario_tareas WHERE id = :id')->execute([':id' => $idTarea]);
                    $mensaje_exito = 'Tarea eliminada.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;

                default:
                    throw new InvalidArgumentException('Acción no reconocida.');
            }

        } catch (InvalidArgumentException $e) {
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('admin_calendario: ' . $e->getMessage());
            $mensaje_error = 'Error al guardar en la base de datos.';
        }
    }
}

// ─── Carga: tareas agrupadas por mes (todas, también inactivas) ──────────
try {
    $pdo = getConexion();
    $todas = $pdo->query('
        SELECT * FROM calendario_tareas
        ORDER BY mes ASC, orden ASC, id ASC
    ')->fetchAll();
    $porMes = array_fill_keys(range(1, 12), []);
    foreach ($todas as $t) $porMes[(int) $t['mes']][] = $t;
} catch (PDOException $e) {
    error_log('admin_calendario GET: ' . $e->getMessage());
    $todas  = [];
    $porMes = array_fill_keys(range(1, 12), []);
    $mensaje_error = $mensaje_error ?: 'Error al cargar las tareas.';
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Calendario Agrícola | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<style>
    .mes-card { background: #fff; border: 1px solid rgba(0,0,0,0.06); border-radius: 12px;
                padding: 1rem; box-shadow: 0 2px 6px rgba(0,0,0,0.03); height: 100%; }
    .mes-card h3 { font-size: 1.05rem; margin: 0 0 .75rem 0; color: var(--color-primary-dark);
                   display:flex; align-items:center; gap:.4rem; padding-bottom:.5rem;
                   border-bottom: 2px solid rgba(212,175,55,0.4); }
    .tarea-row { display:flex; align-items:flex-start; gap:.5rem; padding:.5rem 0;
                 border-bottom: 1px dashed rgba(0,0,0,0.06); font-size:.85rem; }
    .tarea-row:last-child { border-bottom: 0; }
    .tarea-row.inactiva { opacity:.5; }
    .tarea-icon { font-size:1rem; color: var(--color-primary); flex-shrink:0; margin-top:2px; }
    .tarea-titulo { font-weight: 600; }
    .tarea-prio-alta  { border-left: 3px solid #C0392B; padding-left:.5rem; }
    .tarea-prio-media { border-left: 3px solid #E67E22; padding-left:.5rem; }
    .tarea-prio-baja  { border-left: 3px solid #5A7A5F; padding-left:.5rem; }
    .tarea-acciones { margin-left:auto; display:flex; gap:.2rem; flex-shrink:0; }
    .tarea-acciones .btn { padding: .15rem .35rem; font-size: .7rem; }
    .btn-add-mes { width:100%; padding:.4rem; border:1px dashed rgba(0,0,0,0.15);
                   background: #fafafa; color: var(--color-text-muted); border-radius: 6px;
                   cursor:pointer; font-size:.8rem; transition: all .2s; }
    .btn-add-mes:hover { background: rgba(212,175,55,0.08); color: var(--color-accent-dark); border-color: var(--color-accent); }
</style>

<main class="container-fluid px-4" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-calendar3" style="color: var(--color-accent-dark);"></i>
                Calendario <em>Agrícola</em>
            </h1>
            <p>Tareas recomendadas para el olivar mes a mes. Cada tarea puede llevar un consejo práctico para el socio.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Panel
            </a>
            <a href="calendario.php" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> Ver calendario público
            </a>
        </div>
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

    <!-- Rejilla 12 meses (4 cols en xl, 3 en lg, 2 en md) -->
    <div class="row g-3 mb-4">
        <?php foreach ($porMes as $mes => $tareas): ?>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                <div class="mes-card">
                    <h3><i class="bi bi-calendar-event"></i> <?= MESES[$mes] ?></h3>
                    <?php if (empty($tareas)): ?>
                        <p class="text-muted small mb-2">Sin tareas registradas.</p>
                    <?php endif; ?>
                    <?php foreach ($tareas as $t): ?>
                        <div class="tarea-row <?= !$t['activo'] ? 'inactiva' : '' ?> tarea-prio-<?= htmlspecialchars($t['prioridad']) ?>">
                            <i class="bi <?= htmlspecialchars($t['icono']) ?> tarea-icon" aria-hidden="true"></i>
                            <div class="flex-grow-1">
                                <div class="tarea-titulo"><?= htmlspecialchars($t['titulo']) ?></div>
                                <?php if (!$t['activo']): ?>
                                    <small class="text-muted">(oculta)</small>
                                <?php endif; ?>
                            </div>
                            <div class="tarea-acciones">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-tarea"
                                    data-id="<?= (int) $t['id'] ?>"
                                    data-mes="<?= (int) $t['mes'] ?>"
                                    data-titulo="<?= htmlspecialchars($t['titulo']) ?>"
                                    data-descripcion="<?= htmlspecialchars($t['descripcion']) ?>"
                                    data-tip="<?= htmlspecialchars($t['tip'] ?? '') ?>"
                                    data-icono="<?= htmlspecialchars($t['icono']) ?>"
                                    data-prioridad="<?= htmlspecialchars($t['prioridad']) ?>"
                                    data-orden="<?= (int) $t['orden'] ?>"
                                    title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($t['activo']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Ocultar esta tarea?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="archivar">
                                        <input type="hidden" name="id_tarea" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Ocultar">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Mostrar esta tarea de nuevo?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="restaurar">
                                        <input type="hidden" name="id_tarea" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurar">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿BORRAR definitivamente?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="accion" value="borrar">
                                    <input type="hidden" name="id_tarea" value="<?= (int) $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-add-mes mt-2 btn-nueva-tarea" data-mes="<?= $mes ?>">
                        <i class="bi bi-plus-lg"></i> Añadir tarea a <?= MESES[$mes] ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="height: 3rem;"></div>
</main>


<!-- Modal compartido: crear/editar -->
<div class="modal fade" id="modalTarea" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="accion" id="t-accion" value="crear">
      <input type="hidden" name="id_tarea" id="t-id">
      <div class="modal-header">
        <h5 class="modal-title"><span id="t-modo">Nueva</span> tarea</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="admin-label" for="t-mes">Mes *</label>
                <select class="admin-select" id="t-mes" name="mes" required>
                    <?php foreach (MESES as $n => $nombre): ?>
                        <option value="<?= $n ?>"><?= $nombre ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="admin-label" for="t-titulo">Título *</label>
                <input type="text" class="admin-input" id="t-titulo" name="titulo" required maxlength="120">
            </div>
            <div class="col-md-3">
                <label class="admin-label" for="t-orden">Orden</label>
                <input type="number" class="admin-input" id="t-orden" name="orden" min="1" max="99" value="1">
            </div>
            <div class="col-12">
                <label class="admin-label" for="t-descripcion">Descripción *</label>
                <textarea class="admin-textarea" id="t-descripcion" name="descripcion" rows="3" required></textarea>
            </div>
            <div class="col-12">
                <label class="admin-label" for="t-tip">Tip / Consejo</label>
                <textarea class="admin-textarea" id="t-tip" name="tip" rows="2"
                    placeholder="Consejo práctico breve para el socio (opcional)"></textarea>
            </div>
            <div class="col-md-6">
                <label class="admin-label" for="t-icono">Icono Bootstrap</label>
                <input type="text" class="admin-input" id="t-icono" name="icono" value="bi-tree" pattern="bi-[a-z0-9-]+">
                <small class="text-muted">Ej: <code>bi-scissors</code>, <code>bi-droplet</code>. <a href="https://icons.getbootstrap.com/" target="_blank">Catálogo</a></small>
            </div>
            <div class="col-md-6">
                <label class="admin-label" for="t-prioridad">Prioridad</label>
                <select class="admin-select" id="t-prioridad" name="prioridad">
                    <option value="alta">Alta</option>
                    <option value="media" selected>Media</option>
                    <option value="baja">Baja</option>
                </select>
            </div>
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
    const modal = new bootstrap.Modal(document.getElementById('modalTarea'));
    const $ = id => document.getElementById(id);

    document.querySelectorAll('.btn-nueva-tarea').forEach(btn => {
        btn.addEventListener('click', () => {
            $('t-accion').value = 'crear';
            $('t-id').value = '';
            $('t-modo').textContent = 'Nueva';
            $('t-mes').value = btn.dataset.mes;
            $('t-titulo').value = '';
            $('t-descripcion').value = '';
            $('t-tip').value = '';
            $('t-icono').value = 'bi-tree';
            $('t-prioridad').value = 'media';
            $('t-orden').value = '1';
            modal.show();
        });
    });

    document.querySelectorAll('.btn-editar-tarea').forEach(btn => {
        btn.addEventListener('click', () => {
            $('t-accion').value = 'editar';
            $('t-id').value = btn.dataset.id;
            $('t-modo').textContent = 'Editar';
            $('t-mes').value = btn.dataset.mes;
            $('t-titulo').value = btn.dataset.titulo;
            $('t-descripcion').value = btn.dataset.descripcion;
            $('t-tip').value = btn.dataset.tip;
            $('t-icono').value = btn.dataset.icono;
            $('t-prioridad').value = btn.dataset.prioridad;
            $('t-orden').value = btn.dataset.orden;
            modal.show();
        });
    });
})();
</script>
</body>
</html>
