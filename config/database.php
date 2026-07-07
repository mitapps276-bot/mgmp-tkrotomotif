<?php
// =====================================
// KEAMANAN: SESSION COOKIES STRICT
// =====================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// =====================================
// DETEKSI OTOMATIS LOCALHOST VS HOSTING
// =====================================
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '192.168.') === 0);

if ($is_localhost) {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "mgmp_otomotif_db";
} else {
    $host = "localhost";
    $user = "username_hosting_anda";
    $pass = "password_hosting_anda";
    $db   = "database_hosting_anda";
}

try {
    $conn = @mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("Koneksi database gagal: Konfigurasi user/password/db salah.");
    }
} catch (Exception $e) {
    die("Koneksi database gagal (Exception): " . $e->getMessage());
}

if (!defined('TELEMETRY_ENDPOINT')) {
    define('TELEMETRY_ENDPOINT', 'https://wok-item-mounted.ngrok-free.dev/siliak-pusat/api/telemetry.php');
}
?>
