<?php
/**
 * Script de verificación temporal — Comprueba productos en BD
 */
require_once __DIR__ . '/../config/db.php';

$pdo = getConexion();

// 1) Total productos activos con stock
$stmt = $pdo->query('SELECT id, nombre, variedad, precio, stock, imagen FROM productos WHERE activo = 1 AND stock > 0 ORDER BY variedad, nombre');
$rows = $stmt->fetchAll();

echo "=== VERIFICACIÓN DE CATÁLOGO ===" . PHP_EOL;
echo "Total productos activos con stock: " . count($rows) . PHP_EOL . PHP_EOL;

echo str_pad('ID', 4) . str_pad('Nombre', 42) . str_pad('Variedad', 16) . str_pad('Precio', 10) . str_pad('Stock', 8) . 'Imagen' . PHP_EOL;
echo str_repeat('-', 110) . PHP_EOL;

$stockTotal = 0;
foreach ($rows as $r) {
    echo str_pad($r['id'], 4)
       . str_pad($r['nombre'], 42)
       . str_pad($r['variedad'], 16)
       . str_pad(number_format($r['precio'], 2, ',', '.') . ' €', 10)
       . str_pad($r['stock'], 8)
       . $r['imagen'] . PHP_EOL;
    $stockTotal += $r['stock'];
}

echo PHP_EOL . "Stock total: " . $stockTotal . " unidades" . PHP_EOL;

// 2) Variedades únicas
$vars = array_unique(array_column($rows, 'variedad'));
sort($vars);
echo "Variedades únicas: " . count($vars) . " → " . implode(', ', $vars) . PHP_EOL;

// 3) Verificar ENUM actual
$stmtEnum = $pdo->query("SHOW COLUMNS FROM productos LIKE 'variedad'");
$enumInfo = $stmtEnum->fetch();
echo PHP_EOL . "ENUM de variedad en BD: " . $enumInfo['Type'] . PHP_EOL;

// 4) Verificar imagen existe
$imagenes = array_unique(array_column($rows, 'imagen'));
echo PHP_EOL . "=== VERIFICACIÓN DE IMÁGENES ===" . PHP_EOL;
foreach ($imagenes as $img) {
    $path = __DIR__ . '/../assets/img/' . $img;
    $existe = file_exists($path) ? '✅ Existe' : '❌ NO EXISTE';
    echo "  {$img} → {$existe}" . PHP_EOL;
}

echo PHP_EOL . "=== VERIFICACIÓN COMPLETA ===" . PHP_EOL;
