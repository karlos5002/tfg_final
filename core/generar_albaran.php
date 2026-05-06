<?php
// Genera el albarán PDF de una entrega y lo envía al navegador.
// La lógica FPDF vive en core/albaran_pdf.php para poder reutilizarse
// también desde admin/index.php al adjuntar el albarán al email del socio.

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/albaran_pdf.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$idEntrega = filter_input(INPUT_GET, 'id_entrega', FILTER_VALIDATE_INT);
if (!$idEntrega || $idEntrega <= 0) {
    die('Error: ID de entrega no válido.');
}

try {
    $pdo        = getConexion();
    $idUsuario  = (int) $_SESSION['usuario_id'];
    $rol        = $_SESSION['rol'] ?? '';
    // Admin y operario son personal de la almazara → ven cualquier albarán.
    // El socio sólo el suyo (defensa contra ID-tampering).
    $esPersonal = ($rol === 'admin' || $rol === 'operario');

    $entrega = cargarDatosAlbaran($pdo, $idEntrega, $idUsuario, $esPersonal);

    // Si la entrega ha sido anulada, el albarán no tiene validez. Bloqueamos
    // la descarga para todos: ni socio, ni operario, ni admin pueden bajarse
    // un PDF de una entrega cancelada (queda en el histórico, no descargable).
    if ((int) ($entrega['anulada'] ?? 0) === 1) {
        http_response_code(410);   // 410 Gone — recurso anulado/inválido
        die('Este albarán fue anulado y ya no tiene validez.');
    }

} catch (RuntimeException $e) {
    die('Error: ' . $e->getMessage());
} catch (PDOException $e) {
    error_log('Error en generar_albaran.php: ' . $e->getMessage());
    die('Error interno al generar el albarán.');
}

$nombreCompleto = trim(($entrega['socio_apellidos'] ?? '') !== ''
    ? $entrega['socio_apellidos'] . ', ' . $entrega['socio_nombre']
    : $entrega['socio_nombre']);
$numAlbaran = 'ALB-' . str_pad((string) $entrega['id'], 6, '0', STR_PAD_LEFT);
$pdfBin     = construirAlbaranPdf($entrega);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $numAlbaran . '_' . str_replace(' ', '_', $nombreCompleto) . '.pdf"');
header('Content-Length: ' . strlen($pdfBin));
echo $pdfBin;
exit;
