<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Panel del Socio
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Página de aterrizaje del socio tras login. Muestra:
 *   - Bienvenida personalizada
 *   - Widget agro-meteorológico (consume api/prevision.php que a su vez
 *     consume Open-Meteo) con recomendación de recolección
 *   - Tarjetas de acceso a las herramientas: Calculadora, Votaciones, Tienda
 * ============================================================================
 */

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'socio') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

// Mini stats del socio para la columna de la izquierda
try {
    $pdo = getConexion();

    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS total,
               COALESCE(SUM(kilos_aceituna), 0) AS kilos,
               COALESCE(SUM(litros_aceite), 0)  AS litros
        FROM entregas
        WHERE id_socio = :id
    ');
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $miStat = $stmt->fetch();

    $stmtVot = $pdo->query('
        SELECT COUNT(*) FROM votaciones
        WHERE estado = "abierta" AND NOW() BETWEEN fecha_inicio AND fecha_fin
    ');
    $votacionesAbiertas = (int) $stmtVot->fetchColumn();

    // Últimas 3 noticias relevantes para el socio (públicas y "socios").
    // El socio ya está logueado, así que ve ambas.
    $ultimasNoticias = $pdo->query('
        SELECT slug, titulo, resumen, categoria, visibilidad, fecha_publicacion, destacado
        FROM v_noticias_publicas
        ORDER BY destacado DESC, fecha_publicacion DESC
        LIMIT 3
    ')->fetchAll();

    // Tareas del mes actual para el widget del calendario
    $tareasMes = $pdo->prepare('
        SELECT titulo, descripcion, tip, icono, prioridad
        FROM calendario_tareas
        WHERE mes = :m AND activo = 1
        ORDER BY orden ASC, id ASC
    ');
    $tareasMes->execute([':m' => (int) date('n')]);
    $tareasMes = $tareasMes->fetchAll();

} catch (PDOException $e) {
    error_log('Error en panel_socio: ' . $e->getMessage());
    $miStat = ['total' => 0, 'kilos' => 0, 'litros' => 0];
    $votacionesAbiertas = 0;
    $ultimasNoticias    = [];
    $tareasMes          = [];
}

$pageTitle = 'Mi Panel | Cooperativa San Juan Bautista';
require_once 'includes/header.php';
?>
<?php require_once 'includes/navbar.php'; ?>

<main class="container panel-socio-main" id="contenido-principal">

    <!-- ── Cabecera de bienvenida ── -->
    <header class="panel-socio-header">
        <span class="hero-label"><i class="bi bi-house-heart-fill"></i> Tu panel</span>
        <h1>Hola, <em><?= htmlspecialchars($_SESSION['nombre']) ?></em></h1>
        <p>Tu información, las herramientas del olivar y la previsión del tiempo en un solo sitio.</p>
    </header>

    <div class="row g-4">

        <div class="col-lg-7">
            <article class="agro-card" id="agro-widget">
                <header class="agro-card-header">
                    <div>
                        <span class="agro-eyebrow">
                            <i class="bi bi-cloud-sun-fill"></i> Agro-meteorología
                        </span>
                        <h2 class="agro-title" id="agro-localidad">Cargando previsión…</h2>
                    </div>
                    <button type="button" class="agro-refresh" id="agro-refresh"
                            title="Actualizar previsión" aria-label="Actualizar previsión">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </header>

                <!-- Recomendación destacada (la "tarjeta dentro de la tarjeta") -->
                <div class="agro-recomendacion" id="agro-recomendacion">
                    <div class="agro-recomendacion-icono" id="agro-rec-icono">⏳</div>
                    <div class="agro-recomendacion-texto">
                        <div class="agro-rec-titulo" id="agro-rec-titulo">Consultando el servicio meteorológico…</div>
                        <div class="agro-rec-mensaje" id="agro-rec-mensaje">Un momento.</div>
                    </div>
                </div>

                <!-- Previsión a 5 días en tarjetas horizontales -->
                <div class="agro-grid" id="agro-grid">
                    <!-- placeholders de carga -->
                    <div class="agro-dia agro-skeleton"></div>
                    <div class="agro-dia agro-skeleton"></div>
                    <div class="agro-dia agro-skeleton"></div>
                    <div class="agro-dia agro-skeleton"></div>
                    <div class="agro-dia agro-skeleton"></div>
                </div>

                <footer class="agro-footer">
                    <span><i class="bi bi-info-circle"></i> Datos: <span id="agro-fuente">Open-Meteo</span></span>
                    <span id="agro-actualizado"></span>
                </footer>
            </article>
        </div>

        <div class="col-lg-5">

            <div class="row g-3">
                <div class="col-6">
                    <div class="mini-stat">
                        <div class="mini-stat-num"><?= (int) $miStat['total'] ?></div>
                        <div class="mini-stat-lbl">Mis entregas</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mini-stat">
                        <div class="mini-stat-num"><?= number_format($miStat['kilos'], 0, ',', '.') ?></div>
                        <div class="mini-stat-lbl">Kg aportados</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mini-stat">
                        <div class="mini-stat-num"><?= number_format($miStat['litros'], 0, ',', '.') ?></div>
                        <div class="mini-stat-lbl">Litros AOVE</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mini-stat <?= $votacionesAbiertas > 0 ? 'mini-stat-alert' : '' ?>">
                        <div class="mini-stat-num"><?= $votacionesAbiertas ?></div>
                        <div class="mini-stat-lbl">Votaciones abiertas</div>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="d-flex flex-column gap-2 mt-3">
                <a href="mis_entregas.php" class="quick-access">
                    <i class="bi bi-clipboard-data"></i>
                    <div><strong>Mis entregas</strong>
                         <span><?= $miStat['total'] > 0
                                ? $miStat['total'] . ' entregas · descarga albaranes'
                                : 'Histórico y albaranes en PDF' ?></span></div>
                    <i class="bi bi-arrow-right-circle"></i>
                </a>
                <a href="calculadora.php" class="quick-access">
                    <i class="bi bi-calculator"></i>
                    <div><strong>Calculadora del olivar</strong>
                         <span>Simula producción y rendimiento</span></div>
                    <i class="bi bi-arrow-right-circle"></i>
                </a>
                <a href="votaciones.php" class="quick-access<?= $votacionesAbiertas > 0 ? ' destacado' : '' ?>">
                    <i class="bi bi-megaphone"></i>
                    <div><strong>Votaciones</strong>
                         <span><?= $votacionesAbiertas > 0
                                ? 'Tienes ' . $votacionesAbiertas . ' votaci' . ($votacionesAbiertas === 1 ? 'ón' : 'ones') . ' abierta' . ($votacionesAbiertas === 1 ? '' : 's')
                                : 'Asambleas y decisiones' ?></span></div>
                    <i class="bi bi-arrow-right-circle"></i>
                </a>
                <a href="tienda.php" class="quick-access">
                    <i class="bi bi-shop"></i>
                    <div><strong>Tienda online</strong>
                         <span>Compra AOVE de la cooperativa</span></div>
                    <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- ── Widgets: noticias + tareas del olivar este mes ── -->
    <div class="row g-4 mt-2">

        <!-- Últimas noticias -->
        <div class="col-lg-7">
            <article class="agro-card">
                <header class="agro-card-header">
                    <div>
                        <span class="agro-eyebrow"><i class="bi bi-megaphone-fill"></i> Tablón</span>
                        <h2 class="agro-title">Últimas noticias</h2>
                    </div>
                    <a href="noticias.php" class="agro-refresh" title="Ver todas">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </header>

                <?php if (empty($ultimasNoticias)): ?>
                    <p class="text-muted small text-center my-4">
                        <i class="bi bi-inbox"></i> No hay noticias todavía.
                    </p>
                <?php else: ?>
                    <ul class="panel-noticias-lista">
                        <?php foreach ($ultimasNoticias as $n):
                            $catColor = [
                                'comunicado' => 'primary',
                                'novedad'    => 'success',
                                'aviso'      => 'danger',
                                'evento'     => 'warning',
                            ][$n['categoria']] ?? 'secondary';
                        ?>
                            <li class="panel-noticia-item">
                                <a href="noticia.php?slug=<?= urlencode($n['slug']) ?>">
                                    <div class="panel-noticia-meta">
                                        <span class="badge bg-<?= $catColor ?>"><?= htmlspecialchars($n['categoria']) ?></span>
                                        <?php if ($n['destacado']): ?>
                                            <i class="bi bi-star-fill" style="color:#D4AF37;" title="Destacada"></i>
                                        <?php endif; ?>
                                        <?php if ($n['visibilidad'] === 'socios'): ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-lock-fill"></i> socios</span>
                                        <?php endif; ?>
                                        <small class="text-muted ms-auto">
                                            <?= date('d/m/Y', strtotime($n['fecha_publicacion'])) ?>
                                        </small>
                                    </div>
                                    <strong><?= htmlspecialchars($n['titulo']) ?></strong>
                                    <?php if (!empty($n['resumen'])): ?>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($n['resumen']) ?></p>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>

        <!-- Tareas del olivar este mes -->
        <div class="col-lg-5">
            <article class="agro-card">
                <header class="agro-card-header">
                    <div>
                        <span class="agro-eyebrow"><i class="bi bi-calendar3"></i> Tu olivar este mes</span>
                        <?php
                            $mesAct = (int) date('n');
                            $mesesNom = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                                         7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                        ?>
                        <h2 class="agro-title"><?= $mesesNom[$mesAct] ?>: tareas recomendadas</h2>
                    </div>
                    <a href="calendario.php" class="agro-refresh" title="Ver calendario completo">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </header>

                <?php if (empty($tareasMes)): ?>
                    <p class="text-muted small text-center my-4">
                        <i class="bi bi-moon-stars"></i> Mes de descanso vegetativo.
                    </p>
                <?php else: ?>
                    <ul class="panel-tareas-lista">
                        <?php foreach ($tareasMes as $t):
                            $prioColor = [
                                'alta'  => '#C0392B',
                                'media' => '#E67E22',
                                'baja'  => '#5A7A5F',
                            ][$t['prioridad']] ?? '#5A7A5F';
                        ?>
                            <li class="panel-tarea-item">
                                <div class="panel-tarea-icono" style="background:<?= $prioColor ?>22; color:<?= $prioColor ?>;">
                                    <i class="bi <?= htmlspecialchars($t['icono']) ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($t['titulo']) ?></strong>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars($t['descripcion']) ?></p>
                                    <?php if (!empty($t['tip'])): ?>
                                        <div class="panel-tarea-tip">
                                            <i class="bi bi-lightbulb-fill"></i>
                                            <?= htmlspecialchars($t['tip']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>

    </div>

</main>

<script>
/**
 * ────────────────────────────────────────────────────────────────────────
 * Widget agro-meteorológico — consumo de API REST externa con fetch.
 * ────────────────────────────────────────────────────────────────────────
 * Llama directamente a Open-Meteo (https://open-meteo.com), que es
 * gratuito, sin API key y soporta CORS desde el navegador.
 *
 * La lógica de recomendación agraria se calcula EN CLIENTE basándose en
 * las prácticas reales del sector olivarero extremeño:
 *   - Lluvia >2 mm hoy/mañana → la aceituna se moja en el remolque y se pudre.
 *   - Viento >40 km/h         → peligroso para varear y para las redes.
 *   - Helada <2 °C            → daño en la aceituna y baja del rendimiento.
 *   - Probabilidad de lluvia >60 % → precaución, mejor adelantar la jornada.
 *
 * Caché en localStorage 30 min: si entra varias veces al panel, no llama
 * a Open-Meteo otra vez (cuidamos de no abusar del servicio gratuito).
 * ────────────────────────────────────────────────────────────────────────
 */
(() => {
    // Coordenadas de Herrera del Duque (Badajoz)
    const HRR_LAT = 39.165;
    const HRR_LON = -5.042;
    const LOCALIDAD = 'Herrera del Duque';
    const CACHE_KEY = 'sjb_meteo_cache';
    const CACHE_TTL = 30 * 60 * 1000;  // 30 minutos

    const refs = {
        loc:     document.getElementById('agro-localidad'),
        grid:    document.getElementById('agro-grid'),
        recIco:  document.getElementById('agro-rec-icono'),
        recTit:  document.getElementById('agro-rec-titulo'),
        recMsg:  document.getElementById('agro-rec-mensaje'),
        recBox:  document.getElementById('agro-recomendacion'),
        actu:    document.getElementById('agro-actualizado'),
        fuente:  document.getElementById('agro-fuente'),
        refresh: document.getElementById('agro-refresh'),
    };

    const NOMBRE_DIA = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

    function iconoWMO(c) {
        if (c === 0)                            return '☀️';
        if (c === 1 || c === 2)                 return '🌤️';
        if (c === 3)                            return '☁️';
        if (c === 45 || c === 48)               return '🌫️';
        if (c >= 51 && c <= 55)                 return '🌦️';
        if (c >= 61 && c <= 65)                 return '🌧️';
        if (c >= 71 && c <= 75)                 return '🌨️';
        if (c >= 80 && c <= 82)                 return '🌧️';
        if (c >= 95 && c <= 99)                 return '⛈️';
        return '🌡️';
    }

    function descripcionWMO(c) {
        if (c === 0)            return 'Despejado';
        if (c === 1)            return 'Mayormente soleado';
        if (c === 2)            return 'Parcialmente nublado';
        if (c === 3)            return 'Nublado';
        if (c === 45 || c === 48) return 'Niebla';
        if (c >= 51 && c <= 55) return 'Llovizna';
        if (c >= 61 && c <= 64) return 'Lluvia';
        if (c === 65)           return 'Lluvia fuerte';
        if (c >= 71 && c <= 75) return 'Nieve';
        if (c >= 80 && c <= 81) return 'Chubascos';
        if (c === 82)           return 'Chubascos fuertes';
        if (c >= 95)            return 'Tormenta';
        return '';
    }

    /**
     * Calcula la recomendación de recolección a partir de los días de previsión.
     * Mismas reglas que tendría la lógica en servidor — defendibles en la memoria.
     */
    function calcularRecomendacion(dias) {
        if (dias.length < 2) {
            return { nivel:'desconocido', icono:'❓', titulo:'Sin datos suficientes',
                     mensaje:'No hay previsión disponible para los próximos días.' };
        }
        const hoy = dias[0], manana = dias[1];

        if (hoy.precip_mm >= 2 || manana.precip_mm >= 2) {
            const cuando = hoy.precip_mm >= 2 ? 'hoy' : 'mañana';
            const mm     = hoy.precip_mm >= 2 ? hoy.precip_mm : manana.precip_mm;
            return {
                nivel:'malo', icono:'🌧️',
                titulo:'NO llevar aceituna a la almazara',
                mensaje:`Se prevén ${mm} mm de lluvia ${cuando}. La aceituna mojada en el remolque se pudre y baja el rendimiento. Espera a que pase el frente.`
            };
        }
        if (hoy.precip_probabilidad >= 60) {
            return {
                nivel:'precaucion', icono:'⛅',
                titulo:'Cuidado con los chubascos',
                mensaje:`Hay un ${hoy.precip_probabilidad}% de probabilidad de lluvia. Adelanta la jornada y vigila el cielo.`
            };
        }
        if (hoy.viento_kmh >= 40) {
            return {
                nivel:'precaucion', icono:'💨',
                titulo:'Viento fuerte',
                mensaje:`Rachas de hasta ${hoy.viento_kmh} km/h. Mucho cuidado al varear y al colocar las redes.`
            };
        }
        if (hoy.temp_min < 2) {
            return {
                nivel:'precaucion', icono:'❄️',
                titulo:'Riesgo de helada matinal',
                mensaje:`Mínima prevista de ${hoy.temp_min} °C. Espera a media mañana — la aceituna helada pierde calidad.`
            };
        }
        return {
            nivel:'bueno', icono:'☀️',
            titulo:'Buen momento para recolectar',
            mensaje:'Sin lluvia, sin viento fuerte y temperaturas estables. Condiciones óptimas para recolectar y transportar la aceituna.'
        };
    }

    /**
     * Convierte la respuesta cruda de Open-Meteo en el formato que pinta la UI.
     */
    function normalizar(apiResp) {
        const d = apiResp.daily;
        const dias = [];
        for (let i = 0; i < d.time.length; i++) {
            const fecha = new Date(d.time[i] + 'T00:00');
            const codigo = d.weather_code[i];
            dias.push({
                fecha: d.time[i],
                dia_completo: i === 0 ? 'Hoy' : i === 1 ? 'Mañana' : NOMBRE_DIA[fecha.getDay()] + ' ' + fecha.getDate(),
                codigo,
                temp_max: Math.round(d.temperature_2m_max[i]),
                temp_min: Math.round(d.temperature_2m_min[i]),
                precip_mm: Math.round(d.precipitation_sum[i] * 10) / 10,
                precip_probabilidad: d.precipitation_probability_max?.[i] ?? 0,
                viento_kmh: Math.round(d.wind_speed_10m_max[i]),
                icono: iconoWMO(codigo),
                descripcion: descripcionWMO(codigo),
            });
        }
        return {
            localidad: LOCALIDAD,
            actualizado: new Date().toLocaleString('es-ES', { dateStyle:'short', timeStyle:'short' }),
            fuente: 'Open-Meteo',
            dias,
            recomendacion: calcularRecomendacion(dias),
        };
    }

    async function cargarPrevision({ forzar = false } = {}) {
        refs.refresh.classList.add('rotando');

        // Caché en localStorage
        if (!forzar) {
            try {
                const c = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
                if (c && (Date.now() - c.ts) < CACHE_TTL) {
                    pintarDatos(c.data, true);
                    refs.refresh.classList.remove('rotando');
                    return;
                }
            } catch (_) { /* caché corrupta, ignorar */ }
        }

        const url = new URL('https://api.open-meteo.com/v1/forecast');
        url.searchParams.set('latitude',  HRR_LAT);
        url.searchParams.set('longitude', HRR_LON);
        url.searchParams.set('daily',
            'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max');
        url.searchParams.set('timezone', 'Europe/Madrid');
        url.searchParams.set('forecast_days', 5);

        try {
            const resp = await fetch(url, { cache: 'no-store' });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const apiResp = await resp.json();
            const data = normalizar(apiResp);

            // Guardar en caché
            try { localStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data })); }
            catch (_) { /* quota llena, ignorar */ }

            pintarDatos(data, false);

        } catch (err) {
            pintarError('No se pudo contactar con Open-Meteo. Comprueba tu conexión a internet.');
        } finally {
            refs.refresh.classList.remove('rotando');
        }
    }

    function pintarDatos(data, esCache) {
        refs.loc.textContent    = `Previsión: ${data.localidad}`;
        refs.fuente.textContent = data.fuente + (esCache ? ' (caché local)' : '');
        refs.actu.textContent   = 'Actualizado ' + data.actualizado;

        refs.recIco.textContent = data.recomendacion.icono;
        refs.recTit.textContent = data.recomendacion.titulo;
        refs.recMsg.textContent = data.recomendacion.mensaje;
        refs.recBox.className   = 'agro-recomendacion nivel-' + data.recomendacion.nivel;

        refs.grid.innerHTML = data.dias.map(d => `
            <div class="agro-dia">
                <div class="agro-dia-fecha">${d.dia_completo}</div>
                <div class="agro-dia-icono">${d.icono}</div>
                <div class="agro-dia-temp">
                    <span class="t-max">${d.temp_max}°</span>
                    <span class="t-min">${d.temp_min}°</span>
                </div>
                <div class="agro-dia-meta">
                    <span title="Lluvia"><i class="bi bi-droplet"></i> ${d.precip_mm}mm</span>
                    <span title="Viento"><i class="bi bi-wind"></i> ${d.viento_kmh} km/h</span>
                </div>
            </div>
        `).join('');
    }

    function pintarError(msg) {
        refs.loc.textContent    = 'Sin datos meteorológicos';
        refs.recIco.textContent = '⚠';
        refs.recTit.textContent = 'No se pudo cargar la previsión';
        refs.recMsg.textContent = msg;
        refs.recBox.className   = 'agro-recomendacion nivel-error';
        refs.grid.innerHTML     = '';
    }

    refs.refresh.addEventListener('click', () => cargarPrevision({ forzar: true }));
    cargarPrevision();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
