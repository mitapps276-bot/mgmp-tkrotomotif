<?php

session_start();

include 'config/database.php';

// =======================
// CEK LOGIN
// =======================

if(!isset($_SESSION['login'])){

    header("Location:index.php");
    exit;

}

// =======================
// CSRF TOKEN
// =======================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// =======================
// DATA USER LOGIN
// =======================

$user_id = $_SESSION['user_id'];

$nama_guru = $_SESSION['name'];

$upload_message = isset($_SESSION['upload_message']) ? $_SESSION['upload_message'] : "";
$upload_status = isset($_SESSION['upload_status']) ? $_SESSION['upload_status'] : "";

unset($_SESSION['upload_message']);
unset($_SESSION['upload_status']);

// =======================
// POPUP REQUEST NOTIFIKASI
// =======================
$req_popup_type = isset($_SESSION['req_popup_type']) ? $_SESSION['req_popup_type'] : '';
$req_popup_title = isset($_SESSION['req_popup_title']) ? $_SESSION['req_popup_title'] : '';
$req_popup_msg = isset($_SESSION['req_popup_msg']) ? $_SESSION['req_popup_msg'] : '';
unset($_SESSION['req_popup_type']);
unset($_SESSION['req_popup_title']);
unset($_SESSION['req_popup_msg']);

// =======================
// FOTO PROFIL GURU
// =======================

$photo_column = "profile_photo";

