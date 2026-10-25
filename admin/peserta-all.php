<?php
/**
 * Rekap Keseluruhan Peserta se-NTB - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Semua Data Peserta';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

// Filters
$sekolahFilter = (int)($_GET['sekolah_id'] ?? 0);
$kategoriFilter = (int)($_GET['kategori_id'] ?? 0);
$genderFilter = trim($_GET['gender'] ?? '');
$lombaFilter = (int)($_GET['lomba_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$keyword = trim($_GET['q'] ?? '');

$query = "
    SELECT p.*, s.nama_sekolah, s.alamat_sekolah,
           k.nama_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ', ') as daftar_lomba
    FROM peserta p
    JOIN sekolah s ON p.sekolah_id = s.id
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['aktif', 'draft'])) {
    $query .= " AND p.status = ?";
    $params[] = $statusFilter;
} else {
    $query .= " AND p.status IN ('aktif', 'draft')";
}

if ($sekolahFilter > 0) {
    $query .= " AND p.sekolah_id = ?";
    $params[] = $sekolahFilter;
}
if ($kategoriFilter > 0) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $kategoriFilter;
}
if (in_array($genderFilter, ['L', 'P'])) {
    $query .= " AND p.jenis_kelamin = ?";
    $params[] = $genderFilter;
}
if ($lombaFilter > 0) {
    $query .= " AND p.id IN (SELECT peserta_id FROM peserta_lomba WHERE lomba_id = ?)";
    $params[] = $lombaFilter;
}
if (!empty($keyword)) {
    $query .= " AND (p.nama_lengkap LIKE ? OR p.nisn_nta LIKE ? OR s.nama_sekolah LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$query .= " GROUP BY p.id ORDER BY s.nama_sekolah ASC, k.urutan ASC, p.id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pesertaList = $stmt->fetchAll();

// Fetch Reference Lists for Filter Dropdowns
$allSekolah = $pdo->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah ASC")->fetchAll();
$allKategori = $pdo->query("SELECT id, nama_kategori FROM kategori_peserta ORDER BY urutan ASC")->fetchAll();
$allLomba = $pdo->query("SELECT id, nama_lomba, kode_lomba FROM mata_lomba ORDER BY nama_lomba ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Admin Racana</title>
    
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
                <h4 class="fw-bold mb-0 text-dark">Data Semua Peserta</h4>
                <small class="text-muted">Total: <strong><?= count($pesertaList) ?> Peserta</strong></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('admin/cetak-rekap.php') ?>" class="btn btn-outline-secondary btn-sm px-3" target="_blank">
                <i class="fa-solid fa-print me-1"></i> Cetak Rekap
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="custom-card p-3 mb-4 bg-light">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-2">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/NISN/sekolah..." value="<?= e($keyword) ?>">
            </div>
            <div class="col-md-3">
                <select name="sekolah_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Sekolah --</option>
                    <?php foreach ($allSekolah as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sekolahFilter == $s['id'] ? 'selected' : '' ?>><?= e($s['nama_sekolah']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="kategori_id" class="form-select form-select-sm">
                    <option value="0">-- Kategori --</option>
                    <?php foreach ($allKategori as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kategoriFilter == $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="aktif" <?= $statusFilter === 'aktif' ? 'selected' : '' ?>>Resmi / Aktif</option>
                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draf</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="lomba_id" class="form-select form-select-sm">
                    <option value="0">-- Cabang Lomba --</option>
                    <?php foreach ($allLomba as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $lombaFilter == $l['id'] ? 'selected' : '' ?>><?= e($l['nama_lomba']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-scout-primary btn-sm flex-grow-1" title="Terapkan Filter">
                    <i class="fa-solid fa-filter"></i>
                </button>
                <a href="<?= base_url('admin/peserta-all.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="custom-card p-4">
        <?php if (empty($pesertaList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-users-slash fs-1 mb-2"></i>
                <h5>Tidak Ada Data Peserta yang Sesuai Filter</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Status</th>
                            <th>Foto</th>
                            <th>Nama Peserta</th>
                            <th>Asal Sekolah</th>
                            <th>Kategori</th>
                            <th>L/P</th>
                            <th>Kelas</th>
                            <th>Tgl Lahir / Umur</th>
                            <th>Mata Lomba yang Diikuti</th>
                            <th>ID Card</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($pesertaList as $p): 
                            $u = hitung_umur($p['tanggal_lahir']);
                            $fotoUrl = !empty($p['foto']) ? base_url('uploads/peserta/' . $p['foto']) : null;
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <span class="badge bg-success">RESMI</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">DRAF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fotoUrl): ?>
                                        <img src="<?= $fotoUrl ?>" alt="" class="rounded" style="width: 38px; height: 48px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted border" style="width: 38px; height: 48px;">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['nama_lengkap']) ?></div>
                                    <small class="text-muted font-monospace">NTA: <?= e($p['nisn_nta'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($p['nama_sekolah']) ?></div>
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-15 text-dark border"><?= e($p['nama_kategori']) ?></span></td>
                                <td><?= $p['jenis_kelamin'] === 'L' ? '<span class="text-primary fw-bold">L</span>' : '<span class="text-danger fw-bold">P</span>' ?></td>
                                <td><?= e($p['kelas']) ?></td>
                                <td><?= format_tanggal_indonesia($p['tanggal_lahir']) ?><br><span class="badge bg-success bg-opacity-15 text-success"><?= $u['teks'] ?></span></td>
                                <td><?= e($p['daftar_lomba'] ?: '-') ?></td>
                                <td>
                                    <a href="<?= base_url('dashboard/cetak-idcard.php?id=' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Cetak ID Card">
                                        <i class="fa-solid fa-id-card"></i>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
