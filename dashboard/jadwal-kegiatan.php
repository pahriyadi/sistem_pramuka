<?php
/**
 * Jadwal Kegiatan & Perlombaan
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Jadwal Pelaksanaan Kegiatan';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();
$sekolah = get_current_sekolah();

$pdo = getDB();
$stmtLomba = $pdo->query("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' ORDER BY id ASC");
$daftarLomba = $stmtLomba->fetchAll();
$namaAcara = get_setting('nama_acara', 'Kegiatan Racana');
$tglKemah = get_setting('tgl_pelaksanaan_kemah', '');
$rundown = preg_split('/\r\n|\r|\n/', get_setting('rundown_kegiatan', '')) ?: [];
$rundown = array_values(array_filter(array_map('trim', $rundown), static fn($item) => $item !== ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($sekolah['nama_sekolah']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Jadwal Pelaksanaan Kegiatan</h4>
                <small class="text-muted"><?= e($namaAcara) ?><?= $tglKemah !== '' ? ' • ' . e($tglKemah) : '' ?></small>
            </div>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <!-- Timeline Rundown -->
            <div class="custom-card p-4 mb-4">
                <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">
                    <i class="fa-solid fa-calendar-check text-success me-2"></i> Rundown Agenda Utama
                </h5>

                <div class="d-flex flex-column gap-3">
                    <?php if (empty($rundown)): ?>
                        <p class="text-muted small mb-0">Rundown kegiatan belum ditambahkan oleh panitia.</p>
                    <?php else: ?>
                        <?php foreach ($rundown as $agenda): ?>
                            <div class="p-3 border rounded-3 bg-light border-start border-4 border-success">
                                <p class="text-muted small mb-0"><?= nl2br(e($agenda)) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="custom-card p-4">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-map-location-dot text-danger me-2"></i> Lokasi Tiap Mata Lomba</h5>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($daftarLomba as $l): ?>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark"><?= e($l['nama_lomba']) ?></div>
                                <small class="text-muted"><i class="fa-solid fa-location-pin text-danger me-1"></i> <?= e($l['lokasi_lomba']) ?></small>
                            </div>
                            <span class="badge bg-light text-dark border"><?= e($l['kode_lomba']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
