<?php
// hrd/proses_gaji_massal.php
session_start();

// Proteksi: Pastikan hanya Admin HR yang bisa memicu perintah ini
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

// Hubungkan ke database murni
include "../config/database.php";

// Menangkap parameter periode bulan, tahun & hari kerja dari form dashboard HRD
$bulan_aman = (int)$_POST['input_bulan'];
$tahun_aman = (int)$_POST['input_tahun'];
$hari_kerja_aman = (int)$_POST['input_hari_kerja'];

// Simpan parameter periode & hari kerja terakhir ke Session agar tidak ter-reset saat page reload
$_SESSION['gaji_bulan_terakhir'] = $bulan_aman;
$_SESSION['gaji_tahun_terakhir'] = $tahun_aman;
$_SESSION['gaji_hari_kerja_terakhir'] = $hari_kerja_aman;

// TEBAS LURUS: Kirim 3 parameter ke Stored Procedure di MySQL
$query_sp = "CALL sp_GeneratePayrollMassal($bulan_aman, $tahun_aman, $hari_kerja_aman)";
$eksekusi_sp = mysqli_query($koneksi, $query_sp);

if ($eksekusi_sp) {
    // Jika dapur MySQL selesai menghitung, pelayan PHP tinggal balik ke halaman utama
    header("Location: ../dashboard-hrd.php?status=sukses_gaji");
    exit;
} else {
    die("Gagal mengeksekusi Stored Procedure Payroll: " . mysqli_error($koneksi));
}
?>