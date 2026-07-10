<?php
session_start();
include 'config/database.php';

if(!isset($_SESSION['login']) || $_SESSION['role_id'] != 1){
    header("Location:index.php");
    exit;
}

// ==========================================
// PENGATURAN PAGINATION
// ==========================================
$limit = 20; // Menampilkan 20 data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Subquery gabungan (UNION) untuk semua aktivitas
$base_query = "SELECT 'Login' as aktivitas, u.full_name, u.school_name, l.login_time as waktu, 'Berhasil masuk ke sistem' as detail FROM login_activity l JOIN users u ON l.user_id = u.id
    
    UNION ALL
    
    SELECT 'Upload' as aktivitas, COALESCE(u.full_name, m.contributor_name, 'External') as full_name, COALESCE(u.school_name, m.contributor_institution, '-') as school_name, m.created_at as waktu, CONCAT('Mengunggah materi: ', m.title) as detail
    FROM materials m LEFT JOIN users u ON m.user_id = u.id
    
    UNION ALL
    
    SELECT 'Download' as aktivitas, u.full_name, u.school_name, d.downloaded_at as waktu, CONCAT('Mengunduh materi: ', m.title) as detail
    FROM downloads d JOIN users u ON d.user_id = u.id JOIN materials m ON d.material_id = m.id
    
    UNION ALL
    
    SELECT 'Request' as aktivitas, u.full_name, u.school_name, r.created_at as waktu, CONCAT('Meminta materi: ', r.jenis_request) as detail
    FROM material_requests r JOIN users u ON r.user_id = u.id";

// Hitung total seluruh data aktivitas
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM ($base_query) as all_logs");
$total_data = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_data / $limit);

// Ambil data dengan batasan limit dan offset sesuai halaman
$query = mysqli_query($conn, "$base_query ORDER BY waktu DESC LIMIT $limit OFFSET $offset");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Log Aktivitas (Audit Trail)</title>
    <style>
        body{ font-family:Arial; background:#f4f6f9; margin:0; }
        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .content{ flex:1; padding:30px; }
        .card{ background:white; padding:25px; border-radius:12px; box-shadow:0 0 10px rgba(0,0,0,0.05); }
        table{ width:100%; border-collapse:collapse; }
        table th{ background:#2c3e50; color:white; padding:15px; text-align:left; }
        table td{ padding:15px; border-bottom:1px solid #eee; font-size:14px; }
        tr:hover { background: #fbfcfd; }
        .badge { padding:6px 12px; border-radius:15px; font-size:11px; font-weight:bold; color:white; text-transform:uppercase; }
        .b-login { background:#3498db; }
        .b-upload { background:#27ae60; }
        .b-download { background:#f39c12; }
        .b-request { background:#9b59b6; }

        @media(max-width:768px){
            .wrapper{ flex-direction:column; }
            .sidebar{ width:100%; height:auto; position:static; }
            .content{ padding:15px; }
            .card { padding: 15px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="logo">ADMIN PANEL</div>
        <div class="menu">
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="log_aktivitas.php" style="background:#34495e;">Log Aktivitas (Audit)</a>
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    <div class="content">
        <h2 style="margin-top:0; color:#2c3e50;">Log Aktivitas & Jejak Audit Sistem</h2>
        <p style="color:#7f8c8d; margin-top:-10px; margin-bottom:20px;">Memantau seluruh interaksi pengguna: Login, Upload, Download, dan Request.</p>
        
        <div class="card">
            <div style="overflow-x:auto;">
            <table>
                <tr>
                    <th width="15%">Waktu</th>
                    <th width="25%">Pengguna / Sekolah</th>
                    <th width="15%">Tipe Aktivitas</th>
                    <th width="45%">Detail Keterangan</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($query)){ 
                    $b_class = 'b-login';
                    if($row['aktivitas'] == 'Upload') $b_class = 'b-upload';
                    if($row['aktivitas'] == 'Download') $b_class = 'b-download';
                    if($row['aktivitas'] == 'Request') $b_class = 'b-request';
                ?>
                <tr>
                    <td style="color:#7f8c8d; font-size: 13px;"><?= date('d M Y H:i:s', strtotime($row['waktu'])); ?></td>
                    <td><strong><?= htmlspecialchars($row['full_name']); ?></strong><br><span style="font-size:12px; color:#7f8c8d;"><?= htmlspecialchars($row['school_name']); ?></span></td>
                    <td><span class="badge <?= $b_class; ?>"><?= $row['aktivitas']; ?></span></td>
                    <td style="color:#34495e; line-height: 1.5;"><?= htmlspecialchars($row['detail']); ?></td>
                </tr>
                <?php } ?>
            </table>
            </div>
            
            <!-- PAGINATION UI -->
            <?php if($total_pages > 1): ?>
            <div style="margin-top:25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <div style="color:#7f8c8d; font-size:14px;">
                    Menampilkan halaman <strong><?= $page; ?></strong> dari <strong><?= $total_pages; ?></strong> <br>(Total: <?= number_format($total_data); ?> riwayat aktivitas)
                </div>
                <div style="display:flex; gap:8px;">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1; ?>" style="padding:8px 15px; background:#2c3e50; color:white; text-decoration:none; border-radius:6px; font-size:13px; font-weight:bold; transition:0.3s;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?= $i; ?>" style="padding:8px 15px; background:<?= $i == $page ? '#3498db' : '#ecf0f1'; ?>; color:<?= $i == $page ? 'white' : '#2c3e50'; ?>; text-decoration:none; border-radius:6px; font-size:13px; font-weight:bold; transition:0.3s;" onmouseover="this.style.background='<?= $i == $page ? '#2980b9' : '#dfe6e9'; ?>'" onmouseout="this.style.background='<?= $i == $page ? '#3498db' : '#ecf0f1'; ?>'"><?= $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1; ?>" style="padding:8px 15px; background:#2c3e50; color:white; text-decoration:none; border-radius:6px; font-size:13px; font-weight:bold; transition:0.3s;" onmouseover="this.style.background='#34495e'" onmouseout="this.style.background='#2c3e50'">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>