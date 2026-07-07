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
// CEK ADMIN
// =======================

if($_SESSION['role_id'] != 1){

    header("Location:index.php");
    exit;

}

// =======================
// CSRF TOKEN
// =======================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(uniqid(mt_rand(), true));
}
$csrf_token = $_SESSION['csrf_token'];

// =======// FLASH MESSAGE
// =======================
$success_message = "";
if(isset($_SESSION['success'])){
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

$error_message = "";
if(isset($_SESSION['error'])){
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// =======================
// SEARCH
// =======================

$search = '';
$search_param = '%';

if(isset($_GET['search'])){
    $search = trim($_GET['search']);
    $search_param = '%' . $search . '%';
}

// =======================
// DATA USER
// =======================

$stmt = mysqli_prepare($conn, "
    SELECT users.*, roles.role_name 
    FROM users 
    LEFT JOIN roles ON users.role_id = roles.id 
    WHERE users.full_name LIKE ? OR users.username LIKE ? OR users.email LIKE ? 
    ORDER BY users.id DESC
");
mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search_param);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>
<head>

<title>Kelola User</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{

    font-family:Arial;
    background:#f4f6f9;
    margin:0;

}

.wrapper{ display:flex; min-height:100vh; }
.sidebar{ width:250px; height:100vh; background:#2c3e50; position:sticky; top:0; align-self:flex-start; overflow-y:auto; flex-shrink:0; }
.sidebar .logo{ color:white; text-align:center; padding:30px; font-size:24px; font-weight:bold; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar .menu a{ display:block; color:white; text-decoration:none; padding:18px 25px; transition:0.3s; font-size:16px; }
.sidebar .menu a:hover{ background:#34495e; }
.main-content{ flex:1; min-width:0; padding:30px; }

h2{

    color:#2c3e50;

}

.top{

    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:20px;

}

.tambah{

    background:#27ae60;
    color:white;

    padding:12px 20px;

    text-decoration:none;

    border-radius:10px;

    font-weight:bold;

    transition:0.3s;

}

.tambah:hover{

    background:#219150;

}

.search{

    margin-bottom:20px;

}

.search input{

    width:320px;

    padding:12px;

    border-radius:8px;

    border:1px solid #ccc;

    outline:none;

}

.search button{

    padding:12px 18px;

    border:none;

    background:#3498db;

    color:white;

    border-radius:8px;

    cursor:pointer;

}

table{

    width:100%;

    border-collapse:collapse;

    background:white;

    box-shadow:
    0px 0px 10px
    rgba(0,0,0,0.08);

    border-radius:12px;

    overflow:hidden;

}

table th{

    background:#2c3e50;

    color:white;

    padding:18px;

    font-size:15px;

}

table td{

    padding:18px;

    border-bottom:1px solid #eee;

    vertical-align:middle;

}

tr:hover{

    background:#fafafa;

}

/* =======================
ROLE
======================= */

.role{

    padding:6px 14px;

    border-radius:20px;

    color:white;

    font-size:12px;

    font-weight:bold;

    text-transform:uppercase;

}

.admin{

    background:#3498db;

}

.guru{

    background:#27ae60;

}

.visitor{

    background:#f39c12;

}

.external{

    background:#8e44ad;

}

/* =======================
AKSI BUTTON
======================= */

.aksi{

    display:flex;

    gap:10px;

    align-items:center;

}

.btn{

    display:inline-block;

    padding:10px 16px;

    border-radius:8px;

    text-decoration:none;

    color:white;

    font-size:13px;

    font-weight:bold;

    transition:0.3s;

    min-width:75px;

    text-align:center;

}

.btn-edit{

    background:#f39c12;

}

.btn-edit:hover{

    background:#d68910;

}

.btn-hapus{

    background:#e74c3c;

}

.btn-hapus:hover{

    background:#c0392b;

}

/* =======================
RESPONSIVE
======================= */

        /* ======================
           MOBILE NAVIGATION (HAMBURGER)
        ====================== */
        .mobile-nav {
            display: none;
            background: #2c3e50;
            padding: 15px 25px;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: white;
        }
        .hamburger-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

@media(max-width:992px){

    .wrapper{ flex-direction:column; }
    .mobile-nav { display: flex; }
    .sidebar{ width:100%; height:auto; position:static; display: none; }
    .sidebar.active { display: block; }
    .sidebar .logo { display: none; }
    .main-content{ padding:15px; }

    table{

        font-size:13px;

    }

    table{
        font-size:13px;
    }

    table th,
    table td{
        padding:10px;
    }

    .aksi{
        flex-direction: row;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .btn{
        width: auto;
        flex: 1 1 100%;
        box-sizing: border-box;
        text-align: center;
        padding: 8px;
    }

    .search { display:flex; flex-direction:column; gap:10px; }
    .search input { width: 100%; box-sizing: border-box; }
    .top { flex-direction:column; align-items:flex-start; gap:15px; }

}

/* =======================
POPUP MODAL SUCCESS
======================= */
.popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: 0.3s;
}
.popup-overlay.show {
    opacity: 1;
    visibility: visible;
}
.popup-box {
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 350px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    transform: translateY(-20px);
    transition: 0.3s;
}
.popup-overlay.show .popup-box {
    transform: translateY(0);
}
.popup-icon {
    width: 60px;
    height: 60px;
    background: #27ae60;
    color: white;
    font-size: 30px;
    line-height: 60px;
    border-radius: 50%;
    margin: 0 auto 15px;
}
.popup-message {
    font-size: 18px;
    color: #2c3e50;
    margin-bottom: 20px;
    font-weight: bold;
}
.popup-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}
.popup-btn:hover {
    background: #2980b9;
}

</style>

</head>

<body>

<!-- POPUP SUCCESS -->
<?php if(!empty($success_message)){ ?>
<div class="popup-overlay" id="successPopup">
    <div class="popup-box">
        <div class="popup-icon">✓</div>
        <div class="popup-message"><?= htmlspecialchars($success_message); ?></div>
        <button class="popup-btn" onclick="closePopup()">Tutup</button>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('successPopup').classList.add('show');
    });
    function closePopup() {
        document.getElementById('successPopup').classList.remove('show');
    }
</script>
<?php } ?>

<!-- POPUP ERROR -->
<?php if(!empty($error_message)){ ?>
<div class="popup-overlay" id="errorPopup">
    <div class="popup-box">
        <div class="popup-icon" style="background:#e74c3c;">✖</div>
        <div class="popup-message"><?= htmlspecialchars($error_message); ?></div>
        <button class="popup-btn" style="background:#e74c3c;" onclick="closeErrorPopup()">Tutup</button>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('errorPopup').classList.add('show');
    });
    function closeErrorPopup() {
        document.getElementById('errorPopup').classList.remove('show');
    }
</script>
<?php } ?>

