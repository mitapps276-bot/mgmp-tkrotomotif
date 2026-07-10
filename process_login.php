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

date_default_timezone_set('Asia/Makassar');

include 'config/database.php';

// =====================================
// CEK METHOD
// =====================================

if($_SERVER['REQUEST_METHOD'] != 'POST'){

    header("Location:index.php");
    exit;

}

// =====================================
// VALIDASI CSRF TOKEN
// =====================================
if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
    die("Error: Token keamanan (CSRF) pada form login tidak valid!");
}

// =====================================
// AMBIL INPUT
// =====================================

$username = trim($_POST['username']);
$password = trim($_POST['password']);

// =====================================
// PROTEKSI BRUTE FORCE (RATE LIMITING)
// =====================================
$max_attempts = 3; // Maksimal percobaan gagal
$lockout_time = 600; // Waktu kunci 600 detik (10 Menit)

$ip_address = $_SERVER['REMOTE_ADDR'];

// Buat tabel jika belum ada
$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'login_attempts'");
if(mysqli_num_rows($cek_tabel) == 0){
    mysqli_query($conn, "CREATE TABLE login_attempts (ip_address VARCHAR(45) PRIMARY KEY, attempts INT DEFAULT 1, last_attempt DATETIME)");
}
$cek_tabel_user = mysqli_query($conn, "SHOW TABLES LIKE 'login_attempts_user'");
if(mysqli_num_rows($cek_tabel_user) == 0){
    mysqli_query($conn, "CREATE TABLE login_attempts_user (username VARCHAR(100) PRIMARY KEY, attempts INT DEFAULT 1, last_attempt DATETIME)");
}

$stmt_check_ip = mysqli_prepare($conn, "SELECT attempts, UNIX_TIMESTAMP(last_attempt) as last_attempt_ts FROM login_attempts WHERE ip_address = ?");
mysqli_stmt_bind_param($stmt_check_ip, "s", $ip_address);
mysqli_stmt_execute($stmt_check_ip);
$result_ip = mysqli_stmt_get_result($stmt_check_ip);
$ip_data = mysqli_fetch_assoc($result_ip);
mysqli_stmt_close($stmt_check_ip);

$stmt_check_user = mysqli_prepare($conn, "SELECT attempts, UNIX_TIMESTAMP(last_attempt) as last_attempt_ts FROM login_attempts_user WHERE username = ?");
mysqli_stmt_bind_param($stmt_check_user, "s", $username);
mysqli_stmt_execute($stmt_check_user);
$result_user = mysqli_stmt_get_result($stmt_check_user);
$user_data = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_check_user);

if(($ip_data && $ip_data['attempts'] >= $max_attempts) || ($user_data && $user_data['attempts'] >= $max_attempts)) {
    $ip_time_passed = $ip_data ? (time() - $ip_data['last_attempt_ts']) : 999999;
    $user_time_passed = $user_data ? (time() - $user_data['last_attempt_ts']) : 999999;
    
    $time_passed = min($ip_time_passed, $user_time_passed);
    
    if($time_passed < $lockout_time) {
        $wait_time = ceil(($lockout_time - $time_passed) / 60);
        $_SESSION['login_error'] = "Terlalu banyak percobaan gagal. Akses dikunci sementara selama $wait_time menit.";
        header("Location: index.php#login");
        exit;
    } else {
        // Jika waktu kunci sudah lewat, reset percobaan
        $stmt_del_ip = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE ip_address = ?");
        mysqli_stmt_bind_param($stmt_del_ip, "s", $ip_address);
        mysqli_stmt_execute($stmt_del_ip);
        mysqli_stmt_close($stmt_del_ip);
        $stmt_del = mysqli_prepare($conn, "DELETE FROM login_attempts_user WHERE username = ?");
        mysqli_stmt_bind_param($stmt_del, "s", $username);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);
    }
}

// =====================================
// CEK USER
// =====================================

// MENGGUNAKAN PREPARED STATEMENT UNTUK KEAMANAN EKSTRA TINGGI
$stmt = mysqli_prepare($conn, "
    SELECT users.*, roles.role_name
    FROM users
    LEFT JOIN roles ON users.role_id = roles.id
    WHERE users.username = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);

// =====================================
// QUERY ERROR
// =====================================

if(!$query){

    // Catat pesan error ke log server (aman dari pandangan pengguna luar)
    error_log('Query Error (Login): ' . mysqli_error($conn));
    
    // Tampilkan pesan yang ramah kepada pengguna
    die('Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.');

}

// =====================================
// AMBIL DATA USER
// =====================================

$data = mysqli_fetch_assoc($query);

// =====================================
// CEK USERNAME
// =====================================

