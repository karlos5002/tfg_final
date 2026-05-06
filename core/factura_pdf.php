<?php
// ============================================================================
// FACTURA PDF — generación reutilizable
// ============================================================================
// Antes generar_factura.php construía el PDF y lo enviaba al navegador.
// Para poder ADJUNTARLO a un email también, hemos extraído la lógica a esta
// función pura: recibe los datos del pedido y devuelve el PDF como string
// binario (FPDF::Output('S')).
//
// Quien quiera enviarlo al navegador hace:
//     header('Content-Type: application/pdf');
//     echo construirFacturaPdf($pedido, $lineas);
//
// Quien quiera adjuntarlo a un email hace:
//     $bin = construirFacturaPdf($pedido, $lineas);
//     enviarEmail(..., [['contenido'=>$bin, 'nombre'=>'factura.pdf', 'mime'=>'application/pdf']]);
// ============================================================================

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';


/**
 * Genera el PDF de una factura en memoria.
 *
 * @param array $pedido  Fila de pedidos + JOIN usuarios. Claves esperadas:
 *                       pedido_id, fecha_pedido, total, estado, metodo_pago,
 *                       cliente_nombre, cliente_apellidos, cliente_dni,
 *                       cliente_email, cliente_telefono.
 * @param array $lineas  Líneas del pedido. Cada elemento:
 *                       cantidad, precio_unitario, subtotal,
 *                       producto_nombre, producto_variedad.
 *
 * @return string PDF binario (listo para echo o adjuntar).
 */
