<?php
/**
 * ============================================================================
 * API: Estadísticas del Dashboard (api/estadisticas.php)
 * ============================================================================
 * Devuelve un JSON con los datos necesarios para los gráficos y tarjetas
 * del panel de administración.
 *
 * Seguridad:
 *   - Solo accesible para usuarios con rol 'admin'
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Acceso denegado.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getConexion();

    // 1. Resumen financiero
    $stmtResumen = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN estado IN ('pagado','procesando','enviado','entregado') THEN total ELSE 0 END), 0) AS total_facturado,
            COUNT(*) AS total_pedidos
        FROM pedidos
    ");
    $resumen = $stmtResumen->fetch();

    // 2. Ventas por variedad (gráfico Doughnut)
    $stmtVentas = $pdo->query("
        SELECT p.variedad,
               COALESCE(SUM(lp.cantidad), 0) AS total_vendido
        FROM lineas_pedido lp
        INNER JOIN productos p ON lp.id_producto = p.id
        GROUP BY p.variedad
        ORDER BY total_vendido DESC
    ");
    $ventasVariedad = $stmtVentas->fetchAll();

    // 3. Kilos por mes (gráfico de Barras)
    $stmtKilos = $pdo->query("
        SELECT
            DATE_FORMAT(fecha_entrega, '%m/%Y') AS etiqueta,
            SUM(kilos_aceituna)                 AS total_kilos
        FROM entregas
        GROUP BY YEAR(fecha_entrega), MONTH(fecha_entrega)
        ORDER BY YEAR(fecha_entrega), MONTH(fecha_entrega)
    ");
    $kilosPorMes = $stmtKilos->fetchAll();

    // Respuesta JSON
    echo json_encode([
        'resumen'          => [
            'total_facturado' => (float) $resumen['total_facturado'],
            'total_pedidos'   => (int)   $resumen['total_pedidos'],
        ],
        'ventas_variedad'  => $ventasVariedad,
        'kilos_por_mes'    => $kilosPorMes,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Error en api/estadisticas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error al consultar la base de datos.']);
}
