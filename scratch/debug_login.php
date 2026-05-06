<?php
// Verificación completa del login
$pdo = new PDO('mysql:host=localhost;dbname=cooperativa_sjb;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Listar todos los usuarios
$stmt = $pdo->query('SELECT id, email, LEFT(password, 30) as hash_preview, rol, activo FROM usuarios');
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== USUARIOS EN BD ===" . PHP_EOL;
foreach ($usuarios as $u) {
    echo "ID:{$u['id']} | {$u['email']} | hash:{$u['hash_preview']}... | rol:{$u['rol']} | activo:{$u['activo']}" . PHP_EOL;
}

// 2. Probar password_verify para paco
$stmt2 = $pdo->prepare('SELECT password FROM usuarios WHERE email = ? LIMIT 1');
$stmt2->execute(['paco@email.com']);
$hash = $stmt2->fetchColumn();

echo PHP_EOL . "=== TEST PASSWORD ===" . PHP_EOL;
echo "Hash completo: " . $hash . PHP_EOL;
echo "Longitud: " . strlen($hash) . PHP_EOL;
echo "password_verify('1234'): " . (password_verify('1234', $hash) ? 'TRUE' : 'FALSE') . PHP_EOL;
echo "password_verify('password'): " . (password_verify('password', $hash) ? 'TRUE' : 'FALSE') . PHP_EOL;

// 3. Generar nuevo hash correcto
$nuevoHash = password_hash('1234', PASSWORD_DEFAULT);
echo PHP_EOL . "=== NUEVO HASH PARA 1234 ===" . PHP_EOL;
echo $nuevoHash . PHP_EOL;
echo "Verificar nuevo: " . (password_verify('1234', $nuevoHash) ? 'TRUE' : 'FALSE') . PHP_EOL;
