<?php

session_start();

include 'config/database.php';

// =======================
// CEK LOGIN & ROLE ADMIN
// =======================

if(!isset($_SESSION['login']) || $_SESSION['role_id'] != 1){
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
// UBAH STATUS
// =======================
if(isset($_GET['status']) && isset($_GET['id'])){
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    if(in_array($status, ['pending', 'diproses', 'selesai'])){
        // Jika statusnya selesai dan admin menyertakan catatan
        if($status == 'selesai' && isset($_GET['catatan'])){
            $catatan = mysqli_real_escape_string($conn, trim($_GET['catatan']));
            
            // Cek dan buat kolom admin_note jika belum ada
            $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
            if(mysqli_num_rows($cek_col) == 0){
                mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
            }
            
            mysqli_query($conn, "UPDATE material_requests SET status = '$status', admin_note = '$catatan' WHERE id = '$id'");
        } else {
            mysqli_query($conn, "UPDATE material_requests SET status = '$status' WHERE id = '$id'");
        }
        
        $_SESSION['success'] = 'Status request berhasil diperbarui!';
        header("Location: kelola_request.php");
        exit;
    }
}

// =======================
// HAPUS REQUEST
// =======================
if(isset($_GET['hapus'])){
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }
    
    $id = (int)$_GET['hapus'];
    if(isset($_GET['alasan'])){
        $alasan = trim($_GET['alasan']);
        if($alasan !== ''){
            $admin_note = mysqli_real_escape_string($conn, "Sistem: Ditolak - " . $alasan);
            
            // Cek dan buat kolom admin_note jika belum ada
            $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM material_requests LIKE 'admin_note'");
            if(mysqli_num_rows($cek_col) == 0){
                mysqli_query($conn, "ALTER TABLE material_requests ADD admin_note TEXT NULL");
            }
            
            // Soft delete untuk mempertahankan notifikasi lonceng tanpa mengubah skema
            mysqli_query($conn, "UPDATE material_requests SET status = 'selesai', admin_note = '$admin_note' WHERE id = '$id'");
            $_SESSION['success'] = 'Request berhasil dihapus dan notifikasi telah dikirim!';
        }
    }
    header("Location: kelola_request.php");
    exit;
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
// PENCARIAN (SEARCH)
// =======================
$search = '';
if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
}

