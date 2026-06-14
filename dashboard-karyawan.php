<?php
// dashboard-karyawan.php
session_start();

if (!isset($_SESSION['hak_akses']) || $_SESSION['hak_akses'] != 'Karyawan') {
    header("Location: login.php");
    exit;
}

include "config/database.php";
$id_karyawan_session = $_SESSION['id_karyawan'];

// Tarik data profil super-lengkap dari VIEW terbaru
$query_profil = "SELECT * FROM v_DetailProfilKaryawan WHERE ID_Karyawan = $id_karyawan_session";
$eksekusi_profil = mysqli_query($koneksi, $query_profil);
$profil = mysqli_fetch_assoc($eksekusi_profil);

// Ambil rincian komponen slip gaji terakhir secara unik
$query_slip = "SELECT Komponen_Gaji, Kategori, Nominal
               FROM v_LaporanPenggajian
               WHERE NIK = '" . $profil['NIK'] . "'
                 AND ID_Slip = (
                     SELECT id_slip_h
                     FROM slip_gaji_h
                     WHERE id_karyawan = " . $profil['ID_Karyawan'] . "
                     ORDER BY tahun DESC, bulan DESC
                     LIMIT 1
                 )
               ORDER BY Kategori DESC";
$eksekusi_slip = mysqli_query($koneksi, $query_slip);

// Ambil info slip header terakhir untuk menampilkan bulan & tahun periode slip secara jelas
$query_header_slip = "SELECT bulan, tahun FROM slip_gaji_h WHERE id_karyawan = " . $profil['ID_Karyawan'] . " ORDER BY tahun DESC, bulan DESC LIMIT 1";
$eksekusi_header_slip = mysqli_query($koneksi, $query_header_slip);
$header_slip = mysqli_fetch_assoc($eksekusi_header_slip);

$nama_bulan_array = array(
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 5 => "Mei", 6 => "Juni",
    7 => "Juli", 8 => "Agustus", 9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
);
$periode_slip = "Belum Ada Slip Gaji";
if ($header_slip) {
    $bulan_angka = (int)$header_slip['bulan'];
    $periode_slip = $nama_bulan_array[$bulan_angka] . " " . $header_slip['tahun'];
}
  
// Ambil data jam check-in hari ini jika sudah absen
$tgl_hari_ini = date("Y-m-d");
$cek_absen_hari_ini = mysqli_query($koneksi, "SELECT * FROM log_absensi_harian WHERE id_karyawan = $id_karyawan_session AND tanggal = '$tgl_hari_ini'");
$data_absen_hari_ini = mysqli_fetch_assoc($cek_absen_hari_ini);
$sudah_absen = mysqli_num_rows($cek_absen_hari_ini) > 0;

