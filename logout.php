<?php

session_start();

if (isset($_SESSION['user_id'])) {
    include 'config/database.php';
    if ($conn) {
        $user_id = (int)$_SESSION['user_id'];
        @mysqli_query($conn, "UPDATE users SET last_activity = NULL WHERE id = $user_id");
    }
}

session_destroy();

// kembali ke halaman index
header("Location:index.php");

exit;

?>