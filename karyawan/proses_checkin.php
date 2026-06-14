<?php
// Panggil koneksi database murni dengan jalur mundur yang dikunci
include dirname(__FILE__) . "/../config/database.php";
// karyawan/proses_checkin.php
session_start();

// Proteksi halaman: Pastikan hanya Karyawan sah yang bisa absen
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Karyawan') {
    header("Location: ./login.php");
    exit;
}

// Panggil koneksi database murni (tanpa __dir__)
$id_karyawan = (int)$_SESSION['id_karyawan'];
$tanggal_sekarang = date("Y-m-d");
$jam_sekarang = date("H:i:s");

// ==================================================================
// 1. ANTI-FRAUD: LOCK IP ADDRESS WI-FI KANTOR
// ==================================================================
// Mendapatkan IP address pengakses saat ini
$ip_pengakses = $_SERVER['REMOTE_ADDR'];

// Tentukan IP Wi-Fi Kantor yang diizinkan (Contoh: '::1' atau '127.0.0.1' untuk lokal)
$ip_wifi_kantor = "::1"; 

if ($ip_pengakses !== $ip_wifi_kantor) {
    die("Gagal Absen: Anda tidak terhubung ke Wi-Fi resmi kantor! (IP Anda: $ip_pengakses)");
}

// ==================================================================
// 2. ANTI-FRAUD: EMBARGO ENKAPSULASI HARIAN (LOCK DOUBLE CLICK)
// ==================================================================
// Cek apakah karyawan ini sudah melakukan tap absen hari ini
$query_cek_absen = "SELECT * FROM log_absensi_harian WHERE id_karyawan = $id_karyawan AND tanggal = '$tanggal_sekarang'";
$eksekusi_cek = mysqli_query($koneksi, $query_cek_absen);

if (mysqli_num_rows($eksekusi_cek) > 0) {
    // Jika hari ini sudah ada baris absensinya, gembok aksi!
    header("Location: ../dashboard-karyawan.php?status=sudah_absen");
    exit;
}

// ==================================================================
// 3. EKSEKUSI DATA: INSERT TO LOG ABSENSI HARIAN
// ==================================================================
// Tentukan status kehadiran berdasarkan batas toleransi maksimal jam 10:00:00
$status_kehadiran = 'Hadir';
if ($jam_sekarang > '10:00:00') {
    $status_kehadiran = 'Alpa';
}

$query_insert_absen = "INSERT INTO log_absensi_harian (id_karyawan, tanggal, jam_masuk, status_kehadiran, ip_address, waktu_server_masuk)
                       VALUES ($id_karyawan, '$tanggal_sekarang', '$jam_sekarang', '$status_kehadiran', '$ip_pengakses', NOW())";

$eksekusi_insert = mysqli_query($koneksi, $query_insert_absen);

if ($eksekusi_insert) {
    if ($status_kehadiran === 'Alpa') {
        header("Location: ../dashboard-karyawan.php?status=alpa_embargo");
    } else {
        header("Location: ../dashboard-karyawan.php?status=sukses_absen");
    }
    exit;
} else {
    die("Gagal mencatat absensi ke mesin database: " . mysqli_error($koneksi));
}
?>