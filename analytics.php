<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

include 'config/database.php';

if(!isset($_SESSION['login'])){
    header("Location:index.php");
    exit;
}

$POINT_UPLOAD   = 7;
$POINT_DOWNLOAD = 2;
$POINT_LOGIN    = 1;

$cek_photo_column = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_photo'");
if($cek_photo_column && mysqli_num_rows($cek_photo_column) == 0){
    mysqli_query($conn, "ALTER TABLE users ADD profile_photo VARCHAR(255) NULL");
}

function single_value($conn, $sql, $key, $default = 0){
    $query = mysqli_query($conn, $sql);

    if(!$query){
        die("Query analytics gagal: " . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($query);

    return isset($row[$key]) ? $row[$key] : $default;
}

function result_query($conn, $sql){
    $query = mysqli_query($conn, $sql);

    if(!$query){
        die("Query analytics gagal: " . mysqli_error($conn));
    }

    return $query;
}

function e($value){
    return htmlspecialchars((string)(isset($value) ? $value : ''), ENT_QUOTES, 'UTF-8');
}

function percent_value($value, $total){
    if($total <= 0){
        return 0;
    }

    return min(100, ($value / $total) * 100);
}

function format_datetime_id($value){
    if(empty($value)){
        return '-';
    }

    $timestamp = strtotime($value);

    if($timestamp === false){
        return $value;
    }

    return date('d-m-Y H:i', $timestamp);
}

function school_intelligence($school, $point_upload, $point_download, $point_login){
    $total_guru = max(1, (int)$school['total_guru']);
    $total_upload = (int)$school['total_upload'];
    $total_download = (int)$school['total_download'];
    $total_login = (int)$school['total_login'];
    $score = (int)$school['total_score'];

    $score_per_guru = $score / $total_guru;
    $upload_per_guru = $total_upload / $total_guru;
    $login_per_guru = $total_login / $total_guru;
    $download_per_upload = $total_upload > 0 ? $total_download / $total_upload : 0;

    if($score == 0){
        return [
            'status' => 'Belum Teraktivasi',
            'class' => 'danger',
            'priority' => 'Prioritas tinggi',
            'analysis' => 'Belum ada jejak upload, download, atau login guru yang cukup untuk membentuk pola kolaborasi digital sekolah.',
            'recommendation' => 'Mulai dengan aktivasi akun guru, jadwalkan login rutin, lalu targetkan minimal satu materi approved dari sekolah ini.',
            'focus' => 'Aktivasi awal',
            'score_per_guru' => $score_per_guru,
            'upload_per_guru' => $upload_per_guru,
            'login_per_guru' => $login_per_guru,
            'download_per_upload' => $download_per_upload
        ];
    }

    if($total_upload == 0 && $total_login > 0){
        return [
            'status' => 'Memerlukan Konversi Aktivitas',
            'class' => 'warning',
            'priority' => 'Prioritas tinggi',
            'analysis' => 'Guru sudah mulai masuk ke platform, tetapi aktivitas tersebut belum berubah menjadi kontribusi materi.',
            'recommendation' => 'Dorong guru yang sudah login untuk mengunggah perangkat pembelajaran, soal latihan, atau materi pembelajaran sederhana.',
            'focus' => 'Login menjadi upload',
            'score_per_guru' => $score_per_guru,
            'upload_per_guru' => $upload_per_guru,
            'login_per_guru' => $login_per_guru,
            'download_per_upload' => $download_per_upload
        ];
    }

    if($total_upload > 0 && $total_download == 0){
        return [
            'status' => 'Memerlukan Distribusi Materi',
            'class' => 'info',
            'priority' => 'Prioritas sedang',
            'analysis' => 'Sekolah sudah menghasilkan materi, namun materi belum menunjukkan dampak pemanfaatan melalui download.',
            'recommendation' => 'Review judul, kategori, dan relevansi materi, lalu promosikan materi ke guru MGMP lain agar digunakan.',
            'focus' => 'Optimasi pemanfaatan',
            'score_per_guru' => $score_per_guru,
            'upload_per_guru' => $upload_per_guru,
            'login_per_guru' => $login_per_guru,
            'download_per_upload' => $download_per_upload
        ];
    }

    if($score_per_guru >= 20 && $download_per_upload >= 2){
        return [
            'status' => 'Role Model Sekolah',
            'class' => 'success',
            'priority' => 'Pertahankan',
            'analysis' => 'Aktivitas sekolah kuat dan materi yang dibagikan mulai dimanfaatkan oleh pengguna lain.',
            'recommendation' => 'Pertahankan ritme kontribusi, jadikan guru aktif sebagai mentor, dan dokumentasikan praktik baik untuk sekolah lain.',
            'focus' => 'Penguatan praktik baik',
            'score_per_guru' => $score_per_guru,
            'upload_per_guru' => $upload_per_guru,
            'login_per_guru' => $login_per_guru,
            'download_per_upload' => $download_per_upload
        ];
    }

    if($score_per_guru >= 10){
        return [
            'status' => 'Sekolah Aktif',
            'class' => 'primary',
            'priority' => 'Prioritas rendah',
            'analysis' => 'Aktivitas sekolah sudah berjalan, tetapi masih dapat ditingkatkan melalui konsistensi upload dan pemerataan guru aktif.',
            'recommendation' => 'Tetapkan target kontribusi bulanan per guru dan pantau materi yang paling banyak digunakan.',
            'focus' => 'Konsistensi kontribusi',
            'score_per_guru' => $score_per_guru,
            'upload_per_guru' => $upload_per_guru,
            'login_per_guru' => $login_per_guru,
            'download_per_upload' => $download_per_upload
        ];
    }

    return [
        'status' => 'Sekolah Mulai Berkembang',
        'class' => 'warning',
        'priority' => 'Prioritas sedang',
        'analysis' => 'Sekolah sudah memiliki sinyal aktivitas, namun intensitasnya masih rendah dibanding jumlah guru.',
        'recommendation' => 'Fokus pada rutinitas login mingguan dan target upload bertahap agar skor partisipasi naik secara stabil.',
        'focus' => 'Peningkatan intensitas',
        'score_per_guru' => $score_per_guru,
        'upload_per_guru' => $upload_per_guru,
        'login_per_guru' => $login_per_guru,
        'download_per_upload' => $download_per_upload
    ];
}

$total_guru = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role_id = 2
", "total");

$total_materi = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
", "total");

$total_upload_approved = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
    WHERE status = 'approved'
", "total");

$total_upload_approved_internal = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
    WHERE status = 'approved'
    AND user_id IS NOT NULL
", "total");

$total_pending = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
    WHERE status = 'pending'
", "total");

$total_rejected = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
    WHERE status = 'rejected'
", "total");

$total_contributor = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.user_id IS NULL OR u.role_id = 4
", "total");

$total_download = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM downloads d
    INNER JOIN users u ON d.user_id = u.id
    WHERE u.role_id = 2
", "total");

$total_login = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM login_activity l
    INNER JOIN users u ON l.user_id = u.id
    WHERE u.role_id = 2
", "total");

$total_internal_upload = single_value($conn, "
    SELECT COUNT(*) AS total
    FROM materials
    INNER JOIN users ON materials.user_id = users.id
    WHERE users.role_id = 2
    AND materials.status = 'approved'
", "total");

$total_system_score =
    ($total_internal_upload * $POINT_UPLOAD) +
    ($total_download * $POINT_DOWNLOAD) +
    ($total_login * $POINT_LOGIN);

$materi_populer = result_query($conn, "
    SELECT
        materials.title,
        materials.category,
        materials.grade_level,
        materials.created_at,
        COALESCE(users.full_name, materials.contributor_name, 'Contributor External') AS author_name,
        COALESCE(users.school_name, materials.contributor_institution, '-') AS institution_name,
        COUNT(downloads.id) AS total_download
    FROM materials
    LEFT JOIN downloads ON materials.id = downloads.material_id
    LEFT JOIN users ON materials.user_id = users.id
    WHERE materials.status = 'approved'
    AND materials.user_id IS NOT NULL
    GROUP BY
        materials.id,
        materials.title,
        materials.category,
        materials.grade_level,
        materials.created_at,
        author_name,
        institution_name
    ORDER BY total_download DESC, materials.created_at DESC
    LIMIT 5
");

$leaderboard = result_query($conn, "
    SELECT
        users.id,
        users.full_name,
        users.profile_photo,
        COALESCE(NULLIF(users.school_name, ''), 'Sekolah belum diisi') AS school_name,
        COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) AS total_upload,
        COUNT(DISTINCT downloads.id) AS total_download,
        COUNT(DISTINCT login_activity.id) AS total_login,
        (
            COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * $POINT_UPLOAD
            + COUNT(DISTINCT downloads.id) * $POINT_DOWNLOAD
            + COUNT(DISTINCT login_activity.id) * $POINT_LOGIN
        ) AS nilai_partisipasi
    FROM users
    LEFT JOIN materials ON users.id = materials.user_id
    LEFT JOIN downloads ON materials.id = downloads.material_id
    LEFT JOIN login_activity ON users.id = login_activity.user_id
    WHERE users.role_id = 2
    GROUP BY users.id, users.full_name, users.school_name, users.profile_photo
    HAVING nilai_partisipasi > 7
    ORDER BY nilai_partisipasi DESC, total_upload DESC, total_download DESC, total_login DESC, users.full_name ASC
    LIMIT 5
");

$guru_tidak_aktif = result_query($conn, "
    SELECT
        users.id,
        users.full_name,
        users.profile_photo,
        COALESCE(NULLIF(users.school_name, ''), 'Sekolah belum diisi') AS school_name,
        (
            COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * $POINT_UPLOAD
            + COUNT(DISTINCT downloads.id) * $POINT_DOWNLOAD
            + COUNT(DISTINCT login_activity.id) * $POINT_LOGIN
        ) AS nilai_partisipasi
    FROM users
    LEFT JOIN materials ON users.id = materials.user_id
    LEFT JOIN downloads ON materials.id = downloads.material_id
    LEFT JOIN login_activity ON users.id = login_activity.user_id
    WHERE users.role_id = 2
    GROUP BY users.id, users.full_name, users.school_name, users.profile_photo
    HAVING nilai_partisipasi = 0
    ORDER BY users.full_name ASC
");

$grand_total = single_value($conn, "
    SELECT SUM(nilai_total) AS total
    FROM (
        SELECT
            (
                COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * $POINT_UPLOAD
                + COUNT(DISTINCT downloads.id) * $POINT_DOWNLOAD
                + COUNT(DISTINCT login_activity.id) * $POINT_LOGIN
            ) AS nilai_total
        FROM users
        LEFT JOIN materials ON users.id = materials.user_id
        LEFT JOIN downloads ON materials.id = downloads.material_id
        LEFT JOIN login_activity ON users.id = login_activity.user_id
        WHERE users.role_id = 2
        GROUP BY users.id
    ) AS skor_guru
", "total");

$school_analytics = result_query($conn, "
    SELECT 
        school_name,
        COUNT(id) AS total_guru,
        SUM(total_upload) AS total_upload,
        SUM(total_download) AS total_download,
        SUM(total_login) AS total_login,
        SUM(skor_guru) AS total_score
    FROM (
        SELECT 
            users.id,
            COALESCE(NULLIF(users.school_name, ''), 'Sekolah belum diisi') AS school_name,
            COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) AS total_upload,
            COUNT(DISTINCT downloads.id) AS total_download,
            COUNT(DISTINCT login_activity.id) AS total_login,
            (
                COUNT(DISTINCT CASE WHEN materials.status = 'approved' THEN materials.id END) * $POINT_UPLOAD
                + COUNT(DISTINCT downloads.id) * $POINT_DOWNLOAD
                + COUNT(DISTINCT login_activity.id) * $POINT_LOGIN
            ) AS skor_guru
        FROM users
        LEFT JOIN materials ON users.id = materials.user_id
        LEFT JOIN downloads ON materials.id = downloads.material_id
        LEFT JOIN login_activity ON users.id = login_activity.user_id
        WHERE users.role_id = 2
        GROUP BY users.id, users.school_name
    ) AS guru_stats
    GROUP BY school_name
    ORDER BY total_score DESC, total_upload DESC, total_download DESC, school_name ASC
");

$trend_query = result_query($conn, "
    SELECT
        bulan_key,
        DATE_FORMAT(MIN(tanggal), '%b %Y') AS bulan,
        SUM(total_upload) AS total_upload,
        SUM(total_download) AS total_download,
        SUM(total_login) AS total_login
    FROM (
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS bulan_key,
            MIN(created_at) AS tanggal,
            COUNT(*) AS total_upload,
            0 AS total_download,
            0 AS total_login
        FROM materials
        WHERE status = 'approved'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')

        UNION ALL

        SELECT
            DATE_FORMAT(downloaded_at, '%Y-%m') AS bulan_key,
            MIN(downloaded_at) AS tanggal,
            0 AS total_upload,
            COUNT(*) AS total_download,
            0 AS total_login
        FROM downloads
        GROUP BY DATE_FORMAT(downloaded_at, '%Y-%m')

        UNION ALL

        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS bulan_key,
            MIN(created_at) AS tanggal,
            0 AS total_upload,
            0 AS total_download,
            COUNT(*) AS total_login
        FROM login_activity
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ) AS trend
    GROUP BY bulan_key
    ORDER BY bulan_key ASC
");

$label_trend = [];
$data_upload = [];
$data_download = [];
$data_login = [];

while($row = mysqli_fetch_assoc($trend_query)){
    $label_trend[] = $row['bulan'];
    $data_upload[] = (int)$row['total_upload'];
    $data_download[] = (int)$row['total_download'];
    $data_login[] = (int)$row['total_login'];
}

// =====================================
// INTELLIGENT ANALYTICS TREN BULANAN
// =====================================
$trend_analysis = [
    'status' => 'Belum Cukup Data',
    'class' => 'info',
    'insight' => 'Belum ada data aktivitas bulanan yang cukup untuk dianalisis.',
    'recommendation' => 'Terus dorong partisipasi guru agar data tren dapat terbentuk.'
];

$num_months = count($label_trend);
if($num_months == 1) {
    $trend_analysis = [
        'status' => 'Fase Awal',
        'class' => 'primary',
        'insight' => 'Aktivitas terekam pada bulan <b>' . $label_trend[0] . '</b> sebagai titik awal partisipasi.',
        'recommendation' => 'Pertahankan dan tingkatkan partisipasi bulan depan untuk melihat tren pertumbuhan.'
    ];
} elseif ($num_months >= 2) {
    $last_idx = $num_months - 1;
    $prev_idx = $num_months - 2;

    $last_total = $data_upload[$last_idx] + $data_download[$last_idx] + $data_login[$last_idx];
    $prev_total = $data_upload[$prev_idx] + $data_download[$prev_idx] + $data_login[$prev_idx];

    $diff = $last_total - $prev_total;
    $percent_change = ($prev_total > 0) ? round((abs($diff) / $prev_total) * 100, 1) : 100;

    if($diff > 0) {
        $trend_analysis = [
            'status' => 'Meningkat (+'.$percent_change.'%)',
            'class' => 'success',
            'insight' => "Terdapat peningkatan total aktivitas pada bulan <b>{$label_trend[$last_idx]}</b> ({$last_total} aktivitas) dibandingkan <b>{$label_trend[$prev_idx]}</b> ({$prev_total} aktivitas).",
            'recommendation' => 'Momentum yang sangat baik. Pertahankan strategi yang ada dan berikan apresiasi atas kontribusi para guru.'
        ];
    } elseif($diff < 0) {
        $trend_analysis = [
            'status' => 'Menurun (-'.$percent_change.'%)',
            'class' => 'danger',
            'insight' => "Terjadi penurunan total aktivitas pada bulan <b>{$label_trend[$last_idx]}</b> ({$last_total} aktivitas) dibandingkan <b>{$label_trend[$prev_idx]}</b> ({$prev_total} aktivitas).",
            'recommendation' => 'Lakukan evaluasi dan berikan motivasi melalui pengumuman dashboard agar guru kembali aktif di platform.'
        ];
    } else {
        $trend_analysis = [
            'status' => 'Stabil',
            'class' => 'warning',
            'insight' => "Aktivitas pada bulan <b>{$label_trend[$last_idx]}</b> stabil ({$last_total} aktivitas), sama dengan bulan <b>{$label_trend[$prev_idx]}</b>.",
            'recommendation' => 'Tingkatkan target dan promosi materi bulan ini agar grafik tidak stagnan dan kembali menunjukkan tren positif.'
        ];
    }
}

// =====================================
// DATA REQUEST INTERNAL SEKOLAH
// =====================================
$school_requests = [];
$all_requests_query = result_query($conn, "
    SELECT 
        COALESCE(NULLIF(u.school_name, ''), 'Sekolah belum diisi') AS school_name,
        req.jenis_request,
        req.deskripsi,
        COUNT(req.id) AS jumlah_request,
        GROUP_CONCAT(u.full_name SEPARATOR ', ') AS nama_guru
    FROM material_requests req
    JOIN users u ON req.user_id = u.id
    WHERE req.status != 'selesai'
    GROUP BY COALESCE(NULLIF(u.school_name, ''), 'Sekolah belum diisi'), req.jenis_request, req.deskripsi
    ORDER BY jumlah_request DESC, MAX(req.created_at) ASC
");

while($req = mysqli_fetch_assoc($all_requests_query)){
    $school = $req['school_name'];
    
    if(!isset($school_requests[$school])){
        $school_requests[$school] = [];
    }
    
    $detail = $req['deskripsi'];
    if (preg_match('/Target Kelas:\s*(.*?)\r?\nDetail Request:\s*(.*)/s', $req['deskripsi'], $matches)) {
        $detail = trim($matches[1]) . " - " . trim($matches[2]);
    }

    $school_requests[$school][] = [
        'jenis' => $req['jenis_request'],
        'detail' => $detail,
        'jumlah' => $req['jumlah_request'],
        'nama_guru' => $req['nama_guru']
    ];
}

// =====================================
// DATA KOLABORASI LINTAS SEKOLAH
// =====================================
$cross_school_data = [];
$cross_school_query = result_query($conn, "
    SELECT 
        CASE 
            WHEN materials.user_id IS NULL THEN 'Contributor External'
            WHEN uploader_user.role_id = 4 THEN 'Contributor External'
            ELSE COALESCE(NULLIF(uploader_user.school_name, ''), 'Sekolah belum diisi')
        END AS uploader_school,
        CASE 
            WHEN downloader_user.id IS NULL THEN 'Guest/Sistem'
            WHEN downloader_user.role_id = 4 THEN 'Contributor External'
            ELSE COALESCE(NULLIF(downloader_user.school_name, ''), 'Sekolah belum diisi')
        END AS downloader_school,
        COUNT(downloads.id) AS total_interaction
    FROM downloads
    JOIN materials ON downloads.material_id = materials.id
    LEFT JOIN users uploader_user ON materials.user_id = uploader_user.id
    LEFT JOIN users downloader_user ON downloads.user_id = downloader_user.id
    GROUP BY uploader_school, downloader_school
");

while($row = mysqli_fetch_assoc($cross_school_query)){
    $u_school = $row['uploader_school'];
    $d_school = $row['downloader_school'];
    $count = (int)$row['total_interaction'];
    
    if(!isset($cross_school_data[$u_school])) {
        $cross_school_data[$u_school] = ['internal' => 0, 'ekspor' => 0, 'impor' => 0, 'ekspor_detail' => [], 'impor_detail' => []];
    }
    if(!isset($cross_school_data[$d_school])) {
        $cross_school_data[$d_school] = ['internal' => 0, 'ekspor' => 0, 'impor' => 0, 'ekspor_detail' => [], 'impor_detail' => []];
    }
    
    if($u_school === $d_school) {
        $cross_school_data[$u_school]['internal'] += $count;
    } else {
        $cross_school_data[$u_school]['ekspor'] += $count;
        if(!isset($cross_school_data[$u_school]['ekspor_detail'][$d_school])) {
            $cross_school_data[$u_school]['ekspor_detail'][$d_school] = 0;
        }
        $cross_school_data[$u_school]['ekspor_detail'][$d_school] += $count;

        $cross_school_data[$d_school]['impor'] += $count;
        if(!isset($cross_school_data[$d_school]['impor_detail'][$u_school])) {
            $cross_school_data[$d_school]['impor_detail'][$u_school] = 0;
        }
        $cross_school_data[$d_school]['impor_detail'][$u_school] += $count;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics MGMP</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:Arial, sans-serif;
            background:#f4f6f9;
            color:#25313f;
        }

        .wrapper{
            display:flex;
            min-height:100vh;
        }
        .sidebar{
            width:250px; height:100vh; background:#2c3e50;
            position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0;
        }
        .sidebar .logo{
            color:white; text-align:center; padding:30px;
            font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1);
        }
        .sidebar .menu a{
            display:block; color:white; text-decoration:none;
            padding:18px 25px; transition:0.3s; font-size:16px;
        }
        .sidebar .menu a:hover{
            background:#34495e;
        }

        .content{
            width:100%;
            padding:30px;
        }

        .hero{
            background:linear-gradient(135deg,#2c3e50,#1abc9c);
            color:white;
            padding:30px;
            border-radius:18px;
            margin-bottom:24px;
            position:relative;
        }

        .hero h1{
            margin:0 0 10px;
            font-size:36px;
        }

        .hero p{
            margin:0;
            color:#eef8f6;
            line-height:1.7;
        }

        .btn-print {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.5);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-print:hover {
            background: white;
            color: #2c3e50;
        }

        .summary-grid{
            display:grid;
            grid-template-columns:repeat(5,1fr);
            gap:18px;
            margin-bottom:24px;
        }

        .card{
            background:white;
            border-radius:18px;
            padding:24px;
            box-shadow:0 0 12px rgba(0,0,0,0.07);
        }

        .metric h3{
            margin:0;
            color:#6b7785;
            font-size:14px;
            font-weight:bold;
        }

        .metric h1{
            margin:12px 0 0;
            color:#25313f;
            font-size:34px;
        }

        .metric small{
            display:block;
            margin-top:8px;
            color:#7f8c8d;
            line-height:1.5;
        }

        .section-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:20px;
        }

        .full{
            grid-column:1 / -1;
        }

        .card h2{
            margin:0 0 18px;
            font-size:22px;
            color:#25313f;
        }

        .accordion-card{
            background:white;
            border-radius:18px;
            box-shadow:0 0 12px rgba(0,0,0,0.07);
        }

        .accordion-header{
            padding:24px;
            font-size:22px;
            font-weight:bold;
            color:#25313f;
            cursor:pointer;
            display:flex;
            justify-content:space-between;
            align-items:center;
            user-select:none;
            transition:0.3s;
            border-radius:18px;
        }

        .accordion-header:hover{
            background:#fbfcfd;
        }

        .accordion-header.active{
            border-bottom:1px solid #edf0f2;
            border-bottom-left-radius:0;
            border-bottom-right-radius:0;
        }

        .accordion-header::after{
            content:'▼';
            font-size:16px;
            transition:transform 0.3s ease;
        }

        .accordion-header.active::after{
            transform:rotate(-180deg);
        }

        .accordion-body{
            padding:24px;
            display:none;
        }

        .formula{
            display:flex;
            flex-wrap:wrap;
            gap:12px;
        }

        .chip{
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:#eef5f8;
            color:#2c3e50;
            padding:9px 13px;
            border-radius:8px;
            font-size:13px;
            font-weight:bold;
        }

        .item{
            border:1px solid #edf0f2;
            border-radius:12px;
            padding:20px;
            background:#fbfcfd;
            flex:0 0 100%;
            scroll-snap-align:start;
            box-sizing:border-box;
            display:flex;
            flex-direction:column;
            min-height:280px;
            transition:transform 0.3s ease, box-shadow 0.3s ease;
        }

        .item:hover{
            transform:translateY(-5px);
            box-shadow:0 8px 20px rgba(0,0,0,0.08);
        }
        
        .ranking{
            border:1px solid #edf0f2;
            border-radius:12px;
            padding:20px;
            background:#fbfcfd;
            box-sizing:border-box;
            flex:0 0 100%;
            scroll-snap-align:start;
            display:flex;
            flex-direction:column;
            transition:transform 0.3s ease, box-shadow 0.3s ease;
        }

        .ranking:hover{
            transform:translateY(-5px);
            box-shadow:0 8px 20px rgba(0,0,0,0.08);
        }

        .title{
            font-size:17px;
            font-weight:bold;
            color:#25313f;
            line-height:1.4;
        }

        .sub{
            margin-top:6px;
            color:#66737f;
            line-height:1.6;
            font-size:14px;
        }

        .badges{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-top:auto;
        }

        .badge{
            display:inline-block;
            background:#3498db;
            color:white;
            padding:7px 10px;
            border-radius:8px;
            font-size:12px;
            font-weight:bold;
        }

        .badge.green{
            background:#27ae60;
        }

        .badge.purple{
            background:#8e44ad;
        }

        .req-card-item {
            background:#f4f9fd;
            border:1px solid #dceef9;
            padding:12px;
            border-radius:8px;
            font-size:13px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            gap:8px;
            flex:0 0 calc(25% - 9px);
        }

        .ranking-top{
            display:flex;
            justify-content:space-between;
            gap:18px;
        }

        .person{
            display:flex;
            gap:14px;
            min-width:0;
        }

        .rank{
            width:46px;
            height:46px;
            flex:0 0 46px;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            color:white;
            font-size:18px;
            font-weight:bold;
            background:#3498db;
        }

        .rank.gold{
            background:#f1c40f;
        }

        .rank.silver{
            background:#95a5a6;
        }

        .rank.bronze{
            background:#d35400;
        }

        .score{
            color:#27ae60;
            font-size:28px;
            font-weight:bold;
            text-align:right;
            white-space:nowrap;
        }

        .detail{
            margin-top:8px;
            color:#66737f;
            line-height:1.8;
            font-size:13px;
        }

        .status{
            display:inline-block;
            margin-top:10px;
            padding:7px 10px;
            border-radius:8px;
            color:white;
            font-size:12px;
            font-weight:bold;
        }

        .danger{
            background:#e74c3c;
        }

        .warning{
            background:#f39c12;
        }

        .info{
            background:#3498db;
        }

        .primary{
            background:#1abc9c;
        }

        .success{
            background:#27ae60;
        }

        .progress-info{
            display:flex;
            justify-content:space-between;
            margin:auto 0 7px;
            color:#66737f;
            font-size:13px;
        }

        .progress-bar{
            width:100%;
            height:10px;
            background:#e9eef1;
            border-radius:8px;
            overflow:hidden;
        }

        .progress-fill{
            height:100%;
            background:linear-gradient(90deg,#1abc9c,#27ae60);
        }

        .list-bars{
            display:grid;
            gap:14px;
        }

        .bar-row{
            display:grid;
            grid-template-columns:160px 1fr 48px;
            align-items:center;
            gap:12px;
            color:#55616d;
            font-size:14px;
        }

        .mini-bar{
            height:9px;
            border-radius:8px;
            background:#e9eef1;
            overflow:hidden;
        }

        .mini-bar span{
            display:block;
            height:100%;
            background:#3498db;
        }

        .empty{
            color:#7f8c8d;
            line-height:1.7;
            padding:14px 0;
        }

        .chart-box{
            min-height:320px;
        }

        .carousel-wrapper {
            position: relative;
            width: 100%;
        }
        
        .carousel-btn {
            position: absolute;
            top: 105px;
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

        .ai-school-list{
            display:flex;
            overflow-x:auto;
            gap:20px;
            padding-bottom:15px;
            -webkit-overflow-scrolling:touch;
        }
        .ai-school-list::-webkit-scrollbar {
            height: 8px;
        }
        .ai-school-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .ai-school-list::-webkit-scrollbar-thumb {
            background: #bdc3c7;
            border-radius: 10px;
        }
        .ai-school-list::-webkit-scrollbar-thumb:hover {
            background: #95a5a6;
        }

        .ai-school{
            border:1px solid #edf0f2;
            border-radius:12px;
            padding:20px;
            background:#fbfcfd;
            flex:0 0 100%;
            box-sizing:border-box;
            transition:transform 0.3s ease, box-shadow 0.3s ease;
        }

        .ai-school:hover{
            transform:translateY(-5px);
            box-shadow:0 8px 20px rgba(0,0,0,0.08);
        }

        .ai-school-head{
            display:grid;
            grid-template-columns:auto 1fr auto;
            gap:16px;
            align-items:flex-start;
        }

.ai-school-head .title {
    font-size: 24px;
    padding-top: 6px;
}

        .ai-score{
            text-align:right;
        }

        .ai-score strong{
            display:block;
            color:#27ae60;
            font-size:30px;
        }

        .ai-score span{
            color:#66737f;
            font-size:12px;
            font-weight:bold;
        }

        .ai-grid{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:12px;
            margin:16px 0;
        }

        .ai-metric{
            background:white;
            border:1px solid #edf0f2;
            border-radius:10px;
            padding:12px;
        }

        .ai-metric span{
            display:block;
            color:#66737f;
            font-size:12px;
            margin-bottom:6px;
        }

        .ai-metric strong{
            color:#25313f;
            font-size:17px;
        }

        .ai-insight{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
            margin-top:14px;
        }

        .ai-box{
            background:white;
            border:1px solid #edf0f2;
            border-radius:10px;
            padding:14px;
            color:#55616d;
            line-height:1.7;
            font-size:14px;
        }

        .ai-box strong{
            display:block;
            color:#25313f;
            margin-bottom:5px;
        }
        
        .box-spi-analysis {
            background: linear-gradient(135deg, #ffffff 0%, #f4fdf8 100%);
            border-color: #dff5e8;
        }
        
        .box-spi-rec {
            background: linear-gradient(135deg, #f4fdf8 0%, #e6f9ed 100%);
            border-color: #c3eed6;
        }
        
        .box-ksi-analysis {
            background: linear-gradient(135deg, #ffffff 0%, #f4f9fd 100%);
            border-color: #dceef9;
        }
        
        .box-ksi-rec {
            background: linear-gradient(135deg, #f4f9fd 0%, #e3f1fa 100%);
            border-color: #bbdff5;
        }

        @media(max-width:1100px){
            .summary-grid{
                grid-template-columns:repeat(2,1fr);
            }

            .section-grid{
                grid-template-columns:1fr;
            }
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
            .wrapper{ flex-direction:column; }
            .mobile-nav { display: flex; }
            .sidebar{ width:100%; height:auto; position:static; display:none; }
            .sidebar.active { display:block; }

            .content{
                padding:18px;
            }

            .summary-grid{
                grid-template-columns:1fr;
            }

            .ai-school {
                flex: 0 0 85%; /* Menampilkan sedikit kartu selanjutnya agar swipe lebih intuitif */
            }

            .accordion-header {
                flex-direction: row-reverse;
                justify-content: flex-end;
                gap: 15px;
            }

            .hero h1{
                font-size:28px;
            }

            .btn-print {
                position: static;
                margin-top: 15px;
                display: inline-flex;
                width: max-content;
            }

            .ranking-top{
                align-items:flex-start;
            }

            .bar-row{
                grid-template-columns:1fr;
                gap:7px;
            }

            .ai-school-head,
            .ai-insight{
                grid-template-columns:1fr;
            }

            .ai-score{
                text-align:left;
            }

            .ai-grid{
                grid-template-columns:1fr;
            }

            .req-card-item {
                flex:0 0 100%;
            }

            .carousel-btn {
                display: none;
            }

            .wrapper{ flex-direction:column; }
            .sidebar{ width:100%; height:auto; position:static; }
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
            .content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .wrapper { display: block !important; }
            .btn-print { display: none !important; }
            .accordion-body { display: block !important; }
            .accordion-header::after { display: none !important; }
            .carousel-btn { display: none !important; }
            .ai-school-list { display: block !important; overflow: visible !important; }
            .ai-school { page-break-inside: avoid; margin-bottom: 20px !important; }
            .card, .accordion-card { box-shadow: none !important; border: 1px solid #ccc !important; margin-bottom: 20px !important; page-break-inside: avoid; }
            .ranking, .ai-metric, .ai-box { page-break-inside: avoid; border: 1px solid #ccc !important; }
            .hero { background: white !important; color: #2c3e50 !important; border: 2px solid #2c3e50 !important; }
            .hero h1, .hero p { color: #2c3e50 !important; }
            [id^="req_"] { display: flex !important; flex-wrap: wrap !important; overflow: visible !important; }
            .req-card-item { flex: 0 0 calc(50% - 10px) !important; margin-bottom: 10px; page-break-inside: avoid; border: 1px solid #ccc !important; }
            
            /* Peta Kolaborasi Print Styles */
            .cs-box { border: 1px solid #ddd !important; background: white !important; page-break-inside: avoid; }
            .cs-recommendation { border: 1px solid #ddd !important; background: white !important; page-break-inside: avoid; }
        }

        /* Peta Kolaborasi Base Styles */
        .cs-wrapper { display: flex; gap: 15px; flex-wrap: wrap; }
        .cs-box { flex: 1; min-width: 150px; padding: 10px; border-radius: 8px; }
        .cs-internal { background: #f4f6f9; border-left: 3px solid #7f8c8d; }
        .cs-ekspor { background: #fdf8e4; border-left: 3px solid #f39c12; }
        .cs-impor { background: #e8f6f3; border-left: 3px solid #1abc9c; }
        
        .cs-title { font-size: 11px; color: #55616d; font-weight: bold; margin-bottom: 4px; }
        .cs-count { font-size: 16px; color: #2c3e50; font-weight: bold; }
        .cs-count span { font-size: 10px; font-weight: normal; }
        
        .cs-detail-ekspor { font-size: 10px; color: #8a6d3b; margin-top: 6px; line-height: 1.4; }
        .cs-detail-impor { font-size: 10px; color: #3c763d; margin-top: 6px; line-height: 1.4; }
        
        .cs-recommendation { margin-top: 15px; padding: 12px; border-radius: 4px; font-size: 13px; color: #2c3e50; line-height: 1.5; }
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
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
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

<main class="content">
        <section class="hero">
            <h1>Analytics MGMP</h1>
            <p>
            Proses Sistematis Untuk Menganalisa Kolaborasi Guru MGMP
                <strong></strong>
            </p>
            <button onclick="const oldTitle = document.title; document.title = 'Laporan Analytics'; window.print(); document.title = oldTitle;" class="btn-print">
                📥 Download Laporan Analytics.PDF
            </button>
        </section>

        <section class="summary-grid">
            <div class="card metric">
                <h3>Total Guru</h3>
                <h1><?= number_format($total_guru); ?></h1>
            </div>
            <div class="card metric">
                <h3>Total Materi</h3>
                <h1><?= number_format($total_upload_approved_internal); ?></h1>
            </div>
            <div class="card metric">
                <h3>Total Download</h3>
                <h1><?= number_format($total_download); ?></h1>
            </div>
            <div class="card metric">
                <h3>Total Login</h3>
                <h1><?= number_format($total_login); ?></h1>
            </div>
            <div class="card metric">
                <h3>Skor Sistem <br>
                (Gabungan Skor Guru)</h3>
                <h1><?= number_format($total_system_score); ?></h1>
            </div>
        </section>

        <section class="section-grid">
            <div class="card full">
                <h2>Formula Learning Analytics</h2>
                <div class="formula">
                    <span class="chip">Upload Materi = <?= $POINT_UPLOAD; ?> poin</span>
                    <span class="chip">Download Materi = <?= $POINT_DOWNLOAD; ?> poin</span>
                    <span class="chip">Login Harian Guru = <?= $POINT_LOGIN; ?> poin</span>
                    <span class="chip">Skor = Upload + Download + Login</span>
                </div>
            </div>

            <div class="accordion-card">
                <div class="accordion-header">
                    5 Materi Terpopuler
                </div>
                <div class="accordion-body">
                <?php if(mysqli_num_rows($materi_populer) > 0){ ?>
                    <div class="sub" style="margin-bottom: 20px;">
                        <b>Menampilkan Top 5 Materi Paling Banyak Diunduh</b>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php 
                    $rank_materi = 1;
                    while($row = mysqli_fetch_assoc($materi_populer)){ 
                        $rank_class = '';
                        if($rank_materi == 1) $rank_class = 'gold';
                        elseif($rank_materi == 2) $rank_class = 'silver';
                        elseif($rank_materi == 3) $rank_class = 'bronze';
                    ?>
                        <div class="ranking">
                            <div class="ranking-top">
                                <div class="person">
                                    <div class="rank <?= $rank_class; ?>"><?= $rank_materi; ?></div>
                                    <div>
                                        <div class="title"><?= e($row['title']); ?></div>
                                        <div class="sub">
                                            <?= e($row['author_name']); ?> - <?= e($row['institution_name']); ?><br>
                                            Upload: <?= e(format_datetime_id($row['created_at'])); ?>
                                        </div>
                                        <div class="badges" style="margin-top: 10px;">
                                            <span class="badge purple"><?= e($row['category']); ?></span>
                                            <span class="badge"><?= e($row['grade_level']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="score">
                                    <?= number_format($row['total_download']); ?>
                                    <div style="font-size: 13px; color: #66737f; margin-top: -3px;">download</div>
                                </div>
                            </div>
                        </div>
                    <?php $rank_materi++; } ?>
                    </div>
                <?php }else{ ?>
                    <div class="empty">Belum Ada Materi</div>
                <?php } ?>
                </div>
            </div>

            <div class="accordion-card">
                <div class="accordion-header">
                    5 Guru Teraktif
                </div>
                <div class="accordion-body">
                <?php if(mysqli_num_rows($leaderboard) > 0){ ?>
                    <div class="sub" style="margin-bottom: 20px;">
                        <b>Menampilkan Top 5 Guru Paling Aktif</b>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php
                    $rank = 1;
                    while($row = mysqli_fetch_assoc($leaderboard)){
                        $rank_class = '';

                        if($rank == 1){
                            $rank_class = 'gold';
                        }elseif($rank == 2){
                            $rank_class = 'silver';
                        }elseif($rank == 3){
                            $rank_class = 'bronze';
                        }

                        $score = (int)$row['nilai_partisipasi'];
                        $percent = percent_value($score, $grand_total);

                        if($score == 0){
                            $status = 'Tidak Aktif';
                            $status_class = 'danger';
                        }elseif($score <= 15){
                            $status = 'Mulai Berkembang';
                            $status_class = 'warning';
                        }elseif($score <= 40){
                            $status = 'Aktif';
                            $status_class = 'info';
                        }elseif($score <= 80){
                            $status = 'Sangat Aktif';
                            $status_class = 'primary';
                        }else{
                            $status = 'Role Model MGMP';
                            $status_class = 'success';
                        }
                    ?>
                        <div class="ranking">
                            <div class="ranking-top">
                                <div class="person" style="align-items: center;">
                                    <div class="rank <?= $rank_class; ?>"><?= $rank; ?></div>
                                    <?php
                                    $photo_leaderboard = isset($row['profile_photo']) ? $row['profile_photo'] : '';
                                    $initial_leaderboard = strtoupper(substr(trim($row['full_name']), 0, 1));
                                    if(empty($initial_leaderboard)) $initial_leaderboard = "G";
                                    ?>
                                    <?php if(!empty($photo_leaderboard) && file_exists(__DIR__ . "/" . $photo_leaderboard)){ ?>
                                        <img src="<?= htmlspecialchars($photo_leaderboard); ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; flex-shrink:0; border: 2px solid #ecf0f1;">
                                    <?php } else { ?>
                                        <div style="width:80px; height:80px; border-radius:50%; background:#ecf0f1; color:#2c3e50; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:bold; flex-shrink:0; border: 2px solid #ecf0f1;"><?= htmlspecialchars($initial_leaderboard); ?></div>
                                    <?php } ?>
                                    <div>
                                        <div class="title"><?= e($row['full_name']); ?></div>
                                        <div class="sub"><?= e($row['school_name']); ?></div>
                                        <div class="detail">
                                            Upload: <?= number_format($row['total_upload']); ?> x <?= $POINT_UPLOAD; ?> poin<br>
                                            Download materi: <?= number_format($row['total_download']); ?> x <?= $POINT_DOWNLOAD; ?> poin<br>
                                            Login harian: <?= number_format($row['total_login']); ?> x <?= $POINT_LOGIN; ?> poin
                                        </div>
                                        <span class="status <?= $status_class; ?>"><?= $status; ?></span>
                                    </div>
                                </div>
                                <div class="score"><?= number_format($score); ?></div>
                            </div>
                            <div class="progress-info">
                                <span>Kontribusi</span>
                                <span><?= number_format($percent, 1); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $percent; ?>%;"></div>
                            </div>
                        </div>
                    <?php $rank++; } ?>
                    </div>
                <?php }else{ ?>
                    <div class="empty" style="text-align:center; padding: 25px 10px;">
                        <span style="font-size:35px; display:block; margin-bottom:10px;">🏆</span>
                        Belum ada guru yang mencapai ambang batas keaktifan tinggi (Skor &gt; 7).<br>
                        <b style="color: #2c3e50;">Jadilah yang pertama masuk ke Leaderboard!</b>
                    </div>
                <?php } ?>
                </div>
            </div>

            <div class="accordion-card full">
                <div class="accordion-header">
                    <div style="flex: 1; text-align: center;">Daftar Guru Tidak Aktif</div>
                </div>
                <div class="accordion-body">
                    <div class="sub" style="margin-bottom: 20px;">
                        <b>Guru Tidak Aktif</b> adalah guru yang sama sekali belum memiliki aktivitas (Upload, Download, maupun Login) di dalam platform.
                        <br><b style="display:inline-block; margin-top:8px; color:#34495e;">(Klik tombol panah atau geser untuk melihat guru lainnya)</b>
                    </div>
                    <?php if(mysqli_num_rows($guru_tidak_aktif) > 0){ ?>
                        <div class="carousel-wrapper">
                            <button class="carousel-btn prev" onclick="scrollCarousel(-1, 'inactiveTeacherCarousel')">&#10094;</button>
                            <button class="carousel-btn next" onclick="scrollCarousel(1, 'inactiveTeacherCarousel')">&#10095;</button>
                            <div class="ai-school-list" id="inactiveTeacherCarousel">
                            <?php
                            $no_pasif = 1;
                            while($pasif = mysqli_fetch_assoc($guru_tidak_aktif)){
                            ?>
                                <div class="ai-school" style="border-left: 5px solid #e74c3c;">
                                    <div class="ai-school-head">
                                        <div class="person" style="align-items: center;">
                                            <div class="rank" style="background:#e74c3c;"><?= $no_pasif; ?></div>
                                            <?php
                                            $photo_pasif = isset($pasif['profile_photo']) ? $pasif['profile_photo'] : '';
                                            $initial_pasif = strtoupper(substr(trim($pasif['full_name']), 0, 1));
                                            if(empty($initial_pasif)) $initial_pasif = "G";
                                            ?>
                                            <?php if(!empty($photo_pasif) && file_exists(__DIR__ . "/" . $photo_pasif)){ ?>
                                                <img src="<?= htmlspecialchars($photo_pasif); ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; flex-shrink:0; border: 2px solid #ecf0f1;">
                                            <?php } else { ?>
                                                <div style="width:80px; height:80px; border-radius:50%; background:#ecf0f1; color:#2c3e50; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:bold; flex-shrink:0; border: 2px solid #ecf0f1;"><?= htmlspecialchars($initial_pasif); ?></div>
                                            <?php } ?>
                                            <div>
                                                <div class="title"><?= e($pasif['full_name']); ?></div>
                                                <div class="sub"><?= e($pasif['school_name']); ?></div>
                                            </div>
                                        </div>
                                        <div></div>
                                        <div style="display:flex; gap:25px; text-align:right; align-items:center;">
                                            <div class="ai-score">
                                                <strong style="color:#e74c3c;">0</strong>
                                                <span>Total Poin</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ai-insight" style="grid-template-columns: 1fr; margin-top: 20px;">
                                        <div class="ai-box" style="background: #fdf3f2; border-color: #fadbd8;">
                                            <strong style="color: #e74c3c;">Status: Tidak Aktif</strong>
                                            <div style="margin-top: 5px;">Guru ini belum pernah login, mengunduh, maupun mengunggah materi ke dalam platform MGMP. Diperlukan tindak lanjut untuk aktivasi.</div>
                                        </div>
                                    </div>
                                </div>
                            <?php $no_pasif++; } ?>
                            </div>
                        </div>
                    <?php }else{ ?>
                        <div class="empty">Semua guru sudah menunjukkan aktivitas di platform.</div>
                    <?php } ?>
                </div>
            </div>

            <div class="accordion-card full">
                <div class="accordion-header">
                    <div>
                        School Performance Index (SPI)<br>
                        Knowledge Sharing Index   (KSI)
                    </div>
                </div>
                <div class="accordion-body">
                <div class="sub" style="margin-bottom: 20px;">
                    <b>SPI (School Performance Index)</b> adalah total skor kolektif dari seluruh guru di satu sekolah.<br>
                    <b>KSI (Knowledge Sharing Index)</b> adalah analisis cerdas berdasarkan rasio aktivitas untuk memberikan rekomendasi otomatis.
                    <br><b style="display:inline-block; margin-top:8px; color:#34495e;">(Klik tombol panah atau geser untuk melihat analisis sekolah lainnya)</b>
                </div>
                <?php if(mysqli_num_rows($school_analytics) > 0){ ?>
                    <div class="carousel-wrapper">
                        <button class="carousel-btn prev" onclick="scrollCarousel(-1, 'schoolCarousel')">&#10094;</button>
                        <button class="carousel-btn next" onclick="scrollCarousel(1, 'schoolCarousel')">&#10095;</button>
                        <div class="ai-school-list" id="schoolCarousel">
                    <?php
                    $school_rank = 1;
                    while($school = mysqli_fetch_assoc($school_analytics)){
                        $school_score = (int)$school['total_score'];
                        $school_percent = percent_value($school_score, $total_system_score);
                        $ai = school_intelligence(
                            $school,
                            $POINT_UPLOAD,
                            $POINT_DOWNLOAD,
                            $POINT_LOGIN
                        );

                        // Klasifikasi dan Narasi untuk Skor SPI
                        if($school_score >= 100) {
                            $spi_status = 'Sangat Tinggi';
                            $spi_analysis = 'Kinerja kolektif sekolah sangat luar biasa. Kontribusi keseluruhan guru di sekolah ini memberikan dampak besar bagi ekosistem MGMP.';
                            $spi_recommendation = 'Pertahankan performa luar biasa ini. Sekolah Anda dapat menjadi percontohan (Role Model) kinerja kolektif bagi sekolah lain.';
                        } elseif($school_score >= 50) {
                            $spi_status = 'Tinggi';
                            $spi_analysis = 'Kinerja kolektif sekolah tergolong tinggi. Kolaborasi digital antar guru di sekolah ini sudah terbangun dengan baik.';
                            $spi_recommendation = 'Terus dorong guru-guru untuk mempertahankan rutinitas interaksi dan mulai fokus pada kualitas materi yang diunggah.';
                        } elseif($school_score >= 20) {
                            $spi_status = 'Sedang';
                            $spi_analysis = 'Kinerja kolektif sekolah berada pada tingkat rata-rata. Terdapat beberapa aktivitas partisipasi, namun secara kolektif masih bisa dioptimalkan.';
                            $spi_recommendation = 'Ajak lebih banyak rekan guru di sekolah Anda untuk mulai aktif mengunggah dan membagikan perangkat pembelajaran mereka.';
                        } elseif($school_score > 0) {
                            $spi_status = 'Rendah';
                            $spi_analysis = 'Kinerja kolektif sekolah masih tergolong rendah. Baru sedikit aktivitas yang tercatat dan partisipasi belum merata di antara guru-guru.';
                            $spi_recommendation = 'Tingkatkan partisipasi dengan menjadwalkan waktu khusus bagi guru-guru di sekolah untuk mengeksplorasi platform bersama-sama.';
                        } else {
                            $spi_status = 'Tidak Aktif';
                            $spi_analysis = 'Sekolah belum memiliki akumulasi poin. Belum ada aktivitas partisipasi dari satupun guru di sekolah ini.';
                            $spi_recommendation = 'Segera lakukan aktivasi akun dan sosialisasi platform MGMP di sekolah Anda untuk memulai langkah pertama kolaborasi digital.';
                        }
                    ?>
                        <div class="ai-school">
                            <div class="ai-school-head">
                                <div class="person">
                                    <div class="rank"><?= $school_rank; ?></div>
                                    <div>
                                        <div class="title"><?= e($school['school_name']); ?></div>
                                    </div>
                                </div>
                                <div></div>
                                <div style="display:flex; gap:25px; text-align:right; align-items:center;">
                                    <div class="ai-score">
                                        <strong><?= number_format($school_score); ?></strong>
                                        <span>Skor SPI</span>
                                    </div>
                                    <div class="ai-score">
                                    <strong style="color:#3498db;"><?= round($ai['score_per_guru'], 2); ?></strong>
                                        <span>Skor KSI</span>
                                    </div>
                                </div>
                            </div>

                            <div class="ai-grid">
                                <div class="ai-metric">
                                    <span>Guru</span>
                                    <strong><?= number_format($school['total_guru']); ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Upload Approved</span>
                                    <strong><?= number_format($school['total_upload']); ?> x <?= $POINT_UPLOAD; ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Download Materi</span>
                                    <strong><?= number_format($school['total_download']); ?> x <?= $POINT_DOWNLOAD; ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Login Harian</span>
                                    <strong><?= number_format($school['total_login']); ?> x <?= $POINT_LOGIN; ?></strong>
                                </div>
                            </div>

                            <div class="ai-grid">
                                <div class="ai-metric">
                                    <span>Skor per Guru</span>
                                <strong><?= round($ai['score_per_guru'], 2); ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Upload per Guru</span>
                                <strong><?= round($ai['upload_per_guru'], 2); ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Login per Guru</span>
                                <strong><?= round($ai['login_per_guru'], 2); ?></strong>
                                </div>
                                <div class="ai-metric">
                                    <span>Download per Upload</span>
                                <strong><?= round($ai['download_per_upload'], 2); ?></strong>
                                </div>
                            </div>

                            <div class="ai-insight">
                                <div class="ai-box box-spi-analysis">
                                    <strong>School Performance Index (SPI)</strong>
                                    <div style="margin-top: 5px;">Status Kinerja Kolektif: <b style="color: #2c3e50;"><?= $spi_status; ?></b></div>
                                    <div style="margin-top: 5px;"><?= $spi_analysis; ?></div>
                                </div>
                                <div class="ai-box box-spi-rec">
                                    <strong>Rekomendasi Sistem (SPI)</strong>
                                    <div style="margin-top: 5px;"><?= $spi_recommendation; ?></div>
                                </div>
                                <div class="ai-box box-ksi-analysis">
                                    <strong>Knowledge Sharing Index (KSI)</strong>
                                    <div style="margin-top: 5px;"><?= e($ai['analysis']); ?></div>
                                </div>
                                <div class="ai-box box-ksi-rec">
                                    <strong>Rekomendasi Sistem (KSI)</strong>
                                    <div style="margin-top: 5px;"><?= e($ai['recommendation']); ?></div>
                                </div>
                            </div>

                            <div class="progress-info">
                                <span>Kontribusi terhadap aktivitas MGMP</span>
                                <span><?= number_format($school_percent, 1); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?= $school_percent; ?>%;"></div>
                            </div>

                            <!-- Kebutuhan Internal Sekolah -->
                            <div style="margin-top: 20px; border-top: 1px solid #edf0f2; padding-top: 15px;">
                                <strong style="color:#25313f; font-size:14px; display:block; margin-bottom:10px;">Topik Materi Paling Diperlukan (Internal Sekolah)</strong>
                                <?php 
                                $curr_school = $school['school_name'];
                                if(isset($school_requests[$curr_school]) && count($school_requests[$curr_school]) > 0){ 
                                ?>
                                    <div style="position:relative; width:100%;">
                                        <!-- Tombol Panah Kiri (Muncul jika lebih dari 4 materi) -->
                                        <?php if(count($school_requests[$curr_school]) > 4){ ?>
                                            <button onclick="scrollCarousel(-1, 'req_<?= $school_rank; ?>')" style="position:absolute; left:-15px; top:50%; transform:translateY(-50%); background:#2c3e50; color:white; border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:10; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:0.3s;" onmouseover="this.style.background='#1abc9c'" onmouseout="this.style.background='#2c3e50'">&#10094;</button>
                                        <?php } ?>
                                        
                                        <!-- Container Flexbox (Data Request) -->
                                        <div id="req_<?= $school_rank; ?>" style="display:flex; overflow-x:auto; gap:12px; padding-bottom:10px; scrollbar-width:none; scroll-behavior:smooth;">
                                        <?php foreach($school_requests[$curr_school] as $req_item){ ?>
                                            <div class="req-card-item">
                                                <div style="line-height: 1.4;">
                                                    <span style="display:inline-block; background:#bbdff5; color:#004085; padding:3px 6px; border-radius:4px; font-size:10px; font-weight:bold; margin-bottom: 6px;"><?= htmlspecialchars($req_item['jenis']); ?></span>
                                                    <div style="color:#2c3e50; font-weight:bold;"><?= htmlspecialchars($req_item['detail']); ?></div>
                                                </div>
                                                <div style="border-top: 1px dashed #bbdff5; padding-top: 8px; margin-top: auto;">
                                                    <div style="color:#27ae60; font-size:11px; font-weight:bold; margin-bottom: 3px;">
                                                        👤 Diminta <?= $req_item['jumlah']; ?> guru:
                                                    </div>
                                                    <div style="color:#55616d; font-size:11px; font-style:italic; line-height:1.4;">
                                                        <?= htmlspecialchars($req_item['nama_guru']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                        
                                        <!-- Tombol Panah Kanan (Muncul jika lebih dari 4 materi) -->
                                        <?php if(count($school_requests[$curr_school]) > 4){ ?>
                                            <button onclick="scrollCarousel(1, 'req_<?= $school_rank; ?>')" style="position:absolute; right:-15px; top:50%; transform:translateY(-50%); background:#2c3e50; color:white; border:none; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:10; box-shadow:0 2px 5px rgba(0,0,0,0.2); transition:0.3s;" onmouseover="this.style.background='#1abc9c'" onmouseout="this.style.background='#2c3e50'">&#10095;</button>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div style="color:#7f8c8d; font-size:13px; font-style:italic;">Belum ada request materi yang masuk dari guru di sekolah ini.</div>
                                <?php } ?>
                            </div>

                            <!-- Peta Kolaborasi Lintas Sekolah -->
                            <div style="margin-top: 20px; border-top: 1px solid #edf0f2; padding-top: 15px;">
                                <strong style="color:#25313f; font-size:14px; display:block; margin-bottom:10px;">Peta Kolaborasi Lintas Sekolah</strong>
                                <?php 
                                $curr_school = $school['school_name'];
                                $cs_data = isset($cross_school_data[$curr_school]) ? $cross_school_data[$curr_school] : ['internal' => 0, 'ekspor' => 0, 'impor' => 0, 'ekspor_detail' => array(), 'impor_detail' => array()];
                                $total_cs = $cs_data['internal'] + $cs_data['ekspor'] + $cs_data['impor'];
                                
                                if($total_cs > 0) {
                                    $ekspor_str = "";
                                    if(!empty($cs_data['ekspor_detail'])){
                                        arsort($cs_data['ekspor_detail']);
                                        $ekspor_arr = [];
                                        foreach($cs_data['ekspor_detail'] as $s => $c) {
                                            $ekspor_arr[] = htmlspecialchars($s) . " ($c)";
                                        }
                                        $ekspor_str = implode(", ", $ekspor_arr);
                                    }

                                    $impor_str = "";
                                    if(!empty($cs_data['impor_detail'])){
                                        arsort($cs_data['impor_detail']);
                                        $impor_arr = [];
                                        foreach($cs_data['impor_detail'] as $s => $c) {
                                            $impor_arr[] = htmlspecialchars($s) . " ($c)";
                                        }
                                        $impor_str = implode(", ", $impor_arr);
                                    }
                                ?>
                                    <div class="cs-wrapper">
                                        <div class="cs-box cs-internal">
                                            <div class="cs-title">Internal (Satu Sekolah)</div>
                                            <div class="cs-count"><?= $cs_data['internal']; ?> <span>unduhan</span></div>
                                        </div>
                                        <div class="cs-box cs-ekspor">
                                            <div class="cs-title">Ekspor (Diunduh Sekolah Lain)</div>
                                            <div class="cs-count"><?= $cs_data['ekspor']; ?> <span>unduhan</span></div>
                                            <?php if($ekspor_str){ ?>
                                                <div class="cs-detail-ekspor"><b>Ke:</b> <?= $ekspor_str; ?></div>
                                            <?php } ?>
                                        </div>
                                        <div class="cs-box cs-impor">
                                            <div class="cs-title">Impor (Mengunduh Sekolah Lain)</div>
                                            <div class="cs-count"><?= $cs_data['impor']; ?> <span>unduhan</span></div>
                                            <?php if($impor_str){ ?>
                                                <div class="cs-detail-impor"><b>Dari:</b> <?= $impor_str; ?></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    
                                    <?php
                                    $cs_rec_class = 'info';
                                    $cs_rec_text = '';
                                    if ($cs_data['ekspor'] > 0 && $cs_data['ekspor'] >= $cs_data['impor'] && $cs_data['ekspor'] >= $cs_data['internal']) {
                                        $cs_rec_class = 'success';
                                        $cs_rec_text = 'Luar biasa! Materi dari sekolah Anda sangat bermanfaat dan menjadi rujukan utama bagi sekolah lain (Ekspor dominan). Pertahankan kualitas ini!';
                                    } elseif ($cs_data['impor'] > 0 && $cs_data['ekspor'] == 0) {
                                        $cs_rec_class = 'warning';
                                        $cs_rec_text = 'Sekolah Anda aktif memanfaatkan referensi dari luar (Impor tinggi). Mari dorong guru-guru untuk membalas kontribusi dengan mengunggah karya terbaik mereka.';
                                    } elseif ($cs_data['internal'] > 0 && $cs_data['ekspor'] == 0) {
                                        $cs_rec_class = 'primary';
                                        $cs_rec_text = 'Kolaborasi internal sekolah sudah berjalan baik. Coba bagikan dan promosikan materi unggulan Anda ke sekolah lain agar dampaknya lebih luas.';
                                    } elseif ($cs_data['ekspor'] > 0 && $cs_data['impor'] > 0) {
                                        $cs_rec_class = 'success';
                                        $cs_rec_text = 'Siklus kolaborasi yang sangat sehat! Sekolah Anda aktif membagikan materi sekaligus responsif mengambil referensi silang dari sekolah lain.';
                                    } else {
                                        $cs_rec_class = 'info';
                                        $cs_rec_text = 'Terus tingkatkan interaksi pengunggahan dan pengunduhan silang agar ekosistem belajar antar sekolah semakin kaya.';
                                    }
                                    
                                    $rec_color = '#3498db'; $rec_bg = '#eef5fa'; $rec_text_color = '#2980b9';
                                    if($cs_rec_class == 'success') { $rec_color = '#27ae60'; $rec_bg = '#e9f7ef'; $rec_text_color = '#1e8449'; } 
                                    elseif($cs_rec_class == 'warning') { $rec_color = '#f39c12'; $rec_bg = '#fef5e7'; $rec_text_color = '#d68910'; } 
                                    elseif($cs_rec_class == 'primary') { $rec_color = '#8e44ad'; $rec_bg = '#f4ecf7'; $rec_text_color = '#732d91'; }
                                    ?>
                                    <div class="cs-recommendation" style="background: <?= $rec_bg; ?>; border-left: 4px solid <?= $rec_color; ?>;">
                                        <strong style="color: <?= $rec_text_color; ?>;">💡 Rekomendasi Sistem:</strong> <?= $cs_rec_text; ?>
                                    </div>
                                <?php } else { ?>
                                    <div style="color:#7f8c8d; font-size:13px; font-style:italic;">Belum ada aktivitas unduhan materi lintas maupun internal sekolah.</div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php $school_rank++; } ?>
                    </div>
                    </div>
                <?php }else{ ?>
                    <div class="empty">Belum ada data sekolah.</div>
                <?php } ?>
                </div>
            </div>

            <div class="card full">
                <h2>Trend Aktivitas Bulanan</h2>
                <?php if(count($label_trend) > 0){ ?>
                    <div class="ai-insight" style="grid-template-columns: 1fr; margin-bottom: 25px; margin-top: -5px;">
                        <?php 
                        $ta_border = '#3498db';
                        if($trend_analysis['class'] == 'success') $ta_border = '#27ae60';
                        if($trend_analysis['class'] == 'danger') $ta_border = '#e74c3c';
                        if($trend_analysis['class'] == 'warning') $ta_border = '#f39c12';
                        if($trend_analysis['class'] == 'primary') $ta_border = '#1abc9c';
                        ?>
                        <div class="ai-box" style="border-left: 4px solid <?= $ta_border; ?>; display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
                            <div>
                                <strong style="margin-bottom:8px; font-size: 16px;">Analisis Prediktif Tren (AI)</strong>
                                <span style="display:block; font-size:14px; color:#55616d;"><?= $trend_analysis['insight']; ?></span>
                                <span style="display:block; font-size:13px; color:#7f8c8d; margin-top:6px;"><b>Rekomendasi Strategis:</b> <?= $trend_analysis['recommendation']; ?></span>
                            </div>
                            <div>
                                <span class="status <?= $trend_analysis['class']; ?>" style="margin-top:0; font-size:14px; padding:8px 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"><?= $trend_analysis['status']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="chart-box">
                        <canvas id="trendChart"></canvas>
                    </div>
                <?php }else{ ?>
                    <div class="empty">Belum ada aktivitas bulanan yang bisa ditampilkan.</div>
                <?php } ?>
            </div>
        </section>
</main>

<?php if(count($label_trend) > 0){ ?>
<script>
const ctx = document.getElementById('trendChart');

new Chart(ctx, {
    type:'line',
    data:{
        labels: <?= json_encode($label_trend); ?>,
        datasets:[
            {
                label:'Upload',
                data: <?= json_encode($data_upload); ?>,
                borderColor:'#3498db',
                backgroundColor:'rgba(52,152,219,0.14)',
                tension:0.3,
                fill:true
            },
            {
                label:'Download',
                data: <?= json_encode($data_download); ?>,
                borderColor:'#27ae60',
                backgroundColor:'rgba(39,174,96,0.14)',
                tension:0.3,
                fill:true
            },
            {
                label:'Login Harian',
                data: <?= json_encode($data_login); ?>,
                borderColor:'#f39c12',
                backgroundColor:'rgba(243,156,18,0.14)',
                tension:0.3,
                fill:true
            }
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'top'
            }
        },
        scales:{
            y:{
                beginAtZero:true,
                ticks:{
                    precision:0
                }
            }
        }
    }
});
</script>
<?php } ?>

<script>
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
            if (panel.style.display === "block") {
                panel.style.display = "none";
            } else {
                panel.style.display = "block";
            }
        });
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
