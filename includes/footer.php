<?php
// Anclas internas: #contacto vive en este mismo footer; el resto solo existe
// en index.php, así que desde otras páginas se prefijan con "index.php".
$pagActFooter = basename($_SERVER['PHP_SELF'] ?? '');
$enHomeFooter = ($pagActFooter === 'index.php' || $pagActFooter === '');
$anclaHomeFt  = $enHomeFooter ? '' : 'index.php';

// i18n — defensivo: si una página no pasó por header.php, lo cargamos aquí.
if (!function_exists('t')) {
    require_once __DIR__ . '/i18n.php';
}

// Estados del modal login/registro/recuperar. index.php los rellena, otras
// páginas no, así que las inicializamos para evitar "Undefined variable" en PHP 8.
$loginError     = $loginError     ?? '';
$loginExito     = $loginExito     ?? '';
$registroError  = $registroError  ?? '';
$registroExito  = $registroExito  ?? '';
$recuperarError = $recuperarError ?? '';
?>
<footer class="footer" id="contacto" role="contentinfo" aria-label="<?= htmlspecialchars(t('footer.aria')) ?>">
        <div class="container">
            <div class="row g-5">

                <!-- Col 1: Marca, descripción y redes sociales -->
                <div class="col-lg-4 reveal">
                    <!-- href="#" scrollea al top de la página actual sin depender de #inicio -->
                    <a href="#" class="footer-brand" aria-label="<?= htmlspecialchars(t('footer.brand_aria')) ?>" id="footer-brand-link">
                        <span class="brand-icon" aria-hidden="true">🫒</span>
                        San Juan <span class="brand-accent">Bautista</span>
                    </a>
                    <p class="footer-desc">
                        <?= htmlspecialchars(t('footer.desc')) ?>
                    </p>
                    <!-- Redes sociales con iconos y aria-labels -->
                    <div class="footer-social" aria-label="<?= htmlspecialchars(t('footer.social_aria')) ?>">
                        <a href="#" aria-label="<?= htmlspecialchars(t('footer.social_facebook')) ?>" class="social-link" id="social-facebook">
                            <i class="bi bi-facebook" aria-hidden="true"></i>
                        </a>
                        <a href="#" aria-label="<?= htmlspecialchars(t('footer.social_instagram')) ?>" class="social-link" id="social-instagram">
                            <i class="bi bi-instagram" aria-hidden="true"></i>
                        </a>
                        <a href="#" aria-label="<?= htmlspecialchars(t('footer.social_twitter')) ?>" class="social-link" id="social-twitter">
                            <i class="bi bi-twitter-x" aria-hidden="true"></i>
                        </a>
                        <a href="#" aria-label="<?= htmlspecialchars(t('footer.social_youtube')) ?>" class="social-link" id="social-youtube">
                            <i class="bi bi-youtube" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Enlaces rápidos -->
                <div class="col-sm-6 col-lg-2 offset-lg-2 reveal reveal-delay-1">
                    <h3 class="footer-heading"><?= htmlspecialchars(t('footer.h_navigation')) ?></h3>
                    <ul class="footer-links">
                        <li><a href="<?= $anclaHomeFt ?>#inicio"    id="footer-inicio"><?= htmlspecialchars(t('footer.l_inicio')) ?></a></li>
                        <li><a href="<?= $anclaHomeFt ?>#esencia"   id="footer-esencia"><?= htmlspecialchars(t('footer.l_esencia')) ?></a></li>
                        <li><a href="<?= $anclaHomeFt ?>#productos" id="footer-productos"><?= htmlspecialchars(t('footer.l_productos')) ?></a></li>
                        <li><a href="<?= $anclaHomeFt ?>#proceso"   id="footer-proceso"><?= htmlspecialchars(t('footer.l_proceso')) ?></a></li>
                        <li><a href="<?= $anclaHomeFt ?>#visita"    id="footer-visita"><?= htmlspecialchars(t('footer.l_visita')) ?></a></li>
                        <li><a href="calculadora.php" id="footer-calculadora"><?= htmlspecialchars(t('footer.l_calculadora')) ?></a></li>
                    </ul>
                </div>

                <!-- Col 3: Información de contacto -->
                <div class="col-sm-6 col-lg-4 reveal reveal-delay-2">
                    <h3 class="footer-heading"><?= htmlspecialchars(t('footer.h_contacto')) ?></h3>
                    <ul class="footer-contact">
                        <li>
                            <i class="bi bi-geo-alt-fill footer-contact-icon" aria-hidden="true"></i>
                            <span><?= t('footer.address') /* contiene <br> intencional */ ?></span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill footer-contact-icon" aria-hidden="true"></i>
                            <a href="tel:+34924000000" class="footer-contact-link" id="footer-telefono">
                                +34 924 00 00 00
                            </a>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill footer-contact-icon" aria-hidden="true"></i>
                            <a href="mailto:info@coopsanjuanbautista.es" class="footer-contact-link"
                                id="footer-email">
                                info@coopsanjuanbautista.es
                            </a>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill footer-contact-icon" aria-hidden="true"></i>
                            <span><?= htmlspecialchars(t('footer.hours')) ?></span>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Barra inferior: Copyright + Enlaces Legales -->
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p class="footer-copyright">
                        <?= tf('footer.copyright', (int) date('Y')) /* contiene &copy; intencional */ ?>
                    </p>
                    <nav class="footer-legal" aria-label="<?= htmlspecialchars(t('footer.legal_aria')) ?>">
                        <a href="#" id="footer-privacidad"><?= htmlspecialchars(t('footer.legal_privacy')) ?></a>
                        <a href="#" id="footer-aviso-legal"><?= htmlspecialchars(t('footer.legal_notice')) ?></a>
                        <a href="#" id="footer-cookies"><?= htmlspecialchars(t('footer.legal_cookies')) ?></a>
                    </nav>
                </div>
            </div>

        </div>
    </footer>

    <!-- Botón "volver arriba": aparece al hacer scroll -->
    <a href="#" class="back-to-top" id="btn-back-to-top" aria-label="<?= htmlspecialchars(t('footer.back_to_top')) ?>">
        <i class="bi bi-chevron-up" aria-hidden="true"></i>
    </a>

    <!-- Modal multivista: login / registro / recuperar contraseña, alternados con JS. -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content login-modal-content">
                <!-- Cabecera del modal -->
                <div class="modal-header login-modal-header">
                    <div class="login-modal-brand">
                        <span class="brand-icon" aria-hidden="true">🫒</span>
                        <h2 class="modal-title" id="loginModalLabel"><?= htmlspecialchars(t('modal.login.title')) ?></h2>
                    </div>
                    <button type="button" class="btn-close-modal" data-bs-dismiss="modal"
                        aria-label="<?= htmlspecialchars(t('modal.login.close_aria')) ?>" id="btn-close-login">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Vista 1: login -->
                <div class="modal-body login-modal-body" id="vista-login">
                    <p class="login-desc">
                        <?= htmlspecialchars(t('modal.login.desc')) ?>
                    </p>

                    <?php if ($loginExito): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert"
                            style="font-size: 0.85rem; border-radius: 8px;">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            <?= htmlspecialchars($loginExito) ?>
                        </div>
                    <?php endif; ?>

                    <form id="form-login-socios" action="auth/login.php" method="POST" novalidate>
                        <?php if ($loginError): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert"
                                style="font-size: 0.85rem; border-radius: 8px;">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <?= htmlspecialchars($loginError) ?>
                            </div>
                        <?php endif; ?>

                        <div class="login-field">
                            <label for="login-email" class="login-label">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <?= htmlspecialchars(t('modal.login.lbl_email')) ?>
                            </label>
                            <input type="email" class="login-input" id="login-email" name="email"
                                placeholder="<?= htmlspecialchars(t('modal.login.placeholder_email')) ?>" required
                                autocomplete="email" aria-required="true">
                        </div>

                        <div class="login-field">
                            <label for="login-password" class="login-label">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <?= htmlspecialchars(t('modal.login.lbl_password')) ?>
                            </label>
                            <div class="login-password-wrapper">
                                <input type="password" class="login-input" id="login-password" name="password"
                                    placeholder="<?= htmlspecialchars(t('modal.login.placeholder_password')) ?>" required
                                    autocomplete="current-password" aria-required="true">
                                <button type="button" class="login-toggle-password" id="btn-toggle-password"
                                    aria-label="<?= htmlspecialchars(t('modal.login.show_password')) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="login-options">
                            <label class="login-remember" for="login-remember">
                                <input type="checkbox" id="login-remember" name="remember">
                                <span><?= htmlspecialchars(t('modal.login.remember')) ?></span>
                            </label>
                            <a href="#" class="login-forgot" id="link-forgot-password"
                               onclick="mostrarVista('vista-recuperar'); return false;">
                                <?= htmlspecialchars(t('modal.login.forgot')) ?>
                            </a>
                        </div>

                        <button type="submit" class="btn-gold login-submit" id="btn-login-submit">
                            <?= htmlspecialchars(t('modal.login.submit')) ?>
                            <i class="bi bi-arrow-right btn-icon" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <!-- Vista 2: registro -->
                <div class="modal-body login-modal-body" id="vista-registro" style="display: none;">
                    <p class="login-desc">
                        <?= htmlspecialchars(t('modal.register.desc')) ?>
                    </p>

                    <form id="form-registro" action="auth/registro.php" method="POST" novalidate>
                        <?php if ($registroError): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert"
                                style="font-size: 0.85rem; border-radius: 8px;">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <?= htmlspecialchars($registroError) ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2 mb-0">
                            <div class="col-6">
                                <div class="login-field">
                                    <label for="reg-nombre" class="login-label">
                                        <i class="bi bi-person" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.register.lbl_name')) ?>
                                    </label>
                                    <input type="text" class="login-input" id="reg-nombre" name="nombre"
                                        placeholder="<?= htmlspecialchars(t('modal.register.placeholder_name')) ?>" required autocomplete="given-name">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="login-field">
                                    <label for="reg-apellidos" class="login-label">
                                        <i class="bi bi-person" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.register.lbl_surname')) ?>
                                    </label>
                                    <input type="text" class="login-input" id="reg-apellidos" name="apellidos"
                                        placeholder="<?= htmlspecialchars(t('modal.register.placeholder_surname')) ?>" autocomplete="family-name">
                                </div>
                            </div>
                        </div>

                        <div class="login-field">
                            <label for="reg-email" class="login-label">
                                <i class="bi bi-envelope" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.login.lbl_email')) ?>
                            </label>
                            <input type="email" class="login-input" id="reg-email" name="email"
                                placeholder="<?= htmlspecialchars(t('modal.login.placeholder_email')) ?>" required autocomplete="email">
                        </div>

                        <div class="login-field">
                            <label for="reg-telefono" class="login-label">
                                <i class="bi bi-telephone" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.register.lbl_phone')) ?>
                                <small style="font-weight:400; color:var(--color-text-muted);"><?= htmlspecialchars(t('modal.register.opt_short')) ?></small>
                            </label>
                            <input type="tel" class="login-input" id="reg-telefono" name="telefono"
                                placeholder="<?= htmlspecialchars(t('modal.register.placeholder_phone')) ?>" autocomplete="tel">
                        </div>

                        <div class="login-field">
                            <label for="reg-password" class="login-label">
                                <i class="bi bi-lock" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.register.lbl_password')) ?>
                            </label>
                            <input type="password" class="login-input" id="reg-password" name="password"
                                placeholder="<?= htmlspecialchars(t('modal.register.placeholder_pwd')) ?>" required autocomplete="new-password">
                        </div>

                        <div class="login-field">
                            <label for="reg-password2" class="login-label">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.register.lbl_password2')) ?>
                            </label>
                            <input type="password" class="login-input" id="reg-password2" name="password2"
                                placeholder="<?= htmlspecialchars(t('modal.register.placeholder_pwd2')) ?>" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn-gold login-submit" id="btn-registro-submit"
                            style="margin-top: 0.5rem;">
                            <?= htmlspecialchars(t('modal.register.submit')) ?>
                            <i class="bi bi-person-plus btn-icon" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <!-- Vista 3: recuperar contraseña -->
                <div class="modal-body login-modal-body" id="vista-recuperar" style="display: none;">
                    <p class="login-desc">
                        <?= htmlspecialchars(t('modal.recover.desc')) ?>
                    </p>

                    <form id="form-recuperar" action="auth/recuperar_password.php" method="POST" novalidate>
                        <?php if ($recuperarError): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert"
                                style="font-size: 0.85rem; border-radius: 8px;">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <?= htmlspecialchars($recuperarError) ?>
                            </div>
                        <?php endif; ?>

                        <div class="login-field">
                            <label for="rec-email" class="login-label">
                                <i class="bi bi-envelope" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.login.lbl_email')) ?>
                            </label>
                            <input type="email" class="login-input" id="rec-email" name="email"
                                placeholder="<?= htmlspecialchars(t('modal.login.placeholder_email')) ?>" required autocomplete="email">
                        </div>

                        <div class="login-field">
                            <label for="rec-password" class="login-label">
                                <i class="bi bi-key" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.recover.lbl_pwd')) ?>
                            </label>
                            <input type="password" class="login-input" id="rec-password" name="password"
                                placeholder="<?= htmlspecialchars(t('modal.register.placeholder_pwd')) ?>" required autocomplete="new-password">
                        </div>

                        <div class="login-field">
                            <label for="rec-password2" class="login-label">
                                <i class="bi bi-key-fill" aria-hidden="true"></i> <?= htmlspecialchars(t('modal.recover.lbl_pwd2')) ?>
                            </label>
                            <input type="password" class="login-input" id="rec-password2" name="password2"
                                placeholder="<?= htmlspecialchars(t('modal.register.placeholder_pwd2')) ?>" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn-gold login-submit" id="btn-recuperar-submit">
                            <?= htmlspecialchars(t('modal.recover.submit')) ?>
                            <i class="bi bi-shield-check btn-icon" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <!-- Pie del modal: distinto según la vista activa -->
                <div class="modal-footer login-modal-footer" id="modal-footer-login">
                    <p><?= htmlspecialchars(t('modal.login.no_account')) ?>
                        <a href="#" id="link-ir-registro"
                           onclick="mostrarVista('vista-registro'); return false;"><?= htmlspecialchars(t('modal.login.register_here')) ?></a>
                    </p>
                </div>
                <div class="modal-footer login-modal-footer" id="modal-footer-registro" style="display: none;">
                    <p><?= htmlspecialchars(t('modal.login.have_account')) ?>
                        <a href="#" id="link-ir-login-desde-registro"
                           onclick="mostrarVista('vista-login'); return false;"><?= htmlspecialchars(t('modal.login.login_here')) ?></a>
                    </p>
                </div>
                <div class="modal-footer login-modal-footer" id="modal-footer-recuperar" style="display: none;">
                    <p><?= htmlspecialchars(t('modal.login.remembered')) ?>
                        <a href="#" id="link-ir-login-desde-recuperar"
                           onclick="mostrarVista('vista-login'); return false;"><?= htmlspecialchars(t('modal.login.back_to_login')) ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // i18n del modal multivista — inyectado desde PHP, mantiene el JS agnóstico.
    const I18N_MODAL = <?= json_encode([
        'login'    => t('modal.login.title'),
        'register' => t('modal.login.title_register'),
        'recover'  => t('modal.login.title_recover'),
        'verifying'=> t('modal.login.verifying'),
        'show_pwd' => t('modal.login.show_password'),
        'hide_pwd' => t('modal.login.hide_password'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    // Cambia entre las vistas login / registro / recuperar dentro del modal,
    // ajustando título y pie correspondiente.
    function mostrarVista(vistaId) {
        document.getElementById('vista-login').style.display = 'none';
        document.getElementById('vista-registro').style.display = 'none';
        document.getElementById('vista-recuperar').style.display = 'none';

        document.getElementById('modal-footer-login').style.display = 'none';
        document.getElementById('modal-footer-registro').style.display = 'none';
        document.getElementById('modal-footer-recuperar').style.display = 'none';

        // Mostrar la vista seleccionada
        document.getElementById(vistaId).style.display = 'block';

        // Actualizar título y footer según la vista
        const titulo = document.getElementById('loginModalLabel');
        switch (vistaId) {
            case 'vista-login':
                titulo.textContent = I18N_MODAL.login;
                document.getElementById('modal-footer-login').style.display = 'block';
                break;
            case 'vista-registro':
                titulo.textContent = I18N_MODAL.register;
                document.getElementById('modal-footer-registro').style.display = 'block';
                break;
            case 'vista-recuperar':
                titulo.textContent = I18N_MODAL.recover;
                document.getElementById('modal-footer-recuperar').style.display = 'block';
                break;
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <script>
    // Scripts globales: glassmorphism al scrollear, menú móvil, scroll-reveal,
    // active nav link, smooth scroll, contadores y carrito.
    document.addEventListener('DOMContentLoaded', () => {
        'use strict';

        // 1. Navbar: añade .scrolled al pasar el umbral para el efecto glassmorphism
        const navbar = document.getElementById('navbar-principal');
        const backToTop = document.getElementById('btn-back-to-top');
        const SCROLL_THRESHOLD = 80;

        function handleNavbarScroll() {
            const scrolled = window.scrollY > SCROLL_THRESHOLD;
            navbar.classList.toggle('scrolled', scrolled);
            if (backToTop) {
                backToTop.classList.toggle('visible', window.scrollY > 600);
            }
        }

        // Ejecutar al cargar por si la página ya viene scrolleada
        handleNavbarScroll();
        window.addEventListener('scroll', handleNavbarScroll, { passive: true });


        // 2. Menú móvil: hamburguesa → X y cerrado al elegir enlace.
        //    El toggle ya lo registra navbar.php (para páginas sin footer como
        //    calculadora). Aquí solo enganchamos si nadie lo ha hecho aún.
        const menuToggle = document.getElementById('btn-menu-toggle');
        const navCollapse = document.getElementById('navbarNav');

        if (menuToggle && navCollapse && !menuToggle.dataset.toggleBound) {
            menuToggle.dataset.toggleBound = '1';
            menuToggle.addEventListener('click', () => {
                const isOpen = navCollapse.classList.toggle('show');
                menuToggle.classList.toggle('active', isOpen);
                menuToggle.setAttribute('aria-expanded', isOpen);
            });

            document.querySelectorAll('#navbarNav .nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 992) {
                        navCollapse.classList.remove('show');
                        menuToggle.classList.remove('active');
                        menuToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        }


        // 3. Scroll-reveal con IntersectionObserver (no engancha al scroll → más eficiente)
        const revealElements = document.querySelectorAll('.reveal');

        if ('IntersectionObserver' in window) {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        revealObserver.unobserve(entry.target);    // Solo una vez
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

            revealElements.forEach(el => revealObserver.observe(el));
        } else {
            // Fallback para navegadores sin IntersectionObserver
            revealElements.forEach(el => el.classList.add('revealed'));
        }


        // 4. Active link: marca .active el enlace de la sección visible
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.navbar-custom .nav-link:not(.nav-cta)');

        function updateActiveLink() {
            const scrollPos = window.scrollY + 200;

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');

                if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === '#' + sectionId) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }

        window.addEventListener('scroll', updateActiveLink, { passive: true });


        // 5. Smooth scroll a anclas, restando la altura de la navbar fija
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const targetEl = document.querySelector(targetId);
                if (targetEl) {
                    e.preventDefault();
                    const navbarHeight = navbar.offsetHeight;
                    const targetPos = targetEl.getBoundingClientRect().top + window.scrollY - navbarHeight;

                    window.scrollTo({
                        top: targetPos,
                        behavior: 'smooth'
                    });
                }
            });
        });


        // 6. Contadores animados con requestAnimationFrame al entrar en viewport
        const statNumbers = document.querySelectorAll('.stat-number[data-target]');

        function animateCounter(el) {
            const target = parseInt(el.getAttribute('data-target'));
            const duration = 2000;
            const startTime = performance.now();

            function updateCount(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                const eased = 1 - Math.pow(1 - progress, 3);    // ease-out cubic
                const current = Math.floor(eased * target);

                el.textContent = current.toLocaleString(I18N_CART.locale);

                if (progress < 1) {
                    requestAnimationFrame(updateCount);
                } else {
                    el.textContent = target.toLocaleString(I18N_CART.locale);
                }
            }

            requestAnimationFrame(updateCount);
        }

        if ('IntersectionObserver' in window && statNumbers.length > 0) {
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            statNumbers.forEach(el => counterObserver.observe(el));
        }


        // 7. Toggle "ver/ocultar" la contraseña en el modal de login
        const togglePasswordBtn = document.getElementById('btn-toggle-password');
        const passwordInput = document.getElementById('login-password');

        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';

                const icon = togglePasswordBtn.querySelector('i');
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);

                togglePasswordBtn.setAttribute('aria-label',
                    isPassword ? I18N_MODAL.hide_pwd : I18N_MODAL.show_pwd
                );
            });
        }

        // 8. Validación cliente del form de login antes de enviar a auth/login.php
        const loginForm = document.getElementById('form-login-socios');

        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                const email = document.getElementById('login-email');
                const password = document.getElementById('login-password');

                email.classList.remove('login-input-error');
                password.classList.remove('login-input-error');

                let valid = true;

                if (!email.value.trim()) {
                    email.classList.add('login-input-error');
                    valid = false;
                }
                if (!password.value.trim()) {
                    password.classList.add('login-input-error');
                    valid = false;
                }

                if (!valid) {
                    // Prevenir envío solo si hay errores
                    e.preventDefault();
                } else {
                    // Mostrar estado de carga mientras se envía al backend
                    const submitBtn = document.getElementById('btn-login-submit');
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' + I18N_MODAL.verifying;
                    submitBtn.disabled = true;
                    // El formulario se envía normalmente a login.php
                }
            });
        }

        // 9. Auto-abrir el modal en la vista correcta si hay mensajes en sesión
        //    (errores o éxitos del POST anterior) para que el usuario los vea.
        <?php if ($loginError || $loginExito): ?>
        {
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                mostrarVista('vista-login');
                const modal = new bootstrap.Modal(loginModal);
                modal.show();
            }
        }
        <?php endif; ?>

        <?php if ($registroError): ?>
        {
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                mostrarVista('vista-registro');
                const modal = new bootstrap.Modal(loginModal);
                modal.show();
            }
        }
        <?php endif; ?>

        <?php if ($recuperarError): ?>
        {
            const loginModal = document.getElementById('loginModal');
            if (loginModal) {
                mostrarVista('vista-recuperar');
                const modal = new bootstrap.Modal(loginModal);
                modal.show();
            }
        }
        <?php endif; ?>

    });
    </script>

    <!-- Carrito como offcanvas Bootstrap: se rellena por JS al abrir -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="carritoPanel" aria-labelledby="carritoPanelLabel">

        <!-- Cabecera del panel -->
        <div class="offcanvas-header" style="background: var(--color-primary); padding: 1.2rem 1.5rem;">
            <h5 class="offcanvas-title" id="carritoPanelLabel" style="font-family: var(--font-heading); color: var(--color-white); font-size: 1.2rem;">
                <i class="bi bi-bag-fill me-2" aria-hidden="true"></i><?= htmlspecialchars(t('cart.title')) ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="<?= htmlspecialchars(t('cart.close_aria')) ?>"></button>
        </div>

        <!-- Cuerpo: lista de productos -->
        <div class="offcanvas-body" id="carrito-body" style="padding: 0; display: flex; flex-direction: column;">
            <!-- Se rellena dinámicamente con JavaScript -->
            <div id="carrito-items" style="flex: 1; overflow-y: auto; padding: 1rem 1.2rem;"></div>

            <!-- Footer del carrito: total + botón -->
            <div id="carrito-footer" style="border-top: 2px solid rgba(44,76,59,0.08); padding: 1rem 1.2rem; background: var(--color-bg-alt); display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem;">
                    <span style="font-family: var(--font-body); font-weight: 600; font-size: 1rem; color: var(--color-text);"><?= htmlspecialchars(t('cart.total')) ?></span>
                    <span id="carrito-total" style="font-family: var(--font-heading); font-weight: 700; font-size: 1.4rem; color: var(--color-accent-dark);"><?= htmlspecialchars(fmt_precio(0)) ?></span>
                </div>
                <?php if ($estaLogueado): ?>
                    <!-- Botón Stripe Checkout: dispara la creación de la sesión vía AJAX
                         y redirige al usuario a la URL hosteada por Stripe. -->
                    <button type="button" id="btn-checkout-stripe"
                        style="display: block; width: 100%; text-align: center; padding: 0.75rem; background: var(--color-accent); color: var(--color-text); border: none; border-radius: var(--radius-md); font-family: var(--font-body); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; transition: var(--transition-base); cursor: pointer;"
                        onmouseover="this.style.background='var(--color-accent-dark)'; this.style.color='var(--color-white)'" onmouseout="this.style.background='var(--color-accent)'; this.style.color='var(--color-text)'">
                        <i class="bi bi-credit-card-fill me-1" aria-hidden="true"></i>
                        <span id="btn-checkout-stripe-label"><?= htmlspecialchars(t('cart.checkout')) ?></span>
                    </button>
                <?php else: ?>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="offcanvas" style="display: block; text-align: center; padding: 0.75rem; background: var(--color-primary); color: var(--color-white); border-radius: var(--radius-md); text-decoration: none; font-family: var(--font-body); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; transition: var(--transition-base);"
                        onmouseover="this.style.background='var(--color-primary-dark)'" onmouseout="this.style.background='var(--color-primary)'">
                        <i class="bi bi-person-circle me-1" aria-hidden="true"></i> <?= htmlspecialchars(t('cart.login_to_buy')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Carrito AJAX: añadir/quitar productos y renderizado del offcanvas.
    // i18n inyectado desde PHP — todo el JS del carrito habla el idioma actual.
    const I18N_CART = <?= json_encode([
        'adding'        => t('cart.adding'),
        'added'         => t('cart.added'),
        'connection_err'=> t('cart.connection_error'),
        'loading'       => t('cart.loading'),
        'empty_title'   => t('cart.empty_title'),
        'empty_desc'    => t('cart.empty_desc'),
        'load_error'    => t('cart.load_error'),
        'remove_aria'   => t('cart.remove_aria'),
        'locale'        => lang_locale(),
        'eur_prefix'    => lang_actual() === 'en' ? '€' : '',
        'eur_suffix'    => lang_actual() === 'en' ? ''  : ' €',
    ], JSON_UNESCAPED_UNICODE) ?>;

    function fmtPrecioJS(val) {
        return I18N_CART.eur_prefix
             + Number(val).toLocaleString(I18N_CART.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
             + I18N_CART.eur_suffix;
    }

    document.addEventListener('DOMContentLoaded', () => {
        'use strict';

        const contadorNav  = document.getElementById('contador-carrito-nav');
        const carritoItems = document.getElementById('carrito-items');
        const carritoFooter= document.getElementById('carrito-footer');
        const carritoTotal = document.getElementById('carrito-total');
        const carritoPanel = document.getElementById('carritoPanel');

        // Recargar el carrito cada vez que se abre el offcanvas
        if (carritoPanel) {
            carritoPanel.addEventListener('show.bs.offcanvas', () => {
                cargarCarrito();
            });
        }

        // Delegación a document: index.php usa .btn-add-cart-index y tienda.php
        // usa .btn-add-cart. Con un solo handler cubrimos ambas.
        document.addEventListener('click', async (e) => {
            const boton = e.target.closest('.btn-add-cart-index, .btn-add-cart');
            if (!boton || boton.disabled) return;

                boton.disabled = true;
                const idProducto     = boton.getAttribute('data-id');
                const nombreProducto = boton.getAttribute('data-nombre');
                const textoOriginal  = boton.innerHTML;

                boton.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' + I18N_CART.adding;

                try {
                    const resp = await fetch('api/carrito.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ accion: 'añadir', id_producto: parseInt(idProducto) }),
                    });
                    const datos = await resp.json();

                    if (datos.ok) {
                        // Actualizar contador
                        if (contadorNav) contadorNav.textContent = datos.total_items;

                        // Feedback visual
                        boton.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i> ' + I18N_CART.added;
                        boton.style.background = '#2D8B4E';
                        boton.style.color = '#fff';
                        boton.style.borderColor = '#2D8B4E';

                        setTimeout(() => {
                            boton.innerHTML = textoOriginal;
                            boton.style.background = '';
                            boton.style.color = '';
                            boton.style.borderColor = '';
                            boton.disabled = false;
                        }, 1500);
                    } else {
                        alert(datos.mensaje);
                        boton.innerHTML = textoOriginal;
                        boton.disabled = false;
                    }
                } catch (err) {
                    console.error('Error de red:', err);
                    alert(I18N_CART.connection_err);
                    boton.innerHTML = textoOriginal;
                    boton.disabled = false;
                }
        });


        /**
         * Carga el contenido del carrito desde la API y renderiza en el offcanvas.
         */
        async function cargarCarrito() {
            if (!carritoItems) return;

            carritoItems.innerHTML = `<div style="text-align:center; padding:2rem;"><i class="bi bi-hourglass-split" style="font-size:1.5rem; color: var(--color-accent);"></i><p style="margin-top:0.5rem; color: var(--color-text-muted); font-size:0.85rem;">${I18N_CART.loading}</p></div>`;

            try {
                const resp = await fetch('api/carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion: 'obtener' }),
                });
                const datos = await resp.json();

                if (datos.ok && datos.items && datos.items.length > 0) {
                    renderizarCarrito(datos.items, datos.total_importe);
                } else {
                    carritoItems.innerHTML = `
                        <div style="text-align:center; padding:3rem 1rem;">
                            <i class="bi bi-bag" style="font-size:3rem; color: var(--color-accent); opacity:0.3;"></i>
                            <h6 style="font-family: var(--font-heading); margin-top:1rem; color: var(--color-text);">${I18N_CART.empty_title}</h6>
                            <p style="color: var(--color-text-muted); font-size:0.85rem;">${I18N_CART.empty_desc}</p>
                        </div>`;
                    if (carritoFooter) carritoFooter.style.display = 'none';
                }
            } catch (err) {
                carritoItems.innerHTML = `<p style="text-align:center; padding:2rem; color:#C0392B;">${I18N_CART.load_error}</p>`;
            }
        }


        /**
         * Renderiza los items del carrito dentro del offcanvas.
         */
        function renderizarCarrito(items, totalImporte) {
            let html = '';
            items.forEach(item => {
                html += `
                <div style="display:flex; align-items:center; gap:0.8rem; padding:0.8rem 0; border-bottom:1px solid rgba(44,76,59,0.06);">
                    <div style="flex:1;">
                        <p style="font-family:var(--font-body); font-weight:600; font-size:0.9rem; color:var(--color-text); margin:0;">
                            ${item.nombre}
                        </p>
                        <p style="font-size:0.78rem; color:var(--color-text-muted); margin:0.2rem 0 0;">
                            ${item.cantidad} × ${fmtPrecioJS(item.precio)} = <strong>${fmtPrecioJS(item.subtotal)}</strong>
                        </p>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.3rem;">
                        <button type="button" class="btn-carrito-qty" data-accion="restar" data-id="${item.id_producto}"
                            style="width:28px; height:28px; border-radius:50%; border:1px solid rgba(44,76,59,0.15); background:var(--color-white); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:var(--color-text);">
                            <i class="bi bi-dash"></i>
                        </button>
                        <span style="font-weight:600; font-size:0.85rem; min-width:20px; text-align:center;">${item.cantidad}</span>
                        <button type="button" class="btn-carrito-qty" data-accion="sumar" data-id="${item.id_producto}"
                            style="width:28px; height:28px; border-radius:50%; border:1px solid rgba(44,76,59,0.15); background:var(--color-white); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.8rem; color:var(--color-text);">
                            <i class="bi bi-plus"></i>
                        </button>
                        <button type="button" class="btn-carrito-eliminar" data-id="${item.id_producto}"
                            style="width:28px; height:28px; border-radius:50%; border:1px solid rgba(192,57,43,0.2); background:var(--color-white); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.75rem; color:#C0392B; margin-left:0.3rem;"
                            aria-label="${I18N_CART.remove_aria.replace('%s', item.nombre)}">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>`;
            });

            carritoItems.innerHTML = html;
            if (carritoFooter) carritoFooter.style.display = 'block';
            if (carritoTotal) carritoTotal.textContent = fmtPrecioJS(totalImporte);

            // ── Listeners para +/-/eliminar ──
            carritoItems.querySelectorAll('.btn-carrito-qty').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = parseInt(btn.getAttribute('data-id'));
                    const accion = btn.getAttribute('data-accion');

                    // Encontrar la cantidad actual
                    const item = items.find(i => i.id_producto == id);
                    let nuevaCantidad = item ? item.cantidad : 1;
                    nuevaCantidad = accion === 'sumar' ? nuevaCantidad + 1 : nuevaCantidad - 1;

                    if (nuevaCantidad <= 0) {
                        await accionCarrito('eliminar', id);
                    } else {
                        await accionCarrito('actualizar', id, nuevaCantidad);
                    }
                    cargarCarrito();
                });
            });

            carritoItems.querySelectorAll('.btn-carrito-eliminar').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = parseInt(btn.getAttribute('data-id'));
                    await accionCarrito('eliminar', id);
                    cargarCarrito();
                });
            });
        }


        /**
         * Envía una acción al carrito (eliminar/actualizar).
         */
        async function accionCarrito(accion, idProducto, cantidad = 1) {
            try {
                const resp = await fetch('api/carrito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion, id_producto: idProducto, cantidad }),
                });
                const datos = await resp.json();
                if (contadorNav) contadorNav.textContent = datos.total_items || 0;
                return datos;
            } catch (err) {
                console.error(err);
            }
        }


        // ── Botón "Finalizar compra" → Stripe Checkout ───────────────────────
        // Click → POST a core/crear_sesion_stripe.php → recibe URL hosteada
        // por Stripe → redirige al usuario. El cobro se procesa en Stripe y
        // luego vuelve a core/procesar_compra.php?session_id=...
        const btnCheckoutStripe = document.getElementById('btn-checkout-stripe');
        if (btnCheckoutStripe) {
            const btnLabel = document.getElementById('btn-checkout-stripe-label');
            const labelOriginal = btnLabel ? btnLabel.textContent : '';

            btnCheckoutStripe.addEventListener('click', async () => {
                btnCheckoutStripe.disabled = true;
                if (btnLabel) btnLabel.textContent = 'Redirigiendo a la pasarela...';

                try {
                    const resp = await fetch('core/crear_sesion_stripe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: '{}',
                    });
                    const datos = await resp.json();

                    if (datos.ok && datos.url) {
                        // Redirección dura: Stripe Checkout no se embebe, se navega.
                        window.location.href = datos.url;
                    } else {
                        alert(datos.mensaje || 'No se pudo iniciar el pago.');
                        btnCheckoutStripe.disabled = false;
                        if (btnLabel) btnLabel.textContent = labelOriginal;
                    }
                } catch (err) {
                    console.error('Error iniciando Stripe Checkout:', err);
                    alert(I18N_CART.connection_err);
                    btnCheckoutStripe.disabled = false;
                    if (btnLabel) btnLabel.textContent = labelOriginal;
                }
            });
        }

    });
    </script>

    <?php if ($registroExito || $loginExito): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
        <div id="toastBienvenida" class="toast show" role="alert" aria-live="assertive" aria-atomic="true"
            style="background: var(--color-white); border: 1px solid rgba(44,76,59,0.1); border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.15); min-width: 320px; overflow: hidden;">
            <div style="height: 3px; background: linear-gradient(90deg, var(--color-primary), var(--color-accent));"></div>
            <div class="toast-header" style="background: transparent; border-bottom: 1px solid rgba(44,76,59,0.06); padding: 0.8rem 1rem;">
                <span style="font-size: 1.3rem; margin-right: 0.5rem;">🫒</span>
                <strong class="me-auto" style="font-family: var(--font-heading); color: var(--color-primary); font-size: 0.95rem;">
                    <?= htmlspecialchars($registroExito ? t('toast.account_created') : t('toast.welcome')) ?>
                </strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="<?= htmlspecialchars(t('toast.close')) ?>" style="font-size: 0.65rem;"></button>
            </div>
            <div class="toast-body" style="padding: 0.8rem 1rem; font-size: 0.9rem; color: var(--color-text-light); line-height: 1.5;">
                <?php if ($registroExito): ?>
                    <?= htmlspecialchars($registroExito) ?>
                <?php elseif ($loginExito): ?>
                    <?= htmlspecialchars($loginExito) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        // Auto-cerrar el toast después de 5 segundos
        setTimeout(() => {
            const toast = document.getElementById('toastBienvenida');
            if (toast) {
                toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 500);
            }
        }, 5000);
    </script>
    <?php elseif ($estaLogueado && !isset($_GET['logged'])): ?>
    <?php endif; ?>


    <!-- PWA: registramos el service worker al final del body para no bloquear el render -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./sw.js')
                .then(reg  => console.log('Service Worker registrado. Scope:', reg.scope))
                .catch(err => console.error('Error al registrar el Service Worker:', err));
        });
    }
    </script>

</body>
</html>