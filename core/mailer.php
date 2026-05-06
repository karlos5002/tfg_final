<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Helper centralizado de envío de correos
 * ============================================================================
 *
 * API pública:
 *   - enviarEmail(to, subject, htmlBody, [attachments]): array
 *   - emailWrapper(titulo, subtitulo, cuerpoHtml, [variant]): string
 *
 *   Plantillas que usan emailWrapper() (definidas más abajo):
 *   - emailConfirmacionCompra($pedido, $lineas, $usuario)
 *   - emailBienvenidaUsuario($usuario)
 *   - emailEntregaRegistrada($entrega, $socio)
 *   - emailConfirmacionVisita($visita)   [retrocompatibilidad]
 *   - emailCancelacionVisita($visita)    [retrocompatibilidad]
 *
 * Modo de envío:
 *   - EMAIL_MODE = 'smtp' (producción): usa PHPMailer con SMTP de Hostinger.
 *                                       Soporta adjuntos (PDFs en memoria).
 *   - EMAIL_MODE = 'log'  (local):      guarda el HTML en logs/emails/ y los
 *                                       adjuntos en logs/emails/*.pdf.
 *   - EMAIL_MODE = 'auto':              intenta SMTP, si falla cae a log.
 *
 * Compatibilidad: si PHPMailer no está disponible (no se ejecutó composer
 * install), enviarEmail() degrada a mail() o a log automáticamente; la
 * aplicación nunca muere por un email.
 * ============================================================================
 */

require_once __DIR__ . '/../config/email.php';

// Cargar PHPMailer (renombrado a init_libs.php por falso positivo de Avast).
$libsInit = __DIR__ . '/../vendor/init_libs.php';
if (is_file($libsInit)) require_once $libsInit;


