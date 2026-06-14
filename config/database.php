<?php
// config/database.php

// Atur timezone default ke WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Membuat koneksi murni ke database MySQL
$koneksi = mysqli_connect("localhost", "root", "", "db_payroll");

// Memeriksa apakah gerbang koneksi gagal terbuka
if (mysqli_connect_errno()) {
    die("Gagal menyambung ke database MySQL: " . mysqli_connect_error());
}
?>