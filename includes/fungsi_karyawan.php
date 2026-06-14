<?php
// includes/fungsi_karyawan.php

// Fungsi untuk mengambil seluruh data live profil karyawan dari VIEW v_DetailProfilKaryawan
function ambil_semua_profil_karyawan($koneksi) {
    $query = "SELECT * FROM v_DetailProfilKaryawan";
    $eksekusi = mysqli_query($koneksi, $query);
    return $eksekusi;
}

// Fungsi untuk mengambil satu profil data live karyawan berdasarkan ID Karyawan Session
function ambil_profil_karyawan_by_id($koneksi, $id_karyawan) {
    $id_aman = (int)$id_karyawan;
    $query = "SELECT * FROM v_DetailProfilKaryawan WHERE ID_Karyawan = $id_aman";
    $eksekusi = mysqli_query($koneksi, $query);
    $data = mysqli_fetch_assoc($eksekusi);
    return $data;
}