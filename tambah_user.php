<?php

session_start();

include 'config/database.php';

// =======================
// CEK LOGIN
// =======================

if(!isset($_SESSION['login'])){

    header("Location:index.php");
    exit;

}

// =======================
// CEK ADMIN
// =======================

if($_SESSION['role_id'] != 1){

    header("Location:index.php");
    exit;

}

// =======================
// CSRF TOKEN
// =======================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =======================
// PROSES SIMPAN USER
// =======================

if(isset($_POST['submit'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $school_name = trim($_POST['school_name']);
    $role_id = (int) $_POST['role_id'];

    // =======================
    // VALIDASI
    // =======================

    if(
        empty($full_name)
        ||
        empty($username)
        ||
        empty($email)
        ||
        empty($password)
        ||
        empty($role_id)
    ){

        echo "

        <script>

            alert('Semua field wajib diisi');

            location.replace('tambah_user.php');

        </script>

        ";

        exit;

    }

    // =======================
    // CEK EMAIL
    // =======================

    $stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR username = ?");
    mysqli_stmt_bind_param($stmt_cek, "ss", $email, $username);
    mysqli_stmt_execute($stmt_cek);
    mysqli_stmt_store_result($stmt_cek);
    $is_duplicate = mysqli_stmt_num_rows($stmt_cek) > 0;
    mysqli_stmt_close($stmt_cek);

    if($is_duplicate){

        echo "

        <script>

            alert('Email atau Username sudah digunakan');

            location.replace('tambah_user.php');

        </script>

        ";

        exit;

    }

    // =======================
    // HASH PASSWORD
    // =======================

    $hash_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // =======================
    // SIMPAN USER
    // =======================

    $stmt_insert = mysqli_prepare($conn, "
        INSERT INTO users (role_id, username, full_name, school_name, email, password) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt_insert, "isssss", $role_id, $username, $full_name, $school_name, $email, $hash_password);
    mysqli_stmt_execute($stmt_insert);
    $insert = mysqli_stmt_affected_rows($stmt_insert) > 0;
    mysqli_stmt_close($stmt_insert);

    // =======================
    // BERHASIL
    // =======================

    if($insert){

        $_SESSION['success'] = 'User berhasil ditambahkan';
        header("Location: kelola_user.php");
        exit;

    }else{

        echo "

        <script>

            alert('Gagal menambahkan user');

            location.replace('tambah_user.php');

        </script>

        ";

    }

}

?>

<!DOCTYPE html>
<html>
<head>

<title>Tambah User</title>

<style>

body{

    font-family:Arial;
    background:#f4f6f9;
    margin:0;

}

.wrapper{ display:flex; min-height:100vh; }
.sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
.sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
.sidebar .menu a:hover{ background:#34495e; }
.main-content{ flex:1; min-width:0; display:flex; justify-content:center; align-items:center; padding:30px; }

.box{

    width:500px;

    background:white;

    padding:40px;

    border-radius:20px;

    box-shadow:
    0px 0px 15px
    rgba(0,0,0,0.08);

}

h2{

    margin-top:0;

    color:#2c3e50;

    text-align:center;

    margin-bottom:30px;

}

.input-group{

    margin-bottom:20px;

}

label{

    display:block;

    margin-bottom:8px;

    font-weight:bold;

    color:#555;

}

input,
select{

    width:100%;

    padding:14px;

    border:1px solid #ccc;

    border-radius:10px;

    font-size:14px;

    box-sizing:border-box;

    outline:none;

}

input:focus,
select:focus{

    border-color:#3498db;

}

button{

    width:100%;

    padding:15px;

    border:none;

    border-radius:10px;

    background:#27ae60;

    color:white;

    font-size:16px;

    font-weight:bold;

    cursor:pointer;

    transition:0.3s;

}

button:hover{

    background:#219150;

}

.kembali{

    display:block;

    text-align:center;

    margin-top:20px;

    color:#3498db;

    text-decoration:none;

    font-size:14px;

}

.kembali:hover{

    text-decoration:underline;

}

@media(max-width:768px){
    .wrapper{ flex-direction:column; }
    .sidebar{ width:100%; height:auto; position:static; }
    .main-content{ padding:15px; }
    .box{ width:100%; padding:20px; }
}

</style>

</head>

<body>

<div class="wrapper">
    <div class="sidebar">
        <div class="logo">
            ADMIN PANEL
        </div>
        <div class="menu">
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="upload_materi.php">Upload Materi</a>
            <a href="review_materials.php">Review Contributor</a>
            <a href="kelola_request.php">Request Materi</a>
            <a href="analytics.php">Analytics</a>
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

<div class="main-content">
<div class="box">

    <h2>

        Tambah User Baru

    </h2>

    <form method="POST">

        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">

        <!-- NAMA -->

        <div class="input-group">

            <label>

                Nama Lengkap

            </label>

            <input
                type="text"
                name="full_name"
                required
            >

        </div>

        <!-- USERNAME -->

        <div class="input-group">

            <label>

                Username

            </label>

            <input
                type="text"
                name="username"
                required
            >

        </div>

        <!-- EMAIL -->

        <div class="input-group">

            <label>

                Email

            </label>

            <input
                type="email"
                name="email"
                required
            >

        </div>

        <!-- PASSWORD -->

        <div class="input-group">

            <label>

                Password

            </label>

            <input
                type="password"
                name="password"
                required
            >

        </div>

        <!-- SEKOLAH -->

        <div class="input-group">

            <label>

                Nama Sekolah

            </label>

            <input
                type="text"
                name="school_name"
            >

        </div>

        <!-- ROLE -->

        <div class="input-group">

            <label>

                Role

            </label>

            <select
                name="role_id"
                required
            >

                <option value="">

                    -- Pilih Role --

                </option>

                <option value="1">

                    Admin

                </option>

                <option value="2">

                    Guru

                </option>

                <option value="4">

                    External Contributor

                </option>

            </select>

        </div>

        <!-- BUTTON -->

        <button
            type="submit"
            name="submit"
        >

            Simpan User

        </button>

    </form>

    <a
        href="kelola_user.php"
        class="kembali"
    >

        ← Kembali ke Kelola User

    </a>

</div>

</div>
</div>
</body>
</html>
