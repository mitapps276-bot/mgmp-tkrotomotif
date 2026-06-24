<?php

// Memuat konfigurasi koneksi database
include 'config/database.php';

// Set header agar output dikenali sebagai JSON untuk ditarik via AJAX/Fetch
header('Content-Type: application/json');

// Validasi parameter request
if (!isset($_GET['id_sekolah']) || empty($_GET['id_sekolah'])) {
    echo json_encode(['error' => 'Parameter id_sekolah wajib disertakan.']);
    exit;
}

$id_sekolah = $_GET['id_sekolah'];

/* 
 * ALGORITMA ANALITIK:
 * 1. Membuat tabel virtual (kategori_base) berisi 3 kategori materi utama.
 * 2. Menghitung total DOWNLOAD sekolah dari tabel user_activities (di-join dengan materis untuk mendapat relasi kategorinya).
 * 3. Menghitung total REQUEST sekolah dari tabel requests.
 * 4. Menghitung total SUPPLY (status 'approved') global dari tabel materis.
 * 5. Menghitung Rasio Kebutuhan = (Download + Request) / (Supply + 1).
 * 6. Mengurutkan secara descending dan mengambil 1 baris (kategori paling kritis).
 */

$sql = "
    SELECT 
        kategori_base.kategori,
        COALESCE(d.total_download_sekolah, 0) AS total_download_sekolah,
        COALESCE(r.total_request_sekolah, 0) AS total_request_sekolah,
        COALESCE(s.total_supply_global, 0) AS total_supply_global,
        (
            (COALESCE(d.total_download_sekolah, 0) + COALESCE(r.total_request_sekolah, 0)) 
            / 
            (COALESCE(s.total_supply_global, 0) + 1)
        ) AS nilai_rasio_kebutuhan
    FROM (
        SELECT 'Modul Ajar' AS kategori
        UNION SELECT 'Soal'
        UNION SELECT 'Perangkat Ajar'
    ) AS kategori_base
    
    -- 1. LEFT JOIN: Total DOWNLOAD Sekolah (Join ke materis untuk tahu kategorinya)
    LEFT JOIN (
        SELECT 
            m.kategori, 
            COUNT(ua.id) AS total_download_sekolah
        FROM user_activities ua
        JOIN users u ON ua.user_id = u.id
        JOIN materis m ON ua.material_id = m.id
        WHERE u.id_sekolah = ? AND ua.activity_type = 'download'
        GROUP BY m.kategori
    ) d ON kategori_base.kategori = d.kategori
    
    -- 2. LEFT JOIN: Total REQUEST Sekolah
    LEFT JOIN (
        SELECT 
            req.kategori, 
            COUNT(req.id) AS total_request_sekolah
        FROM requests req
        JOIN users u ON req.user_id = u.id
        WHERE u.id_sekolah = ?
        GROUP BY req.kategori
    ) r ON kategori_base.kategori = r.kategori
    
    -- 3. LEFT JOIN: Total SUPPLY Global
    LEFT JOIN (
        SELECT 
            m.kategori, 
            COUNT(m.id) AS total_supply_global
        FROM materis m
        WHERE m.status = 'approved'
        GROUP BY m.kategori
    ) s ON kategori_base.kategori = s.kategori
    
    ORDER BY nilai_rasio_kebutuhan DESC
    LIMIT 1
";

// Menggunakan Prepared Statement untuk mencegah celah keamanan SQL Injection
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $id_sekolah, $id_sekolah);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
if ($row = mysqli_fetch_assoc($result)) {
    // Membulatkan desimal agar hasil rasionya rapi di frontend
    $row['nilai_rasio_kebutuhan'] = round((float) $row['nilai_rasio_kebutuhan'], 2);
    $data = $row;
}

mysqli_stmt_close($stmt);

// Mengembalikan response dalam bentuk JSON
echo json_encode($data);
?>