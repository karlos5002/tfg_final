<?php
require 'config/db.php';
$pdo = getConexion();

// Actualizar contraseña del usuario test con hash conocido
$hash = password_hash('1234', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE usuarios SET password = :p WHERE email = :e');
$stmt->execute([':p' => $hash, ':e' => 'test@test.com']);
echo "Password updated for test@test.com\n";
echo "Verify: " . (password_verify('1234', $hash) ? 'OK' : 'FAIL') . "\n";

// También actualizar juan@test.com
$stmt->execute([':p' => $hash, ':e' => 'juan@test.com']);
echo "Password updated for juan@test.com\n";
