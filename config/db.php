<?php
// ============================================================================
// CONEXIÓN A LA BASE DE DATOS — PDO singleton
// ============================================================================
// Detecta automáticamente si estamos en XAMPP local o en Hostinger gracias
// a config/env.php. En local usa root sin contraseña; en producción usa
// las credenciales que dé Hostinger al crear la BD MySQL.
//
// IMPORTANTE — En Hostinger Premium:
//   1. Panel → Bases de datos → MySQL → "Crear base de datos".
//   2. Copia los 4 datos que te genera (host, nombre, usuario, contraseña).
//   3. Pégalos en las constantes DB_*_PROD definidas más abajo (o, mejor,
//      en config/secrets.local.php → ahí no quedan en el repo).
// ============================================================================

require_once __DIR__ . '/env.php';

if (ES_LOCAL) {
    // ─── XAMPP local ───
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'cooperativa_sjb');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // ─── Hostinger producción ───
    // Edita estos valores con los que muestra el panel de Hostinger
    // tras crear la base de datos en MySQL Databases.
    // Nombre típico Hostinger: u123456789_cooperativa
    // Usuario típico:           u123456789_admin
    // Host:                     localhost (en Hostinger compartido)
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'u000000000_cooperativa');
    if (!defined('DB_USER')) define('DB_USER', 'u000000000_admin');
    if (!defined('DB_PASS')) define('DB_PASS', 'CAMBIAR_POR_PASS_REAL');
}

define('DB_CHARSET', 'utf8mb4');


function getConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // EMULATE_PREPARES=false → prepared statements nativos en MySQL,
            // no emulados en PHP. Bloquea inyección incluso con queries raras.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
            // Forzamos la collation de la conexión a la misma que usan las
            // tablas (utf8mb4_unicode_ci). Sin esto, MySQL 8 / MariaDB 10.5+
            // dan "Illegal mix of collations" al mezclar literales SQL
            // (ej. NULLIF(:tel, "")) con columnas, porque PDO conecta por
            // defecto con utf8mb4_general_ci.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }

    return $pdo;
}