if($data){

    // =====================================
    // PASSWORD DATABASE
    // =====================================

    $db_password = $data['password'];

    // =====================================
    // STATUS LOGIN
    // =====================================

    $login_berhasil = false;

    // =====================================
    // PASSWORD HASH
    // =====================================

    if(

        password_verify(
            $password,
            $db_password
        )

    ){

        $login_berhasil = true;

    }

    // =====================================
    // LOGIN BERHASIL
    // =====================================

    if($login_berhasil){

        // =====================================
        // MENCEGAH SESSION FIXATION
        // =====================================
        
        session_regenerate_id(true);
        
        // Hapus riwayat gagal login jika berhasil masuk
        $stmt_del = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE ip_address = ?");
        mysqli_stmt_bind_param($stmt_del, "s", $ip_address);
        mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);

        // =====================================
        // SESSION LOGIN
        // =====================================

        $_SESSION['login'] = true;

        $_SESSION['user_id'] =
        $data['id'];

        $_SESSION['role_id'] =
        $data['role_id'];

        $_SESSION['role'] =
        $data['role_name'];

        $_SESSION['name'] =
        $data['full_name'];

        $_SESSION['email'] =
        $data['email'];

        $_SESSION['username'] =
        $data['username'];

        // =====================================
        // DATA LOGIN
        // =====================================

        $user_id =
        $data['id'];

        $tanggal_hari_ini =
        date('Y-m-d');

        $waktu_login =
        date('Y-m-d H:i:s');

        // =====================================
        // ROLE USER
        // =====================================

        $role_id =
        $data['role_id'];

        // =====================================
        // LOGIN ACTIVITY
        // UNTUK SEMUA ROLE (ADMIN, GURU, EXTERNAL)
        // =====================================

        if($role_id == 1 || $role_id == 2 || $role_id == 4){

            // =====================================
            // CEK LOGIN HARI INI
            // =====================================

            $stmt_cek_login = mysqli_prepare($conn, "SELECT id FROM login_activity WHERE user_id = ? AND login_date = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_cek_login, "is", $user_id, $tanggal_hari_ini);
            mysqli_stmt_execute($stmt_cek_login);
            mysqli_stmt_store_result($stmt_cek_login);
            $num_rows_login = mysqli_stmt_num_rows($stmt_cek_login);
            mysqli_stmt_close($stmt_cek_login);

            // =====================================
            // JIKA BELUM LOGIN HARI INI
            // =====================================

            if(

                $num_rows_login == 0

            ){

                // =====================================
                // INSERT LOGIN BARU
                // =====================================

                $stmt_insert_login = mysqli_prepare($conn, "INSERT INTO login_activity (user_id, login_date, login_time) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt_insert_login, "iss", $user_id, $tanggal_hari_ini, $waktu_login);
                $insert_login = mysqli_stmt_execute($stmt_insert_login);
                mysqli_stmt_close($stmt_insert_login);

                // =====================================
                // DEBUG ERROR
                // =====================================

                if(!$insert_login){

                    // Catat pesan error ke log server
                    error_log('Activity Log Error (Login): ' . mysqli_error($conn));
                    
                    // Tampilkan pesan yang ramah kepada pengguna
                    die('Terjadi kesalahan pada sistem pencatatan aktivitas. Silakan coba beberapa saat lagi.');

                }

            }

            // =====================================
            // UPDATE LOGIN TERAKHIR
            // =====================================

            else{

                $stmt_update_login = mysqli_prepare($conn, "UPDATE login_activity SET login_time = ? WHERE user_id = ? AND login_date = ?");
                mysqli_stmt_bind_param($stmt_update_login, "sis", $waktu_login, $user_id, $tanggal_hari_ini);
                mysqli_stmt_execute($stmt_update_login);
                mysqli_stmt_close($stmt_update_login);

            }

        }

        // =====================================
        // ADMIN
        // =====================================

        if($role_id == 1){

            header(
                "Location:dashboard_admin.php"
            );

            exit;

        }

        // =====================================
        // GURU
        // =====================================

        elseif($role_id == 2){

            header(
                "Location:dashboard.php"
            );

            exit;

        }

        // =====================================
        // EXTERNAL CONTRIBUTOR
        // =====================================

        elseif($role_id == 4){

            header(
                "Location:contributor_upload.php"
            );

            exit;

        }

        // =====================================
        // ROLE TIDAK VALID
        // =====================================

        else{

            session_unset();
            $_SESSION['login_error'] = "Role akun tidak valid!";
            header("Location: index.php#login");
            exit;

        }

    }

    // =====================================
    // PASSWORD SALAH
    // =====================================

    else{
        $stmt_fail = mysqli_prepare($conn, "INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        mysqli_stmt_bind_param($stmt_fail, "s", $ip_address);
        mysqli_stmt_execute($stmt_fail);
        mysqli_stmt_close($stmt_fail);

        $stmt_fail_user = mysqli_prepare($conn, "INSERT INTO login_attempts_user (username, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        mysqli_stmt_bind_param($stmt_fail_user, "s", $username);
        mysqli_stmt_execute($stmt_fail_user);
        mysqli_stmt_close($stmt_fail_user);

        $_SESSION['login_error'] = "Username atau Password yang Anda masukkan salah!";
        header("Location: index.php#login");
        exit;

    }

}

// =====================================
// USERNAME TIDAK DITEMUKAN
// =====================================

else{
    $stmt_fail = mysqli_prepare($conn, "INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
    mysqli_stmt_bind_param($stmt_fail, "s", $ip_address);
    mysqli_stmt_execute($stmt_fail);
    mysqli_stmt_close($stmt_fail);

    $stmt_fail_user = mysqli_prepare($conn, "INSERT INTO login_attempts_user (username, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
    mysqli_stmt_bind_param($stmt_fail_user, "s", $username);
    mysqli_stmt_execute($stmt_fail_user);
    mysqli_stmt_close($stmt_fail_user);

    $_SESSION['login_error'] = "Username atau Password yang Anda masukkan salah!";
    header("Location: index.php#login");
    exit;

}

?>