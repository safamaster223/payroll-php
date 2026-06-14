<?php
// hrd/proses_karyawan.php
session_start();

// Proteksi: Pastikan hanya Admin HR yang bisa akses eksekutor ini
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

// Hubungkan ke database murni (tanpa __dir__)
include "../config/database.php";

// Menangkap data inputan form tambah karyawan
$nik_input = $_POST['input_nik'];
$nama_input = $_POST['input_nama'];
$jabatan_input = $_POST['input_id_jabatan']; // Berupa ID dari master_jabatan
$username_baru = $_POST['input_username'];
$password_baru = $_POST['input_password'];

// Amankan input data dari SQL Injection
$nik_aman = mysqli_real_escape_string($koneksi, $nik_input);
$nama_aman = mysqli_real_escape_string($koneksi, $nama_input);
$id_jabatan_aman = (int)$jabatan_input;
$user_aman = mysqli_real_escape_string($koneksi, $username_baru);
$pass_aman = mysqli_real_escape_string($koneksi, $password_baru); // Simpel sesuai format seeding awal
$tanggal_sekarang = date("Y-m-d");

// ==================================================================
// AKSI 1: INSERT DATA KARYAWAN BARU
// ==================================================================
$query_karyawan = "INSERT INTO master_karyawan (nik, nama_karyawan, id_jabatan, status_aktif, tanggal_masuk) 
                   VALUES ('$nik_aman', '$nama_aman', $id_jabatan_aman, 1, '$tanggal_sekarang')";
$eksekusi_karyawan = mysqli_query($koneksi, $query_karyawan);

if ($eksekusi_karyawan) {
    // Ambil ID Karyawan yang baru saja terbuat
    $id_karyawan_baru = mysqli_insert_id($koneksi);

    // ==================================================================
    // AKSI 2: OTOMATIS GENERATE AKUN USER LOGIN BARU
    // ==================================================================
    $query_user = "INSERT INTO master_user (username, password_hash, role, id_karyawan) 
                   VALUES ('$user_aman', '$pass_aman', 'Karyawan', $id_karyawan_baru)";
    $eksekusi_user = mysqli_query($koneksi, $query_user);

    if ($eksekusi_user) {
        // Jika sukses semua, kembalikan ke dashboard HRD dengan status sukses
        header("Location: ../dashboard-hrd.php?status=sukses_tambah_karyawan");
        exit;
    } else {
        die("Gagal membuat akun user login otomatis: " . mysqli_error($koneksi));
    }
} else {
    die("Gagal menambahkan data karyawan baru: " . mysqli_error($koneksi));
}
?>