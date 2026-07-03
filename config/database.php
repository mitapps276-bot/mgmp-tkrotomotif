<?php

// =====================================
// KEAMANAN: SEMBUNYIKAN ERROR DARI PUBLIK
// =====================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// =====================================
// KEAMANAN: SESSION COOKIES STRICT
// =====================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// =====================================
// DETEKSI OTOMATIS LOCALHOST VS HOSTING
// =====================================
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '192.168.') === 0);

if ($is_localhost) {
    // Kredensial Database Lokal (XAMPP)
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "mgmp_ppkn_db";
} else {
    // Kredensial Database Hosting (Ubah Sesuai Server Anda!)
    $host = "localhost";
    $user = "username_hosting_anda";
    $pass = "password_hosting_anda";
    $db   = "database_hosting_anda";
}

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal : " . mysqli_connect_error());
}

// echo "Terkoneksi Dengan Database BRO!!! ";

// =====================================
// KONFIGURASI TELEMETRI (SI-LIAK)
// =====================================
if (!defined('TELEMETRY_ENDPOINT')) {
    // Arahkan ke Mothership Pusat (Localhost)
    define('TELEMETRY_ENDPOINT', 'http://localhost/siliak-pusat/api/telemetry.php');
}

// =====================================
// HELPER: SMART MATCHING ALGORITHM
// =====================================
if (!function_exists('jalankanSmartMatching')) {
    function jalankanSmartMatching($conn, $title, $category, $grade_level, $admin_note) {
        $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
        if($cek_col && mysqli_num_rows($cek_col) == 0){
            mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
        }

        $words = explode(" ", strtolower($title));
        $keywords = [];
        foreach($words as $w) {
            $w = trim(preg_replace('/[^a-z0-9]/', '', $w)); // Bersihkan tanda baca
            if(strlen($w) > 2) { $keywords[] = $w; }
        }

        if(count($keywords) > 0) {
            $grade_safe = mysqli_real_escape_string($conn, $grade_level);
            $category_safe = mysqli_real_escape_string($conn, $category);

            // Ambil hanya request yang pending/diproses dengan kategori & kelas yang sama
            $like_grade = '%' . $grade_level . '%';
            $stmt_req = mysqli_prepare($conn, "SELECT id, deskripsi FROM material_requests WHERE status != 'selesai' AND jenis_request = ? AND deskripsi LIKE ?");
            mysqli_stmt_bind_param($stmt_req, "ss", $category, $like_grade);
            mysqli_stmt_execute($stmt_req);
            $cek_req = mysqli_stmt_get_result($stmt_req);

            if($cek_req && mysqli_num_rows($cek_req) > 0) {
                while($r = mysqli_fetch_assoc($cek_req)) {
                    $auto_req_id = $r['id'];
                    $desc_lower = strtolower($r['deskripsi']);
                    $matched_count = 0;
                    foreach($keywords as $kw) { if(strpos($desc_lower, $kw) !== false) { $matched_count++; } }
                    
                    // Jika kecocokan >= 60%, tandai sebagai selesai
                    if(($matched_count / count($keywords)) * 100 >= 60) {
                        $stmt_upd = mysqli_prepare($conn, "UPDATE material_requests SET status = 'selesai', admin_note = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_upd, "si", $admin_note, $auto_req_id);
                        mysqli_stmt_execute($stmt_upd);
                        mysqli_stmt_close($stmt_upd);
                    }
                }
            }
        }
    }
}
?>