// =======================
// AMBIL DATA REQUEST
// =======================
$query = mysqli_query($conn, "
    SELECT r.*, u.full_name, u.school_name 
    FROM material_requests r
    JOIN users u ON r.user_id = u.id
    WHERE (u.full_name LIKE '%$search%'
       OR u.school_name LIKE '%$search%'
       OR r.jenis_request LIKE '%$search%'
       OR r.deskripsi LIKE '%$search%')
       AND (r.admin_note IS NULL OR r.admin_note NOT LIKE 'Sistem: Ditolak - %')
    ORDER BY r.created_at DESC
");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Kelola Request Materi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{ font-family:Arial; background:#f4f6f9; margin:0; }
        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .main-content{ flex:1; min-width:0; padding:30px; }
        
        h2{ color:#2c3e50; margin-bottom:20px; margin-top:0; }
        
        table{ width:100%; border-collapse:collapse; background:white; box-shadow:0px 0px 10px rgba(0,0,0,0.08); border-radius:12px; overflow:hidden; }
        table th{ background:#2c3e50; color:white; padding:15px; text-align:left; font-size:15px; }
        table td{ padding:15px; border-bottom:1px solid #eee; vertical-align:top; line-height:1.5; font-size:14px; }
        tr:hover{ background:#fafafa; }
        
        .badge { display:inline-block; padding:5px 10px; border-radius:15px; font-size:12px; font-weight:bold; color:white; text-transform:uppercase; }
        .bg-pending { background:#e74c3c; }
        .bg-diproses { background:#f39c12; }
        .bg-selesai { background:#27ae60; }
        
        .btn { display:inline-block; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold; color:white; margin-bottom:5px; transition:0.3s; }
        .btn-blue { background:#3498db; } .btn-blue:hover { background:#2980b9; }
        .btn-green { background:#27ae60; } .btn-green:hover { background:#219150; }
        .btn-red { background:#e74c3c; } .btn-red:hover { background:#c0392b; }
        
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
            table{ font-size:13px; }
            table th, table td{ padding:10px; }
            form { flex-direction: column; }
        }
        
        /* =======================
           POPUP MODAL SUCCESS
        ======================= */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
        }
        .popup-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .popup-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            width: 350px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transform: translateY(-20px);
            transition: 0.3s;
        }
        .popup-overlay.show .popup-box {
            transform: translateY(0);
        }
        .popup-icon {
            width: 60px;
            height: 60px;
            background: #27ae60;
            color: white;
            font-size: 30px;
            line-height: 60px;
            border-radius: 50%;
            margin: 0 auto 15px;
        }
        .popup-message {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .popup-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }
        .popup-btn:hover {
            background: #2980b9;
        }
        
        .delete-btn-cancel {
            background: #95a5a6;
        }
        .delete-btn-cancel:hover {
            background: #7f8c8d;
        }
        .delete-btn-confirm {
            background: #e74c3c;
        }
        .delete-btn-confirm:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>

<!-- POPUP SUCCESS -->
<?php if(!empty($success_message)){ ?>
<div class="popup-overlay" id="successPopup">
    <div class="popup-box">
        <div class="popup-icon">✓</div>
        <div class="popup-message"><?= htmlspecialchars($success_message); ?></div>
        <button class="popup-btn" onclick="closePopup()">Tutup</button>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('successPopup').classList.add('show');
    });
    function closePopup() {
        document.getElementById('successPopup').classList.remove('show');
    }
</script>
<?php } ?>

<!-- POPUP DELETE REASON -->
<div class="popup-overlay" id="deletePopup">
    <div class="popup-box">
        <div class="popup-icon" style="background:#e74c3c;">!</div>
        <div class="popup-message">Alasan Hapus Request</div>
        <p style="font-size:12px; color:#7f8c8d; margin-bottom:15px; text-align:left;">Silakan berikan alasan mengapa request ini dihapus. Alasan akan dikirim ke guru.</p>
        <input type="hidden" id="delete_id">
        <textarea id="delete_reason" rows="3" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; margin-bottom:10px; font-family:inherit; resize:none; box-sizing:border-box;" placeholder="Masukkan alasan penghapusan..."></textarea>
        <div id="delete_error" style="color:#e74c3c; font-size:12px; margin-bottom:10px; display:none; text-align:left;">Alasan tidak boleh kosong!</div>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button class="popup-btn delete-btn-cancel" onclick="tutupPopupHapus()">Batal</button>
            <button class="popup-btn delete-btn-confirm" onclick="submitHapus()">Hapus Request</button>
        </div>
    </div>
</div>

<div class="wrapper">
    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform Admin</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <div class="logo">ADMIN PANEL</div>
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
        <h2>Kelola Request Data Materi</h2>
        
        <form method="GET" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Cari berdasarkan nama guru, sekolah, kategori, atau deskripsi..." value="<?= htmlspecialchars($search); ?>" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #ccc; outline: none; font-size: 14px;">
            <button type="submit" style="padding: 12px 25px; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.3s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Cari</button>
            <?php if(!empty($search)){ ?>
                <a href="kelola_request.php" style="padding: 12px 25px; background: #95a5a6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 14px; transition: 0.3s; display: flex; align-items: center;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">Reset</a>
            <?php } ?>
        </form>

        <div style="overflow-x:auto;">
        <table>
            <tr>
                <th width="5%">No</th>
                <th width="20%">Guru / Sekolah</th>
                <th width="20%">Kategori</th>
                <th width="30%">Deskripsi Request</th>
                <th width="10%">Status</th>
                <th width="15%">Aksi</th>
            </tr>
            <?php if(mysqli_num_rows($query) > 0){ 
                $no = 1;
                while($row = mysqli_fetch_assoc($query)){
                    $status_class = 'bg-pending';
                    if($row['status'] == 'diproses') $status_class = 'bg-diproses';
                    if($row['status'] == 'selesai') $status_class = 'bg-selesai';
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td>
                    <strong><?= htmlspecialchars($row['full_name']); ?></strong><br>
                    <span style="color:#777; font-size:12px;"><?= htmlspecialchars(isset($row['school_name']) ? $row['school_name'] : '-'); ?></span><br>
                    <span style="color:#aaa; font-size:11px;"><?= date('d M Y H:i', strtotime($row['created_at'])); ?></span>
                </td>
                <td><strong><?= htmlspecialchars($row['jenis_request']); ?></strong></td>
                <td>
                    <?= nl2br(htmlspecialchars($row['deskripsi'])); ?>
                    <?php if($row['status'] == 'selesai' && !empty($row['admin_note'])){ ?>
                        <div style="margin-top:10px; padding:10px; background:#eafaf1; border-left:4px solid #27ae60; border-radius:5px; font-size:12px; color:#1e8449;">
                            <strong>Catatan:</strong> <?= htmlspecialchars($row['admin_note']); ?>
                        </div>
                    <?php } ?>
                </td>
                <td>
                    <span class="badge <?= $status_class; ?>"><?= $row['status']; ?></span>
                </td>
                <td>
                    <?php if($row['status'] == 'pending'){ ?>
                        <a href="upload_materi.php?request_id=<?= $row['id']; ?>" class="btn btn-blue">Upload Materi</a>
                        <a href="?status=diproses&id=<?= $row['id']; ?>&csrf_token=<?= $csrf_token; ?>" class="btn btn-blue" style="background:#f39c12;">Tandai Diproses</a>
                    <?php } elseif($row['status'] == 'diproses'){ ?>
                        <a href="upload_materi.php?request_id=<?= $row['id']; ?>" class="btn btn-blue">Upload Materi</a>
                        <a href="#" class="btn btn-green" onclick="selesaikanRequest(<?= $row['id']; ?>);">Selesai Manual</a>
                    <?php } ?>
                    
                    <a href="#" class="btn btn-red" onclick="bukaPopupHapus(<?= $row['id']; ?>); return false;">Hapus</a>
                </td>
            </tr>
            <?php } } else { ?>
            <tr>
                <td colspan="6" style="text-align:center; padding:30px; color:#777;">Belum ada request materi.</td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </div>
</div>

<script>
function selesaikanRequest(id) {
    let catatan = prompt("Tandai sebagai selesai?\n\nJika materi sudah ada di Data Materi, tambahkan catatan untuk guru (opsional):", "Sudah tersedia. Silakan cek di Data Materi");
    if (catatan !== null) {
        window.location.href = "?status=selesai&id=" + id + "&catatan=" + encodeURIComponent(catatan) + "&csrf_token=<?= $csrf_token; ?>";
    }
}

function bukaPopupHapus(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_reason').value = '';
    document.getElementById('delete_error').style.display = 'none';
    document.getElementById('deletePopup').classList.add('show');
}

function tutupPopupHapus() {
    document.getElementById('deletePopup').classList.remove('show');
}

function submitHapus() {
    let reason = document.getElementById('delete_reason').value.trim();
    if (reason === '') {
        document.getElementById('delete_error').style.display = 'block';
        return;
    }
    let id = document.getElementById('delete_id').value;
    let csrf = '<?= $csrf_token; ?>';
    window.location.href = "?hapus=" + id + "&alasan=" + encodeURIComponent(reason) + "&csrf_token=" + csrf;
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
