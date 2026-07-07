<?php

session_start();

include 'config/database.php';
require_once 'config/functions.php';

// =======================
// CEK LOGIN
// =======================

if(!isset($_SESSION['login'])){

    echo "
    <script>
        location.replace('index.php');
    </script>
    ";

    exit;

}

// =======================
// TENTUKAN DASHBOARD
// =======================

if($_SESSION['role_id'] == 1){

    $redirect = 'dashboard_admin.php';

}else{

    $redirect = 'dashboard.php';

}

// =======================
// CSRF TOKEN
// =======================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];


// =======================
// HANDLE REQUEST ID (PRE-FILL FORM)
// =======================
$request_id = null;
$request_data = null;
$prefill_category = '';
$prefill_grade = '';
$prefill_title = '';
$is_fulfilling_request = false;

if (isset($_GET['request_id'])) {
    $request_id = (int)$_GET['request_id'];
    // Tambahkan kondisi AND status != 'selesai' agar request yang sudah selesai tidak bisa dibantu lagi
    $stmt_req = mysqli_prepare($conn, "SELECT jenis_request, deskripsi FROM material_requests WHERE id = ? AND status != 'selesai'");
    mysqli_stmt_bind_param($stmt_req, "i", $request_id);
    mysqli_stmt_execute($stmt_req);
    $result_req = mysqli_stmt_get_result($stmt_req);
    if ($row_req = mysqli_fetch_assoc($result_req)) {
        $is_fulfilling_request = true;
        $request_data = $row_req;
        $prefill_category = $request_data['jenis_request'];

        // Ekstrak kelas dari deskripsi
        if (preg_match('/Target Kelas: (Kelas \d+)/', $request_data['deskripsi'], $matches)) {
            $prefill_grade = $matches[1];
        }
        // Ekstrak detail request untuk prefill judul
        if (preg_match('/Detail Request: (.*)/s', $request_data['deskripsi'], $matches_desc)) {
            $prefill_title = trim($matches_desc[1]);
        }
    }
    mysqli_stmt_close($stmt_req);
}

// =======================
// PROSES UPLOAD
// =======================

