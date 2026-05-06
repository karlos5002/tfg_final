<?php
require_once __DIR__ . '/includes/i18n.php';
$pageTitle = t('meta.calc_title');
?>
<?php require_once 'includes/header.php'; ?>

    <?php require_once 'includes/navbar.php'; ?>

    <section class="page-hero" aria-labelledby="page-heading">
        <div class="container">
            <span class="hero-label">
                <i class="bi bi-calculator" aria-hidden="true"></i>
                <?= htmlspecialchars(t('calc.eyebrow')) ?>
            </span>
            <h1 id="page-heading">
                <?= htmlspecialchars(t('calc.title_1')) ?><br><em><?= htmlspecialchars(t('calc.title_2')) ?></em>
            </h1>
            <p class="hero-desc">
                <?= htmlspecialchars(t('calc.subtitle')) ?>
            </p>
        </div>
    </section>

    <main id="contenido-principal">
        <section class="calc-section" aria-labelledby="calc-heading">
            <div class="container">
                <div class="row g-4 g-xl-5">

                    <!-- ─── COLUMNA IZQUIERDA: FORMULARIO ─── -->
                    <div class="col-lg-7">
                        <div class="calc-form-card">
                            <h2 class="form-section-title" id="calc-heading">
                                <i class="bi bi-sliders2-vertical" aria-hidden="true"
                                    style="color: var(--color-accent-dark); margin-right: 0.3rem;"></i>
                                <?= htmlspecialchars(t('calc.form_title')) ?>
                            </h2>
                            <p class="form-section-desc">
                                <?= htmlspecialchars(t('calc.form_desc')) ?>
                            </p>

                            <form id="form-calculadora" aria-label="<?= htmlspecialchars(t('calc.form_aria')) ?>">

                                <!-- Campo 1: Número de olivos -->
                                <div class="calc-field">
                                    <label for="input-olivos" class="calc-label">
                                        <i class="bi bi-tree" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.lbl_olivos')) ?>
                                    </label>
                                    <input type="number" class="calc-input" id="input-olivos"
                                        name="olivos" min="1" max="100000" step="1"
                                        value="100" placeholder="<?= htmlspecialchars(t('calc.placeholder_olivos')) ?>"
                                        aria-describedby="olivos-help" required>
                                    <div class="olivos-visual" id="olivos-help">
                                        <input type="range" class="olivos-range" id="range-olivos"
                                            min="1" max="5000" value="100"
                                            aria-label="<?= htmlspecialchars(t('calc.range_aria')) ?>">
                                        <span class="badge-count" id="badge-olivos">100 <?= htmlspecialchars(t('calc.unit_olivos_plural')) ?></span>
                                    </div>
                                </div>

                                <!-- Campo 2: Tipo de cultivo -->
                                <div class="calc-field">
                                    <label for="select-cultivo" class="calc-label">
                                        <i class="bi bi-droplet-half" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.lbl_culture')) ?>
                                        <span class="label-hint"><?= htmlspecialchars(t('calc.hint_culture')) ?></span>
                                    </label>
                                    <div class="select-wrapper">
                                        <select class="calc-select" id="select-cultivo" name="cultivo">
                                            <option value="0.8"><?= htmlspecialchars(t('calc.opt_dry')) ?></option>
                                            <option value="1.2" selected><?= htmlspecialchars(t('calc.opt_irrigated')) ?></option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Campo 3: Edad del olivar -->
                                <div class="calc-field">
                                    <label for="select-edad" class="calc-label">
                                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.lbl_age')) ?>
                                        <span class="label-hint"><?= htmlspecialchars(t('calc.hint_age')) ?></span>
                                    </label>
                                    <div class="select-wrapper">
                                        <select class="calc-select" id="select-edad" name="edad">
                                            <option value="0.5"><?= htmlspecialchars(t('calc.opt_young')) ?></option>
                                            <option value="1.0" selected><?= htmlspecialchars(t('calc.opt_adult')) ?></option>
                                            <option value="1.3"><?= htmlspecialchars(t('calc.opt_century')) ?></option>
                                        </select>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>

                    <!-- ─── COLUMNA DERECHA: RESULTADOS ─── -->
                    <div class="col-lg-5">
                        <div class="results-card" id="results-card" aria-live="polite" aria-atomic="true">

                            <!-- Cabecera de resultados -->
                            <div class="results-header">
                                <div class="results-icon">
                                    <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                                </div>
                                <h2><?= htmlspecialchars(t('calc.results_title')) ?></h2>
                                <p><?= htmlspecialchars(t('calc.results_subtitle')) ?></p>
                            </div>

                            <!-- Métricas -->
                            <div class="results-body" id="results-body">

                                <!-- Kilos de aceituna -->
                                <div class="result-metric">
                                    <div class="metric-label">
                                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.metric_kilos_olive')) ?>
                                    </div>
                                    <div class="metric-value" id="metric-kilos-aceituna">
                                        — <span class="metric-unit">kg</span>
                                    </div>
                                </div>

                                <!-- Kilos de aceite (rendimiento graso) -->
                                <div class="result-metric">
                                    <div class="metric-label">
                                        <i class="bi bi-funnel" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.metric_kilos_oil')) ?> <small style="opacity:0.6"><?= htmlspecialchars(t('calc.metric_kilos_oil_hint')) ?></small>
                                    </div>
                                    <div class="metric-value" id="metric-kilos-aceite">
                                        — <span class="metric-unit">kg</span>
                                    </div>
                                </div>

                                <!-- Litros reales de AOVE (corregidos por densidad) -->
                                <div class="result-metric">
                                    <div class="metric-label">
                                        <i class="bi bi-droplet-fill" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.metric_litres')) ?> <small style="opacity:0.6"><?= htmlspecialchars(t('calc.metric_litres_hint')) ?></small>
                                    </div>
                                    <div class="metric-value" id="metric-litros">
                                        — <span class="metric-unit">L</span>
                                    </div>
                                </div>

                                <!-- Valor económico -->
                                <div class="result-metric">
                                    <div class="metric-label">
                                        <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                        <?= htmlspecialchars(t('calc.metric_value')) ?>
                                    </div>
                                    <div class="metric-value metric-gold" id="metric-beneficio">
                                        — <span class="metric-unit">€</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumen de parámetros -->
                            <div class="results-summary" id="results-summary">
                                <div class="summary-row">
                                    <span class="summary-label"><?= htmlspecialchars(t('calc.summary_base')) ?></span>
                                    <span class="summary-value">40 <?= htmlspecialchars(t('calc.unit_kg_per_tree')) ?></span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-row">
                                    <span class="summary-label"><?= htmlspecialchars(t('calc.summary_yield')) ?></span>
                                    <span class="summary-value">21%</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-row">
                                    <span class="summary-label"><?= htmlspecialchars(t('calc.summary_density')) ?></span>
                                    <span class="summary-value">0,916 kg/L</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-row">
                                    <span class="summary-label"><?= htmlspecialchars(t('calc.summary_price')) ?></span>
                                    <span class="summary-value"><?= htmlspecialchars(fmt_precio(7.50)) ?>/<?= lang_actual()==='en' ? 'L' : 'L' ?></span>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="info-section" aria-labelledby="info-heading">
            <div class="container">
                <div class="text-center mb-5">
                    <h2 class="form-section-title" id="info-heading" style="font-size: 1.6rem;">
                        <?= htmlspecialchars(t('calc.info_title')) ?>
                    </h2>
                    <p class="form-section-desc" style="max-width: 580px; margin: 0.5rem auto 0;">
                        <?= htmlspecialchars(t('calc.info_subtitle')) ?>
                    </p>
                </div>

                <div class="row g-4">
                    <!-- Card 1 -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="bi bi-moisture" aria-hidden="true"></i>
                            </div>
                            <h3><?= htmlspecialchars(t('calc.card1_title')) ?></h3>
                            <p>
                                <?= htmlspecialchars(t('calc.card1_desc')) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="bi bi-tree" aria-hidden="true"></i>
                            </div>
                            <h3><?= htmlspecialchars(t('calc.card2_title')) ?></h3>
                            <p>
                                <?= htmlspecialchars(t('calc.card2_desc')) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <div class="info-card-icon">
                                <i class="bi bi-currency-euro" aria-hidden="true"></i>
                            </div>
                            <h3><?= htmlspecialchars(t('calc.card3_title')) ?></h3>
                            <p>
                                <?= htmlspecialchars(t('calc.card3_desc')) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="calc-footer" role="contentinfo">
        <div class="container">
            <p>
                <?= tf('footer.copyright', (int) date('Y')) /* &copy; intencional */ ?> ·
                <a href="index.php"><?= htmlspecialchars(t('calc.footer_back')) ?></a>
            </p>
        </div>
    </footer>

    <!-- La calculadora solo usa el grid CSS de Bootstrap, no necesita su JS.
         El menú móvil ya tiene su propio JS dentro de navbar.php. -->

    <script>
    /**
     * =======================================================================
     * CALCULADORA DE RENDIMIENTO DE OLIVAR
     * =======================================================================
     * Cadena de cálculo:
     *   KilosAceituna = (Olivos × 40) × multCultivo × multEdad
     *   KilosAceite   = KilosAceituna × 0.21
     *   LitrosReales  = KilosAceite ÷ 0.916   ← corrección de densidad
     *   Beneficio     = LitrosReales × 7.50
     * =======================================================================
     */

    // Diccionario inyectado desde PHP — locale + unidades pluralizables.
    const I18N_CALC = <?= json_encode([
        'locale'           => lang_locale(),
        'unit_kg'          => 'kg',
        'unit_litres'      => lang_actual() === 'en' ? 'L' : 'litros',
        'unit_eur'         => '€',
        'olivo_singular'   => t('calc.unit_olivos_singular'),
        'olivo_plural'     => t('calc.unit_olivos_plural'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        'use strict';

        /* ─── Constantes físicas y económicas ─── */
        const BASE_KG_POR_OLIVO = 40;      // kg de aceituna por olivo adulto en regadío
        const RENDIMIENTO_GRASO = 0.21;    // 21% de rendimiento medio en almazara
        const DENSIDAD_AOVE     = 0.916;   // kg/L — densidad media del aceite de oliva
        const PRECIO_LITRO      = 7.50;    // €/litro — precio medio en origen

        /* ─── Referencias al DOM ─── */
        const inputOlivos        = document.getElementById('input-olivos');
        const rangeOlivos        = document.getElementById('range-olivos');
        const badgeOlivos        = document.getElementById('badge-olivos');
        const selectCultivo      = document.getElementById('select-cultivo');
        const selectEdad         = document.getElementById('select-edad');
        const metricKilosAceit   = document.getElementById('metric-kilos-aceituna');
        const metricKilosAceite  = document.getElementById('metric-kilos-aceite');
        const metricLitros       = document.getElementById('metric-litros');
        const metricBeneficio    = document.getElementById('metric-beneficio');
        const resultsBody        = document.getElementById('results-body');

        /* ─── Estado anterior (para animaciones CountUp) ─── */
        let prevValues = {
            kilosAceituna: 0,
            kilosAceite: 0,
            litros: 0,
            beneficio: 0
        };

        /**
         * Anima un contador de un valor inicial a uno final.
         * Usa requestAnimationFrame para fluidez a 60fps.
         */
        function animateValue(element, start, end, duration, formatter) {
            const startTime = performance.now();

            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing: ease-out cubic para desaceleración natural
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = start + (end - start) * eased;

                element.innerHTML = formatter(current);

                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    // Valor final exacto (evita errores de punto flotante)
                    element.innerHTML = formatter(end);
                }
            }

            requestAnimationFrame(update);
        }

        /**
         * Formateadores de números para cada métrica.
         * Todos usan toFixed(2) para mostrar exactamente 2 decimales.
         */
        function fmtNumber(val) {
            return val.toLocaleString(I18N_CALC.locale, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        function formatKilos(val)  { return `${fmtNumber(val)} <span class="metric-unit">${I18N_CALC.unit_kg}</span>`; }
        function formatLitros(val) { return `${fmtNumber(val)} <span class="metric-unit">${I18N_CALC.unit_litres}</span>`; }
        function formatEuros(val)  { return `${fmtNumber(val)} <span class="metric-unit">${I18N_CALC.unit_eur}</span>`; }

        /**
         * Función principal de cálculo.
         * Se ejecuta cada vez que el usuario modifica un campo.
         *
         * Cadena de conversión con corrección de densidad:
         *   Aceituna (kg) → Aceite (kg) → Aceite (L) → Valor (€)
         */
        function calcular() {
            const olivos    = parseInt(inputOlivos.value) || 0;
            const multCult  = parseFloat(selectCultivo.value);
            const multEdad  = parseFloat(selectEdad.value);

            // 1. Kilos de aceituna (producción bruta)
            const kilosAceituna = (olivos * BASE_KG_POR_OLIVO) * multCult * multEdad;

            // 2. Kilos de aceite (rendimiento graso del 21%)
            const kilosAceite = kilosAceituna * RENDIMIENTO_GRASO;

            // 3. Litros reales (corrección de densidad: kg ÷ 0.916 kg/L)
            //    1 kg de aceite = 1/0.916 ≈ 1.092 litros
            const litrosReales = kilosAceite / DENSIDAD_AOVE;

            // 4. Valor económico estimado
            const beneficio = litrosReales * PRECIO_LITRO;

            // Duración de la animación
            const duration = 800;

            // Animar cada métrica desde el valor anterior al nuevo
            animateValue(metricKilosAceit, prevValues.kilosAceituna, kilosAceituna, duration, formatKilos);
            animateValue(metricKilosAceite, prevValues.kilosAceite, kilosAceite, duration, formatKilos);
            animateValue(metricLitros, prevValues.litros, litrosReales, duration, formatLitros);
            animateValue(metricBeneficio, prevValues.beneficio, beneficio, duration, formatEuros);

            // Pulso visual en las métricas
            document.querySelectorAll('.metric-value').forEach(el => {
                el.classList.remove('metric-animate');
                // Forzar reflow para reiniciar la animación
                void el.offsetWidth;
                el.classList.add('metric-animate');
            });

            // Guardar valores para la siguiente animación
            prevValues = {
                kilosAceituna,
                kilosAceite,
                litros: litrosReales,
                beneficio
            };
        }

        /**
         * Sincroniza el input numérico con el slider y viceversa.
         */
        function badgeText(val) {
            const unidad = val === 1 ? I18N_CALC.olivo_singular : I18N_CALC.olivo_plural;
            return `${val.toLocaleString(I18N_CALC.locale)} ${unidad}`;
        }

        function syncOlivosFromInput() {
            const val = parseInt(inputOlivos.value) || 0;
            rangeOlivos.value = Math.min(val, 5000);
            badgeOlivos.textContent = badgeText(val);

            // Actualizar el gradiente visual del slider
            updateRangeGradient();
            calcular();
        }

        function syncOlivosFromRange() {
            const val = parseInt(rangeOlivos.value);
            inputOlivos.value = val;
            badgeOlivos.textContent = badgeText(val);

            updateRangeGradient();
            calcular();
        }

        /**
         * Actualiza el gradiente visual del slider para mostrar progreso.
         */
        function updateRangeGradient() {
            const min = rangeOlivos.min;
            const max = rangeOlivos.max;
            const val = rangeOlivos.value;
            const percentage = ((val - min) / (max - min)) * 100;

            rangeOlivos.style.background = `linear-gradient(90deg, 
                #D4AF37 0%, #D4AF37 ${percentage}%, 
                #F5F0E8 ${percentage}%, #F5F0E8 100%)`;
        }

        /* ─── Event listeners ─── */
        inputOlivos.addEventListener('input', syncOlivosFromInput);
        rangeOlivos.addEventListener('input', syncOlivosFromRange);
        selectCultivo.addEventListener('change', calcular);
        selectEdad.addEventListener('change', calcular);

        /* ─── Cálculo inicial ─── */
        updateRangeGradient();
        calcular();
    });
    </script>

</body>
</html>
