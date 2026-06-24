<?php
session_start();
include 'config/database.php';

// =====================================
// KEAMANAN: HANYA ADMIN YANG BISA RESET
// =====================================
if(!isset($_SESSION['login']) || $_SESSION['role_id'] != 1){
    die("<h2>Akses Ditolak! Hanya Administrator yang dapat mereset sistem.</h2>");
}

echo "<div style='font-family: Arial; padding: 30px; line-height: 1.6;'>";
echo "<h2 style='color: #2c3e50;'>Proses Reset Data Sistem untuk Pengujian Blackbox</h2>";

// Matikan sementara proteksi Foreign Key agar penghapusan tidak ditolak
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");

// 1. Reset Riwayat Login & Percobaan Gagal
mysqli_query($conn, "DELETE FROM login_activity");
mysqli_query($conn, "ALTER TABLE login_activity AUTO_INCREMENT = 1");

$cek_la = mysqli_query($conn, "SHOW TABLES LIKE 'login_attempts'");
if($cek_la && mysqli_num_rows($cek_la) > 0) {
    mysqli_query($conn, "DELETE FROM login_attempts");
}
echo "<p style='color: #27ae60;'>✔️ Riwayat login dan percobaan login gagal telah dikosongkan.</p>";

// 2. Reset Riwayat Download
mysqli_query($conn, "DELETE FROM downloads");
mysqli_query($conn, "ALTER TABLE downloads AUTO_INCREMENT = 1");
echo "<p style='color: #27ae60;'>✔️ Riwayat download materi telah dikosongkan.</p>";

// 3. Reset Request Materi
mysqli_query($conn, "DELETE FROM material_requests");
mysqli_query($conn, "ALTER TABLE material_requests AUTO_INCREMENT = 1");
echo "<p style='color: #27ae60;'>✔️ Riwayat request materi telah dikosongkan.</p>";

// 4. Hapus File Fisik Materi (Tanpa menghapus folder landing/galeri/pengumuman)
$upload_dir = __DIR__ . '/assets/uploads/';
$docs_dir = __DIR__ . '/assets/uploads/docs/';

if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '*.*');
    if($files !== false) {
        foreach($files as $file){
            if(is_file($file)) unlink($file);
        }
    }
}

if (is_dir($docs_dir)) {
    $docs_files = glob($docs_dir . '*.*');
    if($docs_files !== false) {
        foreach($docs_files as $file){
            if(is_file($file)) unlink($file);
        }
    }
}
echo "<p style='color: #27ae60;'>✔️ File fisik materi (.pdf, .docx, .zip, dll) telah dihapus dari server.</p>";

// 5. Reset Data Tabel Materi
mysqli_query($conn, "DELETE FROM materials");
mysqli_query($conn, "ALTER TABLE materials AUTO_INCREMENT = 1");
echo "<p style='color: #27ae60;'>✔️ Database materi telah dikosongkan.</p>";

// Nyalakan kembali proteksi Foreign Key
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");

echo "<hr>";
echo "<h3 style='color: #2980b9;'>Selesai! Sistem siap digunakan untuk pengujian (Testing).</h3>";
echo "<p>Kini Anda memiliki sistem dengan 0 poin (Skor Analytics kembali menjadi 0). Data master seperti User, Foto Profil, Pengaturan Portal, dan Pengumuman tetap aman.</p>";
echo "<a href='dashboard_admin.php' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>Kembali ke Dashboard Admin</a>";
echo "</div>";
?>