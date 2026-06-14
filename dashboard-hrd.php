<?php
// dashboard-hrd.php
session_start();

// Proteksi halaman: Jika belum login atau bukan Admin HR, tendang balik ke login.php
if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Admin HR') {
    header("Location: login.php");
    exit;
}

// Hubungkan ke database
include "config/database.php";

// 1. Tarik semua data karyawan aktif dari Super-VIEW v_DetailProfilKaryawan
$query_karyawan = "SELECT v.*, k.id_jabatan, u.username FROM v_DetailProfilKaryawan v
                   INNER JOIN master_karyawan k ON v.ID_Karyawan = k.id_karyawan
                   LEFT JOIN master_user u ON k.id_karyawan = u.id_karyawan";
$eksekusi_karyawan = mysqli_query($koneksi, $query_karyawan);

// 2. Tarik data Log Perubahan Gaji hasil rekaman TRIGGER (Ambil 5 baris terbaru)
$query_log = "SELECT * FROM log_perubahan_gaji ORDER BY waktu_perubahan DESC LIMIT 5";
$eksekusi_log = mysqli_query($koneksi, $query_log);

// 3. Tarik data pengajuan lembur yang masih pending
$query_request_lembur = "SELECT l.*, k.nama_karyawan, k.nik FROM pengajuan_lembur l
                         INNER JOIN master_karyawan k ON l.id_karyawan = k.id_karyawan
                         WHERE l.status_approval = 'Pending' ORDER BY l.id_lembur DESC";
$eksekusi_req_lembur = mysqli_query($koneksi, $query_request_lembur);

// 4. Ambil data jabatan untuk pilihan di Dropdown Form Update Gaji dan Manajemen Karyawan
$query_jabatan_list = "SELECT id_jabatan, nama_jabatan, gaji_pokok FROM master_jabatan";
$eksekusi_jabatan_list = mysqli_query($koneksi, $query_jabatan_list);
$array_jabatan = array();
while ($row_jab = mysqli_fetch_assoc($eksekusi_jabatan_list)) {
    $array_jabatan[] = $row_jab;
}

// 5. Inisialisasi default parameter periode & hari kerja dari Session
$bulan_default = 5;
if (isset($_SESSION['gaji_bulan_terakhir'])) {
    $bulan_default = (int)$_SESSION['gaji_bulan_terakhir'];
}

$tahun_default = 2026;
if (isset($_SESSION['gaji_tahun_terakhir'])) {
    $tahun_default = (int)$_SESSION['gaji_tahun_terakhir'];
}

