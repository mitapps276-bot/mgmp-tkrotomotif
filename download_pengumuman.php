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
// CEK ID
// =======================

if(!isset($_GET['id'])){
    echo "<script>alert('ID pengumuman tidak ditemukan'); history.back();</script>";
    exit;
}

$id = (int)$_GET['id'];

$query = mysqli_query($conn, "SELECT file_path FROM announcements WHERE id='$id'");
$data = mysqli_fetch_assoc($query);

if(!$data || empty($data['file_path'])){
    echo "<script>alert('Lampiran tidak ditemukan'); history.back();</script>";
    exit;
}

$file = $data['file_path'];
$real_file = realpath($file);
$allowed_dir = realpath('assets/uploads');

if($real_file && strpos($real_file, $allowed_dir) === 0 && file_exists($real_file)){
    $file = $real_file;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime_type = 'application/octet-stream';
    switch($ext) {
        case 'pdf': $mime_type = 'application/pdf'; break;
        case 'jpg':
        case 'jpeg': $mime_type = 'image/jpeg'; break;
        case 'png': $mime_type = 'image/png'; break;
        case 'webp': $mime_type = 'image/webp'; break;
        case 'doc': $mime_type = 'application/msword'; break;
        case 'docx': $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
        case 'xls': $mime_type = 'application/vnd.ms-excel'; break;
        case 'xlsx': $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
        case 'ppt': $mime_type = 'application/vnd.ms-powerpoint'; break;
        case 'pptx': $mime_type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'; break;
    }

    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    ob_clean(); // Mencegah file korup jika ada spasi kosong di database.php
    flush();
    readfile($file);
    exit;
}else{
    echo "<script>alert('File fisik tidak ditemukan di server!'); history.back();</script>";
}
?>