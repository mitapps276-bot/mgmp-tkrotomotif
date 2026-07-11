<?php
session_start();
require 'config/database.php';

// Pastikan hanya admin yang bisa mengakses file ini
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    die("<h1>Akses Ditolak!</h1><p>Anda harus login sebagai Admin untuk mengakses halaman ini.</p>");
}
// Blokir akses langsung dari URL (hanya izinkan POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h1 style='color:red;'>Akses Langsung Ditolak!</h1><p>Halaman ini tidak bisa diakses langsung lewat URL. Anda harus menggunakan tombol dari Dashboard Admin.</p><a href='dashboard_admin.php' style='padding: 10px 20px; background-color: #4e73df; color: white; text-decoration: none; border-radius: 5px; display:inline-block; margin-top:20px;'>Kembali ke Dashboard</a></div>");
}

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$error = '';
$success = '';

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error: Token keamanan (CSRF) tidak valid atau kedaluwarsa!");
    }
    if ($step == 3) {
        // Cek validasi langkah 3
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'ya') {
            $error = "Anda harus mengonfirmasi untuk melanjutkan.";
            $step = 2; // Kembali ke langkah 2
        }
    } elseif ($step == 4) {
        if (!isset($_POST['input_3']) || $_POST['input_3'] !== 'ReSet') {
            $error = "Input salah! Anda harus mengetik persis: ReSet";
            $step = 3; 
        }
    } elseif ($step == 5) {
        if (!isset($_POST['input_4']) || $_POST['input_4'] !== 'RESET') {
            $error = "Input salah! Anda harus mengetik persis: RESET";
            $step = 4;
        }
    } elseif ($step == 6) {
        if (!isset($_POST['input_5']) || $_POST['input_5'] !== 'reset') {
            $error = "Input salah! Anda harus mengetik persis: reset";
            $step = 5;
        }
    } elseif ($step == 7) {
        if (!isset($_POST['input_6']) || $_POST['input_6'] !== '12AHASIA(*^#@') {
            $error = "Password Reset SALAH! Proses dibatalkan.";
            $step = 6;
        } else {
            // EKSEKUSI RESET DISINI
            
            // Daftar tabel yang akan dikosongkan (TRUNCATE)
            $tables_to_truncate = [
                'activity_logs',
                'announcements',
                'comments',
                'discussions',
                'downloads',
                'gallery',
                'login_activity',
                'login_attempts',
                'login_attempts_user',
                'login_logs',
                'material_requests',
                'participation_scores',
                'user_badges'
            ];

            mysqli_begin_transaction($conn);
            try {
                // Matikan foreign key check sementara agar truncate berhasil
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

                foreach ($tables_to_truncate as $table) {
                    mysqli_query($conn, "TRUNCATE TABLE `$table`");
                }

                // (Data akun user asli tidak dihapus sesuai permintaan)

                // Nyalakan kembali foreign key check
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
                
                mysqli_commit($conn);
                $success = "RESET SISTEM BERHASIL! Semua data pengujian telah dihapus.";
                $step = 8; // Selesai
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Gagal mereset database: " . $e->getMessage();
                $step = 6;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Sistem MGMP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        h2 { color: #e74a3b; }
        .error { color: red; margin-bottom: 15px; font-weight: bold; }
        .success { color: green; font-size: 1.2em; font-weight: bold; }
        input[type="text"], input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 15px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            text-align: center;
        }
        button {
            background-color: #e74a3b;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
        }
        button:hover {
            background-color: #c0392b;
        }
        .step-info {
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <h2>PERINGATAN!</h2>
        <p>Anda akan melakukan RESET SISTEM. Tindakan ini akan <b>MENGHAPUS SEMUA DATA TRANSAKSI</b> (materi, chat, komentar, log). <b>Seluruh akun user akan TETAP AMAN dan TIDAK dihapus.</b></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="2">
            <button type="submit">Mulai Reset Sistem</button>
        </form>

    <?php elseif ($step == 2): ?>
        <h2>KONFIRMASI TAHAP 1</h2>
        <p>Apakah Anda benar-benar yakin ingin mereset sistem? Data yang terhapus TIDAK BISA dikembalikan!</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="confirm" value="ya">
            <button type="submit">Ya, Lanjutkan</button>
        </form>

    <?php elseif ($step == 3): ?>
        <h2>Langkah 3</h2>
        <p>Masukkan kode keamanan tahap 1.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="4">
            <input type="text" name="input_3" required autocomplete="off" placeholder="Ketik disini...">
            <br>
            <button type="submit">Selanjutnya</button>
        </form>

    <?php elseif ($step == 4): ?>
        <h2>Langkah 4</h2>
        <p>Masukkan kode keamanan tahap 2.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="5">
            <input type="text" name="input_4" required autocomplete="off" placeholder="Ketik disini...">
            <br>
            <button type="submit">Selanjutnya</button>
        </form>

    <?php elseif ($step == 5): ?>
        <h2>Langkah 5</h2>
        <p>Masukkan kode keamanan tahap 3.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="6">
            <input type="text" name="input_5" required autocomplete="off" placeholder="Ketik disini...">
            <br>
            <button type="submit">Selanjutnya</button>
        </form>

    <?php elseif ($step == 6): ?>
        <h2>Langkah Terakhir! (Pamungkas)</h2>
        <p>Masukkan kata sandi pamungkas untuk mengeksekusi sistem.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="step" value="7">
            <input type="password" name="input_6" required autocomplete="off" placeholder="Password Reset">
            <br>
            <button type="submit">Eksekusi Reset Sekarang!</button>
        </form>

    <?php elseif ($step == 8): ?>
        <div class="success"><?= $success ?></div>
        <br><br>
        <a href="dashboard_admin.php" style="color: #4e73df; text-decoration: none; font-weight: bold;">Kembali ke Dashboard Admin</a>
    <?php endif; ?>
</div>

</body>
</html>
