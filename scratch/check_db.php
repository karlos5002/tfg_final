<?php
require '../config/db.php';
$pdo = getConexion();
$stmt = $pdo->query('SELECT id, nombre, apellidos FROM usuarios');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