// ────────────────────────────────────────────────────────────────────────────
// FORMATO DE FECHA EN ESPAÑOL (para plantillas)
// ────────────────────────────────────────────────────────────────────────────
if (!function_exists('formatearFechaEs')) {
    function formatearFechaEs(string $fechaSql): string
    {
        $dias  = ['Sunday'=>'domingo','Monday'=>'lunes','Tuesday'=>'martes',
                  'Wednesday'=>'miércoles','Thursday'=>'jueves',
                  'Friday'=>'viernes','Saturday'=>'sábado'];
        $meses = ['January'=>'enero','February'=>'febrero','March'=>'marzo',
                  'April'=>'abril','May'=>'mayo','June'=>'junio',
                  'July'=>'julio','August'=>'agosto','September'=>'septiembre',
                  'October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];

        $ts = strtotime($fechaSql);
        return $dias[date('l', $ts)] . ', ' . date('j', $ts) . ' de '
             . $meses[date('F', $ts)] . ' de ' . date('Y', $ts);
    }
}


// ────────────────────────────────────────────────────────────────────────────
// ENVÍO PRINCIPAL — enviarEmail(to, subject, htmlBody, attachments)
// ────────────────────────────────────────────────────────────────────────────
/**
 * Envía un email (con o sin adjuntos en memoria).
 *
 * @param string $to            Destinatario.
 * @param string $subject       Asunto (sin prefijo, se añade EMAIL_SUBJECT_PFX).
 * @param string $htmlBody      Cuerpo HTML.
 * @param array  $attachments   Lista de adjuntos en formato:
 *                              [['contenido' => string-binario,
 *                                'nombre'    => 'factura.pdf',
 *                                'mime'      => 'application/pdf'], …]
 *                              Para usar archivos en disco: pasa
 *                              ['ruta' => '/path/file.pdf', 'nombre' => '...'].
 *
 * @return array{ok: bool, modo: string, ruta_log: ?string, error: ?string}
 */
function enviarEmail(string $to, string $subject, string $htmlBody, array $attachments = []): array
{
    $resultado = ['ok' => false, 'modo' => '', 'ruta_log' => null, 'error' => null];
    $asuntoCompleto = EMAIL_SUBJECT_PFX . $subject;

    // ── Modo SMTP / auto: intentar PHPMailer ──
    if (EMAIL_MODE === 'smtp' || EMAIL_MODE === 'auto') {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                // Configuración SMTP
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->Port       = SMTP_PORT;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = (SMTP_SECURE === 'tls')
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->CharSet    = 'UTF-8';
                $mail->Encoding   = 'base64';
                $mail->SMTPDebug  = SMTP_DEBUG_LEVEL;

                // Cabeceras
                $mail->setFrom(EMAIL_FROM_ADDR, EMAIL_FROM_NAME);
                $mail->addReplyTo(EMAIL_REPLY_TO);
                $mail->addAddress($to);
                $mail->Subject = $asuntoCompleto;
                $mail->isHTML(true);
                $mail->Body    = $htmlBody;
                // Versión texto plano para clientes que no renderizan HTML.
                $mail->AltBody = trim(strip_tags(preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $htmlBody)));

                // Adjuntos (en memoria o desde ruta)
                foreach ($attachments as $a) {
                    if (isset($a['ruta']) && is_file($a['ruta'])) {
                        $mail->addAttachment($a['ruta'], $a['nombre'] ?? basename($a['ruta']),
                                             'base64', $a['mime'] ?? 'application/octet-stream');
                    } elseif (isset($a['contenido'])) {
                        $mail->addStringAttachment(
                            $a['contenido'],
                            $a['nombre'] ?? 'adjunto.bin',
                            'base64',
                            $a['mime'] ?? 'application/octet-stream'
                        );
                    }
                }

                $mail->send();
                $resultado['ok']   = true;
                $resultado['modo'] = 'smtp';
                return $resultado;

            } catch (\Throwable $e) {
                // No abortar: registrar y caer al fallback.
                $resultado['error'] = 'PHPMailer falló: ' . $e->getMessage();
                error_log('[mailer] ' . $resultado['error']);
                if (EMAIL_MODE === 'smtp') {
                    // En modo estricto SMTP devolvemos el fallo, pero igualmente
                    // dejamos copia en disco para no perder el contenido.
                    return $resultado + _guardarEmailEnLog($to, $asuntoCompleto, $htmlBody, $attachments);
                }
            }
        } else {
            // PHPMailer no instalado: intentar mail() de PHP (sin adjuntos serios).
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDR . '>',
                'Reply-To: ' . EMAIL_REPLY_TO,
                'X-Mailer: PHP/' . phpversion(),
            ];
            // Si hay adjuntos y no tenemos PHPMailer, igualmente avisamos al admin.
            if (!empty($attachments)) {
                error_log('[mailer] Adjuntos solicitados pero PHPMailer no está instalado — se envía solo HTML.');
            }
            $ok = @mail($to, $asuntoCompleto, $htmlBody, implode("\r\n", $headers));
            if ($ok) {
                $resultado['ok']   = true;
                $resultado['modo'] = 'mail()';
                return $resultado;
            }
            $resultado['error'] = 'PHPMailer no instalado y mail() falló.';
        }
    }

    // ── Fallback / modo log ──
    return $resultado + _guardarEmailEnLog($to, $asuntoCompleto, $htmlBody, $attachments);
}


/**
 * Escribe el HTML del email en logs/emails/ + los adjuntos como ficheros.
 * Útil en local (sin SMTP) y como salvavidas si SMTP falla en producción.
 */
