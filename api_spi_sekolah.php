<?php

// Memuat konfigurasi koneksi database
include 'config/database.php';

// Set header agar output dikenali sebagai JSON untuk ditarik oleh Chart.js
header('Content-Type: application/json');

// Query SQL Perhitungan School Performance Index (SPI)
$sql = "
    SELECT 
        u.id_sekolah,
        u.nama_sekolah,
        COUNT(DISTINCT u.id) AS total_guru,
        
        -- Hitung total poin kolektif berdasarkan bobot activity_type
        COALESCE(SUM(
            CASE 
                WHEN a.activity_type = 'upload_approved' THEN 7
                WHEN a.activity_type = 'download_materi' THEN 2
                WHEN a.activity_type = 'login' THEN 1
                ELSE 0 
            END
        ), 0) AS total_poin_sekolah,
        
        -- Hitung rasio SPI (Total Poin / Jumlah Guru)
        (
            COALESCE(SUM(
                CASE 
                    WHEN a.activity_type = 'upload_approved' THEN 7
                    WHEN a.activity_type = 'download_materi' THEN 2
                    WHEN a.activity_type = 'login' THEN 1
                    ELSE 0 
                END
            ), 0) / COUNT(DISTINCT u.id)
        ) AS nilai_spi

    FROM users u
    LEFT JOIN user_activities a ON u.id = a.user_id
    WHERE u.role = 'guru' -- Asumsi filter hanya mengambil entitas guru
    GROUP BY u.id_sekolah, u.nama_sekolah
    ORDER BY nilai_spi DESC
    LIMIT 5
";

$query = mysqli_query($conn, $sql);

$data = [];
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        // Membulatkan nilai SPI agar rapi (2 angka di belakang koma) untuk ditampilkan di Chart
        $row['nilai_spi'] = round((float) $row['nilai_spi'], 2);
        $data[] = $row;
    }
}

// Menghasilkan format JSON Array
echo json_encode($data);
?>