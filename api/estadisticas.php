<?php
// JSON con datos para los gráficos del dashboard de admin (Chart.js).
// Se llama por fetch desde admin.php.

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Acceso denegado. Se requiere rol de administrador.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getConexion();

    // Total facturado: solo contabilizamos pedidos en estado 'pagado'
    $totalFacturado = (float) $pdo->query("
        SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado = 'pagado'
    ")->fetchColumn();

    $totalPedidos = (int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();

    // Doughnut: unidades vendidas por variedad (sólo en pedidos pagados)
    $ventasVariedad = $pdo->query("
        SELECT p.variedad, SUM(lp.cantidad) AS total_vendido
        FROM lineas_pedido lp
        INNER JOIN productos p ON lp.id_producto = p.id
        INNER JOIN pedidos  ped ON lp.id_pedido   = ped.id
        WHERE ped.estado = 'pagado'
        GROUP BY p.variedad
        ORDER BY total_vendido DESC
    ")->fetchAll();

    // Barras: kilos de aceituna por mes/año (cronológico)
    $kilosPorMes = $pdo->query("
        SELECT YEAR(fecha_entrega)  AS anio,
               MONTH(fecha_entrega) AS mes,
               DATE_FORMAT(fecha_entrega, '%b %Y') AS etiqueta,
               SUM(kilos_aceituna)  AS total_kilos
        FROM entregas
        GROUP BY anio, mes, etiqueta
        ORDER BY anio ASC, mes ASC
    ")->fetchAll();

    $statsEntregas = $pdo->query('
        SELECT COALESCE(SUM(kilos_aceituna), 0) AS total_kilos,
               COALESCE(SUM(litros_aceite),  0) AS total_litros,
               COUNT(*)                         AS total_entregas,
               COUNT(DISTINCT id_socio)         AS socios_activos
        FROM entregas
    ')->fetch();

    echo json_encode([
        'error'   => false,
        'resumen' => [
            'total_facturado' => $totalFacturado,
            'total_pedidos'   => $totalPedidos,
            'total_kilos'     => (float) $statsEntregas['total_kilos'],
            'total_litros'    => (float) $statsEntregas['total_litros'],
            'total_entregas'  => (int)   $statsEntregas['total_entregas'],
            'socios_activos'  => (int)   $statsEntregas['socios_activos'],
        ],
        'ventas_variedad' => $ventasVariedad,
        'kilos_por_mes'   => $kilosPorMes,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Error en estadisticas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'mensaje' => 'Error interno del servidor al obtener estadísticas.']);
}