$cek_photo_column = mysqli_query($conn, "

SHOW COLUMNS FROM users LIKE '$photo_column'

");

if($cek_photo_column && mysqli_num_rows($cek_photo_column) == 0){

    mysqli_query($conn, "

    ALTER TABLE users
    ADD profile_photo VARCHAR(255) NULL

    ");

}

$cek_photo_column = mysqli_query($conn, "

SHOW COLUMNS FROM users LIKE '$photo_column'

");

$has_photo_column = $cek_photo_column && mysqli_num_rows($cek_photo_column) > 0;

if($has_photo_column){

    $user_query = mysqli_query($conn, "

SELECT
full_name,
school_name,
profile_photo
FROM users
WHERE id = '$user_id'

");

}else{

    $user_query = mysqli_query($conn, "

SELECT
full_name,
school_name,
'' AS profile_photo
FROM users
WHERE id = '$user_id'

");

}

$user_data = $user_query ? mysqli_fetch_assoc($user_query) : null;

if($user_data && !empty($user_data['full_name'])){

    $nama_guru = $user_data['full_name'];
    $_SESSION['name'] = $nama_guru;

}

$profile_photo = isset($user_data['profile_photo']) ? $user_data['profile_photo'] : "";
$profile_photo_path = "";

if(!empty($profile_photo) && file_exists(__DIR__ . "/" . $profile_photo)){

    $profile_photo_path = $profile_photo;

}

$profile_initial = strtoupper(substr(trim($nama_guru), 0, 1));

if(empty($profile_initial)){

    $profile_initial = "G";

}

if(isset($_POST['upload_profile_photo'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $max_size = 2 * 1024 * 1024;
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    if(!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] == UPLOAD_ERR_NO_FILE){

        $_SESSION['upload_status'] = "error";
        $_SESSION['upload_message'] = "Pilih foto terlebih dahulu";

    }elseif($_FILES['profile_photo']['error'] != UPLOAD_ERR_OK){

        $_SESSION['upload_status'] = "error";
        $_SESSION['upload_message'] = "Upload foto gagal";

    }elseif($_FILES['profile_photo']['size'] > $max_size){

        $_SESSION['upload_status'] = "error";
        $_SESSION['upload_message'] = "Ukuran foto maksimal 2MB";

    }else{

        $tmp_name = $_FILES['profile_photo']['tmp_name'];
        $extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $image_info = getimagesize($tmp_name);
        $mime_type = isset($image_info['mime']) ? $image_info['mime'] : "";

        if(
            !in_array($extension, $allowed_extensions)
            ||
            !in_array($mime_type, $allowed_mimes)
        ){

            $_SESSION['upload_status'] = "error";
            $_SESSION['upload_message'] = "Format foto tidak didukung (Gunakan JPG, PNG, atau WEBP)";

        }else{

            $upload_dir = "uploads/profile_photos";
            $upload_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $upload_dir);

            if(!is_dir($upload_path)){

                mkdir($upload_path, 0777, true);

            }

            $new_file_name = "user_" . $user_id . "_" . time() . "." . $extension;
            $new_file = $upload_dir . "/" . $new_file_name; // Path untuk Database (URL friendly)
            $new_file_path = $upload_path . DIRECTORY_SEPARATOR . $new_file_name; // Path fisik server

            if(move_uploaded_file($tmp_name, $new_file_path)){

                $safe_new_file = mysqli_real_escape_string($conn, $new_file);

                $update_photo = mysqli_query($conn, "

                UPDATE users
                SET profile_photo = '$safe_new_file'
                WHERE id = '$user_id'

                ");

                if($update_photo){

                    if(
                        !empty($profile_photo)
                        &&
                        strpos($profile_photo, "uploads/profile_photos/") === 0
                        &&
                        file_exists(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profile_photo))
                    ){

                        unlink(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profile_photo));

                    }

                    $_SESSION['upload_status'] = "success";
                    $_SESSION['upload_message'] = "Foto profil berhasil diperbarui";

                }else{

                    if(file_exists($new_file_path)){

                        unlink($new_file_path);

                    }

                    $_SESSION['upload_status'] = "error";
                    $_SESSION['upload_message'] = "Gagal menyimpan foto ke database";

                }

            }else{

                $_SESSION['upload_status'] = "error";
                $_SESSION['upload_message'] = "Gagal memindahkan file foto";

            }

        }

    }

    header("Location:dashboard.php");
    exit;

}

// =======================
// NOTIFIKASI LONCENG
// =======================
$cek_read_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'is_read'");
if($cek_read_col && mysqli_num_rows($cek_read_col) == 0){
    mysqli_query($conn, "ALTER TABLE material_requests ADD is_read TINYINT(1) DEFAULT 0");
}
if(isset($_POST['mark_read'])){
    mysqli_query($conn, "UPDATE material_requests SET is_read = 1 WHERE user_id = '$user_id' AND status = 'selesai'");
    header("Location: dashboard.php");
    exit;
}
$notif_query = mysqli_query($conn, "SELECT id, jenis_request, admin_note, created_at FROM material_requests WHERE user_id = '$user_id' AND status = 'selesai' AND is_read = 0 ORDER BY created_at DESC");
$unread_count = mysqli_num_rows($notif_query);

// =======================
// PROSES REQUEST MATERI
// =======================

if(isset($_POST['submit_request'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $jenis = mysqli_real_escape_string($conn, htmlspecialchars(trim($_POST['jenis_request'])));
    $kelas = mysqli_real_escape_string($conn, htmlspecialchars(trim($_POST['kelas_request'])));
    $deskripsi_input = htmlspecialchars(trim($_POST['deskripsi_request']));
    $user_id_request = $_SESSION['user_id'];
    
    // =====================================
    // AUTO-CHECK MATERI (SMART SEARCH)
    // =====================================
    $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
    if($cek_col && mysqli_num_rows($cek_col) == 0){
        mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
    }

    $status_request = 'pending';
    $admin_note = NULL;
    $alert_msg = "Request data untuk kategori \"$jenis\" berhasil disimpan dan sedang menunggu tindak lanjut admin/guru lain!";

    // Ambil data materi dengan kategori dan kelas yang sama (termasuk milik sendiri, kalau ada langsung dikasi)
    $check_sql = "SELECT id, title, folder_id, created_at, user_id FROM materials WHERE category = '$jenis' AND grade_level = '$kelas' AND status = 'approved' ORDER BY created_at DESC";
    $cek_materi = mysqli_query($conn, $check_sql);
    
    $materi_ditemukan = null;
    $deskripsi_lower = strtolower($deskripsi_input);

    if($cek_materi && mysqli_num_rows($cek_materi) > 0) {
        while($m = mysqli_fetch_assoc($cek_materi)) {
            $stopwords = ['dan','atau','yang','untuk','dari','dalam','ke','di','pada','tentang','dengan','ini','itu','sebagai','materi','bahasa','kelas','bab','semester','kurikulum','merdeka','revisi'];

            // Pecah judul materi menjadi array kata
            $words = explode(" ", strtolower($m['title']));
            $title_keywords = [];
            foreach($words as $w) {
                $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
                if(strlen($w) > 1 && !in_array($w, $stopwords)) { $title_keywords[] = $w; } // Minimal 2 huruf & bukan stopword
            }
            
            // Pecah deskripsi request menjadi array kata
            $req_words = explode(" ", $deskripsi_lower);
            $req_keywords = [];
            foreach($req_words as $w) {
                $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
                if(strlen($w) > 1 && !in_array($w, $stopwords)) { $req_keywords[] = $w; } // Minimal 2 huruf & bukan stopword
            }

            if(count($title_keywords) > 0) {
                $matched_count_title = 0;
                foreach($title_keywords as $kw) { 
                    if(strpos($deskripsi_lower, $kw) !== false) { 
                        $matched_count_title++; 
                    } 
                }
                $pct_title = ($matched_count_title / count($title_keywords)) * 100;
                
                $pct_req = 0;
                if(count($req_keywords) > 0) {
                    $matched_count_req = 0;
                    $title_lower = strtolower($m['title']);
                    foreach($req_keywords as $kw) { 
                        if(strpos($title_lower, $kw) !== false) { 
                            $matched_count_req++; 
                        } 
                    }
                    $pct_req = ($matched_count_req / count($req_keywords)) * 100;
                }

                // Jika minimal 50% kata dari judul materi ada di deskripsi request, 
                // ATAU minimal 50% kata dari request ada di judul materi, anggap cocok
                if($pct_title >= 50 || $pct_req >= 50) {
                    $materi_ditemukan = $m;
                    break;
                }
            }
        }
    }

    if($materi_ditemukan) {
            $mat_folder = $materi_ditemukan['folder_id'];
            $mat_created = $materi_ditemukan['created_at'];
            $mat_user = $materi_ditemukan['user_id'];
            
            // Ambil nama folder
            if($mat_user !== NULL){
                $folder_query = mysqli_query($conn, "SELECT folder_name FROM folders WHERE id = '$mat_folder'");
                $nama_folder = ($folder_query && mysqli_num_rows($folder_query) > 0) ? mysqli_fetch_assoc($folder_query)['folder_name'] : 'Materi';
                $lokasi_teks = "berada di dalam folder \"$nama_folder\"";
            } else {
                $lokasi_teks = "berada di dalam folder \"Kontributor External\"";
            }
            
            $status_request = 'selesai';
            $search_keyword = urlencode($deskripsi_input);
            $admin_note = "Pencarian Otomatis: Ditemukan materi yang relevan dengan request Anda. Silakan cari dengan kata kunci: \"" . htmlspecialchars($deskripsi_input) . "\" di menu Data Materi.";
            
            // Set sesi popup untuk status Selesai
            $_SESSION['req_popup_type'] = 'selesai';
            $_SESSION['req_popup_title'] = 'Materi Ditemukan!';
            
            // Ganti tombol direct download menjadi search redirect
            $search_btn = '<br><br><a href="data_materi.php?search=' . $search_keyword . '" style="display:inline-block; background:#2ecc71; color:white; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:14px; transition:0.3s;" onmouseover="this.style.background=\'#27ae60\'" onmouseout="this.style.background=\'#2ecc71\'">🔍 Lihat Semua Materi Relevan</a>';
            
            $_SESSION['req_popup_msg'] = '<strong>Sistem SI-LIAK</strong> mendeteksi ada materi yang relevan dengan request Anda <b>sudah tersedia</b> di Data Materi.<br><br>Kata Kunci Pencarian: <i style="color:#2980b9;">"' . htmlspecialchars($deskripsi_input) . '"</i>' . $search_btn;
        }
    
    // Set sesi popup untuk status Pending
    if($status_request == 'pending'){
        $_SESSION['req_popup_type'] = 'pending';
        $_SESSION['req_popup_title'] = 'Request Diteruskan!';
        $_SESSION['req_popup_msg'] = 'Materi yang Anda request <b>belum tersedia</b> di database.<br><br>Request Anda telah diteruskan ke Admin untuk ditindaklanjuti atau agar dibantu oleh guru lain.';
    }

    // Gabungkan pilihan kelas ke dalam deskripsi agar langsung terlihat oleh Admin dan Guru lain
    $deskripsi = "Target Kelas: " . $kelas . "\nDetail Request: " . $deskripsi_input;

    // Simpan ke database menggunakan prepared statement
    $stmt_req = mysqli_prepare($conn, "INSERT INTO material_requests (user_id, jenis_request, deskripsi, status, admin_note) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_req, "issss", $user_id_request, $jenis, $deskripsi, $status_request, $admin_note);
    mysqli_stmt_execute($stmt_req);
    $insert_req = mysqli_stmt_affected_rows($stmt_req) > 0;
    mysqli_stmt_close($stmt_req);

    echo "
    <script>
        location.replace('dashboard.php');
    </script>
    ";
    header("Location: dashboard.php");
    exit;

}

// =======================
// HAPUS REQUEST MATERI
// =======================

if(isset($_GET['hapus_request'])){
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $req_id = (int)$_GET['hapus_request'];

    // Pastikan request milik user yang sedang login
    $stmt_hapus = mysqli_prepare($conn, "DELETE FROM material_requests WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt_hapus, "ii", $req_id, $user_id);
    mysqli_stmt_execute($stmt_hapus);
    mysqli_stmt_close($stmt_hapus);

    header("Location: dashboard.php");
    exit;
}

// =======================
// TOTAL GURU
// =======================

$total_guru_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_guru
FROM users
WHERE role_id = 2

");

$total_guru_data = mysqli_fetch_assoc($total_guru_query);

$total_guru = $total_guru_data['total_guru'];

// =======================
// DAFTAR GURU UNTUK MODAL
// =======================

$list_guru_query = mysqli_query($conn, "
    SELECT full_name, school_name FROM users WHERE role_id = 2 ORDER BY full_name ASC
");

// =======================
// TOTAL EXTERNAL CONTRIBUTOR
// =======================

$total_external_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total_contributor
    FROM users
    WHERE role_id = 4
");
$total_external_data = mysqli_fetch_assoc($total_external_query);
$total_external = isset($total_external_data['total_contributor']) ? $total_external_data['total_contributor'] : 0;

// =======================
// DATA UNTUK MODAL EXTERNAL CONTRIBUTOR
// =======================
$list_external_query = mysqli_query($conn, "
    SELECT full_name AS contributor_name, school_name AS contributor_institution 
    FROM users
    WHERE role_id = 4
    ORDER BY full_name ASC
");

// =======================
// PENGUMUMAN ADMIN
// =======================

$cek_table_pengumuman = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if(mysqli_num_rows($cek_table_pengumuman) == 0){
    mysqli_query($conn, "
        CREATE TABLE announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pesan TEXT NOT NULL,
            file_path VARCHAR(255) NULL,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Masukkan pengumuman default jika tabel baru dibuat
    mysqli_query($conn, "INSERT INTO announcements (pesan) VALUES ('Selamat datang di platform MGMP! Mari berkolaborasi dan berbagi materi untuk meningkatkan kualitas pembelajaran bersama.')");
} else {
    $cek_kolom_file = mysqli_query($conn, "SHOW COLUMNS FROM announcements LIKE 'file_path'");
    if(mysqli_num_rows($cek_kolom_file) == 0){
        mysqli_query($conn, "ALTER TABLE announcements ADD file_path VARCHAR(255) NULL");
    }
    $cek_kolom_target = mysqli_query($conn, "SHOW COLUMNS FROM announcements LIKE 'target_audience'");
    if(mysqli_num_rows($cek_kolom_target) == 0){
        mysqli_query($conn, "ALTER TABLE announcements ADD target_audience VARCHAR(20) NOT NULL DEFAULT 'all' AFTER pesan");
    }
}

$pengumuman_query = mysqli_query($conn, "SELECT id, pesan, tanggal, file_path FROM announcements WHERE target_audience IN ('guru', 'all') ORDER BY id DESC LIMIT 1");
$pengumuman_data = mysqli_fetch_assoc($pengumuman_query);
$pengumuman_teks = $pengumuman_data ? $pengumuman_data['pesan'] : "Belum ada pengumuman.";
$pengumuman_tanggal = $pengumuman_data ? date('d M Y H:i', strtotime($pengumuman_data['tanggal'])) : "";
$pengumuman_file = $pengumuman_data ? $pengumuman_data['file_path'] : "";

// =======================
// TOTAL DOWNLOAD
// =======================

$total_download_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_download
FROM downloads

");

$total_download_data =
mysqli_fetch_assoc($total_download_query);

$total_download =
$total_download_data['total_download'];

// =======================
// CEK ADA DATA MATERI?
// =======================

$cek_materi_query = mysqli_query($conn, "

SELECT COUNT(*) AS total
FROM materials

");

$cek_materi_data =
mysqli_fetch_assoc($cek_materi_query);

$ada_materi =
$cek_materi_data['total'] > 0;

// =======================
// TOPIK MATERI PALING DIPERLUKAN (SPESIFIK)
// =======================

$user_school_name = isset($user_data['school_name']) ? mysqli_real_escape_string($conn, $user_data['school_name']) : '';

// Cari request spesifik dari sekolah sendiri dulu
$materi_diperlukan_query = mysqli_query($conn, "
    SELECT 
        req.jenis_request,
        req.deskripsi,
        COUNT(req.id) AS jumlah_request,
        GROUP_CONCAT(CONCAT(u.full_name, ' (', u.school_name, ')') SEPARATOR ', ') AS requesters
    FROM material_requests req
    JOIN users u ON req.user_id = u.id
    WHERE u.school_name = '$user_school_name' AND req.status != 'selesai'
    GROUP BY req.jenis_request, req.deskripsi
    ORDER BY jumlah_request DESC, MAX(req.created_at) ASC
");

$scope_kebutuhan = "Sekolah Anda";

// Jika sekolah sendiri tidak ada request pending, tampilkan request global yang paling banyak
if (!$materi_diperlukan_query || mysqli_num_rows($materi_diperlukan_query) == 0) {
    $materi_diperlukan_query = mysqli_query($conn, "
        SELECT 
            req.jenis_request,
            req.deskripsi,
            COUNT(req.id) AS jumlah_request,
            GROUP_CONCAT(CONCAT(u.full_name, ' (', u.school_name, ')') SEPARATOR ', ') AS requesters
        FROM material_requests req
        JOIN users u ON req.user_id = u.id
        WHERE req.status != 'selesai'
        GROUP BY req.jenis_request, req.deskripsi
        ORDER BY jumlah_request DESC, MAX(req.created_at) ASC
    ");
    $scope_kebutuhan = "Global (Semua Sekolah)";
}

$materi_diperlukan_list = [];

if ($materi_diperlukan_query && mysqli_num_rows($materi_diperlukan_query) > 0) {
    while($row = mysqli_fetch_assoc($materi_diperlukan_query)) {
        $detail = $row['deskripsi'];
        // Memisahkan Kelas dan Judul Deskripsi menjadi format yang bersih
        if (preg_match('/Target Kelas:\s*(.*?)\r?\nDetail Request:\s*(.*)/s', $row['deskripsi'], $matches)) {
            $detail = trim($matches[1]) . " - " . trim($matches[2]);
        }
        $materi_diperlukan_list[] = [
            'jenis' => $row['jenis_request'],
            'detail' => $detail,
            'jumlah' => $row['jumlah_request'],
            'requesters' => $row['requesters']
        ];
    }
}

// =======================
// TOTAL UPLOAD GURU LOGIN
// =======================

$total_upload_guru_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_upload

FROM materials

WHERE user_id = '$user_id'

");

$total_upload_guru_data =
mysqli_fetch_assoc($total_upload_guru_query);

$total_upload_guru =
$total_upload_guru_data['total_upload'];

// =======================
// TOTAL DOWNLOAD GURU LOGIN
// =======================

$total_download_guru_query = mysqli_query($conn, "

SELECT COUNT(downloads.id) AS total_download

FROM materials

LEFT JOIN downloads
ON materials.id = downloads.material_id

WHERE materials.user_id = '$user_id'

");

$total_download_guru_data =
mysqli_fetch_assoc($total_download_guru_query);

$total_download_guru =
$total_download_guru_data['total_download'];

// =======================
// RANKING GURU
// =======================

$ranking_query = mysqli_query($conn, "

SELECT *

FROM(

    SELECT

        users.id,

        (
            (COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * 7)
            +
            (COUNT(DISTINCT downloads.id) * 2)
            +
            (COUNT(DISTINCT login_activity.id) * 1)
        ) AS nilai

    FROM users

    LEFT JOIN materials
    ON users.id = materials.user_id

    LEFT JOIN downloads
    ON materials.id = downloads.material_id

    LEFT JOIN login_activity
    ON users.id = login_activity.user_id

    WHERE users.role_id = 2

    GROUP BY users.id

    ORDER BY nilai DESC

) AS ranking

");

$ranking = 1;

$ranking_user = "-";

while($r = mysqli_fetch_assoc($ranking_query)){

    if($r['id'] == $user_id){

        $ranking_user = $ranking;
        break;

    }

    $ranking++;

}

// =======================
// DATA REQUEST MATERI GURU
// =======================

$request_query = mysqli_query($conn, "

SELECT * FROM material_requests
WHERE user_id = '$user_id'
ORDER BY 
    FIELD(status, 'selesai', 'diproses', 'pending'),
    created_at DESC
LIMIT 10

");

// =======================
// PAPAN REQUEST TERBUKA (GURU LAIN)
// =======================

$open_request_query = mysqli_query($conn, "
SELECT r.*, u.full_name, u.school_name 
FROM material_requests r
JOIN users u ON r.user_id = u.id
WHERE r.status != 'selesai'
AND r.user_id != '$user_id'
ORDER BY r.created_at DESC
");

// =======================
// AKTIVITAS LOGIN HARI INI
// =======================

$recent_logins_query = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.school_name, l.login_time, u.profile_photo, u.role_id 
    FROM login_activity l
    JOIN users u ON l.user_id = u.id
    WHERE u.role_id IN (1, 2, 4) AND l.login_date = CURDATE()
    ORDER BY l.login_time DESC
");

// =======================
// INTELLIGENT ANALYTICS
// =======================

$status_ai = "";
$warna_ai = "";
$rekomendasi = "";

if($total_upload_guru == 0){

    $status_ai = "Tidak Aktif";
    $warna_ai = "#e74c3c";

    $rekomendasi = "
    Anda belum pernah mengupload materi.
    Sistem merekomendasikan mulai berbagi
    materi pembelajaran agar kontribusi MGMP meningkat.
    ";

}elseif($total_upload_guru <= 3){

    $status_ai = "Mulai Berkembang";
    $warna_ai = "#f39c12";

    $rekomendasi = "
    Aktivitas Anda mulai berkembang.
    Disarankan meningkatkan konsistensi upload
    minimal 1 materi setiap minggu.
    ";

}elseif($total_download_guru <= 5){

    $status_ai = "Memerlukan Optimasi";
    $warna_ai = "#3498db";

    $rekomendasi = "
    Materi Anda sudah aktif diupload
    namun tingkat download masih rendah.
    Disarankan membuat materi lebih menarik
    dan relevan dengan kebutuhan MGMP.
    ";

}elseif($ranking_user <= 3){

    $status_ai = "Top Contributor";
    $warna_ai = "#27ae60";

    $rekomendasi = "
    Anda termasuk guru paling aktif
    dalam platform MGMP.
    Pertahankan kualitas kontribusi dan
    bantu guru lain melalui kolaborasi digital.
    ";

}else{

    $status_ai = "Aktif";
    $warna_ai = "#1abc9c";

    $rekomendasi = "
    Aktivitas Anda sudah cukup baik.
    Pertahankan konsistensi upload materi
    dan tingkatkan interaksi kolaboratif.
    ";

}

?>

<!DOCTYPE html>
<html>
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard MGMP</title>

    <style>

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:Arial;
            background:#f4f6f9;
        }

        .wrapper{
            display:flex;
        }

        /* ======================
           SIDEBAR
        ====================== */

        .sidebar{

            width:250px;
            height:100vh;

            background:#2c3e50;

            position:sticky;
            top:0;
            align-self:flex-start;
            overflow-y:auto;

            color:white;
            display:flex;
            flex-direction:column;

        }

        .menu a{

            display:block;

            color:white;

            text-decoration:none;

            padding:18px 25px;

            font-size:16px;

            transition:0.3s;

        }

        .menu a:hover{

            background:#1abc9c;

        }

        /* ======================
           CONTENT
        ====================== */

        .content{

            flex:1;
            min-width:0;

            padding:25px;

        }

        /* ======================
           HERO
        ====================== */

        .hero{

            background:linear-gradient(
                135deg,
                #2ecc71,
                #1abc9c
            );

            color:white;

            padding:25px 35px;

            border-radius:22px;

            margin-bottom:25px;

        }

        .hero-top{

            display:flex;

            justify-content:space-between;

            gap:25px;

            align-items:center;

        }

        .hero-text{

            flex:1;

            min-width:0;

        }

        .hero h1{

            margin-top:0;

            font-size:42px;

            margin-bottom:15px;

        }

        .hero p{

            font-size:18px;

            line-height:1.8;

        }

        .badge{

            display:inline-block;

            padding:10px 18px;

            background:#27ae60;

            border-radius:30px;

            margin-top:10px;

            font-weight:bold;

        }

        .profile-panel{

            width:240px;

            background:rgba(255,255,255,0.14);

            border:1px solid rgba(255,255,255,0.25);

            border-radius:18px;

            padding:20px;

            text-align:center;

        }

        .profile-photo,
        .profile-initial{

            width:100%;

            height:220px;

            border-radius:12px;

            margin:0 auto 15px;

            border:none;

        }

        .profile-photo{

            display:block;

            object-fit:cover;

            object-position:top;
            object-position:center;

            background:white;

        }

        .profile-initial{

            display:flex;

            align-items:center;

            justify-content:center;

            background:#2c3e50;

            color:white;

            font-size:48px;

            font-weight:bold;

        }

        .photo-button{

            width:100%;

            border:none;

            border-radius:8px;

            padding:8px;

            font-size:12px;

            background:#2c3e50;

            color:white;

            font-weight:bold;

            cursor:pointer;

        }

        .upload-alert{

            margin-top:12px;

            padding:10px 12px;

            border-radius:10px;

            font-size:13px;

            line-height:1.4;

        }

        .upload-alert.success{

            background:rgba(39,174,96,0.95);

        }

        .upload-alert.error{

            background:rgba(231,76,60,0.95);

        }

        /* ======================
           GRID CARD
        ====================== */

        .grid{

            display:grid;

            grid-template-columns:
            repeat(2,1fr);

            gap:20px;

            margin-bottom:25px;

            align-items: stretch;

        }

        .card{

            background:white;

            border-radius:20px;

            padding:25px;

            box-shadow:
            0px 0px 12px
            rgba(0,0,0,0.06);

            transition: transform 0.3s ease, box-shadow 0.3s ease;

        }

        .card:hover{

            transform: translateY(-5px);

            box-shadow: 0px 8px 20px rgba(0,0,0,0.12);

        }

        .card h3{

            margin-top:0;

            color:#666;

            font-size:16px;

        }

        .card h1{

            margin-bottom:0;

            font-size:50px;

            color:#2c3e50;

        }

        .empty-box{

            text-align:center;
            color:#888;

            padding:30px 10px;

            line-height:1.8;

        }

        /* ======================
           AI CARD
        ====================== */
        .ai-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .ai-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        .ai-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, #3b82f6, #10b981);
        }
        .ai-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ai-title, .ai-recommendation-title {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 5px;
            background: linear-gradient(90deg, #60a5fa, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: block;
        }
        .ai-subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin: 0;
        }
        .ai-status {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
            background: <?= $warna_ai; ?>;
            color: white;
            box-shadow: 0 4px 15px <?= $warna_ai; ?>66;
            white-space: nowrap;
        }
        .ai-body {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .ai-recommendation {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 20px;
            border-radius: 15px;
            color: #f8fafc;
            line-height: 1.6;
            font-size: 15px;
            border-left: 4px solid #3b82f6;
        }

        /* ======================
           REQUEST SCROLL CAROUSEL
        ====================== */
        .request-scroll-container {
            display: flex;
            overflow-x: auto;
            gap: 20px;
            padding-bottom: 15px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
            scrollbar-width: none; /* Firefox */
            flex: 1;
        }
        .request-scroll-container::-webkit-scrollbar {
            display: none; /* Safari and Chrome */
        }
        .request-card {
            flex: 0 0 calc(50% - 10px); /* Menampilkan maksimal 2 card */
            scroll-snap-align: start;
            background: #f8f9fa;
            border: 1px solid #dce4ec;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        .carousel-wrapper {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
        }
        .carousel-btn {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            z-index: 10;
            font-size: 18px;
            transition: 0.3s;
        }
        .carousel-btn:hover { background: #1abc9c; transform: scale(1.1); }
        .carousel-btn.prev { margin-right: 15px; }
        .carousel-btn.next { margin-left: 15px; }

        /* ======================
           MOBILE NAVIGATION (HAMBURGER)
        ====================== */
        .mobile-nav {
            display: none;
            background: #2c3e50;
            padding: 15px 25px;
            align-items: center;
            justify-content: space-between;
            color: white;
        }
        .hamburger-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .mobile-swipe-hint {
            display: none;
        }
        .mobile-break {
            display: none;
        }

        /* ======================
           RESPONSIVE
        ====================== */
        @media(max-width:992px){
            .wrapper{ flex-direction:column; }
            .mobile-nav { display: flex; }
            .sidebar{ position:static; width:100%; height:auto; display: none; }
            .sidebar.active { display: flex; }
            .grid{ grid-template-columns:1fr; }
            .hero-top{ flex-direction:column; }
            .profile-panel{ width:100%; }
            .ai-header { flex-direction:column; align-items:flex-start; gap:15px; }
        }
        @media(max-width: 1024px) {
            .request-card {
                flex: 0 0 calc(50% - 10px); /* 2 card di layar tablet */
            }
        }
        @media(max-width: 768px) {
            .hero { padding: 20px; }
            .hero h1 { font-size: 26px; margin-bottom: 10px; }
            .hero p { font-size: 14px; line-height: 1.5; text-align: justify; }
            .card { padding: 15px; } /* Kurangi padding card agar konten lega */
            .request-card {
                flex: 0 0 85%; /* Menampilkan 85% card agar card berikutnya terlihat sedikit (ngintip) */
            }
            .active-teacher-card {
                flex: 0 0 85%; /* Sama seperti request card, tampil 85% di mobile */
                width: auto;
            }
            .active-teacher-carousel-wrapper {
                margin-top: -30px; /* Mengurangi jarak berlebih di mobile */
                margin-bottom: -30px;
            }
            .active-teacher-list {
                padding: 35px 5px; /* Mengurangi padding vertikal berlebih di mobile */
            }
            .ai-subtitle { text-align: left; } /* Dikembalikan ke left agar tidak renggang */
            .ai-recommendation { text-align: left; } /* Dikembalikan ke left agar tidak renggang */
            .mobile-swipe-hint { display: block; }
            .mobile-break { display: block; width: 100%; height: 0; }
            .carousel-btn { display: none; } /* Tombol disembunyikan di HP */
        }
    </style>

    <style>
        /* Styles for Accordion and Carousel */
        .accordion-card{
            background:white;
            border-radius:20px;
            box-shadow:0px 0px 12px rgba(0,0,0,0.06);
            margin-bottom:25px;
            overflow: hidden;
        }
        .accordion-header{
            padding:25px;
            cursor:pointer;
            display:flex;
            justify-content:space-between;
            align-items:center;
            user-select:none;
            transition:0.3s;
        }
        .accordion-header h2 {
            margin:0; 
            color:#2c3e50; 
            font-size:22px;
        }
        .accordion-header:hover{ background:#fbfcfd; }
        .accordion-header.active{ border-bottom:1px solid #edf0f2; }
        .accordion-header::after{ content:'▼'; font-size:16px; transition:transform 0.3s ease; color:#2c3e50; }
        .accordion-header.active::after{ transform:rotate(-180deg); }
        .accordion-body{ padding:25px; display:none; }

        .active-teacher-carousel-wrapper {
            position: relative;
            width: 100%;
            margin-top: -60px;
            margin-bottom: -60px;
            z-index: 5;
        }
        .active-teacher-list {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            padding: 65px 5px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        .active-teacher-list::-webkit-scrollbar { height: 8px; }
        .active-teacher-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .active-teacher-list::-webkit-scrollbar-thumb { background: #bdc3c7; border-radius: 10px; }
        .active-teacher-card {
            flex: 0 0 320px; /* Fixed width for each card */
            scroll-snap-align: start;
            box-sizing: border-box;
            display:flex; 
            align-items:center; 
            gap:15px; 
            background:#fbfcfd; 
            padding:12px 15px; 
            border-radius:12px; 
            border:1px solid #edf0f2; 
            transition:transform 0.3s ease;
        }
        .active-teacher-card:hover {
            transform: translateY(-2px);
            z-index: 10;
            position: relative;
        }
        .active-user-photo {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .active-teacher-card:hover .active-user-photo {
            transform: scale(5);
            z-index: 9999;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>

        

        
        
        
        
        

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        

        

        

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        

        

        

        
        

        

        
        
        
        
        
        
        
        
        

        

        

        
        

        

        
        
        
        
        
        
        

        

        

        

        
        

        

        

        
        
        
        
        
        
        
        
        
        
        
        
        

        
        
        
        
        
        
        
        

        

        
        

        

        

        

        
        
        
        
        
        
        
        
        
        
        
        
        

        

        
        

        
        
        
        
        
        
        
        
        
        
        
        

        
        
        
        
        
        
        
        
                
        
        
        
        
        
        
        
        
        
        

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        

        
        
        
        
        
        
        
        

        
        
        

        

        
        
        

        

        
        
        

        

        

        

        
        
        

        
        
        

        

        

        

        

        

        

        
        
        
        

        




</head>
<body>

<div class="wrapper">

    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <!-- SIDEBAR -->

    <div class="sidebar" id="sidebar-menu">

        <div class="menu">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="upload_materi.php">
                Upload Materi
            </a>

            <a href="data_materi.php">
                Data Materi
            </a>

            <a href="analytics.php">
                Analytics
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>

        <div style="padding: 20px; margin-top: auto;">
            <div onclick="openGuruModal()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; cursor: pointer; transition: 0.3s; text-align: center; margin-bottom: 15px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                <h4 style="margin: 0 0 5px 0; color: #bdc3c7; font-size: 13px; text-transform: uppercase;">Total Guru MGMP</h4>
                <h2 style="margin: 0 0 5px 0; color: #1abc9c; font-size: 28px;"><?= $total_guru; ?></h2>
                <p style="margin: 0; font-size: 11px; color: #7f8c8d;">Klik untuk melihat detail</p>
            </div>
            
            <div onclick="openExternalModal()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; cursor: pointer; transition: 0.3s; text-align: center;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                <h4 style="margin: 0 0 5px 0; color: #bdc3c7; font-size: 13px; text-transform: uppercase;">Total External Contributor</h4>
                <h2 style="margin: 0 0 5px 0; color: #f39c12; font-size: 28px;"><?= $total_external; ?></h2>
                <p style="margin: 0; font-size: 11px; color: #7f8c8d;">Klik untuk melihat detail</p>
            </div>
        </div>

    </div>

    <!-- CONTENT -->

    <div class="content">

        <!-- HERO -->
        <style>
        .hero-bg {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1;
            background: url('assets/uploads/landing/1782051293_LIAK.jpg') center 25% / cover no-repeat;
            animation: waveBg 8s ease-in-out infinite alternate;
        }
        .hero-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.85), rgba(41, 128, 185, 0.85));
            z-index: 2;
        }
        @keyframes waveBg {
            0%   { transform: scale(1.1) translate(0%, 0%); }
            25%  { transform: scale(1.1) translate(2%, 2%); }
            50%  { transform: scale(1.1) translate(4%, 0%); }
            75%  { transform: scale(1.1) translate(2%, -2%); }
            100% { transform: scale(1.1) translate(0%, 0%); }
        }
        </style>
        <div class="hero" style="position: relative; overflow: hidden; color: white;">
            <div class="hero-bg"></div>
            <div class="hero-overlay"></div>
            <div class="hero-top" style="position: relative; z-index: 3;">
                <div class="hero-text">
                <h1 style="margin:0; margin-bottom:15px; color: white;">OM SWASTYASTU 🙏</h1>
                    <p>Selamat datang, <strong><?= htmlspecialchars($nama_guru); ?></strong></p>
                    <p>Platform Kolaboratif MGMP - Sistem Informasi Learning Integration & Analitik Kinerja <span class="mobile-break"></span>(SI-LIAK) <span class="mobile-break"></span>Untuk Meningkatkan Partisipasi Guru Dalam Kolaborasi Perangkat dan Materi Pembelajaran.</p>
                
                <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px; position: relative; z-index: 50;">
                    <div class="badge" style="margin-top: 0;">GURU</div>
                </div>
                </div>
                <div class="profile-panel">
                    <?php if(!empty($profile_photo_path)){ ?>
                        <img src="<?= htmlspecialchars($profile_photo_path); ?>" class="profile-photo" alt="Foto profil">
                    <?php }else{ ?>
                        <div class="profile-initial"><?= htmlspecialchars($profile_initial); ?></div>
                    <?php } ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                        <input type="hidden" name="upload_profile_photo" value="1">
                        <label class="photo-button">
                            📸 Ganti Foto
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required style="display:none;" onchange="this.form.submit()">
                        </label>
                    </form>
                    <?php if(!empty($upload_message)){ ?>
                        <div class="upload-alert <?= htmlspecialchars($upload_status); ?>">
                            <?= htmlspecialchars($upload_message); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- TOP CARD -->
        <div class="grid">
            <div class="card">
                <h3 style="margin-top: 0; color: #e67e22; font-weight: bold; margin-bottom: 12px; font-size: 16px;">📢 Pengumuman Admin</h3>
                <p style="font-size: 14px; color: #34495e; line-height: 1.6; margin: 0;">
                    <?= nl2br(htmlspecialchars($pengumuman_teks)); ?>
                </p>
                <?php if(!empty($pengumuman_file)){ ?>
                <div style="margin-top: 15px;">
                    <a href="download_pengumuman.php?id=<?= $pengumuman_data['id']; ?>" target="_blank" style="display: inline-block; background: #3498db; color: white; padding: 8px 15px; border-radius: 6px; font-size: 12px; font-weight: bold; text-decoration: none; transition: 0.3s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">📎 Lihat / Unduh Lampiran</a>
                </div>
                <?php } ?>
                <?php if(!empty($pengumuman_tanggal)){ ?>
                <div style="margin-top: 15px; font-size: 12px; color: #95a5a6; font-style: italic;">
                    Diperbarui: <?= $pengumuman_tanggal; ?>
                </div>
                <?php } ?>
            </div>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #2c3e50; font-weight: bold;">Request Data Materi</h3>
                    <!-- NOTIFICATION BELL -->
                    <div style="position:relative;">
                        <button onclick="toggleNotif()" style="background:#ecf0f1; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer; position:relative; display:flex; align-items:center; justify-content:center; transition:0.3s;" onmouseover="this.style.background='#dfe6e9'" onmouseout="this.style.background='#ecf0f1'">
                            <span style="font-size:20px;">🔔</span>
                            <?php if($unread_count > 0){ ?>
                                <span style="position:absolute; top:-2px; right:-2px; background:#e74c3c; color:white; font-size:10px; font-weight:bold; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid white;"><?= $unread_count; ?></span>
                            <?php } ?>
                        </button>
                        
                        <div id="notifDropdown" style="display:none; position:absolute; right:0; top:50px; width:320px; background:white; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:100; overflow:hidden; border:1px solid #eee;">
                            <div style="background:#2c3e50; padding:15px; display:flex; justify-content:space-between; align-items:center;">
                                <h4 style="margin:0; color:white; font-size:14px;">Notifikasi Request Data Materi Anda</h4>
                                <?php if($unread_count > 0){ ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                    <button type="submit" name="mark_read" style="background:none; border:none; color:#3498db; font-size:12px; cursor:pointer; font-weight:bold; padding:0;">Tandai Dibaca ✓</button>
                                </form>
                                <?php } ?>
                            </div>
                            <div style="max-height:300px; overflow-y:auto; padding:10px; text-align:left;">
                                <?php if($unread_count > 0){ 
                                    while($notif = mysqli_fetch_assoc($notif_query)){
                                ?>
                                    <div style="padding:12px; border-bottom:1px solid #eee; display:flex; gap:12px;">
                                        <div style="font-size:20px;">✅</div>
                                        <div>
                                            <div style="font-size:13px; color:#2c3e50; font-weight:bold; margin-bottom:4px;">Request "<?= htmlspecialchars($notif['jenis_request']); ?>"</div>
                                            <div style="font-size:12px; color:#7f8c8d; line-height:1.4;"><?= htmlspecialchars(str_replace(['Sistem (Otomatis): ', 'Sistem: '], '', $notif['admin_note'])); ?></div>
                                        </div>
                                    </div>
                                <?php } } else { ?>
                                    <div style="padding:20px; text-align:center; color:#7f8c8d; font-size:13px;">Tidak ada notifikasi baru.</div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                    <select name="jenis_request" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ccc; outline: none; font-size: 13px;" required>
                        <option value="">-- Pilih Jenis Request --</option>
                        <option value="Materi Pembelajaran">1. Materi Pembelajaran</option>
                        <option value="Soal Latihan">2. Soal Latihan</option>
                        <option value="Perangkat Pembelajaran">3. Perangkat Pembelajaran</option>
                        <option value="Refleksi">4. Refleksi</option>
                    </select>
                    <select name="kelas_request" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ccc; outline: none; font-size: 13px;" required>
                        <option value="">-- Pilih Kelas --</option>
                        <option value="Kelas 10">Kelas 10</option>
                        <option value="Kelas 11">Kelas 11</option>
                        <option value="Kelas 12">Kelas 12</option>
                    </select>
                    <textarea name="deskripsi_request" rows="2" placeholder="Tuliskan deskripsi spesifik materi yang di-request..." style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 8px; border: 1px solid #ccc; outline: none; resize: none; font-family: Arial; font-size: 13px;" required></textarea>
                    <button 
                        type="submit" 
                        name="submit_request" 
                        style="width: 100%; padding: 10px; background: #3498db; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px; transition: 0.3s;" 
                        onmouseover="this.style.background='#2980b9'" 
                        onmouseout="this.style.background='#3498db'"
                    >
                        Kirim Request
                    </button>
                </form>
            </div>
        </div>

        <!-- TOPIK MATERI PALING DIPERLUKAN -->
        <?php if($ada_materi || !empty($materi_diperlukan_list)){ ?>
        <div class="card" style="margin-bottom: 25px;">
            <h2 style="margin-top:0; color:#2c3e50; font-size:22px; margin-bottom:15px;">Topik Materi Paling Diperlukan</h2>
            <?php if(!empty($materi_diperlukan_list)){ ?>
                <p style="color:#7f8c8d; font-size:14px; margin-top:-5px; margin-bottom:15px;">Topik materi yang saat ini paling banyak di-request (Antrean: <strong><?= $scope_kebutuhan; ?></strong>).</p>
                <div class="carousel-wrapper" style="position:relative;">
                    <?php if(count($materi_diperlukan_list) > 1){ ?>
                        <button class="carousel-btn prev" onclick="scrollTopikDiperlukan(-1)">&#10094;</button>
                    <?php } ?>
                    <div class="request-scroll-container" id="requestCarouselTopik" style="scrollbar-width:none;">
                    <?php foreach($materi_diperlukan_list as $item){ ?>
                        <div class="request-card">
                            <div>
                                <div style="margin-top:12px; margin-bottom:12px;">
                                    <span style="display:inline-block; background:#ecf0f1; color:#34495e; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold;"><?= htmlspecialchars($item['jenis']); ?></span>
                                </div>
                                <p style="font-size: 15px; color: #2c3e50; font-weight: bold; line-height: 1.4; margin-top: 0; margin-bottom: 15px;">
                                    <?= htmlspecialchars($item['detail']); ?>
                                </p>
                            </div>
                            <div style="margin-top: auto; padding-top: 10px;">
                                <span style="display:inline-block; background:#eafaf1; color:#27ae60; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:bold; border:1px solid #2ecc71; margin-bottom: 8px;">Diminta oleh <?= $item['jumlah']; ?> guru</span>
                                <div style="font-size:11px; color:#7f8c8d; line-height:1.4;">
                                    <strong>Pemohon:</strong> <?= htmlspecialchars($item['requesters']); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    </div>
                    <?php if(count($materi_diperlukan_list) > 1){ ?>
                        <button class="carousel-btn next" onclick="scrollTopikDiperlukan(1)">&#10095;</button>
                    <?php } ?>
                </div>
            <?php }else{ ?>
                <div style="width:100%; padding:30px; text-align:center; color:#7f8c8d; background:#f8f9fa; border-radius:12px; border:1px dashed #ccc;">Belum ada request spesifik yang sedang dibutuhkan saat ini.</div>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- STATUS REQUEST MATERI -->
        <div class="card" style="margin-bottom: 25px;">
            <h2 style="margin-top:0; color:#2c3e50; font-size:22px; margin-bottom:15px;">Status Request Materi Anda</h2>
            <p style="color:#7f8c8d; font-size:14px; margin-top:-5px; margin-bottom:15px;">Daftar materi yang pernah Anda request beserta statusnya saat ini.</p>
            <div class="carousel-wrapper">
                <?php if($request_query && mysqli_num_rows($request_query) > 1){ ?>
                    <button class="carousel-btn prev" onclick="scrollRequestSaya(-1)">&#10094;</button>
                <?php } ?>
                <div class="request-scroll-container" id="requestCarouselSaya">
                <?php if($request_query && mysqli_num_rows($request_query) > 0){
                    while($req = mysqli_fetch_assoc($request_query)){
                        $badge_color = '#e74c3c'; // pending
                        if($req['status'] == 'diproses') $badge_color = '#f39c12';
                        if($req['status'] == 'selesai') $badge_color = '#27ae60';
                ?>
                <div class="request-card">
                    <div>
                        <span style="color:#7f8c8d; font-size:12px;"><i style="font-style:normal;">📅</i> <?= date('d M Y H:i', strtotime($req['created_at'])); ?></span>
                        <div style="margin-top:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="display:inline-block; background:#ecf0f1; color:#34495e; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold;"><?= htmlspecialchars($req['jenis_request']); ?></span>
                            <span style="display:inline-block; text-align:center; box-sizing:border-box; background:<?= $badge_color; ?>; color:white; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold; text-transform:uppercase;">
                                <?= $req['status']; ?>
                            </span>
                        </div>
                        <p style="color:#555; font-size:13px; line-height:1.5; margin-top:0; margin-bottom:15px;">
                            <?= nl2br(htmlspecialchars($req['deskripsi'])); ?>
                        </p>
                        <?php if($req['status'] == 'selesai'){ ?>
                            <div style="margin-bottom:15px; font-size:12px;">
                                <a href="data_materi.php" style="color:#27ae60; text-decoration:none; font-weight:bold;">Cek materi di menu Data Materi</a>
                                <?php if(isset($req['admin_note']) && !empty($req['admin_note'])){ 
                                    $is_system_note = (stripos($req['admin_note'], 'Pencarian Otomatis') !== false || stripos($req['admin_note'], 'Sistem') === 0);
                                    $note_label = $is_system_note ? 'Catatan Sistem:' : 'Catatan Admin:';
                                    $clean_note = preg_replace('/^(Pencarian Otomatis|Sistem\s*\(Otomatis\)|Sistem):\s*/i', '', $req['admin_note']);
                                    
                                    $bg_color = $is_system_note ? '#eafaf1' : '#ebf5ff';
                                    $border_color = $is_system_note ? '#27ae60' : '#3498db';
                                    $text_color = $is_system_note ? '#1e8449' : '#2980b9';
                                ?>
                                    <div style="margin-top:6px; padding:8px; background:<?= $bg_color; ?>; border-left:3px solid <?= $border_color; ?>; color:<?= $text_color; ?>; border-radius:4px; font-size:11px;">
                                        <strong><?= $note_label; ?></strong> <?= htmlspecialchars($clean_note); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if($req['status'] == 'selesai'){ ?>
                    <div style="margin-top: auto; padding-top: 10px;">
                        <a href="javascript:void(0);" onclick="confirmDeleteRequest('?hapus_request=<?= $req['id']; ?>&csrf_token=<?= $csrf_token; ?>');" style="display:block; text-align:center; background:#e74c3c; color:white; padding:10px 12px; border-radius:8px; font-size:12px; text-decoration:none; font-weight:bold; transition: 0.3s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Hapus Riwayat</a>
                    </div>
                    <?php } ?>
                </div>
                <?php } } else { ?>
                <div style="width:100%; padding:30px; text-align:center; color:#7f8c8d; background:#f8f9fa; border-radius:12px; border:1px dashed #ccc;">Belum ada history request materi.</div>
                <?php } ?>
                </div>
                <?php if($request_query && mysqli_num_rows($request_query) > 1){ ?>
                    <button class="carousel-btn next" onclick="scrollRequestSaya(1)">&#10095;</button>
                <?php } ?>
            </div>
        </div>

        <!-- PAPAN REQUEST TERBUKA -->
        <div class="card" style="margin-bottom: 25px; border-left: 5px solid #3498db;">
             <h2 style="margin-top:0; color:#2c3e50; font-size:22px; margin-bottom:15px;">Papan Request Guru Lain</h2>
             <p style="color:#7f8c8d; font-size:14px; margin-top:-5px; margin-bottom:15px;">Mari berkolaborasi! Jika Anda memiliki materi yang sedang dibutuhkan oleh rekan di bawah ini, silakan bantu dengan mengunggahnya ke platform.</p>
             <div class="carousel-wrapper">
                 <?php if($open_request_query && mysqli_num_rows($open_request_query) > 1){ ?>
                     <button class="carousel-btn prev" onclick="scrollRequestLain(-1)">&#10094;</button>
                 <?php } ?>
                 <div class="request-scroll-container" id="requestCarouselLain">
                 <?php if($open_request_query && mysqli_num_rows($open_request_query) > 0){
                     while($open_req = mysqli_fetch_assoc($open_request_query)){
                 ?>
                 <div class="request-card">
                     <div>
                         <strong style="color:#2c3e50; font-size:15px;"><?= htmlspecialchars($open_req['full_name']); ?></strong><br>
                         <span style="color:#7f8c8d; font-size:12px;"><?= htmlspecialchars(isset($open_req['school_name']) ? $open_req['school_name'] : '-'); ?></span>
                         <div style="margin-top:12px; margin-bottom:12px;">
                             <span style="display:inline-block; background:#ecf0f1; color:#34495e; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:bold;"><?= htmlspecialchars($open_req['jenis_request']); ?></span>
                         </div>
                         <p style="color:#555; font-size:13px; line-height:1.5; margin-top:0; margin-bottom:15px;">
                             <?= nl2br(htmlspecialchars($open_req['deskripsi'])); ?>
                         </p>
                     </div>
                     <div>
                         <a href="upload_materi.php?request_id=<?= $open_req['id']; ?>" style="display:block; text-align:center; background:#3498db; color:white; padding:10px 12px; border-radius:8px; font-size:12px; text-decoration:none; font-weight:bold; transition: 0.3s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Bantu Upload Materi</a>
                     </div>
                 </div>
                 <?php } } else { ?>
                 <div style="width:100%; padding:30px; text-align:center; color:#7f8c8d; background:#f8f9fa; border-radius:12px; border:1px dashed #ccc;">Saat ini tidak ada request terbuka dari rekan guru yang lain.</div>
                 <?php } ?>
                 </div>
                 <?php if($open_request_query && mysqli_num_rows($open_request_query) > 1){ ?>
                     <button class="carousel-btn next" onclick="scrollRequestLain(1)">&#10095;</button>
                 <?php } ?>
             </div>
        </div>

        <!-- AKTIVITAS LOGIN HARI INI -->
        <div class="accordion-card" style="border-left: 5px solid #2ecc71;">
            <div class="accordion-header">
                <h2>Guru yang Sedang Aktif Hari Ini</h2>
            </div>
            <div class="accordion-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top:-10px; margin-bottom:20px; flex-wrap: wrap; gap: 10px;">
                    <p style="color:#7f8c8d; font-size:14px; margin:0;">Daftar guru yang baru saja masuk ke platform MGMP hari ini.</p>
                    <?php if($recent_logins_query && mysqli_num_rows($recent_logins_query) > 2){ ?>
                    <div style="display: flex; gap: 10px;">
                        <button class="carousel-btn" style="width: 35px; height: 35px; font-size: 14px; margin: 0;" onclick="scrollActiveTeacher(-1)">&#10094;</button>
                        <button class="carousel-btn" style="width: 35px; height: 35px; font-size: 14px; margin: 0;" onclick="scrollActiveTeacher(1)">&#10095;</button>
                    </div>
                    <?php } ?>
                </div>
                
                <?php if($recent_logins_query && mysqli_num_rows($recent_logins_query) > 0){ ?>
                <div class="active-teacher-carousel-wrapper">
                    <div class="active-teacher-list" id="activeTeacherCarousel">
                        <?php
                        while($login = mysqli_fetch_assoc($recent_logins_query)){
                            $login_time = date('H:i', strtotime($login['login_time']));
                            $initial_login = strtoupper(substr(trim($login['full_name']), 0, 1));
                            $photo_login = isset($login['profile_photo']) ? $login['profile_photo'] : '';
                            $is_me = ($login['id'] == $user_id);
                        ?>
                        <div class="active-teacher-card">
                            <?php if(!empty($photo_login) && file_exists(__DIR__ . "/" . $photo_login)){ ?>
                                <img src="<?= htmlspecialchars($photo_login); ?>" class="active-user-photo" style="width:45px; height:45px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                            <?php }else{ ?>
                                <div class="active-user-photo" style="width:45px; height:45px; border-radius:50%; background:#2c3e50; color:white; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:bold; flex-shrink:0;">
                                    <?= htmlspecialchars($initial_login); ?>
                                </div>
                            <?php } ?>
                            <div style="flex:1; min-width:0;">
                                <strong style="color:#2c3e50; font-size:14px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($login['full_name']); ?> 
                                    <?= $is_me ? '<span style="color:#27ae60; font-size:11px;">(Anda)</span>' : ''; ?>
                                </strong>
                                <span style="color:#7f8c8d; font-size:12px; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars(isset($login['school_name']) ? $login['school_name'] : '-'); ?>
                                </span>
                                <?php if($login['role_id'] == 4){ echo '<div style="margin-top:4px;"><span style="display:inline-block; background:#fdf2e9; color:#e67e22; padding:2px 6px; border-radius:4px; font-size:10px; border:1px solid #f39c12;">Ext. Contributor</span></div>'; } ?>
                                <?php if($login['role_id'] == 1){ echo '<div style="margin-top:4px;"><span style="display:inline-block; background:#ebf5ff; color:#2980b9; padding:2px 6px; border-radius:4px; font-size:10px; border:1px solid #3498db;">Admin</span></div>'; } ?>
                            </div>
                            <div style="text-align:right; flex-shrink:0;">
                                <span style="background:#eafaf1; color:#27ae60; padding:4px 8px; border-radius:12px; font-size:11px; font-weight:bold; border:1px solid #2ecc71;">
                                    🟢 <?= $login_time; ?> WITA
                                </span>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <p class="mobile-swipe-hint" style="text-align:center; font-size:12px; color:#95a5a6; margin-top:-10px; margin-bottom:0;">&larr; Geser untuk melihat yang lain &rarr;</p>
                <?php } else { ?>
                <div style="width:100%; padding:20px; text-align:center; color:#7f8c8d; background:#f8f9fa; border-radius:12px; border:1px dashed #ccc;">Belum ada guru yang login hari ini.</div>
                <?php } ?>
            </div>
        </div>

        <!-- INTELLIGENT ANALYTICS -->
        <div class="ai-card">
            <div class="ai-header">
                <div>
                    <h2 class="ai-title">Intelligent Analytics</h2>
                    <p class="ai-subtitle">Sistem menganalisis aktivitas Anda secara otomatis menggunakan Learning Analytics.</p>
                </div>
                <div class="ai-status">
                    <?= $status_ai; ?>
                </div>
            </div>
            <div class="ai-body">
                <div class="ai-recommendation">
                    <span class="ai-recommendation-title">Rekomendasi Sistem</span>
                    <p><?= trim($rekomendasi); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Daftar Guru -->
<div id="guruModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; width: 90%; max-width: 600px; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: 85vh;">
        <div style="background: #2c3e50; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px;">Daftar Guru MGMP</h3>
            <button onclick="closeGuruModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 0; overflow: auto; flex: 1;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 15px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Nama Guru</th>
                        <th style="padding: 15px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Sekolah Asal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_guru_query && mysqli_num_rows($list_guru_query) > 0){
                        while($g = mysqli_fetch_assoc($list_guru_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px; color: #34495e; font-weight: bold;"><?= htmlspecialchars($g['full_name']); ?></td>
                        <td style="padding: 15px; color: #7f8c8d;"><?= htmlspecialchars(isset($g['school_name']) ? $g['school_name'] : '-'); ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="2" style="padding: 30px; text-align: center; color: #7f8c8d;">Belum ada data guru.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal External Contributor -->
<div id="externalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; width: 90%; max-width: 800px; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: 85vh;">
        <div style="background: #2c3e50; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px;">Daftar Akun External Contributor</h3>
            <button onclick="closeExternalModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 0; overflow: auto; flex: 1;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 15px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Nama Kontributor</th>
                        <th style="padding: 15px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Institusi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_external_query && mysqli_num_rows($list_external_query) > 0){
                        while($e = mysqli_fetch_assoc($list_external_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px; color: #34495e;"><strong><?= htmlspecialchars($e['contributor_name']); ?></strong></td>
                        <td style="padding: 15px; color: #7f8c8d;"><?= htmlspecialchars(isset($e['contributor_institution']) ? $e['contributor_institution'] : '-'); ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="2" style="padding: 30px; text-align: center; color: #7f8c8d;">Belum ada akun external contributor terdaftar.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Request -->
<div id="deleteRequestModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 350px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">🗑️</div>
        <h3 style="margin-top: 0; color: #e74c3c; font-size: 20px;">Hapus Riwayat?</h3>
        <p style="color: #555; margin-bottom: 25px; font-size: 14px;">Yakin ingin menghapus riwayat request ini dari daftar Anda?</p>
        <div style="display: flex; justify-content: center; gap: 15px;">
            <button onclick="closeDeleteRequestModal()" style="padding: 10px 20px; border: none; border-radius: 8px; background: #95a5a6; color: white; font-weight: bold; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">Batal</button>
            <a id="confirmDeleteRequestBtn" href="#" style="padding: 10px 20px; border-radius: 8px; background: #e74c3c; color: white; font-weight: bold; text-decoration: none; transition: 0.3s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Hapus</a>
        </div>
    </div>
</div>

<script>
function openGuruModal() { document.getElementById('guruModal').style.display = 'flex'; }
function closeGuruModal() { document.getElementById('guruModal').style.display = 'none'; }
function openExternalModal() { document.getElementById('externalModal').style.display = 'flex'; }
function closeExternalModal() { document.getElementById('externalModal').style.display = 'none'; }
window.onclick = function(e) { 
    let m1 = document.getElementById('guruModal'); 
    let m2 = document.getElementById('deleteRequestModal');
    let m3 = document.getElementById('externalModal');
    if (e.target == m1) { m1.style.display = "none"; } 
    if (e.target == m2) { m2.style.display = "none"; }
    if (e.target == m3) { m3.style.display = "none"; }
}

function scrollRequestLain(direction) {
    const container = document.getElementById('requestCarouselLain');
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function scrollRequestSaya(direction) {
    const container = document.getElementById('requestCarouselSaya');
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function scrollTopikDiperlukan(direction) {
    const container = document.getElementById('requestCarouselTopik');
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function confirmDeleteRequest(url) {
    document.getElementById('confirmDeleteRequestBtn').href = url;
    document.getElementById('deleteRequestModal').style.display = 'flex';
}

function closeDeleteRequestModal() {
    document.getElementById('deleteRequestModal').style.display = 'none';
}

function toggleNotif() {
    let el = document.getElementById('notifDropdown');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
window.addEventListener('click', function(e) {
    let dropdown = document.getElementById('notifDropdown');
    if(dropdown && !e.target.closest('div[style*="position:relative"]') && e.target.tagName !== 'BUTTON') {
        dropdown.style.display = 'none';
    }
});

// Accordion
document.addEventListener("DOMContentLoaded", function() {
    var acc = document.getElementsByClassName("accordion-header");
    for (var i = 0; i < acc.length; i++) {
        acc[i].addEventListener("click", function() {
            this.classList.toggle("active");
            var panel = this.nextElementSibling;
            if (panel.style.display === "block" || panel.style.display === "") {
                panel.style.display = "none";
            } else {
                panel.style.display = "block";
            }
        });
    }
});

function scrollActiveTeacher(direction) {
    const container = document.getElementById('activeTeacherCarousel');
    const scrollAmount = 335; // width of card (320) + gap (15)
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}
</script>

<!-- Modal Result Request Otomatis -->
<?php if(!empty($req_popup_type)){ ?>
<div id="resultModal" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; width: 90%; max-width: 420px; border-radius: 16px; padding: 35px 25px; text-align: center; box-shadow: 0 15px 35px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <?php if($req_popup_type == 'selesai'){ ?>
            <div style="margin-bottom: 15px;"><img src="assets/images/logo.png" alt="Logo SI-LIAK" style="width: 120px; max-width: 45%; height: auto;"></div>
        <?php } else { ?>
            <div style="font-size: 65px; margin-bottom: 15px; line-height: 1;">📨</div>
        <?php } ?>
        <h2 style="color: #2c3e50; margin-top: 0; margin-bottom: 15px; font-size: 24px; font-weight: 800;"><?= $req_popup_title; ?></h2>
        <p style="color: #555; line-height: 1.6; font-size: 15px; margin-bottom: 25px; padding: 0 10px;">
            <?= $req_popup_msg; ?>
        </p>
        <?php if($req_popup_type != 'selesai'){ ?>
            <button onclick="document.getElementById('resultModal').style.display='none'" style="background: #3498db; color: white; border: none; padding: 12px 35px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">Tutup</button>
        <?php } ?>
    </div>
</div>
<style>
@keyframes popIn {
    0% { transform: scale(0.8) translateY(20px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}
</style>
<?php } ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburger-toggle');
    const sidebar = document.getElementById('sidebar-menu');
    
    if (hamburgerBtn && sidebar) {
        hamburgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
});
</script>
</body>
</html>

<?php 
// Jalankan sinkronisasi telemetri asimetris (Pseudo-Cron) di akhir agar tidak mengganggu loading dashboard
include_once 'telemetry_sync.php'; 
?>
