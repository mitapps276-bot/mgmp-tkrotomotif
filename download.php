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
// CEK ID
// =======================

if(!isset($_GET['id'])){

    echo "
    <script>

        alert('ID materi tidak ditemukan');

        location.replace('data_materi.php');

    </script>
    ";

    exit;

}

$id = intval($_GET['id']);

// =======================
// AMBIL DATA MATERI
// =======================

$stmt = mysqli_prepare($conn, "SELECT * FROM materials WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($query);
mysqli_stmt_close($stmt);

// =======================
// CEK DATA
// =======================

if(!$data){

    echo "
    <script>

        alert('Materi tidak ditemukan');

        location.replace('data_materi.php');

    </script>
    ";

    exit;

}

// =======================
// CEK STATUS
// =======================

if($data['status'] != 'approved'){

    echo "
    <script>

        alert('Materi belum diapprove');

        location.replace('data_materi.php');

    </script>
    ";

    exit;

}

// =======================
// CEK DOWNLOAD SUDAH ADA
// =======================

// Cegah pencatatan poin / analytics untuk file milik sendiri
if($data['user_id'] == NULL || $_SESSION['user_id'] != $data['user_id']){

$cek = mysqli_query($conn, "

    SELECT *
    FROM downloads
    WHERE user_id='".$_SESSION['user_id']."'
    AND material_id='$id'

");

// =======================
// SIMPAN DOWNLOAD
// =======================

if(mysqli_num_rows($cek) == 0){

    mysqli_query($conn, "

        INSERT INTO downloads
        (
            user_id,
            material_id
        )

        VALUES
        (
            '".$_SESSION['user_id']."',
            '$id'
        )

    ");

}

}

// =======================
// FILE
// =======================

$file = "assets/uploads/" . $data['file_name'];

// =======================
// DOWNLOAD FILE
// =======================

if(file_exists($file)){

    header('Content-Description: File Transfer');

    header('Content-Type: application/octet-stream');

    header(

        'Content-Disposition: attachment; filename="' .

        basename($file) .

        '"'

    );

    header('Expires: 0');

    header('Cache-Control: must-revalidate');

    header('Pragma: public');

    header('Content-Length: ' . filesize($file));

    readfile($file);

    exit;

}else{

    echo "
    <script>

        alert('File tidak ditemukan');

        location.replace('data_materi.php');

    </script>
    ";

}
?>