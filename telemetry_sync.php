<?php
// Mencegah error muncul ke layar dan mengganggu load dashboard
error_reporting(0);
ini_set('display_errors', 0);

// Pastikan file ini bisa dipanggil secara aman
if (!isset($conn)) {
    // Jika tidak ada koneksi DB aktif (misal dijalankan via CLI), include config
    $config_path = __DIR__ . '/config/database.php';
    if(file_exists($config_path)) {
        require_once $config_path;
    } else {
        return;
    }
}

// Hanya jalankan jika TELEMETRY_ENDPOINT diatur
if (!defined('TELEMETRY_ENDPOINT')) {
    return;
}

// 1. PSEUDO-CRON: Cek waktu sinkronisasi terakhir
$sync_file = __DIR__ . '/config/telemetry_last_sync.txt';
$today = date('Y-m-d');

if (file_exists($sync_file)) {
    $last_sync = file_get_contents($sync_file);
    if (trim($last_sync) === $today) {
        // Sudah disinkronisasi hari ini, berhenti.
        return;
    }
}

// 2. AUTO-REGISTRATION: Generate atau Ambil UUID MGMP Lokal
$uuid_file = __DIR__ . '/config/telemetry_uuid.txt';
if (file_exists($uuid_file)) {
    $mgmp_uuid = trim(file_get_contents($uuid_file));
} else {
    // Generate simple UUID
    $mgmp_uuid = 'LIAK-' . strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2)));
    @file_put_contents($uuid_file, $mgmp_uuid);
}

// Ambil nama host/domain
$domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// 3. PENGUMPULAN DATA (Agregat)
$POINT_UPLOAD = 7;
$POINT_DOWNLOAD = 2;
$POINT_LOGIN = 1;

function get_count($conn, $query) {
    $res = mysqli_query($conn, $query);
    if($res && $row = mysqli_fetch_assoc($res)) {
        return (int)$row['total'];
    }
    return 0;
}

// Populasi
$total_guru = get_count($conn, "SELECT COUNT(*) AS total FROM users WHERE role_id = 2");

// Kinerja (Analytics)
$total_upload = get_count($conn, "SELECT COUNT(*) AS total FROM materials WHERE status = 'approved'");
$total_download = get_count($conn, "SELECT COUNT(*) AS total FROM downloads");
$total_login = get_count($conn, "SELECT COUNT(*) AS total FROM login_activity");

// SPI System
$spi_system = ($total_upload * $POINT_UPLOAD) + ($total_download * $POINT_DOWNLOAD) + ($total_login * $POINT_LOGIN);

// KSI System (Rata-rata Skor per Guru)
$ksi_system = $total_guru > 0 ? round($spi_system / $total_guru, 2) : 0;

// Peta Kolaborasi (Ekspor/Impor/Internal)
$total_ekspor = 0;
$total_impor = 0;
$total_internal = 0;

$cs_query = mysqli_query($conn, "
    SELECT 
        uploader_user.school_name AS uploader_school,
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

if ($cs_query) {
    while($r = mysqli_fetch_assoc($cs_query)) {
        $u_school = $r['uploader_school'];
        $d_school = $r['downloader_school'];
        $count = (int)$r['total_interaction'];
        
        if (empty($u_school)) continue;
        
        if ($d_school === $u_school) {
            $total_internal += $count;
        } elseif ($d_school === 'Guest/Sistem' || $d_school === 'Contributor External') {
            $total_ekspor += $count;
        } else {
            // Jika beda sekolah (A diunduh B -> A ekspor, B impor)
            // Di level nasional/agregat, kita bisa mengukur total transfer.
            // Tapi karena kita mengumpulkan data untuk SEMUA sekolah di MGMP ini,
            // maka total ekspor = A diunduh luar, total impor = B mengunduh luar.
            // Di sini kita catat transfer sebagai ekspor dan impor.
            $total_ekspor += $count;
            $total_impor += $count;
        }
    }
}

// 4. SUSUN PAYLOAD JSON
$payload = [
    'api_secret' => 'LIAK-SYNC-2026-X9',
    'mgmp_id' => $mgmp_uuid,
    'domain' => $domain,
    'sync_time' => date('Y-m-d H:i:s'),
    'metrics' => [
        'total_guru' => $total_guru,
        'total_upload' => $total_upload,
        'total_download' => $total_download,
        'total_login' => $total_login,
        'spi_score' => $spi_system,
        'ksi_score' => $ksi_system
    ],
    'cross_school' => [
        'ekspor' => $total_ekspor,
        'impor' => $total_impor,
        'internal' => $total_internal
    ]
];

// 5. KIRIM DATA VIA cURL (Asimetris / Asynchronous fallback)
$ch = curl_init(TELEMETRY_ENDPOINT);
$json_payload = json_encode($payload);

curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Sangat cepat agar tidak blocking halaman jika server pusat lemot
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. CATAT WAKTU JIKA SUKSES (Toleransi 2xx)
if ($http_code >= 200 && $http_code < 300) {
    @file_put_contents($sync_file, $today);
}
// Jika gagal, besok/login berikutnya akan mencoba kirim lagi.

?>
