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

// Auto-create public_messages table if not exists
$cek_table = @mysqli_query($conn, "SHOW TABLES LIKE 'public_messages'");
if ($cek_table && mysqli_num_rows($cek_table) == 0) {
    $sql_create = "CREATE TABLE public_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $sql_create);
}

if ($method === 'GET') {
    // Ambil 50 pesan terakhir
    $sql = "SELECT pm.*, u.full_name, u.profile_photo 
            FROM public_messages pm
            JOIN users u ON pm.user_id = u.id
            ORDER BY pm.id DESC LIMIT 50";
            
    $q = mysqli_query($conn, $sql);
    $messages = [];
    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $row['is_me'] = ($row['user_id'] == $user_id);
            $row['formatted_time'] = date('d M Y H:i', strtotime($row['created_at']));
            $messages[] = $row;
        }
        // Karena kita ORDER BY DESC dan LIMIT 50 (untuk mendapatkan yg terbaru),
        // kita perlu membalik urutannya agar yang terlama di atas dan terbaru di bawah.
        $messages = array_reverse($messages);
        
        echo json_encode(['status' => 'success', 'data' => $messages]);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($method === 'POST') {
    if (empty(trim($_POST['message_text']))) {
        echo json_encode(['status' => 'error', 'message' => 'Message text required']);
        exit;
    }
    
    $message_text = mysqli_real_escape_string($conn, trim($_POST['message_text']));
    
    $sql_insert = "INSERT INTO public_messages (user_id, message_text) VALUES ($user_id, '$message_text')";
    if (mysqli_query($conn, $sql_insert)) {
        // Auto-Sweep: Hapus pesan yang usianya lebih dari 3 hari secara otomatis
        @mysqli_query($conn, "DELETE FROM public_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}
?>