function _guardarEmailEnLog(string $to, string $asuntoCompleto, string $htmlBody, array $attachments): array
{
    $r = ['ok' => false, 'modo' => 'log', 'ruta_log' => null, 'error' => null];

    if (!is_dir(EMAIL_LOG_DIR)) {
        @mkdir(EMAIL_LOG_DIR, 0775, true);
    }

    $slug   = preg_replace('/[^a-z0-9]+/i', '_', strtolower($asuntoCompleto));
    $slug   = trim(substr($slug, 0, 40), '_');
    $stamp  = date('Ymd_His');
    $hash   = substr(md5($to . $stamp), 0, 6);
    $nombre = sprintf('%s_%s_%s.html', $stamp, $slug, $hash);
    $ruta   = EMAIL_LOG_DIR . $nombre;

    // Listado de adjuntos (se guardan al lado, con el mismo prefijo)
    $listadoAdjuntos = '';
    foreach ($attachments as $i => $a) {
        $contenido = $a['contenido'] ?? (isset($a['ruta']) && is_file($a['ruta']) ? @file_get_contents($a['ruta']) : null);
        if ($contenido === null) continue;
        $nomAdj = sprintf('%s_%s_%s_adj%02d_%s', $stamp, $slug, $hash, $i,
                          preg_replace('/[^A-Za-z0-9._-]+/', '_', $a['nombre'] ?? "adj{$i}.bin"));
        @file_put_contents(EMAIL_LOG_DIR . $nomAdj, $contenido);
        $listadoAdjuntos .= '<li><a href="' . htmlspecialchars($nomAdj) . '">' . htmlspecialchars($a['nombre'] ?? $nomAdj) . '</a></li>';
    }

    $envoltorio = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>'
        . htmlspecialchars($asuntoCompleto) . '</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#FDFBF7;padding:1rem;}'
        . '.log-meta{background:#fff;border:1px solid #2C4C3B;padding:1rem;border-radius:8px;margin-bottom:1.5rem;font-size:.85rem;color:#555}'
        . '.log-meta strong{color:#2C4C3B;}</style></head><body>'
        . '<div class="log-meta"><strong>📧 Email guardado localmente (modo demo)</strong><br>'
        . '<strong>Para:</strong> ' . htmlspecialchars($to) . '<br>'
        . '<strong>De:</strong> ' . htmlspecialchars(EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDR . '>') . '<br>'
        . '<strong>Asunto:</strong> ' . htmlspecialchars($asuntoCompleto) . '<br>'
        . '<strong>Fecha:</strong> ' . date('d/m/Y H:i:s')
        . ($listadoAdjuntos ? '<br><strong>Adjuntos:</strong><ul style="margin:.3rem 0 0 1.2rem;padding:0">' . $listadoAdjuntos . '</ul>' : '')
        . '</div>'
        . $htmlBody
        . '</body></html>';

    if (@file_put_contents($ruta, $envoltorio) !== false) {
        $r['ok']       = true;
        $r['ruta_log'] = $nombre;
    } else {
        $r['error'] = 'No se pudo escribir el log del email en ' . EMAIL_LOG_DIR;
    }
    return $r;
}


// ────────────────────────────────────────────────────────────────────────────
// PLANTILLA BASE REUTILIZABLE — emailWrapper()
// ────────────────────────────────────────────────────────────────────────────
/**
 * Devuelve el HTML completo de un email envuelto en cabecera + footer.
 *
 * @param string $titulo      Título grande (h1 dentro de la cabecera).
 * @param string $subtitulo   Subtítulo en mayúsculas (ej. "Compra confirmada").
 * @param string $cuerpoHtml  HTML interno (lo que va entre cabecera y footer).
 * @param string $variant     'verde' (por defecto), 'dorado' o 'rojo'.
 */
function emailWrapper(string $titulo, string $subtitulo, string $cuerpoHtml, string $variant = 'verde'): string
{
    $cabecera = match ($variant) {
        'rojo'   => 'background:linear-gradient(135deg,#8B5A2B,#6B4220);',
        'dorado' => 'background:linear-gradient(135deg,#D4AF37,#B8962E);',
        default  => 'background:linear-gradient(135deg,#2C4C3B,#1E3529);border-bottom:3px solid #D4AF37;',
    };

    return '
<table width="100%" cellspacing="0" cellpadding="0" style="background:#FDFBF7;font-family:Georgia,Arial,Helvetica,serif;padding:24px 0;">
  <tr><td align="center">
    <table width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(44,76,59,.08);">

      <tr><td style="' . $cabecera . 'padding:32px;text-align:center;color:#fff;">
        <div style="font-size:36px;line-height:1;">🫒</div>
        <h1 style="margin:8px 0 4px;font-family:Georgia,serif;font-weight:600;font-size:22px;">' . htmlspecialchars($titulo) . '</h1>
        <p style="margin:0;color:rgba(255,255,255,.8);font-size:13px;letter-spacing:.05em;text-transform:uppercase;">' . htmlspecialchars($subtitulo) . '</p>
      </td></tr>

      <tr><td style="padding:30px 36px;color:#333;line-height:1.6;font-size:15px;font-family:Georgia,serif;">'
      . $cuerpoHtml .
      '</td></tr>

      <tr><td style="background:#1E3529;padding:18px 36px;text-align:center;color:rgba(255,255,255,.7);font-size:12px;font-family:Arial,Helvetica,sans-serif;">
        <strong style="color:#D4AF37;">Cooperativa San Juan Bautista, S.C.A.</strong><br>
        Aceite de Oliva Virgen Extra · Herrera del Duque (Badajoz)<br>
        <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:rgba(255,255,255,.85);">' . EMAIL_REPLY_TO . '</a>
        · <a href="tel:+34924000000" style="color:rgba(255,255,255,.85);">+34 924 00 00 00</a><br>
        <span style="opacity:.6;display:inline-block;margin-top:6px;">Este correo es automático, no respondas directamente.</span>
      </td></tr>

    </table>
  </td></tr>
</table>';
}


