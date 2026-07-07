<?php

session_start();

include 'config/database.php';
require_once 'config/functions.php';

date_default_timezone_set('Asia/Makassar');

// =====================================
// CEK LOGIN & ROLE
// =====================================

if(!isset($_SESSION['login']) || $_SESSION['role_id'] != 4){

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
// LOGOUT
// =====================================

if(isset($_GET['logout'])){

    session_destroy();

    header("Location:index.php");

    exit;

}

// =====================================
// HAPUS MATERI REJECTED
// =====================================
if(isset($_GET['hapus_rejected'])){
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    
    $del_id = (int)$_GET['hapus_rejected'];
    $session_user_id = $_SESSION['user_id'];
    $cek_mat = mysqli_query($conn, "SELECT file_name FROM materials WHERE id = '$del_id' AND user_id = '$session_user_id' AND status = 'rejected'");
    if($mat = mysqli_fetch_assoc($cek_mat)){
        $path = "assets/uploads/" . $mat['file_name'];
        if(file_exists($path)) unlink($path);
        mysqli_query($conn, "DELETE FROM materials WHERE id = '$del_id'");
        $_SESSION['success'] = "Riwayat materi yang ditolak berhasil dihapus.";
    }
    header("Location: contributor_upload.php");
    exit;
}

// =====================================
// TANDAI MATERI APPROVED SEBAGAI DIBACA
// =====================================
$cek_read_col = mysqli_query($conn, "SHOW COLUMNS FROM materials LIKE 'is_read'");
if($cek_read_col && mysqli_num_rows($cek_read_col) == 0){
    mysqli_query($conn, "ALTER TABLE materials ADD is_read TINYINT(1) DEFAULT 0");
}

if(isset($_POST['mark_approved_read'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    
    $session_user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE materials SET is_read = 1 WHERE user_id = '$session_user_id' AND status = 'approved'");
    
    $_SESSION['success'] = "Pemberitahuan materi disetujui telah disembunyikan.";
    header("Location: contributor_upload.php");
    exit;
}

// =====================================
// FLASH MESSAGE
// =====================================

$message = "";
$success = false;

if(isset($_SESSION['success'])){

    $message = $_SESSION['success'];
    $success = true;

    unset($_SESSION['success']);

}

if(isset($_SESSION['error'])){

    $message = $_SESSION['error'];
    $success = false;

    unset($_SESSION['error']);

}

// =====================================
// DATA USER LOGIN & FOTO PROFIL
// =====================================

$user_id = $_SESSION['user_id'];
$nama_user = $_SESSION['name'];

$upload_message = isset($_SESSION['upload_message']) ? $_SESSION['upload_message'] : "";
$upload_status = isset($_SESSION['upload_status']) ? $_SESSION['upload_status'] : "";

unset($_SESSION['upload_message']);
unset($_SESSION['upload_status']);

$photo_column = "profile_photo";
$cek_photo_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$photo_column'");

if($cek_photo_column && mysqli_num_rows($cek_photo_column) == 0){
    mysqli_query($conn, "ALTER TABLE users ADD profile_photo VARCHAR(255) NULL");
}

$user_query = mysqli_query($conn, "SELECT full_name, school_name, profile_photo FROM users WHERE id = '$user_id'");
$user_data = $user_query ? mysqli_fetch_assoc($user_query) : null;

if($user_data && !empty($user_data['full_name'])){
    $nama_user = $user_data['full_name'];
    $_SESSION['name'] = $nama_user;
}

$profile_photo = isset($user_data['profile_photo']) ? $user_data['profile_photo'] : "";
$profile_photo_path = "";

if(!empty($profile_photo) && file_exists(__DIR__ . "/" . $profile_photo)){
    $profile_photo_path = $profile_photo;
}

$profile_initial = strtoupper(substr(trim($nama_user), 0, 1));

if(empty($profile_initial)){
    $profile_initial = "C";
}

// =====================================
// HITUNG MATERI KONTRIBUTOR & ALASAN
// =====================================

$cek_reject_column = mysqli_query($conn, "SHOW COLUMNS FROM materials LIKE 'reject_reason'");
if($cek_reject_column && mysqli_num_rows($cek_reject_column) == 0){
    mysqli_query($conn, "ALTER TABLE materials ADD reject_reason TEXT NULL");
}

$cek_req_col = mysqli_query($conn, "SHOW COLUMNS FROM materials LIKE 'fulfilled_request_ids'");
if($cek_req_col && mysqli_num_rows($cek_req_col) == 0){
    mysqli_query($conn, "ALTER TABLE materials ADD fulfilled_request_ids VARCHAR(255) NULL");
}

$count_query = mysqli_query($conn, "
    SELECT status, COUNT(*) as total 
    FROM materials 
    WHERE user_id = '$user_id'
    AND (status != 'approved' OR is_read = 0)
    GROUP BY status
");

$counts = ['approved' => 0, 'rejected' => 0, 'pending' => 0];
while($row = mysqli_fetch_assoc($count_query)) {
    $counts[$row['status']] = $row['total'];
}

$has_approved = $counts['approved'] > 0;
$has_rejected = $counts['rejected'] > 0;
$has_pending = $counts['pending'] > 0;

$rejected_details = mysqli_query($conn, "
    SELECT id, title, reject_reason 
    FROM materials 
    WHERE user_id = '$user_id' 
    AND status = 'rejected' 
    ORDER BY created_at DESC
");

// =====================================
// TOPIK MATERI PALING DIPERLUKAN (GLOBAL)
// =====================================

$materi_diperlukan_query = mysqli_query($conn, "
    SELECT 
        GROUP_CONCAT(req.id) AS request_ids,
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

$materi_diperlukan_list = [];
if ($materi_diperlukan_query && mysqli_num_rows($materi_diperlukan_query) > 0) {
    while($row = mysqli_fetch_assoc($materi_diperlukan_query)) {
        $detail = $row['deskripsi'];
        $kelas = "-";
        $judul_saja = $detail;
        if (preg_match('/Target Kelas:\s*(.*?)\r?\nDetail Request:\s*(.*)/s', $row['deskripsi'], $matches)) {
            $kelas = trim($matches[1]);
            $judul_saja = trim($matches[2]);
            $detail = trim($matches[1]) . " - " . trim($matches[2]);
        }
        $materi_diperlukan_list[] = [
            'ids' => $row['request_ids'],
            'jenis' => $row['jenis_request'],
            'kelas' => $kelas,
            'judul_saja' => $judul_saja,
            'detail' => $detail,
            'jumlah' => $row['jumlah_request'],
            'requesters' => $row['requesters']
        ];
    }
}

// =====================================
// PENGUMUMAN ADMIN
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

$pengumuman_query = mysqli_query($conn, "SELECT id, pesan, tanggal, file_path FROM announcements WHERE target_audience IN ('external', 'all') ORDER BY id DESC LIMIT 1");
$pengumuman_data = mysqli_fetch_assoc($pengumuman_query);
$pengumuman_teks = $pengumuman_data ? $pengumuman_data['pesan'] : "Belum ada pengumuman.";
$pengumuman_tanggal = $pengumuman_data ? date('d M Y H:i', strtotime($pengumuman_data['tanggal'])) : "";
$pengumuman_file = $pengumuman_data ? $pengumuman_data['file_path'] : "";

// =====================================
// UPLOAD FOTO PROFIL
// =====================================

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

        if(!in_array($extension, $allowed_extensions) || !in_array($mime_type, $allowed_mimes)){
            $_SESSION['upload_status'] = "error";
            $_SESSION['upload_message'] = "Format foto tidak didukung (Gunakan JPG, PNG, atau WEBP)";
        }else{
            $upload_dir = "uploads/profile_photos";
            $upload_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $upload_dir);

            if(!is_dir($upload_path)){
                mkdir($upload_path, 0777, true);
            }

            $new_file_name = "user_" . $user_id . "_" . time() . "." . $extension;
            $new_file = $upload_dir . "/" . $new_file_name;
            $new_file_path = $upload_path . DIRECTORY_SEPARATOR . $new_file_name;

            if(move_uploaded_file($tmp_name, $new_file_path)){

                $safe_new_file = mysqli_real_escape_string($conn, $new_file);

                $update_photo = mysqli_query($conn, "
                    UPDATE users
                    SET profile_photo = '$safe_new_file'
                    WHERE id = '$user_id'
                ");

                if($update_photo){
                    if(
                        !empty($profile_photo) && 
                        strpos($profile_photo, "uploads/profile_photos/") === 0 && 
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

    header("Location:contributor_upload.php");
    exit;

}

// =====================================
// PROSES UPLOAD
// =====================================

if(isset($_POST['upload'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $name = mysqli_real_escape_string(
        $conn,
        trim($_POST['name'])
    );

    $email = mysqli_real_escape_string(
        $conn,
        trim($_POST['email'])
    );

    $institution = mysqli_real_escape_string(
        $conn,
        trim($_POST['institution'])
    );

    $title = mysqli_real_escape_string(
        $conn,
        trim($_POST['title'])
    );

    $description = mysqli_real_escape_string(
        $conn,
        trim($_POST['description'])
    );

    // =====================================
    // AUTO VALUE
    // =====================================

    $category = !empty($_POST['bantu_kategori']) ? mysqli_real_escape_string($conn, trim($_POST['bantu_kategori'])) : (!empty($_POST['kategori_dropdown']) ? mysqli_real_escape_string($conn, trim($_POST['kategori_dropdown'])) : "External Contributor");
    $fulfilled_request_ids = !empty($_POST['bantu_request_ids']) ? mysqli_real_escape_string($conn, trim($_POST['bantu_request_ids'])) : "";

    $grade_level = !empty($_POST['bantu_kelas']) ? mysqli_real_escape_string($conn, trim($_POST['bantu_kelas'])) : (!empty($_POST['kelas_dropdown']) ? mysqli_real_escape_string($conn, trim($_POST['kelas_dropdown'])) : "-");

    // =====================================
    // VALIDASI
    // =====================================

    if(

        empty($name) ||
        empty($email) ||
        empty($institution) ||
        empty($title)

    ){

        $_SESSION['error'] =
            "Semua field wajib diisi.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    if(

        !isset($_FILES['file']) ||
        $_FILES['file']['name'] == ""

    ){

        $_SESSION['error'] =
            "Silakan pilih file terlebih dahulu.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    $fileName = $_FILES['file']['name'];

    $tmpName = $_FILES['file']['tmp_name'];

    $size = $_FILES['file']['size'];

    $error = $_FILES['file']['error'];

    $allowed = [

        'pdf',
        'doc',
        'docx',
        'ppt',
        'pptx',
        'xls',
        'xlsx',
        'rar',
        'zip'

    ];

    $ext = strtolower(

        pathinfo(
            $fileName,
            PATHINFO_EXTENSION
        )

    );

    if($error !== 0){

        $_SESSION['error'] =
            "Terjadi kesalahan saat upload file.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    if(!in_array($ext, $allowed)){

        $_SESSION['error'] =
            "Format file tidak diizinkan.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    if($size > 2 * 1024 * 1024){

        $_SESSION['error'] =
            "Ukuran file maksimal 2MB.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    // =====================================
    // CEK DUPLIKAT FILE (SCAN ISI)
    // =====================================

    $file_hash = md5_file($tmpName);

    $stmt_hash = mysqli_prepare($conn, "SELECT id FROM materials WHERE file_hash = ?");
    mysqli_stmt_bind_param($stmt_hash, "s", $file_hash);
    mysqli_stmt_execute($stmt_hash);
    mysqli_stmt_store_result($stmt_hash);
    $is_duplicate = mysqli_stmt_num_rows($stmt_hash) > 0;
    mysqli_stmt_close($stmt_hash);

    if($is_duplicate){

        $_SESSION['error'] = "DITOLAK SISTEM FILE SUDAH PERNAH DI UPLOAD";
        header("Location: contributor_upload.php");
        exit;

    }

    $baseName =

        time()
        .
        "_"
        .
        rand(1000,9999)
        .
        "."
        .
        $ext;

    if($ext == 'docx'){
        $newName = "docs/" . $baseName;
        $folderPath = "assets/uploads/docs";
    }else{
        $newName = $baseName;
        $folderPath = "assets/uploads";
    }

    if(!is_dir($folderPath)){

        mkdir(
            $folderPath,
            0777,
            true
        );

    }

    $uploadPath =
        "assets/uploads/" . $newName;

    $upload = move_uploaded_file(

        $tmpName,

        $uploadPath

    );

    if(!$upload){

        $_SESSION['error'] =
            "Upload file gagal.";

        header(
            "Location: contributor_upload.php"
        );

        exit;

    }

    $query = mysqli_query($conn, "

        INSERT INTO materials (

            user_id,
            title,
            description,
            category,
            grade_level,
            file_name,
            file_type,
            file_size,
            file_hash,
            contributor_name,
            contributor_email,
            contributor_institution,
            status,
            fulfilled_request_ids,
            created_at

        ) VALUES (

            '$user_id',
            '$title',
            '$description',
            '$category',
            '$grade_level',
            '$newName',
            '$ext',
            '$size',
            '$file_hash',
            '$name',
            '$email',
            '$institution',
            'pending',
            '$fulfilled_request_ids',
            NOW()

        )

    ");

    if($query){

        $_SESSION['success'] =
            "File berhasil terkirim untuk diverifikasi oleh administrator.";

    }else{

        $_SESSION['error'] =
            "Gagal menyimpan data ke database.";

    }

    header(
        "Location: contributor_upload.php"
    );

    exit;

}

?>

<!DOCTYPE html>
<html>
<head>

    <title>External Contributor MGMP</title>

    <meta charset="UTF-8">

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
            padding:0;

            font-family:Arial;

            background:#f4f6f9;

        }

        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .main-content{ flex:1; min-width:0; padding:30px; }

        .container{

            width:100%;
            max-width:850px;
            margin: 0 auto;

            background:#e8f0fe;
            border: 1px solid #cce0ff;

            border-radius:16px;

            box-shadow: 0 15px 30px rgba(0, 51, 102, 0.15);

        }

        h2{

            margin-top:0;

            text-align:center;

            color:#2c3e50;

            margin-bottom:10px;

        }

        .subtitle{

            text-align:center;

            color:#666;

            margin-bottom:30px;

            line-height:1.7;

        }

        .success{

            background:#d4edda;

            color:#155724;

            padding:14px;

            border-radius:8px;

            margin-bottom:20px;

        }

        .error{

            background:#f8d7da;

            color:#721c24;

            padding:14px;

            border-radius:8px;

            margin-bottom:20px;

        }

        label{

            display:block;

            margin-bottom:8px;

            color:#2c3e50;

            font-weight:bold;

            font-size:14px;

        }

        input,
        textarea,
        select{

            width:100%;

            padding:14px;

            margin-bottom:18px;

            border:1px solid #ccc;

            border-radius:8px;

            font-size:14px;

            background: #ffffff;

        }

        textarea{

            resize:vertical;

            min-height:100px;

        }

        .custom-file{

            width:100%;
            margin-bottom:18px;

        }

        .custom-file input[type=file]{

            display:none;

        }

        .custom-file label{

            width:100%;
            padding:14px;
            background:#ffffff;
            border:1px solid #ccc;
            border-radius:8px;
            cursor:pointer;
            text-align:center;
            font-weight:bold;
            color:#2c3e50;
            transition:0.3s;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;

        }

        .custom-file label:hover{

            background:#dfe6e9;

        }

        button{

            width:100%;

            padding:15px;

            border:none;

            border-radius:8px;

            background:#3498db;

            color:white;

            font-size:16px;

            cursor:pointer;

        }

        button:hover{

            background:#2980b9;

        }

        .menu-disabled{

            opacity:0.5;
            cursor:not-allowed;
            pointer-events:none;

        }

        .approved-message{

            background:#d4edda;
            color:#155724;
            padding:16px 45px 16px 20px;
            border-radius:12px;
            margin:0 auto 20px;
            max-width:550px;
            border-left:5px solid #28a745;
            line-height:1.6;
            font-size:14.5px;
            box-shadow:0 2px 8px rgba(0,0,0,0.05);
            position: relative;

        }

        .rejected-message{

            background:#f8d7da;
            color:#721c24;
            padding:16px 45px 16px 20px;
            border-radius:12px;
            margin:0 auto 20px;
            max-width:550px;
            border-left:5px solid #dc3545;
            line-height:1.6;
            font-size:14.5px;
            box-shadow:0 2px 8px rgba(0,0,0,0.05);
            position: relative;

        }

        .pending-message{

            background:#fff3cd;
            color:#856404;
            padding:16px 45px 16px 20px;
            border-radius:12px;
            margin:0 auto 20px;
            max-width:550px;
            border-left:5px solid #ffc107;
            line-height:1.6;
            font-size:14.5px;
            box-shadow:0 2px 8px rgba(0,0,0,0.05);
            position: relative;

        }

        /* Tombol silang khusus untuk pesan pemberitahuan */
        .close-btn-msg {
            position: absolute !important;
            top: 8px !important;
            right: 8px !important;
            width: 30px !important;
            height: 30px !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            font-size: 26px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            opacity: 0.5;
            transition: 0.3s;
            line-height: 1 !important;
        }
        .close-btn-msg:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* ======================
           HERO HEADER
        ====================== */

        .hero{

            background:linear-gradient(135deg, #8e44ad, #9b59b6);
            color:white;
            padding:20px 30px;
            border-radius:16px;
            margin-bottom:20px;

        }

        .hero-top{

            display:flex;
            justify-content:space-between;
            gap:25px;
            align-items:center;

        }

        .hero-text{ flex:1; min-width:0; }

        .hero h1{
            margin-top:0;
            font-size:28px;
            margin-bottom:10px;
        }

        .hero p{
            font-size:15px;
            line-height:1.6;
            margin:0 0 10px 0;
        }

        .badge{
            display:inline-block;
            padding:10px 18px;
            background:#8e44ad;
            border-radius:30px;
            margin-top:5px;
            font-weight:bold;
            border: 1px solid rgba(255,255,255,0.5);
        }

        .profile-panel{
            width:200px;
            background:rgba(255,255,255,0.14);
            border:1px solid rgba(255,255,255,0.25);
            border-radius:16px;
            padding:15px;
            text-align:center;
        }

        .profile-photo,
        .profile-initial{
            width:100%;
            height:200px;
            border-radius:12px;
            margin:0 auto 10px;
            border:none;
        }

        .profile-photo{ display:block; object-fit:cover; object-position:top; background:white; }
        .profile-initial{ display:flex; align-items:center; justify-content:center; background:#2c3e50; color:white; font-size:48px; font-weight:bold; }

        .photo-button{
            display:block;
            width:100%;
            border:none;
            border-radius:8px;
            padding:8px;
            font-size:12px;
            background:white;
            color:#2c3e50;
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

        .upload-alert.success{ background:rgba(39,174,96,0.95); }
        .upload-alert.error{ background:rgba(231,76,60,0.95); }

        /* ======================
           REQUEST SCROLL CAROUSEL
        ====================== */
        .kebutuhan-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 15px 30px rgba(0, 51, 102, 0.08);
            margin-bottom: 25px;
        }
        .kebutuhan-card h2 { text-align: left; margin-bottom: 10px; }
        .kebutuhan-card .subtitle { text-align: left; margin-bottom: 20px; }
        
        .request-scroll-container {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            gap: 20px;
            padding-bottom: 10px;
            scroll-snap-type: x mandatory;
        }
        .request-scroll-container::-webkit-scrollbar { height: 8px; }
        .request-scroll-container::-webkit-scrollbar-thumb { background: #bdc3c7; border-radius: 4px; }
        .request-card {
            flex: 0 0 calc(50% - 10px);
            background: white;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-height: 250px;
        }
        .request-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .carousel-wrapper { display: flex; align-items: center; position: relative; width: 100%; }
        .carousel-btn { background: #2c3e50; color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; z-index: 10; font-size: 18px; transition: 0.3s; }
        .carousel-btn:hover { background: #3498db; transform: scale(1.1); }
        .carousel-btn.prev { margin-right: 15px; }
        .carousel-btn.next { margin-left: 15px; }

        /* ======================
           TWO COLUMN LAYOUT
        ====================== */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* ======================
           ACCORDION FORM
        ====================== */
        .form-accordion-header {
            background: #3498db;
            color: white;
            padding: 20px 35px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        .form-accordion-header:hover { background: #2980b9; }
        .form-accordion-header h2 { margin: 0; color: white; font-size: 20px; text-align: left; }
        .form-accordion-header .icon { font-size: 16px; transition: transform 0.3s ease; }
        .form-accordion-header.active .icon { transform: rotate(-180deg); }
        .form-accordion-body { padding: 25px 35px; display: none; }

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
            .wrapper{ flex-direction:column; }
            .mobile-nav { display: flex; }
            .sidebar{ position:static; width:100%; height:auto; display: none; }
            .sidebar.active { display: block; }
            .main-content{ padding:20px; }
            .container{ width:92%; }
            .hero-top{ flex-direction:column; align-items:center; }
            .hero-text { margin-bottom: 20px; text-align: center; }
            .hero-text .badge { margin: 10px auto 0; }
            .profile-panel{ width:200px; }
            .sidebar .logo { display: none; }
            .request-card { flex: 0 0 calc(50% - 10px); }
        }

        @media(max-width:1100px){
            .request-card { flex: 0 0 calc(50% - 10px); }
        }


        @media(max-width: 768px) {
            .request-card { flex: 0 0 100%; }
            .carousel-btn { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .hero { padding: 20px; }
            .hero-text h1 { font-size: 24px; }
            .hero-text p { font-size: 14px; text-align: justify; }
        }

    </style>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="wrapper">
    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <div class="logo">
            External Contributor
        </div>
        <div class="menu">
            <a href="#" class="menu-disabled">Dashboard</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="contributor_upload.php">Upload Materi</a>
            <a href="#" class="menu-disabled">Analytics</a>
            <a href="contributor_upload.php?logout=true">Logout</a>
        </div>
    </div>

<div class="main-content">

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
                <p>Selamat datang, <strong><?= htmlspecialchars($nama_user); ?></strong></p>
                <p>Terima kasih telah bergabung sebagai Kontributor Eksternal. Anda dapat membagikan materi dan perangkat pembelajaran yang akan ditinjau oleh Admin sebelum dipublikasikan ke platform.</p>
            
            <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px; position: relative; z-index: 50;">
                <div class="badge" style="margin-top: 0;">External Contributor</div>
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

    <!-- PENGUMUMAN ADMIN -->
    <div class="kebutuhan-card" style="border-left: 5px solid #e67e22; margin-bottom: 25px;">
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

    <?php if($has_approved){ ?>
    <div class="approved-message">
        <button onclick="this.parentElement.style.display='none'" class="close-btn-msg" style="color:#155724;" title="Tutup Sementara">&times;</button>
        <strong>Pemberitahuan (<?= $counts['approved']; ?> Materi Disetujui):</strong> Materi Anda Sudah Disetujui Oleh Administrator Silakan Dilihat Pada Data Materi (SI-LIAK).
        
        <form method="POST" style="margin-top: 12px; margin-bottom: 0;">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <button type="submit" name="mark_approved_read" style="background:#28a745; color:white; padding:6px 15px; border-radius:6px; font-size:12px; font-weight:bold; border:none; cursor:pointer; width:auto; transition:0.3s;" onmouseover="this.style.background='#218838'" onmouseout="this.style.background='#28a745'">Mengerti & Sembunyikan Permanen</button>
        </form>
    </div>
    <?php } ?>

    <?php if($has_rejected){ ?>
    <div class="rejected-message">
        <button onclick="this.parentElement.style.display='none'" class="close-btn-msg" style="color:#721c24;" title="Tutup Pemberitahuan">&times;</button>
        <div style="text-align: center; margin-bottom: 10px;">
            <strong>Pemberitahuan (<?= $counts['rejected']; ?> Materi Ditolak):</strong><br>
            Materi Anda Ditolak Oleh Administrator.
        </div>
        <ul style="margin: 10px 0; padding-left: 20px;">
        <?php while($rej = mysqli_fetch_assoc($rejected_details)){ ?>
            <li style="margin-bottom: 5px;">
                <strong><?= htmlspecialchars($rej['title']); ?></strong> 
                <a href="javascript:void(0);" onclick="confirmDeleteRejected('?hapus_rejected=<?= $rej['id']; ?>&csrf_token=<?= $csrf_token; ?>')" style="background:#dc3545; color:white; padding:3px 8px; border-radius:4px; text-decoration:none; font-size:11px; font-weight:bold; margin-left:5px; transition:0.3s;" onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">Hapus Riwayat</a><br>
                Alasan: <?= htmlspecialchars(!empty($rej['reject_reason']) ? $rej['reject_reason'] : 'Tidak ada alasan spesifik.'); ?>
            </li>
        <?php } ?>
        </ul>
        Silakan periksa kembali relevansi materi Anda sebelum mengunggah ulang.
    </div>
    <?php } ?>

    <?php if($has_pending){ ?>
    <div class="pending-message">
        <button onclick="this.parentElement.style.display='none'" class="close-btn-msg" style="color:#856404;" title="Tutup Pemberitahuan">&times;</button>
        <strong>Pemberitahuan (<?= $counts['pending']; ?> Materi Pending):</strong> Data Materi Yang Anda Upload Sedang Menunggu Administrator Untuk Ditinjau Dan Disetujui Diupload Pada Data Materi (SI-LIAK)
    </div>
    <?php } ?>

    <!-- TOPIK MATERI PALING DIPERLUKAN -->
    <?php if(!empty($materi_diperlukan_list)){ ?>
    <div class="kebutuhan-card">
        <h2 style="margin-top:0; color:#2c3e50; font-size:22px;">Topik Materi Paling Diperlukan</h2>
        <p style="color:#7f8c8d; font-size:14px; margin-top:0; margin-bottom:15px;">Daftar topik materi yang saat ini paling banyak dibutuhkan oleh guru-guru di platform MGMP. Anda dapat membantu dengan mengunggah materi yang relevan.</p>
        <div class="carousel-wrapper" style="position:relative;">
            <?php if(count($materi_diperlukan_list) > 1){ ?>
                <button class="carousel-btn prev" onclick="scrollTopikDiperlukan(-1)">&#10094;</button>
            <?php } ?>
            <div class="request-scroll-container" id="requestCarouselTopik">
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
                    <div style="margin-top: auto; padding-top: 10px; display:flex; flex-direction:column; gap:8px;">
                        <span style="display:inline-block; background:#eafaf1; color:#27ae60; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:bold; border:1px solid #2ecc71; text-align:center;">Dibutuhkan oleh <?= $item['jumlah']; ?> guru</span>
                        <div style="font-size:11px; color:#7f8c8d; line-height:1.4;">
                            <strong>Pemohon:</strong> <?= htmlspecialchars($item['requesters']); ?>
                        </div>
                        <a href="javascript:void(0)" onclick="bantuUpload('<?= htmlspecialchars(addslashes($item['judul_saja'])); ?>', '<?= htmlspecialchars(addslashes($item['jenis'])); ?>', '<?= htmlspecialchars(addslashes($item['kelas'])); ?>', '<?= htmlspecialchars(addslashes($item['ids'])); ?>')" style="display:inline-block; text-align:center; background:#3498db; color:white; padding:8px 12px; border-radius:8px; font-size:12px; text-decoration:none; font-weight:bold; transition: 0.3s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Bantu Upload</a>
                    </div>
                </div>
            <?php } ?>
            </div>
            <?php if(count($materi_diperlukan_list) > 1){ ?>
                <button class="carousel-btn next" onclick="scrollTopikDiperlukan(1)">&#10095;</button>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

<div class="container" id="form-upload-card" style="overflow:hidden;">

    <div class="form-accordion-header <?= ($message != '') ? 'active' : '' ?>" id="form-upload-header" onclick="toggleFormAccordion()">
        <h2>Upload Materi Contributor</h2>
        <span class="icon">▼</span>
    </div>

    <div class="form-accordion-body" id="form-upload-body" style="<?= ($message != '') ? 'display:block;' : '' ?>">

    <div class="subtitle">

       File Yang Dikirim Kontributor External
       Akan Diseleksi Oleh Administrator Sebelum Diunggah Di Data Materi Dan Kontributor Eksternal Dapat Upload
        Dan Download Materi MGMP.

    </div>

    <?php if($message != ""){ ?>

        <div class="<?= $success ? 'success' : 'error'; ?>">

            <?= $message; ?>

        </div>

    <?php } ?>

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
        <input type="hidden" name="bantu_kategori" id="bantu_kategori" value="">
        <input type="hidden" name="bantu_kelas" id="bantu_kelas" value="">
        <input type="hidden" name="bantu_request_ids" id="bantu_request_ids" value="">

        <div id="locked_request_info" style="display:none; margin-bottom: 18px; padding: 15px; background: #eafaf1; border: 1px solid #2ecc71; border-radius: 8px;">
            <label style="color: #27ae60; margin-bottom: 5px;">Merespons Request:</label>
            <div style="font-size: 14px; color: #2c3e50; font-weight: bold;" id="locked_kategori_text"></div>
            <div style="font-size: 13px; color: #7f8c8d; margin-top: 3px;" id="locked_kelas_text"></div>
            <button type="button" onclick="batalBantu()" style="background: none; border: none; color: #e74c3c; font-size: 12px; cursor: pointer; padding: 0; margin-top: 8px; text-decoration: underline; width: auto; font-weight:bold;">Batal Merespons Request</button>
        </div>

        <div class="form-grid">
            <div>
                <label>Nama Lengkap</label>
                <input type="text" name="name" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Sekolah / Instansi</label>
                <input type="text" name="institution" required>
            </div>
            <div id="kategori_container">
                <label>Pilih Kategori Materi</label>
                <select name="kategori_dropdown" id="kategori_dropdown" required>
                    <option value="">-- Pilih Kategori --</option>
                    <option value="Materi Pembelajaran">Materi Pembelajaran</option>
                    <option value="Soal Latihan">Soal Latihan</option>
                    <option value="Perangkat Pembelajaran">Perangkat Pembelajaran</option>
                    <option value="Refleksi">Refleksi</option>
                </select>
            </div>
            <div id="kelas_container">
                <label>Pilih Kelas</label>
                <select name="kelas_dropdown" id="kelas_dropdown" required>
                    <option value="">-- Pilih Kelas --</option>
                    <option value="Kelas 10">Kelas 10</option>
                    <option value="Kelas 11">Kelas 11</option>
                    <option value="Kelas 12">Kelas 12</option>
                    <option value="Umum">Umum</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
            <label>Judul Materi</label>
                <input type="text" name="title" id="input_title" required>
            </div>
        </div>

        <label>Deskripsi Materi</label>

        <textarea
            name="description"
            placeholder="Tambahkan penjelasan materi..."
        ></textarea>

        <label>Pilih File Materi</label>

        <div class="custom-file">

            <input
                type="file"
                name="file"
                id="file"
                required
            >

            <label
                for="file"
                id="file-label"
            >

                Pilih File Materi

            </label>

        </div>

        <button type="submit" name="upload">

            Upload Materi

        </button>

    </form>

</div>

</div>

<!-- Modal Konfirmasi Hapus Riwayat Penolakan -->
<div id="deleteRejectedModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
    <div style="background: white; padding: 25px; width: 90%; max-width: 350px; border-radius: 12px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">🗑️</div>
        <h3 style="margin-top: 0; color: #e74c3c; font-size: 20px;">Hapus Riwayat?</h3>
        <p style="color: #555; margin-bottom: 25px; font-size: 14px;">Yakin ingin menghapus riwayat penolakan materi ini?</p>
        <div style="display: flex; justify-content: center; gap: 15px;">
            <button type="button" onclick="closeDeleteRejectedModal()" style="padding: 10px 20px; border: none; border-radius: 8px; background: #95a5a6; color: white; font-weight: bold; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">Batal</button>
            <a id="confirmDeleteRejectedBtn" href="#" style="padding: 10px 20px; border-radius: 8px; background: #e74c3c; color: white; font-weight: bold; text-decoration: none; transition: 0.3s; display: inline-block; box-sizing: border-box;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Hapus</a>
        </div>
    </div>
</div>

<script>

document
.getElementById('file')
.addEventListener('change', function(){
    let fileName = 'Pilih File Materi';

    if (this.files[0]) {
        // Cek ukuran file (Maksimal 2 MB)
        if (this.files[0].size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Terlalu Besar!',
                text: 'Ukuran maksimal adalah 2 MB. File Anda berukuran ' + (this.files[0].size / (1024 * 1024)).toFixed(2) + ' MB.'
            });
            this.value = ''; // Hapus file yang dipilih
        } else {
            fileName = this.files[0].name;
        }
    }

    document
    .getElementById('file-label')
    .innerHTML = fileName;

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

function scrollTopikDiperlukan(direction) {
    const container = document.getElementById('requestCarouselTopik');
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

function bantuUpload(judul, jenis, kelas, ids) {
    // Buka accordion jika tertutup
    const header = document.getElementById('form-upload-header');
    const body = document.getElementById('form-upload-body');
    if (!header.classList.contains('active')) {
        header.classList.add('active');
        body.style.display = 'block';
    }

    // Isi judul materi dengan detail request agar sesuai
    const titleInput = document.getElementById('input_title');
    titleInput.value = judul;
    titleInput.readOnly = false;
    titleInput.style.backgroundColor = '#fff';
    titleInput.style.cursor = 'text';
    
    document.getElementById('bantu_kategori').value = jenis;
    document.getElementById('bantu_kelas').value = kelas;
    document.getElementById('bantu_request_ids').value = ids;
    document.getElementById('locked_request_info').style.display = 'block';
    document.getElementById('locked_kategori_text').innerText = 'Kategori: ' + jenis;
    document.getElementById('locked_kelas_text').innerText = 'Target Kelas: ' + kelas;
    
    // Sembunyikan dan nonaktifkan dropdown kelas karena sudah dikunci dari request
    const kelasDropdown = document.getElementById('kelas_dropdown');
    kelasDropdown.value = '';
    kelasDropdown.removeAttribute('required');
    document.getElementById('kelas_container').style.display = 'none';
    
    const kategoriDropdown = document.getElementById('kategori_dropdown');
    kategoriDropdown.value = '';
    kategoriDropdown.removeAttribute('required');
    document.getElementById('kategori_container').style.display = 'none';
    
    // Scroll layar ke bagian form upload
    document.getElementById('form-upload-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Memberikan fokus dan efek highlight biru pada input judul agar lebih jelas
    titleInput.focus();
    titleInput.style.boxShadow = '0 0 0 3px rgba(52, 152, 219, 0.3)';
    titleInput.style.borderColor = '#3498db';
    setTimeout(() => {
        titleInput.style.boxShadow = '';
        titleInput.style.borderColor = '';
    }, 2000);
}

function batalBantu() {
    document.getElementById('bantu_kategori').value = '';
    document.getElementById('bantu_kelas').value = '';
    document.getElementById('bantu_request_ids').value = '';
    document.getElementById('locked_request_info').style.display = 'none';
    
    const titleInput = document.getElementById('input_title');
    titleInput.value = '';
    titleInput.readOnly = false;
    titleInput.style.backgroundColor = '#fff';
    titleInput.style.cursor = 'text';

    const kelasDropdown = document.getElementById('kelas_dropdown');
    kelasDropdown.setAttribute('required', 'required');
    document.getElementById('kelas_container').style.display = 'block';
    
    const kategoriDropdown = document.getElementById('kategori_dropdown');
    kategoriDropdown.setAttribute('required', 'required');
    document.getElementById('kategori_container').style.display = 'block';
}

function toggleFormAccordion() {
    const header = document.getElementById('form-upload-header');
    const body = document.getElementById('form-upload-body');
    header.classList.toggle('active');
    if (header.classList.contains('active')) {
        body.style.display = 'block';
    } else {
        body.style.display = 'none';
    }
}

function confirmDeleteRejected(url) {
    document.getElementById('confirmDeleteRejectedBtn').href = url;
    document.getElementById('deleteRejectedModal').style.display = 'flex';
}

function closeDeleteRejectedModal() {
    document.getElementById('deleteRejectedModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    let m = document.getElementById('deleteRejectedModal');
    if (e.target == m) { closeDeleteRejectedModal(); }
});

// Mobile Hamburger Toggle
const hamburger = document.getElementById('hamburger-toggle');
const sidebar = document.getElementById('sidebar-menu');
if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

</script>

</div>
</div>
</body>
</html>
