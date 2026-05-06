<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();
$s = $pdo->query('SELECT id, nombre, slug, variedad, descripcion, precio, stock, imagen FROM productos ORDER BY id');
foreach ($s as $r) {
    echo $r['id'] . ' | ' . $r['nombre'] . ' | ' . $r['slug'] . ' | ' . $r['variedad'] . ' | ' . $r['precio'] . ' | ' . $r['stock'] . ' | ' . $r['imagen'] . PHP_EOL;
}