// ────────────────────────────────────────────────────────────────────────────
// PLANTILLA — CONFIRMACIÓN DE COMPRA (con factura adjunta)
// ────────────────────────────────────────────────────────────────────────────
/**
 * @param array $pedido   ['id'=>int, 'fecha'=>string, 'total'=>float]
 * @param array $lineas   [['nombre'=>..., 'cantidad'=>int, 'subtotal'=>float], …]
 * @param array $usuario  ['nombre'=>..., 'email'=>...]
 */
function emailConfirmacionCompra(array $pedido, array $lineas, array $usuario): string
{
    $numFactura  = 'FAC-' . str_pad((string) $pedido['id'], 6, '0', STR_PAD_LEFT);
    $fecha       = date('d/m/Y H:i', strtotime($pedido['fecha']));
    $base        = (float) $pedido['total'];
    $iva         = round($base * 0.10, 2);
    $totalConIva = round($base + $iva, 2);
    $numItems    = array_sum(array_column($lineas, 'cantidad'));

    $filasLineas = '';
    foreach ($lineas as $l) {
        $filasLineas .= '
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #EDE6D8;">' . htmlspecialchars($l['nombre']) . '</td>
          <td style="padding:8px 0;border-bottom:1px solid #EDE6D8;text-align:center;">' . (int) $l['cantidad'] . '</td>
          <td style="padding:8px 0;border-bottom:1px solid #EDE6D8;text-align:right;">' . number_format($l['subtotal'], 2, ',', '.') . ' €</td>
        </tr>';
    }

    $cuerpo = '
        <p style="margin:0 0 16px;">Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
        <p style="margin:0 0 16px;">¡Gracias por confiar en nosotros! Hemos recibido tu pago y tu pedido ha sido <strong style="color:#2D8B4E;">confirmado</strong> correctamente.</p>
        <p style="margin:0 0 22px;">Adjuntamos la <strong>factura en PDF</strong> a este correo, también puedes consultarla en tu panel de cliente.</p>

        <table width="100%" cellspacing="0" cellpadding="0" style="margin:22px 0;background:#F5F0E8;border-left:4px solid #D4AF37;border-radius:8px;">
          <tr><td style="padding:18px 22px;">
            <p style="margin:0 0 4px;font-size:11px;letter-spacing:.08em;color:#888;text-transform:uppercase;font-weight:600;">Número de factura</p>
            <p style="margin:0 0 16px;font-family:Georgia,serif;font-size:22px;font-weight:700;color:#B8962E;">' . $numFactura . '</p>

            <table width="100%" cellspacing="0" cellpadding="6" style="font-size:14px;color:#333;">
              <tr><td style="color:#888;width:130px;">📅 Fecha</td><td><strong>' . $fecha . '</strong></td></tr>
              <tr><td style="color:#888;">📦 Artículos</td><td><strong>' . $numItems . '</strong></td></tr>
              <tr><td style="color:#888;">💳 Método</td><td><strong>Stripe (tarjeta)</strong></td></tr>
            </table>
          </td></tr>
        </table>

        <h3 style="margin:24px 0 8px;color:#2C4C3B;font-family:Georgia,serif;font-size:16px;">Resumen del pedido</h3>
        <table width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;color:#333;">
          <thead>
            <tr style="background:#2C4C3B;color:#fff;">
              <th style="padding:8px 10px;text-align:left;border-radius:6px 0 0 6px;">Producto</th>
              <th style="padding:8px 0;text-align:center;">Cant.</th>
              <th style="padding:8px 10px;text-align:right;border-radius:0 6px 6px 0;">Subtotal</th>
            </tr>
          </thead>
          <tbody>' . $filasLineas . '</tbody>
        </table>

        <table width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0 24px;font-size:14px;color:#333;">
          <tr><td style="text-align:right;color:#666;padding:2px 4px;">Base imponible:</td>
              <td style="text-align:right;width:120px;padding:2px 4px;">' . number_format($base, 2, ',', '.') . ' €</td></tr>
          <tr><td style="text-align:right;color:#666;padding:2px 4px;">IVA (10%):</td>
              <td style="text-align:right;padding:2px 4px;">' . number_format($iva, 2, ',', '.') . ' €</td></tr>
          <tr><td style="text-align:right;font-weight:700;color:#2C4C3B;font-size:16px;padding:8px 4px 2px;border-top:2px solid #D4AF37;">TOTAL:</td>
              <td style="text-align:right;font-weight:700;color:#2C4C3B;font-size:16px;padding:8px 4px 2px;border-top:2px solid #D4AF37;">' . number_format($totalConIva, 2, ',', '.') . ' €</td></tr>
        </table>

        <p style="text-align:center;margin:28px 0 12px;">
          <a href="' . APP_URL . '/tienda.php" style="display:inline-block;background:#2C4C3B;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-family:Arial,Helvetica,sans-serif;font-weight:600;">Volver a la tienda</a>
        </p>
        <p style="margin:0;color:#666;font-size:13px;text-align:center;">¿Alguna duda? Escríbenos a <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:#B8962E;">' . EMAIL_REPLY_TO . '</a></p>';

    return emailWrapper('Cooperativa San Juan Bautista', 'Compra confirmada', $cuerpo);
}


