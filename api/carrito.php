<?php
// Carrito basado en sesión. Acciones: añadir, eliminar, actualizar, obtener.
// $_SESSION['carrito'] = [ id_producto => ['cantidad','nombre','precio'] ]

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => t('api.cart.method_not_allowed')]);
    exit;
}

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Soporta JSON (caso normal) y FormData (fallback)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input      = json_decode(file_get_contents('php://input'), true);
    $accion     = $input['accion']      ?? 'añadir';
    $idProducto = (int) ($input['id_producto'] ?? 0);
    $cantidad   = (int) ($input['cantidad']    ?? 1);
} else {
    $accion     = $_POST['accion']      ?? 'añadir';
    $idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad   = filter_input(INPUT_POST, 'cantidad',    FILTER_VALIDATE_INT) ?: 1;
}

switch ($accion) {

    case 'añadir':
        if (!$idProducto || $idProducto <= 0) {
            responder(false, t('api.cart.invalid_id'));
        }
        try {
            $pdo  = getConexion();
            $stmt = $pdo->prepare('
                SELECT id, nombre, precio, stock
                FROM productos
                WHERE id = :id AND activo = 1
                LIMIT 1
            ');
            $stmt->execute([':id' => $idProducto]);
            $producto = $stmt->fetch();

            if (!$producto) {
                responder(false, t('api.cart.product_not_found'));
            }

            $cantidadActual = $_SESSION['carrito'][$idProducto]['cantidad'] ?? 0;
            $cantidadNueva  = $cantidadActual + 1;

            if ($cantidadNueva > $producto['stock']) {
                responder(false, tf('api.cart.no_stock', (int) $producto['stock']));
            }

            $_SESSION['carrito'][$idProducto] = [
                'cantidad' => $cantidadNueva,
                'nombre'   => $producto['nombre'],
                'precio'   => (float) $producto['precio'],
            ];

            responder(true, t('api.cart.added_ok'), [
                'producto'      => $producto['nombre'],
                'cantidad'      => $cantidadNueva,
                'precio'        => (float) $producto['precio'],
                'total_items'   => contarItemsCarrito(),
                'total_importe' => calcularTotalCarrito(),
            ]);

        } catch (PDOException $e) {
            error_log('API Carrito — Error al añadir: ' . $e->getMessage());
            responder(false, t('api.cart.server_error'));
        }
        break;

    case 'eliminar':
        if (!$idProducto || !isset($_SESSION['carrito'][$idProducto])) {
            responder(false, t('api.cart.not_in_cart'));
        }
        $nombre = $_SESSION['carrito'][$idProducto]['nombre'];
        unset($_SESSION['carrito'][$idProducto]);

        responder(true, tf('api.cart.removed_ok', $nombre), [
            'total_items'   => contarItemsCarrito(),
            'total_importe' => calcularTotalCarrito(),
        ]);
        break;

    case 'actualizar':
        if (!$idProducto || !isset($_SESSION['carrito'][$idProducto])) {
            responder(false, t('api.cart.not_in_cart'));
        }
        if ($cantidad <= 0) {
            unset($_SESSION['carrito'][$idProducto]);
        } else {
            // Validar stock disponible antes de fijar la nueva cantidad —
            // si no, el usuario podría usar los botones +/- del offcanvas
            // para superar el stock real y caería al pagar.
            try {
                $pdo  = getConexion();
                $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = :id AND activo = 1 LIMIT 1');
                $stmt->execute([':id' => $idProducto]);
                $stockDisponible = (int) ($stmt->fetchColumn() ?: 0);

                if ($cantidad > $stockDisponible) {
                    responder(false, tf('api.cart.only_units_left', $stockDisponible), [
                        'stock_max' => $stockDisponible,
                    ]);
                }
            } catch (PDOException $e) {
                error_log('API Carrito — Error al validar stock en actualizar: ' . $e->getMessage());
                responder(false, t('api.cart.stock_check_error'));
            }
            $_SESSION['carrito'][$idProducto]['cantidad'] = $cantidad;
        }
        responder(true, t('api.cart.updated'), [
            'total_items'   => contarItemsCarrito(),
            'total_importe' => calcularTotalCarrito(),
        ]);
        break;

    case 'obtener':
        $items = [];
        foreach ($_SESSION['carrito'] as $id => $item) {
            $items[] = [
                'id_producto' => $id,
                'nombre'      => $item['nombre'],
                'precio'      => $item['precio'],
                'cantidad'    => $item['cantidad'],
                'subtotal'    => round($item['precio'] * $item['cantidad'], 2),
            ];
        }
        responder(true, t('api.cart.state'), [
            'items'         => $items,
            'total_items'   => contarItemsCarrito(),
            'total_importe' => calcularTotalCarrito(),
        ]);
        break;

    default:
        responder(false, tf('api.cart.unknown_action', htmlspecialchars($accion)));
}


function contarItemsCarrito(): int
{
    $total = 0;
    foreach ($_SESSION['carrito'] as $item) $total += $item['cantidad'];
    return $total;
}

function calcularTotalCarrito(): float
{
    $total = 0.0;
    foreach ($_SESSION['carrito'] as $item) $total += $item['precio'] * $item['cantidad'];
    return round($total, 2);
}

function responder(bool $ok, string $mensaje, array $datos = []): void
{
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $datos), JSON_UNESCAPED_UNICODE);
    exit;
}
