<?php
// =====================================
// SESSION SECURE CONFIG
// Pastikan session cookie aman & tidak hilang saat redirect HTTP→HTTPS
// =====================================
$is_localhost = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '192.168.') === 0);
if (!$is_localhost) {
    ini_set('session.cookie_secure', 1);       // Cookie hanya dikirim via HTTPS
}
ini_set('session.cookie_httponly', 1);     // Cookie tidak bisa diakses via JavaScript
ini_set('session.cookie_samesite', 'Lax'); // Izinkan cross-page navigation normal
ini_set('session.use_strict_mode', 1);     // Tolak session ID yang tidak valid

session_start();

// =================================================================
// SECURITY ENHANCEMENTS
// =================================================================
// Set security headers to protect against common attacks
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' https: data:; object-src 'none'; form-action 'self'; frame-ancestors 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

include 'config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =================================================================
// CEK ERROR LOGIN
// =================================================================
$login_error = '';
if (isset($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Hapus segera agar tidak muncul terus
}

// Ambil data Galeri
$cek_table = mysqli_query($conn, "SHOW TABLES LIKE 'gallery'");
$gallery_items = [];
if($cek_table && mysqli_num_rows($cek_table) > 0){
    $q_galeri = mysqli_query($conn, "SELECT * FROM gallery ORDER BY created_at DESC LIMIT 6");
    while($row = mysqli_fetch_assoc($q_galeri)){
        $gallery_items[] = $row;
    }
}

// Ambil data Landing Page
$ls = [
    'hero_title' => 'Membangun Sinergi<br><span>Pendidikan Berkualitas</span>',
    'hero_subtitle' => 'Wadah kolaborasi antar tenaga pendidik untuk menciptakan ekosistem pembelajaran yang inovatif, terstruktur, dan berbasis data Learning Analytics cerdas.',
    'hero_image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Patung_Catur_Muka%2C_Denpasar.jpg/1280px-Patung_Catur_Muka%2C_Denpasar.jpg',
    'about_title' => 'Ekosistem Terpadu Berbasis Kecerdasan Algoritmik',
    'about_desc1' => 'Sistem Informasi Musyawarah Guru Mata Pelajaran (MGMP) hadir sebagai inovasi akademik modern yang memadukan repositori perangkat ajar dengan komputasi cerdas. Platform ini dirancang untuk mendistribusikan materi pembelajaran secara merata, terstruktur, dan terbebas dari duplikasi data berkat fitur pemindaian sidik jari file (Hashing).',
    'about_desc2' => 'Lebih dari sekadar ruang penyimpanan <i>cloud</i>, platform ini dibekali fitur <strong>Smart Matching</strong> yang mampu mendeteksi ketersediaan request materi secara otomatis. Didukung oleh teknologi <strong>Learning Analytics</strong>, platform ini secara <i>real-time</i> menghasilkan evaluasi performa kolektif sekolah (SPI) dan sistem rekomendasi kolaborasi (KSI) guna mendorong interaksi aktif setiap tenaga pendidik.',
    'about_image' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=800&q=80',
    'analytic_title' => 'Infrastruktur Kolaborasi Akademik Digital',
    'analytic_subtitle' => 'Platform terintegrasi yang dirancang secara spesifik untuk mengoptimalkan produktivitas pedagogis dan memfasilitasi sinergi strategis antar tenaga pendidik.',
    'login_title' => 'Portal Sistem Informasi Akademik',
    'login_desc' => 'Akses khusus bagi administrator, tenaga pendidik, dan kontributor terdaftar untuk masuk ke dalam ruang kerja virtual MGMP.',
    'topbar_text' => 'Selamat Datang di SI-LIAK ( Sistem Informasi Learning Integration & Analitik Kinerja )',
    'navbar_logo_text' => 'SI-LIAK MGMP',
    'about_list1' => 'Sistem pemenuhan permintaan materi otomatis (Smart Matching) dan anti-duplikasi file',
    'about_list2' => 'Pengukuran Learning Analytics (SPI & KSI) beserta sistem rekomendasi cerdas',
    'about_list3' => 'Jalur kontribusi khusus bagi praktisi pendidikan eksternal untuk pengayaan materi',
    'gallery_title' => 'Kegiatan MGMP',
    'gallery_desc' => '',
    'feature1_icon' => '📚',
    'feature1_title' => 'Repositori Digital Terintegrasi',
    'feature1_desc' => 'Fasilitas penyimpanan komprehensif yang dirancang khusus untuk mengarsipkan dan mendistribusikan dokumen pedagogis, modul ajar, serta instrumen evaluasi secara tersentralisasi guna menjamin aksesibilitas dan keamanan data berkelanjutan.',
    'feature2_icon' => '📊',
    'feature2_title' => 'Sistem Meritokrasi Digital',
    'feature2_desc' => 'Mekanisme terotomatisasi yang mengukur dan memberikan atribusi poin prestasi secara kuantitatif berdasarkan tingkat partisipasi aktif tenaga pendidik dalam berbagi (upload) dan memanfaatkan (download) sumber daya pembelajaran.',
    'feature3_icon' => '🤝',
    'feature3_title' => 'Jalur Kontribusi Akademisi Eksternal',
    'feature3_desc' => 'Menyediakan kanal khusus bagi para akademisi, dosen, dan praktisi pendidikan untuk menyumbangkan materi ajar terkurasi guna memperkaya dan meningkatkan mutu referensi pedagogis dalam ekosistem.',
    'feature4_icon' => '👥',
    'feature4_title' => 'Jejaring Kolaborasi Referensi',
    'feature4_desc' => 'Infrastruktur interaktif yang memungkinkan tenaga pendidik untuk mengajukan permohonan spesifik terkait bahan ajar, memfasilitasi pertukaran materi secara responsif antar institusi.',
    'feature5_icon' => '📈',
    'feature5_title' => 'Inovasi & Adaptasi Pedagogis',
    'feature5_desc' => 'Mendorong pengembangan kompetensi instruksional melalui metode observasi, adopsi, dan modifikasi terhadap instrumen pembelajaran unggulan lintas institusi guna memperkaya variasi pendekatan edukatif.',
    'feature6_icon' => '🔒',
    'feature6_title' => 'Sistem Proteksi Integritas Data',
    'feature6_desc' => 'Infrastruktur penyimpanan berbasis komputasi cerdas (Hashing) yang secara otomatis memindai dan memfilter redundansi file, memastikan efisiensi kapasitas serta validitas repositori.',
    'feature7_icon' => '🏆',
    'feature7_title' => 'Leaderboard Kinerja Akademik',
    'feature7_desc' => 'Sistem pemeringkatan transparan berbasis data analitik yang dirancang untuk menstimulasi motivasi intrinsik tenaga pendidik dalam mengoptimalkan kontribusi pedagogis di tingkat ekosistem kota.',
    'feature8_icon' => '📢',
    'feature8_title' => 'Kanal Diseminasi Informasi',
    'feature8_desc' => 'Infrastruktur komunikasi satu arah yang menjamin penyampaian informasi, jadwal, dan edaran resmi dari administrator MGMP secara terstruktur dan terdokumentasi langsung ke dasbor pengguna.',
    'feature9_icon' => '🏫',
    'feature9_title' => 'Indeks Kinerja Institusional',
    'feature9_desc' => 'Sistem analitik yang mengagregasi skor partisipasi individual dari setiap tenaga pendidik menjadi sebuah Indeks Kinerja Institusional (SPI) terukur, merepresentasikan kontribusi kolektif dan reputasi akademik sekolah dalam ekosistem.',
    'footer_title' => 'SI-LIAK MGMP KOTA DENPASAR',
    'footer_desc' => 'Sistem Informasi Learning Integration & Analitik Kinerja (SI-LIAK) Musyawarah Guru Mata Pelajaran. Dedikasi terhadap peningkatan mutu pendidikan melalui digitalisasi pendistribusian materi dan analisis data kinerja yang presisi.',
    'footer_copyright' => 'Sistem Informasi MGMP. Hak Cipta Dilindungi Undang-Undang.',
    'footer_contact_title' => 'Layanan Kontak',
    'footer_contact_1_text' => 'Bantuan Administrasi',
    'footer_contact_1_url' => '#',
    'footer_contact_2_text' => 'Pendaftaran Kontributor',
    'footer_contact_2_url' => '#',
    'footer_contact_3_text' => 'Kebijakan Privasi',
    'footer_contact_3_url' => '#'
];

$cek_landing = mysqli_query($conn, "SHOW TABLES LIKE 'landing_settings'");
if($cek_landing && mysqli_num_rows($cek_landing) > 0){
    $q_ls = mysqli_query($conn, "SELECT * FROM landing_settings WHERE id = 1");
    if($q_ls && mysqli_num_rows($q_ls) > 0){
        $db_ls = mysqli_fetch_assoc($q_ls);
        foreach($db_ls as $key => $val){ 
            if($val !== null) $ls[$key] = $val; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Google Analytics (Global Site Tag) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-1EMKJ3QH6F"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-1EMKJ3QH6F');
    </script>
    <!-- End Google Analytics -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>SI-LIAK - MGMP PPKN Kota Denpasar</title>
    <meta name="description" content="Sistem Informasi Learning Integration & Analitik Kinerja (SI-LIAK). Wadah kolaborasi Musyawarah Guru Mata Pelajaran (MGMP) PPKN Kota Denpasar untuk ekosistem pembelajaran inovatif.">
    <meta name="keywords" content="SI-LIAK, MGMP, PPKN, Denpasar, Pendidikan, Guru, e-Learning, Platform Kolaborasi Guru, Repositori Materi">
    <meta name="author" content="MGMP PPKN Kota Denpasar">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://mgmpmulokdps.com/" />
    
    <!-- Favicon / Logo Web -->
    <link rel="icon" href="assets/images/logo.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    
    <!-- Schema.org Markup untuk Google Knowledge Graph -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "MGMP PPKN Kota Denpasar: SI-LIAK",
      "url": "https://mgmpmulokdps.com",
      "logo": "https://mgmpmulokdps.com/assets/images/logo.png"
    }
    </script>
    
    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="SI-LIAK - MGMP PPKN Kota Denpasar">
    <meta property="og:description" content="Sistem Informasi Learning Integration & Analitik Kinerja (SI-LIAK). Wadah kolaborasi MGMP PPKN Kota Denpasar.">
    <meta property="og:url" content="https://mgmpmulokdps.com/">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SI-LIAK MGMP PPKN Denpasar">
    
    <!-- Academic Fonts: Merriweather for headings, Open Sans for body -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,400;0,700;0,900;1,400&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #003366; /* Classic Navy Blue */
            --secondary: #D4AF37; /* Academic Gold */
            --text-dark: #1E293B;
            --text-muted: #475569;
            --bg-light: #F8FAFC;
            --font-heading: 'Merriweather', serif;
            --font-body: 'Open Sans', sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font-body); }
        html { scroll-behavior: smooth; scroll-padding-top: 85px; }
        body { background: #ffffff; color: var(--text-dark); line-height: 1.7; overflow-x: hidden; }
        
        h1, h2, h3, h4, h5, h6 { font-family: var(--font-heading); }
        
        /* ========================
           TOP BAR
           ======================== */
        .top-bar {
            background: var(--primary);
            color: white;
            padding: 10px 5%;
            font-size: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1001;
            position: relative;
        }
        .top-bar a { color: var(--secondary); text-decoration: none; font-weight: 700; transition: 0.3s; letter-spacing: 0.5px; }
        .top-bar a:hover { color: white; }
        
        /* ========================
           NAVBAR (MENU ATAS)
           ======================== */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 18px 5%; 
            display: flex; justify-content: space-between; align-items: center; z-index: 1000;
            position: sticky; top: 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: 0.3s;
        }
        .navbar .logo { 
            text-decoration: none; display: flex; align-items: center; gap: 15px; 
        }
        .logo-icon {
            background: var(--primary);
            color: white;
            width: 45px; height: 45px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: bold; font-family: var(--font-heading);
            border-radius: 4px;
        }
        .logo-text h2 { font-size: 22px; color: var(--primary); margin: 0; line-height: 1; }
        .logo-text span { font-size: 11px; color: var(--text-muted); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; font-family: var(--font-body); }
        
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 700; font-size: 14px; transition: 0.3s; text-transform: uppercase; letter-spacing: 0.5px;}
        .nav-links a:hover { color: var(--primary); }
        .nav-links a.active { color: var(--primary); border-bottom: 2px solid var(--secondary); padding-bottom: 5px; }
        
        /* ========================
           SECTIONS UMUM
           ======================== */
        .section-container { padding: 25px 5%; }
        .section-tag { color: var(--secondary); font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; font-family: var(--font-body); }
        .section-title { font-size: 28px; color: var(--primary); margin-bottom: 15px; font-weight: 800; line-height: 1.3; }
        .section-subtitle { font-size: 14px; color: var(--text-muted); margin-bottom: 30px; max-width: 700px; line-height: 1.6; text-align: justify; }

        /* ========================
           HERO SECTION (BERANDA)
           ======================== */
        #beranda {
            min-height: 100vh; 
            display: flex; align-items: center; justify-content: center; padding: 0 5%; text-align: center;
            position: relative;
            overflow: hidden;
        }
        .beranda-bg {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1;
            animation: waveBg 8s ease-in-out infinite alternate;
        }
        .beranda-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(0, 33, 71, 0.8), rgba(0, 33, 71, 0.9));
            z-index: 2;
        }
        @keyframes waveBg {
            0%   { transform: scale(1.1) translate(0%, 0%); }
            25%  { transform: scale(1.1) translate(2%, 2%); }
            50%  { transform: scale(1.1) translate(4%, 0%); }
            75%  { transform: scale(1.1) translate(2%, -2%); }
            100% { transform: scale(1.1) translate(0%, 0%); }
        }
        .hero-content { max-width: 900px; color: white; margin-top: -50px; position: relative; z-index: 3; }
        .hero-badge { 
            display: inline-block; padding: 6px 20px; background: rgba(212, 175, 55, 0.2); 
            color: var(--secondary); font-weight: 700; border-left: 3px solid var(--secondary); 
            font-size: 13px; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 2px;
            font-family: var(--font-body);
        }
        #beranda h1 { font-size: 58px; font-weight: 900; margin-bottom: 25px; line-height: 1.2; color: white; }
        #beranda h1 span { color: var(--secondary); font-style: italic; }
        #beranda p { font-size: 18px; color: #e2e8f0; margin-bottom: 40px; line-height: 1.8; margin-left: auto; margin-right: auto; font-family: var(--font-body); max-width: 750px; text-align: center; }
        
        .btn-primary { 
            display: inline-block; background: var(--secondary); color: var(--primary); 
            padding: 16px 40px; border-radius: 4px; text-decoration: none; font-size: 15px; 
            font-weight: 800; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px;
            font-family: var(--font-body);
        }
        .btn-primary:hover { background: white; transform: translateY(-2px); }
        .btn-outline {
            display: inline-block; border: 2px solid white; color: white;
            padding: 14px 40px; border-radius: 4px; text-decoration: none; font-size: 15px; 
            font-weight: 800; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; margin-left: 15px;
            font-family: var(--font-body);
        }
        .btn-outline:hover { background: white; color: var(--primary); }

        /* ========================
           PROFIL & FITUR
           ======================== */
        .about-wrapper { display: flex; gap: 40px; align-items: flex-start; max-width: 1200px; margin: 0 auto; }
        .about-text { flex: 1; }
        .about-text .section-tag { margin-top: 0; }
        .about-text .section-title { font-size: 36px; margin-bottom: 20px; line-height: 1.3; }
        .about-text p { margin-bottom: 20px; color: var(--text-muted); font-size: 16px; text-align: justify; line-height: 1.8; }
        .about-list { list-style: none; margin-top: 15px; }
        .about-list li { margin-bottom: 10px; font-size: 16px; font-weight: 600; color: var(--primary); display: flex; align-items: flex-start; gap: 10px; line-height: 1.5; }
        .about-list li::before { content: '✓'; color: var(--secondary); font-weight: bold; font-size: 18px; line-height: 1.3; }
        .about-image { flex: 1; position: relative; }
        .about-image img { width: 100%; height: calc(100vh - 180px); min-height: 450px; max-height: 750px; object-fit: fill; background-color: transparent; border-radius: 8px; box-shadow: 0 15px 35px rgba(0,0,0,0.15); position: relative; z-index: 2; display: block; animation: floatImg 6s ease-in-out infinite; }
        .about-image::after { content: ''; position: absolute; top: 15px; right: -15px; width: 100%; height: 100%; border: 4px solid var(--secondary); border-radius: 8px; z-index: 1; }

        @keyframes floatImg {
            0% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0); }
        }

        #galeri { background: var(--bg-light); }
        #profil { background: white; min-height: 100vh; display: flex; align-items: flex-start; padding-top: 25px; padding-bottom: 60px; }
        #akademik { background: var(--bg-light); }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; max-width: 1200px; margin: 0 auto; }
        .feature-card { background: white; padding: 20px 20px; border-radius: 8px; border-top: 4px solid var(--primary); box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s; }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); border-color: var(--secondary); }
        .f-icon { width: 50px; height: 50px; background: var(--bg-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--primary); margin-bottom: 15px; }
        .feature-card h3 { font-size: 20px; color: var(--primary); margin-bottom: 10px; }
        .feature-card p { color: var(--text-muted); font-size: 14px; line-height: 1.6; text-align: justify; }

        /* ========================
           GALLERY CARD & EFEK MENGAMBANG
           ======================== */
        .gallery-card { background: white; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: box-shadow 0.3s; padding: 0; overflow: hidden; animation: floatImg 4s ease-in-out infinite; }
        .gallery-card:nth-child(2n) { animation-delay: 1s; }
        .gallery-card:nth-child(3n) { animation-delay: 2s; }
        .gallery-card:hover {
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            animation-play-state: paused;
        }

        /* ========================
           LOGIN SECTION
           ======================== */
        .login-wrapper { display: flex; max-width: 450px; margin: 0 auto; background: #e8f0fe; border-radius: 12px; overflow: hidden; box-shadow: 0 15px 30px rgba(0, 51, 102, 0.15); border: 1px solid #cce0ff; }
        .login-form-container { flex: 1; padding: 40px 40px; background: #e8f0fe; }
        .login-form-container h3 { font-size: 22px; color: var(--primary); margin-bottom: 20px; text-align: center; }
        
        .input-group { margin-bottom: 15px; width: 100%; }
        .input-group label { display: block; margin-bottom: 8px; color: var(--text-dark); font-weight: 700; font-size: 13px; text-align: center; text-transform: uppercase; letter-spacing: 1px; font-family: var(--font-body); }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; transition: 0.3s; background: #ffffff; outline: none; color: var(--text-dark); font-family: var(--font-body); text-align: center; }
        .input-group input:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1); }
        
        .btn-submit { width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 4px; font-size: 14px; font-weight: 800; cursor: pointer; transition: 0.3s; margin-top: 10px; text-transform: uppercase; letter-spacing: 1px; font-family: var(--font-body); }
        .btn-submit:hover { background: #219150; }

        /* ========================
           FOOTER
           ======================== */
        footer { background: #01162B; color: #94a3b8; padding: 30px 5% 15px; font-family: var(--font-body); }
        .footer-grid { display: grid; grid-template-columns: 4fr 1fr; gap: 30px; max-width: 100%; margin: 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 15px; }
        .footer-about h3 { color: white; font-size: 20px; margin-bottom: 10px; font-family: var(--font-heading); }
        .footer-about p { margin-bottom: 10px; line-height: 1.6; font-size: 15px; text-align: justify; max-width: 480px; }
        .footer-links h4 { color: white; font-size: 14px; margin-bottom: 15px; font-family: var(--font-body); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .footer-links ul { list-style: none; }
        .footer-links ul li { margin-bottom: 12px; }
        .footer-links ul li a { color: #94a3b8; text-decoration: none; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; }
        .footer-links ul li a::before { content: '›'; font-size: 18px; font-weight: bold; }
        .footer-links ul li a:hover { color: var(--secondary); transform: translateX(5px); }
        .copyright { text-align: center; font-size: 13px; color: #ffffff; text-shadow: 0 0 5px rgba(255,255,255,0.8), 0 0 10px rgba(255,255,255,0.6), 0 0 15px rgba(255,255,255,0.4); font-weight: 600; letter-spacing: 0.5px; }

        /* ========================
           POPUP MODAL ERROR LOGIN
           ======================== */
        .popup-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center;
            z-index: 9999; opacity: 0; visibility: hidden; transition: 0.3s;
            backdrop-filter: blur(3px);
        }
        .popup-overlay.show { opacity: 1; visibility: visible; }
        .popup-box {
            background: white; padding: 30px; border-radius: 15px; text-align: center;
            width: 90%; max-width: 350px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transform: translateY(-20px) scale(0.9); transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .popup-overlay.show .popup-box { transform: translateY(0) scale(1); }
        .popup-icon.error {
            width: 65px; height: 65px; background: #e74c3c; color: white;
            font-size: 35px; line-height: 65px; border-radius: 50%; margin: 0 auto 15px;
        }
        .popup-message { font-size: 15px; color: #2c3e50; margin-bottom: 25px; font-weight: 600; line-height: 1.6; }
        .popup-btn.error {
            background: #e74c3c; color: white; border: none; padding: 12px 25px; border-radius: 8px;
            font-size: 15px; cursor: pointer; font-weight: bold; transition: 0.3s; width: 100%;
        }
        .popup-btn.error:hover { background: #c0392b; }

        /* ========================
           RESPONSIVE MOBILE
           ======================== */
        @media (max-width: 992px) {
            .about-wrapper { flex-direction: column; }
            .about-image::after { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            #beranda { padding-top: 120px; }
            #beranda h1 { font-size: 40px; }
            .top-bar { flex-direction: column; gap: 10px; text-align: center; }
            .about-image img { height: 300px; }
        }
        @media (max-width: 768px) {
        .navbar { flex-direction: column; gap: 15px; padding: 20px; align-items: center; text-align: center; }
        .nav-links { flex-wrap: wrap; justify-content: center; gap: 15px; }
            .btn-outline { margin-left: 0; margin-top: 15px; display: block; text-align: center; }
            .btn-primary { display: block; text-align: center; }
            .section-container { padding: 60px 5%; }
        }
    </style>
</head>
<body id="page-top">

<!-- POPUP ERROR LOGIN -->
<?php if(!empty($login_error)): ?>
<div class="popup-overlay" id="errorPopup">
    <div class="popup-box">
        <div class="popup-icon error">✖</div>
        <div class="popup-message"><?= htmlspecialchars($login_error); ?></div>
        <button class="popup-btn error" onclick="closeErrorPopup()">Coba Lagi</button>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() { document.getElementById('errorPopup').classList.add('show'); });
    function closeErrorPopup() { document.getElementById('errorPopup').classList.remove('show'); }
</script>
<?php endif; ?>

<!-- TOP BAR -->
<div class="top-bar">
    <div><?= htmlspecialchars($ls['topbar_text']); ?></div>
</div>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="#" class="logo">
        <div class="logo-text">
 <h2><?= htmlspecialchars($ls['navbar_logo_text']); ?></h2>
            <span></span>
        </div>
    </a>
    <div class="nav-links">
        <a href="#page-top" class="active">Beranda</a>
        <a href="#galeri">Galeri</a>
        <a href="#profil">Tentang Sistem</a>
        <a href="#akademik">Infrastruktur Akademik</a>
        <a href="#login">LOGIN</a>
    </div>
</nav>

<!-- HERO SECTION -->
<?php
$bg_pos_x = isset($ls['hero_image_x']) ? htmlspecialchars($ls['hero_image_x']) . '%' : '50%';
$bg_pos_y = isset($ls['hero_image_y']) ? htmlspecialchars($ls['hero_image_y']) . '%' : '50%';
?>
<section id="beranda">
    <div class="beranda-bg" style="background: url('<?= htmlspecialchars($ls['hero_image']); ?>') <?= $bg_pos_x; ?> <?= $bg_pos_y; ?>/100% 100% no-repeat; background-color: #002147;"></div>
    <div class="beranda-overlay"></div>
    <div class="hero-content">
        <h1><?= strip_tags($ls['hero_title'], '<br><span>'); ?></h1>
        <p><?= htmlspecialchars($ls['hero_subtitle']); ?></p>
    </div>
</section>

<!-- GALERI SECTION -->
<section id="galeri" class="section-container">
    <div style="text-align: center;">
        <h2 class="section-title"><?= htmlspecialchars($ls['gallery_title']); ?></h2>
        <?php if(!empty($ls['gallery_desc'])): ?>
            <p class="section-subtitle" style="margin: 0 auto 30px;"><?= htmlspecialchars($ls['gallery_desc']); ?></p>
        <?php endif; ?>
    </div>

    <div class="features-grid" style="max-width: 100%;">
        <?php if(count($gallery_items) > 0): ?>
            <?php foreach($gallery_items as $item): ?>
                <div class="gallery-card">
                    <img src="<?= htmlspecialchars($item['image_path']); ?>" alt="<?= htmlspecialchars($item['title']); ?>" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
                    <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                        <h3 style="font-size: 18px; margin-bottom: 8px;"><?= htmlspecialchars($item['title']); ?></h3>
                        <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;"><?= nl2br(htmlspecialchars($item['description'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 1" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Lokakarya Kurikulum</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Pembahasan dan penyelarasan modul ajar PPKN.</p>
            </div>
        </div>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 2" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Pelatihan Digital</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Integrasi dan adopsi teknologi dalam metode pembelajaran.</p>
            </div>
        </div>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 3" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Evaluasi Semester</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Rapat koordinasi evaluasi pelaksanaan program kerja MGMP.</p>
            </div>
        </div>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 4" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Diskusi Panel</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Membahas strategi pembelajaran yang efektif dan interaktif.</p>
            </div>
        </div>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1427504494785-319ce5156695?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 5" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Pengembangan Modul</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Kerja tim dalam menyusun modul pembelajaran berstandar.</p>
            </div>
        </div>
        <div class="gallery-card">
            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=80" alt="Kegiatan 6" style="width: 100%; height: 250px; object-fit: fill; background: #f8fafc; display: block;">
            <div style="padding: 15px 20px; border-top: 4px solid var(--primary);">
                <h3 style="font-size: 18px; margin-bottom: 8px;">Seminar Pendidikan</h3>
                <p style="margin: 0; font-size: 13px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-align: justify;">Peningkatan kapasitas guru melalui seminar nasional.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- PROFIL & FITUR SECTION -->
<section id="profil" class="section-container">
    <div class="about-wrapper" style="max-width: 100%; width: 100%;">
        <div class="about-text">
            <h2 class="section-title" style="text-align: left;"><?= htmlspecialchars($ls['about_title']); ?></h2>
            <p><?= nl2br(htmlspecialchars($ls['about_desc1'])); ?></p> 
            <p><?= strip_tags($ls['about_desc2'], '<strong><i>'); ?></p>
            <ul class="about-list">
                <li><?= htmlspecialchars($ls['about_list1']); ?></li>
                <li><?= htmlspecialchars($ls['about_list2']); ?></li>
                <li><?= htmlspecialchars($ls['about_list3']); ?></li>
            </ul>
        </div>
        <div class="about-image">
            <img src="<?= htmlspecialchars($ls['about_image']); ?>" alt="Kegiatan Akademik">
        </div>
    </div>
</section>

<!-- PROGRAM AKADEMIK SECTION -->
<section id="akademik" class="section-container">
    <div style="text-align: center;">
        <h2 class="section-title"><?= htmlspecialchars($ls['analytic_title']); ?></h2>
        <p class="section-subtitle" style="margin: 0 auto 30px; font-size: 17px; max-width: 1000px; text-align: center;"><?= htmlspecialchars($ls['analytic_subtitle']); ?></p>
    </div>

    <div class="features-grid" style="max-width: 100%;">
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature1_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature1_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature1_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature2_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature2_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature2_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature3_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature3_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature3_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature4_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature4_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature4_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature5_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature5_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature5_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature6_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature6_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature6_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature7_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature7_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature7_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature8_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature8_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature8_desc']); ?></p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><?= htmlspecialchars($ls['feature9_icon']); ?></div>
            <h3><?= htmlspecialchars($ls['feature9_title']); ?></h3>
            <p><?= htmlspecialchars($ls['feature9_desc']); ?></p>
        </div>
    </div>
</section>

<!-- LOGIN SECTION -->
<section id="login" class="section-container" style="background: white;">
    <div class="login-wrapper">
        <div class="login-form-container">
            <h3>LOGIN</h3>
            <form action="process_login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Masukkan Username Anda" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label>Kata Sandi (Password)</label>
                    <input type="password" name="password" placeholder="Masukkan Kata Sandi" required>
                </div>

                <button type="submit" class="btn-submit">Login ke Dashboard</button>
            </form>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-about">
            <h3><?= htmlspecialchars($ls['footer_title']); ?></h3>
            <p><?= htmlspecialchars($ls['footer_desc']); ?></p>
        </div>
        <div class="footer-links">
            <h4><?= htmlspecialchars($ls['footer_contact_title']); ?></h4>
            <ul>
                <?php
                function formatToWebmail($u) {
                    $u = str_replace(' ', '', trim($u));
                    if(strpos($u, 'mailto:') === 0) {
                        return 'https://mail.google.com/mail/?view=cm&fs=1&to=' . substr($u, 7);
                    }
                    if(strpos($u, '@') !== false && strpos($u, 'http') === false) {
                        return 'https://mail.google.com/mail/?view=cm&fs=1&to=' . $u;
                    }
                    return $u;
                }
                $url1 = formatToWebmail($ls['footer_contact_1_url']);
                $url2 = formatToWebmail($ls['footer_contact_2_url']);
                $url3 = formatToWebmail($ls['footer_contact_3_url']);
                ?>
                <?php if(!empty($ls['footer_contact_1_text'])): ?>
                    <li><a href="<?= htmlspecialchars($url1); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($ls['footer_contact_1_text']); ?></a></li>
                <?php endif; ?>
                <?php if(!empty($ls['footer_contact_2_text'])): ?>
                    <li><a href="<?= htmlspecialchars($url2); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($ls['footer_contact_2_text']); ?></a></li>
                <?php endif; ?>
                <?php if(!empty($ls['footer_contact_3_text'])): ?>
                    <li><a href="<?= htmlspecialchars($url3); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($ls['footer_contact_3_text']); ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="copyright">
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($ls['footer_copyright']); ?>
    </div>
</footer>

<script>
// Skrip untuk membuat menu navigasi aktif sesuai dengan posisi scroll
window.addEventListener('scroll', () => {
    let current = '';
    const sections = document.querySelectorAll('.section-container, #beranda');
    const navLinks = document.querySelectorAll('.nav-links a');

    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (current === 'beranda' && href === '#page-top') {
            link.classList.add('active');
        } else if (current !== '' && href === '#' + current) {
            link.classList.add('active');
        }
    });
});
</script>

</body>
</html>