// ────────────────────────────────────────────────────────────────────────────
// PLANTILLA — BIENVENIDA TRAS REGISTRO
// ────────────────────────────────────────────────────────────────────────────
function emailBienvenidaUsuario(array $usuario): string
{
    $cuerpo = '
        <p style="margin:0 0 16px;">Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
        <p style="margin:0 0 16px;">¡Bienvenido a la <strong>Cooperativa San Juan Bautista</strong>! Acabas de registrarte y nos alegra mucho tenerte con nosotros.</p>
        <p style="margin:0 0 16px;">Desde tu cuenta podrás:</p>

        <table width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;background:#F5F0E8;border-left:4px solid #D4AF37;border-radius:8px;">
          <tr><td style="padding:16px 22px;">
            <ul style="margin:0;padding-left:18px;color:#444;font-size:14px;line-height:1.8;">
              <li>🛒 Comprar nuestros aceites de oliva virgen extra premium.</li>
              <li>📅 Reservar visitas guiadas a la almazara.</li>
              <li>📰 Estar al día de campañas, novedades y eventos.</li>
              <li>📄 Descargar tus facturas en cualquier momento.</li>
            </ul>
          </td></tr>
        </table>

        <p style="text-align:center;margin:28px 0 12px;">
          <a href="' . APP_URL . '/tienda.php" style="display:inline-block;background:#D4AF37;color:#2C4C3B;text-decoration:none;padding:12px 28px;border-radius:6px;font-family:Arial,Helvetica,sans-serif;font-weight:700;">Empezar a comprar</a>
        </p>

        <p style="margin:24px 0 0;color:#555;font-size:14px;">Si no fuiste tú quien creó esta cuenta, escríbenos a
            <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:#B8962E;">' . EMAIL_REPLY_TO . '</a>
            y la eliminaremos.
        </p>';

    return emailWrapper('¡Bienvenido!', 'Tu cuenta ha sido creada', $cuerpo, 'dorado');
}


// ────────────────────────────────────────────────────────────────────────────
// PLANTILLA — ALBARÁN DE ENTREGA REGISTRADO (admin)
// ────────────────────────────────────────────────────────────────────────────
/**
 * @param array $entrega ['id'=>int, 'fecha_entrega'=>'Y-m-d', 'kilos_aceituna'=>float,
 *                        'rendimiento'=>float, 'litros_aceite'=>float|null,
 *                        'observaciones'=>string|null, 'campana'=>string|null,
 *                        'precio_por_kilo'=>float|null]
 * @param array $socio   ['nombre'=>..., 'apellidos'=>..., 'dni'=>...]
 */
