<?php

session_start();

include 'config/database.php';
require_once 'config/functions.php';

// =====================================
// CEK LOGIN
// =====================================

if(!isset($_SESSION['login'])){

    header("Location:index.php");
    exit;

}

// =====================================
// CEK ROLE ADMIN
// =====================================

if($_SESSION['role_id'] != 1){

    header("Location:index.php");
    exit;

}

// =====================================
// CSRF TOKEN
// =====================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================
// FLASH MESSAGE
// =====================================
$success_message = "";
if(isset($_SESSION['success'])){
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// =====================================
// BUAT TABEL site_settings JIKA BELUM ADA
// =====================================
try {
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    @mysqli_query($conn, "INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('telegram_bot_token', '')");
} catch (Exception $e) {
    // Abaikan jika tidak ada akses CREATE TABLE
}

// =====================================
// SIMPAN TOKEN TELEGRAM (dari form admin)
// =====================================
if(isset($_POST['save_telegram_token'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    $tg_token = trim($_POST['telegram_bot_token']);
    // Validasi format token bot (angka:huruf)
    if(!empty($tg_token) && !preg_match('/^\d+:[A-Za-z0-9_\-]+$/', $tg_token)){
        $_SESSION['success'] = "❌ Format Token tidak valid! Format harus: 1234567890:ABCdef...";
    } else {
        $tg_token_safe = mysqli_real_escape_string($conn, $tg_token);
        mysqli_query($conn, "INSERT INTO site_settings (setting_key, setting_value) 
            VALUES ('telegram_bot_token', '$tg_token_safe')
            ON DUPLICATE KEY UPDATE setting_value = '$tg_token_safe', updated_at = NOW()");
        $_SESSION['success'] = "✅ Token Bot Telegram berhasil disimpan!";
    }
    header("Location: dashboard_admin.php");
    exit;
}

// Ambil token yang sudah tersimpan untuk ditampilkan di form
$tg_token_saved = '';
$tg_token_row = mysqli_query($conn, "SELECT setting_value FROM site_settings WHERE setting_key = 'telegram_bot_token'");
if ($tg_token_row && mysqli_num_rows($tg_token_row) > 0) {
    $tg_token_data = mysqli_fetch_assoc($tg_token_row);
    $tg_token_saved = $tg_token_data['setting_value'];
}

// =====================================
// KELOLA PENGUMUMAN ADMIN
// =====================================

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

if(isset($_POST['submit_pengumuman'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    
    $pesan = mysqli_real_escape_string($conn, trim($_POST['pengumuman_baru']));
    $target_audience = mysqli_real_escape_string($conn, trim($_POST['target_audience']));
    $file_path = null;
    
    if(isset($_FILES['file_pengumuman']) && $_FILES['file_pengumuman']['error'] == UPLOAD_ERR_OK){
        $tmp_name = $_FILES['file_pengumuman']['tmp_name'];
        $name = $_FILES['file_pengumuman']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx'];
        $size = $_FILES['file_pengumuman']['size']; // Ukuran dalam bytes
        
        if(in_array($ext, $allowed) && $size <= 5 * 1024 * 1024){ // Batas 5MB
            $upload_dir = "assets/uploads/announcements";
            $target_dir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $upload_dir);
            if(!is_dir($target_dir)){
                mkdir($target_dir, 0777, true);
            }
            $new_name = time() . "_" . preg_replace('/[^a-zA-Z0-9.-]/', '_', $name); // Sanitize filename
            $target_file = $target_dir . DIRECTORY_SEPARATOR . $new_name;
            $db_path = $upload_dir . "/" . $new_name;
            
            if(move_uploaded_file($tmp_name, $target_file)){
                $file_path = $db_path;
            } else {
                echo "<script>alert('Gagal: File lampiran tidak dapat dipindahkan.'); location.replace('dashboard_admin.php');</script>";
                exit;
            }
        } else {
            echo "<script>alert('Gagal: Format tidak diizinkan atau ukuran file melebihi 5MB.'); location.replace('dashboard_admin.php');</script>";
            exit;
        }
    }
    
    if(!empty($pesan)){
        mysqli_query($conn, "TRUNCATE TABLE announcements");
        
        if ($file_path !== null) {
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (pesan, target_audience, file_path) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $pesan, $target_audience, $file_path);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (pesan, target_audience) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $pesan, $target_audience);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 📢 BROADCAST TELEGRAM ke semua guru
        if (function_exists('broadcastTelegram')) {
            $pesan_broadcast = "📢 <b>Pengumuman Resmi MGMP</b>\n\n";
            $pesan_broadcast .= htmlspecialchars(substr(strip_tags($pesan), 0, 300));
            if (strlen(strip_tags($pesan)) > 300) $pesan_broadcast .= "...";
            $pesan_broadcast .= "\n\n— <i>Admin SI-LIAK</i>";
            broadcastTelegram($conn, $pesan_broadcast);
        }

        $_SESSION['success'] = "Pengumuman berhasil disimpan!";
        header("Location: dashboard_admin.php");
        exit;
    }
}

if(isset($_POST['kosongkan_pengumuman'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    mysqli_query($conn, "TRUNCATE TABLE announcements");
    $_SESSION['success'] = "Pengumuman berhasil dikosongkan!";
    header("Location: dashboard_admin.php");
    exit;
}

if(isset($_POST['update_pengumuman'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $id = (int)$_POST['edit_id'];
    $pesan = mysqli_real_escape_string($conn, trim($_POST['edit_pesan']));
    $target_audience = mysqli_real_escape_string($conn, trim($_POST['edit_target_audience']));
    $hapus_file_lama = isset($_POST['hapus_file_lampiran']) ? (int)$_POST['hapus_file_lampiran'] : 0;

    $file_sql = "";

    // Ambil path file lama
    $q_old = mysqli_query($conn, "SELECT file_path FROM announcements WHERE id = '$id'");
    $old_data = mysqli_fetch_assoc($q_old);
    $old_file_path = $old_data ? $old_data['file_path'] : null;

    // 1. Jika ada file baru diupload
    if(isset($_FILES['edit_file_pengumuman']) && $_FILES['edit_file_pengumuman']['error'] == UPLOAD_ERR_OK){
        $tmp_name = $_FILES['edit_file_pengumuman']['tmp_name'];
        $name = $_FILES['edit_file_pengumuman']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'docx'];
        $size = $_FILES['edit_file_pengumuman']['size'];

        if(in_array($ext, $allowed) && $size <= 5 * 1024 * 1024){ // Batas 5MB
            $upload_dir = "assets/uploads/announcements";
            $new_name = time() . "_edit_" . preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
            $target_file = $upload_dir . "/" . $new_name;
            
            if(move_uploaded_file($tmp_name, $target_file)){
                // Hapus file lama jika ada
                if($old_file_path && file_exists($old_file_path)) unlink($old_file_path);
                $file_sql = ", file_path = '$target_file'";
            }
        } else {
            echo "<script>alert('Gagal: Format file harus PDF/DOCX dan ukuran maksimal 5MB.'); location.replace('dashboard_admin.php');</script>";
            exit;
        }
    } 
    // 2. Jika user mencentang hapus file
    elseif($hapus_file_lama == 1) {
        if($old_file_path && file_exists($old_file_path)) unlink($old_file_path);
        $file_sql = ", file_path = NULL";
    }

    if(!empty($pesan)){
        $stmt = mysqli_prepare($conn, "UPDATE announcements SET pesan = ?, target_audience = ? $file_sql WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $pesan, $target_audience, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['success'] = "Pengumuman berhasil diupdate!";
        header("Location: dashboard_admin.php");
        exit;
    } else {
        echo "<script>alert('Gagal: Pesan tidak boleh kosong.'); location.replace('dashboard_admin.php');</script>";
    }
}

$pengumuman_guru_q = mysqli_query($conn, "SELECT id, pesan, file_path FROM announcements WHERE target_audience IN ('guru', 'all') ORDER BY id DESC LIMIT 1");
$pengumuman_guru = mysqli_fetch_assoc($pengumuman_guru_q);

$pengumuman_external_q = mysqli_query($conn, "SELECT id, pesan, file_path FROM announcements WHERE target_audience IN ('external', 'all') ORDER BY id DESC LIMIT 1");
$pengumuman_external = mysqli_fetch_assoc($pengumuman_external_q);

// =====================================
// DATA ADMIN
// =====================================

$nama_admin = $_SESSION['name'];

// =====================================
// TOTAL GURU
// =====================================

$total_guru_query = mysqli_query($conn, "

    SELECT COUNT(*) AS total_guru

    FROM users

    WHERE role_id = 2

");

$total_guru =
mysqli_fetch_assoc(
    $total_guru_query
)['total_guru'];

// =====================================
// DAFTAR GURU UNTUK MODAL
// =====================================
$list_guru_query = mysqli_query($conn, "
    SELECT full_name, school_name FROM users WHERE role_id = 2 ORDER BY full_name ASC
");

// =====================================
// DATA UNTUK MODAL TOTAL UPLOAD
// =====================================
$list_upload_query = mysqli_query($conn, "
    SELECT m.title, m.created_at, COALESCE(u.full_name, m.contributor_name, 'External') AS uploader 
    FROM materials m 
    LEFT JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC LIMIT 100
");

// =====================================
// DATA UNTUK MODAL TOTAL DOWNLOAD
// =====================================
$list_download_query = mysqli_query($conn, "
    SELECT m.title, u.full_name, d.downloaded_at 
    FROM downloads d 
    JOIN materials m ON d.material_id = m.id 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.downloaded_at DESC LIMIT 100
");

// =====================================
// DATA UNTUK MODAL EXTERNAL CONTRIBUTOR
// =====================================
$list_external_query = mysqli_query($conn, "
    SELECT full_name AS contributor_name, school_name AS contributor_institution
    FROM users
    WHERE role_id = 4
    ORDER BY full_name ASC");

// =====================================
// TOTAL UPLOAD
// =====================================

$total_upload_query = mysqli_query($conn, "

    SELECT COUNT(*) AS total_upload

    FROM materials

");

$total_upload =
mysqli_fetch_assoc(
    $total_upload_query
)['total_upload'];

// =====================================
// TOTAL DOWNLOAD
// =====================================

$total_download_query = mysqli_query($conn, "

    SELECT COUNT(*) AS total_download

    FROM downloads

");

$total_download =
mysqli_fetch_assoc(
    $total_download_query
)['total_download'];

// =====================================
// TOTAL EXTERNAL CONTRIBUTOR
// =====================================

$total_contributor_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total_contributor
    FROM users
    WHERE role_id = 4");

$total_contributor =
mysqli_fetch_assoc(
    $total_contributor_query
)['total_contributor'];

// =====================================
// TOTAL PENDING REVIEW
// =====================================

$total_pending_query = mysqli_query($conn, "

    SELECT COUNT(*) AS total_pending
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE (m.user_id IS NULL OR u.role_id = 4)
    AND m.status = 'pending'

");

$total_pending =
mysqli_fetch_assoc(
    $total_pending_query
)['total_pending'];

// =====================================
// DATA UNTUK MODAL PENDING REVIEW EXTERNAL
// =====================================
$list_pending_ext_query = mysqli_query($conn, "
    SELECT COALESCE(m.contributor_name, u.full_name) AS contributor_name, COALESCE(m.contributor_institution, u.school_name) AS contributor_institution, m.title, m.created_at 
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE (m.user_id IS NULL OR u.role_id = 4) AND m.status = 'pending' 
    ORDER BY m.created_at DESC
");

// =====================================
// TOTAL PENDING REQUEST GURU
// =====================================

$total_pending_req_query = mysqli_query($conn, "

    SELECT COUNT(*) AS total_pending_req

    FROM material_requests

    WHERE status = 'pending'

");

$total_pending_req =
mysqli_fetch_assoc($total_pending_req_query)['total_pending_req'];

// =====================================
// DATA PENDING REQUEST GURU UNTUK MODAL
// =====================================
$pending_requests_query = mysqli_query($conn, "
    SELECT r.*, u.full_name, u.school_name
    FROM material_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
");

// =====================================
// AKTIVITAS LOGIN HARI INI
// =====================================

$cek_photo_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_photo'");
if($cek_photo_column && mysqli_num_rows($cek_photo_column) == 0){
    mysqli_query($conn, "ALTER TABLE users ADD profile_photo VARCHAR(255) NULL");
}

$recent_logins_query = mysqli_query($conn, "
    SELECT u.id, u.full_name, u.school_name, l.login_time, u.profile_photo, u.role_id 
    FROM login_activity l
    JOIN users u ON l.user_id = u.id
    WHERE u.role_id IN (1, 2, 4) AND l.login_date = CURDATE()
    ORDER BY l.login_time DESC
");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Dashboard Admin</title>

    <meta charset="UTF-8">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

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

        .sidebar{

            width:250px;
            height:100vh;

            background:#2c3e50;

            position:sticky;
            align-self:flex-start;
            top:0;

            overflow-y:auto;
            display:flex;
            flex-direction:column;

        }

        .logo{

            color:white;

            text-align:center;

            padding:30px;

            font-size:24px;
            font-weight:bold;

            border-bottom:
            1px solid rgba(255,255,255,0.1);

        }

        .menu a{

            display:block;

            color:white;

            text-decoration:none;

            padding:18px 25px;

            transition:0.3s;

            font-size:16px;

        }

        .menu a:hover{

            background:#34495e;

        }

        .content{

            flex:1;
            min-width:0;
            padding:40px;

        }

        .hero{
            color:white;
            padding:40px;
            border-radius:20px;
            margin-bottom:30px;
        }

        .hero h1{

            margin-top:0;
            font-size:42px;

        }

        .hero p{

            line-height:1.8;
            font-size:16px;

        }

        .grid{

            display:grid;

            grid-template-columns:
            repeat(3,1fr);

            gap:20px;

            margin-bottom:30px;

        }

        .card{

            background:white;

            border-radius:18px;

            padding:30px;

            box-shadow:
            0px 0px 12px
            rgba(0,0,0,0.06);

            margin-bottom:25px;

        }

        .card h3{

            margin:0;
            color:#777;
            font-size:18px;

        }

        .card h1{

            margin-top:15px;
            margin-bottom:0;

            font-size:40px;

            color:#2c3e50;

        }
        
        .clickable-card {
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .clickable-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 8px 20px rgba(0,0,0,0.12);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-body {
            padding: 20px;
            overflow: auto;
            flex: 1;
        }
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }

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
        .carousel-btn {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        .carousel-btn:hover { background: #3498db; }

        @media(max-width:1200px){

            .grid{

                grid-template-columns:
                repeat(2,1fr);

            }

        }

        /* ======================
           MOBILE NAVIGATION (HAMBURGER)
        ====================== */
        .mobile-nav {
            display: none;
            background: #2c3e50;
            padding: 15px 25px;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: white;
        }
        .hamburger-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        @media(max-width:992px){
            .wrapper{
                flex-direction:column;
            }
            .mobile-nav {
                display: flex;
            }
            .grid{
                grid-template-columns:1fr;
            }
            .sidebar{
                position:static;
                width:100%;
                height:auto;
                display: none;
            }
            .sidebar.active {
                display: block;
            }
            .sidebar .logo {
                display: none;
            }
            .content{
                padding:20px;
            }
        }

    </style>

</head>

<body>

<!-- POPUP SUCCESS -->
<?php if(!empty($success_message)){ ?>
<div class="modal" id="successPopup" style="display: flex;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div style="font-size: 50px; margin-bottom: 15px; line-height: 1; color: #27ae60;">✓</div>
        <h3 style="color: #27ae60; margin-top:0;">Berhasil!</h3>
        <p style="color: #555; font-size: 14px; margin-bottom: 25px;"><?= htmlspecialchars($success_message); ?></p>
        <div style="display: flex; justify-content: center;">
            <button style="background:#27ae60; color:white; border:none; padding:10px 25px; border-radius:8px; font-weight:bold; cursor:pointer;" onclick="closeSuccessPopup()">Tutup</button>
        </div>
    </div>
</div>
<script>
    function closeSuccessPopup() {
        const modal = document.getElementById('successPopup');
        modal.style.display = 'none';
    }
    // Jika user klik di luar modal, tutup juga
    window.addEventListener('click', function(e) {
        let modal = document.getElementById('successPopup');
        if (e.target == modal) {
            closeSuccessPopup();
        }
    });
    // Jika user menekan tombol Esc, tutup juga
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") {
            closeSuccessPopup();
        }
    });
</script>
<?php } ?>

<div class="wrapper">
    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform Admin</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <div class="logo">

        ADMIN PANEL

    </div>

    <div class="menu">

        <a href="dashboard_admin.php">
            Dashboard
        </a>

        <a href="monitoring_guru.php">
            Monitoring Guru
        </a>

        <a href="data_materi.php">
            Data Materi
        </a>

        <a href="upload_materi.php">
            Upload Materi
        </a>

        <a href="review_materials.php">
            Review Contributor
            <?php if($total_pending > 0){ ?>
                <span style="background:#e74c3c; color:white; padding:3px 8px; border-radius:12px; font-size:12px; margin-left:5px; font-weight:bold; float:right;">
                    <?= $total_pending; ?>
                </span>
            <?php } ?>
        </a>

        <a href="kelola_request.php">
            Request Materi Guru
        </a>

        <a href="analytics.php">
            Analytics
        </a>

        <a href="kelola_informasi.php">Kelola Informasi Umum</a>

        <a href="kelola_user.php">
            Kelola Akun
        </a>

        <a href="log_aktivitas.php">
            Log Aktivitas (Audit)
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>

</div>

<div class="content">

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
            <p>
                <strong>
                    <?= htmlspecialchars($nama_admin); ?>
                </strong>
            </p>
            <p>
                Hak Akses Tertinggi Dalam Platform (SI-LIAK)
            </p>

        </div>
    </div>

    <div class="grid">

        <div class="card clickable-card" onclick="openGuruModal()">

            <h3>Total Guru</h3>

            <h1>

                <?= $total_guru; ?>

            </h1>
            
            <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

        </div>

        <div class="card clickable-card" onclick="openUploadModal()">

            <h3>Total Upload</h3>

            <h1>

                <?= $total_upload; ?>

            </h1>
            
            <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

        </div>

        <div class="card clickable-card" onclick="openDownloadModal()">

            <h3>Total Download</h3>

            <h1>

                <?= $total_download; ?>

            </h1>
            
            <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

        </div>

        <div class="card clickable-card" onclick="openPendingExtModal()">

            <h3>Pending Review External Contributor</h3>

            <h1>

                <?= $total_pending; ?>

            </h1>
            
            <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

        </div>

            <div class="card clickable-card" onclick="openRequestModal()">

                <h3>Pending Request Materi Guru</h3>

                <h1>

                    <?= $total_pending_req; ?>

                </h1>
                
                <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

            </div>

            <div class="card clickable-card" onclick="openExternalModal()">

                <h3>Total External Contributor</h3>

                <h1>

                    <?= $total_contributor; ?>

                </h1>

                <p style="color:#3498db; font-size:12px; margin-top:10px; margin-bottom:0; font-weight:bold;">Klik untuk melihat detail</p>

            </div>

    </div>

    <!-- KONFIGURASI TELEGRAM BOT -->
    <div class="card" style="margin-bottom: 25px; border-left: 4px solid #0088cc;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 5px;">
            <span style="font-size: 28px;">✈️</span>
            <div>
                <h2 style="margin: 0; color: #2c3e50;">Konfigurasi Telegram Bot</h2>
                <p style="color: #666; margin: 3px 0 0 0; font-size: 13px;">Pasang Token Bot untuk mengaktifkan sistem notifikasi otomatis SI-LIAK ke Telegram guru.</p>
            </div>
        </div>

        <?php
        $tg_status = !empty($tg_token_saved);
        ?>

        <div style="margin: 15px 0; padding: 12px 15px; background: <?= $tg_status ? '#d4edda' : '#fff3cd' ?>; border-radius: 8px; border: 1px solid <?= $tg_status ? '#c3e6cb' : '#ffeeba' ?>;">
            <strong><?= $tg_status ? '🟢 Token Aktif' : '🟡 Token Belum Diisi' ?></strong>
            <?php if($tg_status){ ?>
                <span style="font-family: monospace; color: #555; margin-left: 10px;">
                    <?= substr($tg_token_saved, 0, 10) ?>:••••••••••••••••••
                </span>
            <?php } else { ?>
                <span style="color: #856404; margin-left: 10px; font-size: 13px;">Notifikasi Telegram tidak akan berfungsi sampai token diisi.</span>
            <?php } ?>
        </div>

        <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; font-size: 13px; font-weight: bold; color: #555; margin-bottom: 5px;">
                    Token Bot (dari @BotFather)
                </label>
                <input
                    type="text"
                    name="telegram_bot_token"
                    placeholder="Contoh: 1234567890:ABCdefGHIjklMNOpqrSTUvwxYZ"
                    value="<?= htmlspecialchars($tg_token_saved); ?>"
                    style="width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 8px; font-family: monospace; font-size: 13px; box-sizing: border-box;"
                    autocomplete="off"
                >
            </div>
            <button
                type="submit"
                name="save_telegram_token"
                style="padding: 10px 22px; background: #0088cc; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; transition: 0.2s; white-space: nowrap;"
                onmouseover="this.style.background='#006fa6'"
                onmouseout="this.style.background='#0088cc'"
            >
                💾 Simpan Token
            </button>
        </form>

        <p style="margin: 12px 0 0 0; font-size: 12px; color: #888;">
            💡 Belum punya bot? Buka Telegram → cari <strong>@BotFather</strong> → ketik <code>/newbot</code> → ikuti instruksi → copy token yang diberikan.
        </p>
    </div>

    <!-- PENGUMUMAN ADMIN -->
    <div class="card" style="margin-bottom: 25px;">
        <h2 style="margin-top: 0; color: #2c3e50;">Kelola Pengumuman Dashboard</h2>
        <p style="color: #666; margin-top: -10px; margin-bottom: 15px;">Tulis pengumuman baru untuk ditampilkan pada dashboard pengguna.</p>
        <form method="POST" enctype="multipart/form-data" id="buatPengumumanForm" onsubmit="return validatePengumumanSize('buatPengumumanForm', 'file_pengumuman');">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <?php
            $latest_ann_q = mysqli_query($conn, "SELECT * FROM announcements ORDER BY id DESC LIMIT 1");
            $latest_ann = mysqli_fetch_assoc($latest_ann_q);
            $current_pesan = $latest_ann ? $latest_ann['pesan'] : '';
            $current_target = $latest_ann ? $latest_ann['target_audience'] : 'all';
            ?>
            <textarea name="pengumuman_baru" rows="4" style="width: 100%; padding: 15px; border-radius: 10px; border: 1px solid #ccc; font-family: Arial; font-size: 14px; margin-bottom: 15px; box-sizing: border-box; resize: vertical;" placeholder="Tulis pengumuman baru di sini..." required><?= htmlspecialchars($current_pesan); ?></textarea>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #2c3e50; font-weight: bold;">Target Pengumuman:</label>
                <select name="target_audience" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; font-size: 14px;">
                    <option value="all" <?= $current_target == 'all' ? 'selected' : ''; ?>>Semua Pengguna (Guru & Kontributor)</option>
                    <option value="guru" <?= $current_target == 'guru' ? 'selected' : ''; ?>>Hanya Guru</option>
                    <option value="external" <?= $current_target == 'external' ? 'selected' : ''; ?>>Hanya Kontributor Eksternal</option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-size: 14px; color: #2c3e50; font-weight: bold;">Lampirkan File (Max 5MB):</label>
                <?php if($latest_ann && !empty($latest_ann['file_path'])) { ?>
                    <div style="margin-bottom: 10px; font-size: 13px; color: #34495e;">File saat ini: <a href="<?= htmlspecialchars($latest_ann['file_path']); ?>" target="_blank"><?= basename($latest_ann['file_path']); ?></a></div>
                <?php } ?>
                <div>
                    <input type="file" name="file_pengumuman" id="file_pengumuman" style="display: none;" onchange="validatePengumumanSize('', this.id)">
                    <label for="file_pengumuman" id="file_pengumuman_label" style="display: block; width: 100%; padding: 10px; border-radius: 8px; border: 1px dashed #3498db; font-size: 14px; background: #fbfcfd; box-sizing: border-box; cursor: pointer; color: #2980b9; text-align: center; transition: 0.3s; font-weight: bold;" onmouseover="this.style.background='#ebf5ff'" onmouseout="this.style.background='#fbfcfd'">📁 Klik untuk memilih file lampiran baru...</label>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" name="submit_pengumuman" style="background: #27ae60; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='#219150'" onmouseout="this.style.background='#27ae60'">Simpan Pengumuman</button>
                <button type="button" onclick="openKosongkanModal()" style="background: #e74c3c; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Kosongkan</button>
            </div>
        </form>


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
            <?php } else { ?>
            <div style="width:100%; padding:20px; text-align:center; color:#7f8c8d; background:#f8f9fa; border-radius:12px; border:1px dashed #ccc;">Belum ada guru yang login hari ini.</div>
            <?php } ?>
        </div>
    </div>

</div>

</div>

<!-- Modal Daftar Guru -->
<div id="guruModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Daftar Guru MGMP</h3>
            <button class="close-btn" onclick="closeGuruModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Nama Guru</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Sekolah Asal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_guru_query && mysqli_num_rows($list_guru_query) > 0){
                        while($g = mysqli_fetch_assoc($list_guru_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #34495e;"><strong><?= htmlspecialchars($g['full_name']); ?></strong></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= htmlspecialchars(isset($g['school_name']) ? $g['school_name'] : '-'); ?></td>
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

<!-- Modal Request Materi -->
<div id="requestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Request Materi Guru (Pending)</h3>
            <button class="close-btn" onclick="closeRequestModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Tanggal</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Guru / Sekolah</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Kategori</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($pending_requests_query && mysqli_num_rows($pending_requests_query) > 0){
                        while($req = mysqli_fetch_assoc($pending_requests_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= date('d M Y', strtotime($req['created_at'])); ?></td>
                        <td style="padding: 12px; color: #34495e;">
                            <strong><?= htmlspecialchars($req['full_name']); ?></strong><br>
                            <span style="font-size:12px; color:#7f8c8d;"><?= htmlspecialchars(isset($req['school_name']) ? $req['school_name'] : '-'); ?></span>
                        </td>
                        <td style="padding: 12px; color: #34495e; font-size: 13px;"><strong><?= htmlspecialchars($req['jenis_request']); ?></strong></td>
                        <td style="padding: 12px;">
                            <a href="upload_materi.php?request_id=<?= $req['id']; ?>" style="background:#27ae60; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold; display:inline-block; margin-bottom:4px;">Upload</a>
                            <a href="kelola_request.php?search=<?= urlencode($req['full_name']); ?>" style="background:#3498db; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold; display:inline-block;">Kelola</a>
                        </td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="4" style="padding: 30px; text-align: center; color: #7f8c8d;">Tidak ada request materi yang pending.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Upload -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Daftar Materi (Upload)</h3>
            <button class="close-btn" onclick="closeUploadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Judul Materi</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Uploader</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_upload_query && mysqli_num_rows($list_upload_query) > 0){
                        while($u = mysqli_fetch_assoc($list_upload_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #34495e;"><strong><?= htmlspecialchars($u['title']); ?></strong></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= htmlspecialchars($u['uploader']); ?></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= date('d M Y H:i', strtotime($u['created_at'])); ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="3" style="padding: 30px; text-align: center; color: #7f8c8d;">Belum ada data upload.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Download -->
<div id="downloadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Daftar Aktivitas Download</h3>
            <button class="close-btn" onclick="closeDownloadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Judul Materi</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Diunduh Oleh</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_download_query && mysqli_num_rows($list_download_query) > 0){
                        while($d = mysqli_fetch_assoc($list_download_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #34495e;"><strong><?= htmlspecialchars($d['title']); ?></strong></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= htmlspecialchars($d['full_name']); ?></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= date('d M Y H:i', strtotime($d['downloaded_at'])); ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="3" style="padding: 30px; text-align: center; color: #7f8c8d;">Belum ada data download.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal External Contributor -->
<div id="externalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Daftar Akun External Contributor</h3>
            <button class="close-btn" onclick="closeExternalModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Nama Kontributor</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Institusi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_external_query && mysqli_num_rows($list_external_query) > 0){
                        while($e = mysqli_fetch_assoc($list_external_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #34495e;"><strong><?= htmlspecialchars($e['contributor_name']); ?></strong></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= htmlspecialchars(isset($e['contributor_institution']) ? $e['contributor_institution'] : '-'); ?></td>
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

<!-- Modal Pending External -->
<div id="pendingExtModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Pending Review External Contributor</h3>
            <button class="close-btn" onclick="closePendingExtModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="position: sticky; top: 0; background: #ecf0f1; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Tanggal</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Nama Kontributor</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Materi</th>
                        <th style="padding: 12px; color: #2c3e50; border-bottom: 2px solid #bdc3c7;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($list_pending_ext_query && mysqli_num_rows($list_pending_ext_query) > 0){
                        while($pe = mysqli_fetch_assoc($list_pending_ext_query)){
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= date('d M Y', strtotime($pe['created_at'])); ?></td>
                        <td style="padding: 12px; color: #34495e;"><strong><?= htmlspecialchars($pe['contributor_name']); ?></strong><br><span style="font-size:12px; color:#7f8c8d;"><?= htmlspecialchars($pe['contributor_institution']); ?></span></td>
                        <td style="padding: 12px; color: #7f8c8d; font-size: 13px;"><?= htmlspecialchars($pe['title']); ?></td>
                        <td style="padding: 12px;">
                            <a href="review_materials.php" style="background:#3498db; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold; display:inline-block;">Review</a>
                        </td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="4" style="padding: 30px; text-align: center; color: #7f8c8d;">Tidak ada materi pending.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Edit Pengumuman -->
<div id="editPengumumanModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Edit Pengumuman</h3>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data" id="editPengumumanForm" onsubmit="return validatePengumumanSize('editPengumumanForm', 'edit_file_pengumuman');">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div style="margin-bottom: 15px;">
                    <label for="edit_pesan" style="display: block; margin-bottom: 8px; font-weight: bold; color: #34495e;">Pesan Pengumuman</label>
                    <textarea name="edit_pesan" id="edit_pesan" rows="5" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px;"></textarea>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="edit_target_audience_display" style="display: block; margin-bottom: 8px; font-weight: bold; color: #34495e;">Target (Terkunci)</label>
                    <input type="text" id="edit_target_audience_display" disabled style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #eee; cursor: not-allowed;">
                    <!-- Input tersembunyi ini yang akan mengirimkan data ke server -->
                    <input type="hidden" name="edit_target_audience" id="edit_target_audience_hidden">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #34495e;">Lampiran</label>
                    <div id="current_file_info" style="font-size: 13px; margin-bottom: 10px;"></div>
                    
                    <input type="file" name="edit_file_pengumuman" id="edit_file_pengumuman" style="display: none;" accept=".pdf,.docx" onchange="validatePengumumanSize('', this.id)">
                    <label for="edit_file_pengumuman" id="edit_file_label" style="display: block; width: 100%; padding: 10px; border-radius: 8px; border: 1px dashed #3498db; font-size: 14px; background: #fbfcfd; box-sizing: border-box; cursor: pointer; color: #2980b9; text-align: center; transition: 0.3s; font-weight: bold;">Pilih File Pengumuman</label>

                    <span style="font-size: 12px; color: #7f8c8d; display: block; margin-top: 5px;">* Biarkan kosong jika tidak ingin mengubah lampiran. (PDF/DOCX, Max 5MB)</span>
                </div>

                <div style="text-align: right;">
                    <button type="submit" name="update_pengumuman" style="background: #f39c12; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s;">Update Pengumuman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Kosongkan Pengumuman -->
<div id="kosongkanModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-header" style="background: #e74c3c;">
            <h3>Konfirmasi</h3>
            <button class="close-btn" onclick="closeKosongkanModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="font-size: 50px; color: #e74c3c; margin-bottom: 15px;">⚠️</div>
            <p style="color: #34495e; font-size: 15px; margin-bottom: 25px;">Apakah Anda yakin ingin mengosongkan pengumuman? Pengumuman yang ada saat ini akan dihapus permanen.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeKosongkanModal()" style="background: #95a5a6; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s;">Batal</button>
                    <button type="submit" name="kosongkan_pengumuman" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s;">Ya, Kosongkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('file_pengumuman').addEventListener('change', function(){
    let fileName = this.files[0] ? 'Terpilih: ' + this.files[0].name : '📁 Klik untuk memilih file lampiran...';
    document.getElementById('file_pengumuman_label').innerText = fileName;
});

document.getElementById('edit_file_pengumuman').addEventListener('change', function(){
    let fileName = this.files[0] ? 'File Baru: ' + this.files[0].name : 'Pilih File Pengumuman';
    document.getElementById('edit_file_label').innerText = fileName;
});

function openRequestModal() { document.getElementById('requestModal').style.display = 'flex'; }
function closeRequestModal() { document.getElementById('requestModal').style.display = 'none'; }
function openGuruModal() { document.getElementById('guruModal').style.display = 'flex'; }
function closeGuruModal() { document.getElementById('guruModal').style.display = 'none'; }
function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }
function openDownloadModal() { document.getElementById('downloadModal').style.display = 'flex'; }
function closeDownloadModal() { document.getElementById('downloadModal').style.display = 'none'; }
function openExternalModal() { document.getElementById('externalModal').style.display = 'flex'; }
function closeExternalModal() { document.getElementById('externalModal').style.display = 'none'; }
function openPendingExtModal() { document.getElementById('pendingExtModal').style.display = 'flex'; }
function closePendingExtModal() { document.getElementById('pendingExtModal').style.display = 'none'; }
function openKosongkanModal() { document.getElementById('kosongkanModal').style.display = 'flex'; }
function closeKosongkanModal() { document.getElementById('kosongkanModal').style.display = 'none'; }
function openEditModal(id, pesan, target, filePath) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_pesan').value = pesan;
    
    // Set target audience
    document.getElementById('edit_target_audience_hidden').value = target; // Mengisi input tersembunyi
    const targetDisplay = document.getElementById('edit_target_audience_display');
    targetDisplay.value = (target === 'guru') ? 'Hanya Guru' : 'Hanya Kontributor';
    

    const fileInfoDiv = document.getElementById('current_file_info');
    if (filePath) {
        fileInfoDiv.innerHTML = `File saat ini: <a href="${filePath}" target="_blank">${filePath.split('/').pop()}</a> <label style="margin-left:10px; font-size:12px;"><input type="checkbox" name="hapus_file_lampiran" value="1" form="editPengumumanForm"> Hapus file ini</label>`;
    } else {
        fileInfoDiv.innerHTML = 'Tidak ada file terlampir.';
    }

    document.getElementById('edit_file_label').innerText = 'Pilih File Pengumuman';
    document.getElementById('editPengumumanModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editPengumumanModal').style.display = 'none'; }

window.addEventListener('click', function(e) {
    let m = document.getElementById('requestModal');
    let m2 = document.getElementById('guruModal');
    let m3 = document.getElementById('uploadModal');
    let m4 = document.getElementById('downloadModal');
    let m5 = document.getElementById('externalModal');
    let m6 = document.getElementById('pendingExtModal');
    let m7 = document.getElementById('editPengumumanModal');
    let m8 = document.getElementById('kosongkanModal');
    if (e.target == m) { m.style.display = "none"; }
    if (e.target == m2) { m2.style.display = "none"; }
    if (e.target == m3) { m3.style.display = "none"; }
    if (e.target == m4) { m4.style.display = "none"; }
    if (e.target == m5) { m5.style.display = "none"; }
    if (e.target == m6) { m6.style.display = "none"; }
    if (e.target == m7) { m7.style.display = "none"; }
    if (e.target == m8) { m8.style.display = "none"; }
});

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

<script>
// Mobile Hamburger Toggle
const hamburger = document.getElementById('hamburger-toggle');
const sidebar = document.getElementById('sidebar-menu');
if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

function validatePengumumanSize(formId, inputId) {
    const input = document.getElementById(inputId);
    if (input && input.files && input.files.length > 0) {
        const fileSize = input.files[0].size;
        if (fileSize > 5 * 1024 * 1024) { // 5MB
            Swal.fire({
                icon: 'error',
                title: 'Ukuran File Terlalu Besar',
                text: 'Ukuran file melebihi batas 5MB. Silakan kompres file Anda atau pilih file lain.',
                confirmButtonColor: '#e74c3c'
            });
            input.value = ''; // Reset input agar file raksasa batal dipilih
            return false; // Mencegah form tersubmit
        }
    }
    return true; // Lanjutkan submit
}
</script>

</body>
</html>

<?php 
// Jalankan sinkronisasi telemetri asimetris (Pseudo-Cron) di akhir agar tidak mengganggu loading dashboard
include_once 'telemetry_sync.php'; 
?>
