<?php
// karyawan/proses_ajukan_lembur.php
session_start();

if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Karyawan') {
    header("Location: ../login.php");
    exit;
}

include "../config/database.php";

$id_karyawan = (int)$_SESSION['id_karyawan'];
$durasi = (int)$_POST['durasi_jam'];
$alasan = mysqli_real_escape_string($koneksi, $_POST['keterangan_alasan']);
$tanggal_sekarang = date("Y-m-d");

$query = "INSERT INTO pengajuan_lembur (id_karyawan, tanggal_lembur, durasi_jam, keterangan, status_approval) 
          VALUES ($id_karyawan, '$tanggal_sekarang', $durasi, '$alasan', 'Pending')";

if (mysqli_query($koneksi, $query)) {
    header("Location: ../dashboard-karyawan.php?status=sukses_lembur");
    exit;
} else {
    die("Gagal menyimpan pengajuan lembur: " . mysqli_error($koneksi));
}
?>