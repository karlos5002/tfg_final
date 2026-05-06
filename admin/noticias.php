<?php
// CRUD del tablón de noticias. Todas las acciones (crear/editar/borrar/toggle)
// pasan por POST con CSRF. El slug se genera automáticamente del título y se
// hace único añadiendo sufijo numérico si colisiona.

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

/**
 * Genera un slug a partir del título: minúsculas, sin acentos, espacios → guiones.
 * Si ya existe en BD, añade sufijo numérico hasta que sea único.
 */
function generarSlugUnico(PDO $pdo, string $titulo, ?int $excluirId = null): string {
    // 1. Slugificar
    $base = mb_strtolower($titulo, 'UTF-8');
    $base = strtr($base,
        ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
         'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
         'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u']);
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?: '';
    $base = trim($base, '-');
    $base = $base !== '' ? $base : 'noticia';
    $base = mb_substr($base, 0, 180);

    // 2. Comprobar unicidad (excluyendo el propio id si estamos editando)
    $slug = $base;
    $i    = 2;
    $sql  = 'SELECT COUNT(*) FROM noticias WHERE slug = :slug' . ($excluirId ? ' AND id <> :id' : '');
    $stmt = $pdo->prepare($sql);
    while (true) {
        $params = [':slug' => $slug];
        if ($excluirId) $params[':id'] = $excluirId;
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $i++;
    }
}

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
                    $idNoticia    = $accion === 'editar'
                        ? filter_input(INPUT_POST, 'id_noticia', FILTER_VALIDATE_INT)
                        : null;
                    $titulo       = trim($_POST['titulo'] ?? '');
                    $resumen      = trim($_POST['resumen'] ?? '');
                    $contenido    = trim($_POST['contenido'] ?? '');
                    $categoria    = $_POST['categoria']   ?? 'comunicado';
                    $visibilidad  = $_POST['visibilidad'] ?? 'publica';
                    $destacado    = isset($_POST['destacado']) ? 1 : 0;

                    if (mb_strlen($titulo) < 4 || mb_strlen($titulo) > 180) {
                        throw new InvalidArgumentException('El título debe tener entre 4 y 180 caracteres.');
                    }
                    if ($contenido === '') {
                        throw new InvalidArgumentException('El contenido no puede estar vacío.');
                    }
                    if (mb_strlen($resumen) > 280) {
                        throw new InvalidArgumentException('El resumen no puede superar 280 caracteres.');
                    }
                    if (!in_array($categoria, ['comunicado','novedad','aviso','evento'], true)) {
                        throw new InvalidArgumentException('Categoría no válida.');
                    }
                    if (!in_array($visibilidad, ['publica','socios'], true)) {
                        throw new InvalidArgumentException('Visibilidad no válida.');
                    }

                    $slug = generarSlugUnico($pdo, $titulo, $idNoticia);

                    if ($accion === 'crear') {
                        $pdo->prepare('
                            INSERT INTO noticias (titulo, slug, resumen, contenido, categoria, visibilidad, destacado, id_autor)
                            VALUES (:t, :s, :r, :c, :cat, :v, :d, :a)
                        ')->execute([
                            ':t' => $titulo, ':s' => $slug,
                            ':r' => $resumen !== '' ? $resumen : null,
                            ':c' => $contenido, ':cat' => $categoria,
                            ':v' => $visibilidad, ':d' => $destacado,
                            ':a' => (int) ($_SESSION['usuario_id'] ?? 0) ?: null,
                        ]);
                        $mensaje_exito = 'Noticia publicada correctamente.';
                    } else {
                        if (!$idNoticia) throw new InvalidArgumentException('ID inválido.');
                        $pdo->prepare('
                            UPDATE noticias
                            SET titulo = :t, slug = :s, resumen = :r, contenido = :c,
                                categoria = :cat, visibilidad = :v, destacado = :d
                            WHERE id = :id
                        ')->execute([
                            ':t' => $titulo, ':s' => $slug,
                            ':r' => $resumen !== '' ? $resumen : null,
                            ':c' => $contenido, ':cat' => $categoria,
                            ':v' => $visibilidad, ':d' => $destacado,
                            ':id' => $idNoticia,
                        ]);
                        $mensaje_exito = 'Noticia actualizada.';
                    }
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'archivar':
                case 'restaurar':
                    $idNoticia = filter_input(INPUT_POST, 'id_noticia', FILTER_VALIDATE_INT);
                    if (!$idNoticia) throw new InvalidArgumentException('ID inválido.');
                    $nuevo = $accion === 'archivar' ? 0 : 1;
                    $pdo->prepare('UPDATE noticias SET activo = :a WHERE id = :id')
                        ->execute([':a' => $nuevo, ':id' => $idNoticia]);
                    $mensaje_exito = $accion === 'archivar' ? 'Noticia archivada.' : 'Noticia restaurada.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;


                case 'borrar':
                    $idNoticia = filter_input(INPUT_POST, 'id_noticia', FILTER_VALIDATE_INT);
                    if (!$idNoticia) throw new InvalidArgumentException('ID inválido.');
                    $pdo->prepare('DELETE FROM noticias WHERE id = :id')->execute([':id' => $idNoticia]);
                    $mensaje_exito = 'Noticia eliminada definitivamente.';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    break;

                default:
                    throw new InvalidArgumentException('Acción no reconocida.');
            }

        } catch (InvalidArgumentException $e) {
            $mensaje_error = $e->getMessage();
        } catch (PDOException $e) {
            error_log('admin_noticias: ' . $e->getMessage());
            $mensaje_error = 'Error al guardar en la base de datos.';
        }
    }
}

