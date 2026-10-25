<?php
/**
 * Master Admin Dashboard - Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Dashboard Panitia Racana';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();

$pdo = getDB();

// Global Statistics
$totalSekolah = (int)$pdo->query("SELECT COUNT(*) FROM sekolah")->fetchColumn();
$totalVerifikasi = (int)$pdo->query("SELECT COUNT(*) FROM sekolah WHERE status_verifikasi = 'terverifikasi'")->fetchColumn();
$totalMenunggu = (int)$pdo->query("SELECT COUNT(*) FROM sekolah WHERE status_verifikasi = 'menunggu'")->fetchColumn();

$totalPeserta = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE status = 'aktif'")->fetchColumn();
$totalPutra = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE jenis_kelamin = 'L' AND status = 'aktif'")->fetchColumn();
$totalPutri = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE jenis_kelamin = 'P' AND status = 'aktif'")->fetchColumn();

// Payment Statistics
$totalBayarLunas = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'disetujui'")->fetchColumn();
$totalBayarPending = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'menunggu_konfirmasi'")->fetchColumn();
$totalDanaMasuk = (float)$pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE status = 'disetujui'")->fetchColumn();

// Total Peserta per Mata Lomba
$stmtLombaStats = $pdo->query("
    SELECT m.id, m.kode_lomba, m.nama_lomba, m.max_peserta_per_sekolah,
           COUNT(pl.id) as total_peserta_terdaftar
    FROM mata_lomba m
    LEFT JOIN peserta_lomba pl ON m.id = pl.lomba_id
    LEFT JOIN peserta p ON pl.peserta_id = p.id AND p.status = 'aktif'
    WHERE m.status_aktif = 'Y'
    GROUP BY m.id
    ORDER BY total_peserta_terdaftar DESC
");
$lombaStats = $stmtLombaStats->fetchAll();

// Recent Contingents
$stmtRecentSekolah = $pdo->query("
    SELECT s.*, u.username,
           COUNT(p.id) as total_peserta_terisi
    FROM sekolah s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN peserta p ON s.id = p.sekolah_id AND p.status = 'aktif'
    GROUP BY s.id
    ORDER BY s.id DESC
    LIMIT 6
");
$recentSekolah = $stmtRecentSekolah->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Racana Universitas Samawa</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Dashboard Panitia</h4>
                <small class="text-muted">Pusat Kendali <?= e(get_setting('nama_acara', 'Kegiatan Racana')) ?> • <?= e(get_setting('nama_brand', 'Racana')) ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline-scout-navy btn-sm px-3">
                <i class="fa-solid fa-users-gear me-1"></i> Users
            </a>
            <a href="<?= base_url('admin/pembayaran.php') ?>" class="btn btn-scout-gold btn-sm px-3">
                <i class="fa-solid fa-credit-card me-1"></i> Pembayaran
            </a>
            <a href="<?= base_url('admin/cetak-rekap.php') ?>" class="btn btn-outline-secondary btn-sm px-3" target="_blank">
                <i class="fa-solid fa-print me-1"></i> Cetak Rekap
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- PENDING PAYMENTS ALERT BANNER -->
    <?php if ($totalBayarPending > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #ffc107 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-money-bill-transfer fs-2 text-warning"></i>
                <div>
                    <div class="fw-bold text-dark fs-6">Ada <?= $totalBayarPending ?> Pembayaran Kontingen Menunggu Verifikasi!</div>
                    <small class="text-muted">Sekolah telah mengunggah bukti transfer struk pembayaran. Silakan periksa dan verifikasi berkas mereka.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('admin/pembayaran.php?status=menunggu_konfirmasi') ?>" class="btn btn-dark btn-sm fw-bold px-3">
                    <i class="fa-solid fa-stamp me-1"></i> Verifikasi Sekarang
                </a>
            </div>
        </div>
    <?php endif; ?>


    <!-- Summary Widgets Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-widget green h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Dana Terverifikasi</span>
                        <h4 class="fw-bold mb-0 text-success font-monospace"><?= format_rupiah($totalDanaMasuk) ?></h4>
                    </div>
                    <div class="feature-icon-wrapper icon-green mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <span class="text-success fw-bold"><?= $totalBayarLunas ?> Lunas</span> &bull; 
                    <span class="<?= $totalBayarPending > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= $totalBayarPending ?> Menunggu</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget gold h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Kontingen Sekolah</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalSekolah ?></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-amber mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-school"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <span class="text-success fw-bold"><?= $totalVerifikasi ?> Terverifikasi</span> &bull; 
                    <span class="text-warning fw-bold"><?= $totalMenunggu ?> Menunggu</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget blue h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Total Seluruh Peserta</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalPeserta ?></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-blue mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <?= $totalPutra ?> Pa &bull; <?= $totalPutri ?> Pi terdaftar
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget purple h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Mata Lomba Aktif</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= count($lombaStats) ?></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-purple mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>
                <div class="small text-muted text-truncate" title="<?= e(get_setting('nama_acara', 'Mata Lomba Kompetisi')) ?>">
                    <?= e(get_setting('nama_acara', 'Mata Lomba Kompetisi')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Contingents Table -->
        <div class="col-lg-7">
            <div class="custom-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-school text-success me-2"></i> Pangkalan Sekolah yang Baru Mendaftar</h5>
                    <a href="<?= base_url('admin/kontingen.php') ?>" class="small text-success fw-bold text-decoration-none">
                        Lihat Semua (<?= $totalSekolah ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($recentSekolah)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-school-circle-xmark fs-2 mb-2"></i>
                        <p class="small">Belum ada sekolah yang mendaftar akun.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th>Sekolah</th>
                                    <th>Pembina / Kontak</th>
                                    <th>Peserta Terisi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSekolah as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($s['nama_sekolah']) ?></div>
                                            <small class="text-muted text-truncate d-block" style="max-width: 180px;"><?= e($s['alamat_sekolah']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= e($s['nama_pembina'] ?: '-') ?></div>
                                            <small class="text-muted"><?= e($s['no_wa_pembina'] ?: '-') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-25 text-dark fw-bold">
                                                <?= $s['total_peserta_terisi'] ?> / 24 Org
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $s['status_verifikasi'] === 'terverifikasi' ? 'success' : ($s['status_verifikasi'] === 'ditolak' ? 'danger' : 'warning text-dark') ?>">
                                                <?= strtoupper($s['status_verifikasi']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('admin/kontingen-detail.php?id=' . $s['id']) ?>" class="btn btn-sm btn-outline-primary" title="Detail Kontingen">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Participants per Competition -->
        <div class="col-lg-5">
            <div class="custom-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-trophy text-warning me-2"></i> Peminat Cabang Lomba</h5>
                    <a href="<?= base_url('admin/mata-lomba.php') ?>" class="small text-success fw-bold text-decoration-none">
                        Kelola Lomba <i class="fa-solid fa-gear"></i>
                    </a>
                </div>

                <div class="d-flex flex-column gap-2">
                    <?php foreach ($lombaStats as $ls): ?>
                        <div class="p-2 px-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small"><?= e($ls['nama_lomba']) ?></div>
                                <small class="text-muted font-monospace"><?= e($ls['kode_lomba']) ?></small>
                            </div>
                            <span class="badge bg-success py-2 px-3">
                                <?= $ls['total_peserta_terdaftar'] ?> Peserta
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
