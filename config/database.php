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
$is_cli = (php_sapi_name() === 'cli');
$is_localhost = $is_cli || (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '192.168.') === 0));

if ($is_localhost) {
    // Kredensial Database Lokal (XAMPP)
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "mgmp_bali_db";
} else {
    // Kredensial Database Hosting (Ubah Sesuai Server Anda!)
    $host = "localhost";
    $user = "username_hosting_anda";
    $pass = "password_hosting_anda";
    $db   = "database_hosting_anda";
}

try {
    $conn = @mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("Koneksi database gagal: Konfigurasi user/password/db salah. (Apakah file database.php tertimpa setting lokal?)");
    }
} catch (Exception $e) {
    die("Koneksi database gagal (Exception): " . $e->getMessage() . "<br><br><b>PENTING:</b> Pastikan setting di config/database.php sudah disesuaikan dengan database server hosting Anda (bukan XAMPP/root).");
}

// =====================================
// KONFIGURASI TELEMETRI (SI-LIAK)
// =====================================
if (!defined('TELEMETRY_ENDPOINT')) {
    // Arahkan ke Mothership Pusat (Localhost)
    define('TELEMETRY_ENDPOINT', 'https://wok-item-mounted.ngrok-free.dev/siliak-pusat/api/telemetry.php');
}

?>
