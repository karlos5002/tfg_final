<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Gestión de Votaciones (admin)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Pantalla del admin para crear nuevas votaciones y consultar el estado de
 * las existentes (con enlace al detalle de resultados).
 *
 * Seguridad:
 *   - rol === 'admin' obligatorio.
 *   - Token CSRF en el form de creación.
 *   - INSERT atómico de votación + opciones dentro de TRANSACCIÓN.
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

$mensaje_exito = '';
$mensaje_error = '';

// PROCESAMIENTO POST — crear nueva votación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_votacion') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $mensaje_error = 'Token CSRF inválido. Recarga la página.';
    } else {
        $titulo       = trim($_POST['titulo']      ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $fecha_inicio = $_POST['fecha_inicio']     ?? '';
        $fecha_fin    = $_POST['fecha_fin']        ?? '';
        $estado       = in_array($_POST['estado'] ?? '', ['borrador', 'abierta'], true)
                            ? $_POST['estado'] : 'borrador';
        $quorum       = filter_input(INPUT_POST, 'quorum', FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1, 'max_range' => 100, 'default' => 30]]);
        // Las opciones llegan como un array (el form envía 2..6 inputs con name="opciones[]")
        $opciones = array_filter(
            array_map('trim', $_POST['opciones'] ?? []),
            fn($t) => $t !== ''
        );

        // ─── Validación ─────────────────────────────────────────────────
        // Margen de 5 min al pasado: tolera el tiempo que el admin tarda en
        // rellenar el formulario, pero rechaza fechas claramente retrocesivas.
        $minTimestamp = time() - 300;

        if ($titulo === '' || mb_strlen($titulo) > 150) {
            $mensaje_error = 'El título es obligatorio (máx. 150 caracteres).';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $fecha_inicio) ||
                  !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $fecha_fin)) {
            $mensaje_error = 'Fechas inválidas.';
        } elseif (strtotime($fecha_inicio) < $minTimestamp) {
            $mensaje_error = 'La fecha de inicio no puede ser anterior a la hora actual.';
        } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
            $mensaje_error = 'La fecha de cierre debe ser posterior a la de inicio.';
        } elseif (count($opciones) < 2 || count($opciones) > 6) {
            $mensaje_error = 'Debes definir entre 2 y 6 opciones.';
        } else {
            try {
                $pdo = getConexion();
                $pdo->beginTransaction();

                // Insertar la votación
                $stmtV = $pdo->prepare('
                    INSERT INTO votaciones
                        (titulo, descripcion, fecha_inicio, fecha_fin, estado, quorum_minimo, id_admin_creador)
                    VALUES (:t, :d, :fi, :ff, :e, :q, :a)
                ');
                $stmtV->execute([
                    ':t'  => $titulo,
                    ':d'  => $descripcion ?: null,
                    ':fi' => str_replace('T', ' ', $fecha_inicio) . ':00',
                    ':ff' => str_replace('T', ' ', $fecha_fin)    . ':00',
                    ':e'  => $estado,
                    ':q'  => $quorum,
                    ':a'  => (int) $_SESSION['usuario_id'],
                ]);
                $idVot = (int) $pdo->lastInsertId();

                // Insertar las opciones
                $stmtO = $pdo->prepare('
                    INSERT INTO votacion_opciones (id_votacion, texto, orden)
                    VALUES (:v, :t, :o)
                ');
                foreach (array_values($opciones) as $i => $textoOpcion) {
                    $stmtO->execute([
                        ':v' => $idVot,
                        ':t' => mb_substr($textoOpcion, 0, 120),
                        ':o' => $i + 1,
                    ]);
                }

                $pdo->commit();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $mensaje_exito = 'Votación creada correctamente' . ($estado === 'abierta'
                    ? ' y publicada para los socios.'
                    : ' como borrador.');

            } catch (PDOException $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                error_log('Error creando votación: ' . $e->getMessage());
                $mensaje_error = 'Error al guardar la votación. Inténtalo de nuevo.';
            }
        }
    }
}


