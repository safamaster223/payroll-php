# Dokumen Arsitektur & Analisis Sistem Penggajian (Payroll App Enterprise)

Dokumen ini menjelaskan struktur teknis, arsitektur database, dan alur integrasi logika sistem penggajian yang digunakan dalam aplikasi Payroll App. Sistem ini menerapkan arsitektur *Database-Centric*, di mana kalkulasi finansial berat dan pencatatan riwayat audit didelegasikan langsung ke mesin database MySQL menggunakan 4 komponen utama: **Stored Procedure, Cursor, Trigger, dan View**.

---

## 1. Peta Lokasi Komponen Database (File: `db_payroll (1).sql`)

Seluruh logika database dideklarasikan di dalam file SQL Dump `db_payroll (1).sql`. Berikut adalah rincian baris kodenya:

### A. Stored Procedure (Engine Perhitungan Gaji Massal)
*   **Nama Objek:** `sp_GeneratePayrollMassal`
*   **Lokasi Baris:** `Baris 28 s/d 120`
*   **Deskripsi:** Engine utama yang dipanggil secara massal lewat kontrol dashboard HRD untuk menghitung rekap absensi, denda keterlambatan (kelipatan 10 menit), denda alpa, jam lembur, total pendapatan kotor, total denda, hingga gaji bersih akhir.

### B. Cursor (Pemroses Loop Data Karyawan)
*   **Lokasi Baris:** Dideklarasikan di dalam Stored Procedure `sp_GeneratePayrollMassal` pada `Baris 55 s/d 62` (`DECLARE cursor_karyawan CURSOR FOR ...`).
*   **Proses Loop:** Dibuka pada `Baris 72` (`OPEN cursor_karyawan;`), diloop pada `Baris 74 s/d 117` (`proses_loop: LOOP ... FETCH ... END LOOP proses_loop;`), dan ditutup pada `Baris 119` (`CLOSE cursor_karyawan;`).
*   **Deskripsi:** Digunakan untuk melakukan perulangan (iterasi) satu per satu pada setiap data karyawan aktif untuk dihitung gajinya secara sekuensial dan disimpan ke dalam tabel `slip_gaji_h` dan `slip_gaji_d`.

### C. Trigger (Audit Log Gaji Otomatis)
*   **Nama Objek:** `tr_LogPerubahanGaji`
*   **Lokasi Baris:** `Baris 212 s/d 231`
*   **Tabel Terkait:** `master_jabatan` (`AFTER UPDATE ON master_jabatan`)
*   **Deskripsi:** Secara otomatis menyala (fire) setiap kali HRD memperbarui nominal gaji pokok jabatan di tabel master. Trigger ini mencatat nilai lama, nilai baru, waktu perubahan, dan user database yang melakukan perubahan (`USER()`) ke dalam tabel `log_perubahan_gaji`.

### D. Views (Enkapsulasi Data untuk Tampilan UI)
1.  **View Profil Karyawan & Ringkasan Gaji (`v_detailprofilkaryawan`)**
    *   **Lokasi Baris:** `Baris 691` (definisi awal di SQL)
    *   **Deskripsi:** Menggabungkan profil karyawan, rekap absensi terakhir, serta total potongan denda dan gaji bersih terakhir dari slip gaji ter-generate. Digunakan sebagai sumber utama tabel dashboard HRD dan portal karyawan.
2.  **View Transparansi Detail Slip Gaji (`v_laporanpenggajian`)**
    *   **Lokasi Baris:** `Baris 700`
    *   **Deskripsi:** Menghubungkan header slip gaji (`slip_gaji_h`), detail rincian slip (`slip_gaji_d`), dan master data karyawan untuk diumpankan ke tabel slip gaji interaktif.

---

## 2. Analisis Kasus Kasus Kegagalan Sinkronisasi (Masalah Awal)

### Sebab Masalah
Pada awalnya, database view `v_detailprofilkaryawan` ditulis dengan subquery absensi yang terpisah secara buta (`ORDER BY tahun DESC, bulan DESC LIMIT 1`).
*   Ketika slip gaji sudah digenerate untuk **Bulan Juni 2026** (Periode Terbaru), kolom `Gaji_Terakhir_Diterima` mengambil nominal slip Juni (Rp 1.020.000,00).
*   Namun karena rekap absensi bulan Juni belum ada di database, subquery `Total_Jam_Lembur` mundur mencari data absensi teranyar yang tersedia, yaitu **Bulan Mei 2026** (28 Jam Lembur).
*   Data campuran yang tidak sinkron ini (Gaji Juni + Absensi Mei) dikirimkan PHP ke atribut `data-*` tombol detail.

