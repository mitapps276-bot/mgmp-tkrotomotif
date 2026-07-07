<?php
// =====================================================
// SI-LIAK TELEGRAM NOTIFIKASI HELPER
// File: config/telegram.php
//
// AMAN: Semua fungsi bersifat non-blocking.
// Jika Telegram gagal/timeout, proses utama
// sistem TIDAK akan terganggu sama sekali.
// =====================================================

if (!defined('TELEGRAM_BOT_TOKEN')) {
    $tg_token_from_db = '';

    // Coba baca dari database (prioritas utama)
    if (isset($conn) && $conn) {
        try {
            $tg_q = @mysqli_query($conn, "SELECT setting_value FROM site_settings WHERE setting_key = 'telegram_bot_token' LIMIT 1");
            if ($tg_q && mysqli_num_rows($tg_q) > 0) {
                $tg_row = mysqli_fetch_assoc($tg_q);
                $tg_token_from_db = isset($tg_row['setting_value']) ? $tg_row['setting_value'] : '';
            }
        } catch (Exception $e) {
            // Abaikan jika tabel site_settings belum ada, biarkan membaca dari file config
        }
    }

    if (!empty($tg_token_from_db)) {
        define('TELEGRAM_BOT_TOKEN', $tg_token_from_db);
    } else {
        // Fallback: coba dari file config (untuk server yang tidak pakai DB)
        $telegram_config_file = __DIR__ . '/telegram_config.php';
        if (file_exists($telegram_config_file)) {
            require_once $telegram_config_file;
        }
        if (!defined('TELEGRAM_BOT_TOKEN')) {
            define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
        }
    }
}

/**
 * Kirim pesan Telegram ke satu chat_id (personal).
 * Non-blocking: timeout 3 detik, error diabaikan.
 *
 * @param string $chat_id  Chat ID penerima
 * @param string $pesan    Isi pesan (mendukung Markdown)
 * @return bool            true jika berhasil, false jika gagal/skip
 */
function kirimTelegram($chat_id, $pesan) {
    $token = TELEGRAM_BOT_TOKEN;

    // Jika token kosong atau chat_id kosong, skip tanpa error
    if (empty($token) || empty($chat_id)) {
        return false;
    }

    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = array(
        'chat_id'    => $chat_id,
        'text'       => $pesan,
        'parse_mode' => 'HTML',
    );

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);          // Maksimal tunggu 3 detik
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);   // Koneksi maksimal 3 detik
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode == 200);
    } catch (Exception $e) {
        // Abaikan semua error, jangan ganggu proses utama
        return false;
    }
}

/**
 * Broadcast pesan Telegram ke SEMUA guru yang punya chat_id.
 * Non-blocking per kiriman.
 *
 * @param object $conn   Koneksi mysqli
 * @param string $pesan  Isi pesan
 */
function broadcastTelegram($conn, $pesan) {
    $token = TELEGRAM_BOT_TOKEN;
    if (empty($token)) return;

    try {
        $q = mysqli_query($conn, "SELECT telegram_chat_id FROM users WHERE telegram_chat_id IS NOT NULL AND telegram_chat_id != '' AND role_id != 1");
        if (!$q) return;

        while ($row = mysqli_fetch_assoc($q)) {
            kirimTelegram($row['telegram_chat_id'], $pesan);
        }
    } catch (Exception $e) {
        return;
    }
}

/**
 * Kirim notifikasi ke uploader/kontributor berdasarkan material_id.
 *
 * @param object $conn        Koneksi mysqli
 * @param int    $material_id ID materi
 * @param string $pesan       Isi pesan
 */
function notifKontributorTelegram($conn, $material_id, $pesan) {
    try {
        $mid = intval($material_id);
        $q = mysqli_query($conn, "
            SELECT u.telegram_chat_id 
            FROM materials m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id = '$mid' AND u.telegram_chat_id IS NOT NULL AND u.telegram_chat_id != ''
        ");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            kirimTelegram($row['telegram_chat_id'], $pesan);
        }
    } catch (Exception $e) {
        return;
    }
}

/**
 * Kirim notifikasi ke guru yang punya request yang di-match.
 *
 * @param object $conn       Koneksi mysqli
 * @param int    $request_id ID request yang selesai
 * @param string $pesan      Isi pesan
 */
function notifGuruRequestTelegram($conn, $request_id, $pesan) {
    try {
        $rid = intval($request_id);
        $q = mysqli_query($conn, "
            SELECT u.telegram_chat_id 
            FROM material_requests mr
            LEFT JOIN users u ON mr.user_id = u.id
            WHERE mr.id = '$rid' AND u.telegram_chat_id IS NOT NULL AND u.telegram_chat_id != ''
        ");
        if ($q && mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_assoc($q);
            kirimTelegram($row['telegram_chat_id'], $pesan);
        }
    } catch (Exception $e) {
        return;
    }
}
