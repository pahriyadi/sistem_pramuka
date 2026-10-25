<?php
/**
 * Cetak ID Card Peserta & Bindamping Resmi
 * Racana Universitas Samawa (UNSA)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_login();

$pdo = getDB();
$userRole = get_user_role();
$sekolah = null;
$pesertaId = (int)($_GET['id'] ?? 0);
$sekolahIdParam = (int)($_GET['sekolah_id'] ?? 0);

if ($userRole === 'admin') {
    if ($sekolahIdParam > 0) {
        $stmtSek = $pdo->prepare("SELECT * FROM sekolah WHERE id = ?");
        $stmtSek->execute([$sekolahIdParam]);
        $sekolah = $stmtSek->fetch();
    }
} else {
    $sekolah = get_current_sekolah();
}

$query = "
    SELECT p.*, s.nama_sekolah, s.gudep_putra, s.gudep_putri, s.alamat_sekolah,
           k.nama_kategori, k.kode_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ' • ') as daftar_lomba
    FROM peserta p
    JOIN sekolah s ON p.sekolah_id = s.id
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE p.status = 'aktif'
";
$params = [];

if ($pesertaId > 0) {
    $query .= " AND p.id = ?";
    $params[] = $pesertaId;
    if ($userRole === 'sekolah' && $sekolah) {
        $query .= " AND p.sekolah_id = ?";
        $params[] = $sekolah['id'];
    }
} elseif ($sekolah) {
    $query .= " AND p.sekolah_id = ?";
    $params[] = $sekolah['id'];
}

$query .= " GROUP BY p.id ORDER BY k.urutan ASC, p.id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pesertaList = $stmt->fetchAll();
$namaAcara = get_setting('nama_acara', 'Kegiatan Racana');
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$gudep = get_setting('gudep_putra', '01.089') . ' - ' . get_setting('gudep_putri', '01.090');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak ID Card Peserta | <?= e($namaRacana) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
    <style>
        body {
            background-color: #f1f5f9;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
            }
            .cards-container {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 20px !important;
                justify-content: flex-start !important;
            }
        }
    </style>
</head>
<body class="p-3 p-md-4">

<!-- Top Action Bar (Hidden when printing) -->
<div class="no-print mb-4 bg-white p-3 rounded-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $userRole === 'admin' ? base_url('admin/peserta-all.php') : base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
        </a>
        <h5 class="fw-bold mb-0 text-dark">Pratinjau Cetak ID Card (<?= count($pesertaList) ?> Kartu)</h5>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-scout-gold fw-bold btn-sm px-4" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Cetak / Print ID Card
        </button>
    </div>
</div>

<?php if (empty($pesertaList)): ?>
    <div class="text-center py-5 bg-white rounded-4 shadow-sm p-4">
        <i class="fa-solid fa-id-card-clip fs-1 text-muted mb-3"></i>
        <h5 class="fw-bold">Tidak Ada Data ID Card</h5>
        <p class="text-muted small">Belum ada peserta yang didaftarkan pada kontingen ini.</p>
    </div>
<?php else: ?>
    <div class="cards-container d-flex flex-wrap justify-content-center gap-4">
        <?php foreach ($pesertaList as $p): 
            $fotoUrl = !empty($p['foto']) ? base_url('uploads/peserta/' . $p['foto']) : null;
            $roleColor = '#0b2545'; // Default Navy
            if (strpos($p['kode_kategori'], 'BAR_PUTRA') !== false) {
                $roleBadge = 'SANGGA PUTRA';
                $roleColor = '#0b2545'; // Biru Tua Laut
            } elseif (strpos($p['kode_kategori'], 'BAR_PUTRI') !== false) {
                $roleBadge = 'SANGGA PUTRI';
                $roleColor = '#800000'; // Merah Maron
            } else {
                $roleBadge = 'BINDAMPING';
                $roleColor = '#5d3a00'; // Coklat Pramuka
            }
        ?>
            <!-- Single Printable ID Card -->
            <div class="id-card-wrapper" style="border-color: <?= $roleColor ?>;">
                <!-- Header Card -->
                <div class="id-card-header" style="background: linear-gradient(135deg, #07172b 0%, <?= $roleColor ?> 100%);">
                    <div class="event-title"><?= e($namaAcara) ?></div>
                    <div class="org-title text-white"><?= e($namaRacana) ?></div>
                    <small class="text-white-50" style="font-size: 0.65rem;">Gudep <?= e($gudep) ?></small>
                </div>

                <!-- Photo Section -->
                <div class="id-card-photo-container">
                    <?php if ($fotoUrl): ?>
                        <img src="<?= $fotoUrl ?>" alt="<?= e($p['nama_lengkap']) ?>" class="id-card-photo" style="border-color: <?= $roleColor ?>;">
                    <?php else: ?>
                        <div class="id-card-photo d-flex flex-column align-items-center justify-content-center bg-light text-muted" style="border-color: <?= $roleColor ?>;">
                            <i class="fa-solid fa-user fs-2 text-secondary mb-1"></i>
                            <span style="font-size: 0.65rem;">PAS FOTO</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Participant Info -->
                <div class="id-card-body">
                    <div class="id-card-role-badge" style="background-color: <?= $roleColor ?>;">
                        <?= $roleBadge ?>
                    </div>
                    <div class="id-card-name text-truncate"><?= e($p['nama_lengkap']) ?></div>
                    <div class="id-card-school text-truncate"><?= e($p['nama_sekolah']) ?></div>

                    <!-- Selected Competitions -->
                    <div class="id-card-lomba-list">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.65rem;">
                            <i class="fa-solid fa-trophy text-warning me-1"></i> Mata Lomba Diikuti:
                        </div>
                        <div class="text-muted" style="line-height: 1.2;">
                            <?= e($p['daftar_lomba'] ?: 'Peserta Perkemahan & Bakti') ?>
                        </div>
                    </div>
                </div>

                <!-- Footer Card -->
                <div class="id-card-footer">
                    <div>
                        <span class="fw-bold text-dark d-block">ID: UNSA-<?= str_pad($p['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <span><?= $p['jenis_kelamin'] === 'L' ? 'Putra' : 'Putri' ?> &bull; <?= e($p['kelas']) ?></span>
                    </div>
                    <div class="text-end font-monospace" style="font-size: 0.6rem;">
                        <i class="fa-solid fa-qrcode fs-5 text-dark"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>
