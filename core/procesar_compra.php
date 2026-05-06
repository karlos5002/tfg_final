<?php
// ============================================================================
// PROCESAR COMPRA — Confirmación post-pago Stripe
// ============================================================================
// Este script SOLO se invoca como `success_url` de Stripe Checkout, una vez
// que el usuario ha completado (o intentado) el pago en la pasarela.
//
// Flujo:
//   1. Stripe redirige a:  procesar_compra.php?session_id=cs_test_...
//   2. Recuperamos la sesión de Stripe (Session::retrieve).
//   3. Si payment_status === 'paid' → insertar pedido en BD (transacción).
//   4. Si no → redirigir al carrito con mensaje de error.
//
// Toda la operación BD (cabecera + líneas + descuento de stock + movimientos)
// va dentro de una transacción: si algo falla, rollBack y la BD queda intacta.
// ============================================================================

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe_config.php';
require_once __DIR__ . '/../vendor/init_libs.php';
require_once __DIR__ . '/factura_pdf.php';
require_once __DIR__ . '/mailer.php';

// ── Validaciones de acceso ───────────────────────────────────────────────
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['login_error'] = 'Debes iniciar sesión para realizar una compra.';
    header('Location: ../index.php');
    exit;
}

// Sin session_id no se puede acceder: ya no se permite "compra directa"
// como antes, todo pago debe pasar por la pasarela Stripe.
$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_DEFAULT,
                          ['options' => ['default' => null]]);
if (!$sessionId || !preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
    $_SESSION['error_compra'] = 'Acceso no autorizado: falta la sesión de pago.';
    header('Location: ../tienda.php');
    exit;
}

if (empty($_SESSION['carrito'])) {
    // Si el carrito está vacío puede ser que el usuario refresque la página
    // de éxito tras haber completado ya la compra → enviarle a su panel.
    header('Location: ../tienda.php');
    exit;
}

$idUsuario = (int) $_SESSION['usuario_id'];
$carrito   = $_SESSION['carrito'];

