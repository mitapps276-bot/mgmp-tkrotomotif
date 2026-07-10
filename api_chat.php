<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Auto-create private_messages table if not exists
$cek_table = @mysqli_query($conn, "SHOW TABLES LIKE 'private_messages'");
if ($cek_table && mysqli_num_rows($cek_table) == 0) {
    $sql_create = "CREATE TABLE private_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message_text TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $sql_create);
}

if ($method === 'GET') {
    if (!isset($_GET['target_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Target ID required']);
        exit;
    }
    
    $target_id = (int)$_GET['target_id'];
    
    // Tandai pesan dari target_id ke user_id sebagai sudah dibaca
    mysqli_query($conn, "UPDATE private_messages SET is_read = 1 WHERE sender_id = $target_id AND receiver_id = $user_id AND is_read = 0");
    
    // Ambil percakapan 1-on-1
    $sql = "SELECT pm.*, u.full_name AS sender_name, u.profile_photo 
            FROM private_messages pm
            JOIN users u ON pm.sender_id = u.id
            WHERE (pm.sender_id = $user_id AND pm.receiver_id = $target_id) 
               OR (pm.sender_id = $target_id AND pm.receiver_id = $user_id)
            ORDER BY pm.created_at ASC";
            
    $q = mysqli_query($conn, $sql);
    $messages = [];
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $row['is_me'] = ($row['sender_id'] == $user_id);
            $row['formatted_time'] = date('d M Y H:i', strtotime($row['created_at']));
            $messages[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $messages]);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($method === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!isset($_POST['message_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Message ID required']);
            exit;
        }
        $message_id = (int)$_POST['message_id'];
        
        // Hanya bisa menghapus pesan miliknya sendiri
        $q_del = mysqli_query($conn, "DELETE FROM private_messages WHERE id = $message_id AND sender_id = $user_id");
        if ($q_del) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete']);
        }
        exit;
    }

    if (!isset($_POST['target_id']) || empty(trim($_POST['message_text']))) {
        echo json_encode(['status' => 'error', 'message' => 'Target ID and message text required']);
        exit;
    }
    
    $target_id = (int)$_POST['target_id'];
    $message_text = mysqli_real_escape_string($conn, trim($_POST['message_text']));
    
    $sql_insert = "INSERT INTO private_messages (sender_id, receiver_id, message_text) VALUES ($user_id, $target_id, '$message_text')";
    if (mysqli_query($conn, $sql_insert)) {
        // Auto-Sweep: Hapus pesan yang usianya lebih dari 3 hari secara otomatis
        @mysqli_query($conn, "DELETE FROM private_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        
        // Notifikasi Telegram
        if (function_exists('kirimTelegram')) {
            $q_target = mysqli_query($conn, "SELECT telegram_chat_id FROM users WHERE id = $target_id");
            if ($q_target && mysqli_num_rows($q_target) > 0) {
                $target_chat_id = mysqli_fetch_assoc($q_target)['telegram_chat_id'];
                if (!empty($target_chat_id)) {
                    $sender_name = 'Seseorang';
                    $q_sender = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $user_id");
                    if ($q_sender && mysqli_num_rows($q_sender) > 0) {
                        $sender_name = mysqli_fetch_assoc($q_sender)['full_name'];
                    }
                    
                    $pesan_tg = "💬 *Pesan Pribadi Baru*\n\n"
                              . "👤 *Dari:* " . $sender_name . "\n"
                              . "💬 *Pesan:* " . stripslashes($message_text) . "\n\n"
                              . "Silakan cek dashboard SI-LIAK Anda untuk membalas.";
                    kirimTelegram($target_chat_id, $pesan_tg);
                }
            }
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}
?>
