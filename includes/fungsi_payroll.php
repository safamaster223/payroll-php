<?php
// includes/fungsi_payroll.php

// Fungsi untuk mengambil laporan penggajian ringkas langsung dari VIEW
function ambil_laporan_penggajian($koneksi) {
    // PHP tidak perlu menghitung, tinggal sedot data dari VIEW database
    $query = "SELECT * FROM v_LaporanPenggajian";
    $eksekusi = mysqli_query($koneksi, $query);
    return $eksekusi;
}

// Fungsi untuk mengambil rincian komponen slip milik satu karyawan dari VIEW
function ambil_slip_karyawan_berdasarkan_nik($koneksi, $nik_karyawan) {
    $nik_aman = mysqli_real_escape_string($koneksi, $nik_karyawan);
    $query = "SELECT * FROM v_LaporanPenggajian WHERE NIK = '$nik_aman' ORDER BY Tahun DESC, Bulan DESC";
    $eksekusi = mysqli_query($koneksi, $query);
    return $eksekusi;
}
?>