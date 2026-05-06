<?php
// Catálogo de la tienda online con carrito AJAX y filtro por variedad.
// Los productos agotados siguen siendo visibles (botón deshabilitado) — más
// realista que ocultarlos y ayuda al SEO de las fichas.

session_start();
require_once __DIR__ . '/config/db.php';

$totalCarrito = 0;
if (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $totalCarrito += $item['cantidad'];
    }
}

try {
    $pdo = getConexion();

    $stmtProductos = $pdo->query('
        SELECT id, nombre, slug, variedad, descripcion, precio, stock, stock_minimo, imagen
        FROM productos
        WHERE activo = 1
        ORDER BY (stock = 0), variedad, nombre
    ');
    $productos = $stmtProductos->fetchAll();

    $variedades = array_unique(array_column($productos, 'variedad'));

} catch (PDOException $e) {
    error_log('Error en tienda.php: ' . $e->getMessage());
    $productos  = [];
    $variedades = [];
}

// Cargar i18n antes del header para poder localizar título y meta-description
// con las claves específicas de tienda (en lugar de los defaults globales).
require_once __DIR__ . '/includes/i18n.php';
$pageTitle       = t('meta.shop_title');
$pageDescription = t('meta.shop_desc');
?>
<?php require_once 'includes/header.php'; ?>

    <?php require_once 'includes/navbar.php'; ?>


    <main id="contenido-principal">

        <?php
        // Si procesar_compra.php abortó la transacción, aquí mostramos el motivo.
        // Sin este bloque el usuario volvía a la tienda sin ver ningún feedback
        // ("no sucede nada" al finalizar compra → en realidad fallaba silenciosamente).
        if (!empty($_SESSION['error_compra'])):
            $msgErrorCompra = $_SESSION['error_compra'];
            unset($_SESSION['error_compra']); // mostrar una sola vez
        ?>
            <div class="container pt-4">
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <div>
                        <strong><?= htmlspecialchars(t('shop.error_compra')) ?></strong>
                        <?= htmlspecialchars($msgErrorCompra) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="hero-mini" aria-labelledby="tienda-heading">
            <!-- Partículas decorativas -->
            <span class="particle particle-1" aria-hidden="true"></span>
            <span class="particle particle-2" aria-hidden="true"></span>
            <span class="particle particle-3" aria-hidden="true"></span>
            <span class="particle particle-4" aria-hidden="true"></span>

            <div class="container">
                <div class="hero-mini-content">
                    <span class="section-label"><?= htmlspecialchars(t('shop.label')) ?></span>
                    <h1 id="tienda-heading">
                        <?= htmlspecialchars(t('shop.title_1')) ?> <em><?= htmlspecialchars(t('shop.title_2')) ?></em>
                    </h1>
                    <p>
                        <?= htmlspecialchars(t('shop.subtitle')) ?>
                    </p>
                </div>
            </div>
        </section>


        <section class="filtros-section" aria-label="<?= htmlspecialchars(t('shop.aria_filters')) ?>">
            <div class="container">
                <div class="filtros-wrapper" id="filtros-variedad">
                    <button class="filtro-btn active" data-variedad="todas" id="filtro-todas">
                        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> <?= htmlspecialchars(t('shop.filter_all')) ?>
                    </button>
                    <?php foreach ($variedades as $variedad): ?>
                        <button class="filtro-btn"
                            data-variedad="<?= htmlspecialchars($variedad) ?>"
                            id="filtro-<?= htmlspecialchars(strtolower($variedad)) ?>">
                            <?= htmlspecialchars($variedad) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>


        <!-- Catálogo de productos (dinámico desde BD) -->
        <section class="productos-grid" aria-label="<?= htmlspecialchars(t('shop.aria_catalog')) ?>">
            <div class="container">

                <?php if (empty($productos)): ?>
                    <!-- Estado vacío -->
                    <div class="catalogo-vacio">
                        <i class="bi bi-bag-x" aria-hidden="true"></i>
                        <h3><?= htmlspecialchars(t('shop.empty_title')) ?></h3>
                        <p><?= htmlspecialchars(t('shop.empty_desc')) ?></p>
                    </div>

                <?php else: ?>
                    <div class="row g-4 g-lg-5" id="grid-productos">

                        <?php foreach ($productos as $producto): ?>
                            <?php
                            // Estado de stock — 3 niveles + agotado
                            $stockUnits = (int) $producto['stock'];
                            $stockMin   = (int) $producto['stock_minimo'];
                            if ($stockUnits === 0) {
                                $stockClase = 'agotado';
                                $stockTexto = t('shop.stock_out');
                            } elseif ($stockUnits <= $stockMin) {
                                $stockClase = 'bajo';
                                $stockTexto = tf(
                                    $stockUnits === 1 ? 'shop.stock_low_singular' : 'shop.stock_low_plural',
                                    $stockUnits
                                );
                            } else {
                                $stockClase = 'ok';
                                $stockTexto = t('shop.stock_ok');
                            }
                            ?>
                            <div class="col-sm-6 col-lg-4 col-xl-3 producto-col"
                                data-variedad="<?= htmlspecialchars($producto['variedad']) ?>">

                                <article class="product-card<?= $stockUnits === 0 ? ' is-agotado' : '' ?>"
                                         id="producto-<?= $producto['id'] ?>">

                                    <div class="product-card-img">
                                        <span class="product-badge-variedad badge-<?= htmlspecialchars($producto['variedad']) ?>">
                                            <?= htmlspecialchars($producto['variedad']) ?>
                                        </span>
                                        <?php if ($stockUnits === 0): ?>
                                            <span class="product-badge-agotado" aria-label="<?= htmlspecialchars(t('shop.stock_out')) ?>"><?= htmlspecialchars(t('shop.stock_out')) ?></span>
                                        <?php endif; ?>
                                        <img src="assets/img/<?= htmlspecialchars($producto['imagen']) ?>"
                                            alt="<?= htmlspecialchars(tf('shop.product_alt', $producto['nombre'], $producto['variedad'])) ?>"
                                            width="400" height="400" loading="lazy">
                                    </div>

                                    <div class="product-card-body">
                                        <h3 class="product-card-title">
                                            <?= htmlspecialchars($producto['nombre']) ?>
                                        </h3>
                                        <p class="product-card-desc">
                                            <?= htmlspecialchars($producto['descripcion']) ?>
                                        </p>

                                        <div class="stock-info stock-<?= $stockClase ?>">
                                            <span class="stock-dot"></span>
                                            <?= htmlspecialchars($stockTexto) ?>
                                        </div>

                                        <div class="product-card-footer">
                                            <span class="product-price">
                                                <?= number_format($producto['precio'], 2, ',', '.') ?>€
                                            </span>

                                            <?php if ($stockUnits === 0): ?>
                                                <button type="button" class="btn-add-cart" disabled
                                                    aria-label="<?= htmlspecialchars(tf('shop.aria_out', $producto['nombre'])) ?>">
                                                    <i class="bi bi-x-circle" aria-hidden="true"></i> <?= htmlspecialchars(t('shop.btn_out')) ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button"
                                                    class="btn-add-cart"
                                                    data-id="<?= $producto['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($producto['nombre']) ?>"
                                                    data-stock="<?= $stockUnits ?>"
                                                    aria-label="<?= htmlspecialchars(tf('shop.aria_add', $producto['nombre'])) ?>"
                                                    id="btn-add-<?= $producto['id'] ?>">
                                                    <i class="bi bi-bag-plus" aria-hidden="true"></i> <?= htmlspecialchars(t('shop.btn_add')) ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </article>

                            </div>
                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>

            </div>
        </section>

    </main>


    <!-- Filtro por variedad: oculta/muestra .producto-col en cliente, sin recargar. -->
    <script>
    (() => {
        const botones    = document.querySelectorAll('.filtro-btn');
        const productos  = document.querySelectorAll('.producto-col');
        if (!botones.length || !productos.length) return;

        botones.forEach(btn => {
            btn.addEventListener('click', () => {
                const seleccionada = btn.dataset.variedad;

                // Actualizar estado activo
                botones.forEach(b => b.classList.toggle('active', b === btn));

                // Filtrar tarjetas con un fade rápido para que no parezca un parpadeo
                productos.forEach(col => {
                    const coincide = (seleccionada === 'todas' || col.dataset.variedad === seleccionada);
                    col.style.transition = 'opacity .2s ease';
                    if (coincide) {
                        col.style.display = '';
                        requestAnimationFrame(() => { col.style.opacity = '1'; });
                    } else {
                        col.style.opacity = '0';
                        // Esperar a que termine el fade antes de quitar del flujo
                        setTimeout(() => { col.style.display = 'none'; }, 200);
                    }
                });
            });
        });
    })();
    </script>

    <?php require_once 'includes/footer.php'; ?>
