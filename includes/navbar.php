<?php
// Navbar global. Los enlaces ancla (#inicio, etc.) solo existen en index.php,
// así que desde otras páginas se prefijan con "index.php". Los botones de
// carrito y login dependen del offcanvas/modal que vive en footer.php — si la
// página no incluye footer.php (calculadora), degradan a links directos.
//
// i18n: el motor se carga en includes/header.php, así que t() y lang_actual()
// están disponibles aquí. Si alguien incluye este navbar SIN haber pasado
// por header.php, fallback a require_once defensivo.

if (!function_exists('t')) {
    require_once __DIR__ . '/i18n.php';
}

$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$enHome       = ($paginaActual === 'index.php' || $paginaActual === '');
$anclaHome    = $enHome ? '' : 'index.php';

// Páginas que incluyen footer.php (= ofrecen offcanvas + modal de login)
$tieneModalesGlobales = in_array($paginaActual, ['index.php', 'tienda.php'], true);
?>
<header>
        <nav class="navbar-custom" id="navbar-principal" role="navigation" aria-label="<?= htmlspecialchars(t('nav.aria_principal')) ?>">
            <div class="container">
                <div class="navbar-inner">

                    <!-- Logo textual: evita un asset extra y siempre se ve nítido. -->
                    <a href="<?= $anclaHome ?>#inicio" class="navbar-brand-custom"
                        aria-label="<?= htmlspecialchars(t('nav.brand_aria')) ?>" id="navbar-logo">
                        <span class="brand-icon" aria-hidden="true">🫒</span>
                        San Juan <span class="brand-accent">Bautista</span>
                    </a>

                    <!-- Hamburguesa de móvil: las 3 barras animan a "X" al abrir -->
                    <button class="navbar-toggler-custom d-lg-none" type="button" aria-controls="navbarNav"
                        aria-expanded="false" aria-label="<?= htmlspecialchars(t('nav.toggle_aria')) ?>" id="btn-menu-toggle">
                        <span class="toggler-bar" aria-hidden="true"></span>
                        <span class="toggler-bar" aria-hidden="true"></span>
                        <span class="toggler-bar" aria-hidden="true"></span>
                    </button>

                    <!-- Enlaces de navegación -->
                    <div class="navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav" role="menubar">
                            <li class="nav-item" role="none">
                                <a class="nav-link<?= $enHome ? ' active' : '' ?>" href="<?= $anclaHome ?>#inicio" role="menuitem"<?= $enHome ? ' aria-current="page"' : '' ?>
                                    id="nav-inicio"><?= htmlspecialchars(t('nav.inicio')) ?></a>
                            </li>
                            <li class="nav-item" role="none">
                                <a class="nav-link" href="<?= $anclaHome ?>#esencia" role="menuitem"
                                    id="nav-esencia"><?= htmlspecialchars(t('nav.esencia')) ?></a>
                            </li>
                            <li class="nav-item" role="none">
                                <a class="nav-link" href="<?= $anclaHome ?>#productos" role="menuitem"
                                    id="nav-productos"><?= htmlspecialchars(t('nav.productos')) ?></a>
                            </li>
                            <li class="nav-item" role="none">
                                <!-- #contacto vive en el footer global, así que solo funciona si lo incluye la página -->
                                <a class="nav-link" href="<?= $tieneModalesGlobales ? '#contacto' : 'index.php#contacto' ?>" role="menuitem"
                                    id="nav-contacto"><?= htmlspecialchars(t('nav.contacto')) ?></a>
                            </li>
                            <li class="nav-item" role="none">
                                <a class="nav-link<?= in_array($paginaActual, ['noticias.php','noticia.php'], true) ? ' active' : '' ?>" href="noticias.php" role="menuitem"
                                    id="nav-noticias">
                                    <i class="bi bi-megaphone" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.noticias')) ?>
                                </a>
                            </li>
                            <li class="nav-item" role="none">
                                <a class="nav-link<?= $paginaActual === 'calendario.php' ? ' active' : '' ?>" href="calendario.php" role="menuitem"
                                    id="nav-calendario">
                                    <i class="bi bi-calendar3" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.calendario')) ?>
                                </a>
                            </li>
                            <li class="nav-item" role="none">
                                <a class="nav-link<?= $paginaActual === 'calculadora.php' ? ' active' : '' ?>" href="calculadora.php" role="menuitem"
                                    id="nav-calculadora">
                                    <i class="bi bi-calculator" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.calculadora')) ?>
                                </a>
                            </li>
                            <li class="nav-item nav-item-cta" role="none">
                                <a class="nav-link nav-cta<?= $paginaActual === 'tienda.php' ? ' active' : '' ?>" href="tienda.php" role="menuitem" id="nav-cta-tienda">
                                    <i class="bi bi-shop" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.tienda')) ?>
                                </a>
                            </li>
                            <li class="nav-item" role="none">
                                <?php if ($tieneModalesGlobales): ?>
                                    <a class="nav-link nav-cta" href="#" role="menuitem" id="nav-cta-carrito"
                                        data-bs-toggle="offcanvas" data-bs-target="#carritoPanel"
                                        style="background: var(--color-accent); color: var(--color-text);">
                                        <i class="bi bi-bag" aria-hidden="true"></i>
                                        <span id="contador-carrito-nav" class="badge rounded-pill bg-dark" style="font-size: 0.65rem; vertical-align: top; margin-left: 2px;"><?= $totalCarrito ?></span>
                                    </a>
                                <?php else: ?>
                                    <!-- Sin offcanvas en esta página: vamos a la tienda -->
                                    <a class="nav-link nav-cta" href="tienda.php" role="menuitem" id="nav-cta-carrito"
                                        title="<?= htmlspecialchars(t('nav.cart_title')) ?>"
                                        style="background: var(--color-accent); color: var(--color-text);">
                                        <i class="bi bi-bag" aria-hidden="true"></i>
                                        <span id="contador-carrito-nav" class="badge rounded-pill bg-dark" style="font-size: 0.65rem; vertical-align: top; margin-left: 2px;"><?= $totalCarrito ?></span>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <?php if ($estaLogueado): ?>
                                <?php if ($_SESSION['rol'] === 'admin'): ?>
                                    <li class="nav-item" role="none">
                                        <a class="nav-link nav-login" href="admin/index.php" role="menuitem" id="nav-admin-panel">
                                            <i class="bi bi-shield-lock" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.admin')) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($_SESSION['rol'] === 'socio'): ?>
                                    <li class="nav-item" role="none">
                                        <a class="nav-link<?= $paginaActual === 'panel_socio.php' ? ' active' : '' ?>"
                                           href="panel_socio.php" role="menuitem" id="nav-mi-panel"
                                           title="<?= htmlspecialchars(t('nav.mi_panel_title')) ?>">
                                            <i class="bi bi-house-heart" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.mi_panel')) ?>
                                        </a>
                                    </li>
                                    <li class="nav-item" role="none">
                                        <a class="nav-link<?= $paginaActual === 'mis_entregas.php' ? ' active' : '' ?>"
                                           href="mis_entregas.php" role="menuitem" id="nav-mis-entregas"
                                           title="<?= htmlspecialchars(t('nav.mis_entregas_title')) ?>">
                                            <i class="bi bi-clipboard-data" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.mis_entregas')) ?>
                                        </a>
                                    </li>
                                    <li class="nav-item" role="none">
                                        <a class="nav-link<?= $paginaActual === 'votaciones.php' ? ' active' : '' ?>"
                                           href="votaciones.php" role="menuitem" id="nav-votaciones"
                                           title="<?= htmlspecialchars(t('nav.votaciones_title')) ?>">
                                            <i class="bi bi-megaphone" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.votaciones')) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li class="nav-item" role="none">
                                    <span class="nav-link" style="cursor:default; opacity:0.85;" id="nav-nombre-usuario">
                                        <i class="bi bi-person-check-fill" aria-hidden="true"></i>
                                        <?= htmlspecialchars($_SESSION['nombre']) ?>
                                    </span>
                                </li>
                                <li class="nav-item" role="none">
                                    <a class="nav-link nav-login" href="auth/logout.php" role="menuitem" id="nav-cerrar-sesion"
                                        title="<?= htmlspecialchars(tf('nav.salir_title', $_SESSION['nombre'])) ?>">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.salir')) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item" role="none">
                                    <?php if ($tieneModalesGlobales): ?>
                                        <a class="nav-link nav-login" href="#" role="menuitem" id="nav-login-socios"
                                            data-bs-toggle="modal" data-bs-target="#loginModal">
                                            <i class="bi bi-person-circle" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.socios')) ?>
                                        </a>
                                    <?php else: ?>
                                        <!-- Sin modal local: a la home, que sí lo tiene -->
                                        <a class="nav-link nav-login" href="index.php#login" role="menuitem" id="nav-login-socios"
                                            title="<?= htmlspecialchars(t('nav.socios_title')) ?>">
                                            <i class="bi bi-person-circle" aria-hidden="true"></i> <?= htmlspecialchars(t('nav.socios')) ?>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>

                            <!-- ─── Selector de idioma ─── -->
                            <li class="nav-item nav-item-lang" role="none">
                                <div class="nav-lang-switch" role="group" aria-label="<?= htmlspecialchars(t('nav.lang_aria')) ?>">
                                    <a href="<?= htmlspecialchars(lang_url('es')) ?>"
                                       class="lang-opt<?= lang_actual() === 'es' ? ' active' : '' ?>"
                                       hreflang="es" lang="es"
                                       title="<?= htmlspecialchars(t('nav.lang_es_title')) ?>"
                                       aria-current="<?= lang_actual() === 'es' ? 'true' : 'false' ?>">
                                        <span class="lang-flag" aria-hidden="true">🇪🇸</span>
                                        <span class="lang-code">ES</span>
                                    </a>
                                    <a href="<?= htmlspecialchars(lang_url('en')) ?>"
                                       class="lang-opt<?= lang_actual() === 'en' ? ' active' : '' ?>"
                                       hreflang="en" lang="en"
                                       title="<?= htmlspecialchars(t('nav.lang_en_title')) ?>"
                                       aria-current="<?= lang_actual() === 'en' ? 'true' : 'false' ?>">
                                        <span class="lang-flag" aria-hidden="true">🇬🇧</span>
                                        <span class="lang-code">EN</span>
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </nav>
    </header>

    <script>
    // Toggle del menú móvil. Vive en el navbar (no en el footer) para que
    // funcione también en páginas que NO incluyen footer.php (calculadora).
    // Marcamos el botón con dataset.toggleBound para evitar registrar el
    // handler dos veces si una página incluye además el script del footer.
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            var menuToggle  = document.getElementById('btn-menu-toggle');
            var navCollapse = document.getElementById('navbarNav');
            if (!menuToggle || !navCollapse || menuToggle.dataset.toggleBound) return;
            menuToggle.dataset.toggleBound = '1';

            menuToggle.addEventListener('click', function () {
                var isOpen = navCollapse.classList.toggle('show');
                menuToggle.classList.toggle('active', isOpen);
                menuToggle.setAttribute('aria-expanded', isOpen);
            });

            // En móvil, cerramos el menú al pulsar un enlace.
            navCollapse.querySelectorAll('.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth < 992) {
                        navCollapse.classList.remove('show');
                        menuToggle.classList.remove('active');
                        menuToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        });
    })();
    </script>
