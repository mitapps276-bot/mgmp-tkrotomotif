<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include 'config/database.php';
echo "--- MATERIALS ---\n";
$res = mysqli_query($conn, "SELECT id, title, category, grade_level, status FROM materials ORDER BY id DESC LIMIT 5");
while($row = mysqli_fetch_assoc($res)) print_r($row);

echo "--- REQUESTS ---\n";
$res2 = mysqli_query($conn, "SELECT id, jenis_request, deskripsi, status, admin_note FROM material_requests ORDER BY id DESC LIMIT 5");
while($row2 = mysqli_fetch_assoc($res2)) print_r($row2);
?>
