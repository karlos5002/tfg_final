<?php
/**
 * Sesión necesaria para:
 *   - Mostrar estado de login en la navbar
 *   - Mostrar errores de login desde login.php
 *   - Gestionar el carrito de compras
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/i18n.php';

// Capturar y limpiar mensajes de sesión (vienen de login.php, registro.php, recuperar_password.php).
// flash_get() resuelve la clave i18n al idioma actual y limpia la sesión.
$loginError     = flash_get('login_error');
$loginExito     = flash_get('login_exito');
$registroError  = flash_get('registro_error');
$registroExito  = flash_get('registro_exito');
$recuperarError = flash_get('recuperar_error');

$estaLogueado = isset($_SESSION['usuario_id']);

// ── Carrito: contar items actuales ──
$totalCarrito = 0;
if (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $totalCarrito += $item['cantidad'];
    }
}

// ── Productos: obtener los 3 primeros activos con stock para la vitrina ──
try {
    $pdo = getConexion();
    $stmtProd = $pdo->query('
        SELECT id, nombre, variedad, descripcion, precio, stock, imagen
        FROM productos
        WHERE activo = 1 AND stock > 0
        ORDER BY id ASC
        LIMIT 3
    ');
    $productosVitrina = $stmtProd->fetchAll();

    // Total de productos disponibles para mostrar el CTA "X variedades más en la tienda"
    $totalProductosTienda = (int) $pdo->query('
        SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock > 0
    ')->fetchColumn();
} catch (PDOException $e) {
    error_log('Error cargando productos en index.php: ' . $e->getMessage());
    $productosVitrina      = [];
    $totalProductosTienda  = 0;
}
?>
<?php require_once 'includes/header.php'; ?>

    <!-- Skip-link para navegación por teclado (WCAG 2.4.1): invisible hasta el foco -->
    <a href="#contenido-principal" class="skip-link" id="skip-link">
        <?= htmlspecialchars(t('skip_link')) ?>
    </a>

    <?php require_once 'includes/navbar.php'; ?>

    <main id="contenido-principal">

        <!-- Hero a pantalla completa: imagen del olivar + overlay degradado -->
        <section class="hero" id="inicio" aria-labelledby="hero-heading">

            <!-- Imagen de fondo: olivar andaluz con efecto zoom suave -->
            <div class="hero-bg" role="img"
                aria-label="<?= htmlspecialchars(t('hero.bg_aria')) ?>">
            </div>

            <!-- Overlay: capa oscura degradada para contraste texto-fondo.
                 Asegura ratio de contraste WCAG 4.5:1 sobre cualquier zona de la imagen -->
            <div class="hero-overlay" aria-hidden="true"></div>

            <!-- Partículas decorativas flotantes (puntos dorados) -->
            <div class="hero-particles" aria-hidden="true">
                <span class="particle particle-1"></span>
                <span class="particle particle-2"></span>
                <span class="particle particle-3"></span>
                <span class="particle particle-4"></span>
                <span class="particle particle-5"></span>
            </div>

            <!-- Contenido del Hero -->
            <div class="hero-content">
                <span class="hero-label" id="hero-label">
                    <?= htmlspecialchars(t('hero.label')) ?>
                </span>
                <h1 class="hero-title" id="hero-heading">
                    <?= htmlspecialchars(t('hero.title_1')) ?><br><em><?= htmlspecialchars(t('hero.title_2')) ?></em>
                </h1>
                <p class="hero-subtitle">
                    <?= htmlspecialchars(t('hero.subtitle')) ?>
                </p>
                <div class="hero-actions">
                    <a href="#productos" class="btn-gold" id="hero-cta">
                        <?= htmlspecialchars(t('hero.cta_primary')) ?>
                        <i class="bi bi-arrow-right btn-icon" aria-hidden="true"></i>
                    </a>
                    <a href="#esencia" class="btn-ghost" id="hero-cta-secondary">
                        <?= htmlspecialchars(t('hero.cta_secondary')) ?>
                    </a>
                </div>
            </div>

            <!-- Indicador de scroll — guía visual para nuevos visitantes -->
            <div class="scroll-indicator" aria-hidden="true">
                <div class="scroll-mouse"></div>
                <span><?= htmlspecialchars(t('hero.scroll')) ?></span>
            </div>

        </section>

        <!-- Barra de estadísticas con contadores animados al entrar en viewport -->
        <section class="stats-bar" id="stats" aria-label="<?= htmlspecialchars(t('stats.aria')) ?>">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item reveal" id="stat-anos">
                        <span class="stat-number" data-target="70">0</span>
                        <span class="stat-suffix">+</span>
                        <span class="stat-label"><?= htmlspecialchars(t('stats.years')) ?></span>
                    </div>
                    <div class="stat-item reveal reveal-delay-1" id="stat-familias">
                        <span class="stat-number" data-target="350">0</span>
                        <span class="stat-suffix"></span>
                        <span class="stat-label"><?= htmlspecialchars(t('stats.families')) ?></span>
                    </div>
                    <div class="stat-item reveal reveal-delay-2" id="stat-hectareas">
                        <span class="stat-number" data-target="2500">0</span>
                        <span class="stat-suffix">+</span>
                        <span class="stat-label"><?= htmlspecialchars(t('stats.hectares')) ?></span>
                    </div>
                    <div class="stat-item reveal reveal-delay-3" id="stat-premios">
                        <span class="stat-number" data-target="12">0</span>
                        <span class="stat-suffix"></span>
                        <span class="stat-label"><?= htmlspecialchars(t('stats.awards')) ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección "Nuestra esencia": layout asimétrico 5/7 imagen/texto + badge "+70 años" -->
        <section class="section about-section" id="esencia" aria-labelledby="about-heading">
            <div class="container">
                <div class="row align-items-center g-5 about-row">

                    <div class="col-lg-5 reveal">
                        <div class="about-img-wrapper">
                            <!-- Borde decorativo exterior dorado -->
                            <div class="about-img-deco" aria-hidden="true"></div>
                            <img src="assets/img/olivar-tradicion.png"
                                alt="<?= htmlspecialchars(t('about.img_alt')) ?>"
                                width="987" height="1480" loading="lazy">

                            <!-- Badge decorativo flotante con dato clave -->
                            <div class="about-badge" aria-label="<?= htmlspecialchars(t('about.badge_aria')) ?>">
                                <span class="about-badge-number"><?= htmlspecialchars(t('about.badge_number')) ?></span>
                                <span class="about-badge-text"><?= t('about.badge_text') /* contiene <br> intencional */ ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 reveal reveal-delay-1">
                        <div class="about-text-content">
                            <span class="section-label"><?= htmlspecialchars(t('about.label')) ?></span>
                            <h2 class="section-title" id="about-heading">
                                <?= htmlspecialchars(t('about.title_1')) ?><br><?= htmlspecialchars(t('about.title_2')) ?> <em
                                    class="fw-serif text-accent"><?= htmlspecialchars(t('about.title_3')) ?></em>
                            </h2>
                            <span class="title-ornament" aria-hidden="true"></span>
                            <p>
                                <?= htmlspecialchars(t('about.p1')) ?>
                            </p>
                            <p>
                                <?= htmlspecialchars(t('about.p2')) ?>
                            </p>

                            <!-- Valores de marca en grid compacto de 2 columnas.
                                 Decisión: checkmarks dorados refuerzan la calidad de forma visual
                                 sin necesidad de texto largo. Rápido de escanear. -->
                            <ul class="values-list" aria-label="<?= htmlspecialchars(t('about.values_aria')) ?>">
                                <li>
                                    <i class="bi bi-check-circle-fill value-icon" aria-hidden="true"></i>
                                    <?= htmlspecialchars(t('about.value_1')) ?>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill value-icon" aria-hidden="true"></i>
                                    <?= htmlspecialchars(t('about.value_2')) ?>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill value-icon" aria-hidden="true"></i>
                                    <?= htmlspecialchars(t('about.value_3')) ?>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill value-icon" aria-hidden="true"></i>
                                    <?= htmlspecialchars(t('about.value_4')) ?>
                                </li>
                            </ul>

                            <a href="#productos" class="btn-outline-gold" id="about-cta">
                                <?= htmlspecialchars(t('about.cta')) ?>
                                <i class="bi bi-arrow-right btn-icon" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Sección "Productos": grid de 3 columnas con badge de categoría, título serif y precio dorado -->
        <section class="section products-section" id="productos" aria-labelledby="products-heading">
            <div class="container">

                <!-- Cabecera de sección centrada -->
                <div class="section-header text-center reveal">
                    <span class="section-label"><?= htmlspecialchars(t('products.label')) ?></span>
                    <h2 class="section-title" id="products-heading">
                        <?= htmlspecialchars(t('products.title_1')) ?> <em class="fw-serif text-accent"><?= htmlspecialchars(t('products.title_2')) ?></em>
                    </h2>
                    <p class="section-subtitle mx-auto">
                        <?= htmlspecialchars(t('products.subtitle')) ?>
                    </p>
                    <span class="title-ornament mx-auto" aria-hidden="true"></span>
                </div>

                <!-- Grid de productos (3 columnas en desktop) -->
                <div class="row g-4 g-lg-5" id="grid-productos-inicio">

                    <?php
                    // Badges por variedad (reutilizando las clases CSS existentes)
                    $badgeClases = [
                        'Picual'     => 'product-badge-eco',
                        'Arbequina'  => 'product-badge-premium',
                        'Hojiblanca' => 'product-badge-suave',
                        'Coupage'    => 'product-badge-premium',
                    ];
                    $delayIndex = 0;
                    foreach ($productosVitrina as $prod):
                        $delayIndex++;
                        $badgeClass = $badgeClases[$prod['variedad']] ?? 'product-badge-eco';
                    ?>
                    <div class="col-md-6 col-lg-4 reveal reveal-delay-<?= $delayIndex ?>">
                        <article class="product-card" id="producto-<?= $prod['id'] ?>">
                            <div class="product-card-img">
                                <span class="product-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($prod['variedad']) ?>
                                </span>
                                <img src="assets/img/<?= htmlspecialchars($prod['imagen']) ?>"
                                    alt="<?= htmlspecialchars($prod['nombre']) ?> — AOVE variedad <?= htmlspecialchars($prod['variedad']) ?>"
                                    width="400" height="400" loading="lazy">
                            </div>
                            <div class="product-card-body">
                                <h3 class="product-card-title"><?= htmlspecialchars($prod['nombre']) ?></h3>
                                <p class="product-card-desc">
                                    <?= htmlspecialchars($prod['descripcion']) ?>
                                </p>
                                <div class="product-card-footer">
                                    <span class="product-price">
                                        <?= number_format($prod['precio'], 2, ',', '.') ?>€
                                    </span>
                                    <button type="button" class="btn-card btn-add-cart-index"
                                        data-id="<?= $prod['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($prod['nombre']) ?>"
                                        aria-label="<?= htmlspecialchars(tf('products.aria_add', $prod['nombre'])) ?>"
                                        id="btn-comprar-<?= $prod['id'] ?>">
                                        <i class="bi bi-bag-plus" aria-hidden="true"></i> <?= htmlspecialchars(t('products.btn_add')) ?>
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($productosVitrina)): ?>
                        <div class="col-12 text-center py-5">
                            <p class="text-muted"><?= htmlspecialchars(t('products.empty')) ?></p>
                        </div>
                    <?php endif; ?>

                </div><!-- /.row -->

                <?php
                // ── CTA "ver más en la tienda" ──
                // Sólo aparece si el catálogo real tiene más variedades que las
                // 3 destacadas que mostramos arriba: evita decir "hay más" si no
                // las hay (mentira al cliente) y se autoadapta al stock real.
                $masVariedades = max(0, $totalProductosTienda - count($productosVitrina));
                if ($masVariedades > 0):
                ?>
                <div class="cta-tienda-mas reveal" aria-label="<?= htmlspecialchars(t('cta_more.aria')) ?>">
                    <div class="cta-tienda-mas-icono" aria-hidden="true">
                        <i class="bi bi-shop-window"></i>
                    </div>
                    <div class="cta-tienda-mas-texto">
                        <p class="cta-tienda-mas-eyebrow"><?= htmlspecialchars(t('cta_more.eyebrow')) ?></p>
                        <h3><?= tf($masVariedades === 1 ? 'cta_more.title_singular' : 'cta_more.title_plural', $masVariedades) /* contiene <strong> intencional */ ?></h3>
                        <p><?= htmlspecialchars(t('cta_more.desc')) ?></p>
                    </div>
                    <a href="tienda.php" class="btn-gold" id="cta-ver-tienda">
                        <?= htmlspecialchars(t('cta_more.btn')) ?>
                        <i class="bi bi-arrow-right btn-icon" aria-hidden="true"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section process-section" id="proceso" aria-labelledby="process-heading">
            <div class="container">
                <div class="section-header text-center reveal">
                    <span class="section-label"><?= htmlspecialchars(t('process.label')) ?></span>
                    <h2 class="section-title" id="process-heading">
                        <?= htmlspecialchars(t('process.title_1')) ?> <em class="fw-serif text-accent"><?= htmlspecialchars(t('process.title_2')) ?></em>
                    </h2>
                    <p class="section-subtitle mx-auto">
                        <?= htmlspecialchars(t('process.subtitle')) ?>
                    </p>
                    <span class="title-ornament mx-auto" aria-hidden="true"></span>
                </div>

                <div class="process-grid">
                    <!-- Paso 1 -->
                    <div class="process-step reveal" id="process-step-1">
                        <div class="process-step-number" aria-hidden="true">01</div>
                        <div class="process-step-icon">
                            <i class="bi bi-tree" aria-hidden="true"></i>
                        </div>
                        <h3 class="process-step-title"><?= htmlspecialchars(t('process.step1_title')) ?></h3>
                        <p class="process-step-desc">
                            <?= htmlspecialchars(t('process.step1_desc')) ?>
                        </p>
                    </div>

                    <!-- Conector visual -->
                    <div class="process-connector" aria-hidden="true">
                        <div class="connector-line"></div>
                        <i class="bi bi-chevron-right"></i>
                    </div>

                    <!-- Paso 2 -->
                    <div class="process-step reveal reveal-delay-1" id="process-step-2">
                        <div class="process-step-number" aria-hidden="true">02</div>
                        <div class="process-step-icon">
                            <i class="bi bi-gear" aria-hidden="true"></i>
                        </div>
                        <h3 class="process-step-title"><?= htmlspecialchars(t('process.step2_title')) ?></h3>
                        <p class="process-step-desc">
                            <?= htmlspecialchars(t('process.step2_desc')) ?>
                        </p>
                    </div>

                    <!-- Conector visual -->
                    <div class="process-connector" aria-hidden="true">
                        <div class="connector-line"></div>
                        <i class="bi bi-chevron-right"></i>
                    </div>

                    <!-- Paso 3 -->
                    <div class="process-step reveal reveal-delay-2" id="process-step-3">
                        <div class="process-step-number" aria-hidden="true">03</div>
                        <div class="process-step-icon">
                            <i class="bi bi-droplet" aria-hidden="true"></i>
                        </div>
                        <h3 class="process-step-title"><?= htmlspecialchars(t('process.step3_title')) ?></h3>
                        <p class="process-step-desc">
                            <?= htmlspecialchars(t('process.step3_desc')) ?>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section" id="visita" aria-labelledby="cta-heading">
            <div class="cta-overlay" aria-hidden="true"></div>
            <div class="container">
                <div class="cta-content reveal">
                    <span class="section-label"><?= htmlspecialchars(t('visit.label')) ?></span>
                    <h2 class="section-title" id="cta-heading">
                        <?= htmlspecialchars(t('visit.title')) ?>
                    </h2>
                    <p class="cta-subtitle">
                        <?= htmlspecialchars(t('visit.subtitle')) ?>
                    </p>
                    <a href="#" class="btn-gold" id="cta-reservar"
                       data-bs-toggle="modal" data-bs-target="#modalReservaVisita">
                        <?= htmlspecialchars(t('visit.cta')) ?>
                        <i class="bi bi-calendar-event btn-icon" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </section>

        <section class="section map-section" id="ubicacion" aria-labelledby="map-heading">
            <div class="container">
                <div class="section-header text-center reveal">
                    <span class="section-label"><?= htmlspecialchars(t('map.label')) ?></span>
                    <h2 class="section-title" id="map-heading">
                        <?= htmlspecialchars(t('map.title_1')) ?> <em class="fw-serif text-accent"><?= htmlspecialchars(t('map.title_2')) ?></em>
                    </h2>
                    <p class="section-subtitle mx-auto">
                        <?= htmlspecialchars(t('map.subtitle')) ?>
                    </p>
                    <span class="title-ornament mx-auto" aria-hidden="true"></span>
                </div>

                <div class="map-wrapper reveal">
                    <div class="map-info-card">
                        <div class="map-info-icon">
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="map-info-title"><?= htmlspecialchars(t('map.cooperative')) ?></h3>
                            <p class="map-info-address"><?= t('map.address') /* contiene <br> intencional */ ?></p>
                        </div>
                    </div>
                    <div class="map-container" id="google-map">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d24788.15!2d-5.0483!3d39.1567!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd6645c32f9b4a87%3A0x40463fd8ca23eb0!2sHerrera%20del%20Duque%2C%20Badajoz!5e0!3m2!1ses!2ses!4v1700000000000!5m2!1ses!2ses"
                            width="100%"
                            height="450"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="<?= htmlspecialchars(t('map.iframe_title')) ?>"
                            aria-label="<?= htmlspecialchars(t('map.iframe_aria')) ?>">
                        </iframe>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <?php
        // Token CSRF (si index.php todavía no lo creó)
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        // Mínima fecha aceptable: mañana (24h de antelación)
        $minFecha = date('Y-m-d', strtotime('+1 day'));
    ?>
    <div class="modal fade" id="modalReservaVisita" tabindex="-1"
         aria-labelledby="modalReservaVisitaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content reserva-modal-content">
                <div class="modal-header reserva-modal-header">
                    <div class="reserva-modal-brand">
                        <span class="brand-icon" aria-hidden="true">🫒</span>
                        <h2 class="modal-title" id="modalReservaVisitaLabel"><?= htmlspecialchars(t('reserva.title')) ?></h2>
                    </div>
                    <button type="button" class="btn-close-modal" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars(t('reserva.close_aria')) ?>">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="modal-body reserva-modal-body">
                    <p class="reserva-intro">
                        <?= htmlspecialchars(t('reserva.intro')) ?>
                    </p>

                    <form id="form-reserva-visita" novalidate>
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-nombre">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars(t('reserva.lbl_name')) ?>
                                </label>
                                <input type="text" class="reserva-input" id="rsv-nombre" name="nombre"
                                       maxlength="100" required>
                            </div>
                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-email">
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars(t('reserva.lbl_email')) ?>
                                </label>
                                <input type="email" class="reserva-input" id="rsv-email" name="email"
                                       maxlength="180" required>
                            </div>

                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-tel">
                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars(t('reserva.lbl_phone')) ?>
                                    <small class="opcional"><?= htmlspecialchars(t('reserva.opt_short')) ?></small>
                                </label>
                                <input type="tel" class="reserva-input" id="rsv-tel" name="telefono"
                                       placeholder="+34 600 123 456">
                            </div>
                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-personas">
                                    <i class="bi bi-people"></i> <?= htmlspecialchars(t('reserva.lbl_persons')) ?>
                                </label>
                                <input type="number" class="reserva-input" id="rsv-personas" name="num_personas"
                                       min="1" max="10" value="2" required>
                            </div>

                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-fecha">
                                    <i class="bi bi-calendar-event"></i> <?= htmlspecialchars(t('reserva.lbl_date')) ?>
                                </label>
                                <input type="date" class="reserva-input" id="rsv-fecha" name="fecha_visita"
                                       min="<?= $minFecha ?>" required>
                                <small class="reserva-hint">
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars(t('reserva.hint_date')) ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="reserva-label" for="rsv-hora">
                                    <i class="bi bi-clock"></i> <?= htmlspecialchars(t('reserva.lbl_turn')) ?>
                                </label>
                                <select class="reserva-input" id="rsv-hora" name="hora_visita" required>
                                    <option value=""><?= htmlspecialchars(t('reserva.turn_placeholder')) ?></option>
                                    <option value="10:00"><?= htmlspecialchars(t('reserva.turn_morning')) ?></option>
                                    <option value="12:00"><?= htmlspecialchars(t('reserva.turn_lunch')) ?></option>
                                    <option value="17:00"><?= htmlspecialchars(t('reserva.turn_afternoon')) ?></option>
                                    <option value="19:00"><?= htmlspecialchars(t('reserva.turn_sunset')) ?></option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="reserva-label">
                                    <i class="bi bi-list-stars"></i> <?= htmlspecialchars(t('reserva.lbl_type')) ?>
                                </label>
                                <div class="reserva-radios">
                                    <label class="reserva-radio">
                                        <input type="radio" name="tipo_visita" value="completa" checked>
                                        <div>
                                            <strong><?= htmlspecialchars(t('reserva.type_full')) ?></strong>
                                            <span><?= htmlspecialchars(t('reserva.type_full_desc')) ?></span>
                                        </div>
                                    </label>
                                    <label class="reserva-radio">
                                        <input type="radio" name="tipo_visita" value="almazara">
                                        <div>
                                            <strong><?= htmlspecialchars(t('reserva.type_only_mill')) ?></strong>
                                            <span><?= htmlspecialchars(t('reserva.type_only_mill_desc')) ?></span>
                                        </div>
                                    </label>
                                    <label class="reserva-radio">
                                        <input type="radio" name="tipo_visita" value="cata">
                                        <div>
                                            <strong><?= htmlspecialchars(t('reserva.type_only_tasting')) ?></strong>
                                            <span><?= htmlspecialchars(t('reserva.type_only_tasting_desc')) ?></span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="reserva-label" for="rsv-comentarios">
                                    <i class="bi bi-chat-left-text"></i> <?= htmlspecialchars(t('reserva.lbl_comments')) ?>
                                    <small class="opcional"><?= htmlspecialchars(t('reserva.opt_short')) ?></small>
                                </label>
                                <textarea class="reserva-input" id="rsv-comentarios" name="comentarios"
                                          rows="2" maxlength="500"
                                          placeholder="<?= htmlspecialchars(t('reserva.placeholder_comments')) ?>"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn-gold btn-reservar-submit" id="btn-rsv-submit">
                            <i class="bi bi-calendar-check"></i> <?= htmlspecialchars(t('reserva.btn_submit')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 para la confirmación post-reserva -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // ─── Reserva de visita: validación + fetch a la API ──────────────────
    // Diccionario inyectado desde PHP — mantiene el JS agnóstico al idioma.
    const I18N_RESERVA = <?= json_encode([
        'sending'           => t('reserva.sending'),
        'btn_submit'        => t('reserva.btn_submit'),
        'warn_title'        => t('reserva.warn_title'),
        'warn_text'         => t('reserva.warn_text'),
        'error_title'       => t('reserva.error_title'),
        'success_title'     => t('reserva.success_title'),
        'success_intro'     => t('reserva.success_intro'),
        'success_save'      => t('reserva.success_save'),
        'success_btn'       => t('reserva.success_btn'),
        'error_conn_title'  => t('reserva.error_conn_title'),
        'error_conn_text'   => t('reserva.error_conn_text'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-reserva-visita');
        const btn  = document.getElementById('btn-rsv-submit');
        const modalEl = document.getElementById('modalReservaVisita');
        if (!form) return;

        // Bloquear lunes en el date picker (validación cliente; el servidor
        // tiene la última palabra). El usuario verá feedback inmediato.
        const inputFecha = document.getElementById('rsv-fecha');
        inputFecha.addEventListener('change', () => {
            const dia = new Date(inputFecha.value + 'T00:00').getDay(); // 1 = lunes
            if (dia === 1) {
                Swal.fire({
                    icon: 'warning',
                    title: I18N_RESERVA.warn_title,
                    text:  I18N_RESERVA.warn_text,
                });
                inputFecha.value = '';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Recolectar campos en un objeto plano (compatible con la API)
            const data = Object.fromEntries(new FormData(form).entries());
            data.num_personas = parseInt(data.num_personas, 10);

            const txtOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + I18N_RESERVA.sending;

            try {
                const resp = await fetch('api/reservar_visita.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(data)
                });
                const out = await resp.json();

                if (!resp.ok || out.error) {
                    Swal.fire({
                        icon:  'error',
                        title: I18N_RESERVA.error_title,
                        text:  out.mensaje || `Error ${resp.status}`,
                    });
                    return;
                }

                // Cerrar modal y mostrar confirmación con número de reserva
                bootstrap.Modal.getInstance(modalEl)?.hide();

                Swal.fire({
                    icon:  'success',
                    title: I18N_RESERVA.success_title,
                    html:  `<div style="text-align:left; line-height:1.6;">
                              <p>${I18N_RESERVA.success_intro}</p>
                              <p style="font-size:1.4rem; font-weight:700; color:#B8962E;
                                        font-family: 'Playfair Display', serif; margin:.5rem 0;">
                                ${out.codigo}
                              </p>
                              <p>${out.mensaje}</p>
                              <small style="color:#888;">${I18N_RESERVA.success_save}</small>
                            </div>`,
                    confirmButtonText: I18N_RESERVA.success_btn,
                    confirmButtonColor: '#2C4C3B',
                });

                form.reset();

            } catch (err) {
                console.error(err);
                Swal.fire({
                    icon:  'error',
                    title: I18N_RESERVA.error_conn_title,
                    text:  I18N_RESERVA.error_conn_text,
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = txtOriginal;
            }
        });
    });
    </script>

    <?php require_once 'includes/footer.php'; ?>
