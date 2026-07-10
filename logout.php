<?php

session_start();

session_destroy();

// kembali ke halaman index
header("Location:index.php");

exit;

?>