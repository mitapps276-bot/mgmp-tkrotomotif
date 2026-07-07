<?php

session_start();

include 'config/database.php';
require_once 'config/functions.php';

date_default_timezone_set('Asia/Makassar');

// ======================================
// CEK LOGIN ADMIN
// ======================================

if(!isset($_SESSION['login'])){

    header("Location:index.php");
    exit;

}

// ======================================
// CEK ROLE ADMIN
// ======================================

if($_SESSION['role_id'] != 1){

    header("Location:index.php");
    exit;

}

// ======================================
// CSRF TOKEN
// ======================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// ======================================
// APPROVE MATERI
// ======================================

if(isset($_GET['approve'])){

    // Validasi CSRF Token
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $id = intval($_GET['approve']);

    mysqli_query($conn, "

        UPDATE materials
        SET status='approved'
        WHERE id='$id'

    ");

    // ==========================================
    // AUTO-DETECT REQUEST (SMART MATCHING)
    // ==========================================
    $cek_mat = mysqli_query($conn, "SELECT title, category, grade_level, contributor_name, fulfilled_request_ids FROM materials WHERE id = '$id'");
    if($cek_mat && mysqli_num_rows($cek_mat) > 0) {
        $mat = mysqli_fetch_assoc($cek_mat);
        
        $admin_note = mysqli_real_escape_string($conn, "Materi dibantu upload oleh Kontributor External (" . $mat['contributor_name'] . ") melalui verifikasi Administrator. Silakan cek di menu Data Materi.");
        
        if (!empty($mat['fulfilled_request_ids'])) {
            $req_ids = mysqli_real_escape_string($conn, $mat['fulfilled_request_ids']);
            mysqli_query($conn, "UPDATE material_requests SET status = 'selesai', admin_note = '$admin_note' WHERE id IN ($req_ids)");
            
            // ✅ NOTIFIKASI TELEGRAM: Kirim ke guru-guru yang me-request
            if (function_exists('notifGuruRequestTelegram')) {
                $id_array = explode(',', $mat['fulfilled_request_ids']);
                foreach ($id_array as $req_id) {
                    $req_id = trim($req_id);
                    if (!empty($req_id)) {
                        $pesan_tg = "🔔 <b>SI-LIAK Notifikasi</b>\n\n";
                        $pesan_tg .= "Halo! Kabar baik, request materi Anda telah dipenuhi!\n\n";
                        $pesan_tg .= "📚 <b>Judul Materi:</b> " . htmlspecialchars($mat['title']) . "\n";
                        $pesan_tg .= "👤 <b>Diunggah Oleh:</b> " . htmlspecialchars($mat['contributor_name']) . " (Kontributor External)\n\n";
                        $pesan_tg .= "Silakan cek di menu <b>Data Materi</b> pada platform SI-LIAK.";
                        notifGuruRequestTelegram($conn, $req_id, $pesan_tg);
                    }
                }
            }
        }
        
        // Panggil fungsi helper dari database.php untuk mendeteksi request lain yang mirip
        jalankanSmartMatching($conn, $mat['title'], $mat['category'], $mat['grade_level'], $admin_note);
    }

    $_SESSION['popup_type'] = 'success';
    $_SESSION['popup_msg'] = 'Materi berhasil disetujui (Approved).';

    // ✅ NOTIFIKASI TELEGRAM ke kontributor
    if (function_exists('notifKontributorTelegram')) {
        $pesan_approve = "✅ <b>Materi Anda Disetujui!</b>\n\n";
        $pesan_approve .= "Halo! Materi yang Anda kirimkan ke SI-LIAK telah <b>disetujui</b> oleh Admin.\n\n";
        $pesan_approve .= "📚 <b>Judul:</b> " . htmlspecialchars($mat['title']) . "\n\n";
        $pesan_approve .= "Materi Anda kini tersedia untuk diakses seluruh guru MGMP. Terima kasih atas kontribusinya! 🙏";
        notifKontributorTelegram($conn, $id, $pesan_approve);
    }

    header("Location: review_materials.php");
    exit;
}

// ======================================
// REJECT MATERI
// ======================================

if(isset($_GET['reject'])){

    // Validasi CSRF Token
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $id = intval($_GET['reject']);
    $reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, trim($_GET['reason'])) : '';

    $cek_reject_column = mysqli_query($conn, "SHOW COLUMNS FROM materials LIKE 'reject_reason'");
    if($cek_reject_column && mysqli_num_rows($cek_reject_column) == 0){
        mysqli_query($conn, "ALTER TABLE materials ADD reject_reason TEXT NULL");
    }

    mysqli_query($conn, "

        UPDATE materials
        SET status='rejected', reject_reason='$reason'
        WHERE id='$id'

    ");

    $_SESSION['popup_type'] = 'reject';
    $_SESSION['popup_msg'] = 'Materi telah ditolak (Rejected) dan dikembalikan ke kontributor.';

    // ❌ NOTIFIKASI TELEGRAM ke kontributor
    if (function_exists('notifKontributorTelegram')) {
        $pesan_reject = "❌ <b>Materi Perlu Diperbaiki</b>\n\n";
        $pesan_reject .= "Halo! Materi yang Anda kirimkan ke SI-LIAK belum dapat disetujui.\n\n";
        if (!empty($reason)) {
            $pesan_reject .= "📝 <b>Alasan Admin:</b>\n" . htmlspecialchars($reason) . "\n\n";
        }
        $pesan_reject .= "Silakan perbaiki dan upload kembali. Terima kasih atas pengertiannya.";
        notifKontributorTelegram($conn, $id, $pesan_reject);
    }

    header("Location: review_materials.php");
    exit;
}

// ======================================
// DOWNLOAD FILE EXTERNAL
// ======================================

if(isset($_GET['download'])){

    $id = intval($_GET['download']);

    $download_query = mysqli_query($conn, "

        SELECT *
        FROM materials
        WHERE id='$id'

    ");

    $file = mysqli_fetch_assoc($download_query);

    if($file){

        $file_path =
        "assets/uploads/" . $file['file_name'];

        if(file_exists($file_path)){

            header("Content-Description: File Transfer");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"".$file['file_name']."\"");
            header("Expires: 0");
            header("Cache-Control: must-revalidate");
            header("Pragma: public");
            header("Content-Length: " . filesize($file_path));

            readfile($file_path);

            exit;

        }else{

            $_SESSION['popup_type'] = 'error';
            $_SESSION['popup_msg'] = 'File materi tidak ditemukan di server.';
            header("Location: review_materials.php");
            exit;

        }

    }

}

// ======================================
// AMBIL DATA PENDING
// ======================================

$data = mysqli_query($conn, "

    SELECT m.*, u.full_name, u.email, u.school_name
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.status='pending'
    ORDER BY m.created_at DESC

");

$popup_msg = isset($_SESSION['popup_msg']) ? $_SESSION['popup_msg'] : '';
$popup_type = isset($_SESSION['popup_type']) ? $_SESSION['popup_type'] : '';
unset($_SESSION['popup_msg']);
unset($_SESSION['popup_type']);

?>

<!DOCTYPE html>
<html>
<head>

    <title>Review Materials</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>

        body{
            font-family: Arial;
            background: #f4f6f9;
            margin: 0;
        }

        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .main-content{ flex:1; min-width:0; padding:30px; }
        
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
            .sidebar{ width:100%; height:auto; position:static; display: none; } 
            .sidebar.active { display: block; }
            .sidebar .logo { display: none; }
            .main-content{ padding:15px; } 
        }

        h2{
            margin-bottom: 20px;
        }

        table{
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        table th,
        table td{
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        /* Membatasi lebar kolom agar tidak memanjang merusak tabel */
        table td:nth-child(2) { max-width: 250px; word-wrap: break-word; overflow-wrap: break-word; hyphens: auto; } /* Judul */
        table td:nth-child(4) { max-width: 180px; word-wrap: break-word; overflow-wrap: break-word; } /* Email */
        table td:nth-child(5) { max-width: 200px; word-wrap: break-word; overflow-wrap: break-word; } /* Instansi */
        table td:nth-child(6) { max-width: 200px; word-wrap: break-word; overflow-wrap: break-word; } /* File */

        table th{
            background: #007bff;
            color: white;
        }

        .approve{
            background: #27ae60;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s;
            margin-bottom: 4px;
        }
        .approve:hover {
            background: #219150;
        }

        .reject{
            background: #e74c3c;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s;
            margin-bottom: 4px;
        }
        .reject:hover {
            background: #c0392b;
        }

        .download{
            background: #3498db;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            transition: 0.3s;
            margin-bottom: 4px;
        }
        .download:hover {
            background: #2980b9;
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
        }
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .modal-content h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            min-height: 80px;
            margin-bottom: 15px;
            box-sizing: border-box;
            font-family: inherit;
            resize: vertical;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; background: #95a5a6; color: white; font-weight: bold; transition:0.3s; }
        .btn-confirm { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; background: #e74c3c; color: white; font-weight: bold; transition:0.3s; }
        .btn-cancel:hover { background: #7f8c8d; }
        .btn-confirm:hover { background: #c0392b; }
        .btn-success { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; background: #27ae60; color: white; font-weight: bold; transition:0.3s; }
        .btn-success:hover { background: #219150; }

    </style>

</head>
<body>

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
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="upload_materi.php">Upload Materi</a>
            <a href="review_materials.php">Review Contributor</a>
            <a href="kelola_request.php">Request Materi</a>
            <a href="analytics.php">Analytics</a>
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

<div class="main-content">
    <div class="page-header" style="margin-bottom: 25px;">
        <h1 style="margin: 0; font-size: 26px; color: #2c3e50;">Review Kontributor Eksternal</h1>
        <div style="color: #7f8c8d; font-size: 14px; margin-top: 5px;">
            Verifikasi dan evaluasi materi yang dikirimkan oleh kontributor eksternal sebelum diterbitkan.
        </div>
    </div>
<div style="overflow-x:auto;">
<table>

    <tr>

        <th>No</th>
        <th>Judul</th>
        <th>Contributor</th>
        <th>Email</th>
        <th>Instansi</th>
        <th>File</th>
        <th>Tanggal</th>
        <th>Aksi</th>

    </tr>

    <?php

    $no = 1;

    while($row = mysqli_fetch_assoc($data)){

    ?>

    <tr>

        <td><?= $no++; ?></td>

        <td>
            <?= htmlspecialchars($row['title']); ?>
        </td>

        <td>
            <?= htmlspecialchars($row['contributor_name'] ?: $row['full_name']); ?>
        </td>

        <td>
            <?= htmlspecialchars($row['contributor_email'] ?: $row['email']); ?>
        </td>

        <td>
            <?= htmlspecialchars($row['contributor_institution'] ?: $row['school_name']); ?>
        </td>

        <td>

            <a
                class="download"
                href="?download=<?= $row['id']; ?>"
            >
                Download
            </a>

        </td>

        <td>
            <?= $row['created_at']; ?>
        </td>

        <td>

            <a
                class="approve"
                href="#"
                onclick="approveMaterial(<?= $row['id']; ?>); return false;"
            >
                Approve
            </a>

            <a
                class="reject"
                href="#"
                onclick="rejectMaterial(<?= $row['id']; ?>)"
            >
                Reject
            </a>

        </td>

    </tr>

    <?php } ?>

</table>
</div>

</div>
</div>

<!-- Modal Custom Approve -->
<div id="approveModal" class="modal">
    <div class="modal-content" style="text-align: center;">
        <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">✅</div>
        <h3 style="margin-top: 0; color: #27ae60; font-size: 20px;">Approve Materi?</h3>
        <p style="color: #555; margin-bottom: 25px; font-size: 14px;">Yakin ingin menyetujui materi ini? Materi akan dipublikasikan ke Data Materi publik.</p>
        <div class="modal-actions" style="justify-content: center;">
            <button class="btn-cancel" onclick="closeApproveModal()">Batal</button>
            <button class="btn-success" onclick="submitApprove()">Approve Materi</button>
        </div>
    </div>
</div>

<!-- Modal Custom Reject -->
<div id="rejectModal" class="modal">
    <div class="modal-content" style="text-align: center;">
        <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">🛑</div>
        <h3 style="margin-top: 0; color: #e74c3c; font-size: 20px;">Tolak Materi?</h3>
        <p style="color: #555; margin-bottom: 15px; font-size: 14px;">Materi yang ditolak akan dikembalikan ke Kontributor untuk diperbaiki.</p>
        <textarea id="rejectReason" placeholder="Tuliskan alasan penolakan di sini..." style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; min-height: 80px; margin-bottom: 20px; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
        <div class="modal-actions" style="justify-content: center;">
            <button class="btn-cancel" onclick="closeRejectModal()">Batal</button>
            <button class="btn-confirm" onclick="submitReject()">Tolak Materi</button>
        </div>
    </div>
</div>

<!-- Modal Action Result -->
<?php if(!empty($popup_msg)){ ?>
<div id="resultModal" class="modal" style="display: flex; backdrop-filter: blur(3px);">
    <div class="modal-content" style="text-align: center; max-width: 350px;">
        <?php if($popup_type == 'success'){ ?>
            <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">✅</div>
            <h3 style="margin-top: 0; color: #27ae60; font-size: 20px;">Berhasil!</h3>
        <?php } elseif($popup_type == 'reject') { ?>
            <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">🛑</div>
            <h3 style="margin-top: 0; color: #e74c3c; font-size: 20px;">Ditolak!</h3>
        <?php } else { ?>
            <div style="font-size: 50px; margin-bottom: 10px; line-height: 1;">⚠️</div>
            <h3 style="margin-top: 0; color: #e74c3c; font-size: 20px;">Gagal!</h3>
        <?php } ?>
        <p style="color: #555; margin-bottom: 25px; font-size: 14px;"><?= htmlspecialchars($popup_msg); ?></p>
        <div class="modal-actions" style="justify-content: center;">
            <button class="<?= $popup_type == 'success' ? 'btn-success' : 'btn-confirm' ?>" onclick="document.getElementById('resultModal').style.display='none'">Tutup</button>
        </div>
    </div>
</div>
<?php } ?>

<script>
let currentRejectId = null;
let csrfToken = '<?= $csrf_token; ?>';

let currentApproveId = null;

function approveMaterial(id) {
    currentApproveId = id;
    document.getElementById('approveModal').style.display = 'flex';
}
function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
    currentApproveId = null;
}
function submitApprove() {
    if (currentApproveId !== null) {
        window.location.href = '?approve=' + currentApproveId + '&csrf_token=' + csrfToken;
    }
}

function rejectMaterial(id) {
    currentRejectId = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    currentRejectId = null;
}
function submitReject() {
    var reason = document.getElementById('rejectReason').value.trim();
    if (currentRejectId !== null) {
        window.location.href = '?reject=' + currentRejectId + '&reason=' + encodeURIComponent(reason) + '&csrf_token=' + csrfToken;
    }
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
</script>
</body>
</html>
