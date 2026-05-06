<?php
/**
 * Script temporal — Actualiza la columna `imagen` de cada producto
 * para que apunte a su imagen única generada.
 */
require_once __DIR__ . '/../config/db.php';

$pdo = getConexion();

$updates = [
    // Productos originales
    1 => 'aceite-picual.png',        // Garrafa AOVE 5L (Picual)
    2 => 'aceite-arbequina.png',     // Botella Cristal Premium 500ml (Arbequina)
    3 => 'aceite-coupage.png',       // Pack Degustacion 3x250ml (Coupage)
    4 => 'aceite-hojiblanca.png',    // Garrafa Hojiblanca 2L (Hojiblanca)
    // Productos extremeños
    5 => 'aceite-manzanilla.png',    // Manzanilla Cacereña 500ml
    6 => 'aceite-verdial.png',       // Verdial de Badajoz 2L
    7 => 'aceite-cornezuelo.png',    // Cornezuelo Tierra de Barros 500ml
    8 => 'aceite-morisca.png',       // Morisca DOP Monterrubio 750ml
    9 => 'aceite-carrasquena.png',   // Carrasqueña Centenaria 250ml
];

$stmt = $pdo->prepare('UPDATE productos SET imagen = ? WHERE id = ?');

echo "=== ACTUALIZANDO IMÁGENES ===" . PHP_EOL;
foreach ($updates as $id => $imagen) {
    $stmt->execute([$imagen, $id]);
    $affected = $stmt->rowCount();
    $status = $affected > 0 ? '✅ Actualizado' : '⚠️ Sin cambios (ya tenía esa imagen o ID no existe)';
    echo "  ID {$id} → {$imagen}  {$status}" . PHP_EOL;
}

// Verificación final
echo PHP_EOL . "=== VERIFICACIÓN FINAL ===" . PHP_EOL;
$check = $pdo->query('SELECT id, nombre, imagen FROM productos ORDER BY id');
foreach ($check->fetchAll() as $row) {
    $path = __DIR__ . '/../assets/img/' . $row['imagen'];
    $existe = file_exists($path) ? '✅' : '❌';
    echo "  [{$row['id']}] {$row['nombre']} → {$row['imagen']} {$existe}" . PHP_EOL;
}