if(isset($_POST['upload'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    // =======================
    // AMBIL DATA
    // =======================

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $grade_level = trim($_POST['grade_level']);

    // =======================
    // VALIDASI INPUT
    // =======================

    if(
        empty($title)
        ||
        empty($description)
        ||
        empty($category)
        ||
        empty($grade_level)
    ){
        $_SESSION['upload_error'] = 'Semua form wajib diisi!';
        header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
        exit;

    }

    // =======================
    // VALIDASI FILE
    // =======================

    if(
        !isset($_FILES['file'])
        ||
        $_FILES['file']['error'] != 0
    ){
        $_SESSION['upload_error'] = 'File materi belum dipilih!';
        header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
        exit;

    }

    // =======================
    // DATA FILE
    // =======================

    $file_name = $_FILES['file']['name'];

    $tmp_name = $_FILES['file']['tmp_name'];

    $file_size = $_FILES['file']['size'];

    $file_type = $_FILES['file']['type'];

    // =======================
    // VALIDASI SIZE
    // =======================

    $max_size = 2 * 1024 * 1024;

    if($file_size > $max_size){
        $_SESSION['upload_error'] = 'Ukuran file maksimal 2 MB!';
        header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
        exit;

    }

    // =======================
    // EXTENSION FILE & MIME TYPE (REAL)
    // =======================

    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    // =======================
    // VALIDASI BERDASARKAN CATEGORY
    // =======================

    if($category == 'Perangkat Pembelajaran'){

        $allowed_extension = ['zip', 'rar'];
        $allowed_mime = [
            'application/zip', 
            'application/x-zip-compressed', 
            'multipart/x-zip',
            'application/x-rar-compressed', 
            'application/vnd.rar', 
            'application/x-rar',
            'application/octet-stream'
        ];

        if(!in_array($file_extension, $allowed_extension) || !in_array($mime_type, $allowed_mime)){
            $_SESSION['upload_error'] = 'Perangkat Pembelajaran hanya boleh file ZIP atau RAR yang valid!';
            header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
            exit;
        }

    }else{

        $allowed_extension = ['pdf', 'docx', 'pptx', 'xlsx'];
        $allowed_mime = [
            'application/pdf', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream' // Terkadang office file dibaca sebagai octet-stream
        ];

        if(!in_array($file_extension, $allowed_extension) || !in_array($mime_type, $allowed_mime)){
            $_SESSION['upload_error'] = 'Format file tidak diizinkan! (Hanya PDF, DOCX, PPTX, XLSX yang valid)';
            header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
            exit;
        }

    }

    // =======================
    // HASH FILE
    // =======================

    $file_hash = md5_file($tmp_name);

    // =======================
    // CEK DUPLIKAT
    // =======================

    $stmt_hash = mysqli_prepare($conn, "SELECT id FROM materials WHERE file_hash = ?");
    mysqli_stmt_bind_param($stmt_hash, "s", $file_hash);
    mysqli_stmt_execute($stmt_hash);
    mysqli_stmt_store_result($stmt_hash);
    $is_duplicate = mysqli_stmt_num_rows($stmt_hash) > 0;
    mysqli_stmt_close($stmt_hash);

    if($is_duplicate){
        $_SESSION['upload_error'] = 'Materi dengan file yang sama persis sudah pernah diupload di sistem!';
        $_SESSION['redirect_url'] = $redirect;
        header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
        exit;

    }

    // =======================
    // NAMA FILE BARU
    // =======================

    $base_file_name =

        time()
        .
        "_"
        .
        preg_replace(
            '/[^a-zA-Z0-9.-]/',
            '_',
            $file_name
        );

    if($file_extension == 'docx'){
        $new_file_name = "docs/" . $base_file_name;
        $folder_path = "assets/uploads/docs";
    }else{
        $new_file_name = $base_file_name;
        $folder_path = "assets/uploads";
    }

    // =======================
    // CEK FOLDER
    // =======================

    if(!is_dir($folder_path)){

        mkdir(
            $folder_path,
            0777,
            true
        );

    }

    // =======================
    // PATH FILE
    // =======================

    $upload_path =

        "assets/uploads/"
        .
        $new_file_name;

    // =======================
    // FOLDER ID
    // =======================

    $folder_id = 0;
    $cat_safe = mysqli_real_escape_string($conn, $category);
    $get_folder = mysqli_query($conn, "SELECT id FROM folders WHERE folder_name LIKE '%$cat_safe%' LIMIT 1");
    if ($get_folder && mysqli_num_rows($get_folder) > 0) {
        $f_row = mysqli_fetch_assoc($get_folder);
        $folder_id = $f_row['id'];
    }

    // =======================
    // UPLOAD FILE
    // =======================

    if(move_uploaded_file($tmp_name, $upload_path)){

        // =======================
        // INSERT DATABASE
        // =======================

        $stmt = mysqli_prepare($conn, "
            INSERT INTO materials (
                user_id, title, description, category, grade_level, folder_id, file_name, file_size, file_type, file_hash, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
        ");
        mysqli_stmt_bind_param($stmt, "issssisiss", $_SESSION['user_id'], $title, $description, $category, $grade_level, $folder_id, $new_file_name, $file_size, $file_type, $file_hash);
        mysqli_stmt_execute($stmt);
        $insert = mysqli_stmt_affected_rows($stmt) > 0;
        $new_material_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // =======================
        // BERHASIL
        // =======================

        if($insert){

            // Jika upload ini untuk memenuhi request, update status request
            if(isset($_POST['request_id']) && !empty($_POST['request_id'])){
                $fulfilled_request_id = (int)$_POST['request_id'];
                
                // Cek dan buat kolom admin_note jika belum ada
                $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
                if($cek_col && mysqli_num_rows($cek_col) == 0){
                    mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
                }
                
                $uploader_name = $_SESSION['name'];
                $is_admin = ($_SESSION['role_id'] == 1);
                
                if($is_admin) {
                    $admin_note = mysqli_real_escape_string($conn, "Materi telah diunggah oleh Admin (" . $uploader_name . ") sebagai respons atas request ini. Silakan cek di menu Data Materi.");
                } else {
                    $admin_note = mysqli_real_escape_string($conn, "Sistem: Materi telah dibantu unggah oleh rekan guru (" . $uploader_name . ") sebagai respons atas request Anda. Silakan cek di menu Data Materi.");
                }
                
                $stmt_update_req = mysqli_prepare($conn, "UPDATE material_requests SET status = 'selesai', admin_note = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update_req, "si", $admin_note, $fulfilled_request_id);
                mysqli_stmt_execute($stmt_update_req);
                mysqli_stmt_close($stmt_update_req);
            }

            // ==========================================
            // SELALU JALANKAN SMART MATCHING UNTUK REQUEST LAIN YANG MIRIP
            // ==========================================
            $uploader_name = $_SESSION['name'];
            $auto_admin_note = mysqli_real_escape_string($conn, "Sistem (Otomatis): Materi yang mungkin relevan dengan request Anda telah diunggah oleh (" . $uploader_name . "). Silakan cari di menu Data Materi menggunakan kata kunci request Anda.");
            
            // Panggil fungsi helper dari database.php
            jalankanSmartMatching($conn, $title, $category, $grade_level, $auto_admin_note);

            $_SESSION['upload_success'] = 'Upload materi berhasil!';
            $_SESSION['redirect_url'] = $redirect;
            header("Location: upload_materi.php");
            exit;

        }else{

            if(file_exists($upload_path)){

                unlink($upload_path);

            }
            
            $_SESSION['upload_error'] = 'Terjadi kesalahan sistem saat menyimpan ke database!';
            header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
            exit;

        }

    }else{
        $_SESSION['upload_error'] = 'Gagal mengunggah file materi ke server!';
        header("Location: upload_materi.php" . (isset($_POST['request_id']) ? "?request_id=".$_POST['request_id'] : ""));
        exit;

    }

}

// =======================
// POPUP VARIABLES
// =======================
$upload_error = isset($_SESSION['upload_error']) ? $_SESSION['upload_error'] : '';
$upload_success = isset($_SESSION['upload_success']) ? $_SESSION['upload_success'] : '';
$redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '';

unset($_SESSION['upload_error']);
unset($_SESSION['upload_success']);
unset($_SESSION['redirect_url']);

?>

<!DOCTYPE html>
<html>
<head>

    <title>Upload Materi</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>

        body{

            margin:0;
            font-family:Arial;
            background:#f4f4f4;

        }

        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .main-content{ flex:1; min-width:0; padding-top:40px; }

        .box{

            width:550px;
            max-width: 90%;
            background:white;
            padding:25px;
            margin:20px auto; /* Kurangi margin atas bawah di HP */
            border-radius:10px;
            box-shadow:0px 0px 10px rgba(0,0,0,0.1);
            box-sizing: border-box; /* Agar padding tidak menambah lebar */

        }

        h2{

            text-align:center;
            margin-bottom:20px;

        }

        input,
        textarea,
        select{

            width:100%;
            padding:12px;
            margin-top:10px;
            box-sizing:border-box;
            border:1px solid #ccc;
            border-radius:5px;

        }

        textarea{

            resize:none;

        }

        .button-group{

            display:flex;
            gap:15px;
            margin-top:15px;

        }

        .upload-btn{

            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#16a34a;
            color:white;
            border-radius:8px;
            cursor:pointer;
            text-align:center;
            font-size:18px;
            font-weight:bold;
            height:58px;
            transition:0.3s;

        }

        .upload-btn:hover{

            background:#15803d;

        }

        .submit-btn{

            flex:1;
            height:58px;
            background:#2c3e50;
            color:white;
            border:none;
            border-radius:8px;
            cursor:pointer;
            font-size:18px;
            font-weight:bold;
            transition:0.3s;

        }

        .submit-btn:hover{

            background:#1e293b;

        }

        #file-name{

            margin-top:12px;
            font-size:14px;
            color:#333;
            font-weight:bold;

        }

        .info{

            margin-top:20px;
            background:#eef5ff;
            padding:15px;
            border-radius:8px;
            line-height:1.8;
            font-size:14px;

        }

        /* =======================
           POPUP MODAL
        ======================= */
        .popup-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center;
            z-index: 9999; opacity: 0; visibility: hidden; transition: 0.3s;
            backdrop-filter: blur(3px);
        }
        .popup-overlay.show { opacity: 1; visibility: visible; }
        .popup-box {
            background: white; padding: 30px; border-radius: 15px; text-align: center;
            width: 90%; max-width: 350px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transform: translateY(-20px) scale(0.9); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .popup-overlay.show .popup-box { transform: translateY(0) scale(1); }
        .popup-icon {
            width: 65px; height: 65px; color: white;
            font-size: 35px; line-height: 65px; border-radius: 50%; margin: 0 auto 15px;
        }
        .popup-message { font-size: 15px; color: #2c3e50; margin-bottom: 25px; font-weight: 600; line-height: 1.6; }
        .popup-btn {
            color: white; border: none; padding: 12px 25px; border-radius: 8px;
            font-size: 15px; cursor: pointer; font-weight: bold; transition: 0.3s; width: 100%;
        }

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

        @media(max-width:768px){

            .box{
                width:95%;
            }

            .button-group{
                flex-direction:column;
            }

            .wrapper{ flex-direction:column; }
            .mobile-nav { display: flex; }
            .sidebar{ width:100%; height:auto; position:static; display: none; }
            .sidebar.active { display: block; }
        }

    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- POPUP ERROR / SUCCESS -->
<?php if(!empty($upload_error)): ?>
<div class="popup-overlay show" id="errorPopup">
    <div class="popup-box">
        <div class="popup-icon" style="background:#e74c3c;">✖</div>
        <div class="popup-message"><?= htmlspecialchars($upload_error); ?></div>
        <?php if(!empty($redirect_url)): ?>
            <button class="popup-btn" style="background:#e74c3c;" onclick="window.location.href='<?= htmlspecialchars($redirect_url); ?>'" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Kembali ke Dashboard</button>
        <?php else: ?>
            <button class="popup-btn" style="background:#e74c3c;" onclick="document.getElementById('errorPopup').classList.remove('show')" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Coba Lagi</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if(!empty($upload_success)): ?>
<div class="popup-overlay show" id="successPopup">
    <div class="popup-box">
        <div class="popup-icon" style="background:#27ae60;">✓</div>
        <div class="popup-message"><?= htmlspecialchars($upload_success); ?></div>
        <button class="popup-btn" style="background:#3498db;" onclick="window.location.href='<?= htmlspecialchars($redirect_url ?: $redirect); ?>'" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Kembali ke Dashboard</button>
    </div>
</div>
<?php endif; ?>

<div class="wrapper">

    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <?php $sidebar_role = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 0; ?>
        <div class="logo">
            <?= ($sidebar_role == 1) ? 'ADMIN PANEL' : 'MGMP PLATFORM'; ?>
        </div>
        <div class="menu">
            <?php if($sidebar_role == 1){ ?>
                <a href="dashboard_admin.php">Dashboard</a>
                <a href="monitoring_guru.php">Monitoring Guru</a>
                <a href="data_materi.php">Data Materi</a>
                <a href="upload_materi.php">Upload Materi</a>
                <a href="review_materials.php">Review Contributor</a>
                <a href="kelola_request.php">Request Materi</a>
                <a href="analytics.php">Analytics</a>
                <a href="kelola_user.php">Kelola Akun</a>
            <?php } else { ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="data_materi.php">Data Materi</a>
                <a href="upload_materi.php">Upload Materi</a>
                <a href="analytics.php">Analytics</a>
            <?php } ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

<div class="main-content">
<div class="box">

    <h2>

        Upload Materi

    </h2>

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

        <?php if ($is_fulfilling_request): ?>
            <input type="hidden" name="request_id" value="<?= htmlspecialchars($request_id); ?>">
            
            <div class="info" style="background:#d4edda; color:#155724; border-left: 5px solid #28a745; margin-bottom:20px;">
                Anda sedang membantu memenuhi request materi. Kategori dan Kelas telah dikunci sesuai dengan data request.
            </div>

            <!-- CATEGORY (LOCKED) -->
            <div style="margin-bottom: 10px;">
                <label>Jenis Materi (Terkunci)</label>
                <input type="text" value="<?= htmlspecialchars($prefill_category); ?>" disabled style="background:#eee; cursor: not-allowed;">
                <input type="hidden" name="category" value="<?= htmlspecialchars($prefill_category); ?>">
            </div>

            <!-- GRADE LEVEL (LOCKED) -->
            <div style="margin-bottom: 10px;">
                <label>Kelas (Terkunci)</label>
                <input type="text" value="<?= htmlspecialchars($prefill_grade); ?>" disabled style="background:#eee; cursor: not-allowed;">
                <input type="hidden" name="grade_level" value="<?= htmlspecialchars($prefill_grade); ?>">
            </div>

        <?php else: ?>
        <!-- CATEGORY -->
            <select name="category" id="category" required>
                <option value="">-- Pilih Jenis Materi --</option>
                <option value="Materi Pembelajaran">Materi Pembelajaran</option>
                <option value="Soal Latihan">Soal Latihan</option>
                <option value="Perangkat Pembelajaran">Perangkat Pembelajaran</option>
                <option value="Refleksi">Refleksi</option>
            </select>

        <!-- GRADE LEVEL -->
            <select name="grade_level" id="grade_level" required>
                <option value="">-- Pilih Kelas --</option>
                <option value="Kelas 10">Kelas 10</option>
                <option value="Kelas 11">Kelas 11</option>
                <option value="Kelas 12">Kelas 12</option>
            </select>
        <?php endif; ?>

        <!-- TITLE -->
        
        <?php if ($is_fulfilling_request): ?>
            <div style="margin-top: 10px; margin-bottom: 10px;">
                <label>Judul Materi (Terkunci)</label>
                <input type="text" value="<?= htmlspecialchars($prefill_title); ?>" disabled style="background:#eee; cursor: not-allowed;">
                <input type="hidden" name="title" value="<?= htmlspecialchars($prefill_title); ?>">
            </div>
        <?php else: ?>
            <input
                type="text"
                name="title"
                placeholder="Judul Materi"
                required
            >
        <?php endif; ?>

        <!-- DESCRIPTION -->

        <textarea
            name="description"
            placeholder="Masukkan deskripsi materi."
            rows="5"
            required></textarea>

        <!-- FILE -->

        <input
            type="file"
            name="file"
            id="file"
            hidden
            required
        >

        <!-- BUTTON GROUP -->

        <div class="button-group">

            <label class="upload-btn" for="file">

                Pilih File Materi

            </label>

            <button
                type="submit"
                name="upload"
                class="submit-btn"
            >

                Upload Materi

            </button>

        </div>

        <!-- FILE NAME -->

        <p id="file-name"></p>

        <!-- INFO -->

        <div class="info">

            <b>Materi Pembelajaran, Soal Latihan, & Refleksi:</b>

            <br>

            Hanya PDF, DOCX, PPTX, XLSX

            <br>

            <b>Perangkat Pembelajaran:</b>

            <br>

            Hanya ZIP / RAR

            <br>


            <b>Ukuran maksimal:</b>

            <br>

            2 MB

        </div>

    </form>

</div>

<script>

document
.getElementById('file')
.addEventListener('change', function(){

    if(this.files.length > 0){
        let file = this.files[0];
        
        // Cek ukuran file (Maksimal 2 MB)
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Terlalu Besar!',
                text: 'Ukuran maksimal adalah 2 MB. File Anda berukuran ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB.'
            });
            this.value = ''; // Hapus file yang dipilih
            document.getElementById('file-name').innerHTML = 'Tidak ada file yang dipilih (Maks 2MB)';
            return;
        }

        document.getElementById('file-name').innerHTML =

            "File dipilih : "
            +
            file.name;

    }

});

// Proteksi tambahan: Blokir tombol submit jika file terlalu besar
document.querySelector('form').addEventListener('submit', function(e) {
    let fileInput = document.getElementById('file');
    if (fileInput.files.length > 0) {
        let file = fileInput.files[0];
        if (file.size > 2 * 1024 * 1024) {
            e.preventDefault(); // Hentikan proses upload ke server
            Swal.fire({
                icon: 'error',
                title: 'Gagal Upload!',
                text: 'Ukuran maksimal adalah 2 MB. File Anda berukuran ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB.'
            });
            return false;
        }
    } else {
        // Jika required di-bypass (misal karena hidden)
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'File Belum Dipilih',
            text: 'Silakan pilih file materi terlebih dahulu!'
        });
        return false;
    }
});

</script>

</div>
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
