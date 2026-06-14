<?php
// auth/proses_login.php
session_start();

// Panggil file koneksi database tanpa __dir__
include "./config/database.php";
$koneksi = mysqli_connect("localhost", "root", "", "db_payroll");
// Menangkap data kiriman dari form login
$username_input = $_POST['pengguna'];
$password_input = $_POST['sandi'];

// Mencegah SQL Injection sederhana
$username_aman = mysqli_real_escape_string($koneksi, $username_input);
$password_aman = mysqli_real_escape_string($koneksi, $password_input);

// Cari user di database berdasarkan username
$query_user = "SELECT * FROM master_user WHERE username = '$username_aman'";
$eksekusi_query = mysqli_query($koneksi, $query_user);

if (mysqli_num_rows($eksekusi_query) === 1) {
    $data_user = mysqli_fetch_assoc($eksekusi_query);
    
    // Validasi password (bisa menggunakan password_verify, di sini dicocokkan langsung sesuai data seeding)
    if ($password_aman === $data_user['password_hash']) {
        
        // Daftarkan data ke dalam Session browser
        $_SESSION['id_user'] = $data_user['id_user'];
        $_SESSION['username'] = $data_user['username'];
        $_SESSION['hak_akses'] = $data_user['role'];
        $_SESSION['id_karyawan'] = $data_user['id_karyawan'];
        
        // Arahkan halaman berdasarkan Role / Hak Akses
        if ($data_user['role'] == 'Admin HR') {
            header("Location: ../dashboard-hrd.php");
            exit;
        } else {
            header("Location: ../dashboard-karyawan.php");
            exit;
        }
    }
}

// Jika gagal terproses, tendang balik ke halaman login dengan status gagal
header("Location: ../login.php?pesan=gagal");
exit;
?>