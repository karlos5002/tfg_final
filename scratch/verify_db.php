<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();

// Productos
$stmt = $pdo->query('SELECT id, nombre, variedad, precio, stock, imagen FROM productos WHERE activo = 1 ORDER BY id');
$rows = $stmt->fetchAll();
echo "Productos activos: " . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    echo "  [{$r['id']}] {$r['nombre']} | {$r['variedad']} | {$r['precio']}€ | stock:{$r['stock']} | {$r['imagen']}" . PHP_EOL;
}

// Variedades
$vars = array_unique(array_column($rows, 'variedad'));
sort($vars);
echo PHP_EOL . "Variedades: " . count($vars) . " → " . implode(', ', $vars) . PHP_EOL;

// Usuarios (tildes)
echo PHP_EOL . "Usuarios:" . PHP_EOL;
$u = $pdo->query('SELECT id, nombre, apellidos, rol FROM usuarios ORDER BY id');
foreach ($u->fetchAll() as $r) {
    echo "  [{$r['id']}] {$r['nombre']} {$r['apellidos']} ({$r['rol']})" . PHP_EOL;
}

// ENUM
$e = $pdo->query("SHOW COLUMNS FROM productos LIKE 'variedad'")->fetch();
echo PHP_EOL . "ENUM: " . $e['Type'] . PHP_EOL;
