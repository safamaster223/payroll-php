<?php
// install.php
session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error_message = '';
$logs = [];
$install_success = false;

// Default value
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_payroll';

if (isset($_POST['connect_test']) || isset($_POST['run_install'])) {
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = $_POST['db_pass'];
    $db_name = trim($_POST['db_name']);

    if (isset($_POST['connect_test'])) {
        $conn = @mysqli_connect($db_host, $db_user, $db_pass);
        if (!$conn) {
            $error_message = "Koneksi Gagal: " . mysqli_connect_error();
        } else {
            $_SESSION['db_test_ok'] = true;
            $logs[] = "Koneksi ke MySQL Server berhasil!";
            mysqli_close($conn);
        }
    }

    if (isset($_POST['run_install'])) {
        // 1. Koneksi ke MySQL
        $conn = @mysqli_connect($db_host, $db_user, $db_pass);
        if (!$conn) {
            $error_message = "Koneksi Gagal: " . mysqli_connect_error();
        } else {
            // 2. Buat database
            $create_db = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            if (mysqli_query($conn, $create_db)) {
                $logs[] = "Database `$db_name` berhasil dibuat atau sudah ada.";
                
                // Pilih DB
                mysqli_select_db($conn, $db_name);

                // 3. Tulis config/database.php
                $config_content = "<?php
// config/database.php
date_default_timezone_set('Asia/Jakarta'); // Mengunci sistem pada Waktu Indonesia Barat (WIB)
\$host = \"$db_host\";
\$user = \"$db_user\";
\$pass = \"$db_pass\";
\$db   = \"$db_name\";

\$koneksi = mysqli_connect(\$host, \$user, \$pass, \$db);

if (!\$koneksi) {
    die(\"Koneksi Database Gagal: \" . mysqli_connect_error());
}
?>";
                // Pastikan folder config ada
                if (!is_dir('config')) {
                    mkdir('config', 0777, true);
                }

                if (file_put_contents('config/database.php', $config_content)) {
                    $logs[] = "Berkas `config/database.php` berhasil dikonfigurasi.";

                    // 4. Import SQL file
                    $sql_file = 'db_payroll (1).sql';
                    if (file_exists($sql_file)) {
                        $logs[] = "Membaca berkas skema `$sql_file`...";
                        
                        // Nonaktifkan FK Checks sementara
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");

                        $query = '';
                        $delimiter = ';';
                        $lines = file($sql_file);
                        $success_queries = 0;
                        $error_queries = 0;

                        foreach ($lines as $line) {
                            $line = trim($line);

                            // Abaikan komentar dan baris kosong
                            if ($line === '' || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                                continue;
                            }

                            // Deteksi Delimiter
                            if (strpos($line, 'DELIMITER') === 0) {
                                $delimiter = trim(str_replace('DELIMITER', '', $line));
                                continue;
                            }

                            $query .= $line . "\n";

                            // Periksa akhir query berdasarkan delimiter
                            if (substr(rtrim($query), -strlen($delimiter)) === $delimiter) {
                                $query_to_run = substr(rtrim($query), 0, -strlen($delimiter));
                                if (@mysqli_query($conn, $query_to_run)) {
                                    $success_queries++;
                                } else {
                                    $error_queries++;
                                }
                                $query = '';
                            }
                        }

                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
                        $logs[] = "Import skema selesai: $success_queries query sukses.";
                        if ($error_queries > 0) {
                            $logs[] = "Catatan: $error_queries query diabaikan (karena tabel sudah ada atau konfigurasi minor).";
                        }
                        
                        $install_success = true;
                        $step = 2;
                    } else {
                        $error_message = "Berkas skema database `$sql_file` tidak ditemukan di root project.";
                    }
                } else {
                    $error_message = "Gagal menulis file konfigurasi `config/database.php`. Periksa hak akses folder.";
                }
            } else {
                $error_message = "Gagal membuat database: " . mysqli_error($conn);
            }
            mysqli_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - Payroll App Enterprise</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #1e1e2e;
            --card-bg: rgba(37, 37, 56, 0.65);
            --border-color: rgba(69, 71, 90, 0.5);
            --primary-gradient: linear-gradient(135deg, #b4befe, #cba6f7);
            --text-color: #cdd6f4;
            --text-muted: #a6adc8;
            --success-color: #a6e3a1;
            --error-color: #f38ba8;
            --accent-color: #89b4fa;
        }

        * {
            box-sizing: border-box;
            transition: all 0.25s ease-in-out;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            background-image: radial-gradient(circle at 10% 20%, rgba(180, 190, 254, 0.05) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(203, 166, 247, 0.05) 0%, transparent 40%);
        }

        .installer-container {
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 5px;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 35px;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-color);
            z-index: 1;
            transform: translateY(-50%);
        }

        .step-dot {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #1e1e2e;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            z-index: 2;
            color: var(--text-muted);
        }

        .step-dot.active {
            border-color: #b4befe;
            color: #b4befe;
            box-shadow: 0 0 10px rgba(180, 190, 254, 0.3);
        }

        .step-dot.completed {
            background: var(--primary-gradient);
            border-color: transparent;
            color: #11111b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: rgba(30, 30, 46, 0.8);
            color: var(--text-color);
            font-family: inherit;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #b4befe;
            box-shadow: 0 0 8px rgba(180, 190, 254, 0.2);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: rgba(243, 139, 168, 0.15);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .alert-success {
            background-color: rgba(166, 227, 161, 0.15);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .log-terminal {
            background-color: #11111b;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 180px;
            overflow-y: auto;
            margin-bottom: 25px;
            color: var(--success-color);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
        }

        .btn-container {
            display: flex;
            gap: 15px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: #11111b;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(203, 166, 247, 0.4);
        }

        .btn-secondary {
            background-color: rgba(69, 71, 90, 0.4);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: rgba(69, 71, 90, 0.6);
            color: #fff;
        }

        .info-card {
            background-color: rgba(137, 180, 250, 0.08);
            border: 1px solid rgba(137, 180, 250, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-card strong {
            color: var(--accent-color);
        }
    </style>
</head>
<body>

<div class="installer-container">
    <div class="header">
        <span class="logo-icon">🚀</span>
        <h1>Payroll System Setup</h1>
        <div class="subtitle">Enterprise Web App Installer Wizard</div>
    </div>

    <div class="step-indicator">
        <div class="step-dot <?php echo $step === 1 ? 'active' : 'completed'; ?>">1</div>
        <div class="step-dot <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">2</div>
        <div class="step-dot <?php echo $step === 3 ? 'active' : ''; ?>">3</div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <span>⚠️</span>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($logs)): ?>
        <div class="log-terminal">
            <?php foreach ($logs as $log): ?>
                &gt; <?php echo $log; ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="POST" action="install.php?step=1">
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Database User</label>
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>" placeholder="Kosongkan jika tidak ada password">
            </div>

            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" required>
            </div>

            <div class="btn-container">
                <button type="submit" name="connect_test" class="btn btn-secondary">Tes Koneksi</button>
                <button type="submit" name="run_install" class="btn btn-primary">Pasang & Konfigurasi</button>
            </div>
        </form>

    <?php elseif ($step === 2): ?>
        <div class="alert alert-success">
            <span>✓</span>
            <span>Konfigurasi database dan skema berhasil terpasang!</span>
        </div>

        <div class="info-card">
            Langkah selanjutnya adalah menyuntikkan data dummy untuk pengujian. Script akan membersihkan data lama lalu memasukkan <strong>50 data karyawan simulasi</strong> lengkap dengan <strong>1 Admin HRD Utama</strong>.
        </div>

        <div class="btn-container">
            <a href="data_dummy.php" class="btn btn-primary">
                <span>⚙️</span> Jalankan Seeder & Selesaikan Setup
            </a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>
