<?php
/**
 * Cetak Laporan Rekapitulasi Panitia Pelaksana
 * Racana Universitas Samawa (UNSA)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

// 1. Rekap Sekolah Kontingen
$stmtSek = $pdo->query("
    SELECT s.*, 
           COUNT(p.id) as total_peserta,
           SUM(CASE WHEN p.jenis_kelamin = 'L' THEN 1 ELSE 0 END) as total_putra,
           SUM(CASE WHEN p.jenis_kelamin = 'P' THEN 1 ELSE 0 END) as total_putri
    FROM sekolah s
    LEFT JOIN peserta p ON s.id = p.sekolah_id AND p.status = 'aktif'
    GROUP BY s.id
    ORDER BY s.nama_sekolah ASC
");
$sekolahList = $stmtSek->fetchAll();

// 2. Rekap per Mata Lomba
$stmtLomba = $pdo->query("
    SELECT m.*, COUNT(pl.id) as total_peserta
    FROM mata_lomba m
    LEFT JOIN peserta_lomba pl ON m.id = pl.lomba_id
    GROUP BY m.id
    ORDER BY m.id ASC
");
$lombaList = $stmtLomba->fetchAll();

// Total Summary
$totalSemuaSekolah = count($sekolahList);
$totalSemuaPeserta = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE status = 'aktif'")->fetchColumn();
$totalSemuaPutra = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE jenis_kelamin = 'L' AND status = 'aktif'")->fetchColumn();
$totalSemuaPutri = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE jenis_kelamin = 'P' AND status = 'aktif'")->fetchColumn();

$namaAcara = get_setting('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-NTB 2026');
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$alamatRacana = get_setting('alamat_racana', '');
$emailRacana = get_setting('email_racana', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Panitia - <?= e($namaAcara) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background-color: #f8fafc;
            color: #000000;
        }
        .sheet-container {
            max-width: 950px;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .header-kop {
            border-bottom: 3px double #000000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
            }
            .sheet-container {
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print bg-dark text-white p-3 mb-4 d-flex justify-content-between align-items-center">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="<?= base_url('admin/index.php') ?>" class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Panel Admin
        </a>
        <button class="btn btn-warning fw-bold btn-sm px-4" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Cetak Laporan (Print / PDF)
        </button>
    </div>
</div>

<div class="sheet-container">
    <!-- KOP RESMI -->
    <div class="header-kop text-center">
        <h5 class="fw-bold mb-1 text-uppercase">PANITIA PELAKSANA <?= e($namaAcara) ?></h5>
        <h4 class="fw-bold mb-1 text-uppercase"><?= e($namaRacana) ?></h4>
        <div class="small mb-1">Sekretariat: <?= e($alamatRacana) ?></div>
        <small class="text-muted">Website: <?= e($_SERVER['HTTP_HOST'] ?? '') ?><?= $emailRacana !== '' ? ' • Email: ' . e($emailRacana) : '' ?></small>
    </div>

    <div class="text-center mb-4">
        <h5 class="fw-bold text-decoration-underline mb-1">LAPORAN REKAPITULASI PENDAFTARAN KONTINGEN &amp; PESERTA</h5>
        <small class="fw-semibold"><?= e($namaAcara) ?></small>
    </div>

    <!-- Ringkasan Statistik Global -->
    <div class="row g-2 mb-4 text-center" style="font-size: 0.85rem;">
        <div class="col-3">
            <div class="p-2 border bg-light">
                <span class="text-muted d-block small">Total Sekolah:</span>
                <strong class="fs-6"><?= $totalSemuaSekolah ?> Kontingen</strong>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border bg-light">
                <span class="text-muted d-block small">Total Peserta:</span>
                <strong class="fs-6"><?= $totalSemuaPeserta ?> Orang</strong>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border bg-light">
                <span class="text-muted d-block small">Peserta Putra:</span>
                <strong class="fs-6"><?= $totalSemuaPutra ?> Orang</strong>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border bg-light">
                <span class="text-muted d-block small">Peserta Putri:</span>
                <strong class="fs-6"><?= $totalSemuaPutri ?> Orang</strong>
            </div>
        </div>
    </div>

    <!-- 1. TABEL REKAP KONTINGEN -->
    <h6 class="fw-bold mb-2">1. REKAPITULASI KONTINGEN PANGKALAN SEKOLAH</h6>
    <table class="table table-bordered table-sm align-middle mb-4" style="font-size: 0.8rem;">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 30px;">No</th>
                <th>Nama Sekolah / Pangkalan</th>
                <th>Gudep</th>
                <th>Pembina Pendamping</th>
                <th>No. WA</th>
                <th>Putra</th>
                <th>Putri</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sekolahList)): ?>
                <tr>
                    <td colspan="9" class="text-center py-2 text-muted">Belum ada data pendaftar.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($sekolahList as $s): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold"><?= e($s['nama_sekolah']) ?></td>
                        <td class="text-center"><?= e($s['gudep_putra'] ?: '-') ?></td>
                        <td><?= e($s['nama_pembina'] ?: '-') ?></td>
                        <td><?= e($s['no_wa_pembina'] ?: '-') ?></td>
                        <td class="text-center"><?= $s['total_putra'] ?></td>
                        <td class="text-center"><?= $s['total_putri'] ?></td>
                        <td class="text-center fw-bold"><?= $s['total_peserta'] ?></td>
                        <td class="text-center"><?= strtoupper($s['status_verifikasi']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 2. TABEL REKAP MATA LOMBA -->
    <h6 class="fw-bold mb-2">2. REKAPITULASI PESERTA PER CABANG MATA LOMBA</h6>
    <table class="table table-bordered table-sm align-middle" style="font-size: 0.8rem;">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 30px;">No</th>
                <th>Kode</th>
                <th>Nama Cabang Mata Lomba</th>
                <th>PJ Lomba</th>
                <th>Lokasi</th>
                <th>Total Peserta Terdaftar</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($lombaList as $l): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center font-monospace"><?= e($l['kode_lomba']) ?></td>
                    <td class="fw-bold"><?= e($l['nama_lomba']) ?></td>
                    <td><?= e($l['penanggung_jawab'] ?: '-') ?></td>
                    <td><?= e($l['lokasi_lomba']) ?></td>
                    <td class="text-center fw-bold"><?= $l['total_peserta'] ?> Peserta</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- PENGESAHAN LAPORAN -->
    <div class="row mt-5 pt-3" style="font-size: 0.88rem;">
        <div class="col-6 text-center">
            <div>Mengetahui,</div>
            <div class="fw-bold">Ketua <?= e($namaRacana) ?></div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline">KAK AHMAD RIDWAN</div>
            <div class="small text-muted">NTA. 23.01.089.001</div>
        </div>

        <div class="col-6 text-center">
            <div>Sumbawa, <?= format_tanggal_indonesia(date('Y-m-d')) ?></div>
            <div class="fw-bold">Ketua Panitia Pelaksana</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline">KAK DIMAS PRATAMA</div>
            <div class="small text-muted">NTA. 23.01.089.014</div>
        </div>
    </div>
</div>

</body>
</html>
