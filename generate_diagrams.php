<?php
// generate_diagrams.php

// Buat folder public/images jika belum ada
if (!is_dir('public/images')) {
    mkdir('public/images', 0777, true);
}

// Diagram 1: Sequence Diagram
$mermaid1 = "sequenceDiagram
    autonumber
    actor HR as Admin HRD
    actor KR as Karyawan
    participant Web as Aplikasi Web (PHP)
    participant DB as Database (MySQL)

    KR->>Web: Input Username & Password (login.php)
    Web->>DB: Validasi kredensial di `master_user`
    DB-->>Web: Data User & Role (Karyawan)
    Web-->>KR: Redirect ke Portal Karyawan

    KR->>Web: Klik \"Tap Check-In Sekarang\" (dashboard-karyawan.php)
    Web->>Web: Proteksi IP Wi-Fi Kantor (harus ::1 / 127.0.0.1)
    Web->>DB: Insert ke `log_absensi_harian`
    DB-->>KR: Status Sukses Absen & Menampilkan Jam Tap

    KR->>Web: Ajukan Lembur (Input Durasi & Alasan)
    Web->>DB: Insert ke `pengajuan_lembur` (Status: Pending)

    HR->>Web: Login sebagai Admin HRD
    Web-->>HR: Tampilkan Daftar Karyawan & Log Perubahan Gaji
    HR->>Web: ACC / Tolak Pengajuan Lembur Karyawan
    Web->>DB: Update status di `pengajuan_lembur` (Approved/Rejected)

    HR->>Web: Pilih Periode & Klik \"Eksekusi SP Gaji Massal\"
    Web->>DB: Panggil `CALL sp_GeneratePayrollMassal(Bulan, Tahun)`
    DB->>DB: Mesin Database menghitung gaji, denda, dan lembur otomatis
    DB-->>Web: Hasil komputasi selesai disimpan
    Web-->>HR: Tampilkan notifikasi \"Kalkulasi Finansial Berhasil!\"";

// Diagram 2: ERD
$mermaid2 = "erDiagram
    master_jabatan ||--|{ master_karyawan : \"memiliki\"
    master_jabatan ||--|{ log_perubahan_gaji : \"dicatat oleh trigger\"
    master_karyawan ||--|| master_user : \"memiliki akun\"
    master_karyawan ||--|{ log_absensi_harian : \"mencatat tap\"
    master_karyawan ||--|{ pengajuan_lembur : \"mengajukan\"
    master_karyawan ||--|{ rekap_absensi : \"memiliki rekap bulanan\"
    master_karyawan ||--|{ slip_gaji_h : \"memiliki slip gaji\"
    slip_gaji_h ||--|{ slip_gaji_d : \"memiliki rincian detail\"";

// Diagram 3: Architecture
$mermaid3 = "graph TD
    subgraph Database MySQL
        A[Trigger: tr_LogPerubahanGaji] -->|Otomatis catat perubahan| B(log_perubahan_gaji)
        C[Stored Procedure: sp_GeneratePayrollMassal] -->|Dijalankan lewat Cursor| D{Loop Karyawan}
        D -->|Hitung Lembur & Denda| E[slip_gaji_h & slip_gaji_d]
        F[Views: v_detailprofilkaryawan] -->|Gabungkan data dinamis| G(Tampilan Web PHP)
    end
    
    H[Admin HRD] -->|Picu Update Gaji| A
    H -->|Picu SP Gaji Massal| C";

function download_diagram($mermaidCode, $theme, $filename) {
    // Bungkus payload dalam format JSON untuk mermaid.ink
    $payload = [
        "code" => $mermaidCode,
        "mermaid" => [
            "theme" => $theme
        ]
    ];
    $json = json_encode($payload);
    
    // Encode ke base64 url-safe
    $base64 = base64_encode($json);
    $base64_url = strtr($base64, '+/', '-_');
    $base64_url = rtrim($base64_url, '=');
    
    $url = "https://mermaid.ink/img/" . $base64_url;
    echo "Downloading $filename ($theme) from $url...\n";
    
    // Set User-Agent headers agar server tidak memblokir request
    $options = [
        "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
            "timeout" => 30
        ]
    ];
    $context = stream_context_create($options);
    $imgData = file_get_contents($url, false, $context);
    if ($imgData !== false) {
        file_put_contents('public/images/' . $filename, $imgData);
        echo "Saved to public/images/$filename successfully.\n";
    } else {
        echo "Failed to download $filename.\n";
    }
}

// Download versi gelap (Dark Mode)
download_diagram($mermaid1, 'dark', 'workflow_seq_dark.png');
download_diagram($mermaid2, 'dark', 'database_erd_dark.png');
download_diagram($mermaid3, 'dark', 'architecture_flow_dark.png');

// Download versi terang (Light Mode)
download_diagram($mermaid1, 'default', 'workflow_seq_light.png');
download_diagram($mermaid2, 'default', 'database_erd_light.png');
download_diagram($mermaid3, 'default', 'architecture_flow_light.png');
?>
