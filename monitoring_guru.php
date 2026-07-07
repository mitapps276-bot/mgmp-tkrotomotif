<?php

session_start();

date_default_timezone_set('Asia/Makassar');

include 'config/database.php';

// =====================================
// CEK LOGIN
// =====================================

if(!isset($_SESSION['login'])){

    header("Location:index.php");
    exit;

}

// =====================================
// BOBOT LEARNING ANALYTICS
// =====================================
//
// Upload Materi  = 7 poin
// Download       = 2 poin
// Login Harian   = 1 poin
//
// =====================================

$POINT_UPLOAD   = 7;
$POINT_DOWNLOAD = 2;
$POINT_LOGIN    = 1;

// =====================================
// FILTER SEARCH
// =====================================

$search = '';

if(isset($_GET['search'])){

    $search = mysqli_real_escape_string(
        $conn,
        trim($_GET['search'])
    );

}

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
// TOTAL UPLOAD
// =====================================

$total_upload_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_upload
FROM materials
INNER JOIN users ON materials.user_id = users.id
WHERE users.role_id = 2 AND materials.status = 'approved'

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
INNER JOIN users ON downloads.user_id = users.id
WHERE users.role_id = 2

");

$total_download =
mysqli_fetch_assoc(
    $total_download_query
)['total_download'];

// =====================================
// LOGIN HARI INI
// =====================================

$total_login_today_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_login_today

FROM login_activity

INNER JOIN users
ON login_activity.user_id = users.id

WHERE users.role_id = 2

AND login_activity.login_date = CURDATE()

");

$total_login_today =
mysqli_fetch_assoc(
    $total_login_today_query
)['total_login_today'];

// =====================================
// DOWNLOAD HARI INI
// =====================================

$total_download_today_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_download_today
FROM downloads
INNER JOIN users ON downloads.user_id = users.id
WHERE users.role_id = 2 AND DATE(downloads.downloaded_at)=CURDATE()

");

$total_download_today =
mysqli_fetch_assoc(
    $total_download_today_query
)['total_download_today'];

// =====================================
// UPLOAD HARI INI
// =====================================

$total_upload_today_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_upload_today
FROM materials
INNER JOIN users ON materials.user_id = users.id
WHERE users.role_id = 2 AND materials.status = 'approved'
AND DATE(materials.created_at)=CURDATE()

");

$total_upload_today =
mysqli_fetch_assoc(
    $total_upload_today_query
)['total_upload_today'];

// =====================================
// TOTAL LOGIN SISTEM
// =====================================

$total_login_query = mysqli_query($conn, "

SELECT COUNT(*) AS total_login
FROM login_activity
INNER JOIN users ON login_activity.user_id = users.id
WHERE users.role_id = 2

");

$total_login =
mysqli_fetch_assoc(
    $total_login_query
)['total_login'];

// =====================================
// TOTAL SKOR SISTEM
// =====================================

$total_system_score =

($total_upload * $POINT_UPLOAD)
+
($total_download * $POINT_DOWNLOAD)
+
($total_login * $POINT_LOGIN);

$upload_score =
$total_upload * $POINT_UPLOAD;

$download_score =
$total_download * $POINT_DOWNLOAD;

$login_score =
$total_login * $POINT_LOGIN;

// =====================================
// STATUS KONDISI SISTEM
// =====================================

if($total_system_score >= 300){

    $system_status =
    "Sangat Aktif";

    $system_badge =
    "sangat-aktif";

}

elseif($total_system_score >= 150){

    $system_status =
    "Aktif";

    $system_badge =
    "aktif";

}

elseif($total_system_score >= 50){

    $system_status =
    "Cukup Aktif";

    $system_badge =
    "cukup";

}

else{

    $system_status =
    "Kurang Aktif";

    $system_badge =
    "rendah";

}

// =====================================
// DATA MONITORING GURU
// =====================================

$monitoring = mysqli_query($conn, "

SELECT

    users.id,
    users.full_name,
    users.email,
    users.school_name,

    COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END)
    AS total_upload,

    COUNT(DISTINCT downloads.id)
    AS total_download,

    COUNT(DISTINCT login_activity.id)
    AS total_login,

    MAX(

        GREATEST(

            COALESCE(
                downloads.downloaded_at,
                '2000-01-01 00:00:00'
            ),

            COALESCE(
                login_activity.login_time,
                '2000-01-01 00:00:00'
            )

        )

    ) AS aktivitas_terakhir,

    (

        (

            COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END)
            * $POINT_UPLOAD

        )

        +

        (

            COUNT(DISTINCT downloads.id)
            * $POINT_DOWNLOAD

        )

        +

        (

            COUNT(DISTINCT login_activity.id)
            * $POINT_LOGIN

        )

    ) AS skor_aktivitas

