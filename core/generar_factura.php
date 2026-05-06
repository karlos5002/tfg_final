<?php
// Genera la factura PDF de un pedido y la envía al navegador.
// Toda la lógica de FPDF vive en core/factura_pdf.php para que pueda
// reutilizarse desde el flujo de emails post-compra.

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/factura_pdf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$idPedido = filter_input(INPUT_GET, 'id_pedido', FILTER_VALIDATE_INT);
if (!$idPedido || $idPedido <= 0) {
    die('Error: ID de pedido no válido.');
}

try {
    $pdo       = getConexion();
    $idUsuario = (int) $_SESSION['usuario_id'];
    $esAdmin   = ($_SESSION['rol'] === 'admin');

    [$pedido, $lineas] = cargarDatosFactura($pdo, $idPedido, $idUsuario, $esAdmin);

} catch (RuntimeException $e) {
    die('Error: ' . $e->getMessage());
} catch (PDOException $e) {
    error_log('Error en generar_factura.php: ' . $e->getMessage());
    die('Error interno al generar la factura.');
}

$numFactura = 'FAC-' . str_pad($pedido['pedido_id'], 6, '0', STR_PAD_LEFT);
$pdfBin     = construirFacturaPdf($pedido, $lineas);

// Inline → se abre en el visor del navegador (target="_blank" no se queda en blanco).
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Factura_' . $numFactura . '.pdf"');
header('Content-Length: ' . strlen($pdfBin));
echo $pdfBin;
exit;