// ─── Carga ───────────────────────────────────────────────────────────────
$verArchivadas = (bool) ($_GET['archivadas'] ?? 0);
try {
    $pdo = getConexion();
    $sqlList = '
        SELECT n.*, COALESCE(CONCAT(u.nombre, " ", COALESCE(u.apellidos,"")), "—") AS autor_nombre
        FROM noticias n
        LEFT JOIN usuarios u ON u.id = n.id_autor
    ';
    $sqlList .= $verArchivadas ? ' WHERE n.activo = 0' : ' WHERE n.activo = 1';
    $sqlList .= ' ORDER BY n.destacado DESC, n.fecha_publicacion DESC';
    $noticias = $pdo->query($sqlList)->fetchAll();

    $stats = $pdo->query('
        SELECT
            SUM(activo = 1)             AS publicadas,
            SUM(activo = 0)             AS archivadas,
            SUM(visibilidad = "socios") AS solo_socios,
            SUM(destacado = 1)          AS destacadas
        FROM noticias
    ')->fetch();
} catch (PDOException $e) {
    error_log('admin_noticias GET: ' . $e->getMessage());
    $noticias = [];
    $stats    = ['publicadas' => 0, 'archivadas' => 0, 'solo_socios' => 0, 'destacadas' => 0];
    $mensaje_error = $mensaje_error ?: 'Error al cargar las noticias.';
}

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Tablón de Noticias | Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>
<?php require_once '../includes/admin_navbar.php'; ?>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-megaphone-fill" style="color: var(--color-accent-dark);"></i>
                Tablón de <em>Noticias</em>
            </h1>
            <p>Publica comunicados, avisos y novedades visibles en el sitio público o sólo para socios.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Panel
            </a>
            <a href="noticias.php" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> Ver tablón público
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

    <!-- KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="bi bi-broadcast"></i></div>
                <div class="stat-label">Publicadas</div>
                <div class="stat-value"><?= (int) $stats['publicadas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="bi bi-star-fill"></i></div>
                <div class="stat-label">Destacadas</div>
                <div class="stat-value"><?= (int) $stats['destacadas'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="bi bi-lock-fill"></i></div>
                <div class="stat-label">Sólo socios</div>
                <div class="stat-value"><?= (int) $stats['solo_socios'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="bi bi-archive-fill"></i></div>
                <div class="stat-label">Archivadas</div>
                <div class="stat-value"><?= (int) $stats['archivadas'] ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Form de alta -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header">
                    <i class="bi bi-plus-circle-fill"></i><h2>Nueva Noticia</h2>
                </div>
                <div class="form-card-body">
                    <form method="POST" action="noticias.php" id="form-noticia" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="accion" value="crear" id="form-accion">
                        <input type="hidden" name="id_noticia" value="" id="form-id">

                        <div class="mb-3">
                            <label class="admin-label" for="not-titulo">Título *</label>
                            <input type="text" class="admin-input" id="not-titulo" name="titulo"
                                   maxlength="180" minlength="4" required>
                        </div>
                        <div class="mb-3">
                            <label class="admin-label" for="not-resumen">Resumen <small class="text-muted">(máx. 280)</small></label>
                            <textarea class="admin-textarea" id="not-resumen" name="resumen" rows="2" maxlength="280"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="admin-label" for="not-contenido">Contenido *</label>
                            <textarea class="admin-textarea" id="not-contenido" name="contenido" rows="6" required></textarea>
                            <small class="text-muted">Texto plano. Las líneas vacías se respetan como párrafos.</small>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="admin-label" for="not-categoria">Categoría</label>
                                <select class="admin-select" id="not-categoria" name="categoria">
                                    <option value="comunicado">Comunicado</option>
                                    <option value="novedad">Novedad</option>
                                    <option value="aviso">Aviso</option>
                                    <option value="evento">Evento</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="admin-label" for="not-visibilidad">Visibilidad</label>
                                <select class="admin-select" id="not-visibilidad" name="visibilidad">
                                    <option value="publica">Pública</option>
                                    <option value="socios">Sólo socios</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="not-destacado" name="destacado" value="1">
                            <label class="form-check-label" for="not-destacado">
                                <i class="bi bi-star-fill" style="color:#D4AF37;"></i> Destacar en lo alto del tablón
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-registrar flex-grow-1" id="form-submit-btn">
                                <i class="bi bi-check-lg"></i> Publicar
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="form-cancelar" style="display:none;">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista -->
        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="header-left">
                        <i class="bi bi-list-ul"></i>
                        <h2><?= $verArchivadas ? 'Archivadas' : 'Publicadas' ?></h2>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="noticias.php<?= $verArchivadas ? '' : '?archivadas=1' ?>"
                           class="btn btn-sm <?= $verArchivadas ? 'btn-outline-success' : 'btn-outline-secondary' ?>">
                            <?php if ($verArchivadas): ?>
                                <i class="bi bi-broadcast"></i> Ver publicadas
                            <?php else: ?>
                                <i class="bi bi-archive"></i> Ver archivadas
                            <?php endif; ?>
                        </a>
                        <span class="badge-count"><?= count($noticias) ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if (empty($noticias)): ?>
                        <div class="table-empty"><i class="bi bi-inbox"></i><p>Sin noticias.</p></div>
                    <?php else: ?>
                        <table class="table table-hover table-admin mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th class="text-center">Categoría</th>
                                    <th class="text-center">Visibilidad</th>
                                    <th>Publicada</th>
                                    <th>Autor</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($noticias as $n):
                                    $catColors = [
                                        'comunicado' => 'primary',
                                        'novedad'    => 'success',
                                        'aviso'      => 'danger',
                                        'evento'     => 'warning',
                                    ];
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($n['destacado']): ?>
                                                <i class="bi bi-star-fill" style="color:#D4AF37;" title="Destacada"></i>
                                            <?php endif; ?>
                                            <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                                            <?php if (!empty($n['resumen'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(mb_substr($n['resumen'], 0, 100)) ?>…</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $catColors[$n['categoria']] ?? 'secondary' ?>">
                                                <?= htmlspecialchars($n['categoria']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($n['visibilidad'] === 'socios'): ?>
                                                <span class="badge bg-info text-dark"><i class="bi bi-lock-fill"></i> socios</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark"><i class="bi bi-globe"></i> pública</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d/m/Y H:i', strtotime($n['fecha_publicacion'])) ?>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($n['autor_nombre']) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>"
                                                   target="_blank" class="btn btn-sm btn-outline-primary"
                                                   title="Ver en el sitio">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-not"
                                                    data-id="<?= (int) $n['id'] ?>"
                                                    data-titulo="<?= htmlspecialchars($n['titulo']) ?>"
                                                    data-resumen="<?= htmlspecialchars($n['resumen'] ?? '') ?>"
                                                    data-contenido="<?= htmlspecialchars($n['contenido']) ?>"
                                                    data-categoria="<?= htmlspecialchars($n['categoria']) ?>"
                                                    data-visibilidad="<?= htmlspecialchars($n['visibilidad']) ?>"
                                                    data-destacado="<?= (int) $n['destacado'] ?>"
                                                    title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($n['activo']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Archivar esta noticia? Dejará de ser visible en el tablón.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="accion" value="archivar">
                                                        <input type="hidden" name="id_noticia" value="<?= (int) $n['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="Archivar">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Restaurar esta noticia? Volverá a ser visible.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="accion" value="restaurar">
                                                        <input type="hidden" name="id_noticia" value="<?= (int) $n['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurar">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿BORRAR DEFINITIVAMENTE esta noticia? No se puede deshacer.');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="accion" value="borrar">
                                                    <input type="hidden" name="id_noticia" value="<?= (int) $n['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const form     = document.getElementById('form-noticia');
    const btnSubmit = document.getElementById('form-submit-btn');
    const btnCancel = document.getElementById('form-cancelar');
    const inputs = {
        accion:      document.getElementById('form-accion'),
        id:          document.getElementById('form-id'),
        titulo:      document.getElementById('not-titulo'),
        resumen:     document.getElementById('not-resumen'),
        contenido:   document.getElementById('not-contenido'),
        categoria:   document.getElementById('not-categoria'),
        visibilidad: document.getElementById('not-visibilidad'),
        destacado:   document.getElementById('not-destacado'),
    };

    function entrarModoEdicion(d) {
        inputs.accion.value      = 'editar';
        inputs.id.value          = d.id;
        inputs.titulo.value      = d.titulo;
        inputs.resumen.value     = d.resumen;
        inputs.contenido.value   = d.contenido;
        inputs.categoria.value   = d.categoria;
        inputs.visibilidad.value = d.visibilidad;
        inputs.destacado.checked = d.destacado === '1' || d.destacado === 1;
        btnSubmit.innerHTML = '<i class="bi bi-check-lg"></i> Guardar cambios';
        btnCancel.style.display = '';
        form.scrollIntoView({behavior:'smooth', block:'start'});
        inputs.titulo.focus();
    }
    function salirModoEdicion() {
        form.reset();
        inputs.accion.value = 'crear';
        inputs.id.value = '';
        btnSubmit.innerHTML = '<i class="bi bi-check-lg"></i> Publicar';
        btnCancel.style.display = 'none';
    }

    document.querySelectorAll('.btn-editar-not').forEach(btn => {
        btn.addEventListener('click', () => entrarModoEdicion(btn.dataset));
    });
    btnCancel.addEventListener('click', salirModoEdicion);
})();
</script>
</body>
</html>
