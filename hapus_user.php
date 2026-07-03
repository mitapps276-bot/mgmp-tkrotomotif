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
// VALIDASI ID USER
// =======================

// =======================
// VALIDASI CSRF TOKEN
// =======================
if(!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']){
    die("Error: Token keamanan (CSRF) tidak valid!");
}

if(!isset($_GET['id'])){

    header("Location:kelola_user.php");
    exit;

}

$id = (int) $_GET['id'];

if($id <= 0){

    header("Location:kelola_user.php");
    exit;

}

// =======================
// CEGAH HAPUS AKUN SENDIRI
// =======================

if(isset($_SESSION['user_id']) && $id == (int) $_SESSION['user_id']){

    $_SESSION['error'] = 'Akun yang sedang login tidak bisa dihapus';
    header("Location:kelola_user.php");
    exit;

}

// =======================
// CEK USER
// =======================

$stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_cek, "i", $id);
mysqli_stmt_execute($stmt_cek);
mysqli_stmt_store_result($stmt_cek);
$num_rows = mysqli_stmt_num_rows($stmt_cek);
mysqli_stmt_close($stmt_cek);

if($num_rows == 0){

    $_SESSION['error'] = 'User tidak ditemukan';
    header("Location:kelola_user.php");
    exit;

}

// =======================
// HAPUS USER
// =======================

$stmt_la = mysqli_prepare($conn, "DELETE FROM login_activity WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_la, "i", $id);
mysqli_stmt_execute($stmt_la);
mysqli_stmt_close($stmt_la);

$stmt_dl = mysqli_prepare($conn, "DELETE FROM downloads WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_dl, "i", $id);
mysqli_stmt_execute($stmt_dl);
mysqli_stmt_close($stmt_dl);

$stmt_mat = mysqli_prepare($conn, "UPDATE materials SET user_id = NULL WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_mat, "i", $id);
mysqli_stmt_execute($stmt_mat);
mysqli_stmt_close($stmt_mat);

$stmt_hapus = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_hapus, "i", $id);
$hapus = mysqli_stmt_execute($stmt_hapus);
mysqli_stmt_close($stmt_hapus);

if($hapus){
    $_SESSION['success'] = 'User berhasil dihapus';
    header("Location:kelola_user.php");
    exit;
}else{
    $_SESSION['error'] = 'Gagal menghapus user';
    header("Location:kelola_user.php");
    exit;
}
?>
