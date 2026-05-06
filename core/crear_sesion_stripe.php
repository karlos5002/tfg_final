<?php
// ============================================================================
// CREACIÓN DE SESIÓN STRIPE CHECKOUT
// ============================================================================
// Recibe (vía AJAX desde el botón "Finalizar compra"):
//   - El carrito de $_SESSION['carrito']
// Devuelve JSON:
//   { ok: true,  url: "https://checkout.stripe.com/c/pay/cs_test_..." }
//   { ok: false, mensaje: "..." }
//
// Importante:
//   * NO se inserta nada en la BD aquí. La inserción ocurre en
//     procesar_compra.php SOLO después de que Stripe confirme el pago.
//   * Los precios se recalculan desde la tabla `productos`: nunca confiar
//     en lo que viene en $_SESSION['carrito'] (un atacante podría haberlo
//     manipulado vía DevTools para pagar 0,01€).
// ============================================================================

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/stripe_config.php';
require_once __DIR__ . '/../vendor/init_libs.php'; // Carga Stripe SDK + PHPMailer

// ── Validaciones previas ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debes iniciar sesión para pagar.']);
    exit;
}

if (empty($_SESSION['carrito'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'El carrito está vacío.']);
    exit;
}

$idUsuario = (int) $_SESSION['usuario_id'];
$carrito   = $_SESSION['carrito'];

try {
    // ── Recalcular precios y construir line_items para Stripe ─────────────
    $pdo = getConexion();

    $stmt = $pdo->prepare('
        SELECT id, nombre, precio, stock
        FROM productos
        WHERE id = :id AND activo = 1
        LIMIT 1
    ');

    $lineItems = []; // formato exigido por Stripe Checkout

    foreach ($carrito as $idProducto => $item) {
        $stmt->execute([':id' => $idProducto]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new Exception("El producto \"{$item['nombre']}\" ya no está disponible.");
        }
        if ($producto['stock'] < $item['cantidad']) {
            throw new Exception("Stock insuficiente para \"{$producto['nombre']}\". " .
                                "Pedidos: {$item['cantidad']}, disponible: {$producto['stock']}.");
        }

        // Stripe espera el importe en céntimos (entero). 12,50 € → 1250.
        $precioCentimos = (int) round(((float) $producto['precio']) * 100);

        $lineItems[] = [
            'price_data' => [
                'currency'     => STRIPE_CURRENCY,
                'product_data' => [
                    'name' => $producto['nombre'],
                ],
                'unit_amount'  => $precioCentimos,
            ],
            'quantity' => (int) $item['cantidad'],
        ];
    }

    // ── Configurar el SDK de Stripe con la clave secreta ──────────────────
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    // ── Crear la Checkout Session ────────────────────────────────────────
    // Stripe Checkout es la pasarela "hosted": el formulario de tarjeta lo
    // muestra Stripe en su propio dominio (PCI-compliant), no nuestro PHP.
    $sesion = \Stripe\Checkout\Session::create([
        'mode'                 => 'payment',
        'payment_method_types' => ['card'],
        'line_items'           => $lineItems,
        'locale'               => STRIPE_LOCALE,
        'success_url'          => STRIPE_SUCCESS_URL,
        'cancel_url'           => STRIPE_CANCEL_URL,
        // metadata.id_usuario nos permite (en un futuro) auditar pagos
        // desde el dashboard de Stripe sin depender solo de la BD.
        'metadata' => [
            'id_usuario' => $idUsuario,
        ],
    ]);

    // Guardamos el ID de sesión en $_SESSION como salvaguarda extra: si el
    // usuario manipula la URL al volver, podemos comparar.
    $_SESSION['stripe_session_id'] = $sesion->id;

    echo json_encode([
        'ok'  => true,
        'url' => $sesion->url, // URL hosteada por Stripe a la que redirigir
        'id'  => $sesion->id,
    ]);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Error específico del API de Stripe (clave inválida, parámetros, etc.)
    error_log('Stripe API error en crear_sesion_stripe.php: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'No se pudo conectar con la pasarela de pago. Inténtalo de nuevo.',
    ]);
    exit;

} catch (Exception $e) {
    // Errores de validación (stock, producto inactivo, etc.)
    error_log('Error en crear_sesion_stripe.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'ok'      => false,
        'mensaje' => $e->getMessage(),
    ]);
    exit;
}
