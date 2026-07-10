<?php
$f = 'config/database.php';
if(file_exists($f)) {
    $content = file_get_contents($f);
    
    // Cari blok fungsi lama
    $start = strpos($content, "if (!function_exists('jalankanSmartMatching')) {");
    if($start !== false) {
        // Ambil isi dari awal sampai sebelum blok fungsi lama
        $new_content = substr($content, 0, $start);
        
        // Kita juga pastikan tag penutup ?> tetap ada jika sebelumnya ada
        $new_content = trim($new_content) . "\n\n?>";
        
        // Simpan kembali
        if(file_put_contents($f, $new_content)) {
            echo "<h1>BERHASIL: Kode parasit di config/database.php telah dibasmi!</h1>";
            echo "<p>Sekarang sistem Anda akan menggunakan fungsi terbaru yang ada di config/functions.php (yang berisi kode Telegram).</p>";
        } else {
            echo "<h1>GAGAL: Tidak ada izin untuk mengedit file. Silakan hapus kode secara manual melalui cPanel.</h1>";
        }
    } else {
        echo "<h1>TIDAK DITEMUKAN: Kode lama sudah bersih.</h1>";
    }
} else {
    echo "File database.php tidak ditemukan.";
}
?>
