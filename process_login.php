<?php

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

$stmt_check_ip = mysqli_prepare($conn, "SELECT attempts, UNIX_TIMESTAMP(last_attempt) as last_attempt_ts FROM login_attempts WHERE ip_address = ?");
mysqli_stmt_bind_param($stmt_check_ip, "s", $ip_address);
mysqli_stmt_execute($stmt_check_ip);
$result_ip = mysqli_stmt_get_result($stmt_check_ip);
$ip_data = mysqli_fetch_assoc($result_ip);
mysqli_stmt_close($stmt_check_ip);

if($ip_data && $ip_data['attempts'] >= $max_attempts) {
    $time_passed = time() - $ip_data['last_attempt_ts'];
    if($time_passed < $lockout_time) {
        $wait_time = ceil(($lockout_time - $time_passed) / 60);
        $_SESSION['login_error'] = "Terlalu banyak percobaan gagal. Akses dikunci sementara selama $wait_time menit.";
        header("Location: index.php#login");
        exit;
    } else {
        // Jika waktu kunci sudah lewat, reset percobaan
        mysqli_query($conn, "DELETE FROM login_attempts WHERE ip_address = '$ip_address'");
    }
}

// =====================================
// AMBIL INPUT
// =====================================

$username = trim($_POST['username']);

$password = trim($_POST['password']);

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
        // HANYA UNTUK GURU
        // =====================================

        if($role_id == 2){

            // =====================================
            // CEK LOGIN HARI INI
            // =====================================

            $cek_login = mysqli_query($conn, "

            SELECT id

            FROM login_activity

            WHERE user_id = '$user_id'

            AND login_date = '$tanggal_hari_ini'

            LIMIT 1

            ");

            // =====================================
            // JIKA BELUM LOGIN HARI INI
            // =====================================

            if(

                mysqli_num_rows($cek_login) == 0

            ){

                // =====================================
                // INSERT LOGIN BARU
                // =====================================

                $insert_login = mysqli_query($conn, "

                INSERT INTO login_activity(

                    user_id,
                    login_date,
                    login_time

                )VALUES(

                    '$user_id',
                    '$tanggal_hari_ini',
                    '$waktu_login'

                )

                ");

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

                mysqli_query($conn, "

                UPDATE login_activity

                SET login_time = '$waktu_login'

                WHERE user_id = '$user_id'

                AND login_date = '$tanggal_hari_ini'

                ");

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

    $_SESSION['login_error'] = "Username atau Password yang Anda masukkan salah!";
    header("Location: index.php#login");
    exit;

}

?>