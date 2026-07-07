<?php
// ==========================================
// CORE FUNCTIONS
// Memisahkan fungsi logika dari database.php 
// agar tetap terupdate via Git.
// ==========================================

// Load Telegram Helper (non-blocking, aman)
if (file_exists(__DIR__ . '/telegram.php')) {
    require_once __DIR__ . '/telegram.php';
}

if (!function_exists('jalankanSmartMatching')) {
    function jalankanSmartMatching($conn, $title, $category, $grade_level, $admin_note) {
        $cek_col = @mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
        if($cek_col && mysqli_num_rows($cek_col) == 0){
            @mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
        }

        // Bersihkan keyword pencarian
        $words = explode(" ", strtolower($title));
        $keywords = array();
        foreach($words as $w) {
            $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
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

                        // ✅ NOTIFIKASI TELEGRAM: Kirim ke guru yang punya request ini
                        if (function_exists('notifGuruRequestTelegram')) {
                            $pesan_tg = "🔔 <b>SI-LIAK Notifikasi</b>\n\n";
                            $pesan_tg .= "Halo! Sistem SI-LIAK mendeteksi materi yang relevan dengan request Anda sudah tersedia.\n\n";
                            $pesan_tg .= "📚 <b>Judul Materi:</b> " . htmlspecialchars($title) . "\n";
                            $pesan_tg .= "🗂️ <b>Kategori:</b> " . htmlspecialchars($category) . "\n\n";
                            $pesan_tg .= "Silakan cek di menu <b>Data Materi</b> pada platform SI-LIAK.";
                            notifGuruRequestTelegram($conn, $auto_req_id, $pesan_tg);
                        }
                    }
                }
            }
        }
    }
}
?>