FROM users

LEFT JOIN materials
ON users.id = materials.user_id

LEFT JOIN downloads
ON materials.id = downloads.material_id

LEFT JOIN login_activity
ON users.id = login_activity.user_id

WHERE users.role_id = 2

AND(

    users.full_name LIKE '%$search%'

    OR

    users.school_name LIKE '%$search%'

)

GROUP BY users.id

ORDER BY

    skor_aktivitas DESC,

    aktivitas_terakhir DESC

");

// =====================================
// TOP GURU
// =====================================

$top_guru = mysqli_query($conn, "

SELECT

    users.full_name,
    users.school_name,
    COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) AS total_upload,
    COUNT(DISTINCT downloads.id) AS total_download,
    COUNT(DISTINCT login_activity.id) AS total_login,

    (

        (COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * 7)

        +

        (COUNT(DISTINCT downloads.id) * 2)

        +

        (COUNT(DISTINCT login_activity.id) * 1)

    ) AS skor

FROM users

LEFT JOIN materials
ON users.id = materials.user_id

LEFT JOIN downloads
ON materials.id = downloads.material_id

LEFT JOIN login_activity
ON users.id = login_activity.user_id

WHERE users.role_id = 2

GROUP BY users.id

HAVING skor > 0

ORDER BY skor DESC, total_upload DESC, total_download DESC, total_login DESC, users.full_name ASC
LIMIT 1

");

$top_guru_data = mysqli_fetch_assoc($top_guru);

?>

<!DOCTYPE html>
<html>
<head>

<title>Monitoring Aktivitas Guru</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{

    margin:0;
    font-family:Arial;
    background:#f4f6f9;

}

