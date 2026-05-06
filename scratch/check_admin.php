<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();

// Verificar admin
$stmt = $pdo->query("SELECT id, email, password, rol FROM usuarios WHERE rol = 'admin'");
$admin = $stmt->fetch();
echo "Admin found: ID={$admin['id']}, email={$admin['email']}, rol={$admin['rol']}" . PHP_EOL;
echo "Hash stored: {$admin['password']}" . PHP_EOL;
echo "password_verify('1234', hash): " . (password_verify('1234', $admin['password']) ? 'TRUE ✅' : 'FALSE ❌') . PHP_EOL;

// Verificar script de login
echo PHP_EOL . "All users:" . PHP_EOL;
$all = $pdo->query("SELECT id, email, rol FROM usuarios ORDER BY id");
foreach ($all->fetchAll() as $u) {
    echo "  [{$u['id']}] {$u['email']} ({$u['rol']})" . PHP_EOL;
}
