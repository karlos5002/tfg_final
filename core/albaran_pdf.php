<?php
// ============================================================================
// ALBARÁN PDF — generación reutilizable
// ============================================================================
// Análogo a core/factura_pdf.php: extrae la lógica de FPDF para que se pueda
// reutilizar tanto desde generar_albaran.php (descarga al navegador) como
// desde admin/index.php para adjuntarlo al email del socio cuando se registra
// una nueva entrega.
// ============================================================================

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';


/**
 * Genera el PDF del albarán de una entrega en memoria.
 *
 * @param array $entrega Fila de entregas + JOIN usuarios + LEFT JOIN campanas.
 *                       Claves esperadas:
 *                         id, fecha_entrega, kilos_aceituna, rendimiento,
 *                         litros_aceite, observaciones, created_at,
 *                         socio_nombre, socio_apellidos, socio_dni,
 *                         socio_email, socio_telefono,
 *                         campana, campana_codigo, precio_por_kilo,
 *                         campana_estado.
 *
 * @return string PDF binario.
 */
function construirAlbaranPdf(array $entrega): string
{
    $PDF_MARGIN = 15;

    // FPDF usa fuentes core en cp1252. Convertimos para que €, ñ, … rendericen.
    $t = static fn(string $s): string => mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

    $colorPrimario = [44, 76, 59];
    $colorAccento  = [212, 175, 55];
    $colorTexto    = [51, 51, 51];
    $colorClaro    = [245, 243, 237];

    $cooperativa = [
        'nombre' => 'COOPERATIVA SAN JUAN BAUTISTA, S.C.A.',
        'cif'    => 'F-06123456',
        'dir1'   => 'Ctra. de Olivenza, km 3.5',
        'dir2'   => '06100 Olivenza (Badajoz)',
        'tel'    => '924 490 123',
        'email'  => 'info@coopsanjuanbautista.es',
    ];

    $numAlbaran   = 'ALB-' . str_pad((string) $entrega['id'], 6, '0', STR_PAD_LEFT);
    $horaRegistro = isset($entrega['created_at'])
        ? date('d/m/Y H:i', strtotime($entrega['created_at']))
        : date('d/m/Y H:i');
    $kilos        = (float) $entrega['kilos_aceituna'];
    $rendimiento  = (float) $entrega['rendimiento'];
    $litros       = (float) ($entrega['litros_aceite'] ?? 0);

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetMargins($PDF_MARGIN, $PDF_MARGIN, $PDF_MARGIN);


    // ── Cabecera ──
    $pdf->SetFillColor(...$colorPrimario);
    $pdf->Rect(0, 0, 210, 35, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetXY($PDF_MARGIN, 10);
    $pdf->Cell(120, 6, $t($cooperativa['nombre']), 0, 1);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell(120, 5, $t($cooperativa['dir1']), 0, 1);
    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell(120, 5, $t($cooperativa['dir2'] . ' · CIF: ' . $cooperativa['cif']), 0, 1);
    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell(120, 5, $t('Tel: ' . $cooperativa['tel'] . ' · ' . $cooperativa['email']), 0, 1);

    // Caja dorada con nº de albarán
    $pdf->SetFillColor(...$colorAccento);
    $pdf->Rect(140, 8, 55, 22, 'F');
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY(140, 11);
    $pdf->Cell(55, 5, 'ALBARAN DE ENTREGA', 0, 1, 'C');
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetX(140);
    $pdf->Cell(55, 7, $numAlbaran, 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetX(140);
    $pdf->Cell(55, 5, 'Fecha registro: ' . $horaRegistro, 0, 1, 'C');

    $pdf->SetTextColor(...$colorTexto);


    // ── Datos del socio ──
    $pdf->Ln(10);
    $pdf->SetY(45);

    $pdf->SetFillColor(...$colorClaro);
    $pdf->Rect($PDF_MARGIN, $pdf->GetY(), 180, 32, 'F');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->Cell(180, 7, '  DATOS DEL SOCIO', 0, 1, 'L');

    $pdf->SetTextColor(...$colorTexto);
    $pdf->SetFont('Helvetica', '', 10);

    $nombreCompleto = trim(($entrega['socio_apellidos'] ?? '') !== ''
        ? $entrega['socio_apellidos'] . ', ' . $entrega['socio_nombre']
        : $entrega['socio_nombre']);

    $pdf->SetX($PDF_MARGIN + 3);
    $pdf->Cell(30, 6, $t('Nombre:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(70, 6, $t($nombreCompleto), 0, 0);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(20, 6, 'DNI:', 0, 0);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 6, $t($entrega['socio_dni'] ?? '—'), 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetX($PDF_MARGIN + 3);
    $pdf->Cell(30, 6, 'Email:', 0, 0);
    $pdf->Cell(70, 6, $t($entrega['socio_email']), 0, 0);
    $pdf->Cell(20, 6, $t('Teléfono:'), 0, 0);
    $pdf->Cell(50, 6, $t($entrega['socio_telefono'] ?? '—'), 0, 1);

    $pdf->SetX($PDF_MARGIN + 3);
    $pdf->Cell(30, 6, $t('Campaña:'), 0, 0);
    $pdf->SetFont('Helvetica', 'B', 10);
    $campanaTxt = $entrega['campana_codigo'] ?? ($entrega['campana'] ?? '—');
    if (!empty($entrega['campana_estado']) && $entrega['campana_estado'] === 'cerrada') {
        $campanaTxt .= ' (cerrada)';
    }
    $pdf->Cell(70, 6, $t($campanaTxt), 0, 0);

    if (!empty($entrega['precio_por_kilo'])) {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(30, 6, $t('Precio €/kg:'), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(50, 6, $t(number_format((float) $entrega['precio_por_kilo'], 4, ',', '.') . ' €'), 0, 1);
    } else {
        $pdf->Ln(6);
    }


    // ── Tabla de detalle ──
    $pdf->Ln(8);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->Cell(180, 7, 'DETALLE DE LA ENTREGA', 0, 1, 'L');

    $pdf->SetFillColor(...$colorPrimario);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(40, 8, 'CONCEPTO',                 1, 0, 'L', true);
    $pdf->Cell(50, 8, $t('DESCRIPCIÓN'),          1, 0, 'L', true);
    $pdf->Cell(35, 8, 'CANTIDAD',                 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'UNIDAD',                   1, 0, 'C', true);
    $pdf->Cell(20, 8, 'NOTAS',                    1, 1, 'C', true);

    $pdf->SetTextColor(...$colorTexto);
    $pdf->SetFont('Helvetica', '', 9);

    $pdf->Cell(40, 8, 'Aceituna bruta',                          1, 0);
    $pdf->Cell(50, 8, $t('Recepción en almazara'),               1, 0);
    $pdf->Cell(35, 8, number_format($kilos, 2, ',', '.'),        1, 0, 'R');
    $pdf->Cell(35, 8, 'kg',                                      1, 0, 'C');
    $pdf->Cell(20, 8, '',                                        1, 1, 'C');

    $pdf->Cell(40, 8, 'Rendimiento',                             1, 0);
    $pdf->Cell(50, 8, $t('% graso analítico'),                   1, 0);
    $pdf->Cell(35, 8, number_format($rendimiento, 2, ',', '.'),  1, 0, 'R');
    $pdf->Cell(35, 8, '%',                                       1, 0, 'C');
    $pdf->Cell(20, 8, '',                                        1, 1, 'C');

    $pdf->SetFillColor(...$colorClaro);
    $pdf->Cell(40, 8, 'AOVE estimado',                     1, 0, 'L', true);
    $pdf->Cell(50, 8, 'Densidad 0,916 kg/L',               1, 0, 'L', true);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(35, 8, number_format($litros, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'litros',                            1, 0, 'C', true);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(20, 8, '',                                  1, 1, 'C', true);

    if (!empty($entrega['precio_por_kilo'])) {
        $precioKilo  = (float) $entrega['precio_por_kilo'];
        $liquidacion = round($kilos * $precioKilo, 2);
        $pdf->SetFillColor(...$colorAccento);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(40, 8, $t('Liquidación est.'),                          1, 0, 'L', true);
        $pdf->Cell(50, 8, $t('Kilos × precio campaña'),                    1, 0, 'L', true);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(35, 8, number_format($liquidacion, 2, ',', '.'),        1, 0, 'R', true);
        $pdf->Cell(35, 8, $t('€'),                                         1, 0, 'C', true);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(20, 8, 'estim.',                                        1, 1, 'C', true);
        $pdf->SetTextColor(...$colorTexto);
    }

    if (!empty($entrega['observaciones'])) {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(40, 6, 'Observaciones:', 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell(180, 5, $t($entrega['observaciones']), 1, 'L');
    }


    // ── Firmas ──
    $pdf->Ln(20);
    $pdf->SetFont('Helvetica', '', 9);

    $y = $pdf->GetY();
    $pdf->Line($PDF_MARGIN + 10,  $y + 14, $PDF_MARGIN + 70,  $y + 14);
    $pdf->Line($PDF_MARGIN + 110, $y + 14, $PDF_MARGIN + 170, $y + 14);

    $pdf->SetXY($PDF_MARGIN + 10, $y + 16);
    $pdf->Cell(60, 5, 'Firma cooperativa', 0, 0, 'C');
    $pdf->SetXY($PDF_MARGIN + 110, $y + 16);
    $pdf->Cell(60, 5, 'Firma del socio',   0, 0, 'C');


    // ── Pie con nota legal ──
    $pdf->SetY(-25);
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->MultiCell(0, 4,
        $t('Este albarán es un justificante de entrega y depósito de aceituna en la almazara. '
         . 'Los datos analíticos de rendimiento se confirman tras el proceso de molturación. '
         . 'Documento generado electrónicamente — válido sin firma manuscrita.'),
        0, 'C');


    return $pdf->Output('S');
}


/**
 * Carga los datos de la entrega + datos del socio + datos de la campaña
 * desde la BD, controlando permisos. Devuelve la fila combinada.
 *
 * @throws RuntimeException si la entrega no existe o el usuario no tiene permisos.
 */
function cargarDatosAlbaran(PDO $pdo, int $idEntrega, int $idUsuario, bool $esPersonal): array
{
    $sql = '
        SELECT e.id, e.fecha_entrega, e.campana, e.kilos_aceituna, e.rendimiento,
               e.litros_aceite, e.observaciones, e.created_at,
               e.anulada, e.motivo_anulacion, e.fecha_anulacion,
               u.nombre    AS socio_nombre,
               u.apellidos AS socio_apellidos,
               u.dni       AS socio_dni,
               u.email     AS socio_email,
               u.telefono  AS socio_telefono,
               c.codigo          AS campana_codigo,
               c.precio_por_kilo AS precio_por_kilo,
               c.estado          AS campana_estado
        FROM entregas e
        INNER JOIN usuarios u ON e.id_socio = u.id
        LEFT  JOIN campanas c ON c.id = e.id_campana
        WHERE e.id = :id_entrega
    ';
    if (!$esPersonal) $sql .= ' AND e.id_socio = :id_usuario';

    $stmt = $pdo->prepare($sql);
    $params = [':id_entrega' => $idEntrega];
    if (!$esPersonal) $params[':id_usuario'] = $idUsuario;
    $stmt->execute($params);
    $entrega = $stmt->fetch();

    if (!$entrega) {
        throw new RuntimeException('Entrega no encontrada o sin permisos.');
    }
    return $entrega;
}