function construirFacturaPdf(array $pedido, array $lineas): string
{
    $PDF_MARGIN     = 15;
    $IVA_PORCENTAJE = 10;   // Aceite de oliva: tipo reducido (art. 91 Ley 37/1992)

    $colorPrimario = [44, 76, 59];
    $colorAccento  = [212, 175, 55];
    $colorTexto    = [51, 51, 51];
    $colorClaro    = [245, 243, 237];
    $colorBorde    = [200, 192, 176];

    $cooperativa = [
        'nombre' => 'COOPERATIVA SAN JUAN BAUTISTA, S.C.A.',
        'cif'    => 'F-06123456',
        'dir1'   => 'Ctra. de Olivenza, km 3.5',
        'dir2'   => '06100 Olivenza (Badajoz)',
        'tel'    => '924 490 123',
        'email'  => 'info@coopsanjuanbautista.es',
        'web'    => 'www.coopsanjuanbautista.es',
    ];

    $baseImponible = (float) $pedido['total'];
    $importeIva    = round($baseImponible * $IVA_PORCENTAJE / 100, 2);
    $totalConIva   = round($baseImponible + $importeIva, 2);
    $fechaPedido   = date('d/m/Y H:i', strtotime($pedido['fecha_pedido']));
    $numFactura    = 'FAC-' . str_pad($pedido['pedido_id'], 6, '0', STR_PAD_LEFT);

    // Helper interno: fuerza Latin-1 para que FPDF (sin embed de fuentes UTF-8)
    // renderice acentos, ñ, € correctamente.
    $t = static fn(string $s): string => iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);


    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetMargins($PDF_MARGIN, $PDF_MARGIN, $PDF_MARGIN);


    // ── Cabecera ──
    $pdf->SetFillColor(...$colorPrimario);
    $pdf->Rect(0, 0, 210, 38, 'F');

    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY($PDF_MARGIN, 8);
    $pdf->Cell(120, 8, $t($cooperativa['nombre']), 0, 1);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$colorAccento);
    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell(120, 4, 'CIF: ' . $cooperativa['cif'] . '  |  ' . $cooperativa['dir1'], 0, 1);
    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell(120, 4, $cooperativa['dir2'] . '  |  Tel: ' . $cooperativa['tel'], 0, 1);

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(140, 10);
    $pdf->Cell(55, 6, 'FACTURA', 0, 1, 'R');
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(...$colorAccento);
    $pdf->SetXY(140, 17);
    $pdf->Cell(55, 7, $numFactura, 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(140, 25);
    $pdf->Cell(55, 5, 'Fecha: ' . $fechaPedido, 0, 1, 'R');


    // ── Datos cliente / pedido ──
    $pdf->Ln(12);
    $yCliente = $pdf->GetY();

    $pdf->SetFillColor(...$colorClaro);
    $pdf->SetDrawColor(...$colorBorde);
    $pdf->Rect($PDF_MARGIN, $yCliente, 180, 28, 'DF');

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->SetXY($PDF_MARGIN + 5, $yCliente + 3);
    $pdf->Cell(80, 5, 'DATOS DEL CLIENTE', 0, 1);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$colorTexto);
    $nombreCompleto = $pedido['cliente_nombre'] . ' ' . ($pedido['cliente_apellidos'] ?? '');
    $pdf->SetXY($PDF_MARGIN + 5, $yCliente + 9);
    $pdf->Cell(80, 4.5, $t('Nombre: ' . trim($nombreCompleto)), 0, 1);
    $pdf->SetX($PDF_MARGIN + 5);
    $pdf->Cell(80, 4.5, 'DNI/NIF: ' . ($pedido['cliente_dni'] ?? 'No registrado'), 0, 1);
    $pdf->SetX($PDF_MARGIN + 5);
    $pdf->Cell(80, 4.5, 'Email: ' . $pedido['cliente_email'], 0, 1);

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->SetXY(115, $yCliente + 3);
    $pdf->Cell(80, 5, 'DATOS DEL PEDIDO', 0, 1);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$colorTexto);
    $pdf->SetXY(115, $yCliente + 9);
    $pdf->Cell(80, 4.5, $t('Nº Pedido: ' . $pedido['pedido_id']), 0, 1);
    $pdf->SetXY(115, $yCliente + 13.5);
    $pdf->Cell(80, 4.5, 'Fecha: ' . $fechaPedido, 0, 1);
    $pdf->SetXY(115, $yCliente + 18);
    $pdf->Cell(80, 4.5, $t('Estado: ' . ucfirst($pedido['estado'])), 0, 1);


    // ── Tabla de líneas ──
    $pdf->Ln(10);
    $anchos = [90, 22, 30, 38];

    $pdf->SetFillColor(...$colorPrimario);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);

    $pdf->SetX($PDF_MARGIN);
    $pdf->Cell($anchos[0], 8, '  PRODUCTO', 0, 0, 'L', true);
    $pdf->Cell($anchos[1], 8, 'CANT.',     0, 0, 'C', true);
    $pdf->Cell($anchos[2], 8, 'P. UNIT.',  0, 0, 'R', true);
    $pdf->Cell($anchos[3], 8, 'SUBTOTAL',  0, 1, 'R', true);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$colorTexto);

    $fila = 0;
    foreach ($lineas as $linea) {
        $fila++;
        $pdf->SetFillColor(...($fila % 2 === 0 ? $colorClaro : [255, 255, 255]));

        $nombreProd = $t($linea['producto_nombre']);
        $variedad   = $t('(' . $linea['producto_variedad'] . ')');
        $precioUnit = number_format($linea['precio_unitario'], 2, ',', '.') . ' EUR';
        $subtotal   = number_format($linea['subtotal'],        2, ',', '.') . ' EUR';

        $pdf->SetX($PDF_MARGIN);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($anchos[0], 7, '  ' . $nombreProd . ' ' . $variedad, 0, 0, 'L', true);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($anchos[1], 7, $linea['cantidad'], 0, 0, 'C', true);
        $pdf->Cell($anchos[2], 7, $precioUnit,        0, 0, 'R', true);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($anchos[3], 7, $subtotal,          0, 1, 'R', true);
    }

    $pdf->SetDrawColor(...$colorPrimario);
    $pdf->Line($PDF_MARGIN, $pdf->GetY(), 195, $pdf->GetY());


    // ── Totales ──
    $pdf->Ln(3);
    $xT = 120;

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$colorTexto);
    $pdf->SetX($xT);
    $pdf->Cell(38, 6, 'Base imponible:', 0, 0, 'R');
    $pdf->Cell(37, 6, number_format($baseImponible, 2, ',', '.') . ' EUR', 0, 1, 'R');
    $pdf->SetX($xT);
    $pdf->Cell(38, 6, 'IVA (10%):', 0, 0, 'R');
    $pdf->Cell(37, 6, number_format($importeIva, 2, ',', '.') . ' EUR', 0, 1, 'R');

    $pdf->SetDrawColor(...$colorAccento);
    $pdf->Line($xT, $pdf->GetY(), 195, $pdf->GetY());

    $pdf->Ln(1);
    $pdf->SetFillColor(...$colorAccento);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->SetX($xT);
    $pdf->Cell(38, 9, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(37, 9, number_format($totalConIva, 2, ',', '.') . ' EUR', 0, 1, 'R', true);


    // ── Pie con nota legal ──
    $pdf->Ln(12);
    $pdf->SetDrawColor(...$colorAccento);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($PDF_MARGIN, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetLineWidth(0.2);

    $pdf->Ln(5);
    $pdf->SetFont('Helvetica', 'I', 7.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetX($PDF_MARGIN);
    $pdf->MultiCell(180, 3.5, $t(
        'NOTA LEGAL: Este documento sirve como justificante de compra y tiene validez fiscal conforme a la ' .
        'normativa vigente. IVA aplicado al tipo reducido del 10% según el art. 91.Uno de la Ley 37/1992 ' .
        'del Impuesto sobre el Valor Añadido. Conserve este documento para posibles reclamaciones.'
    ), 0, 'C');

    $pdf->Ln(5);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(...$colorPrimario);
    $pdf->Cell(180, 5, $t('Gracias por confiar en Cooperativa San Juan Bautista'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(...$colorAccento);
    $pdf->Cell(180, 4, $cooperativa['web'] . '  |  ' . $cooperativa['email'], 0, 1, 'C');


    // 'S' = devolver PDF como string (no enviar al navegador, no escribir a disco).
    return $pdf->Output('S');
}


/**
 * Carga los datos del pedido + líneas desde la BD (con control de permisos)
 * y devuelve [pedido, lineas] listos para construirFacturaPdf().
 *
 * @return array{0: array, 1: array} [pedido, lineas]
 * @throws RuntimeException si el pedido no existe o el usuario no tiene permisos.
 */
function cargarDatosFactura(PDO $pdo, int $idPedido, int $idUsuario, bool $esAdmin): array
{
    $sqlPedido = '
        SELECT p.id AS pedido_id, p.fecha_pedido, p.total, p.estado, p.metodo_pago,
               u.nombre    AS cliente_nombre,
               u.apellidos AS cliente_apellidos,
               u.dni       AS cliente_dni,
               u.email     AS cliente_email,
               u.telefono  AS cliente_telefono
        FROM pedidos p
        INNER JOIN usuarios u ON p.id_usuario = u.id
        WHERE p.id = :id_pedido
    ';
    if (!$esAdmin) $sqlPedido .= ' AND p.id_usuario = :id_usuario';

    $stmtPedido = $pdo->prepare($sqlPedido);
    $params = [':id_pedido' => $idPedido];
    if (!$esAdmin) $params[':id_usuario'] = $idUsuario;
    $stmtPedido->execute($params);
    $pedido = $stmtPedido->fetch();

    if (!$pedido) {
        throw new RuntimeException('Pedido no encontrado o sin permisos.');
    }

    $stmtLineas = $pdo->prepare('
        SELECT lp.cantidad, lp.precio_unitario, lp.subtotal,
               pr.nombre   AS producto_nombre,
               pr.variedad AS producto_variedad
        FROM lineas_pedido lp
        INNER JOIN productos pr ON lp.id_producto = pr.id
        WHERE lp.id_pedido = :id_pedido
        ORDER BY pr.nombre
    ');
    $stmtLineas->execute([':id_pedido' => $idPedido]);
    $lineas = $stmtLineas->fetchAll();

    if (empty($lineas)) {
        throw new RuntimeException('El pedido no tiene líneas de detalle.');
    }

    return [$pedido, $lineas];
}
