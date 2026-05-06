<?php
// Test script to verify login credentials
$pdo = new PDO('mysql:host=localhost;dbname=cooperativa_sjb;charset=utf8mb4', 'root', '');
$stmt = $pdo->prepare('SELECT id, email, password, activo FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute([':email' => 'paco@email.com']);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

echo "User found: " . ($u ? "YES" : "NO") . PHP_EOL;
if ($u) {
    echo "ID: " . $u['id'] . PHP_EOL;
    echo "Activo: " . $u['activo'] . PHP_EOL;
    echo "Hash stored: " . $u['password'] . PHP_EOL;
    echo "Verify '1234': " . (password_verify('1234', $u['password']) ? "OK" : "FAIL") . PHP_EOL;
    echo "Hash length: " . strlen($u['password']) . PHP_EOL;
}
