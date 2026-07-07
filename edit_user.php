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
// MIGRATION: ADD TELEGRAM CHAT ID COLUMN (Jika belum ada)
// =======================
try {
    $cek_kolom = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'telegram_chat_id'");
    if($cek_kolom && mysqli_num_rows($cek_kolom) == 0){
        @mysqli_query($conn, "ALTER TABLE users ADD telegram_chat_id VARCHAR(50) NULL AFTER password");
    }
} catch (Exception $e) {
    // Abaikan jika tidak ada akses ALTER TABLE
}

// =======================
// CSRF TOKEN
// =======================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// =======================
// AMBIL ID USER
// =======================

if(!isset($_GET['id'])){

    header("Location:kelola_user.php");
    exit;

}

$id = (int) $_GET['id'];

// =======================
// AMBIL DATA USER
// =======================

$query = mysqli_query($conn, "

SELECT *
FROM users
WHERE id = '$id'

");

$user = mysqli_fetch_assoc($query);

// =======================
// JIKA USER TIDAK ADA
// =======================

if(!$user){

    echo "

    <script>

        alert('User tidak ditemukan');

        location.replace('kelola_user.php');

    </script>

    ";

    exit;

}

// =======================
// UPDATE USER
// =======================

if(isset($_POST['update'])){

    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        die("Error: Token keamanan (CSRF) tidak valid!");
    }

    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $school_name = trim($_POST['school_name']);
    $role_id = (int) $_POST['role_id'];
    $password = trim($_POST['password']);
    $telegram_chat_id = isset($_POST['telegram_chat_id']) ? trim($_POST['telegram_chat_id']) : '';
    $telegram_chat_id = preg_replace('/[^0-9\-]/', '', $telegram_chat_id); // Hanya angka & tanda minus

    // =======================
    // VALIDASI EMAIL DUPLIKAT
    // =======================

    $stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
    mysqli_stmt_bind_param($stmt_cek, "ssi", $email, $username, $id);
    mysqli_stmt_execute($stmt_cek);
    mysqli_stmt_store_result($stmt_cek);
    $is_duplicate = mysqli_stmt_num_rows($stmt_cek) > 0;
    mysqli_stmt_close($stmt_cek);

    if($is_duplicate){

        echo "

        <script>

            alert('Email atau Username sudah digunakan oleh akun lain');

            location.replace('edit_user.php?id=$id');

        </script>

        ";

        exit;

    }

    // =======================
    // JIKA PASSWORD DIISI
    // =======================

    if(!empty($password)){

        $hash_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt_update = mysqli_prepare($conn, "
            UPDATE users SET full_name = ?, username = ?, email = ?, school_name = ?, role_id = ?, password = ?, telegram_chat_id = ? WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt_update, "ssssissi", $full_name, $username, $email, $school_name, $role_id, $hash_password, $telegram_chat_id, $id);
        mysqli_stmt_execute($stmt_update);
        $update = mysqli_stmt_affected_rows($stmt_update) >= 0;
        mysqli_stmt_close($stmt_update);

    }

    // =======================
    // JIKA PASSWORD KOSONG
    // =======================

    else{

        $stmt_update = mysqli_prepare($conn, "
            UPDATE users SET full_name = ?, username = ?, email = ?, school_name = ?, role_id = ?, telegram_chat_id = ? WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt_update, "sssssii", $full_name, $username, $email, $school_name, $role_id, $telegram_chat_id, $id);
        mysqli_stmt_execute($stmt_update);
        $update = mysqli_stmt_affected_rows($stmt_update) >= 0;
        mysqli_stmt_close($stmt_update);

    }

    // =======================
    // HASIL UPDATE
    // =======================

    if($update){

        $_SESSION['success'] = 'User berhasil diupdate';
        header("Location: kelola_user.php");
        exit;

    }else{

        echo "

        <script>

            alert('Gagal update user');

            location.replace('edit_user.php?id=$id');

        </script>

        ";

    }

}

?>

<!DOCTYPE html>
<html>
<head>

<title>Edit User</title>

<style>

body{

    margin:0;
    font-family:Arial;
    background:#f4f6f9;

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

    text-align:center;

    color:#2c3e50;

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

    border-radius:10px;

    border:1px solid #ccc;

    outline:none;

    box-sizing:border-box;

}

input:focus,
select:focus{

    border-color:#3498db;

}

.info{

    font-size:12px;

    color:#777;

    margin-top:5px;

}

button{

    width:100%;

    padding:15px;

    border:none;

    border-radius:10px;

    background:#f39c12;

    color:white;

    font-size:16px;

    font-weight:bold;

    cursor:pointer;

    transition:0.3s;

}

button:hover{

    background:#d68910;

}

.kembali{

    display:block;

    margin-top:20px;

    text-align:center;

    text-decoration:none;

    color:#3498db;

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

        Edit User

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
                value="<?= htmlspecialchars($user['full_name']); ?>"
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
                value="<?= htmlspecialchars(isset($user['username']) ? $user['username'] : ''); ?>"
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
                value="<?= htmlspecialchars($user['email']); ?>"
            >

        </div>

        <!-- PASSWORD -->

        <div class="input-group">

            <label>

                Password Baru

            </label>

            <input
                type="password"
                name="password"
            >

            <div class="info">

                Kosongkan jika tidak ingin mengganti password

            </div>

        </div>

        <!-- SEKOLAH -->

        <div class="input-group">

            <label>

                Nama Sekolah

            </label>

            <input
                type="text"
                name="school_name"
                value="<?= htmlspecialchars($user['school_name']); ?>"
            >

        </div>

        <!-- TELEGRAM CHAT ID -->

        <div class="input-group">

            <label>
                Telegram Chat ID
                <small style="display:block; color:#888; font-size:12px; font-weight:normal; margin-top:3px;">
                    Cari bot <strong>@userinfobot</strong> di Telegram lalu klik /start. Copy angka di bagian "Id" dan paste di sini.
                </small>
            </label>

            <input
                type="text"
                name="telegram_chat_id"
                placeholder="Contoh: 123456789"
                value="<?= htmlspecialchars(isset($user['telegram_chat_id']) ? $user['telegram_chat_id'] : ''); ?>"
                style="font-family: monospace;"
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

                <option
                    value="1"
                    <?= ($user['role_id'] == 1) ? 'selected' : ''; ?>
                >

                    Admin

                </option>

                <option
                    value="2"
                    <?= ($user['role_id'] == 2) ? 'selected' : ''; ?>
                >

                    Guru

                </option>

                <option
                    value="4"
                    <?= ($user['role_id'] == 4) ? 'selected' : ''; ?>
                >

                    External Contributor

                </option>

            </select>

        </div>

        <!-- BUTTON -->

        <button
            type="submit"
            name="update"
        >

            Update User

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
