<?php
//error_reporting(0);
//ini_set('display_errors', 0);
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'config/functions.php';

// Auto-create table if it doesn't exist
try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS material_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT NULL DEFAULT NULL,
        comment_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES material_comments(id) ON DELETE CASCADE
    )");
    
    // Add parent_id column if the table already existed before this update
    mysqli_query($conn, "ALTER TABLE material_comments ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER user_id");
    mysqli_query($conn, "ALTER TABLE material_comments ADD CONSTRAINT fk_parent_comment FOREIGN KEY (parent_id) REFERENCES material_comments(id) ON DELETE CASCADE");
} catch (Exception $e) {
    // Ignore error if table exists but foreign key fails, or column already exists
}

function sendJson($data) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['status' => 'error', 'message' => 'Unauthorized']);
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 2;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!isset($_GET['material_id'])) {
        sendJson(['status' => 'error', 'message' => 'Material ID required']);
    }
    
    $material_id = (int)$_GET['material_id'];
    $comments = [];
    
    $sql = "SELECT c.*, u.full_name as nama, u.school_name as sekolah_asal 
            FROM material_comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.material_id = $material_id 
            ORDER BY c.created_at ASC";
            
    try {
        $q = mysqli_query($conn, $sql);
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $row['is_owner'] = ($row['user_id'] == $user_id || $role_id == 1) ? true : false;
                $row['formatted_date'] = date('d M Y H:i', strtotime($row['created_at']));
                $comments[] = $row;
            }
            sendJson(['status' => 'success', 'data' => $comments]);
        } else {
            sendJson(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
    } catch (Exception $e) {
        sendJson(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

if ($method === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // DELETE LOGIC
        if (!isset($_POST['comment_id'])) {
            sendJson(['status' => 'error', 'message' => 'Comment ID required']);
        }
        $comment_id = (int)$_POST['comment_id'];
        
        // Verify ownership
        if ($role_id != 1) {
            $verify = mysqli_query($conn, "SELECT id FROM material_comments WHERE id = $comment_id AND user_id = $user_id");
            if (mysqli_num_rows($verify) === 0) {
                sendJson(['status' => 'error', 'message' => 'You do not have permission to delete this comment.']);
            }
        }
        
        try {
            $delete = mysqli_query($conn, "DELETE FROM material_comments WHERE id = $comment_id");
            if ($delete) {
                sendJson(['status' => 'success']);
            } else {
                sendJson(['status' => 'error', 'message' => 'Failed to delete']);
            }
        } catch (Exception $e) {
            sendJson(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        // ADD COMMENT LOGIC
        if (!isset($_POST['material_id']) || !isset($_POST['comment_text']) || trim($_POST['comment_text']) === '') {
            sendJson(['status' => 'error', 'message' => 'Invalid data']);
        }
        
        $material_id = (int)$_POST['material_id'];
        $comment_text = mysqli_real_escape_string($conn, trim($_POST['comment_text']));
        
        $parent_id_val = "NULL";
        if (isset($_POST['parent_id']) && !empty($_POST['parent_id'])) {
            $parent_id_val = (int)$_POST['parent_id'];
        }
        
        $sql = "INSERT INTO material_comments (material_id, user_id, parent_id, comment_text) VALUES ($material_id, $user_id, $parent_id_val, '$comment_text')";
        try {
            if (mysqli_query($conn, $sql)) {
                // Trigger Telegram Notif
                if (function_exists('notifGuruCommentTelegram')) {
                    // Ambil nama pengirim dari DB
                    $user_name = 'Seseorang';
                    $un_q = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $user_id");
                    if ($un_q && mysqli_num_rows($un_q) > 0) {
                        $user_name = mysqli_fetch_assoc($un_q)['full_name'];
                    }

                    $mat_q = mysqli_query($conn, "SELECT file_name FROM materials WHERE id = $material_id");
                    $mat_title = "Materi";
                    if ($mat_q && mysqli_num_rows($mat_q) > 0) {
                        $mat_title = mysqli_fetch_assoc($mat_q)['file_name'];
                    }
                    
                    $pesan = "💬 *Komentar Baru di Materi Anda*\n\n"
                           . "👤 *Dari:* " . $user_name . "\n"
                           . "📄 *Materi:* " . $mat_title . "\n"
                           . "💬 *Komentar:* " . stripslashes($comment_text) . "\n\n"
                           . "Silakan cek dashboard SI-LIAK Anda untuk membalas.";
                    notifGuruCommentTelegram($conn, $material_id, $pesan);
                }
                
                sendJson(['status' => 'success']);
            } else {
                sendJson(['status' => 'error', 'message' => mysqli_error($conn)]);
            }
        } catch (Exception $e) {
            sendJson(['status' => 'error', 'message' => 'Gagal menyimpan (Materi mungkin tidak bisa dikomentari): ' . $e->getMessage()]);
        }
    }
}
?>
