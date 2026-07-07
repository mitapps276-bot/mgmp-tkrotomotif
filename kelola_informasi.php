<?php
session_start();
include 'config/database.php';

// =======================
// CEK LOGIN ADMIN
// =======================
if(!isset($_SESSION['login']) || $_SESSION['role_id'] != 1){
    header("Location:index.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// Simpan tab mana yang terakhir dibuka
$active_tab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : 'portal';
unset($_SESSION['active_tab']);

// =======================
// BUAT TABEL JIKA BELUM ADA
// =======================
$cek_table_landing = mysqli_query($conn, "SHOW TABLES LIKE 'landing_settings'");
if(mysqli_num_rows($cek_table_landing) == 0){
    mysqli_query($conn, "
        CREATE TABLE landing_settings (
            id INT PRIMARY KEY,
            hero_title TEXT,
            hero_subtitle TEXT,
            hero_image VARCHAR(255),
            about_title TEXT,
            about_desc1 TEXT,
            about_desc2 TEXT,
            about_image VARCHAR(255),
            analytic_title TEXT,
            analytic_subtitle TEXT,
            login_title TEXT,
            login_desc TEXT
        )
    ");
    $q_insert = "INSERT INTO landing_settings (id, hero_title, hero_subtitle, hero_image, about_title, about_desc1, about_desc2, about_image, analytic_title, analytic_subtitle, login_title, login_desc) VALUES (1, 'Membangun Sinergi<br><span>Pendidikan Berkualitas</span>', 'Wadah kolaborasi antar tenaga pendidik untuk menciptakan ekosistem pembelajaran yang inovatif, terstruktur, dan berbasis data Learning Analytics cerdas.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Patung_Catur_Muka%2C_Denpasar.jpg/1280px-Patung_Catur_Muka%2C_Denpasar.jpg', 'Ekosistem Terpadu Berbasis Kecerdasan Algoritmik', 'Sistem Informasi Musyawarah Guru Mata Pelajaran (MGMP) hadir sebagai inovasi akademik modern yang memadukan repositori perangkat ajar dengan komputasi cerdas. Platform ini dirancang untuk mendistribusikan materi pembelajaran secara merata, terstruktur, dan terbebas dari duplikasi data berkat fitur pemindaian sidik jari file (Hashing).', 'Lebih dari sekadar ruang penyimpanan <i>cloud</i>, platform ini dibekali fitur <strong>Smart Matching</strong> yang mampu mendeteksi ketersediaan request materi secara otomatis. Didukung oleh teknologi <strong>Learning Analytics</strong>, platform ini secara <i>real-time</i> menghasilkan evaluasi performa kolektif sekolah (SPI) dan sistem rekomendasi kolaborasi (KSI) guna mendorong interaksi aktif setiap tenaga pendidik.', 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=800&q=80', 'Infrastruktur Kolaborasi Akademik Digital', 'Platform terintegrasi yang dirancang secara spesifik untuk mengoptimalkan produktivitas pedagogis dan memfasilitasi sinergi strategis antar tenaga pendidik.', 'Portal Sistem Informasi Akademik', 'Akses khusus bagi administrator, tenaga pendidik, dan kontributor terdaftar untuk masuk ke dalam ruang kerja virtual MGMP.')";
    mysqli_query($conn, $q_insert);
}

// Tambah kolom pengaturan posisi background
$cek_kolom_x = mysqli_query($conn, "SHOW COLUMNS FROM landing_settings LIKE 'hero_image_x'");
if(mysqli_num_rows($cek_kolom_x) == 0){
    mysqli_query($conn, "ALTER TABLE landing_settings ADD hero_image_x VARCHAR(10) DEFAULT '50'");
}
$cek_kolom_y = mysqli_query($conn, "SHOW COLUMNS FROM landing_settings LIKE 'hero_image_y'");
if(mysqli_num_rows($cek_kolom_y) == 0){
    mysqli_query($conn, "ALTER TABLE landing_settings ADD hero_image_y VARCHAR(10) DEFAULT '50'");
}

// Tambah kolom baru untuk dynamic index
$new_cols = [
    'topbar_text' => 'TEXT',
    'navbar_logo_text' => 'TEXT',
    'about_list1' => 'TEXT',
    'about_list2' => 'TEXT',
    'about_list3' => 'TEXT',
    'gallery_title' => 'VARCHAR(255)',
    'gallery_desc' => 'TEXT',
    'feature1_icon' => 'VARCHAR(50)',
    'feature1_title' => 'VARCHAR(255)',
    'feature1_desc' => 'TEXT',
    'feature2_icon' => 'VARCHAR(50)',
    'feature2_title' => 'VARCHAR(255)',
    'feature2_desc' => 'TEXT',
    'feature3_icon' => 'VARCHAR(50)',
    'feature3_title' => 'VARCHAR(255)',
    'feature3_desc' => 'TEXT',
    'feature4_icon' => 'VARCHAR(50)',
    'feature4_title' => 'VARCHAR(255)',
    'feature4_desc' => 'TEXT',
    'feature5_icon' => 'VARCHAR(50)',
    'feature5_title' => 'VARCHAR(255)',
    'feature5_desc' => 'TEXT',
    'feature6_icon' => 'VARCHAR(50)',
    'feature6_title' => 'VARCHAR(255)',
    'feature6_desc' => 'TEXT',
    'feature7_icon' => 'VARCHAR(50)',
    'feature7_title' => 'VARCHAR(255)',
    'feature7_desc' => 'TEXT',
    'feature8_icon' => 'VARCHAR(50)',
    'feature8_title' => 'VARCHAR(255)',
    'feature8_desc' => 'TEXT',
    'feature9_icon' => 'VARCHAR(50)',
    'feature9_title' => 'VARCHAR(255)',
    'feature9_desc' => 'TEXT',
    'footer_title' => 'VARCHAR(255)',
    'footer_desc' => 'TEXT',
    'footer_copyright' => 'VARCHAR(255)',
    'footer_contact_title' => 'VARCHAR(255)',
    'footer_contact_1_text' => 'VARCHAR(255)',
    'footer_contact_1_url' => 'VARCHAR(255)',
    'footer_contact_2_text' => 'VARCHAR(255)',
    'footer_contact_2_url' => 'VARCHAR(255)',
    'footer_contact_3_text' => 'VARCHAR(255)',
    'footer_contact_3_url' => 'VARCHAR(255)'
];
foreach($new_cols as $col => $type){
    $cek_col = mysqli_query($conn, "SHOW COLUMNS FROM landing_settings LIKE '$col'");
    if(mysqli_num_rows($cek_col) == 0){
        mysqli_query($conn, "ALTER TABLE landing_settings ADD $col $type");
    }
}

$cek_table_galeri = mysqli_query($conn, "SHOW TABLES LIKE 'gallery'");
if(mysqli_num_rows($cek_table_galeri) == 0){
    mysqli_query($conn, "
        CREATE TABLE gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// =======================
// UPDATE PENGATURAN PORTAL
// =======================
if(isset($_POST['update_landing'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Error: Token keamanan (CSRF) tidak valid!");

    $hero_title = mysqli_real_escape_string($conn, trim($_POST['hero_title']));
    $hero_subtitle = mysqli_real_escape_string($conn, trim($_POST['hero_subtitle']));
    $about_title = mysqli_real_escape_string($conn, trim($_POST['about_title']));
    $about_desc1 = mysqli_real_escape_string($conn, trim($_POST['about_desc1']));
    $about_desc2 = mysqli_real_escape_string($conn, trim($_POST['about_desc2']));
    $analytic_title = mysqli_real_escape_string($conn, trim($_POST['analytic_title']));
    $analytic_subtitle = mysqli_real_escape_string($conn, trim($_POST['analytic_subtitle']));
    $login_title = mysqli_real_escape_string($conn, trim(isset($_POST['login_title']) ? $_POST['login_title'] : ''));
    $login_desc = mysqli_real_escape_string($conn, trim(isset($_POST['login_desc']) ? $_POST['login_desc'] : ''));
    $hero_image_x = mysqli_real_escape_string($conn, trim(isset($_POST['hero_image_x']) ? $_POST['hero_image_x'] : '50'));
    $hero_image_y = mysqli_real_escape_string($conn, trim(isset($_POST['hero_image_y']) ? $_POST['hero_image_y'] : '50'));

    $topbar_text = mysqli_real_escape_string($conn, trim(isset($_POST['topbar_text']) ? $_POST['topbar_text'] : 'Selamat Datang di SI-LIAK ( Sistem Informasi Learning Integration & Analitik Kinerja )'));
    $navbar_logo_text = mysqli_real_escape_string($conn, trim(isset($_POST['navbar_logo_text']) ? $_POST['navbar_logo_text'] : 'SI-LIAK MGMP'));
    $about_list1 = mysqli_real_escape_string($conn, trim(isset($_POST['about_list1']) ? $_POST['about_list1'] : 'Sistem pemenuhan permintaan materi otomatis (Smart Matching) dan anti-duplikasi file'));
    $about_list2 = mysqli_real_escape_string($conn, trim(isset($_POST['about_list2']) ? $_POST['about_list2'] : 'Pengukuran Learning Analytics (SPI & KSI) beserta sistem rekomendasi cerdas'));
    $about_list3 = mysqli_real_escape_string($conn, trim(isset($_POST['about_list3']) ? $_POST['about_list3'] : 'Jalur kontribusi khusus bagi praktisi pendidikan eksternal untuk pengayaan materi'));
    $gallery_title = mysqli_real_escape_string($conn, trim(isset($_POST['gallery_title']) ? $_POST['gallery_title'] : 'Kegiatan MGMP'));
    $gallery_desc = mysqli_real_escape_string($conn, trim(isset($_POST['gallery_desc']) ? $_POST['gallery_desc'] : ''));
    $feature1_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature1_icon']) ? $_POST['feature1_icon'] : '📚'));
    $feature1_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature1_title']) ? $_POST['feature1_title'] : 'Repositori Digital Terintegrasi'));
    $feature1_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature1_desc']) ? $_POST['feature1_desc'] : 'Fasilitas penyimpanan komprehensif yang dirancang khusus untuk mengarsipkan dan mendistribusikan dokumen pedagogis, modul ajar, serta instrumen evaluasi secara tersentralisasi guna menjamin aksesibilitas dan keamanan data berkelanjutan.'));
    $feature2_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature2_icon']) ? $_POST['feature2_icon'] : '📊'));
    $feature2_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature2_title']) ? $_POST['feature2_title'] : 'Sistem Meritokrasi Digital'));
    $feature2_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature2_desc']) ? $_POST['feature2_desc'] : 'Mekanisme terotomatisasi yang mengukur dan memberikan atribusi poin prestasi secara kuantitatif berdasarkan tingkat partisipasi aktif tenaga pendidik dalam berbagi (upload) dan memanfaatkan (download) sumber daya pembelajaran.'));
    $feature3_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature3_icon']) ? $_POST['feature3_icon'] : '🤝'));
    $feature3_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature3_title']) ? $_POST['feature3_title'] : 'Jalur Kontribusi Akademisi Eksternal'));
    $feature3_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature3_desc']) ? $_POST['feature3_desc'] : 'Menyediakan kanal khusus bagi para akademisi, dosen, dan praktisi pendidikan untuk menyumbangkan materi ajar terkurasi guna memperkaya dan meningkatkan mutu referensi pedagogis dalam ekosistem.'));
    $feature4_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature4_icon']) ? $_POST['feature4_icon'] : '👥'));
    $feature4_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature4_title']) ? $_POST['feature4_title'] : 'Jejaring Kolaborasi Referensi'));
    $feature4_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature4_desc']) ? $_POST['feature4_desc'] : 'Infrastruktur interaktif yang memungkinkan tenaga pendidik untuk mengajukan permohonan spesifik terkait bahan ajar, memfasilitasi pertukaran materi secara responsif antar institusi.'));
    $feature5_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature5_icon']) ? $_POST['feature5_icon'] : '📈'));
    $feature5_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature5_title']) ? $_POST['feature5_title'] : 'Inovasi & Adaptasi Pedagogis'));
    $feature5_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature5_desc']) ? $_POST['feature5_desc'] : 'Mendorong pengembangan kompetensi instruksional melalui metode observasi, adopsi, dan modifikasi terhadap instrumen pembelajaran unggulan lintas institusi guna memperkaya variasi pendekatan edukatif.'));
    $feature6_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature6_icon']) ? $_POST['feature6_icon'] : '🔒'));
    $feature6_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature6_title']) ? $_POST['feature6_title'] : 'Sistem Proteksi Integritas Data'));
    $feature6_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature6_desc']) ? $_POST['feature6_desc'] : 'Infrastruktur penyimpanan berbasis komputasi cerdas (Hashing) yang secara otomatis memindai dan memfilter redundansi file, memastikan efisiensi kapasitas serta validitas repositori.'));
    $feature7_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature7_icon']) ? $_POST['feature7_icon'] : '🏆'));
    $feature7_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature7_title']) ? $_POST['feature7_title'] : 'Leaderboard Kinerja Akademik'));
    $feature7_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature7_desc']) ? $_POST['feature7_desc'] : 'Sistem pemeringkatan transparan berbasis data analitik yang dirancang untuk menstimulasi motivasi intrinsik tenaga pendidik dalam mengoptimalkan kontribusi pedagogis di tingkat ekosistem kota.'));
    $feature8_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature8_icon']) ? $_POST['feature8_icon'] : '📢'));
    $feature8_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature8_title']) ? $_POST['feature8_title'] : 'Kanal Diseminasi Informasi'));
    $feature8_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature8_desc']) ? $_POST['feature8_desc'] : 'Infrastruktur komunikasi satu arah yang menjamin penyampaian informasi, jadwal, dan edaran resmi dari administrator MGMP secara terstruktur dan terdokumentasi langsung ke dasbor pengguna.'));
    $feature9_icon = mysqli_real_escape_string($conn, trim(isset($_POST['feature9_icon']) ? $_POST['feature9_icon'] : '🏫'));
    $feature9_title = mysqli_real_escape_string($conn, trim(isset($_POST['feature9_title']) ? $_POST['feature9_title'] : 'Indeks Kinerja Institusional'));
    $feature9_desc = mysqli_real_escape_string($conn, trim(isset($_POST['feature9_desc']) ? $_POST['feature9_desc'] : 'Sistem analitik yang mengagregasi skor partisipasi individual dari setiap tenaga pendidik menjadi sebuah Indeks Kinerja Institusional (SPI) terukur, merepresentasikan kontribusi kolektif dan reputasi akademik sekolah dalam ekosistem.'));
    $footer_title = mysqli_real_escape_string($conn, trim(isset($_POST['footer_title']) ? $_POST['footer_title'] : 'SI-LIAK MGMP KOTA DENPASAR'));
    $footer_desc = mysqli_real_escape_string($conn, trim(isset($_POST['footer_desc']) ? $_POST['footer_desc'] : 'Sistem Informasi Learning Integration & Analitik Kinerja (SI-LIAK) Musyawarah Guru Mata Pelajaran. Dedikasi terhadap peningkatan mutu pendidikan melalui digitalisasi pendistribusian materi dan analisis data kinerja yang presisi.'));
    $footer_copyright = mysqli_real_escape_string($conn, trim(isset($_POST['footer_copyright']) ? $_POST['footer_copyright'] : 'Sistem Informasi MGMP. Hak Cipta Dilindungi Undang-Undang.'));
    $footer_contact_title = mysqli_real_escape_string($conn, trim(isset($_POST['footer_contact_title']) ? $_POST['footer_contact_title'] : 'Layanan Kontak'));

    $footer_contact_1_text = mysqli_real_escape_string($conn, trim(isset($_POST['footer_contact_1_text']) ? $_POST['footer_contact_1_text'] : 'Bantuan Administrasi'));
    $url1_raw = trim(isset($_POST['footer_contact_1_url']) ? $_POST['footer_contact_1_url'] : '#');
    if (!empty($url1_raw) && $url1_raw !== '#' && !preg_match('/^(https?|mailto|tel):/i', $url1_raw)) {
        $url1_raw = 'mailto:' . $url1_raw;
    }
    $footer_contact_1_url = mysqli_real_escape_string($conn, $url1_raw);

    $footer_contact_2_text = mysqli_real_escape_string($conn, trim(isset($_POST['footer_contact_2_text']) ? $_POST['footer_contact_2_text'] : 'Pendaftaran Kontributor'));
    $url2_raw = trim(isset($_POST['footer_contact_2_url']) ? $_POST['footer_contact_2_url'] : '#');
    if (!empty($url2_raw) && $url2_raw !== '#' && !preg_match('/^(https?|mailto|tel):/i', $url2_raw)) {
        $url2_raw = 'mailto:' . $url2_raw;
    }
    $footer_contact_2_url = mysqli_real_escape_string($conn, $url2_raw);

    $footer_contact_3_text = mysqli_real_escape_string($conn, trim(isset($_POST['footer_contact_3_text']) ? $_POST['footer_contact_3_text'] : 'Kebijakan Privasi'));
    $url3_raw = trim(isset($_POST['footer_contact_3_url']) ? $_POST['footer_contact_3_url'] : '#');
    if (!empty($url3_raw) && $url3_raw !== '#' && !preg_match('/^(https?|mailto|tel):/i', $url3_raw)) {
        $url3_raw = 'mailto:' . $url3_raw;
    }
    $footer_contact_3_url = mysqli_real_escape_string($conn, $url3_raw);

    function uploadGambar($file_input_name, $label_nama) {
        if(isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE){
            $err = $_FILES[$file_input_name]['error'];
            $size = $_FILES[$file_input_name]['size'];
            
            if($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE || $size > 2 * 1024 * 1024) {
                $_SESSION['error'] = "Gagal upload $label_nama: Ukuran file terlalu besar (Maksimal 2MB)!";
                return null;
            } elseif ($err != UPLOAD_ERR_OK) {
                $_SESSION['error'] = "Gagal upload $label_nama: Terjadi kesalahan sistem (Kode Error: $err).";
                return null;
            }

            $name = $_FILES[$file_input_name]['name'];
            $tmp_name = $_FILES[$file_input_name]['tmp_name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if(!in_array($ext, $allowed)){
                $_SESSION['error'] = "Gagal upload $label_nama: Format harus JPG, PNG, atau WEBP!";
                return null;
            }

            $upload_dir = "assets/uploads/landing";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_name = time() . "_" . preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
            $target_file = $upload_dir . "/" . $new_name;
            
            if(move_uploaded_file($tmp_name, $target_file)) {
                return $target_file;
            } else {
                $_SESSION['error'] = "Gagal upload $label_nama: File tidak dapat dipindahkan ke folder tujuan.";
                return null;
            }
        }
        return null;
    }

    $hero_image_sql = ""; $about_image_sql = "";
    $new_hero = uploadGambar('hero_image', 'Gambar Latar'); if($new_hero) $hero_image_sql = "hero_image = '$new_hero',";
    $new_about = uploadGambar('about_image', 'Gambar Profil'); if($new_about) $about_image_sql = "about_image = '$new_about',";

    $q_update = "UPDATE landing_settings SET $hero_image_sql $about_image_sql hero_title = '$hero_title', hero_subtitle = '$hero_subtitle', hero_image_x = '$hero_image_x', hero_image_y = '$hero_image_y', about_title = '$about_title', about_desc1 = '$about_desc1', about_desc2 = '$about_desc2', analytic_title = '$analytic_title', analytic_subtitle = '$analytic_subtitle', login_title = '$login_title', login_desc = '$login_desc', topbar_text = '$topbar_text', navbar_logo_text = '$navbar_logo_text', about_list1 = '$about_list1', about_list2 = '$about_list2', about_list3 = '$about_list3', gallery_title = '$gallery_title', gallery_desc = '$gallery_desc', feature1_icon = '$feature1_icon', feature1_title = '$feature1_title', feature1_desc = '$feature1_desc', feature2_icon = '$feature2_icon', feature2_title = '$feature2_title', feature2_desc = '$feature2_desc', feature3_icon = '$feature3_icon', feature3_title = '$feature3_title', feature3_desc = '$feature3_desc', feature4_icon = '$feature4_icon', feature4_title = '$feature4_title', feature4_desc = '$feature4_desc', feature5_icon = '$feature5_icon', feature5_title = '$feature5_title', feature5_desc = '$feature5_desc', feature6_icon = '$feature6_icon', feature6_title = '$feature6_title', feature6_desc = '$feature6_desc', feature7_icon = '$feature7_icon', feature7_title = '$feature7_title', feature7_desc = '$feature7_desc', feature8_icon = '$feature8_icon', feature8_title = '$feature8_title', feature8_desc = '$feature8_desc', feature9_icon = '$feature9_icon', feature9_title = '$feature9_title', feature9_desc = '$feature9_desc', footer_title = '$footer_title', footer_desc = '$footer_desc', footer_copyright = '$footer_copyright', footer_contact_title = '$footer_contact_title', footer_contact_1_text = '$footer_contact_1_text', footer_contact_1_url = '$footer_contact_1_url', footer_contact_2_text = '$footer_contact_2_text', footer_contact_2_url = '$footer_contact_2_url', footer_contact_3_text = '$footer_contact_3_text', footer_contact_3_url = '$footer_contact_3_url' WHERE id = 1";
    
    if(mysqli_query($conn, $q_update)){
        if(!isset($_SESSION['error'])){
            $_SESSION['success'] = "Pengaturan Halaman Portal berhasil diperbarui!";
        } else {
            $_SESSION['success'] = "Teks berhasil disimpan, namun ada masalah pada saat upload gambar.";
        }
    } else {
        error_log("DB Error (Update Portal): " . mysqli_error($conn));
        $_SESSION['error'] = "Terjadi kesalahan sistem saat menyimpan data portal. Silakan hubungi teknisi.";
    }

    $_SESSION['active_tab'] = 'portal';
    header("Location: kelola_informasi.php");
    exit;
}

// =======================
// TAMBAH GALERI
// =======================
if(isset($_POST['tambah_galeri'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Error: Token (CSRF) tidak valid!");

    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    if(isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK){
        // Validasi ukuran file
        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = "Ukuran file galeri maksimal 2MB.";
            $_SESSION['active_tab'] = 'galeri';
            header("Location: kelola_informasi.php");
            exit;
        }
        $tmp_name = $_FILES['image']['tmp_name'];
        $name = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if(in_array($ext, $allowed)){
            $upload_dir = "assets/uploads/gallery";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_name = time() . "_" . preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
            $target_file = $upload_dir . "/" . $new_name;
            
            if(move_uploaded_file($tmp_name, $target_file)){
                mysqli_query($conn, "INSERT INTO gallery (title, description, image_path) VALUES ('$title', '$description', '$target_file')");
                $_SESSION['success'] = "Foto galeri berhasil ditambahkan!";
            }
        } else {
            $_SESSION['error'] = "Format file galeri harus JPG, PNG, atau WEBP";
        }
    } else {
        $_SESSION['error'] = "Gagal mengupload file galeri.";
    }
    $_SESSION['active_tab'] = 'galeri';
    header("Location: kelola_informasi.php");
    exit;
}

// =======================
// HAPUS GALERI
// =======================
if(isset($_GET['hapus_galeri'])){
    if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) die("Error: Token (CSRF) tidak valid!");
    
    $id = (int)$_GET['hapus_galeri'];
    $q = mysqli_query($conn, "SELECT image_path FROM gallery WHERE id = '$id'");
    if($r = mysqli_fetch_assoc($q)){
        if(file_exists($r['image_path'])) unlink($r['image_path']);
        mysqli_query($conn, "DELETE FROM gallery WHERE id = '$id'");
        $_SESSION['success'] = "Foto galeri berhasil dihapus!";
    }
    $_SESSION['active_tab'] = 'galeri';
    header("Location: kelola_informasi.php");
    exit;
}

// =======================
// EDIT GALERI
// =======================
if(isset($_POST['edit_galeri'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Error: Token (CSRF) tidak valid!");

    $id = (int)$_POST['edit_id'];
    $title = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['edit_description']));

    $image_sql = "";
    if(isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] == UPLOAD_ERR_OK){
        // Validasi ukuran file
        if ($_FILES['edit_image']['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = "Ukuran file galeri maksimal 2MB.";
            $_SESSION['active_tab'] = 'galeri';
            header("Location: kelola_informasi.php");
            exit;
        }
        $tmp_name = $_FILES['edit_image']['tmp_name'];
        $name = $_FILES['edit_image']['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if(in_array($ext, $allowed)){
            $upload_dir = "assets/uploads/gallery";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_name = time() . "_edit_" . preg_replace('/[^a-zA-Z0-9.-]/', '_', $name);
            $target_file = $upload_dir . "/" . $new_name;
            
            if(move_uploaded_file($tmp_name, $target_file)){
                // Hapus file lama dari server
                $q_old = mysqli_query($conn, "SELECT image_path FROM gallery WHERE id = '$id'");
                if($r_old = mysqli_fetch_assoc($q_old)){
                    if(file_exists($r_old['image_path'])) unlink($r_old['image_path']);
                }
                $image_sql = ", image_path = '$target_file'";
            }
        } else {
            $_SESSION['error'] = "Format file galeri harus JPG, PNG, atau WEBP";
        }
    }

    if(!isset($_SESSION['error'])) {
        $upd = mysqli_query($conn, "UPDATE gallery SET title = '$title', description = '$description' $image_sql WHERE id = '$id'");
        if($upd){
            $_SESSION['success'] = "Data galeri berhasil diperbarui!";
        } else {
            error_log("DB Error (Edit Galeri): " . mysqli_error($conn));
            $_SESSION['error'] = "Terjadi kesalahan sistem saat memperbarui galeri.";
        }
    }
    
    $_SESSION['active_tab'] = 'galeri';
    header("Location: kelola_informasi.php");
    exit;
}

$query_ls = mysqli_query($conn, "SELECT * FROM landing_settings WHERE id = 1");
$ls = mysqli_fetch_assoc($query_ls);
$query_galeri = mysqli_query($conn, "SELECT * FROM gallery ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kelola Informasi Umum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{ font-family:Arial; background:#f4f6f9; margin:0; }
        .wrapper{ display:flex; min-height:100vh; }
        .sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; }
        .sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
        .sidebar .menu a:hover{ background:#34495e; }
        .main-content{ flex:1; padding:30px; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 0 10px rgba(0,0,0,0.05); margin-bottom:30px; }
        h2 { margin-top:0; color:#2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h3 { color: #3498db; margin-top: 0; }
        
        .input-group { margin-bottom:15px; }
        label { display:block; margin-bottom:5px; font-weight:bold; color:#555; font-size:14px; }
        input[type="text"], textarea { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-family: inherit; }
        input[type="file"] { border:1px dashed #ccc; width:100%; padding:10px; border-radius:6px; background:#f9f9f9; box-sizing:border-box; }
        
        .btn-submit { background:#27ae60; color:white; border:none; padding:14px 25px; border-radius:6px; font-size:16px; font-weight:bold; cursor:pointer; width:100%; transition: 0.3s; }
        .btn-submit:hover { background:#219150; }
        
        .custom-file-input { display: none !important; }
        .custom-file-btn { display: block !important; background: #ecf0f1 !important; border: 1px dashed #bdc3c7 !important; padding: 12px 15px !important; border-radius: 6px !important; cursor: pointer !important; font-weight: bold !important; color: #2c3e50 !important; transition: 0.3s !important; text-align: center !important; font-size: 14px !important; margin-bottom: 0 !important; }
        .custom-file-btn:hover { background: #dfe6e9 !important; border-color: #3498db !important; color: #3498db !important; }
        .file-name { display: block; margin-top: 5px; font-size: 13px; color: #27ae60; font-weight: bold; }

        .image-preview { width: 100%; max-width: 300px; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 10px; border: 1px solid #ccc; }
        .info-text { font-size: 12px; color: #7f8c8d; margin-top: 5px; display: block; }

        .tab-container { display: flex; gap: 10px; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 25px; }
        .tab-btn { padding: 12px 25px; cursor: pointer; border: none; background: #ecf0f1; border-radius: 6px; font-weight: bold; color: #2c3e50; transition: 0.3s; font-size: 15px; }
        .tab-btn:hover { background: #dfe6e9; }
        .tab-btn.active { background: #3498db; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        table { width:100%; border-collapse:collapse; }
        table th { background:#2c3e50; color:white; padding:12px; text-align:left; }
        table td { padding:12px; border-bottom:1px solid #eee; vertical-align:top; }
        .btn-hapus { background:#e74c3c; color:white; padding:6px 12px; text-decoration:none; border-radius:4px; font-size:12px; font-weight:bold; }
        .btn-edit { background:#f39c12; color:white; border:none; padding:6px 12px; text-decoration:none; border-radius:4px; font-size:12px; font-weight:bold; cursor:pointer; margin-right:5px; }
        .btn-edit:hover { background:#d68910; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-content { background-color: white; padding: 25px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #2c3e50; }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; }
        .close-btn:hover { color: #e74c3c; }

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
            .tab-container { flex-direction: column; }
            .card { padding: 15px; }
        }
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
        <div class="logo">ADMIN PANEL</div>
        <div class="menu">
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="upload_materi.php">Upload Materi</a>
            <a href="review_materials.php">Review Contributor</a>
            <a href="kelola_request.php">Request Materi</a>
            <a href="analytics.php">Analytics</a>
            <a href="kelola_informasi.php" style="background:#34495e;">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="log_aktivitas.php">Log Aktivitas (Audit)</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    <div class="main-content">
        <?php if(isset($_SESSION['success'])){ ?>
            <div style="background:#27ae60; color:white; padding:15px; border-radius:8px; margin-bottom:20px;">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php } ?>
        <?php if(isset($_SESSION['error'])){ ?>
            <div style="background:#e74c3c; color:white; padding:15px; border-radius:8px; margin-bottom:20px;">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php } ?>

        <div class="tab-container">
            <button class="tab-btn <?= $active_tab == 'portal' ? 'active' : '' ?>" onclick="openTab(event, 'portal')">1. Pengaturan Teks & Gambar Portal</button>
            <button class="tab-btn <?= $active_tab == 'galeri' ? 'active' : '' ?>" onclick="openTab(event, 'galeri')">2. Dokumentasi Galeri</button>
        </div>

        <!-- TAB PORTAL -->
        <div id="portal" class="tab-content <?= $active_tab == 'portal' ? 'active' : '' ?>">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                <div class="card">
                    <h3>0. Pengaturan Header & Navbar</h3>
                    <div class="input-group">
                        <label>Teks Topbar</label>
                        <input type="text" name="topbar_text" required value="<?= htmlspecialchars(isset($ls['topbar_text']) ? $ls['topbar_text'] : 'Selamat Datang di SI-LIAK ( Sistem Informasi Learning Integration & Analitik Kinerja )'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Teks Logo Navbar</label>
                        <input type="text" name="navbar_logo_text" required value="<?= htmlspecialchars(isset($ls['navbar_logo_text']) ? $ls['navbar_logo_text'] : 'SI-LIAK MGMP'); ?>">
                    </div>
                </div>
                <div class="card">
                    <h3>1. Bagian Beranda Utama (Hero Section)</h3>
                    <div class="input-group">
                        <label>Judul Utama (Mendukung tag HTML seperti &lt;br&gt; dan &lt;span&gt;)</label>
                        <input type="text" name="hero_title" required value="<?= htmlspecialchars($ls['hero_title']); ?>">
                    </div>
                    <div class="input-group">
                        <label>Sub-Judul (Deskripsi bawah judul)</label>
                        <textarea name="hero_subtitle" rows="3" required><?= htmlspecialchars($ls['hero_subtitle']); ?></textarea>
                    </div>
                    <div class="input-group">
                        <label>Gambar Latar Belakang</label>
                        <?php if(!empty($ls['hero_image'])): ?><img src="<?= htmlspecialchars($ls['hero_image']); ?>" class="image-preview" id="preview_hero"><?php endif; ?>
                        <label for="hero_image" class="custom-file-btn">📸 Pilih Gambar</label>
                        <input type="file" name="hero_image" id="hero_image" class="custom-file-input" accept="image/*" onchange="document.getElementById('name_hero').innerText = this.files.length > 0 ? 'File terpilih: ' + this.files[0].name : '';">
                        <span id="name_hero" class="file-name"></span>
                        <span class="info-text">* Biarkan kosong jika tidak mengubah gambar latar. (Saran ukuran: 1920x1080, Maksimal 2MB)</span>
                    </div>
                    <div class="input-group">
                        <label>Posisi Latar Belakang (X - Kiri/Kanan): <span id="val_x"><?= htmlspecialchars(isset($ls['hero_image_x']) ? $ls['hero_image_x'] : '50'); ?></span>%</label>
                        <input type="range" name="hero_image_x" id="hero_x" min="-100" max="200" value="<?= htmlspecialchars(isset($ls['hero_image_x']) ? $ls['hero_image_x'] : '50'); ?>" oninput="document.getElementById('val_x').innerText = this.value; updatePreviewPosition();" style="width: 100%; border: none; padding: 0; background: transparent;">
                    </div>
                    <div class="input-group">
                        <label>Posisi Latar Belakang (Y - Atas/Bawah): <span id="val_y"><?= htmlspecialchars(isset($ls['hero_image_y']) ? $ls['hero_image_y'] : '50'); ?></span>%</label>
                        <input type="range" name="hero_image_y" id="hero_y" min="-100" max="200" value="<?= htmlspecialchars(isset($ls['hero_image_y']) ? $ls['hero_image_y'] : '50'); ?>" oninput="document.getElementById('val_y').innerText = this.value; updatePreviewPosition();" style="width: 100%; border: none; padding: 0; background: transparent;">
                    </div>
                </div>
                <div class="card">
                    <h3>2. Bagian Profil (Tentang Sistem)</h3>
                    <div class="input-group">
                        <label>Judul Bagian</label>
                        <input type="text" name="about_title" required value="<?= htmlspecialchars($ls['about_title']); ?>">
                    </div>
                    <div class="input-group">
                        <label>Paragraf Pertama</label>
                        <textarea name="about_desc1" rows="3" required><?= htmlspecialchars($ls['about_desc1']); ?></textarea>
                    </div>
                    <div class="input-group">
                        <label>Paragraf Kedua</label>
                        <textarea name="about_desc2" rows="3" required><?= htmlspecialchars($ls['about_desc2']); ?></textarea>
                    </div>
                    <div class="input-group">
                        <label>Gambar Representasi Profil</label>
                        <?php if(!empty($ls['about_image'])): ?><img src="<?= htmlspecialchars($ls['about_image']); ?>" class="image-preview"><?php endif; ?>
                        <label for="about_image" class="custom-file-btn">📸 Pilih Gambar</label>
                        <input type="file" name="about_image" id="about_image" class="custom-file-input" accept="image/*" onchange="document.getElementById('name_about').innerText = this.files.length > 0 ? 'File terpilih: ' + this.files[0].name : '';">
                        <span id="name_about" class="file-name"></span>
                    </div>
                    <div class="input-group">
                        <label>Poin Keunggulan 1</label>
                        <input type="text" name="about_list1" value="<?= htmlspecialchars(isset($ls['about_list1']) ? $ls['about_list1'] : 'Sistem pemenuhan permintaan materi otomatis (Smart Matching) dan anti-duplikasi file'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Poin Keunggulan 2</label>
                        <input type="text" name="about_list2" value="<?= htmlspecialchars(isset($ls['about_list2']) ? $ls['about_list2'] : 'Pengukuran Learning Analytics (SPI & KSI) beserta sistem rekomendasi cerdas'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Poin Keunggulan 3</label>
                        <input type="text" name="about_list3" value="<?= htmlspecialchars(isset($ls['about_list3']) ? $ls['about_list3'] : 'Jalur kontribusi khusus bagi praktisi pendidikan eksternal untuk pengayaan materi'); ?>">
                    </div>
                </div>
                <div class="card">
                    <h3>3. Bagian Teks Galeri Kegiatan</h3>
                    <div class="input-group">
                        <label>Judul Galeri</label>
                    <input type="text" name="gallery_title" value="<?= htmlspecialchars(isset($ls['gallery_title']) ? $ls['gallery_title'] : 'Kegiatan MGMP'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Deskripsi Galeri</label>
                        <textarea name="gallery_desc" rows="2"><?= htmlspecialchars(isset($ls['gallery_desc']) ? $ls['gallery_desc'] : ''); ?></textarea>
                    </div>
                </div>
                <div class="card">
                    <h3>4. Bagian Fitur / Program Analitik</h3>
                    <div class="input-group">
                        <label>Judul Bagian</label>
                        <input type="text" name="analytic_title" required value="<?= htmlspecialchars($ls['analytic_title']); ?>">
                    </div>
                    <div class="input-group">
                        <label>Sub-Judul</label>
                        <textarea name="analytic_subtitle" rows="2" required><?= htmlspecialchars($ls['analytic_subtitle']); ?></textarea>
                    </div>
                    <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 1</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature1_icon" value="<?= htmlspecialchars(isset($ls['feature1_icon']) ? $ls['feature1_icon'] : '📚'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature1_title" value="<?= htmlspecialchars(isset($ls['feature1_title']) ? $ls['feature1_title'] : 'Repositori Digital Terintegrasi'); ?>" style="flex:1" placeholder="Judul Fitur 1">
                        </div>
                        <textarea name="feature1_desc" rows="2"><?= htmlspecialchars(isset($ls['feature1_desc']) ? $ls['feature1_desc'] : 'Fasilitas penyimpanan komprehensif yang dirancang khusus untuk mengarsipkan dan mendistribusikan dokumen pedagogis, modul ajar, serta instrumen evaluasi secara tersentralisasi guna menjamin aksesibilitas dan keamanan data berkelanjutan.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 2</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature2_icon" value="<?= htmlspecialchars(isset($ls['feature2_icon']) ? $ls['feature2_icon'] : '📊'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature2_title" value="<?= htmlspecialchars(isset($ls['feature2_title']) ? $ls['feature2_title'] : 'Sistem Meritokrasi Digital'); ?>" style="flex:1" placeholder="Judul Fitur 2">
                        </div>
                        <textarea name="feature2_desc" rows="2"><?= htmlspecialchars(isset($ls['feature2_desc']) ? $ls['feature2_desc'] : 'Mekanisme terotomatisasi yang mengukur dan memberikan atribusi poin prestasi secara kuantitatif berdasarkan tingkat partisipasi aktif tenaga pendidik dalam berbagi (upload) dan memanfaatkan (download) sumber daya pembelajaran.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 3</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature3_icon" value="<?= htmlspecialchars(isset($ls['feature3_icon']) ? $ls['feature3_icon'] : '🤝'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature3_title" value="<?= htmlspecialchars(isset($ls['feature3_title']) ? $ls['feature3_title'] : 'Jalur Kontribusi Akademisi Eksternal'); ?>" style="flex:1" placeholder="Judul Fitur 3">
                        </div>
                        <textarea name="feature3_desc" rows="2"><?= htmlspecialchars(isset($ls['feature3_desc']) ? $ls['feature3_desc'] : 'Menyediakan kanal khusus bagi para akademisi, dosen, dan praktisi pendidikan untuk menyumbangkan materi ajar terkurasi guna memperkaya dan meningkatkan mutu referensi pedagogis dalam ekosistem.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 4</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature4_icon" value="<?= htmlspecialchars(isset($ls['feature4_icon']) ? $ls['feature4_icon'] : '👥'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature4_title" value="<?= htmlspecialchars(isset($ls['feature4_title']) ? $ls['feature4_title'] : 'Jejaring Kolaborasi Referensi'); ?>" style="flex:1" placeholder="Judul Fitur 4">
                        </div>
                        <textarea name="feature4_desc" rows="2"><?= htmlspecialchars(isset($ls['feature4_desc']) ? $ls['feature4_desc'] : 'Infrastruktur interaktif yang memungkinkan tenaga pendidik untuk mengajukan permohonan spesifik terkait bahan ajar, memfasilitasi pertukaran materi secara responsif antar institusi.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 5</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature5_icon" value="<?= htmlspecialchars(isset($ls['feature5_icon']) ? $ls['feature5_icon'] : '📈'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature5_title" value="<?= htmlspecialchars(isset($ls['feature5_title']) ? $ls['feature5_title'] : 'Inovasi & Adaptasi Pedagogis'); ?>" style="flex:1" placeholder="Judul Fitur 5">
                        </div>
                        <textarea name="feature5_desc" rows="2"><?= htmlspecialchars(isset($ls['feature5_desc']) ? $ls['feature5_desc'] : 'Mendorong pengembangan kompetensi instruksional melalui metode observasi, adopsi, dan modifikasi terhadap instrumen pembelajaran unggulan lintas institusi guna memperkaya variasi pendekatan edukatif.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 6</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature6_icon" value="<?= htmlspecialchars(isset($ls['feature6_icon']) ? $ls['feature6_icon'] : '🔒'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature6_title" value="<?= htmlspecialchars(isset($ls['feature6_title']) ? $ls['feature6_title'] : 'Sistem Proteksi Integritas Data'); ?>" style="flex:1" placeholder="Judul Fitur 6">
                        </div>
                        <textarea name="feature6_desc" rows="2"><?= htmlspecialchars(isset($ls['feature6_desc']) ? $ls['feature6_desc'] : 'Infrastruktur penyimpanan berbasis komputasi cerdas (Hashing) yang secara otomatis memindai dan memfilter redundansi file, memastikan efisiensi kapasitas serta validitas repositori.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 7</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature7_icon" value="<?= htmlspecialchars(isset($ls['feature7_icon']) ? $ls['feature7_icon'] : '🏆'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature7_title" value="<?= htmlspecialchars(isset($ls['feature7_title']) ? $ls['feature7_title'] : 'Leaderboard Kinerja Akademik'); ?>" style="flex:1" placeholder="Judul Fitur 7">
                        </div>
                        <textarea name="feature7_desc" rows="2"><?= htmlspecialchars(isset($ls['feature7_desc']) ? $ls['feature7_desc'] : 'Sistem pemeringkatan transparan berbasis data analitik yang dirancang untuk menstimulasi motivasi intrinsik tenaga pendidik dalam mengoptimalkan kontribusi pedagogis di tingkat ekosistem kota.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 8</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature8_icon" value="<?= htmlspecialchars(isset($ls['feature8_icon']) ? $ls['feature8_icon'] : '📢'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature8_title" value="<?= htmlspecialchars(isset($ls['feature8_title']) ? $ls['feature8_title'] : 'Kanal Diseminasi Informasi'); ?>" style="flex:1" placeholder="Judul Fitur 8">
                        </div>
                        <textarea name="feature8_desc" rows="2"><?= htmlspecialchars(isset($ls['feature8_desc']) ? $ls['feature8_desc'] : 'Infrastruktur komunikasi satu arah yang menjamin penyampaian informasi, jadwal, dan edaran resmi dari administrator MGMP secara terstruktur dan terdokumentasi langsung ke dasbor pengguna.'); ?></textarea>
                    </div>
                    <div style="margin-top:15px;">
                        <label style="color:#3498db; font-size:15px; margin-bottom:10px;">Fitur 9</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="feature9_icon" value="<?= htmlspecialchars(isset($ls['feature9_icon']) ? $ls['feature9_icon'] : '🏫'); ?>" style="width:60px;" placeholder="Ikon">
                            <input type="text" name="feature9_title" value="<?= htmlspecialchars(isset($ls['feature9_title']) ? $ls['feature9_title'] : 'Indeks Kinerja Institusional'); ?>" style="flex:1" placeholder="Judul Fitur 9">
                        </div>
                        <textarea name="feature9_desc" rows="2"><?= htmlspecialchars(isset($ls['feature9_desc']) ? $ls['feature9_desc'] : 'Sistem analitik yang mengagregasi skor partisipasi individual dari setiap tenaga pendidik menjadi sebuah Indeks Kinerja Institusional (SPI) terukur, merepresentasikan kontribusi kolektif dan reputasi akademik sekolah dalam ekosistem.'); ?></textarea>
                    </div>
                </div>
                <div class="card">
                    <h3>5. Bagian Footer</h3>
                    <div class="input-group">
                        <label>Judul Footer</label>
                        <input type="text" name="footer_title" value="<?= htmlspecialchars(isset($ls['footer_title']) ? $ls['footer_title'] : 'SI-LIAK MGMP KOTA DENPASAR'); ?>">
                    </div>
                    <div class="input-group">
                        <label>Deskripsi Footer</label>
                        <textarea name="footer_desc" rows="3"><?= htmlspecialchars(isset($ls['footer_desc']) ? $ls['footer_desc'] : 'Sistem Informasi Learning Integration & Analitik Kinerja (SI-LIAK) Musyawarah Guru Mata Pelajaran. Dedikasi terhadap peningkatan mutu pendidikan melalui digitalisasi pendistribusian materi dan analisis data kinerja yang presisi.'); ?></textarea>
                    </div>
                    <div class="input-group">
                        <label>Teks Hak Cipta (Copyright)</label>
                        <input type="text" name="footer_copyright" value="<?= htmlspecialchars(isset($ls['footer_copyright']) ? $ls['footer_copyright'] : 'Sistem Informasi MGMP. Hak Cipta Dilindungi Undang-Undang.'); ?>">
                        <span class="info-text">* Tahun akan ditambahkan secara otomatis di depan teks (Contoh: © <?= date('Y'); ?> ... )</span>
                    </div>
                    <div class="input-group" style="margin-top:20px; padding-top:20px; border-top:1px solid #eee;">
                        <label>Judul Layanan Kontak</label>
                        <input type="text" name="footer_contact_title" value="<?= htmlspecialchars(isset($ls['footer_contact_title']) ? $ls['footer_contact_title'] : 'Layanan Kontak'); ?>">
                    </div>
                    <div style="margin-top:10px;">
                        <label style="color:#3498db; font-size:14px; margin-bottom:8px;">Tautan Kontak 1</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="footer_contact_1_text" value="<?= htmlspecialchars(isset($ls['footer_contact_1_text']) ? $ls['footer_contact_1_text'] : 'Bantuan Administrasi'); ?>" style="flex:1" placeholder="Teks Tautan">
                            <input type="text" name="footer_contact_1_url" value="<?= htmlspecialchars(isset($ls['footer_contact_1_url']) ? $ls['footer_contact_1_url'] : '#'); ?>" style="flex:1" placeholder="Cth: admin@email.com" onblur="this.value = this.value.replace(/\s/g, ''); if(this.value.includes('@') && !this.value.startsWith('mailto:') && !this.value.startsWith('http')) this.value = 'mailto:' + this.value;">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label style="color:#3498db; font-size:14px; margin-bottom:8px;">Tautan Kontak 2 (Opsional)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="footer_contact_2_text" value="<?= htmlspecialchars(isset($ls['footer_contact_2_text']) ? $ls['footer_contact_2_text'] : 'Pendaftaran Kontributor'); ?>" style="flex:1" placeholder="Teks Tautan">
                            <input type="text" name="footer_contact_2_url" value="<?= htmlspecialchars(isset($ls['footer_contact_2_url']) ? $ls['footer_contact_2_url'] : '#'); ?>" style="flex:1" placeholder="Cth: daftar@email.com" onblur="this.value = this.value.replace(/\s/g, ''); if(this.value.includes('@') && !this.value.startsWith('mailto:') && !this.value.startsWith('http')) this.value = 'mailto:' + this.value;">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label style="color:#3498db; font-size:14px; margin-bottom:8px;">Tautan Kontak 3 (Opsional)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="footer_contact_3_text" value="<?= htmlspecialchars(isset($ls['footer_contact_3_text']) ? $ls['footer_contact_3_text'] : 'Kebijakan Privasi'); ?>" style="flex:1" placeholder="Teks Tautan">
                            <input type="text" name="footer_contact_3_url" value="<?= htmlspecialchars(isset($ls['footer_contact_3_url']) ? $ls['footer_contact_3_url'] : '#'); ?>" style="flex:1" placeholder="URL / Link" onblur="this.value = this.value.replace(/\s/g, ''); if(this.value.includes('@') && !this.value.startsWith('mailto:') && !this.value.startsWith('http')) this.value = 'mailto:' + this.value;">
                        </div>
                    </div>
                </div>
                <button type="submit" name="update_landing" class="btn-submit">Simpan Semua Perubahan Portal</button>
            </form>
        </div>

        <!-- TAB GALERI -->
        <div id="galeri" class="tab-content <?= $active_tab == 'galeri' ? 'active' : '' ?>">
            <div class="card">
                <h2>Tambah Dokumentasi Galeri</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                    <div class="input-group">
                        <label>Judul Kegiatan</label>
                        <input type="text" name="title" required placeholder="Contoh: Lokakarya Kurikulum">
                    </div>
                    <div class="input-group">
                        <label>Keterangan Kegiatan</label>
                        <textarea name="description" rows="3" required placeholder="Deskripsi singkat..."></textarea>
                    </div>
                    <div class="input-group">
                        <label>Upload Foto (JPG/PNG/WEBP)</label>
                        <label for="image_galeri" class="custom-file-btn">📸 Pilih Gambar</label>
                        <input type="file" name="image" id="image_galeri" class="custom-file-input" required accept="image/*" onchange="document.getElementById('name_galeri').innerText = this.files.length > 0 ? 'File terpilih: ' + this.files[0].name : '';">
                        <span id="name_galeri" class="file-name"></span>
                    </div>
                    <button type="submit" name="tambah_galeri" class="btn-submit" style="width:auto; padding:10px 20px;">+ Unggah Galeri</button>
                </form>
            </div>

            <div class="card">
                <div style="overflow-x:auto;">
                <h2>Daftar Galeri Sistem (Tampil max 6 di Portal)</h2>
                <table>
                    <tr>
                        <th width="15%">Foto</th>
                        <th width="25%">Judul</th>
                        <th width="45%">Deskripsi</th>
                        <th width="15%">Aksi</th>
                    </tr>
                    <?php if(mysqli_num_rows($query_galeri) > 0){ 
                        while($row = mysqli_fetch_assoc($query_galeri)){ ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($row['image_path']); ?>" style="width:100%; border-radius:6px; aspect-ratio:16/9; object-fit:cover;"></td>
                        <td><strong><?= htmlspecialchars($row['title']); ?></strong><br><small><?= date('d M Y', strtotime($row['created_at'])); ?></small></td>
                        <td style="font-size: 14px; color: #555;"><?= nl2br(htmlspecialchars($row['description'])); ?></td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn-edit" style="margin-right:0;"
                                    data-id="<?= $row['id']; ?>" 
                                    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    data-desc="<?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    data-img="<?= htmlspecialchars($row['image_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    onclick="openEditGaleriModal(this)">Edit
                                </button>
                                <a href="?hapus_galeri=<?= $row['id']; ?>&csrf_token=<?= $csrf_token; ?>" class="btn-hapus" onclick="return confirm('Yakin menghapus foto ini?');">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php } } else { ?>
                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#777;">Belum ada dokumentasi galeri.</td></tr>
                    <?php } ?>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Galeri -->
<div id="editGaleriModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Dokumentasi Galeri</h3>
            <button class="close-btn" onclick="closeEditGaleriModal()">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="input-group">
                <label>Judul Kegiatan</label>
                <input type="text" name="edit_title" id="edit_title" required>
            </div>
            <div class="input-group">
                <label>Keterangan Kegiatan</label>
                <textarea name="edit_description" id="edit_description" rows="3" required></textarea>
            </div>
            <div class="input-group">
                <label>Gambar Saat Ini</label>
                <img src="" id="edit_preview_image" style="width: 100%; height: 150px; object-fit: cover; border-radius: 6px; margin-bottom: 10px; border: 1px solid #ccc;">
                <label>Ganti Foto (Opsional, Biarkan kosong jika tidak diganti)</label>
                <label for="edit_image" class="custom-file-btn">📸 Pilih Gambar Baru</label>
                <input type="file" name="edit_image" id="edit_image" class="custom-file-input" accept="image/*" onchange="document.getElementById('edit_name_galeri').innerText = this.files.length > 0 ? 'File terpilih: ' + this.files[0].name : '';">
                <span id="edit_name_galeri" class="file-name"></span>
            </div>
            <button type="submit" name="edit_galeri" class="btn-submit">Simpan Perubahan</button>
        </form>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

function updatePreviewPosition() {
    var x = document.getElementById('hero_x').value;
    var y = document.getElementById('hero_y').value;
    var preview = document.getElementById('preview_hero');
    if (preview) {
        preview.style.objectPosition = x + '% ' + y + '%';
    }
}
document.addEventListener("DOMContentLoaded", function() { updatePreviewPosition(); });

function openEditGaleriModal(btn) {
    document.getElementById('edit_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_title').value = btn.getAttribute('data-title');
    document.getElementById('edit_description').value = btn.getAttribute('data-desc');
    document.getElementById('edit_preview_image').src = btn.getAttribute('data-img');
    document.getElementById('edit_name_galeri').innerText = '';
    document.getElementById('editGaleriModal').style.display = 'flex';
}
function closeEditGaleriModal() {
    document.getElementById('editGaleriModal').style.display = 'none';
}
window.addEventListener('click', function(e) {
    let m = document.getElementById('editGaleriModal');
    if (e.target == m) { m.style.display = "none"; }
});
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