<!-- POPUP DELETE -->
<div class="popup-overlay" id="deletePopup">
    <div class="popup-box">
        <div class="popup-icon" style="background: #e74c3c; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:40px;">!</div>
        <div class="popup-message">Yakin ingin menghapus user ini?</div>
        <div style="display:flex; justify-content:center; gap:10px;">
            <button class="popup-btn" style="background:#bdc3c7; color:#333;" onclick="closeDeletePopup()">Batal</button>
            <a id="confirmDeleteBtn" href="#" class="popup-btn" style="background:#e74c3c; text-decoration:none; display:flex; align-items:center;">Hapus</a>
        </div>
    </div>
</div>
<script>
    function showDeletePopup(event, url) {
        event.preventDefault();
        document.getElementById('confirmDeleteBtn').href = url;
        document.getElementById('deletePopup').classList.add('show');
    }
    function closeDeletePopup() {
        document.getElementById('deletePopup').classList.remove('show');
    }
</script>

<div class="wrapper">
    <!-- MOBILE NAVIGATION (HAMBURGER) -->
    <div class="mobile-nav">
        <strong>MGMP Platform Admin</strong>
        <button class="hamburger-btn" id="hamburger-toggle">☰</button>
    </div>

    <div class="sidebar" id="sidebar-menu">
        <div class="logo">
            ADMIN PANEL
        </div>
        <div class="menu">
            <a href="dashboard_admin.php">Dashboard</a>
            <a href="monitoring_guru.php">Monitoring Guru</a>
            <a href="data_materi.php">Data Materi</a>
            <a href="upload_materi.php">Upload Materi</a>
            <a href="review_materials.php">Review Contributor</a>
            <a href="kelola_request.php">Request Materi</a>
            <a href="analytics.php">Analytics</a>
            <a href="kelola_informasi.php">Kelola Informasi Umum</a>
            <a href="kelola_user.php">Kelola Akun</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

<div class="main-content">
<div class="top">

    <h2>

        Kelola Akun User

    </h2>

    <div>

        <a
            href="tambah_user.php"
            class="tambah"
        >

            + Tambah User

        </a>

    </div>

</div>

<form method="GET">

    <div class="search">

        <input
            type="text"
            name="search"
            placeholder="Cari user..."
            value="<?= htmlspecialchars($search); ?>"
        >

        <button type="submit">

            Cari

        </button>

    </div>

</form>

<div style="overflow-x:auto;">
<table>

<tr>

    <th>No</th>
    <th>Username</th>
    <th>Nama</th>
    <th>Email</th>
    <th>Sekolah</th>
    <th>Role</th>
    <th width="180">
        Aksi
    </th>

</tr>

<?php

$no = 1;

while($row = mysqli_fetch_assoc($query)){

?>

<tr>

    <td>

        <?= $no++; ?>

    </td>

    <td>

        <?= htmlspecialchars(isset($row['username']) ? $row['username'] : '-'); ?>

    </td>

    <td>

        <?= htmlspecialchars($row['full_name']); ?>

    </td>

    <td>

        <?= htmlspecialchars($row['email']); ?>

    </td>

    <td>

        <?= htmlspecialchars($row['school_name']); ?>

    </td>

    <td>

        <?php

        $class = 'guru';

        if($row['role_id'] == 1){

            $class = 'admin';

        }elseif($row['role_id'] == 3){

            $class = 'visitor';

        }elseif($row['role_id'] == 4){

            $class = 'external';

        }

        ?>

        <span class="role <?= $class; ?>">

            <?= htmlspecialchars($row['role_name']); ?>

        </span>

    </td>

    <td>

        <div class="aksi">

            <a
                class="btn btn-edit"
                href="edit_user.php?id=<?= $row['id']; ?>"
            >

                Edit

            </a>

            <a
                class="btn btn-hapus"
                href="#"
                onclick="showDeletePopup(event, 'hapus_user.php?id=<?= $row['id']; ?>&csrf_token=<?= $csrf_token; ?>')"
            >

                Hapus

            </a>

        </div>

    </td>

</tr>

<?php } ?>

</table>
</div>

</div>
</div>
<script>
// Mobile Hamburger Toggle
const hamburger = document.getElementById('hamburger-toggle');
const sidebar = document.getElementById('sidebar-menu');
if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}
</script>
</body>
</html>
