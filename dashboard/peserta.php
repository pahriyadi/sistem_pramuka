<?php
/**
 * Kelola Data Peserta & Bindamping Kontingen
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Daftar Peserta Kontingen';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();
if (!$sekolah) {
    die("Data sekolah tidak ditemukan.");
}

$sekolahId = (int)$sekolah['id'];
$isLocked = ($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci';
$waNumber = get_whatsapp_number();
$waHelpText = rawurlencode('Halo Admin ' . get_setting('nama_brand', 'RACANA UNSA') . ', kami dari ' . $sekolah['nama_sekolah'] . ' ingin meminta pembukaan akses edit pendaftaran peserta.');

// Filter by category if set
$kategoriFilter = (int)($_GET['kategori'] ?? 0);
$statusFilter = trim($_GET['status'] ?? ''); // 'aktif', 'draft', or '' for all

$query = "
    SELECT p.*, k.nama_kategori, k.kode_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ', ') as daftar_lomba,
           COUNT(pl.id) as total_lomba_diikuti
    FROM peserta p
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE p.sekolah_id = ?
";
$params = [$sekolahId];

if (!empty($statusFilter) && in_array($statusFilter, ['aktif', 'draft'])) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
} else {
    $query .= " AND p.status IN ('aktif', 'draft')";
}

if ($kategoriFilter > 0) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $kategoriFilter;
}

$query .= " GROUP BY p.id ORDER BY k.urutan ASC, p.status ASC, p.id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pesertaList = $stmt->fetchAll();

// Total counts
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM peserta WHERE sekolah_id = ? AND status IN ('aktif', 'draft')");
$stmtTotal->execute([$sekolahId]);
$totalPeserta = (int)$stmtTotal->fetchColumn();

$stmtAktif = $pdo->prepare("SELECT COUNT(*) FROM peserta WHERE sekolah_id = ? AND status = 'aktif'");
$stmtAktif->execute([$sekolahId]);
$totalAktif = (int)$stmtAktif->fetchColumn();

$stmtDraft = $pdo->prepare("SELECT COUNT(*) FROM peserta WHERE sekolah_id = ? AND status = 'draft'");
$stmtDraft->execute([$sekolahId]);
$totalDraft = (int)$stmtDraft->fetchColumn();

// Fetch Kategori for Filter Tabs
$stmtKat = $pdo->prepare("
    SELECT k.*, 
           COUNT(p.id) as count_per_kat,
           SUM(CASE WHEN p.status = 'aktif' THEN 1 ELSE 0 END) as count_aktif,
           SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as count_draft
    FROM kategori_peserta k
    LEFT JOIN peserta p ON p.kategori_id = k.id AND p.sekolah_id = ? AND p.status IN ('aktif', 'draft')
    GROUP BY k.id
    ORDER BY k.urutan ASC
");
$stmtKat->execute([$sekolahId]);
$listKategori = $stmtKat->fetchAll();
$totalKuota = array_sum(array_map(static fn($kategori) => (int)$kategori['kuota_max'], $listKategori));
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
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-dark">Data Anggota Kontingen</h4>
                    <?php if ($isLocked): ?>
                        <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Terkunci</span>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-15 text-success border border-success"><i class="fa-solid fa-lock-open me-1"></i> Terbuka</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?= $totalPeserta ?>/<?= $totalKuota ?> Kuota (<?= $totalAktif ?> Resmi, <?= $totalDraft ?> Draf)</small>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn <?= $isLocked ? 'btn-outline-primary' : 'btn-scout-gold' ?> btn-sm px-3 shadow-sm">
                <i class="fa-solid <?= $isLocked ? 'fa-eye' : 'fa-layer-group' ?> me-1"></i> <?= $isLocked ? 'Lihat Form' : 'Form Multi-Batch' ?>
            </a>
            <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" class="btn btn-outline-scout-navy btn-sm px-3" target="_blank">
                <i class="fa-solid fa-id-card me-1"></i> ID Card
            </a>
            <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" class="btn btn-outline-secondary btn-sm px-3" target="_blank">
                <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Rekap A.01
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- LOCKED STATUS BANNER -->
    <?php if ($isLocked): ?>
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #dc3545 !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-6">Status Pendaftaran: Terkunci (Finalisasi Resmi)</div>
                    <small class="text-muted">Pangkalan sekolah Anda telah memfinalisasi seluruh data peserta. Hak akses edit dan hapus dikunci demi validitas data. Hubungi Panitia Admin jika ada perbaikan data.</small>
                </div>
            </div>
            <div>
                <?php if ($waNumber !== ''): ?>
                <a href="https://wa.me/<?= e($waNumber) ?>?text=<?= e($waHelpText) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm px-3 shadow-sm">
                    <i class="fa-brands fa-whatsapp me-1"></i> Bantuan Panitia
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- DRAFT NOTICE BANNER -->
    <?php if ($totalDraft > 0 && !$isLocked): ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation fs-3 text-warning"></i>
                <div>
                    <div class="fw-bold text-dark">Terdapat <?= $totalDraft ?> Data Anggota Berstatus DRAF</div>
                    <small class="text-muted">Data berstatus Draf belum masuk ke rekap perlombaan resmi dan belum dapat dicetak ID Card resminya. Silakan fiksasi data jika sudah lengkap.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-dark btn-sm px-3 fw-bold">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Buka Form Multi-Batch &amp; Finalisasi
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Bar (Category & Status) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <!-- Category Filter Tabs -->
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('dashboard/peserta.php' . ($statusFilter ? '?status=' . $statusFilter : '')) ?>" class="btn btn-sm <?= $kategoriFilter === 0 ? 'btn-success' : 'btn-outline-secondary' ?>">
                Semua Kategori <span class="badge bg-dark ms-1"><?= $totalPeserta ?></span>
            </a>
            <?php foreach ($listKategori as $kat): 
                $katUrl = base_url('dashboard/peserta.php?kategori=' . $kat['id'] . ($statusFilter ? '&status=' . $statusFilter : ''));
            ?>
                <a href="<?= $katUrl ?>" class="btn btn-sm <?= $kategoriFilter == $kat['id'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= e($kat['nama_kategori']) ?> <span class="badge bg-secondary ms-1"><?= $kat['count_per_kat'] ?>/<?= $kat['kuota_max'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Status Filter Buttons -->
        <div class="btn-group btn-group-sm">
            <a href="<?= base_url('dashboard/peserta.php' . ($kategoriFilter ? '?kategori=' . $kategoriFilter : '')) ?>" class="btn <?= empty($statusFilter) ? 'btn-dark' : 'btn-outline-secondary' ?>">
                Semua Status
            </a>
            <a href="<?= base_url('dashboard/peserta.php?status=aktif' . ($kategoriFilter ? '&kategori=' . $kategoriFilter : '')) ?>" class="btn <?= $statusFilter === 'aktif' ? 'btn-success' : 'btn-outline-success' ?>">
                <i class="fa-solid fa-circle-check me-1"></i> Resmi (<?= $totalAktif ?>)
            </a>
            <a href="<?= base_url('dashboard/peserta.php?status=draft' . ($kategoriFilter ? '&kategori=' . $kategoriFilter : '')) ?>" class="btn <?= $statusFilter === 'draft' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">
                <i class="fa-solid fa-bookmark me-1"></i> Draf (<?= $totalDraft ?>)
            </a>
        </div>
    </div>

    <!-- Main Table -->
    <div class="custom-card p-4">
        <?php if (empty($pesertaList)): ?>
            <div class="text-center py-5">
                <div class="feature-icon-wrapper icon-green mx-auto mb-3">
                    <i class="fa-solid fa-users-slash fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark">Tidak Ada Data Peserta</h5>
                <p class="text-muted small mb-4">Belum ada anggota yang didaftarkan pada filter kategori / status ini.</p>
                <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-scout-gold px-4 py-2">
                    <i class="fa-solid fa-layer-group me-1"></i> Buka Formulir Pendaftaran Multi-Batch
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45px;">No</th>
                            <th>Status</th>
                            <th>Foto</th>
                            <th>Nama Lengkap</th>
                            <th>Kategori Barisan</th>
                            <th>Kelas</th>
                            <th>Tgl Lahir / Umur</th>
                            <th>Gender</th>
                            <th>Mata Lomba yang Diikuti</th>
                            <th class="text-center" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($pesertaList as $p): 
                            $umurData = hitung_umur($p['tanggal_lahir']);
                            $fotoUrl = !empty($p['foto']) ? base_url('uploads/peserta/' . $p['foto']) : null;
                        ?>
                            <tr>
                                <td class="fw-semibold text-muted"><?= $no++ ?></td>
                                <td>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> RESMI</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-bookmark me-1"></i> DRAF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fotoUrl): ?>
                                        <img src="<?= $fotoUrl ?>" alt="<?= e($p['nama_lengkap']) ?>" class="rounded" style="width: 45px; height: 55px; object-fit: cover; border: 2px solid #0d5c3a;">
                                    <?php else: ?>
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted border" style="width: 45px; height: 55px;" title="Belum ada foto">
                                            <i class="fa-solid fa-image text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['nama_lengkap']) ?></div>
                                    <small class="text-muted font-monospace">NTA/NISN: <?= e($p['nisn_nta'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-15 text-dark border">
                                        <?= e($p['nama_kategori']) ?>
                                    </span>
                                </td>
                                <td><?= e($p['kelas']) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= format_tanggal_indonesia($p['tanggal_lahir']) ?></div>
                                    <span class="badge bg-success bg-opacity-15 text-success font-monospace" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-cake-candles me-1"></i> <?= $umurData['teks'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['jenis_kelamin'] === 'L'): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-mars me-1"></i> Laki-laki</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-venus me-1"></i> Perempuan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['daftar_lomba'])): ?>
                                        <div class="small text-dark fw-semibold"><?= e($p['daftar_lomba']) ?></div>
                                        <small class="text-muted">(<?= $p['total_lomba_diikuti'] ?> Cabang Lomba)</small>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Belum memilih lomba</em></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($p['status'] === 'aktif'): ?>
                                            <a href="<?= base_url('dashboard/cetak-idcard.php?id=' . $p['id']) ?>" target="_blank" class="btn btn-outline-success" title="Cetak ID Card Peserta">
                                                <i class="fa-solid fa-id-card"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isLocked): ?>
                                            <button class="btn btn-outline-secondary" disabled title="Data telah difinalisasi dan terkunci. Hubungi Admin untuk edit.">
                                                <i class="fa-solid fa-lock text-muted"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="<?= base_url('dashboard/peserta-edit.php?id=' . $p['id']) ?>" class="btn btn-outline-primary" title="Edit Peserta">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="<?= base_url('dashboard/peserta-hapus.php?id=' . $p['id']) ?>" class="btn btn-outline-danger" title="Hapus Peserta" onclick="return confirm('Apakah Anda yakin ingin menghapus peserta <?= e(addslashes($p['nama_lengkap'])) ?>?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
