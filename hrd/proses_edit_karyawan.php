<?php
// hrd/proses_edit_karyawan.php
session_start();

// Proteksi: Pastikan hanya Admin HR yang bisa akses eksekutor ini
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: ../login.php");
    exit;
}

// Hubungkan ke database
include "../config/database.php";

// Menangkap data inputan form edit karyawan
$id_karyawan_input = $_POST['id_karyawan'];
$nik_input = $_POST['input_nik'];
$nama_input = $_POST['input_nama'];
$jabatan_input = $_POST['input_id_jabatan'];
$status_aktif_input = $_POST['input_status_aktif'];
$username_input = $_POST['input_username'];
$password_input = $_POST['input_password'];

// Amankan input data dari SQL Injection
$id_karyawan_aman = (int)$id_karyawan_input;
$nik_aman = mysqli_real_escape_string($koneksi, $nik_input);
$nama_aman = mysqli_real_escape_string($koneksi, $nama_input);
$id_jabatan_aman = (int)$jabatan_input;
$status_aktif_aman = (int)$status_aktif_input;
$user_aman = mysqli_real_escape_string($koneksi, $username_input);

// 1. Update master_karyawan
$query_karyawan = "UPDATE master_karyawan
                   SET nik = '$nik_aman',
                       nama_karyawan = '$nama_aman',
                       id_jabatan = $id_jabatan_aman,
                       status_aktif = $status_aktif_aman
                   WHERE id_karyawan = $id_karyawan_aman";

$eksekusi_karyawan = mysqli_query($koneksi, $query_karyawan);

if ($eksekusi_karyawan) {
    // 2. Cek apakah user login sudah ada untuk karyawan ini
    $query_cek_user = "SELECT id_user FROM master_user WHERE id_karyawan = $id_karyawan_aman";
    $eksekusi_cek = mysqli_query($koneksi, $query_cek_user);

    if (mysqli_num_rows($eksekusi_cek) > 0) {
        // Jika user sudah ada, lakukan UPDATE
        if ($password_input != "") {
            // Jika password diisi, update password juga
            $pass_aman = mysqli_real_escape_string($koneksi, $password_input);
            $query_user = "UPDATE master_user
                           SET username = '$user_aman',
                               password_hash = '$pass_aman'
                           WHERE id_karyawan = $id_karyawan_aman";
        } else {
            // Jika password kosong, jangan ubah password
            $query_user = "UPDATE master_user
                           SET username = '$user_aman'
                           WHERE id_karyawan = $id_karyawan_aman";
        }
        $eksekusi_user = mysqli_query($koneksi, $query_user);
    } else {
        // Jika belum ada akun user, buatkan baru (misal terhapus sebelumnya)
        $pass_aman = "karyawan_123"; // password default
        if ($password_input != "") {
            $pass_aman = mysqli_real_escape_string($koneksi, $password_input);
        }
        $query_user = "INSERT INTO master_user (username, password_hash, role, id_karyawan)
                       VALUES ('$user_aman', '$pass_aman', 'Karyawan', $id_karyawan_aman)";
        $eksekusi_user = mysqli_query($koneksi, $query_user);
    }

    if ($eksekusi_user) {
        header("Location: ../dashboard-hrd.php?status=sukses_edit_karyawan");
        exit;
    } else {
        die("Gagal memperbarui akun user login: " . mysqli_error($koneksi));
    }
} else {
    die("Gagal memperbarui data karyawan: " . mysqli_error($koneksi));
}
?>