function emailEntregaRegistrada(array $entrega, array $socio): string
{
    $codigo      = 'ALB-' . str_pad((string) $entrega['id'], 6, '0', STR_PAD_LEFT);
    $fechaTexto  = formatearFechaEs($entrega['fecha_entrega']);
    $kilos       = number_format((float) $entrega['kilos_aceituna'], 2, ',', '.');
    $rendimiento = number_format((float) $entrega['rendimiento'], 2, ',', '.');
    $litros      = isset($entrega['litros_aceite']) ? number_format((float) $entrega['litros_aceite'], 2, ',', '.') : '—';
    $campana     = $entrega['campana'] ?? '—';

    $liquidacion = '';
    if (!empty($entrega['precio_por_kilo'])) {
        $importe = round((float) $entrega['kilos_aceituna'] * (float) $entrega['precio_por_kilo'], 2);
        $liquidacion = '<tr><td style="color:#888;">💶 Liquidación est.</td><td><strong>' . number_format($importe, 2, ',', '.') . ' €</strong> <span style="color:#888;font-size:12px;">(estimada)</span></td></tr>';
    }

    $cuerpo = '
        <p style="margin:0 0 16px;">Hola <strong>' . htmlspecialchars($socio['nombre']) . '</strong>,</p>
        <p style="margin:0 0 16px;">Tu entrega de aceituna del <strong>' . $fechaTexto . '</strong> ha sido <strong style="color:#2D8B4E;">registrada</strong> en nuestra almazara.</p>
        <p style="margin:0 0 22px;">Adjuntamos el <strong>albarán en PDF</strong> firmado, guárdalo como justificante. También podrás verlo en tu panel de socio.</p>

        <table width="100%" cellspacing="0" cellpadding="0" style="margin:22px 0;background:#F5F0E8;border-left:4px solid #D4AF37;border-radius:8px;">
          <tr><td style="padding:18px 22px;">
            <p style="margin:0 0 4px;font-size:11px;letter-spacing:.08em;color:#888;text-transform:uppercase;font-weight:600;">Código de albarán</p>
            <p style="margin:0 0 16px;font-family:Georgia,serif;font-size:22px;font-weight:700;color:#B8962E;">' . $codigo . '</p>

            <table width="100%" cellspacing="0" cellpadding="6" style="font-size:14px;color:#333;">
              <tr><td style="color:#888;width:160px;">🌿 Campaña</td><td><strong>' . htmlspecialchars((string) $campana) . '</strong></td></tr>
              <tr><td style="color:#888;">⚖️ Aceituna entregada</td><td><strong>' . $kilos . ' kg</strong></td></tr>
              <tr><td style="color:#888;">📊 Rendimiento</td><td><strong>' . $rendimiento . ' %</strong></td></tr>
              <tr><td style="color:#888;">🫒 AOVE estimado</td><td><strong>' . $litros . ' L</strong></td></tr>
              ' . $liquidacion . '
            </table>
          </td></tr>
        </table>

        <p style="margin:0 0 16px;color:#555;font-size:14px;">Los datos analíticos definitivos se confirmarán tras el proceso de molturación. Si detectas alguna diferencia, escríbenos a <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:#B8962E;">' . EMAIL_REPLY_TO . '</a>.</p>';

    return emailWrapper('Entrega registrada', 'Albarán ' . $codigo, $cuerpo);
}


