<?php
/**
 * Cetak Formulir Rekap Kontingen (A.01 & B.01)
 * Racana Universitas Samawa (UNSA)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_login();

$pdo = getDB();
$userRole = get_user_role();
$sekolah = null;
$sekolahIdParam = (int)($_GET['sekolah_id'] ?? 0);

if ($userRole === 'admin') {
    if ($sekolahIdParam > 0) {
        $stmtSek = $pdo->prepare("SELECT * FROM sekolah WHERE id = ?");
        $stmtSek->execute([$sekolahIdParam]);
        $sekolah = $stmtSek->fetch();
    } else {
        // Fallback to first school for admin
        $stmtSek = $pdo->query("SELECT * FROM sekolah ORDER BY id ASC LIMIT 1");
        $sekolah = $stmtSek->fetch();
    }
} else {
    $sekolah = get_current_sekolah();
}

if (!$sekolah) {
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
        <h3>Data Kontingen Sekolah Tidak Ditemukan</h3>
        <p>Belum ada data sekolah yang terdaftar atau parameter sekolah_id tidak valid.</p>
        <a href='" . base_url('admin/kontingen.php') . "'>Kembali ke Data Kontingen</a>
    </div>");
}

$sekolahId = (int)$sekolah['id'];

// Fetch All 24 Participants
$stmtP = $pdo->prepare("
    SELECT p.*, k.nama_kategori, k.kode_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ', ') as daftar_lomba
    FROM peserta p
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE p.sekolah_id = ? AND p.status = 'aktif'
    GROUP BY p.id
    ORDER BY k.urutan ASC, p.id ASC
");
$stmtP->execute([$sekolahId]);
$pesertaList = $stmtP->fetchAll();

$namaAcara = get_setting('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-NTB 2026');
$temaAcara = get_setting('tema_acara', 'Satyaku Kudarmakan, Darmaku Kubaktikan Demi Kejayaan Tana Samawa');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Rekap Kontingen A.01 - <?= e($sekolah['nama_sekolah']) ?></title>
    
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
            max-width: 900px;
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
        <a href="<?= $userRole === 'admin' ? base_url('admin/kontingen.php') : base_url('dashboard/index.php') ?>" class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
        <button class="btn btn-warning fw-bold btn-sm px-4" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Cetak Formulir (Print / PDF)
        </button>
    </div>
</div>

<div class="sheet-container">
    <!-- KOP RESMI -->
    <div class="header-kop text-center">
        <h5 class="fw-bold mb-1 text-uppercase">GERAKAN PRAMUKA GUGUS DEPAN 01.089 - 01.090</h5>
        <h4 class="fw-bold mb-1 text-uppercase">RACANA UNIVERSITAS SAMAWA (UNSA) SUMBAWA</h4>
        <div class="small mb-1">Sekretariat: Jl. By Pass Sering, Kerato, Kec. Unter Iwes, Kabupaten Sumbawa, NTB 84316</div>
        <small class="text-muted">Website: <?= e($_SERVER['HTTP_HOST'] ?? 'racanaunsa.my.id') ?> &bull; Email: <?= e(get_setting('kontak_email', 'racana@unsa.ac.id')) ?></small>
    </div>

    <div class="text-center mb-4">
        <h5 class="fw-bold text-decoration-underline mb-1">FORMULIR REKAPITULASI PENDAFTARAN KONTINGEN (FORM A.01)</h5>
        <small class="fw-semibold"><?= e($namaAcara) ?></small>
    </div>

    <!-- DATA PANGKALAN SEKOLAH -->
    <table class="table table-bordered table-sm mb-4" style="font-size: 0.88rem;">
        <tr>
            <th style="width: 25%; background: #f1f5f9;">Nama Pangkalan / Sekolah</th>
            <td style="width: 75%; font-weight: bold;"><?= e($sekolah['nama_sekolah']) ?></td>
        </tr>
        <tr>
            <th style="background: #f1f5f9;">Nomor Gudep Pangkalan</th>
            <td>Putra: <?= e($sekolah['gudep_putra'] ?: '01.001') ?> &bull; Putri: <?= e($sekolah['gudep_putri'] ?: '01.002') ?></td>
        </tr>
        <tr>
            <th style="background: #f1f5f9;">Alamat Lengkap Sekolah</th>
            <td><?= e($sekolah['alamat_sekolah']) ?></td>
        </tr>
        <tr>
            <th style="background: #f1f5f9;">Pembina Pendamping / Kontak</th>
            <td><?= e($sekolah['nama_pembina'] ?: '-') ?> (No. WA: <?= e($sekolah['no_wa_pembina'] ?: '-') ?>)</td>
        </tr>
        <tr>
            <th style="background: #f1f5f9;">Kepala Sekolah / Kamabigus</th>
            <td><?= e($sekolah['nama_kamabigus'] ?: '-') ?></td>
        </tr>
    </table>

    <!-- DAFTAR PESERTA & BINDAMPING -->
    <h6 class="fw-bold mb-2">DAFTAR PESERTA DAN PEMBINA PENDAMPING (FORM B.01)</h6>
    <table class="table table-bordered table-sm align-middle" style="font-size: 0.78rem;">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 30px;">No</th>
                <th>Nama Lengkap</th>
                <th>Kategori / Barisan</th>
                <th>L/P</th>
                <th>Kelas</th>
                <th>Tgl Lahir / Umur</th>
                <th>Mata Lomba yang Diikuti</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pesertaList)): ?>
                <tr>
                    <td colspan="7" class="text-center py-3 text-muted">Belum ada peserta yang didaftarkan.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($pesertaList as $p): 
                    $u = hitung_umur($p['tanggal_lahir']);
                ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold"><?= e($p['nama_lengkap']) ?></td>
                        <td><?= e($p['nama_kategori']) ?></td>
                        <td class="text-center"><?= $p['jenis_kelamin'] ?></td>
                        <td><?= e($p['kelas']) ?></td>
                        <td><?= format_tanggal_indonesia($p['tanggal_lahir']) ?> (<?= $u['teks'] ?>)</td>
                        <td><?= e($p['daftar_lomba'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- TANDA TANGAN & PENGESAHAN -->
    <div class="row mt-5 pt-3" style="font-size: 0.88rem;">
        <div class="col-6 text-center">
            <div>Mengetahui,</div>
            <div class="fw-bold">Kepala Sekolah / Kamabigus</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($sekolah['nama_kamabigus'] ?: '..........................................') ?></div>
            <div class="small text-muted">NIP. ........................................</div>
        </div>

        <div class="col-6 text-center">
            <div>Sumbawa, <?= format_tanggal_indonesia(date('Y-m-d')) ?></div>
            <div class="fw-bold">Pembina Pendamping Kontingen</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($sekolah['nama_pembina'] ?: '..........................................') ?></div>
            <div class="small text-muted">NTA. ........................................</div>
        </div>
    </div>
</div>

</body>
</html>
