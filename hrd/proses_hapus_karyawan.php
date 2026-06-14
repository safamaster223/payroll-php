<?php
// hrd/proses_hapus_karyawan.php
session_start();

// Proteksi: Pastikan hanya Admin HR yang bisa akses eksekutor ini
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

// Hubungkan ke database
include "../config/database.php";

// Menangkap ID karyawan dari parameter URL
$id_karyawan_input = $_GET['id'];
$id_karyawan_aman = (int)$id_karyawan_input;

// Coba lakukan penghapusan keras (hard delete) dari database
$query_delete = "DELETE FROM master_karyawan WHERE id_karyawan = $id_karyawan_aman";
$eksekusi_delete = mysqli_query($koneksi, $query_delete);

if ($eksekusi_delete) {
    // Jika berhasil dihapus (karena belum ada transaksi/riwayat absensi/slip gaji)
    header("Location: ../dashboard-hrd.php?status=sukses_hapus_karyawan");
    exit;
} else {
    // Jika gagal, cek apakah karena ada relasi foreign key constraint (error code 1451)
    $error_number = mysqli_errno($koneksi);
    if ($error_number == 1451) {
        // Lakukan soft-delete (menonaktifkan status karyawan) sebagai alternatif aman
        $query_soft_delete = "UPDATE master_karyawan SET status_aktif = 0 WHERE id_karyawan = $id_karyawan_aman";
        $eksekusi_soft = mysqli_query($koneksi, $query_soft_delete);

        if ($eksekusi_soft) {
            header("Location: ../dashboard-hrd.php?status=sukses_nonaktif_karyawan");
            exit;
        } else {
            die("Gagal menonaktifkan status karyawan: " . mysqli_error($koneksi));
        }
    } else {
        // Jika karena error lain, tampilkan errornya
        die("Gagal menghapus data karyawan: " . mysqli_error($koneksi));
    }
}
?>