### Akibat Masalah
*   **Di Tampilan Awal PHP (Tabel HRD):** Menampilkan gaji bersih asli bulan Juni yaitu **Rp 1.020.000,00** (hanya Gapok + Tunjangan, karena lembur Juni masih 0).
*   **Di Modal Detail (Perhitungan Ulang JS):** Javascript menghitung ulang menggunakan data campuran tersebut: `Gapok (Juni: 20rb) + Tunjangan (Juni: 1jt) + Uang Lembur (Mei: 28 jam x 50rb = 1.4jt) - Denda Telat (Mei: 30rb) = Rp 2.390.000,00`.
*   Aplikasi mengalami inkonsistensi data yang parah di mana total gaji di modal detail berbeda jauh dengan tabel utama.

### Solusi yang Diterapkan
Mengubah View `v_detailprofilkaryawan` menggunakan `LEFT JOIN` terarah. Data rekap absensi dikunci secara wajib pada bulan dan tahun yang sama dengan slip gaji terakhir yang ter-generate (`s.bulan = r.bulan AND s.tahun = r.tahun`). Jika rekap absensi periode tersebut belum ada, maka data lembur dikembalikan sebagai `0` (sinkron dengan slip gaji terbaru).

---

## 3. Analisis Mendalam 4 Unsur Database (View, SP, Cursor, Trigger)

Berikut adalah analisis logis berdasarkan skenario bisnis aplikasi payroll:

### A. VIEW
*   **Cara Dibuat:** Dideklarasikan menggunakan sintaks `CREATE VIEW AS SELECT...` untuk membungkus query relasional yang kompleks (`JOIN`, `GROUP BY`, `COALESCE`).
*   **Cara Bekerja:** Bertindak sebagai tabel virtual. MySQL tidak menyimpan data fisik di dalam view, melainkan mengeksekusi query di balik layar setiap kali view dipanggil (`SELECT * FROM v_detailprofilkaryawan`).
*   **Cara Digunakan:** Diintegrasikan ke PHP (misal di `dashboard-hrd.php` baris 15 dan `dashboard-karyawan.php` baris 14 & 19) sehingga developer PHP tidak perlu menulis query `JOIN` yang panjang di dalam kode PHP.
*   **Keunggulan:** 
    *   Menyederhanakan kode PHP (Clean Code).
    *   Sentralisasi logika bisnis: Jika struktur tabel database berubah, developer cukup memperbarui View di database tanpa harus mengubah puluhan file PHP yang membaca data tersebut.
*   **Kelemahan:** Jika query di dalam view mengandung banyak subquery tidak terefisien (seperti versi awal), performa query akan sangat lambat saat data karyawan bertambah banyak karena MySQL harus menjalankan subquery per baris data (*correlated subquery*).
*   **Analisis Jika-Sebab-Akibat:**
    *   *Jika* sistem **tidak menggunakan View**, 
    *   *Sebab* developer terpaksa menulis query raw SQL `JOIN` yang sangat rumit dan panjang di dalam file PHP (`dashboard-hrd.php`, `dashboard-karyawan.php`, `fungsi_karyawan.php`),
    *   *Akibatnya* kode program menjadi kotor (*spaghetti code*), sulit dirawat, dan jika terjadi perubahan rumus slip gaji, developer harus mencari dan mengubah query di seluruh file PHP satu per satu, meningkatkan risiko bug dan inkonsistensi.

---

### B. STORED PROCEDURE (SP)
*   **Cara Dibuat:** Ditulis langsung di MySQL menggunakan `CREATE PROCEDURE` dengan parameter input bulan dan tahun (`IN p_Bulan INT, IN p_Tahun INT`).
*   **Cara Bekerja:** Kumpulan perintah SQL yang dikompilasi dan disimpan di server MySQL. Saat dipanggil, database mengeksekusi seluruh logika keuangan secara internal.
*   **Cara Digunakan:** Dipanggil dari PHP di `hrd/proses_gaji_massal.php` menggunakan query `CALL sp_GeneratePayrollMassal($bulan, $tahun)`.
*   **Keunggulan:**
    *   **Performa Sangat Tinggi:** Komputasi berjalan langsung di memori server database tanpa bolak-balik mengirim data mentah ke server PHP (*network round-trip reduction*).
    *   **Keamanan Ekstra:** Proteksi manipulasi nilai gaji karena logika kalkulasi terbungkus rapi di dalam database server, aman dari eksploitasi injeksi kode PHP.