try {
    // ── 1. Verificar el pago consultando a Stripe ─────────────────────────
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $sesionStripe = \Stripe\Checkout\Session::retrieve($sessionId);

    // Comprobación primaria: ¿el pago ha sido marcado como "paid"?
    if ($sesionStripe->payment_status !== 'paid') {
        throw new Exception('El pago no se ha completado correctamente. ' .
                            'Estado: ' . $sesionStripe->payment_status);
    }

    // Comprobación extra: el id de sesión coincide con el que guardamos
    // al crearla. Evita que un usuario reutilice un session_id antiguo.
    if (isset($_SESSION['stripe_session_id']) &&
        $_SESSION['stripe_session_id'] !== $sessionId) {
        throw new Exception('La sesión de pago no coincide con la esperada.');
    }

    // Comprobación extra: el usuario que vuelve es el mismo que inició
    // el checkout (metadata.id_usuario lo dejamos en crear_sesion_stripe.php).
    if (isset($sesionStripe->metadata->id_usuario) &&
        (int) $sesionStripe->metadata->id_usuario !== $idUsuario) {
        throw new Exception('La sesión de pago pertenece a otro usuario.');
    }

    // ── 2. Insertar el pedido en BD dentro de una transacción ─────────────
    $pdo = getConexion();
    $pdo->beginTransaction();

    // Recalculamos precios desde la BD: nunca confiar en lo que viene en
    // la sesión (un atacante podría haber editado $_SESSION['carrito']).
    $totalPedido = 0.0;
    $lineas      = [];

    $stmtProducto = $pdo->prepare('
        SELECT id, nombre, precio, stock
        FROM productos
        WHERE id = :id AND activo = 1
        LIMIT 1
    ');

    foreach ($carrito as $idProducto => $item) {
        $stmtProducto->execute([':id' => $idProducto]);
        $producto = $stmtProducto->fetch();

        if (!$producto) {
            throw new Exception("El producto \"{$item['nombre']}\" ya no está disponible.");
        }
        if ($producto['stock'] < $item['cantidad']) {
            throw new Exception("Stock insuficiente para \"{$producto['nombre']}\". " .
                                "Pedidos: {$item['cantidad']}, disponible: {$producto['stock']}.");
        }

        $precioReal = (float) $producto['precio'];
        $subtotal   = round($precioReal * $item['cantidad'], 2);

        $lineas[] = [
            'id_producto'     => (int) $idProducto,
            'cantidad'        => (int) $item['cantidad'],
            'precio_unitario' => $precioReal,
            'subtotal'        => $subtotal,
            'nombre'          => $producto['nombre'],
        ];

        $totalPedido += $subtotal;
    }

    $totalPedido = round($totalPedido, 2);

    // Insertar cabecera del pedido. Estado 'pagado' porque Stripe ya nos
    // confirmó el cobro (payment_status === 'paid').
    $stmtPedido = $pdo->prepare('
        INSERT INTO pedidos (id_usuario, total, estado, metodo_pago)
        VALUES (:id_usuario, :total, "pagado", "Stripe (tarjeta)")
    ');
    $stmtPedido->execute([':id_usuario' => $idUsuario, ':total' => $totalPedido]);
    $idPedido = (int) $pdo->lastInsertId();

    // El campo `subtotal` de lineas_pedido es una columna GENERATED en MySQL:
    // se calcula sola, por eso no la insertamos explícitamente.
    $stmtLinea = $pdo->prepare('
        INSERT INTO lineas_pedido (id_pedido, id_producto, cantidad, precio_unitario)
        VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario)
    ');
    foreach ($lineas as $l) {
        $stmtLinea->execute([
            ':id_pedido'       => $idPedido,
            ':id_producto'     => $l['id_producto'],
            ':cantidad'        => $l['cantidad'],
            ':precio_unitario' => $l['precio_unitario'],
        ]);
    }

    // Descontar stock. Importante: con ATTR_EMULATE_PREPARES=false PDO no deja
    // reusar el MISMO placeholder dos veces en una query → :cantidad y
    // :cantidad_min se declaran por separado aunque tengan el mismo valor.
    $stmtStock = $pdo->prepare('
        UPDATE productos
        SET stock = stock - :cantidad
        WHERE id = :id AND stock >= :cantidad_min
    ');

    // Lectura del stock previo antes del UPDATE para registrar stock_antes en
    // el movimiento. Va dentro de la transacción, así que ningún otro pedido
    // puede alterarlo entre ambas sentencias.
    $stmtStockPrevio = $pdo->prepare('SELECT stock FROM productos WHERE id = :id LIMIT 1');

    $stmtMovimiento = $pdo->prepare('
        INSERT INTO movimientos_stock
            (id_producto, tipo, cantidad, stock_antes, stock_despues, motivo, id_pedido, id_usuario)
        VALUES
            (:id_producto, "salida", :cantidad, :stock_antes, :stock_despues,
             :motivo, :id_pedido, :id_usuario)
    ');

    foreach ($lineas as $l) {
        $stmtStockPrevio->execute([':id' => $l['id_producto']]);
        $stockAntes = (int) $stmtStockPrevio->fetchColumn();

        $stmtStock->execute([
            ':cantidad'     => $l['cantidad'],
            ':cantidad_min' => $l['cantidad'],
            ':id'           => $l['id_producto'],
        ]);
        // rowCount=0 significa que otro pedido se llevó las últimas unidades
        // entre nuestro SELECT y este UPDATE — abortamos la transacción.
        if ($stmtStock->rowCount() === 0) {
            throw new Exception("Error al descontar stock de \"{$l['nombre']}\". " .
                                "Puede que otro usuario haya comprado las últimas unidades.");
        }

        // Auditoría: cada línea genera un movimiento 'salida' ligado al pedido.
        $stmtMovimiento->execute([
            ':id_producto'   => $l['id_producto'],
            ':cantidad'      => $l['cantidad'],
            ':stock_antes'   => $stockAntes,
            ':stock_despues' => $stockAntes - $l['cantidad'],
            ':motivo'        => 'Venta — pedido #' . $idPedido,
            ':id_pedido'     => $idPedido,
            ':id_usuario'    => $idUsuario,
        ]);
    }

    $pdo->commit();

    // ── Email post-compra con factura PDF adjunta ─────────────────────────
    // El pedido YA está confirmado en BD; si el envío falla (SMTP caído,
    // PHPMailer no instalado, etc.) NO abortamos: el usuario igualmente
    // tiene su pedido, sólo se pierde la notificación. Logueamos el fallo
    // para que el admin lo revise.
    try {
        [$pedidoFactura, $lineasFactura] = cargarDatosFactura($pdo, $idPedido, $idUsuario, true);
        $pdfFactura = construirFacturaPdf($pedidoFactura, $lineasFactura);

        $usuario = [
            'nombre' => $_SESSION['nombre'] ?? $pedidoFactura['cliente_nombre'],
            'email'  => $pedidoFactura['cliente_email'],
        ];
        $datosCorreo = [
            'id'    => $idPedido,
            'fecha' => $pedidoFactura['fecha_pedido'],
            'total' => $totalPedido,
        ];
        // Líneas en formato simplificado para la plantilla del email.
        $lineasCorreo = array_map(fn($l) => [
            'nombre'   => $l['producto_nombre'] . ' (' . $l['producto_variedad'] . ')',
            'cantidad' => (int) $l['cantidad'],
            'subtotal' => (float) $l['subtotal'],
        ], $lineasFactura);

        $numFactura = 'FAC-' . str_pad((string) $idPedido, 6, '0', STR_PAD_LEFT);

        enviarEmail(
            $usuario['email'],
            'Confirmación de tu compra · ' . $numFactura,
            emailConfirmacionCompra($datosCorreo, $lineasCorreo, $usuario),
            [[
                'contenido' => $pdfFactura,
                'nombre'    => $numFactura . '.pdf',
                'mime'      => 'application/pdf',
            ]]
        );
    } catch (\Throwable $e) {
        error_log('[procesar_compra] No se pudo enviar email de confirmación al cliente: ' . $e->getMessage());
        // No re-lanzamos: la compra ya está cerrada y el usuario debe ver éxito.
    }

    // Vaciar el carrito DESPUÉS del commit: si vaciamos antes y el commit
    // fallara, perderíamos los datos para que el usuario reintente.
    $_SESSION['carrito']      = [];
    unset($_SESSION['stripe_session_id']); // ya consumida, no reutilizar
    $_SESSION['compra_exito'] = [
        'id_pedido' => $idPedido,
        'total'     => $totalPedido,
        'items'     => count($lineas),
    ];

    header('Location: ../exito.php?id_pedido=' . $idPedido);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Error consultando a Stripe (clave inválida, sesión inexistente, etc.)
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Stripe API error en procesar_compra.php: ' . $e->getMessage());
    $_SESSION['error_compra'] = 'No se pudo verificar el pago con la pasarela. ' .
                                'Si se te ha cobrado, contacta con la cooperativa.';
    header('Location: ../tienda.php');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Error en procesar_compra.php: ' . $e->getMessage());
    $_SESSION['error_compra'] = $e->getMessage();
    header('Location: ../tienda.php');
    exit;
}
