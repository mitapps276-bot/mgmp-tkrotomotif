<?php
$sidebar_role = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 0;
$is_admin    = ($sidebar_role == 1);
$is_guru     = ($sidebar_role == 2);
$is_visitor  = ($sidebar_role == 3);
$is_external = ($sidebar_role == 4);
?>
<div class="sidebar">
    <div class="logo">
        <?= ($is_admin) ? 'ADMIN PANEL' : (($is_external) ? 'External Contributor' : 'MGMP PLATFORM'); ?>
    </div>
    <div class="menu">
        <?php if($is_admin){ ?>
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="upload_materi.php">Upload Materi</a>
            <a href="review_materials.php">Review Contributor</a>
            <a href="kelola_request.php">Request Materi Guru</a>
            <a href="analytics.php">Analytics</a>
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="log_aktivitas.php">Log Aktivitas (Audit)</a>
        <?php } elseif($is_external) { ?>
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
        
        <?php if($is_external){ ?>
            <a href="contributor_upload.php?logout=true">Logout</a>
        <?php } else { ?>
            <a href="logout.php">Logout</a>
        <?php } ?>
    </div>

    <?php if($is_guru){ 
        // Mengambil total guru khusus untuk tampilan di sidebar Guru
        $q_tg = mysqli_query($conn, "SELECT COUNT(*) AS total_guru FROM users WHERE role_id = 2");
        $tg = $q_tg ? mysqli_fetch_assoc($q_tg)['total_guru'] : 0;
        
        $q_ext = mysqli_query($conn, "SELECT COUNT(*) AS total_contributor FROM users WHERE role_id = 4");
        $te = $q_ext ? mysqli_fetch_assoc($q_ext)['total_contributor'] : 0;
    ?>
    <div style="padding: 20px; margin-top: auto;">
        <div onclick="openGuruModal()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; cursor: pointer; transition: 0.3s; text-align: center; margin-bottom: 15px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
            <h4 style="margin: 0 0 5px 0; color: #bdc3c7; font-size: 13px; text-transform: uppercase;">Total Guru MGMP</h4>
            <h2 style="margin: 0 0 5px 0; color: #1abc9c; font-size: 28px;"><?= $tg; ?></h2>
            <p style="margin: 0; font-size: 11px; color: #7f8c8d;">Klik untuk melihat detail</p>
        </div>
        <div onclick="openExternalModal()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; cursor: pointer; transition: 0.3s; text-align: center;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
            <h4 style="margin: 0 0 5px 0; color: #bdc3c7; font-size: 13px; text-transform: uppercase;">Total External Contributor</h4>
            <h2 style="margin: 0 0 5px 0; color: #f39c12; font-size: 28px;"><?= $te; ?></h2>
            <p style="margin: 0; font-size: 11px; color: #7f8c8d;">Klik untuk melihat detail</p>
        </div>
    </div>
    <?php } ?>
</div>