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

    $_SESSION['error'] = "Akun yang sedang login tidak bisa dihapus";
    header("Location:kelola_user.php");
    exit;

}

// =======================
// CEK USER
// =======================

$cek_user = mysqli_query($conn, "

SELECT id
FROM users
WHERE id = '$id'

");

if(mysqli_num_rows($cek_user) == 0){

    $_SESSION['error'] = "User tidak ditemukan";
    header("Location:kelola_user.php");
    exit;

}

// =======================
// HAPUS USER
// =======================

mysqli_query($conn, "

DELETE FROM login_activity
WHERE user_id = '$id'

");

mysqli_query($conn, "

DELETE FROM downloads
WHERE user_id = '$id'

");

mysqli_query($conn, "

UPDATE materials
SET user_id = NULL
WHERE user_id = '$id'

");

$hapus = mysqli_query($conn, "

DELETE FROM users
WHERE id = '$id'

");

if($hapus){

    $_SESSION['success'] = "User berhasil dihapus!";
    header("Location:kelola_user.php");
    exit;

}else{

    $_SESSION['error'] = "Gagal menghapus user";
    header("Location:kelola_user.php");
    exit;

}

?>
