<?php
/**
 * Katalog Cabang Kegiatan & Mata Lomba Aktif
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Cabang Kegiatan & Mata Lomba Aktif';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

$kategori = trim($_GET['kategori'] ?? '');
$search = trim($_GET['q'] ?? '');

$query = "
    SELECT m.*, COUNT(pl.id) as total_peserta
    FROM mata_lomba m
    LEFT JOIN peserta_lomba pl ON m.id = pl.lomba_id
    WHERE m.status_aktif = 'Y'
";
$params = [];

if (!empty($kategori)) {
    $query .= " AND m.kategori_lomba = ?";
    $params[] = $kategori;
}

if (!empty($search)) {
    $query .= " AND (m.nama_lomba LIKE ? OR m.kode_lomba LIKE ? OR m.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY m.id ORDER BY m.id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$daftarLomba = $stmt->fetchAll();

$namaAcara = get_setting('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-NTB 2026');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-trophy me-1"></i> KOMPETISI PRAMUKA</span>
        <h1 class="mb-2">Cabang Mata Lomba</h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Petunjuk teknis, kriteria penilaian, dan jadwal pelaksanaan lomba perkemahan.
        </p>
    </div>
</section>

<!-- Filter & Search Bar -->
<section class="py-4 bg-light border-bottom">
    <div class="container">
        <form method="GET" action="" class="row g-2 align-items-center justify-content-between">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari nama lomba atau kode..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-4">
                <select name="kategori" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori Lomba --</option>
                    <option value="Campuran / Umum" <?= $kategori === 'Campuran / Umum' ? 'selected' : '' ?>>Campuran / Umum</option>
                    <option value="Putra" <?= $kategori === 'Putra' ? 'selected' : '' ?>>Khusus Putra</option>
                    <option value="Putri" <?= $kategori === 'Putri' ? 'selected' : '' ?>>Khusus Putri</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-scout-navy w-100 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Terapkan
                </button>
                <?php if (!empty($search) || !empty($kategori)): ?>
                    <a href="<?= base_url('lomba.php') ?>" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>

<!-- Lomba Cards Grid -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Daftar Cabang Lomba (<?= count($daftarLomba) ?> Lomba)</h4>
                <small class="text-muted">Klik pada kartu lomba untuk membuka petunjuk teknis (Juknis) lengkap</small>
            </div>
            <a href="<?= base_url('petunjuk-teknis.php') ?>" class="btn btn-outline-navy btn-sm">
                <i class="fa-solid fa-book-open me-1"></i> Juknis Keseluruhan
            </a>
        </div>

        <?php if (empty($daftarLomba)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-trophy fs-1 mb-3 text-secondary"></i>
                <h5 class="fw-bold">Tidak Ada Cabang Lomba yang Ditemukan</h5>
                <p class="small">Coba ubah kata kunci pencarian atau filter kategori Anda.</p>
                <a href="<?= base_url('lomba.php') ?>" class="btn btn-scout-navy btn-sm px-4">Tampilkan Semua Lomba</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($daftarLomba as $lomba): 
                    $iconClass = !empty($lomba['icon_lomba']) ? $lomba['icon_lomba'] : 'fa-trophy';
                ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="custom-card p-4 h-100 d-flex flex-column border">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge badge-scout-navy font-monospace">
                                    <?= e($lomba['kode_lomba']) ?>
                                </span>
                                <span class="badge bg-warning bg-opacity-25 text-dark small fw-semibold">
                                    <i class="fa-solid fa-users me-1"></i> Max <?= $lomba['max_peserta_per_sekolah'] ?> Org
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 36px; height: 36px; background: linear-gradient(135deg, #0b2545, #800000) !important;">
                                    <i class="fa-solid <?= $iconClass ?> text-warning"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-dark"><?= e($lomba['nama_lomba']) ?></h5>
                            </div>

                            <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.6;">
                                <?= e($lomba['deskripsi']) ?>
                            </p>

                            <div class="p-2 px-3 bg-light rounded-3 small mb-3">
                                <div class="text-muted d-flex justify-content-between mb-1">
                                    <span><i class="fa-solid fa-location-pin text-danger me-1"></i> Lokasi:</span>
                                    <strong class="text-dark"><?= e($lomba['lokasi_lomba']) ?></strong>
                                </div>
                                <div class="text-muted d-flex justify-content-between">
                                    <span><i class="fa-solid fa-calendar-day text-primary me-1"></i> Jadwal:</span>
                                    <strong class="text-dark text-truncate" style="max-width: 160px;"><?= e($lomba['jadwal_lomba'] ?: 'Hari Ke-2') ?></strong>
                                </div>
                            </div>

                            <!-- Tombol Langsung ke Informasi Lengkap Lomba -->
                            <a href="<?= base_url('lomba-detail.php?id=' . $lomba['id']) ?>" class="btn btn-outline-navy w-100 fw-bold py-2 mt-auto">
                                <i class="fa-solid fa-circle-info me-1"></i> Lihat Informasi Lengkap &amp; Juknis <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
