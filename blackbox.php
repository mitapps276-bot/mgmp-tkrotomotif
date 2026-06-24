<?php
// Header untuk memaksa browser mendownload file sebagai dokumen Ms Word
header("Content-Type: application/vnd.ms-word");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Disposition: attachment; filename=\"Dokumen_Uji_Blackbox_MGMP.doc\"");

// HTML content yang akan di-render menjadi MS Word
echo "
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset='utf-8'>
<style>
    body { font-family: 'Arial', sans-serif; font-size: 11pt; line-height: 1.5; }
    h1 { text-align: center; color: #2c3e50; font-size: 18pt; text-transform: uppercase; }
    h2 { color: #34495e; font-size: 14pt; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 20px; }
    h3 { color: #2980b9; font-size: 12pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; vertical-align: top; }
    th { background-color: #ecf0f1; font-weight: bold; text-align: center; }
    .pass-box { width: 20px; height: 20px; border: 1px solid #000; display: inline-block; margin-right: 5px; }
</style>
</head>
<body>

    <h1>DOKUMEN PENGUJIAN BLACKBOX (BLACK BOX TESTING)</h1>
    <p align='center'><b>SISTEM INFORMASI MGMP BERBASIS LEARNING ANALYTICS (SI-LIAK)</b></p>
    <br>
    <p><b>Tanggal Pengujian :</b> ..................................................</p>
    <p><b>Penguji           :</b> ..................................................</p>
    <p><b>Deskripsi         :</b> Dokumen ini berisi skenario pengujian fungsionalitas sistem (Blackbox Testing) untuk memastikan seluruh fitur aplikasi MGMP berjalan sesuai dengan logika bisnis yang dirancang, termasuk algoritma kompleks seperti Smart Matching dan kalkulasi Learning Analytics.</p>

    <br><br>

    <h2>MODUL 1: AUTENTIKASI & KEAMANAN SISTEM</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>AUTH-01</td>
            <td>Login Valid</td>
            <td>1. Akses halaman index.php<br>2. Masukkan Username dan Password valid<br>3. Klik Login</td>
            <td>Sistem mengarahkan pengguna ke halaman Dashboard yang sesuai dengan Role-nya (Admin/Guru/Contributor).</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>AUTH-02</td>
            <td>Validasi Anti-Brute Force (Rate Limiting)</td>
            <td>1. Masukkan kombinasi Username/Password salah sebanyak 3 kali berturut-turut.</td>
            <td>Sistem memblokir IP pengguna selama 10 menit dan memunculkan alert 'Terlalu banyak percobaan gagal'.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>AUTH-03</td>
            <td>Role-Based Access Control (Otorisasi)</td>
            <td>1. Login sebagai Guru.<br>2. Ubah URL browser secara manual ke `dashboard_admin.php`.</td>
            <td>Sistem langsung me-redirect kembali ke halaman index / dashboard guru (Akses ditolak).</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>AUTH-04</td>
            <td>Proteksi SQL Injection pada Login</td>
            <td>1. Pada form login, masukkan username: `' OR '1'='1`<br>2. Isi password sembarang.</td>
            <td>Sistem menolak login karena Prepared Statements menggagalkan eksploitasi SQL.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <h2>MODUL 2: MANAJEMEN MATERI & ANTI-DUPLIKASI (HASHING)</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>MAT-01</td>
            <td>Validasi Ekstensi File</td>
            <td>1. Buka halaman Upload Materi.<br>2. Pilih kategori 'Soal Latihan'.<br>3. Upload file berformat `.exe` atau `.php`.</td>
            <td>Sistem memunculkan alert 'Format file tidak diizinkan' dan menggagalkan upload.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>MAT-02</td>
            <td>Validasi Ukuran File</td>
            <td>1. Upload file dokumen dengan ukuran di atas 5 MB.</td>
            <td>Sistem memunculkan alert 'Ukuran file maksimal 5 MB' dan menggagalkan upload.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>MAT-03</td>
            <td>Algoritma Anti-Duplikasi (MD5 File Hashing)</td>
            <td>1. Upload file PDF bernama `tugas1.pdf`.<br>2. Ubah nama file yang sama menjadi `soal_tugas.pdf` dan upload kembali.</td>
            <td>Sistem mendeteksi isi file sama (hash cocok), lalu memunculkan alert 'Materi yang sama sudah pernah diupload'.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>MAT-04</td>
            <td>Pencatatan Download</td>
            <td>1. Login sebagai Guru A.<br>2. Masuk ke Data Materi dan download materi milik Guru B.</td>
            <td>File berhasil terunduh. Angka 'Total Download' pada materi tersebut dan poin analitik Guru B bertambah.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>MAT-05</td>
            <td>Cegah Self-Download Point</td>
            <td>1. Login sebagai Guru A.<br>2. Download materi milik sendiri.</td>
            <td>File terunduh, TETAPI sistem tidak menambahkan poin analitik / riwayat download baru ke tabel.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <h2>MODUL 3: SISTEM REQUEST & SMART MATCHING ALGORITHM</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>REQ-01</td>
            <td>Pembuatan Request Baru</td>
            <td>1. Guru membuat request 'Modul Logaritma' untuk Kelas 10.<br>2. Pastikan belum ada materi terkait di database.</td>
            <td>Sistem menyimpan request dengan status 'Pending' dan memunculkan pop-up 'Request Diteruskan!'.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>REQ-02</td>
            <td>Auto-Detect Materi yang Sudah Ada (Smart Request)</td>
            <td>1. Ada materi 'Modul Logaritma' Kelas 10 di database.<br>2. Guru membuat request dengan keyword serupa.</td>
            <td>Sistem langsung mengubah status menjadi 'Selesai' dan memunculkan pop-up 'Materi Ditemukan!' tanpa perlu menunggu Admin.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>REQ-03</td>
            <td>Smart Matching (Trigger dari Upload)</td>
            <td>1. Guru A memiliki request 'Aljabar' (Pending).<br>2. Guru B mengupload materi berjudul 'Pelajaran Aljabar'.</td>
            <td>Setelah Guru B berhasil upload, sistem memindai request yang pending dan otomatis mengubah status request Guru A menjadi 'Selesai' dengan catatan sistem.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>REQ-04</td>
            <td>Notifikasi Lonceng (Bell Notification)</td>
            <td>1. Setelah skenario REQ-03 terjadi, login sebagai Guru A.<br>2. Cek lonceng notifikasi di sudut kanan.</td>
            <td>Lonceng menampilkan angka merah. Saat diklik, menampilkan catatan sistem bahwa materi telah dibantu upload.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <h2>MODUL 4: LEARNING ANALYTICS & GAMIFIKASI</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>ANL-01</td>
            <td>Pencatatan Poin Harian (Login)</td>
            <td>1. Guru login untuk pertama kalinya hari ini.</td>
            <td>Tabel `login_activity` mencatat aktivitas. Skor Analitik guru bertambah 1 Poin.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>ANL-02</td>
            <td>Validasi Batas Poin Login Harian</td>
            <td>1. Guru logout dan login kembali di hari yang sama.</td>
            <td>Sistem hanya memperbarui jam login (`login_time`), poin analitik login tetap (tidak dobel).</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>ANL-03</td>
            <td>Perhitungan Multiplier Skor</td>
            <td>1. Guru berhasil upload 1 materi.<br>2. Materi di-download oleh 2 guru lain.</td>
            <td>Skor guru diakumulasikan secara tepat: (1 Upload * 7) + (2 Download * 2) = 11 Poin.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>ANL-04</td>
            <td>Intelligent Analytics (Status Guru)</td>
            <td>1. Buka dashboard guru.<br>2. Cek kotak 'Intelligent Analytics' di bagian bawah.</td>
            <td>Sistem memberikan diagnosis AI (cth: 'Mulai Berkembang' / 'Top Contributor') beserta rekomendasi teks yang sesuai dengan rasio poin guru tersebut.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>ANL-05</td>
            <td>School Performance Index (SPI)</td>
            <td>1. Buka menu Analytics.<br>2. Cek bagian 'School Performance Index'.</td>
            <td>Sistem mengelompokkan poin berdasarkan nama sekolah dengan agregasi yang akurat dari guru-guru di sekolah tersebut.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <h2>MODUL 5: KONTRIBUTOR EKSTERNAL & VERIFIKASI ADMIN</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>EXT-01</td>
            <td>Upload oleh Kontributor</td>
            <td>1. Login sebagai External Contributor.<br>2. Upload materi baru.</td>
            <td>Materi masuk ke database dengan status 'Pending'. Kontributor tidak dapat melihat file tersebut di tabel publik.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>EXT-02</td>
            <td>Verifikasi Admin (Approve)</td>
            <td>1. Login Admin > Review Contributor.<br>2. Klik 'Approve' pada materi di atas.</td>
            <td>Status berubah menjadi 'Approved'. Materi kini muncul di folder 'Kontributor External' pada menu Data Materi publik.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>EXT-03</td>
            <td>Verifikasi Admin (Reject dengan Alasan)</td>
            <td>1. Admin melakukan 'Reject' pada materi lain dengan menyertakan teks alasan penolakan.</td>
            <td>Pada Dashboard Kontributor, muncul kotak merah berisi notifikasi penolakan beserta alasan spesifik dari Admin.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <h2>MODUL 6: KELOLA INFORMASI & UI/UX</h2>
    
    <table>
        <tr>
            <th width='5%'>ID</th>
            <th width='20%'>Fungsi yang Diuji</th>
            <th width='25%'>Langkah Pengujian</th>
            <th width='30%'>Hasil yang Diharapkan (Expected Result)</th>
            <th width='20%'>Status (Pass/Fail)</th>
        </tr>
        <tr>
            <td>UIX-01</td>
            <td>Update Teks Landing Page</td>
            <td>1. Admin masuk ke Kelola Informasi Umum.<br>2. Ubah Judul Hero dan Simpan.</td>
            <td>Halaman beranda publik (`index.php`) langsung menampilkan perubahan teks secara real-time.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>UIX-02</td>
            <td>Pagination Data Materi</td>
            <td>1. Masuk ke Data Materi.<br>2. Buka folder yang materinya lebih dari 4.</td>
            <td>Sistem membatasi tampilan hanya 4 baris, dan memunculkan tombol 'Prev' & 'Next' untuk menggeser daftar.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
        <tr>
            <td>UIX-03</td>
            <td>Upload / Ganti Foto Profil</td>
            <td>1. Pada Dashboard, klik tombol 'Ganti Foto'.<br>2. Upload foto `.jpg` di bawah 2MB.</td>
            <td>Foto langsung ter-update di dashboard, header, tabel aktivitas, dan leaderboard tanpa error.</td>
            <td>[  ] Pass<br>[  ] Fail<br>Catatan:</td>
        </tr>
    </table>

    <br><br><br>
    <table style='border: none;'>
        <tr>
            <td style='border: none; text-align: center; width: 50%;'></td>
            <td style='border: none; text-align: center; width: 50%;'>
                <p>Mengetahui,</p>
                <br><br><br><br>
                <p><b>(_________________________)</b></p>
                <p>Penguji / Quality Assurance</p>
            </td>
        </tr>
    </table>

</body>
</html>
";
?>