// LECTURA — listado de votaciones con métricas
try {
    $pdo = getConexion();

    $totalSocios = (int) $pdo->query('
        SELECT COUNT(*) FROM usuarios WHERE rol = "socio" AND activo = 1
    ')->fetchColumn();

    $stmtV = $pdo->query('
        SELECT v.id, v.titulo, v.estado, v.fecha_inicio, v.fecha_fin, v.quorum_minimo,
               (SELECT COUNT(*) FROM votos WHERE id_votacion = v.id)            AS votos_emitidos,
               (SELECT COUNT(*) FROM votacion_opciones WHERE id_votacion = v.id) AS num_opciones
        FROM votaciones v
        ORDER BY FIELD(v.estado,"abierta","borrador","cerrada"), v.fecha_fin DESC
    ');
    $votaciones = $stmtV->fetchAll();

} catch (PDOException $e) {
    error_log('Error cargando votaciones admin: ' . $e->getMessage());
    $votaciones  = [];
    $totalSocios = 0;
    $mensaje_error = $mensaje_error ?: 'Error al cargar las votaciones.';
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Gestión de Votaciones | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-megaphone-fill" style="color: var(--color-accent-dark);" aria-hidden="true"></i>
                Gestión de <em>Votaciones</em>
            </h1>
            <p>Crea asambleas, consulta participación y publica resultados. Quórum por defecto: 30 % de socios activos (<?= $totalSocios ?>).</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Panel
        </a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($mensaje_exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if ($mensaje_error): ?>
        <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── Formulario de creación ── -->
        <div class="col-lg-5">
            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-plus-circle-fill"></i>
                    <h2>Nueva votación</h2>
                </div>
                <div class="form-card-body">
                    <form method="POST" action="votaciones.php" id="form-nueva-votacion" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="crear_votacion">

                        <div class="mb-3">
                            <label class="admin-label" for="vot-titulo">Título</label>
                            <input type="text" class="admin-input" id="vot-titulo" name="titulo"
                                   maxlength="150" placeholder="Ej: Inversión en envasadora" required>
                        </div>

                        <div class="mb-3">
                            <label class="admin-label" for="vot-desc">Descripción</label>
                            <textarea class="admin-textarea" id="vot-desc" name="descripcion"
                                      rows="3" placeholder="Contexto y consecuencias para los socios"></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <?php
                            // min HTML5: el navegador no deja escoger una hora pasada.
                            // La validación servidor (con margen de 5 min) cierra el flanco.
                            $minAhora = date('Y-m-d\TH:i');
                            ?>
                            <div class="col-6">
                                <label class="admin-label" for="vot-inicio">Inicio</label>
                                <input type="datetime-local" class="admin-input" id="vot-inicio"
                                       name="fecha_inicio" value="<?= $minAhora ?>"
                                       min="<?= $minAhora ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="admin-label" for="vot-fin">Cierre</label>
                                <input type="datetime-local" class="admin-input" id="vot-fin"
                                       name="fecha_fin" value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>"
                                       min="<?= $minAhora ?>" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="admin-label" for="vot-quorum">Quórum mínimo (%)</label>
                                <input type="number" class="admin-input" id="vot-quorum"
                                       name="quorum" min="1" max="100" value="30" required>
                            </div>
                            <div class="col-6">
                                <label class="admin-label" for="vot-estado">Publicación</label>
                                <select class="admin-select" id="vot-estado" name="estado">
                                    <option value="borrador">Guardar como borrador</option>
                                    <option value="abierta">Publicar inmediatamente</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="admin-label">Opciones de voto (entre 2 y 6)</label>
                            <div id="opciones-wrapper">
                                <div class="opcion-row">
                                    <input type="text" class="admin-input" name="opciones[]"
                                           placeholder="Opción 1 (ej: Sí, aprobar)" maxlength="120" required>
                                    <button type="button" class="btn-quitar-opcion" aria-label="Quitar">×</button>
                                </div>
                                <div class="opcion-row">
                                    <input type="text" class="admin-input" name="opciones[]"
                                           placeholder="Opción 2 (ej: No)" maxlength="120" required>
                                    <button type="button" class="btn-quitar-opcion" aria-label="Quitar">×</button>
                                </div>
                            </div>
                            <button type="button" class="btn-anadir-opcion" id="btn-add-opcion">
                                <i class="bi bi-plus-lg"></i> Añadir otra opción
                            </button>
                        </div>

                        <button type="submit" class="btn-registrar">
                            <i class="bi bi-megaphone"></i> Crear votación
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Listado de votaciones ── -->
        <div class="col-lg-7">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="header-left">
                        <i class="bi bi-list-check"></i>
                        <h2>Votaciones creadas</h2>
                    </div>
                    <span class="badge-count">
                        <?= count($votaciones) ?> votaci<?= count($votaciones) === 1 ? 'ón' : 'ones' ?>
                    </span>
                </div>
                <div class="table-responsive">
                    <?php if (empty($votaciones)): ?>
                        <div class="table-empty">
                            <i class="bi bi-megaphone"></i>
                            <p>Aún no has creado ninguna votación.</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-hover table-admin mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Cierre</th>
                                    <th class="text-center">Participación</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($votaciones as $v):
                                    $pct = $totalSocios > 0
                                        ? round($v['votos_emitidos'] * 100 / $totalSocios, 1)
                                        : 0;
                                    $colorEstado = match ($v['estado']) {
                                        'abierta'  => 'success',
                                        'cerrada'  => 'secondary',
                                        'borrador' => 'warning',
                                        default    => 'secondary',
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($v['titulo']) ?></strong>
                                            <br><small class="text-muted"><?= (int) $v['num_opciones'] ?> opciones</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $colorEstado ?>"><?= htmlspecialchars($v['estado']) ?></span>
                                        </td>
                                        <td class="text-center text-muted small">
                                            <?= date('d/m/Y H:i', strtotime($v['fecha_fin'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= $v['votos_emitidos'] ?></strong>/<?= $totalSocios ?>
                                            (<?= $pct ?>%)
                                            <br>
                                            <small class="<?= $pct >= $v['quorum_minimo'] ? 'text-success' : 'text-muted' ?>">
                                                quórum <?= $v['quorum_minimo'] ?>%
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="votacion_resultados.php?id=<?= (int) $v['id'] ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver resultados detallados">
                                                <i class="bi bi-bar-chart"></i> Resultados
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
// Form dinámico de opciones (2..6)
(() => {
    const wrapper = document.getElementById('opciones-wrapper');
    const btnAdd  = document.getElementById('btn-add-opcion');
    const MAX     = 6;

    function refrescarBotones() {
        const filas = wrapper.querySelectorAll('.opcion-row');
        // Quitar visible sólo si hay >2 filas
        filas.forEach(f => {
            f.querySelector('.btn-quitar-opcion').style.visibility =
                filas.length > 2 ? 'visible' : 'hidden';
        });
        btnAdd.disabled = filas.length >= MAX;
    }

    btnAdd.addEventListener('click', () => {
        const filas = wrapper.querySelectorAll('.opcion-row').length;
        if (filas >= MAX) return;
        const div = document.createElement('div');
        div.className = 'opcion-row';
        div.innerHTML = `
            <input type="text" class="admin-input" name="opciones[]"
                   placeholder="Opción ${filas + 1}" maxlength="120" required>
            <button type="button" class="btn-quitar-opcion" aria-label="Quitar">×</button>`;
        wrapper.appendChild(div);
        refrescarBotones();
    });

    wrapper.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-quitar-opcion')) {
            const filas = wrapper.querySelectorAll('.opcion-row');
            if (filas.length <= 2) return;
            e.target.closest('.opcion-row').remove();
            refrescarBotones();
        }
    });

    refrescarBotones();
})();
</script>

</body>
</html>
