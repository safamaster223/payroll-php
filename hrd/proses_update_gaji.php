<?php
// hrd/proses_update_gaji.php
session_start();

// Proteksi halaman: Pastikan hanya Admin HR yang bisa merubah gaji master
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

// Hubungkan ke database murni
include "../config/database.php";

// Menangkap data input dari form dashboard HRD
$id_jabatan_aman = (int)$_POST['input_id_jabatan'];
$gaji_baru_aman  = (float)$_POST['input_gaji_baru'];

// Eksekusi query UPDATE standar ke database
$query_update = "UPDATE master_jabatan SET gaji_pokok = $gaji_baru_aman WHERE id_jabatan = $id_jabatan_aman";
$eksekusi_update = mysqli_query($koneksi, $query_update);

if ($eksekusi_update) {
    // Jika update sukses, TRIGGER tr_LogPerubahanGaji di MySQL otomatis langsung mencatat ke log_perubahan_gaji!
    header("Location: ../dashboard-hrd.php?status=sukses_update_gaji");
    exit;
} else {
    die("Gagal merubah data gaji pokok master: " . mysqli_error($koneksi));
}
?>