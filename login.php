<?php
// login.php
session_start();

// Jika user sudah login, langsung lempar ke dashboard yang sesuai
if (isset($_SESSION['hak_akses'])) {
    if ($_SESSION['hak_akses'] == 'Admin HR') {
        header("Location: dashboard-hrd.php");
        exit;
    } else {
        header("Location: dashboard-karyawan.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Payroll - Masuk</title>
    <style>
        body { background-color: #1e1e2e; color: #cdd6f4; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .kotak-login { background-color: #252538; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); width: 320px; }
        h2 { text-align: center; margin-bottom: 24px; color: #f5c2e7; }
        .grup-input { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 14px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #45475a; border-radius: 4px; background-color: #1e1e2e; color: #cdd6f4; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #cba6f7; border: none; border-radius: 4px; color: #11111b; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background-color: #b4befe; }
        .notif-gagal { background-color: #f38ba8; color: #11111b; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-size: 14px; }
    </style>
</head>
<body>

<div class="kotak-login">
    <h2>PAYROLL SYSTEM</h2>
    
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
        <div class="notif-gagal">Username atau Password salah!</div>
    <?php endif; ?>

    <form action="auth/proses_login.php" method="POST">
        <div class="grup-input">
            <label>Username</label>
            <input type="text" name="pengguna" required placeholder="Masukkan username...">
        </div>
        <div class="grup-input">
            <label>Password</label>
            <input type="password" name="sandi" required placeholder="Masukkan password...">
        </div>
        <button type="submit">Masuk Sistem</button>
    </form>
</div>

</body>
</html>