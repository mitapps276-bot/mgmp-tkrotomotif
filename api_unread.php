<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$unread_counts = [];

$cek_pm_table = @mysqli_query($conn, "SHOW TABLES LIKE 'private_messages'");
if ($cek_pm_table && mysqli_num_rows($cek_pm_table) > 0) {
    $q_unread = @mysqli_query($conn, "SELECT sender_id, COUNT(*) as count FROM private_messages WHERE receiver_id = $user_id AND is_read = 0 GROUP BY sender_id");
    if ($q_unread) {
        while ($r = mysqli_fetch_assoc($q_unread)) {
            $unread_counts[$r['sender_id']] = $r['count'];
        }
    }
}

echo json_encode(['status' => 'success', 'data' => $unread_counts]);
?>
