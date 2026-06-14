<?php
// hrd/proses_approval_lembur.php
session_start();

if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

$id_lembur = (int)$_GET['id'];
$status_keputusan = $_GET['status']; // Berisi 'Approved' atau 'Rejected'

if ($status_keputusan == 'Approved' || $status_keputusan == 'Rejected') {
    $query = "UPDATE pengajuan_lembur SET status_approval = '$status_keputusan' WHERE id_lembur = $id_lembur";
    
    if (mysqli_query($koneksi, $query)) {
        header("Location: ../dashboard-hrd.php?status=sukses_approval");
        exit;
    } else {
        die("Gagal memproses keputusan approval: " . mysqli_error($koneksi));
    }
}
?>