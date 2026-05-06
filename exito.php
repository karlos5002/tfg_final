<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Confirmación de Compra (exito.php)
 * ============================================================================
 * Muestra la confirmación de un pedido exitoso con los datos relevantes
 * y un botón para descargar la factura en PDF.
 * ============================================================================
 */
session_start();
require_once __DIR__ . '/config/db.php';

// ── Verificar login ──
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// ── Obtener datos del pedido ──
$idPedido = filter_input(INPUT_GET, 'id_pedido', FILTER_VALIDATE_INT);
$compraExito = $_SESSION['compra_exito'] ?? null;

// Si no hay datos de éxito en sesión, consultar la BD
if (!$compraExito && $idPedido) {
    try {
        $pdo = getConexion();
        $stmt = $pdo->prepare('
            SELECT id, total, estado, fecha_pedido
            FROM pedidos
            WHERE id = :id AND id_usuario = :uid
            LIMIT 1
        ');
        $stmt->execute([':id' => $idPedido, ':uid' => $_SESSION['usuario_id']]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $compraExito = [
                'id_pedido' => $pedido['id'],
                'total'     => $pedido['total'],
            ];

            // Contar líneas
            $stmtL = $pdo->prepare('SELECT COUNT(*) FROM lineas_pedido WHERE id_pedido = :id');
            $stmtL->execute([':id' => $idPedido]);
            $compraExito['items'] = (int) $stmtL->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log('Error en exito.php: ' . $e->getMessage());
    }
}

if (!$compraExito) {
    header('Location: tienda.php');
    exit;
}

// Limpiar datos de sesión temporal
unset($_SESSION['compra_exito']);

require_once __DIR__ . '/includes/i18n.php';
$pageTitle = t('meta.exito_title');
?>
<?php require_once 'includes/header.php'; ?>
    <?php require_once 'includes/navbar.php'; ?>
    <div class="exito-card">
        <!-- Icono de éxito -->
        <div class="check-icon">
            <i class="bi bi-check-lg"></i>
        </div>

        <h1 class="exito-title"><?= htmlspecialchars(t('exito.title')) ?></h1>
        <p class="exito-subtitle">
            <?= htmlspecialchars(tf('exito.subtitle', $_SESSION['nombre'])) ?>
        </p>

        <!-- Datos del pedido -->
        <div class="datos-pedido">
            <div class="dato">
                <span class="dato-label"><?= htmlspecialchars(t('exito.lbl_order')) ?></span>
                <span class="dato-valor">#<?= $compraExito['id_pedido'] ?></span>
            </div>
            <div class="dato">
                <span class="dato-label"><?= htmlspecialchars(t('exito.lbl_items')) ?></span>
                <span class="dato-valor"><?= htmlspecialchars(tf(
                    $compraExito['items'] === 1 ? 'exito.items_singular' : 'exito.items_plural',
                    $compraExito['items']
                )) ?></span>
            </div>
            <div class="dato">
                <span class="dato-label"><?= htmlspecialchars(t('exito.lbl_subtotal')) ?></span>
                <span class="dato-valor"><?= htmlspecialchars(fmt_precio($compraExito['total'])) ?></span>
            </div>
            <div class="dato">
                <span class="dato-label"><?= htmlspecialchars(t('exito.lbl_iva')) ?></span>
                <span class="dato-valor"><?= htmlspecialchars(fmt_precio($compraExito['total'] * 0.10)) ?></span>
            </div>
            <div class="dato" style="border-top: 2px solid rgba(44,76,59,0.1); padding-top: 0.6rem;">
                <span class="dato-label" style="font-size: 1rem; color: #2C4C3B;"><?= htmlspecialchars(t('exito.lbl_total')) ?></span>
                <span class="dato-valor" style="font-size: 1.2rem; color: #D4AF37;">
                    <?= htmlspecialchars(fmt_precio($compraExito['total'] * 1.10)) ?>
                </span>
            </div>
        </div>

        <!-- Botón descargar factura (el generador vive en core/, no en la raíz) -->
        <a href="core/generar_factura.php?id_pedido=<?= $compraExito['id_pedido'] ?>"
           class="btn-factura" target="_blank" rel="noopener">
            <i class="bi bi-file-earmark-pdf"></i> <?= htmlspecialchars(t('exito.btn_invoice')) ?>
        </a>

        <br>

        <!-- Volver a la tienda -->
        <a href="tienda.php" class="btn-volver">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(t('exito.btn_continue')) ?>
        </a>
    </div>
</body>
</html>