*   **Kelemahan:** Logika bisnis yang rumit sulit didebug di dalam MySQL dibanding di bahasa pemrograman seperti PHP. Selain itu, Stored Procedure membebani CPU server database.
*   **Analisis Jika-Sebab-Akibat:**
    *   *Jika* sistem **tidak menggunakan Stored Procedure**,
    *   *Sebab* proses perhitungan gaji massal dipindahkan sepenuhnya ke loop bahasa PHP (PHP menarik ribuan baris log absensi satu per satu, menghitung denda dan lembur di PHP, lalu melakukan query `INSERT`/`UPDATE` ratusan kali ke database),
    *   *Akibatnya* server akan mengalami *latency* tinggi, *Memory Limit Exhausted* (PHP Crash), atau *Request Timeout* karena server PHP dan MySQL kelelahan melakukan transfer data bolak-balik untuk menghitung penggajian massal.

---

### C. CURSOR
*   **Cara Dibuat:** Dideklarasikan di dalam Stored Procedure menggunakan `DECLARE cursor_name CURSOR FOR SELECT...`.
*   **Cara Bekerja:** Pointer yang digunakan untuk menunjuk baris data hasil query satu per satu. Menggunakan loop sekuensial (`LOOP ... FETCH ... END LOOP`) dengan handler `NOT FOUND` untuk menghentikan loop ketika baris data habis.
*   **Cara Digunakan:** Di dalam `sp_GeneratePayrollMassal` untuk meloop seluruh karyawan yang berstatus aktif guna menghitung slip gajinya.
*   **Keunggulan:** Memungkinkan pemrosesan logika baris-per-baris (*Row-by-Row Processing*) yang sangat spesifik yang tidak bisa diselesaikan hanya dengan query set-based `UPDATE` atau `INSERT` biasa (misalnya karena harus menghasilkan rincian detail slip gaji dinamis ke tabel lain `slip_gaji_d`).
*   **Kelemahan:** Cursor bersifat lambat dibandingkan query operasi set database (SQL murni tanpa loop) karena dia memproses baris demi baris secara prosedural.
*   **Analisis Jika-Sebab-Akibat:**
    *   *Jika* database **tidak menggunakan Cursor**,
    *   *Sebab* database MySQL murni bersifat set-based (bekerja pada kumpulan data sekaligus) dan tidak mendukung perulangan prosedural baris demi baris tanpa cursor,
    *   *Akibatnya* database tidak akan mampu memproses logika kondisional dinamis per karyawan (seperti memasukkan baris "Uang Lembur Aktif" ke `slip_gaji_d` hanya jika karyawan tersebut punya jam lembur) dalam satu kali proses eksekusi di sisi database.

---

### D. TRIGGER
*   **Cara Dibuat:** Dibuat menggunakan perintah `CREATE TRIGGER` yang dikaitkan pada aksi tertentu (`AFTER UPDATE`) pada tabel master (`master_jabatan`).
*   **Cara Bekerja:** Mesin trigger berjalan secara otomatis di latar belakang database ketika ada operasi `UPDATE` pada kolom `gaji_pokok` di tabel `master_jabatan`. Trigger membandingkan data lama (`OLD.gaji_pokok`) dengan data baru (`NEW.gaji_pokok`). Jika berbeda, dia memasukkan log ke `log_perubahan_gaji`.
*   **Cara Digunakan:** Berjalan secara senyap/otomatis tanpa perlu dipicu oleh kode PHP. Setiap kali HRD mengubah gaji pokok di form `dashboard-hrd.php` (melalui `hrd/proses_update_gaji.php`), log perubahan langsung tercatat secara otomatis.
*   **Keunggulan:**
    *   **Audit Trail yang Tidak Bisa Dimanipulasi:** Log pasti tercatat karena trigger berjalan di level database. Sekalipun ada user yang mengubah gaji pokok langsung dari aplikasi pihak ketiga (seperti DBeaver atau phpMyAdmin) tanpa lewat aplikasi web, perubahan tersebut tetap akan otomatis tercatat.
    *   **Pemisahan Tanggung Jawab (Decoupling):** Kode PHP tidak perlu tahu tentang adanya sistem log audit perubahan gaji.
*   **Kelemahan:** Terlalu banyak trigger aktif dapat memperlambat operasi penulisan data (`INSERT`/`UPDATE`/`DELETE`) dan bisa menyebabkan efek domino yang sulit dilacak jika terjadi error database.
*   **Analisis Jika-Sebab-Akibat:**
    *   *Jika* sistem **tidak menggunakan Trigger**,
    *   *Sebab* pencatatan log audit perubahan gaji didelegasikan ke file PHP (`proses_update_gaji.php`),
    *   *Akibatnya* jika ada admin nakal yang meretas database dan mengubah gaji pokok langsung melalui database server tanpa melalui aplikasi web PHP, aksi kecurangan tersebut tidak akan pernah tercatat di log audit, merusak transparansi dan kepatuhan sistem keamanan finansial.
