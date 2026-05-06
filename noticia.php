<?php
// Detalle de una noticia individual. Una sola query por slug.
// Si la noticia es 'socios' y no hay sesión → 403 con CTA al login.
// Si no existe → 404 amistoso (no se filtra info de existencia).

session_start();
require_once __DIR__ . '/config/db.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '' || !preg_match('/^[a-z0-9\-]{1,200}$/', $slug)) {
    http_response_code(404);
    $error404 = true;
}

$noticia    = null;
$bloqueada  = false;
$logueado   = isset($_SESSION['rol']);

if (empty($error404)) {
    try {
        $pdo  = getConexion();
        $stmt = $pdo->prepare('SELECT * FROM v_noticias_publicas WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $noticia = $stmt->fetch();

        if (!$noticia) {
            http_response_code(404);
        } elseif ($noticia['visibilidad'] === 'socios' && !$logueado) {
            // Bloqueamos contenido pero no la URL: 403 + CTA al login.
            // Mostramos sólo el título y la teaser.
            http_response_code(403);
            $bloqueada = true;
        } else {
            // Sugeridas: 3 más recientes excluyendo la actual
            $stmt2 = $pdo->prepare('
                SELECT slug, titulo, categoria, fecha_publicacion, resumen
                FROM v_noticias_publicas
                WHERE slug <> :s' . ($logueado ? '' : ' AND visibilidad = "publica"') . '
                ORDER BY fecha_publicacion DESC
                LIMIT 3
            ');
            $stmt2->execute([':s' => $slug]);
            $sugeridas = $stmt2->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('Error en noticia.php: ' . $e->getMessage());
        $error404 = true;
    }
}

require_once __DIR__ . '/includes/i18n.php';
$pageTitle = $noticia
    ? htmlspecialchars($noticia['titulo']) . ' | Noticias | Cooperativa San Juan Bautista'
    : t('meta.noticia_404_title');
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container noticias-main" id="contenido-principal">

    <a href="noticias.php" class="btn btn-link mb-3 ps-0">
        <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(t('noticia.back')) ?>
    </a>

    <?php if (!empty($error404) || !$noticia): ?>
        <div class="noticias-vacio">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
            <h3><?= htmlspecialchars(t('noticia.not_found_title')) ?></h3>
            <p><?= htmlspecialchars(t('noticia.not_found_desc')) ?></p>
            <a href="noticias.php" class="btn btn-primary mt-2">
                <i class="bi bi-newspaper"></i> <?= htmlspecialchars(t('noticia.see_board')) ?>
            </a>
        </div>

    <?php elseif ($bloqueada): ?>
        <article class="noticia-detalle">
            <span class="badge bg-info text-dark">
                <i class="bi bi-lock-fill"></i> <?= htmlspecialchars(t('noticia.members_only')) ?>
            </span>
            <h1 class="noticia-detalle-titulo"><?= htmlspecialchars($noticia['titulo']) ?></h1>
            <?php if (!empty($noticia['resumen'])): ?>
                <p class="noticia-detalle-resumen"><?= htmlspecialchars($noticia['resumen']) ?></p>
            <?php endif; ?>
            <div class="alert alert-warning mt-4">
                <i class="bi bi-info-circle-fill"></i>
                <?= tf('noticia.locked_text', 'index.php#loginModal') /* contiene <strong> y <a> intencionales */ ?>
            </div>
        </article>

    <?php else: ?>
        <?php
            $catColor = [
                'comunicado' => 'primary',
                'novedad'    => 'success',
                'aviso'      => 'danger',
                'evento'     => 'warning',
            ][$noticia['categoria']] ?? 'secondary';
            $parrafos = preg_split('/\n\s*\n/', trim($noticia['contenido']));
            $catLabel = t('noticias.cat_one_' . $noticia['categoria'], $noticia['categoria']);
        ?>
        <article class="noticia-detalle">
            <div class="noticia-detalle-meta">
                <span class="badge bg-<?= $catColor ?>"><?= htmlspecialchars($catLabel) ?></span>
                <?php if ($noticia['visibilidad'] === 'socios'): ?>
                    <span class="badge bg-info text-dark">
                        <i class="bi bi-lock-fill"></i> <?= htmlspecialchars(t('noticias.only_members')) ?>
                    </span>
                <?php endif; ?>
                <span class="noticia-fecha">
                    <i class="bi bi-calendar3"></i>
                    <?= htmlspecialchars(fmt_fecha($noticia['fecha_publicacion'], true)) ?>
                </span>
                <span class="noticia-autor">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($noticia['autor_nombre']) ?>
                </span>
            </div>

            <h1 class="noticia-detalle-titulo"><?= htmlspecialchars($noticia['titulo']) ?></h1>

            <?php if (!empty($noticia['resumen'])): ?>
                <p class="noticia-detalle-resumen"><?= htmlspecialchars($noticia['resumen']) ?></p>
            <?php endif; ?>

            <div class="noticia-detalle-cuerpo">
                <?php foreach ($parrafos as $p): ?>
                    <p><?= nl2br(htmlspecialchars(trim($p))) ?></p>
                <?php endforeach; ?>
            </div>
        </article>

        <?php if (!empty($sugeridas)): ?>
            <hr class="my-5">
            <h3 class="mb-3">
                <i class="bi bi-collection"></i> <?= htmlspecialchars(t('noticia.more')) ?>
            </h3>
            <div class="row g-3">
                <?php foreach ($sugeridas as $s):
                    $sLabel = t('noticias.cat_one_' . $s['categoria'], $s['categoria']);
                ?>
                    <div class="col-md-4">
                        <a href="noticia.php?slug=<?= urlencode($s['slug']) ?>" class="noticia-sugerida">
                            <span class="badge bg-light text-dark mb-2"><?= htmlspecialchars($sLabel) ?></span>
                            <strong><?= htmlspecialchars($s['titulo']) ?></strong>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-calendar3"></i>
                                <?= htmlspecialchars(fmt_fecha($s['fecha_publicacion'])) ?>
                            </small>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>