$hari_kerja_default = 26;
if (isset($_SESSION['gaji_hari_kerja_terakhir'])) {
    $hari_kerja_default = (int)$_SESSION['gaji_hari_kerja_terakhir'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>HRD Control Center - Enterprise</title>
    <style>
        body { background-color: #1e1e2e; color: #cdd6f4; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .kontainer { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; background-color: #252538; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #f5c2e7; font-size: 24px; }
        .tombol-keluar { background-color: #f38ba8; color: #11111b; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .kartu { background-color: #252538; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .kartu h3 { margin-top: 0; color: #cba6f7; border-bottom: 1px solid #45475a; padding-bottom: 8px; }
        
        .form-gaji { display: flex; gap: 15px; align-items: center; margin-top: 15px; }
        select, button.btn-proses { padding: 10px; border-radius: 4px; border: 1px solid #45475a; font-size: 14px; }
        select { background-color: #1e1e2e; color: #cdd6f4; width: 150px; }
        button.btn-proses { background-color: #a6e3a1; color: #11111b; font-weight: bold; cursor: pointer; border: none; }
        button.btn-proses:hover { background-color: #94e2d5; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background-color: #1e1e2e; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #45475a; }
        th { background-color: #313244; color: #cba6f7; }
        .info-nilai { font-weight: bold; color: #f9e2af; }
        .notif-sukses { background-color: #a6e3a1; color: #11111b; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body>

<div class="kontainer">

    <div class="header">
        <div>
            <h1>HRD Enterprise Control Center</h1>
            <small style="color: #a6adc8;">Login Sebagai: <strong><?php echo $_SESSION['username']; ?></strong> (Admin)</small>
        </div>
        <a href="auth/proses_logout.php" class="tombol-keluar">Keluar Sistem</a>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_gaji'): ?>
        <div class="notif-sukses">✓ Mesin Engine CURSOR Berhasil Melakukan Kalkulasi Finansial Massal!</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_update_gaji'): ?>
        <div class="notif-sukses" style="background-color: #f5c2e7; color: #11111b;">✓ Gaji Pokok Jabatan Berhasil Diperbarui! Mesin TRIGGER Otomatis Mengunci Log Transparansi!</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_tambah_karyawan'): ?>
        <div class="notif-sukses" style="background-color: #a6e3a1; color: #11111b;">✓ Karyawan Baru Berhasil Ditambahkan dan Akun Login Otomatis Dibuat!</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_edit_karyawan'): ?>
        <div class="notif-sukses" style="background-color: #89b4fa; color: #11111b;">✓ Data Karyawan dan Akun Login Berhasil Diperbarui!</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_hapus_karyawan'): ?>
        <div class="notif-sukses" style="background-color: #f38ba8; color: #11111b;">✓ Data Karyawan Berhasil Dihapus Permanen!</div>
    <?php endif; ?>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_nonaktif_karyawan'): ?>
        <div class="notif-sukses" style="background-color: #f9e2af; color: #11111b;">✓ Karyawan Berhasil Dinonaktifkan (Status Non-Aktif) karena Memiliki Histori Data!</div>
    <?php endif; ?>
    
    <div class="kartu">
        <h3>Kalkulator Penggajian Massal (Engine Stored Procedure)</h3>
        <p style="color: #a6adc8; font-size: 14px; margin: 0;">Pilih periode bulan dan tahun untuk memicu proses loop sekuensial perhitungan pendapatan, denda keterlambatan (kelipatan 10 menit), alpa, dan sinkronisasi rincian slip.</p>
        
        <form action="hrd/proses_gaji_massal.php" method="POST" class="form-gaji">
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Bulan</label>
                <select name="input_bulan" required>
                    <option value="5" <?php if ($bulan_default == 5) { echo 'selected'; } ?>>Mei (05)</option>
                    <option value="6" <?php if ($bulan_default == 6) { echo 'selected'; } ?>>Juni (06)</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Tahun</label>
                <select name="input_tahun" required>
                    <option value="2026" <?php if ($tahun_default == 2026) { echo 'selected'; } ?>>2026</option>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Hari Kerja Efektif</label>
                <input type="number" name="input_hari_kerja" value="<?php echo $hari_kerja_default; ?>" min="1" max="31" required style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; width: 80px;">
            </div>
            <div style="margin-top: 18px;">
                <button type="submit" class="btn-proses">Eksekusi SP Gaji Massal</button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <h3>Daftar Pengajuan Lembur Karyawan (Butuh Persetujuan HRD)</h3>
        <p style="color: #a6adc8; font-size: 14px;">Berikut adalah daftar karyawan yang meminta konfirmasi jam lembur beserta komentar alasan lapangannya.</p>
        <table>
            <thead>
                <tr>
                    <th>Karyawan (NIK)</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Request Durasi</th>
                    <th>Alasan Komentar Karyawan</th>
                    <th>Aksi Keputusan HRD</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($eksekusi_req_lembur) > 0): ?>
                    <?php while($req = mysqli_fetch_assoc($eksekusi_req_lembur)): ?>
                        <tr>
                            <td><strong><?php echo $req['nama_karyawan']; ?></strong> (<small><?php echo $req['nik']; ?></small>)</td>
                            <td><?php echo $req['tanggal_lembur']; ?></td>
                            <td style="color: #f9e2af; font-weight:bold;"><?php echo $req['durasi_jam']; ?> Jam</td>
                            <td><em>"<?php echo $req['keterangan']; ?>"</em></td>
                            <td>
                                <a href="hrd/proses_approval_lembur.php?id=<?php echo $req['id_lembur']; ?>&status=Approved" style="background-color: #a6e3a1; color: #11111b; padding: 5px 10px; border-radius:4px; text-decoration:none; font-weight:bold; font-size:12px; margin-right:5px;">✓ ACC</a>
                                <a href="hrd/proses_approval_lembur.php?id=<?php echo $req['id_lembur']; ?>&status=Rejected" style="background-color: #f38ba8; color: #11111b; padding: 5px 10px; border-radius:4px; text-decoration:none; font-weight:bold; font-size:12px;">✕ Tolak</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #a6adc8;">Tidak ada pengajuan lembur yang pending saat ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="kartu">
        <h3>Update Nominal Gaji Master Jabatan (Pemicu Otomatis TRIGGER)</h3>
        <p style="color: #a6adc8; font-size: 14px; margin: 0;">Gunakan form ini untuk mengubah standar gaji pokok per jabatan. Aksi ini akan langsung menyalakan mesin <code>tr_LogPerubahanGaji</code> di database secara senyap.</p>
        
        <form action="hrd/proses_update_gaji.php" method="POST" class="form-gaji">
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Pilih Jabatan</label>
                <select name="input_id_jabatan" style="width: 200px;" required>
                    <?php foreach ($array_jabatan as $jab): ?>
                        <option value="<?php echo $jab['id_jabatan']; ?>">
                            <?php echo $jab['nama_jabatan']; ?> (Rp <?php echo number_format($jab['gaji_pokok'], 0, ',', '.'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Gaji Pokok Baru (Rp)</label>
                <input type="number" name="input_gaji_baru" min="1000" required placeholder="Misal: 5000000" style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px;">
            </div>
            <div style="margin-top: 18px;">
                <button type="submit" class="btn-proses" style="background-color: #f5c2e7;">Update Gaji Pokok</button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <h3>Tambah Karyawan Baru (Registrasi Akun Otomatis)</h3>
        <p style="color: #a6adc8; font-size: 14px; margin: 0;">Gunakan form ini untuk menambahkan karyawan baru ke sistem. Sistem akan secara otomatis menggenerasikan akun login karyawan tersebut.</p>

        <form action="hrd/proses_karyawan.php" method="POST" class="form-gaji" style="flex-wrap: wrap;">
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">NIK Karyawan</label>
                <input type="text" name="input_nik" required placeholder="Contoh: NIK2026051" style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; width: 150px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Nama Lengkap</label>
                <input type="text" name="input_nama" required placeholder="Nama Karyawan" style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; width: 200px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Jabatan</label>
                <select name="input_id_jabatan" style="width: 200px;" required>
                    <option value="">-- Pilih Jabatan --</option>
                    <?php foreach ($array_jabatan as $jab): ?>
                        <option value="<?php echo $jab['id_jabatan']; ?>">
                            <?php echo $jab['nama_jabatan']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Username</label>
                <input type="text" name="input_username" required placeholder="Username Login" style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; width: 150px;">
            </div>
            <div>
                <label style="display:block; font-size:12px; margin-bottom:5px;">Password</label>
                <input type="password" name="input_password" required placeholder="Password Login" style="padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; width: 150px;">
            </div>
            <div style="margin-top: 18px;">
                <button type="submit" class="btn-proses" style="background-color: #a6e3a1;">Tambah Karyawan</button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <h3>Data Live Rekap Profil Karyawan & Audit Transaksi (VIEW: v_DetailProfilKaryawan)</h3>
        <p style="color: #a6adc8; font-size: 14px;">Klik tombol <strong>"Lihat Dokumen Detail"</strong> pada masing-masing karyawan untuk memicu enkapsulasi data profil pribadi, kinerja absensi harian, dan kalkulasi keuangan secara interaktif.</p>
        
        <table>
            <thead>
                <tr>
                    <th>NIK</th>
                    <th>Nama Karyawan</th>
                    <th>Jabatan</th>
                    <th>Gaji Bersih Akhir (Header H)</th>
                    <th>Aksi Dokumen</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($eksekusi_karyawan) > 0): ?>
                    <?php while($karyawan = mysqli_fetch_assoc($eksekusi_karyawan)): ?>
                        <tr>
                            <td><code><?php echo $karyawan['NIK']; ?></code></td>
                            <td style="color: #89b4fa; font-weight: bold;"><?php echo $karyawan['Nama_Karyawan']; ?></td>
                            <td><?php echo $karyawan['Jabatan']; ?></td>
                            <td style="color: #a6e3a1; font-weight: bold;">Rp <?php echo number_format($karyawan['Gaji_Terakhir_Diterima'], 2, ',', '.'); ?></td>
                            <td>
                                <button type="button" class="btn-detail-js"
                                        style="background-color: #b4befe; color: #11111b; border:none; padding: 6px 12px; border-radius:4px; font-weight:bold; cursor:pointer;"
                                        data-nik="<?php echo $karyawan['NIK']; ?>"
                                        data-nama="<?php echo $karyawan['Nama_Karyawan']; ?>"
                                        data-jabatan="<?php echo $karyawan['Jabatan']; ?>"
                                        data-gapok="<?php echo number_format($karyawan['Gaji_Pokok'], 2, ',', '.'); ?>"
                                        data-tunjangan="<?php echo number_format($karyawan['Tunjangan'], 2, ',', '.'); ?>"
                                        data-masuk="<?php echo $karyawan['Total_Masuk_Bulan_Ini']; ?>"
                                        data-alpa="<?php echo $karyawan['Total_Alpa_Bulan_Ini']; ?>"
                                        data-menit-telat="<?php echo $karyawan['Total_Menit_Terlambat']; ?>"
                                        data-frekuensi-telat="<?php echo $karyawan['Frekuensi_Kelipatan_Terlambat']; ?>"
                                        data-potongan-denda="<?php echo number_format($karyawan['Total_Potongan_Denda'], 2, ',', '.'); ?>"
                                        data-lembur="<?php echo $karyawan['Total_Jam_Lembur']; ?>"
                                        data-tgl-masuk="<?php echo $karyawan['Tanggal_Masuk']; ?>"
                                        data-status="<?php echo $karyawan['Status_Aktif'] ? 'Aktif Bekerja' : 'Non-Aktif'; ?>"
                                        data-gaji-Social-clean="<?php echo number_format($karyawan['Gaji_Terakhir_Diterima'], 2, ',', '.'); ?>">
                                    🔍 Lihat Dokumen Detail
                                </button>
                                <button type="button" class="btn-edit-js"
                                        style="background-color: #f9e2af; color: #11111b; border:none; padding: 6px 12px; border-radius:4px; font-weight:bold; cursor:pointer; margin-left: 5px;"
                                        data-id-karyawan="<?php echo $karyawan['ID_Karyawan']; ?>"
                                        data-nik="<?php echo $karyawan['NIK']; ?>"
                                        data-nama="<?php echo $karyawan['Nama_Karyawan']; ?>"
                                        data-id-jabatan="<?php echo $karyawan['id_jabatan']; ?>"
                                        data-status-aktif="<?php echo $karyawan['Status_Aktif']; ?>"
                                        data-username="<?php echo htmlspecialchars($karyawan['username'] . ""); ?>">
                                    ✏️ Edit
                                </button>
                                <a href="hrd/proses_hapus_karyawan.php?id=<?php echo $karyawan['ID_Karyawan']; ?>"
                                   style="background-color: #f38ba8; color: #11111b; text-decoration: none; padding: 6px 12px; border-radius:4px; font-weight:bold; font-size: 13px; cursor:pointer; margin-left: 5px; display: inline-block;"
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus/menonaktifkan karyawan <?php echo htmlspecialchars($karyawan['Nama_Karyawan']); ?>?');">
                                    🗑️ Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="kartu">
        <h3>Log Audit Transparansi Perubahan Gaji Master (Hasil TRIGGER: tr_LogPerubahanGaji)</h3>
        <p style="color: #a6adc8; font-size: 14px; margin: 0;">Merekam secara otomatis setiap kali ada query UPDATE pada nominal gaji pokok di tabel master jabatan.</p>
        <table>
            <thead>
                <tr>
                    <th>Jabatan</th>
                    <th>Gaji Lama</th>
                    <th>Gaji Baru</th>
                    <th>User Pemasok</th>
                    <th>Waktu Perubahan</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($eksekusi_log) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($eksekusi_log)): ?>
                        <tr>
                            <td><?php echo $log['nama_jabatan']; ?></td>
                            <td style="color: #f38ba8;">Rp <?php echo number_format($log['gaji_pokok_lama'], 2, ',', '.'); ?></td>
                            <td style="color: #a6e3a1;">Rp <?php echo number_format($log['gaji_pokok_baru'], 2, ',', '.'); ?></td>
                            <td><code style="color: #f5c2e7;"><?php echo $log['user_pemasok']; ?></code></td>
                            <td><?php echo $log['waktu_perubahan']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #a6adc8;">Belum ada riwayat aktivitas trigger log terpantau.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="modalDetailKaryawan" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(17,17,27,0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div style="background-color: #252538; color: #cdd6f4; padding: 25px; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 15px 30px rgba(0,0,0,0.6); border: 1px solid #45475a;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #cba6f7; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #cba6f7;">Berkas Dokumen Detail Karyawan</h3>
            <span id="tombolCloseModal" style="font-size: 28px; font-weight: bold; cursor: pointer; color: #f38ba8; line-height: 20px;">&times;</span>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px;">
            <div>
                <h4 style="color:#89b4fa; margin: 0 0 8px 0; border-bottom: 1px solid #313244;">Informasi Pribadi</h4>
                <p style="margin: 6px 0;"><strong>NIK:</strong> <span id="md-nik"></span></p>
                <p style="margin: 6px 0;"><strong>Nama:</strong> <span id="md-nama" style="font-weight: bold;"></span></p>
                <p style="margin: 6px 0;"><strong>Jabatan:</strong> <span id="md-jabatan"></span></p>
                <p style="margin: 6px 0;"><strong>Tanggal Masuk:</strong> <span id="md-tgl-masuk"></span></p>
                <p style="margin: 6px 0;"><strong>Status Kerja:</strong> <span id="md-status"></span></p>
            </div>
            <div>
                <h4 style="color:#f9e2af; margin: 0 0 8px 0; border-bottom: 1px solid #313244;">Rekap Kinerja Absensi</h4>
                <p style="margin: 6px 0;"><strong>Total Masuk:</strong> <span id="md-masuk" style="color:#a6e3a1; font-weight:bold;"></span> Hari</p>
                <p style="margin: 6px 0;"><strong>Mangkir Kerja (Alpa):</strong> <span id="md-alpa" style="color:#f38ba8; font-weight:bold;"></span> Kali</p>
                <p style="margin: 6px 0;"><strong>Total Jam Lembur:</strong> <span id="md-lembur" style="color:#89b4fa; font-weight:bold;"></span> Jam</p>
                <p style="margin: 6px 0;"><strong>Keterlambatan:</strong> <span id="md-menit-telat"></span> Menit (<span id="md-frekuensi-telat" style="color:#f38ba8; font-weight:bold;"></span>x Kelipatan 10m)</p>
            </div>
        </div>

        <div style="margin-top: 20px; background-color: #1e1e2e; padding: 15px; border-radius: 6px; border: 1px solid #45475a;">
            <h4 style="color:#a6e3a1; margin: 0 0 10px 0; text-align:center; text-transform: uppercase; font-size:12px; letter-spacing:1px;">Komponen Snapshot Transaksi Slip Gaji</h4>
            <div style="display:flex; justify-content:space-between; margin-bottom: 6px; font-size:14px;"><span>Gaji Pokok Base:</span><span id="md-gapok" style="font-weight:bold; color:#f9e2af;"></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 6px; font-size:14px;"><span>Tunjangan Jabatan:</span><span id="md-tunjangan" style="font-weight:bold; color:#f9e2af;"></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 6px; color:#f38ba8; font-size:14px;"><span>Total Akumulasi Potongan Denda (D):</span><strong id="md-potongan-denda"></strong></div>
            <div style="display:flex; justify-content:space-between; margin-top: 10px; border-top: 1px solid #45475a; padding-top: 8px; color:#a6e3a1; font-size:15px;">
                <strong>TOTAL GAJI BERSIH (HEADER H):</strong>
                <strong id="md-gaji-bersih"></strong>
            </div>
        </div>
    </div>
</div>

<div id="modalEditKaryawan" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(17,17,27,0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div style="background-color: #252538; color: #cdd6f4; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 15px 30px rgba(0,0,0,0.6); border: 1px solid #45475a;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f9e2af; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #f9e2af;">Edit Data Karyawan</h3>
            <span id="tombolCloseEditModal" style="font-size: 28px; font-weight: bold; cursor: pointer; color: #f38ba8; line-height: 20px;">&times;</span>
        </div>

        <form action="hrd/proses_edit_karyawan.php" method="POST" style="display: flex; flex-direction: column; gap: 15px; font-size: 14px;">
            <input type="hidden" name="id_karyawan" id="edit-id-karyawan">

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">NIK Karyawan</label>
                <input type="text" name="input_nik" id="edit-nik" required style="width: 95%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Nama Lengkap</label>
                <input type="text" name="input_nama" id="edit-nama" required style="width: 95%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Jabatan</label>
                <select name="input_id_jabatan" id="edit-id-jabatan" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
                    <?php foreach ($array_jabatan as $jab): ?>
                        <option value="<?php echo $jab['id_jabatan']; ?>">
                            <?php echo $jab['nama_jabatan']; ?> (Rp <?php echo number_format($jab['gaji_pokok'], 0, ',', '.'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Status Kerja</label>
                <select name="input_status_aktif" id="edit-status-aktif" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
                    <option value="1">Aktif Bekerja</option>
                    <option value="0">Non-Aktif</option>
                </select>
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Username Login</label>
                <input type="text" name="input_username" id="edit-username" required style="width: 95%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Password Baru</label>
                <input type="password" name="input_password" placeholder="Kosongkan jika tidak ingin mengubah password" style="width: 95%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4;">
                <small style="color: #a6adc8; font-size:11px; display:block; margin-top:3px;">Biarkan kosong jika tetap menggunakan password lama.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button type="button" id="tombolBatalEdit" style="background-color: #f38ba8; color: #11111b; border:none; padding: 10px 20px; border-radius:4px; font-weight:bold; cursor:pointer;">Batal</button>
                <button type="submit" style="background-color: #a6e3a1; color: #11111b; border:none; padding: 10px 20px; border-radius:4px; font-weight:bold; cursor:pointer;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Ganti kode script di bagian bawah dashboard-hrd.php dengan ini wak
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("modalDetailKaryawan");
    const closeBtn = document.getElementById("tombolCloseModal");
    const detailButtons = document.querySelectorAll(".btn-detail-js");

    detailButtons.forEach(function(button) {
        button.addEventListener("click", function() {
            // 1. Ambil data mentah string dari atribut tombol, ubah ke angka murni untuk dihitung
            const gapok = parseFloat(this.getAttribute("data-gapok").replace(/\./g, '').replace(',', '.'));
            const tunjangan = parseFloat(this.getAttribute("data-tunjangan").replace(/\./g, '').replace(',', '.'));
            const jamLembur = parseFloat(this.getAttribute("data-lembur"));
            const menitTelat = parseFloat(this.getAttribute("data-menit-telat"));
            const alpa = parseFloat(this.getAttribute("data-alpa"));

            // 2. Definisikan Tarif Parameter Operasional sesuai Konfigurasi Master
            const tarifDendaTerlambat10m = 10000;

            // 3. Jalankan Rumus Matematika Sekuensial (SINKRON DENGAN SP DATABASE BARU)
            // Dapatkan jumlah hari kerja aktif dari input secara dinamis
            const inputHariKerjaDOM = document.querySelector("input[name='input_hari_kerja']");
            let hariKerjaAktif = 26;
            if (inputHariKerjaDOM) {
                hariKerjaAktif = parseInt(inputHariKerjaDOM.value);
            }

            // Hitung alpa secara dinamis
            const masuk = parseFloat(this.getAttribute("data-masuk"));
            const jumlahAlpa = Math.max(0, hariKerjaAktif - masuk);

            // Hitung Lembur Bertingkat Depnaker (1/173)
            let totalUangLembur = 0;
            if (jamLembur > 0) {
                const ratePerJam = (gapok + tunjangan) / 173.0;
                totalUangLembur = (1.5 * ratePerJam) + ((jamLembur - 1) * 2.0 * ratePerJam);
            }

            // Denda Alpa Proporsional
            const totalDendaAlpa = jumlahAlpa * (gapok / hariKerjaAktif);

            // Denda Keterlambatan kelipatan 10 menit
            const totalDendaTelat = Math.floor(menitTelat / 10) * tarifDendaTerlambat10m;

            const totalPotongan = totalDendaTelat + totalDendaAlpa;
            const totalPendapatan = gapok + tunjangan + totalUangLembur;
            const gajiBersihAkhir = totalPendapatan - totalPotongan;

            // 4. Injeksi Teks Hasil Hitungan Matang ke dalam Elemen Modal Pop-Up
            document.getElementById("md-nik").innerText = this.getAttribute("data-nik");
            document.getElementById("md-nama").innerText = this.getAttribute("data-nama");
            document.getElementById("md-jabatan").innerText = this.getAttribute("data-jabatan");
            document.getElementById("md-tgl-masuk").innerText = this.getAttribute("data-tgl-masuk");
            document.getElementById("md-status").innerText = this.getAttribute("data-status");

            document.getElementById("md-masuk").innerText = this.getAttribute("data-masuk");
            document.getElementById("md-alpa").innerText = jumlahAlpa;
            document.getElementById("md-lembur").innerText = this.getAttribute("data-lembur");
            document.getElementById("md-menit-telat").innerText = this.getAttribute("data-menit-telat");
            document.getElementById("md-frekuensi-telat").innerText = this.getAttribute("data-frekuensi-telat");

            // Format Rupiah Interaktif untuk Komponen Slip Gaji
            document.getElementById("md-gapok").innerText = "Rp " + gapok.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById("md-tunjangan").innerText = "Rp " + tunjangan.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Tampilkan komponen lemburan yang tadinya hilang secara dinamis
            document.getElementById("md-potongan-denda").innerText = "-Rp " + totalPotongan.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById("md-gaji-bersih").innerText = "Rp " + gajiBersihAkhir.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Tambahkan baris informasi info uang lemburan aktif di dalam kotak snapshot modal di mana pun
            const infoBox = document.getElementById("md-gapok").parentElement.parentElement;
            // Cek apakah teks lembur sudah ada atau belum, biar tidak duplikat saat diklik berkali-kali
            if (!document.getElementById("md-live-lembur")) {
                const barisLembur = document.createElement("div");
                barisLembur.id = "md-live-lembur";
                barisLembur.style = "display:flex; justify-content:space-between; margin-bottom: 6px; font-size:14px; color:#a6e3a1;";
                barisLembur.innerHTML = `<span>Uang Lembur Aktif (${jamLembur} Jam):</span><strong>Rp ${totalUangLembur.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
                infoBox.insertBefore(barisLembur, document.getElementById("md-potongan-denda").parentElement);
            } else {
                document.getElementById("md-live-lembur").innerHTML = `<span>Uang Lembur Aktif (${jamLembur} Jam):</span><strong>Rp ${totalUangLembur.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
            }

            // Tambahkan baris informasi potongan mangkir kerja (Alpa) di dalam kotak snapshot modal jika ada
            if (!document.getElementById("md-live-alpa")) {
                const barisAlpa = document.createElement("div");
                barisAlpa.id = "md-live-alpa";
                barisAlpa.style = "display:flex; justify-content:space-between; margin-bottom: 6px; font-size:14px; color:#f38ba8;";
                barisAlpa.innerHTML = `<span>Potongan Mangkir (${jumlahAlpa} Hari):</span><strong>-Rp ${totalDendaAlpa.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
                infoBox.insertBefore(barisAlpa, document.getElementById("md-potongan-denda").parentElement);
            } else {
                document.getElementById("md-live-alpa").innerHTML = `<span>Potongan Mangkir (${jumlahAlpa} Hari):</span><strong>-Rp ${totalDendaAlpa.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
            }

            modal.style.display = "flex";
        });
    });

    // Modal Edit Karyawan
    const editModal = document.getElementById("modalEditKaryawan");
    const closeEditBtn = document.getElementById("tombolCloseEditModal");
    const cancelEditBtn = document.getElementById("tombolBatalEdit");
    const editButtons = document.querySelectorAll(".btn-edit-js");

    editButtons.forEach(function(button) {
        button.addEventListener("click", function() {
            const idKaryawan = this.getAttribute("data-id-karyawan");
            const nik = this.getAttribute("data-nik");
            const nama = this.getAttribute("data-nama");
            const idJabatan = this.getAttribute("data-id-jabatan");
            const statusAktif = this.getAttribute("data-status-aktif");
            const username = this.getAttribute("data-username");

            document.getElementById("edit-id-karyawan").value = idKaryawan;
            document.getElementById("edit-nik").value = nik;
            document.getElementById("edit-nama").value = nama;
            document.getElementById("edit-id-jabatan").value = idJabatan;
            document.getElementById("edit-status-aktif").value = statusAktif;
            document.getElementById("edit-username").value = username;

            editModal.style.display = "flex";
        });
    });

    closeEditBtn.addEventListener("click", function() { editModal.style.display = "none"; });
    cancelEditBtn.addEventListener("click", function() { editModal.style.display = "none"; });
    window.addEventListener("click", function(e) {
        if (e.target === editModal) {
            editModal.style.display = "none";
        }
    });

    closeBtn.addEventListener("click", function() { modal.style.display = "none"; });
    window.addEventListener("click", function(e) { if (e.target === modal) { modal.style.display = "none"; } });
});
</script>

</body>
</html>