// ────────────────────────────────────────────────────────────────────────────
// PLANTILLAS DE VISITAS — retrocompatibilidad con código existente
// ────────────────────────────────────────────────────────────────────────────
function emailConfirmacionVisita(array $v): string
{
    $codigo  = 'VST-' . str_pad((string) $v['id'], 6, '0', STR_PAD_LEFT);
    $fecha   = formatearFechaEs($v['fecha_visita']);
    $hora    = date('H:i', strtotime($v['hora_visita']));
    $tipo    = match ($v['tipo_visita']) {
        'cata'     => 'Cata guiada',
        'almazara' => 'Visita a la almazara',
        'completa' => 'Visita completa (almazara + cata)',
        default    => $v['tipo_visita'],
    };
    $duracion = match ($v['tipo_visita']) {
        'cata'     => '~45 minutos',
        'almazara' => '~45 minutos',
        'completa' => '~90 minutos',
        default    => '',
    };

    $cuerpo = '
        <p style="margin:0 0 16px;">Hola <strong>' . htmlspecialchars($v['nombre']) . '</strong>,</p>
        <p style="margin:0 0 16px;">¡Tenemos buenas noticias! Tu reserva en nuestra almazara ha sido <strong style="color:#2D8B4E;">confirmada</strong>. Te esperamos con muchas ganas para mostrarte de cerca cómo nace nuestro AOVE.</p>

        <table width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;background:#F5F0E8;border-left:4px solid #D4AF37;border-radius:8px;">
          <tr><td style="padding:18px 22px;">
            <p style="margin:0 0 4px;font-size:11px;letter-spacing:.08em;color:#888;text-transform:uppercase;font-weight:600;">Código de reserva</p>
            <p style="margin:0 0 14px;font-family:Georgia,serif;font-size:22px;font-weight:700;color:#B8962E;">' . $codigo . '</p>
            <table width="100%" cellspacing="0" cellpadding="6" style="font-size:14px;">
              <tr><td style="color:#888;width:130px;">📅 Fecha</td><td><strong>' . $fecha . '</strong></td></tr>
              <tr><td style="color:#888;">🕐 Hora</td><td><strong>' . $hora . '</strong></td></tr>
              <tr><td style="color:#888;">👥 Personas</td><td><strong>' . (int) $v['num_personas'] . '</strong></td></tr>
              <tr><td style="color:#888;">🍃 Experiencia</td><td><strong>' . htmlspecialchars($tipo) . '</strong> <span style="color:#888;font-size:12px;">(' . $duracion . ')</span></td></tr>
            </table>
          </td></tr>
        </table>

        <p style="margin:0 0 8px;font-weight:600;color:#2C4C3B;">Antes de venir:</p>
        <ul style="margin:0 0 16px 18px;padding:0;color:#555;font-size:14px;">
          <li>Llega 10 minutos antes para registrarte cómodamente.</li>
          <li>Calzado cerrado y cómodo (visitarás zona de elaboración).</li>
          <li>Si necesitas cancelar o cambiar la reserva, escríbenos al menos 24 h antes a <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:#B8962E;">' . EMAIL_REPLY_TO . '</a>.</li>
        </ul>
        <p style="margin:24px 0 4px;color:#555;">Estamos en <strong>Ctra. de la Almazara, s/n · 06670 Herrera del Duque (Badajoz)</strong>.</p>';

    return emailWrapper('Cooperativa San Juan Bautista', 'Reserva confirmada', $cuerpo);
}


function emailCancelacionVisita(array $v): string
{
    $codigo = 'VST-' . str_pad((string) $v['id'], 6, '0', STR_PAD_LEFT);
    $fecha  = date('d/m/Y', strtotime($v['fecha_visita']));
    $hora   = date('H:i',   strtotime($v['hora_visita']));

    $cuerpo = '
        <p style="margin:0 0 16px;">Hola <strong>' . htmlspecialchars($v['nombre']) . '</strong>,</p>
        <p style="margin:0 0 16px;">Lamentamos informarte que tu reserva con código <strong>' . $codigo . '</strong> para el <strong>' . $fecha . ' a las ' . $hora . '</strong> ha sido <strong style="color:#C0392B;">cancelada</strong>.</p>
        <p style="margin:0 0 16px;">Si crees que se trata de un error o quieres hacer otra reserva, contacta con nosotros en <a href="mailto:' . EMAIL_REPLY_TO . '" style="color:#B8962E;">' . EMAIL_REPLY_TO . '</a> o por teléfono al <strong>+34 924 00 00 00</strong>.</p>
        <p style="margin:0;color:#555;">Sentimos las molestias y esperamos verte pronto.</p>';

    return emailWrapper('Cooperativa San Juan Bautista', 'Reserva cancelada', $cuerpo, 'rojo');
}
