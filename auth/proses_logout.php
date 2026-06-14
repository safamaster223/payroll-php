<?php
// auth/proses_logout.php
session_start();

// Hancurkan semua data session
session_unset();
session_destroy();

// Tendang kembali ke halaman login
header("Location: ../login.php");
exit;
?>