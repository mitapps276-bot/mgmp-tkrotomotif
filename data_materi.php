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
// IDENTIFIKASI ROLE
// =======================

$role_id = $_SESSION['role_id'];
$user_id = (int) $_SESSION['user_id'];

$is_admin      = ($role_id == 1);
$is_guru       = ($role_id == 2);
$is_visitor    = ($role_id == 3);
$is_external   = ($role_id == 4);

// =======================
// DASHBOARD
// =======================

if($is_admin){

    $dashboard = "dashboard_admin.php";

}else{

    $dashboard = "dashboard.php";

}

// =======================
// FLASH MESSAGE
// =======================
$success_message = "";
if(isset($_SESSION['success'])){
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
// =======================
// HAPUS MATERI
// =======================

if(isset($_GET['hapus'])){

    // Validasi CSRF Token
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $id = intval($_GET['hapus']);

    // =======================
    // AMBIL DATA MATERI
    // =======================

    $cek = mysqli_query($conn, "

        SELECT *

        FROM materials

        WHERE id = '$id'

    ");

    if(mysqli_num_rows($cek) > 0){

        $materi = mysqli_fetch_assoc($cek);

        // =======================
        // VALIDASI HAK HAPUS
        // =======================

        $boleh_hapus = false;

        // ADMIN
        if($is_admin){

            $boleh_hapus = true;

        }

        // GURU HANYA FILE SENDIRI
        if($is_guru && $materi['user_id'] == $user_id){
            $boleh_hapus = true;
        }

        // EXTERNAL HANYA FILE SENDIRI (Termasuk mendeteksi materi lama tanpa ID)
        if($is_external){
            if($materi['user_id'] == $user_id) $boleh_hapus = true;
            elseif(!empty($_SESSION['email']) && $materi['contributor_email'] == $_SESSION['email']) $boleh_hapus = true;
            elseif(!empty($_SESSION['name']) && $materi['contributor_name'] == $_SESSION['name']) $boleh_hapus = true;
        }

        if($boleh_hapus){

            // =======================
            // HAPUS FILE FISIK
            // =======================

            $path =

                "assets/uploads/"
                .
                $materi['file_name'];

            if(file_exists($path)){

                unlink($path);

            }

            // =======================
            // HAPUS DATA DOWNLOAD
            // =======================

            mysqli_query($conn, "

                DELETE FROM downloads

                WHERE material_id = '$id'

            ");

            // =======================
            // HAPUS DATA MATERIAL
            // =======================

            mysqli_query($conn, "

                DELETE FROM materials

                WHERE id = '$id'

            ");

            $_SESSION['success'] = "Materi berhasil dihapus.";
            header("Location: data_materi.php");

            exit;

        }

    }

}

// =======================
// SEARCH HANDLER
// =======================
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$search_query_sql = "";
if(!empty($search_query)) {
    $words = explode(" ", $search_query);
    $conditions = [];
    foreach($words as $w) {
        $w = trim(preg_replace('/[^a-zA-Z0-9]/', '', $w));
        if(strlen($w) > 2 && strtolower($w) != 'materi' && strtolower($w) != 'tolong' && strtolower($w) != 'mohon') {
            $conditions[] = "(materials.title LIKE '%$w%' OR materials.description LIKE '%$w%')";
        }
    }
    
    if(count($conditions) > 0) {
        $search_query_sql = " AND (" . implode(" OR ", $conditions) . ") ";
    } else {
        $search_query_sql = " AND (materials.title LIKE '%$search_query%' OR materials.description LIKE '%$search_query%') ";
    }
}

// =======================
// AMBIL FOLDER
// =======================

$folder_query = mysqli_query($conn, "

    SELECT *
    FROM folders
    ORDER BY id ASC

");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Data Materi MGMP</title>

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
            font-family:Arial;
            background:#f4f6f9;
            margin:0;
        }

        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .sidebar .menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 14px 20px;
            background: transparent;
            border-radius: 12px;
            border: 1px solid transparent;
            transition: all 0.3s ease;
            font-size: 15px;
            font-weight: bold;
        }
        .sidebar .menu a:hover, .sidebar .menu a[style*="background"] {
            background: #3498db !important;
            transform: translateX(5px);
            border-color: #2980b9;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .main-content{ flex:1; min-width:0; padding:30px; }

        .menu-disabled{

            opacity:0.5;
            cursor:not-allowed;
            pointer-events:none;

        }

        .top-bar{

            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;

        }

        .top-bar h2{

            margin:0;
            color:#2c3e50;

        }

        .btn-contributor{

            background:#8e44ad;
            color:white;
            text-decoration:none;
            padding:12px 20px;
            border-radius:10px;
            font-weight:bold;

        }

        .folder-card{

            background:white;
            border-radius:15px;
            margin-bottom:30px;
            overflow:hidden;
            box-shadow:0 0 10px rgba(0,0,0,0.05);

        }

        .accordion-header{

            background:#2c3e50;
            color:white;
            padding:20px;
            font-size:22px;
            font-weight:bold;
            cursor:pointer;
            display:flex;
            justify-content:space-between;
            align-items:center;
            user-select:none;
            transition:0.3s;

        }

        .accordion-header:hover{
            background:#34495e;
        }

        .accordion-header::after{
            content:'▼';
            font-size:16px;
            transition:transform 0.3s ease;
        }

        .accordion-header.active::after{
            transform:rotate(-180deg);
        }

        .folder-body{

            padding:20px;
            display:none; /* Sembunyikan konten secara default */

        }

        table{

            width:100%;
            border-collapse:collapse;
            table-layout:fixed;

        }

        table th{

            background:#ecf0f1;
            padding:15px;
            text-align:left;

        }

        table td{

            padding:15px;
            border-bottom:1px solid #eee;
            vertical-align:middle;

        }

        .col-no{
            width:70px;
        }

        .col-materi{
            width:30%;
        }

        .col-upload{
            width:25%;
        }

        .col-file{
            width:25%;
            word-break:break-word;
        }

        .col-aksi{
            width:180px;
            text-align:center;
        }

        .judul{

            font-size:17px;
            font-weight:bold;
            color:#2c3e50;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;

        }

        .deskripsi{

            color:#555;
            margin-top:8px;
            line-height:1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;

        }

        .tanggal{

            margin-top:10px;
            font-size:13px;
            color:#7f8c8d;
            font-style:italic;

        }

        .download{

            background:#27ae60;
            color:white;
            padding:10px 15px;
            border-radius:8px;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:130px;
            height:45px;
            font-weight:bold;
            text-align:center;

        }

        .download:hover{

            background:#1e8449;

        }

        .hapus{

            background:#e74c3c;
            color:white;
            padding:10px 15px;
            border-radius:8px;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:130px;
            height:45px;
            font-weight:bold;
            text-align:center;
            margin-top:10px;

        }

        .hapus:hover{

            background:#c0392b;

        }

        .aksi-wrapper{

            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;

        }

        .kosong{

            text-align:center;
            padding:40px;
            border:2px dashed #ddd;
            border-radius:10px;
            color:#777;

        }

        .badge-external{

            display:inline-block;
            background:#8e44ad;
            color:white;
            padding:6px 12px;
            border-radius:20px;
            font-size:12px;
            margin-top:8px;

        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-content { background-color: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transform: translateY(-20px); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .modal.show .modal-content { transform: translateY(0); }
        .modal-content h3 { margin-top: 0; color: #e74c3c; font-size: 22px; }
        .modal-actions { display: flex; justify-content: center; gap: 15px; margin-top: 25px; }
        .btn-cancel { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; background: #95a5a6; color: white; font-weight: bold; transition:0.3s; }
        .btn-confirm { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; background: #e74c3c; color: white; font-weight: bold; text-decoration: none; transition:0.3s; display: inline-block; }
        .btn-cancel:hover { background: #7f8c8d; }
        .btn-confirm:hover { background: #c0392b; }

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

        @media(max-width:900px){

            body{
                padding:0; /* Hapus padding body agar nav full width */
            }

            .main-content {
                padding: 15px; /* Pindahkan padding ke konten utama saja */
            }

            table{
                font-size:13px;
                min-width: 800px; /* Paksa tabel tetap lebar agar tidak penyok dan bisa di-scroll horisontal */
            }

            .accordion-header{
                font-size:16px;
                padding: 15px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .top-bar{
                flex-direction:column;
                gap:15px;
                align-items:flex-start;
            }

            .wrapper{ flex-direction:column; }
            .mobile-nav { display: flex; }
            .sidebar{ width:100%; height:auto; position:static; display:none; }
            .sidebar.active { display:block; }
        }

    </style>

</head>

<body>

<!-- POPUP SUCCESS -->
<?php if(!empty($success_message)){ ?>
<div class="modal" id="successPopup" style="display: flex;">
    <div class="modal-content">
        <div style="font-size: 50px; margin-bottom: 15px; line-height: 1; color: #27ae60;">✓</div>
        <h3 style="color: #27ae60;">Berhasil!</h3>
        <p style="color: #555; font-size: 14px; margin-bottom: 0;"><?= htmlspecialchars($success_message); ?></p>
        <div class="modal-actions">
            <button class="btn-confirm" style="background:#27ae60;" onclick="closeSuccessPopup()">Tutup</button>
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
</script>
<?php } ?>

<div class="wrapper">

    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <div class="logo">
            <?= ($is_admin) ? 'ADMIN PANEL' : 'MGMP PLATFORM'; ?>
        </div>
        <div class="menu">
            <?php if($is_admin){ ?>
                <a href="dashboard_admin.php">Dashboard</a>
                <a href="monitoring_guru.php">Monitoring Guru</a>
                <a href="data_materi.php">Data Materi</a>
                <a href="upload_materi.php">Upload Materi</a>
                <a href="review_materials.php">Review Contributor</a>
                <a href="kelola_request.php">Request Materi</a>
                <a href="analytics.php">Analytics</a>
                <a href="kelola_user.php">Kelola Akun</a>
            <?php } elseif($is_external){ ?>
                <a href="#" class="menu-disabled">Dashboard</a>
                <a href="data_materi.php">Data Materi</a>
                <a href="contributor_upload.php">Upload Materi</a>
                <a href="#" class="menu-disabled">Analytics</a>
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
<div class="top-bar">

    <h2>

        Data Materi MGMP

    </h2>

    <?php if($is_external){ ?>

    <a
        href="contributor_upload.php"
        class="btn-contributor"
    >

        Upload Materi Contributor

    </a>

    <?php } ?>

</div>

<?php if(!empty($search_query)){ ?>
    <div style="background: #eef2f5; padding: 15px 20px; border-radius: 8px; border-left: 5px solid #3498db; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <div>
            <h3 style="margin: 0; font-size: 18px; color: #2c3e50;">Pencarian Materi</h3>
            <p style="margin: 5px 0 0 0; color: #555; font-size: 14px;">Menampilkan hasil untuk: <strong>"<?= htmlspecialchars($search_query); ?>"</strong></p>
        </div>
        <a href="data_materi.php" style="padding: 10px 20px; background: #e74c3c; color: white; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; display: flex; align-items: center; gap: 5px;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">Tutup ✖</a>
    </div>
<?php } else { ?>
    <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px; margin-top:20px;">
        <input type="text" name="search" placeholder="Cari materi berdasarkan judul atau deskripsi..." value="" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #ccc; outline: none; font-size: 14px;">
        <button type="submit" style="padding: 12px 25px; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.3s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Cari</button>
    </form>
<?php } ?>

<?php

while($folder = mysqli_fetch_assoc($folder_query)){

    $folder_id = $folder['id'];

    $materi_query = mysqli_query($conn, "

        SELECT

            materials.*,
            users.full_name,
            users.school_name

        FROM materials

        LEFT JOIN users
        ON materials.user_id = users.id

        WHERE materials.folder_id = '$folder_id'

        AND materials.status = 'approved'

        AND (users.role_id != 4 OR users.role_id IS NULL)
        
        $search_query_sql

        ORDER BY materials.created_at DESC

    ");

    if(!empty($search_query) && mysqli_num_rows($materi_query) == 0) {
        continue;
    }

?>

<div class="folder-card">

    <div class="accordion-header">

        <span>📁 <?= htmlspecialchars($folder['folder_name']); ?></span>

    </div>

    <div class="folder-body">

        <?php if(mysqli_num_rows($materi_query) > 0){ ?>

        <div style="overflow-x:auto;">
        <table id="table_<?= $folder_id; ?>">

            <tr>

                <th class="col-no">No</th>
                <th class="col-materi">Materi</th>
                <th class="col-upload">Diupload Oleh</th>
                <th class="col-file">Nama File</th>
                <th class="col-aksi">Aksi</th>

            </tr>

            <?php

            $no = 1;

            while($row = mysqli_fetch_assoc($materi_query)){

            ?>

            <tr class="row_<?= $folder_id; ?>">

                <td class="col-no">

                    <?= $no++; ?>

                </td>

                <td class="col-materi">

                    <div class="judul">

                        <?= htmlspecialchars($row['title']); ?>

                    </div>

                    <div class="deskripsi">

                        <?php
                            $desc = isset($row['description']) ? $row['description'] : '';
                            $grade = isset($row['grade_level']) ? $row['grade_level'] : '';
                            $extra = "* Materi ini berlaku untuk semua kelas";
                            $has_extra = false;

                            if (stripos($desc, $extra) !== false) {
                                $desc = str_ireplace($extra, "", $desc);
                                $has_extra = true;
                            }
                            
                            if (stripos($grade, 'Umum') !== false) {
                                $has_extra = true;
                            }
                            
                            echo nl2br(htmlspecialchars(trim($desc)));
                            
                            if ($has_extra) {
                                echo '<br><br><span style="color:#e67e22; font-style:italic;">' . $extra . '</span>';
                            }
                        ?>

                    </div>

                    <div class="tanggal">

                        Upload :
                        <?= date('d F Y H:i', strtotime($row['created_at'])); ?>

                    </div>

                    <div style="margin-top:10px;">
                        <?php if(!empty($row['category'])): ?>
                        <span style="background:#9b59b6; color:white; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:bold; margin-right:5px;">
                            <?= htmlspecialchars($row['category']); ?>
                        </span>
                        <?php endif; ?>
                        <span style="background:#3498db; color:white; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:bold;">
                            <?= htmlspecialchars($row['grade_level']); ?>
                        </span>
                    </div>

                </td>

                <td class="col-upload">

                    <?= htmlspecialchars($row['full_name']); ?>

                    <br><br>

                    <?= htmlspecialchars($row['school_name']); ?>

                </td>

                <td class="col-file">

                    <?= htmlspecialchars($row['file_name']); ?>

                </td>

                <td class="col-aksi">

                    <div class="aksi-wrapper">
                        <a href="#" class="diskusi-btn" onclick="openCommentModal(<?= $row['id']; ?>, '<?= htmlspecialchars(addslashes($row['file_name'])); ?>'); return false;" style="background:#2ecc71;color:white;padding:10px 15px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;width:130px;height:45px;font-weight:bold;transition:0.3s;margin-bottom:10px;box-sizing:border-box;" onmouseover="this.style.background='#27ae60'" onmouseout="this.style.background='#2ecc71'">💬 Diskusi</a>

                        <?php

                        // =====================================
                        // ADMIN
                        // =====================================

                        if($is_admin){

                        ?>

                            <a
                                href="download.php?id=<?= $row['id']; ?>"
                                target="_blank"
                                class="download"
                            >

                                Download

                            </a>

                            <a
                            href="#"
                                class="hapus"
                            onclick="openDeleteModal('?hapus=<?= $row['id']; ?>&csrf_token=<?= $csrf_token; ?>'); return false;"
                            >

                                Hapus

                            </a>

                        <?php

                        }

                        // =====================================
                        // GURU
                        // =====================================

                        elseif($is_guru){

                            // ==========================
                            // FILE MILIK SENDIRI
                            // ==========================

                            if($row['user_id'] == $user_id){

                        ?>

                                <a
                                    href="download.php?id=<?= $row['id']; ?>"
                                    target="_blank"
                                    class="download"
                                >

                                    Download

                                </a>

                                <a
                                    href="#"
                                    class="hapus"
                                    onclick="openDeleteModal('?hapus=<?= $row['id']; ?>&csrf_token=<?= $csrf_token; ?>'); return false;"
                                >

                                    Hapus

                                </a>

                        <?php

                            }

                            // ==========================
                            // FILE MILIK GURU LAIN
                            // ==========================

                            else{

                        ?>

                                <a
                                    href="download.php?id=<?= $row['id']; ?>"
                                    target="_blank"
                                    class="download"
                                >

                                    Download

                                </a>

                        <?php

                            }

                        }

                        // =====================================
                        // VISITOR / EXTERNAL
                        // =====================================

                        else{

                        ?>

                            <a
                                href="download.php?id=<?= $row['id']; ?>"
                                target="_blank"
                                class="download"
                            >

                                Download

                            </a>

                        <?php } ?>

                    </div>

                </td>

            </tr>

            <?php } ?>

        </table>
        </div>

        <div class="pagination-controls" id="pagination_<?= $folder_id; ?>" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="font-size: 14px; color: #7f8c8d;" id="pageInfo_<?= $folder_id; ?>"></div>
            <div style="display: flex; gap: 10px;">
                <button onclick="changePage('<?= $folder_id; ?>', -1)" id="btnPrev_<?= $folder_id; ?>" style="padding: 8px 15px; background: #2c3e50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s;" onmouseover="if(!this.disabled) this.style.background='#34495e'" onmouseout="if(!this.disabled) this.style.background='#2c3e50'">&laquo; Prev</button>
                <button onclick="changePage('<?= $folder_id; ?>', 1)" id="btnNext_<?= $folder_id; ?>" style="padding: 8px 15px; background: #2c3e50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s;" onmouseover="if(!this.disabled) this.style.background='#34495e'" onmouseout="if(!this.disabled) this.style.background='#2c3e50'">Next &raquo;</button>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                initPagination('<?= $folder_id; ?>');
            });
        </script>

        <?php }else{ ?>

            <div class="kosong">

                <h3>

                    Folder Kosong

                </h3>

                <p>

                    Belum ada materi pada folder ini.

                </p>

            </div>

        <?php } ?>

    </div>

</div>

<?php } ?>

<!-- =====================================
Kolaborator External
===================================== -->

<?php

$external_query = mysqli_query($conn, "

    SELECT materials.*, users.full_name, users.school_name

    FROM materials
    LEFT JOIN users ON materials.user_id = users.id

    WHERE status = 'approved'

    AND (materials.user_id IS NULL OR users.role_id = 4)
    
    $search_query_sql

    ORDER BY created_at DESC

");

$show_external = true;
if(!empty($search_query) && mysqli_num_rows($external_query) == 0) {
    $show_external = false;
}

if($show_external) {

?>

<div class="folder-card">

    <div class="accordion-header">

        <span>🌐 Kolaborator External</span>

    </div>

    <div class="folder-body">

        <?php if(mysqli_num_rows($external_query) > 0){ ?>

        <div style="overflow-x:auto;">
        <table id="table_ext">

            <tr>

                <th class="col-no">No</th>
                <th class="col-materi">Materi</th>
                <th class="col-upload">Nama Kontributor</th>
                <th class="col-upload">Institusi</th>
                <th class="col-file">File</th>
                <th class="col-aksi">Aksi</th>

            </tr>

            <?php

            $no = 1;

            while($external = mysqli_fetch_assoc($external_query)){

            ?>

            <tr class="row_ext">

                <td class="col-no">

                    <?= $no++; ?>

                </td>

                <td class="col-materi">

                    <div class="judul">

                        <?= htmlspecialchars($external['title']); ?>

                    </div>

                    <div class="deskripsi">

                        <?php
                            $desc = isset($external['description']) ? $external['description'] : '';
                            $grade = isset($external['grade_level']) ? $external['grade_level'] : '';
                            $extra = "* Materi ini berlaku untuk semua kelas";
                            $has_extra = false;

                            if (stripos($desc, $extra) !== false) {
                                $desc = str_ireplace($extra, "", $desc);
                                $has_extra = true;
                            }
                            
                            if (stripos($grade, 'Umum') !== false) {
                                $has_extra = true;
                            }
                            
                            echo nl2br(htmlspecialchars(trim($desc)));
                            
                            if ($has_extra) {
                                echo '<br><br><span style="color:#e67e22; font-style:italic;">' . $extra . '</span>';
                            }
                        ?>

                    </div>

                    <div class="tanggal">

                        Upload :
                        <?= date('d F Y H:i', strtotime($external['created_at'])); ?>

                    </div>

                    <div style="margin-top:12px;">
                        <?php if(!empty($external['category'])): ?>
                        <span style="background:#9b59b6; color:white; padding:6px 12px; border-radius:20px; font-size:12px; margin-left: 5px; font-weight:bold;">
                            <?= htmlspecialchars($external['category']); ?>
                        </span>
                        <?php endif; ?>
                        <span style="background:#3498db; color:white; padding:6px 12px; border-radius:20px; font-size:12px; margin-left: 5px; font-weight:bold;">
                            <?= htmlspecialchars($external['grade_level']); ?>
                        </span>
                    </div>

                </td>

                <td class="col-upload">

                    <?= htmlspecialchars($external['contributor_name'] ?: $external['full_name']); ?>

                </td>

                <td class="col-upload">

                    <?= htmlspecialchars($external['contributor_institution'] ?: $external['school_name']); ?>

                </td>

                <td class="col-file">

                    <?= htmlspecialchars($external['file_name']); ?>

                </td>

                <td class="col-aksi">

                    <div class="aksi-wrapper">

                        <a
                            href="download.php?id=<?= $external['id']; ?>"
                            target="_blank"
                            class="download"
                        >

                            Download

                        </a>

                        <?php

                        $show_delete = false;
                        if($is_admin){
                            $show_delete = true;
                        } elseif($is_external){
                            if($external['user_id'] == $user_id) $show_delete = true;
                            elseif(!empty($_SESSION['email']) && $external['contributor_email'] == $_SESSION['email']) $show_delete = true;
                            elseif(!empty($_SESSION['name']) && $external['contributor_name'] == $_SESSION['name']) $show_delete = true;
                        }
                        
                        if($show_delete){

                        ?>

                        <a
                            href="#"
                            class="hapus"
                            onclick="openDeleteModal('?hapus=<?= $external['id']; ?>&csrf_token=<?= $csrf_token; ?>'); return false;"
                        >

                            Hapus

                        </a>

                        <?php } ?>

                    </div>

                </td>

            </tr>

            <?php } ?>

        </table>
        </div>

        <div class="pagination-controls" id="pagination_ext" style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="font-size: 14px; color: #7f8c8d;" id="pageInfo_ext"></div>
            <div style="display: flex; gap: 10px;">
                <button onclick="changePage('ext', -1)" id="btnPrev_ext" style="padding: 8px 15px; background: #2c3e50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s;" onmouseover="if(!this.disabled) this.style.background='#34495e'" onmouseout="if(!this.disabled) this.style.background='#2c3e50'">&laquo; Prev</button>
                <button onclick="changePage('ext', 1)" id="btnNext_ext" style="padding: 8px 15px; background: #2c3e50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s;" onmouseover="if(!this.disabled) this.style.background='#34495e'" onmouseout="if(!this.disabled) this.style.background='#2c3e50'">Next &raquo;</button>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                initPagination('ext');
            });
        </script>

        <?php }else{ ?>

            <div class="kosong">

                <h3>

                    Belum Ada Kolaborator External

                </h3>

                <p>

                    Materi external yang diapprove admin
                    akan muncul di sini.

                </p>

            </div>

        <?php } ?>

    </div>

</div>

<?php } ?>

</div>
</div>

<!-- Modal Custom Delete -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div style="font-size: 50px; margin-bottom: 15px; line-height: 1;">🗑️</div>
        <h3>Hapus Materi?</h3>
        <p style="color: #555; font-size: 14px; margin-bottom: 0;">Yakin ingin menghapus materi ini? File akan dihapus permanen dari sistem.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeDeleteModal()">Batal</button>
            <a href="#" id="confirmDeleteBtn" class="btn-confirm">Ya, Hapus</a>
        </div>
    </div>
</div>

<!-- Modal Hapus Komentar -->
<div id="deleteCommentModal" class="modal" style="z-index: 10000;">
    <div class="modal-content">
        <div style="font-size: 50px; margin-bottom: 15px; line-height: 1;">🗑️</div>
        <h3>Hapus Komentar?</h3>
        <p style="color: #555; font-size: 14px; margin-bottom: 0;">Yakin ingin menghapus komentar ini?</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeDeleteCommentModal()">Batal</button>
            <button id="confirmDeleteCommentBtn" class="btn-confirm" onclick="processDeleteComment()">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
function openDeleteModal(url) {
    const modal = document.getElementById('deleteModal');
    document.getElementById('confirmDeleteBtn').href = url;
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

window.addEventListener('click', function(e) {
    let modal = document.getElementById('deleteModal');
    if (e.target == modal) closeDeleteModal();
    let cModal = document.getElementById('commentModal');
    if (e.target == cModal) closeCommentModal();
    let dModal = document.getElementById('deleteCommentModal');
    if (e.target == dModal) closeDeleteCommentModal();
});

const itemsPerPage = 4;
let currentPage = {};

function initPagination(folderId) {
    currentPage[folderId] = 1;
    showPage(folderId);
}

function changePage(folderId, delta) {
    const rows = document.querySelectorAll('.row_' + folderId);
    const totalPages = Math.ceil(rows.length / itemsPerPage);
    
    currentPage[folderId] += delta;
    if (currentPage[folderId] < 1) currentPage[folderId] = 1;
    if (currentPage[folderId] > totalPages) currentPage[folderId] = totalPages;
    
    showPage(folderId);
}

function showPage(folderId) {
    const rows = document.querySelectorAll('.row_' + folderId);
    const totalItems = rows.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const start = (currentPage[folderId] - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    
    rows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
    
    const info = document.getElementById('pageInfo_' + folderId);
    if(info) {
        let endShow = end > totalItems ? totalItems : end;
        let startShow = totalItems === 0 ? 0 : start + 1;
        info.innerHTML = `Menampilkan <strong>${startShow}-${endShow}</strong> dari <strong>${totalItems}</strong> materi`;
    }
    
    const btnPrev = document.getElementById('btnPrev_' + folderId);
    const btnNext = document.getElementById('btnNext_' + folderId);
    
    if(btnPrev) { btnPrev.disabled = currentPage[folderId] === 1 || totalItems === 0; btnPrev.style.opacity = btnPrev.disabled ? '0.5' : '1'; btnPrev.style.cursor = btnPrev.disabled ? 'not-allowed' : 'pointer'; }
    if(btnNext) { btnNext.disabled = currentPage[folderId] === totalPages || totalPages === 0; btnNext.style.opacity = btnNext.disabled ? '0.5' : '1'; btnNext.style.cursor = btnNext.disabled ? 'not-allowed' : 'pointer'; }
    
    const paginationContainer = document.getElementById('pagination_' + folderId);
    if(paginationContainer) { paginationContainer.style.display = totalItems <= itemsPerPage ? 'none' : 'flex'; }
}

document.addEventListener("DOMContentLoaded", function() {
    var acc = document.getElementsByClassName("accordion-header");
    for (var i = 0; i < acc.length; i++) {
        acc[i].addEventListener("click", function() {
            this.classList.toggle("active");
            var panel = this.nextElementSibling;
            if (panel.style.display === "block") {
                panel.style.display = "none";
            } else {
                panel.style.display = "block";
            }
        });
    }
});
</script>
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
<?php if(!empty($search_query)){ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var headers = document.getElementsByClassName("accordion-header");
    for (var i = 0; i < headers.length; i++) {
        headers[i].classList.add("active");
        var panel = headers[i].nextElementSibling;
        if (panel) {
            panel.style.display = "block";
        }
    }
});
</script>
<?php } ?>
<!-- Modal Komentar -->
<div id="commentModal" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color:#fff;margin:5% auto;padding:20px;border-radius:8px;width:90%;max-width:600px;box-shadow:0 4px 15px rgba(0,0,0,0.2);">
        <span class="close" onclick="closeCommentModal()" style="color:#aaa;float:right;font-size:28px;font-weight:bold;cursor:pointer;">&times;</span>
        <h3 id="commentModalTitle" style="margin-top:0;color:#2c3e50;border-bottom:1px solid #eee;padding-bottom:10px;">💬 Diskusi Materi</h3>
        
        <div id="commentsList" style="max-height:300px;overflow-y:auto;margin:15px 0;padding-right:10px;">
            <p style="text-align:center;color:#7f8c8d;">Memuat komentar...</p>
        </div>
        
        <div style="border-top:1px solid #eee;padding-top:15px;">
            <form id="commentForm" onsubmit="submitComment(event)">
                <input type="hidden" id="commentMaterialId" name="material_id">
                <div id="replyIndicator" style="display:none;background:#f0f3f4;padding:8px;border-radius:5px;margin-bottom:10px;font-size:13px;color:#2c3e50;border-left:3px solid #3498db;justify-content:space-between;align-items:center;">
                    <span id="replyText">Membalas komentar...</span>
                    <button type="button" onclick="cancelReply()" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;font-size:14px;">&times; Batal</button>
                </div>
                <textarea id="commentText" name="comment_text" rows="3" placeholder="Tulis komentar Anda di sini..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:5px;resize:vertical;font-family:inherit;margin-bottom:10px;" required></textarea>
                <button type="submit" id="btnSubmitComment" style="background:#3498db;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-weight:bold;transition:0.3s;">Kirim Komentar</button>
            </form>
        </div>
    </div>
</div>

<script>
let currentMaterialId = 0;
let replyingToCommentId = null;

function replyTo(commentId, nama) {
    replyingToCommentId = commentId;
    document.getElementById('replyIndicator').style.display = 'flex';
    document.getElementById('replyText').innerHTML = 'Membalas komentar <strong>' + nama + '</strong>';
    document.getElementById('commentText').focus();
}

function cancelReply() {
    replyingToCommentId = null;
    document.getElementById('replyIndicator').style.display = 'none';
}

function openCommentModal(materialId, fileName) {
    currentMaterialId = materialId;
    document.getElementById('commentMaterialId').value = materialId;
    document.getElementById('commentModalTitle').innerHTML = '💬 Diskusi: <br><small style="color:#7f8c8d;font-weight:normal;">' + fileName + '</small>';
    document.getElementById('commentModal').style.display = 'block';
    document.getElementById('commentText').value = '';
    cancelReply();
    loadComments();
}

function closeCommentModal() {
    document.getElementById('commentModal').style.display = 'none';
}

function loadComments() {
    const list = document.getElementById('commentsList');
    list.innerHTML = '<p style="text-align:center;color:#7f8c8d;">Memuat komentar...</p>';
    
    fetch('api_comments.php?material_id=' + currentMaterialId)
        .then(res => res.text())
        .then(text => {
            try {
                let data = JSON.parse(text);
                if(data.status === 'success') {
                    if(data.data.length === 0) {
                        list.innerHTML = '<p style="text-align:center;color:#7f8c8d;font-style:italic;">Belum ada komentar. Jadilah yang pertama!</p>';
                    } else {
                        let html = '';
                        let parents = data.data.filter(c => !c.parent_id);
                        let replies = data.data.filter(c => c.parent_id);

                        parents.forEach(c => {
                            let delBtn = c.is_owner ? `<button onclick="deleteComment(${c.id})" style="float:right;background:none;border:none;color:#e74c3c;cursor:pointer;font-size:12px;padding:0;" title="Hapus Komentar">🗑️ Hapus</button>` : '';
                            let replyBtn = `<button onclick="replyTo(${c.id}, '${c.nama.replace(/'/g, "\\'")}')" style="float:left;background:none;border:none;color:#3498db;cursor:pointer;font-size:12px;padding:0;margin-top:8px;font-weight:bold;" title="Balas Komentar ini">↩️ Balas</button>`;
                            html += `
                            <div style="background:#f8f9fa;padding:10px;border-radius:6px;margin-bottom:10px;border-left:3px solid #3498db;">
                                <div style="margin-bottom:5px;">
                                    <strong style="color:#2c3e50;">${c.nama}</strong> 
                                    <span style="color:#95a5a6;font-size:12px;margin-left:5px;">(${c.sekolah_asal})</span>
                                    <span style="float:right;color:#bdc3c7;font-size:11px;">${c.formatted_date}</span>
                                </div>
                                <div style="color:#34495e;line-height:1.4;white-space:pre-wrap;font-size:14px;">${c.comment_text}</div>
                                ${replyBtn}
                                ${delBtn}
                                <div style="clear:both;"></div>
                            </div>`;
                            
                            let pReplies = replies.filter(r => r.parent_id == c.id);
                            pReplies.forEach(r => {
                                let delReplyBtn = r.is_owner ? `<button onclick="deleteComment(${r.id})" style="float:right;background:none;border:none;color:#e74c3c;cursor:pointer;font-size:12px;padding:0;" title="Hapus Balasan">🗑️ Hapus</button>` : '';
                                html += `
                                <div style="background:#fff;padding:8px 10px;border-radius:6px;margin-bottom:10px;margin-left:30px;border-left:2px solid #bdc3c7;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="margin-bottom:3px;">
                                        <strong style="color:#2c3e50;font-size:13px;">${r.nama}</strong> 
                                        <span style="color:#95a5a6;font-size:11px;margin-left:5px;">(${r.sekolah_asal})</span>
                                        <span style="float:right;color:#bdc3c7;font-size:11px;">${r.formatted_date}</span>
                                    </div>
                                    <div style="color:#555;line-height:1.4;white-space:pre-wrap;font-size:13px;">${r.comment_text}</div>
                                    ${delReplyBtn}
                                    <div style="clear:both;"></div>
                                </div>`;
                            });
                        });
                        list.innerHTML = html;
                        list.scrollTop = list.scrollHeight;
                    }
                } else {
                    list.innerHTML = '<p style="color:#e74c3c;">Gagal memuat: ' + data.message + '</p>';
                }
            } catch(e) {
                list.innerHTML = '<p style="color:#e74c3c;">Error Server: </p><div style="font-size:10px;background:#eee;padding:5px;word-break:break-all;">' + text + '</div>';
            }
        })
        .catch(e => {
            list.innerHTML = '<p style="color:#e74c3c;">Terjadi kesalahan koneksi.</p>';
        });
}

function submitComment(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitComment');
    btn.innerHTML = 'Mengirim...';
    btn.disabled = true;
    
    const formData = new FormData(document.getElementById('commentForm'));
    if (replyingToCommentId) {
        formData.append('parent_id', replyingToCommentId);
    }
    
    fetch('api_comments.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        btn.innerHTML = 'Kirim Komentar';
        btn.disabled = false;
        try {
            let data = JSON.parse(text);
            if(data.status === 'success') {
                document.getElementById('commentText').value = '';
                cancelReply();
                loadComments();
            } else {
                alert('Gagal: ' + data.message);
            }
        } catch(e) {
            console.error("Parse error:", text);
            alert("Terjadi kesalahan parsing: \n\n" + text.substring(0, 100));
        }
    })
    .catch(e => {
        btn.innerHTML = 'Kirim Komentar';
        btn.disabled = false;
        alert('Kesalahan koneksi internet');
    });
}

let currentCommentIdToDelete = null;

function deleteComment(commentId) {
    currentCommentIdToDelete = commentId;
    const modal = document.getElementById('deleteCommentModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeDeleteCommentModal() {
    const modal = document.getElementById('deleteCommentModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

function processDeleteComment() {
    if(!currentCommentIdToDelete) return;
    
    closeDeleteCommentModal();
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('comment_id', currentCommentIdToDelete);
    
    fetch('api_comments.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            loadComments();
        } else {
            alert('Gagal: ' + data.message);
        }
    });
}

window.onclick = function(event) {
    const modal = document.getElementById('commentModal');
    if (event.target == modal) {
        closeCommentModal();
    }
}
</script>
</body>
</html>
