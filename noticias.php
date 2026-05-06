<?php
// Tablón público de noticias. Si no hay sesión sólo muestra "publica";
// con sesión (cualquier rol) muestra también "socios".
// Filtros: por categoría (?cat=...) y por destacadas/normales.

session_start();
require_once __DIR__ . '/config/db.php';

$logueado    = isset($_SESSION['rol']);
$filtroCat   = $_GET['cat'] ?? '';
$catsValidas = ['comunicado', 'novedad', 'aviso', 'evento'];
if (!in_array($filtroCat, $catsValidas, true)) $filtroCat = '';

try {
    $pdo = getConexion();

    $sql = '
        SELECT id, titulo, slug, resumen, contenido, categoria, visibilidad,
               destacado, fecha_publicacion, autor_nombre
        FROM v_noticias_publicas
        WHERE 1 = 1
    ';
    $params = [];
    if (!$logueado) {
        $sql .= ' AND visibilidad = "publica"';
    }
    if ($filtroCat !== '') {
        $sql .= ' AND categoria = :cat';
        $params[':cat'] = $filtroCat;
    }
    $sql .= ' ORDER BY destacado DESC, fecha_publicacion DESC LIMIT 30';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $noticias = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Error en noticias.php: ' . $e->getMessage());
    $noticias = [];
}

require_once __DIR__ . '/includes/i18n.php';
$pageTitle = t('meta.noticias_title');
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container noticias-main" id="contenido-principal">

    <header class="noticias-header">
        <span class="hero-label"><i class="bi bi-megaphone-fill"></i> <?= htmlspecialchars(t('noticias.eyebrow')) ?></span>
        <h1><?= htmlspecialchars(t('noticias.title_1')) ?> <em><?= htmlspecialchars(t('noticias.title_2')) ?></em></h1>
        <p><?= htmlspecialchars(t('noticias.subtitle')) ?></p>
    </header>

    <!-- Filtros por categoría -->
    <div class="noticias-filtros mb-4">
        <a href="noticias.php" class="filtro-noticia <?= $filtroCat === '' ? 'active' : '' ?>">
            <i class="bi bi-collection"></i> <?= htmlspecialchars(t('noticias.filter_all')) ?>
        </a>
        <?php
        $catLabels = [
            'comunicado' => ['key' => 'noticias.cat_comunicado', 'icon' => 'bi-file-text-fill'],
            'novedad'    => ['key' => 'noticias.cat_novedad',    'icon' => 'bi-stars'],
            'aviso'      => ['key' => 'noticias.cat_aviso',      'icon' => 'bi-exclamation-triangle-fill'],
            'evento'     => ['key' => 'noticias.cat_evento',     'icon' => 'bi-calendar-event-fill'],
        ];
        foreach ($catLabels as $val => $info): ?>
            <a href="noticias.php?cat=<?= $val ?>"
               class="filtro-noticia filtro-cat-<?= $val ?> <?= $filtroCat === $val ? 'active' : '' ?>">
                <i class="bi <?= $info['icon'] ?>"></i> <?= htmlspecialchars(t($info['key'])) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$logueado): ?>
        <div class="alert alert-info" role="alert" style="border-left:4px solid var(--color-accent);">
            <i class="bi bi-info-circle"></i>
            <?= t('noticias.guest_alert') /* contiene <strong> intencional */ ?>
        </div>
    <?php endif; ?>

    <?php if (empty($noticias)): ?>
        <div class="noticias-vacio">
            <i class="bi bi-newspaper" aria-hidden="true"></i>
            <h3><?= htmlspecialchars(t('noticias.empty_title')) ?></h3>
            <p><?= htmlspecialchars(t('noticias.empty_desc')) ?></p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($noticias as $n):
                $catColor = [
                    'comunicado' => 'primary',
                    'novedad'    => 'success',
                    'aviso'      => 'danger',
                    'evento'     => 'warning',
                ][$n['categoria']] ?? 'secondary';
                $extracto = $n['resumen'] !== null && $n['resumen'] !== ''
                    ? $n['resumen']
                    : mb_substr(strip_tags($n['contenido']), 0, 200) . '…';
                $catLabel = t('noticias.cat_one_' . $n['categoria'], $n['categoria']);
            ?>
                <div class="<?= $n['destacado'] ? 'col-12' : 'col-md-6' ?>">
                    <article class="noticia-card <?= $n['destacado'] ? 'noticia-destacada' : '' ?>">
                        <?php if ($n['destacado']): ?>
                            <span class="noticia-pin"><i class="bi bi-star-fill"></i> <?= htmlspecialchars(t('noticias.featured')) ?></span>
                        <?php endif; ?>

                        <div class="noticia-meta">
                            <span class="badge bg-<?= $catColor ?>">
                                <?= htmlspecialchars($catLabel) ?>
                            </span>
                            <?php if ($n['visibilidad'] === 'socios'): ?>
                                <span class="badge bg-info text-dark">
                                    <i class="bi bi-lock-fill"></i> <?= htmlspecialchars(t('noticias.only_members')) ?>
                                </span>
                            <?php endif; ?>
                            <span class="noticia-fecha">
                                <i class="bi bi-calendar3"></i>
                                <?= htmlspecialchars(fmt_fecha($n['fecha_publicacion'])) ?>
                            </span>
                        </div>

                        <h2 class="noticia-titulo">
                            <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>">
                                <?= htmlspecialchars($n['titulo']) ?>
                            </a>
                        </h2>

                        <p class="noticia-extracto"><?= htmlspecialchars($extracto) ?></p>

                        <footer class="noticia-footer">
                            <span class="noticia-autor">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($n['autor_nombre']) ?>
                            </span>
                            <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>" class="noticia-leer-mas">
                                <?= htmlspecialchars(t('noticias.read_more')) ?> <i class="bi bi-arrow-right"></i>
                            </a>
                        </footer>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>