.wrapper{ display:flex; min-height:100vh; }
.sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
.sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
.sidebar .menu a:hover{ background:#34495e; }
.main-content{ flex:1; min-width:0; }

.container{

    padding:30px;

}

h1{

    color:#2c3e50;
    margin-bottom:10px;

}

.subtitle{

    color:#666;
    margin-bottom:30px;

}

.top-grid{

    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:20px;
    margin-bottom:30px;

}

.info-card{

    background:white;
    border-radius:18px;
    padding:25px;
    box-shadow:0px 0px 15px rgba(0,0,0,0.06);

}

.info-card h3{

    margin:0;
    color:#777;
    font-size:14px;

}

.info-card h1{

    margin-top:15px;
    margin-bottom:0;
    font-size:38px;
    color:#2c3e50;

}

.formula-box{

    background:white;
    border-radius:18px;
    padding:25px;
    margin-bottom:30px;
    box-shadow:0px 0px 15px rgba(0,0,0,0.06);

}

.formula-box h2{

    margin-top:0;
    color:#2c3e50;

}

.formula-item{

    padding:10px 0;
    font-size:16px;
    color:#444;

}

.analytics-chart-wrap{

    display:grid;
    grid-template-columns:320px 1fr;
    gap:30px;
    align-items:center;

}

.analytics-chart{

    height:280px;

}

.analytics-summary{

    display:grid;
    gap:12px;

}

.analytics-row{

    display:flex;
    justify-content:space-between;
    gap:20px;
    padding:14px 16px;
    border-radius:12px;
    background:#f4f6f9;
    color:#444;

}

.analytics-row strong{

    color:#2c3e50;

}

.formula-highlight{

    color:#27ae60;
    font-weight:bold;

}

.system-status{

    margin-top:20px;
    padding-top:20px;
    border-top:1px solid #eee;

}

.system-status p{

    margin-top:10px;
    line-height:1.8;
    color:#555;

}

.range-chart-wrap{

    margin-top:15px;
    display:grid;
    grid-template-columns:260px 1fr;
    gap:25px;
    align-items:center;

}

.range-chart{

    height:240px;

}

.range-info{

    display:grid;
    gap:10px;

}

.range-row{

    padding:12px 14px;
    border-radius:12px;
    background:#f4f6f9;
    color:#444;
    font-size:14px;

}

.card{

    background:white;
    border-radius:20px;
    padding:25px;
    box-shadow:0px 0px 15px rgba(0,0,0,0.06);

}

.accordion-card{

    background:white;
    border-radius:20px;
    box-shadow:0px 0px 15px rgba(0,0,0,0.06);
    margin-bottom: 30px;

}

.accordion-header{

    padding:25px;
    font-size:20px;
    font-weight:bold;
    color:#2c3e50;
    cursor:pointer;
    display:flex;
    justify-content:space-between;
    align-items:center;
    user-select:none;
    transition:0.3s;
    border-radius:20px;

}

.accordion-header:hover{ background:#fbfcfd; }
.accordion-header.active{ border-bottom:1px solid #edf0f2; border-bottom-left-radius:0; border-bottom-right-radius:0; }
.accordion-header::after{ content:'▼'; font-size:16px; transition:transform 0.3s ease; }
.accordion-header.active::after{ transform:rotate(-180deg); }
.accordion-body{ padding:25px; display:none; }

.search-box{

    display:flex;
    gap:10px;
    margin-bottom:25px;

}

.search-box input{

    flex:1;
    padding:14px;
    border-radius:10px;
    border:1px solid #ddd;
    font-size:14px;

}

.search-box button{

    border:none;
    background:#3498db;
    color:white;
    padding:14px 20px;
    border-radius:10px;
    cursor:pointer;

}

.table-responsive{

    overflow-x:auto;

}

table{

    width:100%;
    border-collapse:collapse;

}

table th{

    background:#2c3e50;
    color:white;
    padding:16px;
    text-align:left;
    font-size:14px;

}

table td{

    padding:18px 16px;
    border-bottom:1px solid #eee;
    vertical-align:top;

}

table tr:hover{

    background:#fafafa;

}

.badge{

    padding:7px 14px;
    border-radius:20px;
    color:white;
    font-size:12px;
    font-weight:bold;
    display:inline-block;

}

.sangat-aktif{

    background:#27ae60;

}

.aktif{

    background:#1abc9c;

}

.cukup{

    background:#f39c12;

}

.rendah{

    background:#e74c3c;

}

.progress{

    width:100%;
    height:10px;
    background:#ecf0f1;
    border-radius:20px;
    overflow:hidden;
    margin-top:8px;

}

.progress-fill{

    height:100%;
    background:linear-gradient(
        90deg,
        #1abc9c,
        #27ae60
    );

}

.top-card{

    margin-top:30px;

    background:
    linear-gradient(
        135deg,
        #1abc9c,
        #16a085
    );

    color:white;

    border-radius:20px;
    padding:30px;

}

.top-card h2{

    margin-top:0;

}

.top-card h1{

    margin-bottom:10px;
    font-size:38px;
    color:white;

}

.top-card p{

    line-height:1.8;

}

.empty-top{

    margin-top:30px;

    background:white;

    border-radius:20px;

    padding:40px;

    text-align:center;

    box-shadow:0px 0px 15px rgba(0,0,0,0.06);

}

.empty-top h2{

    color:#2c3e50;
    margin-bottom:15px;

}

.empty-top p{

    color:#777;
    line-height:1.8;

}

.detail-aktivitas{

    line-height:1.7;
    font-size:13px;
    color:#666;
    margin-top:8px;

}

/* =========================
CAROUSEL TABLE
========================= */
.carousel-wrapper {
    position: relative;
    width: 100%;
}
.carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: #2c3e50;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    font-size: 18px;
    transition: 0.3s;
    opacity: 0.9;
}
.carousel-btn:hover { background: #1abc9c; opacity: 1; transform: translateY(-50%) scale(1.1); }
.carousel-btn.prev { left: -15px; }
.carousel-btn.next { right: -15px; }

.table-carousel-list {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    padding-bottom: 10px;
}
.table-carousel-list::-webkit-scrollbar { height: 8px; }
.table-carousel-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
.table-carousel-list::-webkit-scrollbar-thumb { background: #bdc3c7; border-radius: 10px; }
.table-carousel-item {
    flex: 0 0 100%;
    scroll-snap-align: start;
    box-sizing: border-box;
    padding: 0 2px;
}

@media(max-width:1200px){

    .top-grid{

        grid-template-columns:repeat(2,1fr);

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
    .page-header { flex-direction: column; align-items: flex-start !important; gap: 10px; }
    .page-header .btn-print { width: 100%; justify-content: center; }
    .top-grid{ grid-template-columns:1fr; }
    .analytics-chart-wrap{ grid-template-columns:1fr; }
    .range-chart-wrap{ grid-template-columns:1fr; }
    .wrapper{ flex-direction:column; }
    .mobile-nav { display: flex; }
    .sidebar{ width:100%; height:auto; position:static; display: none; }
    .sidebar.active { display: block; }
    .sidebar .logo { display: none; }
    .carousel-btn { display: none; }
    /* Memastikan tabel bisa digulir secara horizontal di mobile */
    .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
}

/* ======================
   PRINT STYLES
====================== */
@media print {
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        background: white !important;
    }
    .sidebar { display: none !important; }
    .mobile-nav { display: none !important; }
    .main-content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
    .wrapper { display: block !important; }
    .btn-print { display: none !important; }
    .search-box { display: none !important; }
    .carousel-btn { display: none !important; }
    .table-carousel-list { display: block !important; overflow: visible !important; }
    .table-carousel-item { display: block !important; margin-bottom: 20px !important; }
    .accordion-body { display: block !important; }
    .accordion-header::after { display: none !important; }
    
    /* Memastikan tabel tampil penuh di PDF */
    .table-container { overflow: visible !important; width: 100% !important; }
    table { width: 100% !important; page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    
    /* Membuat Grid menjadi Block agar menyusun ke bawah di kertas A4 */
    .top-grid, .analytics-chart-wrap, .range-chart-wrap {
        display: block !important;
        width: 100% !important;
    }

    .card, .info-card, .formula-box, .top-card, .accordion-card, .chart-card { 
        width: 100% !important;
        box-shadow: none !important; 
        border: 1px solid #ccc !important; 
        page-break-inside: avoid; 
        margin-bottom: 20px !important; 
    }
    .top-card { background: white !important; color: #2c3e50 !important; border: 2px solid #1abc9c !important; }
    .top-card h1, .top-card p, .top-card h2 { color: #2c3e50 !important; }
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
<div class="container" style="position: relative;">

    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h1 style="margin-bottom: 0;">
            Monitoring Aktivitas Semua Guru
        </h1>
        <button onclick="handlePrintOrDownload()" class="btn-print" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">
            🖨️ Cetak / Unduh PDF
        </button>
    </div>

    <div class="subtitle">

        Monitoring Realtime Aktivitas Guru
        Berbasis Learning Analytics

    </div>

    <!-- ================================= -->
    <!-- SUMMARY -->
    <!-- ================================= -->

    <div class="top-grid">

        <div class="info-card">

            <h3>Total Guru</h3>

            <h1>
                <?= $total_guru; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Total Upload</h3>

            <h1>
                <?= $total_upload; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Total Download</h3>

            <h1>
                <?= $total_download; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Total Login</h3>

            <h1>
                <?= $total_login; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Download Hari Ini</h3>

            <h1>
                <?= $total_download_today; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Upload Hari Ini</h3>

            <h1>
                <?= $total_upload_today; ?>
            </h1>

        </div>

        <div class="info-card">

            <h3>Login Hari Ini</h3>

            <h1>
                <?= $total_login_today; ?>
            </h1>

        </div>

    </div>

    <!-- ================================= -->
    <!-- TOP GURU -->
    <!-- ================================= -->

    <?php if($top_guru_data){ ?>

    <div class="top-card">

        <h2>
            Guru Paling Aktif
        </h2>

            <h1 style="font-size: 28px; margin-bottom: 5px;">
                <?= htmlspecialchars($top_guru_data['full_name']); ?>
            </h1>
            <p style="margin-top: 0; margin-bottom: 15px; font-weight: bold; opacity: 0.9;">
                <?= htmlspecialchars($top_guru_data['school_name']); ?>
            </p>
        
        <p style="border-top: 1px solid rgba(255,255,255,0.3); padding-top: 15px; margin-bottom: 0;">
            Menjadi guru dengan kontribusi tertinggi di platform (Skor: <?= $top_guru_data['skor']; ?> poin).
        </p>

    </div>

    <?php } else { ?>

    <div class="empty-top">

        <h2>
            Belum Ada Guru Aktif
        </h2>

        <p>

            Belum terdapat aktivitas upload,
            download, maupun login guru
            pada platform MGMP.

        </p>

    </div>

    <?php } ?>

    <!-- ================================= -->
    <!-- FORMULA -->
    <!-- ================================= -->

    <div class="formula-box">

        <h2>
            Perhitungan Learning Analytics
        </h2>

        <div class="analytics-chart-wrap">

            <div class="analytics-chart">

                <canvas id="learningAnalyticsChart"></canvas>

            </div>

            <div class="analytics-summary">

                <div class="analytics-row">
                    <span>Upload Materi</span>
                    <strong><?= $total_upload; ?> × 7 = <?= $upload_score; ?> poin</strong>
                </div>

                <div class="analytics-row">
                    <span>Download Materi</span>
                    <strong><?= $total_download; ?> × 2 = <?= $download_score; ?> poin</strong>
                </div>

                <div class="analytics-row">
                    <span>Login Harian</span>
                    <strong><?= $total_login; ?> × 1 = <?= $login_score; ?> poin</strong>
                </div>

                <div class="analytics-row">
                    <span>Total Skor Sistem</span>
                    <strong><?= $total_system_score; ?> poin</strong>
                </div>

            </div>

        </div>

        <!-- ================================= -->
        <!-- KONDISI SISTEM -->
        <!-- ================================= -->

        <div class="system-status">

            <div class="formula-item">

                Status Sistem :

                <span class="badge <?= $system_badge; ?>">

                    <?= $system_status; ?>

                </span>

            </div>

            <div class="range-chart-wrap">

                <div class="range-chart">

                    <canvas id="systemRangeChart"></canvas>

                </div>

                <div class="range-info">

                    <div class="range-row">
                        0 - 49 poin = Kurang Aktif
                    </div>

                    <div class="range-row">
                        50 - 149 poin = Cukup Aktif
                    </div>

                    <div class="range-row">
                        150 - 299 poin = Aktif
                    </div>

                    <div class="range-row">
                        ≥ 300 poin = Sangat Aktif
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ================================= -->
    <!-- TABLE -->
    <!-- ================================= -->

    <div class="card">
    <div class="accordion-card">
        
        <div class="accordion-header active">
            Daftar Peringkat Aktivitas Guru
        </div>
        
        <div class="accordion-body" style="display: block;">

        <form method="GET">

            <div class="search-box">

                <input
                    type="text"
                    name="search"
                    placeholder="Cari guru atau sekolah..."
                    value="<?= htmlspecialchars($search); ?>"
                >

                <button type="submit">
                    Cari
                </button>

            </div>

        </form>

                <?php
        $monitoring_data = [];
        while($row = mysqli_fetch_assoc($monitoring)){
            $monitoring_data[] = $row;
        }
        $chunks = array_chunk($monitoring_data, 5); // Pisahkan 5 baris per halaman
        ?>

        <div class="carousel-wrapper">
            <button class="carousel-btn prev" onclick="scrollCarousel(-1, 'guruTableCarousel')">&#10094;</button>
            <button class="carousel-btn next" onclick="scrollCarousel(1, 'guruTableCarousel')">&#10095;</button>
            
            <div class="table-carousel-list" id="guruTableCarousel">
                <?php
                $no = 1;
                if(count($chunks) > 0){
                    foreach($chunks as $chunk){
                ?>
                <div class="table-carousel-item">
                    <div class="table-responsive">

                        <table>

                            <thead>

                                <tr>

                                    <th>Rank</th>
                                    <th>Guru</th>
                                    <th>Sekolah</th>
                                    <th>Upload</th>
                                    <th>Download</th>
                                    <th>Login</th>
                                    <th>Skor Aktivitas</th>
                                    <th>Status</th>
                                    <th>Aktivitas Terakhir</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            foreach($chunk as $row){

                    $skor =
                    $row['skor_aktivitas'];

                    // =================================
                    // STATUS GURU
                    // =================================

                    if($skor >= 50){

                        $status =
                        "Sangat Aktif";

                        $badge =
                        "sangat-aktif";

                    }

                    elseif($skor >= 20){

                        $status =
                        "Aktif";

                        $badge =
                        "aktif";

                    }

                    elseif($skor >= 8){

                        $status =
                        "Cukup Aktif";

                        $badge =
                        "cukup";

                    }

                    else{

                        $status =
                        "Kurang Aktif";

                        $badge =
                        "rendah";

                    }

                    $persen =
                    min(100, $skor);

                ?>

                <tr>

                    <td>

                        <strong>
                            #<?= $no++; ?>
                        </strong>

                    </td>

                    <td>

                        <strong>

                            <?= htmlspecialchars(
                                $row['full_name']
                            ); ?>

                        </strong>

                        <br>

                        <small>

                            <?= htmlspecialchars(
                                $row['email']
                            ); ?>

                        </small>

                        <div class="detail-aktivitas">

                            Upload :
                            <?= $row['total_upload']; ?>

                            × 7 poin

                            <br>

                            Download :
                            <?= $row['total_download']; ?>

                            × 2 poin

                            <br>

                            Login :
                            <?= $row['total_login']; ?>

                            × 1 poin

                        </div>

                    </td>

                    <td>

                        <?= htmlspecialchars(
                            $row['school_name']
                        ); ?>

                    </td>

                    <td>

                        <?= $row['total_upload']; ?>

                    </td>

                    <td>

                        <?= $row['total_download']; ?>

                    </td>

                    <td>

                        <?= $row['total_login']; ?>

                    </td>

                    <td width="220">

                        <strong>

                            <?= $skor; ?>

                        </strong>

                        <div class="progress">

                            <div
                                class="progress-fill"
                                style="width:<?= $persen; ?>%;"
                            ></div>

                        </div>

                    </td>

                    <td>

                        <span
                            class="badge <?= $badge; ?>"
                        >

                            <?= $status; ?>

                        </span>

                    </td>

                    <td>

                        <?php

                        if(

                            $row['aktivitas_terakhir']

                            &&

                            $row['aktivitas_terakhir']
                            != '2000-01-01 00:00:00'

                        ){

                            echo date(

                                'd M Y H:i',

                                strtotime(
                                    $row['aktivitas_terakhir']
                                )

                            );

                        }

                        else{

                            echo
                            "Belum Ada Aktivitas";

                        }

                        ?>

                    </td>

                </tr>

                <?php } ?>

                            </tbody>

                        </table>

                    </div>
                </div>
                <?php } } else { ?>
                <div class="table-carousel-item">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Guru</th>
                                    <th>Sekolah</th>
                                    <th>Upload</th>
                                    <th>Download</th>
                                    <th>Login</th>
                                    <th>Skor Aktivitas</th>
                                    <th>Status</th>
                                    <th>Aktivitas Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:30px; color:#7f8c8d;">Tidak ada data guru yang ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        </div>

</div>

<script>

const learningAnalyticsCtx =
document.getElementById('learningAnalyticsChart');

new Chart(learningAnalyticsCtx, {
    type:'pie',
    data:{
        labels:[
            'Upload Materi',
            'Download Materi',
            'Login Harian'
        ],
        datasets:[{
            data:[
                <?= $upload_score; ?>,
                <?= $download_score; ?>,
                <?= $login_score; ?>
            ],
            backgroundColor:[
                '#3498db',
                '#27ae60',
                '#f39c12'
            ],
            borderColor:'#ffffff',
            borderWidth:3
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'bottom'
            },
            tooltip:{
                callbacks:{
                    label:function(context){
                        return context.label + ': ' + context.raw + ' poin';
                    }
                }
            }
        }
    }
});

const systemRangeCtx =
document.getElementById('systemRangeChart');

new Chart(systemRangeCtx, {
    type:'pie',
    data:{
        labels:[
            'Kurang Aktif',
            'Cukup Aktif',
            'Aktif',
            'Sangat Aktif'
        ],
        datasets:[{
            data:[
                50,
                100,
                150,
                100
            ],
            backgroundColor:[
                '#e74c3c',
                '#f39c12',
                '#1abc9c',
                '#27ae60'
            ],
            borderColor:'#ffffff',
            borderWidth:3
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'bottom'
            },
            tooltip:{
                callbacks:{
                    label:function(context){
                        const ranges = [
                            '0 - 49 poin',
                            '50 - 149 poin',
                            '150 - 299 poin',
                            '>= 300 poin'
                        ];

                        return context.label + ': ' + ranges[context.dataIndex];
                    }
                }
            }
        }
    }
});

function scrollCarousel(direction, id) {
    const container = document.getElementById(id);
    const scrollAmount = container.clientWidth;
    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
}

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

</script>

</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// Mobile Hamburger Toggle
const hamburger = document.getElementById('hamburger-toggle');
const sidebar = document.getElementById('sidebar-menu');
if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

// Handler Cetak / Unduh PDF
function handlePrintOrDownload() {
    if (window.innerWidth <= 992) {
        const btn = document.querySelector('.btn-print');
        const originalText = btn.innerHTML;
        btn.innerHTML = "⏳ Menyiapkan PDF...";
        btn.disabled = true;
        
        const mobileNav = document.querySelector('.mobile-nav');
        if(mobileNav) mobileNav.style.display = 'none';
        btn.style.display = 'none';
        
        const element = document.querySelector('.main-content');
        
        var opt = {
          margin:       5,
          filename:     'Monitoring_Aktivitas_Guru.pdf',
          image:        { type: 'jpeg', quality: 0.95 },
          html2canvas:  { scale: 1.5, useCORS: true }, // Scale diturunkan sedikit untuk mencegah memori HP Vivo/Android penuh
          jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Timeout jika HP ngelag atau gagal
        let fallbackTimer = setTimeout(() => {
            if(mobileNav) mobileNav.style.display = 'flex';
            btn.style.display = 'flex';
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert("Maaf, browser HP Anda (Vivo/Oppo/Bawaan) memblokir unduhan otomatis atau memori tidak cukup. Kami akan menggunakan mode cetak bawaan.");
            window.print();
        }, 15000); // 15 detik timeout

        try {
            html2pdf().set(opt).from(element).save().then(() => {
                clearTimeout(fallbackTimer);
                if(mobileNav) mobileNav.style.display = 'flex';
                btn.style.display = 'flex';
                btn.innerHTML = originalText;
                btn.disabled = false;
            }).catch((err) => {
                clearTimeout(fallbackTimer);
                console.error("PDF Error: ", err);
                if(mobileNav) mobileNav.style.display = 'flex';
                btn.style.display = 'flex';
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert("Gagal membuat PDF. Mengalihkan ke mode cetak bawaan.");
                window.print();
            });
        } catch(e) {
            clearTimeout(fallbackTimer);
            if(mobileNav) mobileNav.style.display = 'flex';
            btn.style.display = 'flex';
            btn.innerHTML = originalText;
            btn.disabled = false;
            window.print();
        }
    } else {
        window.print();
    }
}
</script>

</body>
</html>
