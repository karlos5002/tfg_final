<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Votaciones (vista socio)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Página accesible sólo a socios. Lista las votaciones abiertas + las cerradas
 * con sus resultados. Permite emitir voto en las abiertas (una sola vez por
 * socio gracias a la PK compuesta de la tabla `votos`).
 *
 * Decisión UX — Resultados visibles SÓLO al cerrar la votación:
 *   Mientras la votación está abierta, los socios NO ven el recuento parcial.
 *   Esto evita el "efecto manada" en que el primer voto público condiciona
 *   los siguientes. Al cerrar la votación, los resultados se hacen públicos
 *   junto con el porcentaje de quórum alcanzado.
 *
 * ============================================================================
 */

session_start();

// ─── SEGURIDAD: sólo socios autenticados ─────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'socio') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$idSocio = (int) ($_SESSION['usuario_id'] ?? 0);

try {
    $pdo = getConexion();

    // ─── Total de socios activos (denominador del quórum) ───
    $totalSocios = (int) $pdo->query('
        SELECT COUNT(*) FROM usuarios WHERE rol = "socio" AND activo = 1
    ')->fetchColumn();

    // ─── Votaciones visibles para los socios: abiertas + cerradas ───
    // Las "borrador" se ocultan: el admin todavía las está preparando.
    $stmtV = $pdo->query('
        SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, estado, quorum_minimo
        FROM votaciones
        WHERE estado IN ("abierta", "cerrada")
        ORDER BY estado ASC, fecha_fin DESC
    ');
    $votaciones = $stmtV->fetchAll();

    // ─── Para cada votación, traemos sus opciones y si este socio ya votó ───
    $stmtOps     = $pdo->prepare('SELECT id, texto FROM votacion_opciones WHERE id_votacion = :id ORDER BY orden');
    $stmtMiVoto  = $pdo->prepare('SELECT id_opcion FROM votos WHERE id_votacion = :id AND id_socio = :s');
    $stmtRes     = $pdo->prepare('
        SELECT vo.id, vo.texto, COUNT(v.id_opcion) AS num_votos
        FROM votacion_opciones vo
        LEFT JOIN votos v ON v.id_opcion = vo.id
        WHERE vo.id_votacion = :id
        GROUP BY vo.id, vo.texto, vo.orden
        ORDER BY vo.orden
    ');
    $stmtTotalVotos = $pdo->prepare('SELECT COUNT(*) FROM votos WHERE id_votacion = :id');

    foreach ($votaciones as &$v) {
        $stmtOps->execute([':id' => $v['id']]);
        $v['opciones'] = $stmtOps->fetchAll();

        $stmtMiVoto->execute([':id' => $v['id'], ':s' => $idSocio]);
        $v['mi_opcion'] = $stmtMiVoto->fetchColumn() ?: null;

        // Sólo cargamos resultados si la votación está cerrada (privacidad durante)
        if ($v['estado'] === 'cerrada') {
            $stmtRes->execute([':id' => $v['id']]);
            $v['resultados'] = $stmtRes->fetchAll();

            $stmtTotalVotos->execute([':id' => $v['id']]);
            $v['total_votos'] = (int) $stmtTotalVotos->fetchColumn();
            $v['participacion_pct'] = $totalSocios > 0
                ? round($v['total_votos'] * 100 / $totalSocios, 1)
                : 0;
            $v['quorum_alcanzado'] = $v['participacion_pct'] >= $v['quorum_minimo'];
        }
    }
    unset($v);

} catch (PDOException $e) {
    error_log('Error en votaciones.php: ' . $e->getMessage());
    $votaciones  = [];
    $totalSocios = 0;
    $errorCarga  = 'No se pudieron cargar las votaciones.';
}

$pageTitle = 'Votaciones | Área de Socios';
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container votaciones-main" id="contenido-principal">

    <header class="votaciones-header">
        <span class="hero-label"><i class="bi bi-megaphone"></i> Asamblea cooperativa</span>
        <h1>Votaciones <em>activas</em></h1>
        <p>Tu voz cuenta. Cada votación necesita al menos un <strong><?= $votaciones[0]['quorum_minimo'] ?? 30 ?>%</strong> de socios participando para ser válida.</p>
    </header>

    <?php if (!empty($errorCarga)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorCarga) ?></div>
    <?php endif; ?>

    <?php if (empty($votaciones)): ?>
        <div class="votacion-vacia">
            <i class="bi bi-inbox"></i>
            <p>No hay votaciones por ahora. El administrador te avisará cuando se convoque la próxima asamblea.</p>
        </div>
    <?php else: ?>

        <?php foreach ($votaciones as $v): ?>
            <?php
                $abierta   = $v['estado'] === 'abierta';
                $yaVotó    = $v['mi_opcion'] !== null;
                $finFmt    = date('d/m/Y H:i', strtotime($v['fecha_fin']));
                $inicioFmt = date('d/m/Y', strtotime($v['fecha_inicio']));
            ?>
            <article class="votacion-card <?= $abierta ? 'votacion-abierta' : 'votacion-cerrada' ?>">

                <header class="votacion-card-header">
                    <div>
                        <span class="votacion-estado-badge <?= $v['estado'] ?>">
                            <i class="bi bi-<?= $abierta ? 'circle-fill' : 'check-circle-fill' ?>"></i>
                            <?= $abierta ? 'Abierta' : 'Cerrada' ?>
                        </span>
                        <h2><?= htmlspecialchars($v['titulo']) ?></h2>
                    </div>
                    <div class="votacion-fechas">
                        <small><i class="bi bi-calendar-range"></i></small>
                        <small><?= $inicioFmt ?> — <?= $finFmt ?></small>
                    </div>
                </header>

                <p class="votacion-desc"><?= nl2br(htmlspecialchars($v['descripcion'])) ?></p>

                <?php if ($abierta && $yaVotó): ?>
                    <?php
                        $textoMiOpcion = '';
                        foreach ($v['opciones'] as $op) {
                            if ((int) $op['id'] === (int) $v['mi_opcion']) {
                                $textoMiOpcion = $op['texto'];
                                break;
                            }
                        }
                    ?>
                    <div class="votacion-confirmacion">
                        <i class="bi bi-check2-circle"></i>
                        <div>
                            <strong>Tu voto está registrado.</strong><br>
                            <span>Has elegido: <em><?= htmlspecialchars($textoMiOpcion) ?></em></span><br>
                            <small>Los resultados se publicarán al cerrar la votación.</small>
                        </div>
                    </div>

                <?php elseif ($abierta && !$yaVotó): ?>
                    <form class="votacion-form" data-id-votacion="<?= (int) $v['id'] ?>">
                        <fieldset>
                            <legend class="visually-hidden">Opciones de voto</legend>
                            <?php foreach ($v['opciones'] as $op): ?>
                                <label class="votacion-opcion">
                                    <input type="radio"
                                           name="opcion_<?= (int) $v['id'] ?>"
                                           value="<?= (int) $op['id'] ?>"
                                           required>
                                    <span><?= htmlspecialchars($op['texto']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <button type="submit" class="btn-emitir-voto">
                            <i class="bi bi-check2-square"></i> Emitir voto
                        </button>
                        <p class="votacion-aviso">
                            <i class="bi bi-info-circle"></i>
                            El voto es <strong>definitivo</strong>: no podrás cambiarlo después.
                        </p>
                    </form>

                <?php else: /* cerrada */ ?>
                    <?php
                        $maxVotos = 0;
                        foreach ($v['resultados'] as $r) {
                            if ($r['num_votos'] > $maxVotos) $maxVotos = (int) $r['num_votos'];
                        }
                    ?>
                    <div class="votacion-resultados">
                        <div class="votacion-quorum <?= $v['quorum_alcanzado'] ? 'ok' : 'ko' ?>">
                            <strong><?= $v['quorum_alcanzado'] ? 'Decisión válida' : 'Sin quórum' ?></strong>
                            <span><?= $v['participacion_pct'] ?>% de participación
                                  (<?= $v['total_votos'] ?>/<?= $totalSocios ?> socios · mínimo <?= $v['quorum_minimo'] ?>%)</span>
                        </div>

                        <ul class="votacion-resultados-lista">
                            <?php foreach ($v['resultados'] as $r): ?>
                                <?php
                                    $pct = $v['total_votos'] > 0
                                        ? round($r['num_votos'] * 100 / $v['total_votos'], 1)
                                        : 0;
                                    $ganadora = ($maxVotos > 0 && (int) $r['num_votos'] === $maxVotos);
                                ?>
                                <li class="<?= $ganadora && $v['quorum_alcanzado'] ? 'ganadora' : '' ?>">
                                    <div class="resultado-fila">
                                        <span class="resultado-texto"><?= htmlspecialchars($r['texto']) ?></span>
                                        <span class="resultado-numero"><?= (int) $r['num_votos'] ?> votos · <?= $pct ?>%</span>
                                    </div>
                                    <div class="resultado-barra"><span style="width: <?= $pct ?>%"></span></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </article>
        <?php endforeach; ?>

    <?php endif; ?>

</main>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

document.querySelectorAll('.votacion-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const idVotacion = form.dataset.idVotacion;
        const opcion = form.querySelector('input[type=radio]:checked');
        if (!opcion) return;
        const idOpcion = opcion.value;

        // Confirmación: el voto es irreversible
        if (!confirm('¿Confirmas tu voto? Esta acción es definitiva y no se puede modificar.')) return;

        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        const txtOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando…';

        try {
            const resp = await fetch('api/votar.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    id_votacion: parseInt(idVotacion, 10),
                    id_opcion:   parseInt(idOpcion,   10),
                    csrf_token:  CSRF_TOKEN,
                })
            });
            const data = await resp.json();

            if (!resp.ok || data.error) {
                alert(data.mensaje || `Error ${resp.status}`);
                btn.disabled = false;
                btn.innerHTML = txtOriginal;
                return;
            }

            // Recargamos para mostrar el "ya votado" con su confirmación
            window.location.reload();

        } catch (err) {
            alert('Error de red. Inténtalo de nuevo.');
            btn.disabled = false;
            btn.innerHTML = txtOriginal;
        }
    });
});
</script>

</body>
</html>
