<?php
// Calendario agrícola visual: 12 meses con tareas y consejos por mes.
// Página pública. Resalta el mes actual y permite hacer scroll a cualquier mes.

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/i18n.php';

// Helpers locales que delegan en el motor i18n para tener un único punto
// de verdad sobre los nombres de mes (también se usan en formato fecha).
$mesActual    = (int) date('n');
$mesNombre    = fn(int $m): string => t('mes.' . $m);
$mesAbrev     = fn(int $m): string => t('mes.short.' . $m);

try {
    $pdo = getConexion();
    $tareas = $pdo->query('
        SELECT id, mes, titulo, descripcion, tip, icono, prioridad
        FROM calendario_tareas
        WHERE activo = 1
        ORDER BY mes ASC, orden ASC, id ASC
    ')->fetchAll();

    $porMes = array_fill_keys(range(1, 12), []);
    foreach ($tareas as $t) $porMes[(int) $t['mes']][] = $t;
    $totalTareas = count($tareas);
} catch (PDOException $e) {
    error_log('Error en calendario.php: ' . $e->getMessage());
    $porMes      = array_fill_keys(range(1, 12), []);
    $totalTareas = 0;
}

$pageTitle = t('meta.calendario_title');
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container calendario-main" id="contenido-principal">

    <header class="calendario-header">
        <span class="hero-label"><i class="bi bi-calendar3"></i> <?= htmlspecialchars(t('calendario.eyebrow')) ?></span>
        <h1><?= htmlspecialchars(t('calendario.title_1')) ?> <em><?= htmlspecialchars(t('calendario.title_2')) ?></em> <?= htmlspecialchars(t('calendario.title_3')) ?></h1>
        <p>
            <?= tf('calendario.subtitle', $totalTareas) /* contiene <strong> intencional */ ?>
        </p>
    </header>

    <!-- Navegación rápida por meses -->
    <nav class="calendario-nav" aria-label="<?= htmlspecialchars(t('calendario.nav_aria')) ?>">
        <?php for ($n = 1; $n <= 12; $n++): ?>
            <a href="#mes-<?= $n ?>" class="cal-nav-link <?= $n === $mesActual ? 'cal-nav-actual' : '' ?>"
               title="<?= htmlspecialchars(tf('calendario.go_to', $mesNombre($n))) ?>">
                <span class="cal-nav-num"><?= $n ?></span>
                <span class="cal-nav-name"><?= htmlspecialchars($mesAbrev($n)) ?></span>
            </a>
        <?php endfor; ?>
    </nav>

    <!-- Aviso del mes actual -->
    <div class="alert alert-custom alert-success cal-mes-actual-banner" role="status">
        <i class="bi bi-stars"></i>
        <div>
            <strong><?= htmlspecialchars(tf('calendario.current_month_intro', $mesNombre($mesActual))) ?></strong>
            <?php $tHoy = count($porMes[$mesActual]); ?>
            <?= htmlspecialchars($tHoy === 1
                ? t('calendario.tasks_singular')
                : tf('calendario.tasks_plural', $tHoy)) ?>
            <a href="#mes-<?= $mesActual ?>"><?= htmlspecialchars(t('calendario.see_now')) ?></a>
        </div>
    </div>

    <!-- Rejilla 4×3 con los 12 meses -->
    <div class="calendario-grid">
        <?php foreach ($porMes as $mes => $tareasDelMes):
            $esActual = ($mes === $mesActual);
        ?>
            <article id="mes-<?= $mes ?>" class="cal-mes-card <?= $esActual ? 'cal-mes-actual' : '' ?>">
                <header class="cal-mes-header">
                    <span class="cal-mes-num"><?= str_pad((string) $mes, 2, '0', STR_PAD_LEFT) ?></span>
                    <h2 class="cal-mes-titulo"><?= htmlspecialchars($mesNombre($mes)) ?></h2>
                    <?php if ($esActual): ?>
                        <span class="cal-mes-badge"><i class="bi bi-stars"></i> <?= htmlspecialchars(t('calendario.current_badge')) ?></span>
                    <?php endif; ?>
                </header>

                <?php if (empty($tareasDelMes)): ?>
                    <p class="cal-mes-vacio">
                        <i class="bi bi-moon-stars"></i> <?= htmlspecialchars(t('calendario.empty_month')) ?>
                    </p>
                <?php else: ?>
                    <ul class="cal-tareas-lista">
                        <?php foreach ($tareasDelMes as $t):
                            $prioColor = [
                                'alta'  => '#C0392B',
                                'media' => '#E67E22',
                                'baja'  => '#5A7A5F',
                            ][$t['prioridad']] ?? '#5A7A5F';
                        ?>
                            <li class="cal-tarea cal-tarea-<?= htmlspecialchars($t['prioridad']) ?>">
                                <div class="cal-tarea-icono" style="background:<?= $prioColor ?>22; color:<?= $prioColor ?>;">
                                    <i class="bi <?= htmlspecialchars($t['icono']) ?>" aria-hidden="true"></i>
                                </div>
                                <div class="cal-tarea-cuerpo">
                                    <h3 class="cal-tarea-titulo">
                                        <?= htmlspecialchars($t['titulo']) ?>
                                        <span class="cal-tarea-prio" style="background:<?= $prioColor ?>22; color:<?= $prioColor ?>;"
                                              title="<?= htmlspecialchars(tf('calendario.priority', $t['prioridad'])) ?>">
                                            <?= htmlspecialchars($t['prioridad']) ?>
                                        </span>
                                    </h3>
                                    <p class="cal-tarea-desc"><?= htmlspecialchars($t['descripcion']) ?></p>
                                    <?php if (!empty($t['tip'])): ?>
                                        <div class="cal-tarea-tip">
                                            <i class="bi bi-lightbulb-fill"></i>
                                            <span><?= htmlspecialchars($t['tip']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-muted mt-4 mb-5 small">
        <i class="bi bi-info-circle"></i>
        <?= htmlspecialchars(t('calendario.disclaimer')) ?>
    </p>

</main>

<?php require_once 'includes/footer.php'; ?>