// Ambil riwayat pengajuan lembur milik karyawan
$query_riwayat_lembur = "SELECT * FROM pengajuan_lembur WHERE id_karyawan = $id_karyawan_session ORDER BY tanggal_lembur DESC, id_lembur DESC";
$eksekusi_riwayat_lembur = mysqli_query($koneksi, $query_riwayat_lembur);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portal Karyawan - ESS Enterprise</title>
    <style>
        body { background-color: #1e1e2e; color: #cdd6f4; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .kontainer { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; background-color: #252538; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #f5c2e7; font-size: 24px; }
        .tombol-keluar { background-color: #f38ba8; color: #11111b; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .grid-dashboard { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .kartu { background-color: #252538; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); margin-bottom: 20px; }
        .kartu h3 { margin-top: 0; color: #cba6f7; border-bottom: 1px solid #45475a; padding-bottom: 8px; }
        .info-baris { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 15px; }
        .info-label { color: #a6adc8; }
        .info-nilai { font-weight: bold; color: #f9e2af; }
        .alert-box { padding: 12px; border-radius: 4px; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background-color: #1e1e2e; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #45475a; font-size: 14px; }
        th { background-color: #313244; color: #cba6f7; }
    </style>
</head>
<body>

<div class="kontainer">
    <div class="header">
        <div>
            <h1>Portal Mandiri Karyawan (ESS)</h1>
            <small style="color: #a6adc8;">Sistem Keamanan Terkunci IP Kantor</small>
        </div>
        <a href="auth/proses_logout.php" class="tombol-keluar">Log Out Sistem</a>
    </div>

    <div class="grid-dashboard">
        <div>
            <div class="kartu">
                <h3>Profil Pribadi Anda</h3>
                <div class="info-baris"><span class="info-label">NIK:</span><span class="info-nilai"><?php echo $profil['NIK']; ?></span></div>
                <div class="info-baris"><span class="info-label">Nama:</span><span class="info-nilai" style="color:#89b4fa;"><?php echo $profil['Nama_Karyawan']; ?></span></div>
                <div class="info-baris"><span class="info-label">Jabatan:</span><span class="info-nilai"><?php echo $profil['Jabatan']; ?></span></div>
                <div class="info-baris"><span class="info-label">Tanggal Masuk:</span><span class="info-nilai"><?php echo $profil['Tanggal_Masuk']; ?></span></div>
                <div class="info-baris"><span class="info-label">Status Kerja:</span><span class="info-nilai"><?php echo $profil['Status_Aktif'] ? 'Aktif Perusahaan' : 'Non-Aktif'; ?></span></div>
            </div>

            <div class="kartu">
                <h3>Live Check-In Kehadiran</h3>
                <div style="background-color: #313244; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px; color: #a6adc8;">
                    <strong>Aturan Jadwal Kantor:</strong><br>
                    • Jam Masuk: 07:00 - 08:00 WIB<br>
                    • Terlambat: > 08:00 WIB (Berlaku Akumulasi Denda)
                </div>

                <?php if ($sudah_absen): ?>
                    <?php if ($data_absen_hari_ini['status_kehadiran'] == 'Alpa'): ?>
                        <div class="alert-box" style="background-color: #f38ba8; color: #11111b; text-align:center;">
                            ⚠ Check-In Ditutup: Anda Terhitung Alpa!<br>
                            <small style="color:#11111b; font-weight: normal;">Sistem memblokir absensi karena Tap setelah pukul 10:00 WIB (Tap: <?php echo $data_absen_hari_ini['jam_masuk']; ?>)</small>
                        </div>
                    <?php else: ?>
                        <div class="alert-box" style="background-color: #313244; color: #a6e3a1; text-align:center;">
                            ✓ Anda Sudah Check-In Hari Ini<br>
                            <small style="color:#a6adc8;">Jam Tap: <?php echo $data_absen_hari_ini['jam_masuk']; ?> WIB</small>
                        </div>
                    <?php endif; ?>
                    <button style="width: 100%; padding: 12px; background-color: #45475a; color: #a6adc8; border: none; border-radius: 4px; font-weight: bold; cursor: not-allowed;" disabled>Tombol Terkunci</button>
                <?php else: ?>
                    <form action="karyawan/proses_checkin.php" method="POST">
                        <button type="submit" style="width: 100%; padding: 12px; background-color: #a6e3a1; color: #11111b; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Tap Check-In Sekarang</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="kartu">
                <h3>Ajukan Lembur Kerja</h3>
                
                <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses_lembur'): ?>
                    <div class="alert-box" style="background-color: #313244; color: #a6e3a1; text-align:center;">
                        ✓ Pengajuan lembur berhasil dikirim!
                    </div>
                <?php endif; ?>

                <form action="karyawan/proses_ajukan_lembur.php" method="POST">
                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:13px; margin-bottom:5px; color:#a6adc8;">Durasi Lembur (Jam)</label>
                        <input type="number" name="durasi_jam" min="1" max="12" required placeholder="Misal: 3" style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; box-sizing: border-box;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:13px; margin-bottom:5px; color:#a6adc8;">Alasan Lembur / Keterangan</label>
                        <textarea name="keterangan_alasan" required placeholder="Tulis tugas yang dikerjakan saat lembur..." style="width: 100%; height: 80px; padding: 10px; border-radius: 4px; border: 1px solid #45475a; background-color: #1e1e2e; color: #cdd6f4; font-size: 14px; box-sizing: border-box; resize: none;"></textarea>
                    </div>
                    <button type="submit" style="width: 100%; padding: 12px; background-color: #b4befe; color: #11111b; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Kirim Pengajuan Lembur</button>
                </form>
            </div>
        </div>

        <div>
            <div class="kartu">
                <h3>Rekapan Kinerja & Akumulasi Keterlambatan Bulan Ini</h3>
                <div class="grid-dashboard" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div style="background-color:#1e1e2e; padding:12px; border-radius:4px; text-align:center;">
                        <span style="font-size:12px; color:#a6adc8;">Total Masuk</span><br>
                        <strong style="font-size:20px; color:#a6e3a1;"><?php echo $profil['Total_Masuk_Bulan_Ini']; ?> Hari</strong>
                    </div>
                    <div style="background-color:#1e1e2e; padding:12px; border-radius:4px; text-align:center;">
                        <span style="font-size:12px; color:#a6adc8;">Total Alpa</span><br>
                        <strong style="font-size:20px; color:#f38ba8;"><?php echo $profil['Total_Alpa_Bulan_Ini']; ?> Hari</strong>
                    </div>
                </div>

                <div style="background-color: #f38ba8; color: #11111b; padding: 15px; border-radius: 6px; font-size: 14px;">
                    <strong>Pemberitahuan Sistem Denda Keterlambatan:</strong><br>
                    Aturan: Terlambat dihitung per kelipatan 10 menit denda <strong>-Rp 10.000</strong>.<br>
                    Akumulasi Anda: Total terlambat <strong><?php echo $profil['Total_Menit_Terlambat']; ?> Menit</strong>, terhitung mengalami <strong><?php echo $profil['Frekuensi_Kelipatan_Terlambat']; ?>x Kelipatan</strong>.<br>
                    <strong style="font-size:16px;">Total Potongan: -Rp <?php echo number_format(($profil['Frekuensi_Kelipatan_Terlambat'] * $profil['Tarif_Denda_Terlambat']), 0, ',', '.'); ?></strong>
                </div>
            </div>

            <div class="kartu">
                <h3>Rincian Transparansi Slip Gaji (Periode: <?php echo $periode_slip; ?>)</h3>
                <table>
                    <thead>
                        <tr><th>Komponen Struktur Slip</th><th>Kategori Jenis</th><th>Nominal Snapshot</th></tr>
                    </thead>
                    <tbody>
                        <?php while($slip = mysqli_fetch_assoc($eksekusi_slip)): ?>
                            <tr>
                                <td><strong><?php echo $slip['Komponen_Gaji']; ?></strong></td>
                                <td><span style="color: <?php echo $slip['Kategori'] == 'Pendapatan' ? '#a6e3a1' : '#f38ba8'; ?>;"><?php echo $slip['Kategori']; ?></span></td>
                                <td class="info-nilai">Rp <?php echo number_format($slip['Nominal'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr style="background-color:#313244;">
                            <td colspan="2"><strong>TOTAL GAJI BERSIH AKHIR (MASTER HEADER / H)</strong></td>
                            <td style="color:#a6e3a1; font-weight:bold; font-size:16px;">Rp <?php echo number_format($profil['Gaji_Terakhir_Diterima'], 2, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="kartu">
                <h3>Riwayat Pengajuan Lembur Anda</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Durasi</th>
                            <th>Keterangan / Alasan</th>
                            <th>Status Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($eksekusi_riwayat_lembur) > 0): ?>
                            <?php while ($lembur = mysqli_fetch_assoc($eksekusi_riwayat_lembur)): ?>
                                <tr>
                                    <td><?php echo $lembur['tanggal_lembur']; ?></td>
                                    <td style="color:#f9e2af; font-weight:bold;"><?php echo $lembur['durasi_jam']; ?> Jam</td>
                                    <td><em>"<?php echo htmlspecialchars($lembur['keterangan']); ?>"</em></td>
                                    <td>
                                        <?php if ($lembur['status_approval'] == 'Approved'): ?>
                                            <span style="color:#a6e3a1; font-weight:bold;">✓ Disetujui (Approved)</span>
                                        <?php elseif ($lembur['status_approval'] == 'Rejected'): ?>
                                            <span style="color:#f38ba8; font-weight:bold;">✕ Ditolak (Rejected)</span>
                                        <?php else: ?>
                                            <span style="color:#f9e2af; font-style:italic;">⏳ Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #a6adc8;">Belum ada riwayat pengajuan lembur.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>