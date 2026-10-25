<?php
/**
 * Halaman Detail Informasi & Juknis Cabang Mata Lomba Aktif
 * Racana Universitas Samawa (UNSA)
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

$lombaId = (int)($_GET['id'] ?? 0);
$kodeLomba = trim($_GET['kode'] ?? '');

if ($lombaId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM mata_lomba WHERE id = ? AND status_aktif = 'Y' LIMIT 1");
    $stmt->execute([$lombaId]);
} elseif (!empty($kodeLomba)) {
    $stmt = $pdo->prepare("SELECT * FROM mata_lomba WHERE kode_lomba = ? AND status_aktif = 'Y' LIMIT 1");
    $stmt->execute([$kodeLomba]);
} else {
    // Default to first active competition
    $stmt = $pdo->query("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' ORDER BY id ASC LIMIT 1");
}

$lomba = $stmt->fetch();

if (!$lomba) {
    set_flash('danger', 'Informasi cabang lomba tidak ditemukan atau sedang dinonaktifkan.');
    header('Location: ' . base_url('lomba.php'));
    exit;
}

$pageTitle = 'Informasi & Juknis ' . $lomba['nama_lomba'];
$namaUniversitas = get_setting('nama_universitas', 'Universitas Samawa');
$lokasiKegiatan = get_setting('lokasi_kegiatan', '');

// Count total participants in this competition
$stmtPesertaCount = $pdo->prepare("
    SELECT COUNT(pl.id) 
    FROM peserta_lomba pl 
    JOIN peserta p ON pl.peserta_id = p.id 
    WHERE pl.lomba_id = ? AND p.status = 'aktif'
");
$stmtPesertaCount->execute([$lomba['id']]);
$totalPesertaLomba = (int)$stmtPesertaCount->fetchColumn();

// Fetch other active competitions for sidebar recommendations
$stmtOther = $pdo->prepare("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' AND id != ? ORDER BY id ASC LIMIT 5");
$stmtOther->execute([$lomba['id']]);
$otherLomba = $stmtOther->fetchAll();

$statusPendaftaran = get_setting('status_pendaftaran', 'BUKA');
$namaAcara = get_setting('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-NTB 2026');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header / Hero Detail Lomba -->
<section class="py-4 text-white position-relative" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 45%, #800000 85%, #3e2600 100%) !important; border-bottom: 3px solid var(--scout-gold-main);">
    <div class="container py-2 position-relative" style="z-index: 2;">
        <div class="mb-3">
            <a href="<?= base_url('lomba.php') ?>" class="btn btn-sm btn-outline-light px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Lomba
            </a>
        </div>

        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold font-monospace px-2 py-1">
                        KODE: <?= e($lomba['kode_lomba']) ?>
                    </span>
                    <span class="badge bg-scout-navy px-2 py-1">
                        Kategori: <?= e($lomba['kategori_lomba']) ?>
                    </span>
                    <span class="badge bg-light text-dark px-2 py-1">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> <?= strtoupper($statusPendaftaran) ?>
                    </span>
                </div>

                <h1 class="display-6 fw-extrabold text-white mb-2" style="letter-spacing: -0.5px;">
                    <?= e($lomba['nama_lomba']) ?>
                </h1>

                <p class="text-light text-opacity-90 mb-3 small" style="font-size: 0.95rem; line-height: 1.6;">
                    <?= e($lomba['deskripsi']) ?>
                </p>

                <!-- Quick Metric Badges -->
                <div class="d-flex flex-wrap gap-4 text-white-50 small border-top border-white border-opacity-10 pt-3">
                    <div>
                        <span class="d-block text-warning fw-semibold">Maks. Kuota per Sekolah</span>
                        <strong class="text-white fs-6"><?= $lomba['max_peserta_per_sekolah'] ?> Orang / Kontingen</strong>
                    </div>
                    <div>
                        <span class="d-block text-warning fw-semibold">Lokasi Perlombaan</span>
                        <strong class="text-white fs-6"><?= e($lomba['lokasi_lomba']) ?></strong>
                    </div>
                    <div>
                        <span class="d-block text-warning fw-semibold">Total Pendaftar Saat Ini</span>
                        <strong class="text-white fs-6"><?= $totalPesertaLomba ?> Peserta</strong>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="p-4 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15 text-center text-lg-start">
                    <h6 class="text-warning fw-bold text-uppercase small mb-2"><i class="fa-solid fa-calendar-day me-1"></i> Jadwal Pelaksanaan</h6>
                    <p class="text-white mb-3 fw-semibold small"><?= e($lomba['jadwal_lomba'] ?: 'Jadwal belum ditentukan') ?></p>

                    <h6 class="text-warning fw-bold text-uppercase small mb-2"><i class="fa-solid fa-user-tie me-1"></i> Penanggung Jawab</h6>
                    <p class="text-white mb-3 small"><?= e($lomba['penanggung_jawab']) ?></p>

                    <?php if (!empty($lomba['kontak_pj'])): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lomba['kontak_pj']) ?>?text=Halo%20PJ%20Lomba%20<?= urlencode($lomba['nama_lomba']) ?>,%20saya%20ingin%20bertanya%20mengenai%20juknis%20lomba" target="_blank" class="btn btn-success w-100 btn-sm fw-bold mb-2">
                            <i class="fa-brands fa-whatsapp me-1"></i> Hubungi PJ Lomba (WhatsApp)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Detail Content -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="row g-5">
            <!-- Left: Full Technical Guidelines -->
            <div class="col-lg-8">
                <!-- 1. JUKNIS LENGKAP -->
                <div class="custom-card p-4 p-md-5 mb-4">
                    <h4 class="fw-bold text-dark mb-4 border-bottom pb-3">
                        <i class="fa-solid fa-file-lines text-success me-2"></i> Petunjuk Teknis (Juknis) Lengkap
                    </h4>

                    <div class="text-muted mb-4" style="line-height: 1.8;">
                        <?php if (!empty($lomba['juknis_lengkap'])): ?>
                            <?= nl2br(e($lomba['juknis_lengkap'])) ?>
                        <?php else: ?>
                            <p><?= e($lomba['juknis_singkat'] ?: 'Petunjuk teknis mengikuti ketentuan umum kepramukaan penegak.') ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- 2. KRITERIA PENILAIAN -->
                    <h5 class="fw-bold text-dark mt-5 mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-list-check text-warning me-2"></i> Kriteria &amp; Bobot Penilaian
                    </h5>
                    
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <?php if (!empty($lomba['kriteria_penilaian'])): ?>
                            <div class="text-muted small" style="line-height: 1.8;">
                                <?= nl2br(e($lomba['kriteria_penilaian'])) ?>
                            </div>
                        <?php else: ?>
                            <ul class="text-muted small ps-3 mb-0">
                                <li>Kerapian dan ketepatan teknik kepramukaan (40%)</li>
                                <li>Kekompakan dan kedisiplinan tim (30%)</li>
                                <li>Kreativitas dan estetika penampilan (30%)</li>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- 3. ALAT DAN BAHAN -->
                    <h5 class="fw-bold text-dark mt-4 mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-toolbox text-primary me-2"></i> Ketentuan Alat &amp; Perlengkapan
                    </h5>

                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <?php if (!empty($lomba['alat_bahan'])): ?>
                            <div class="text-muted small" style="line-height: 1.8;">
                                <?= nl2br(e($lomba['alat_bahan'])) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Perlengkapan standar seragam pramuka lengkap dan alat bantu lomba sesuai cabang.</p>
                        <?php endif; ?>
                    </div>

                    <!-- 4. LOKASI DAN FASILITAS -->
                    <h5 class="fw-bold text-dark mt-4 mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-map-location-dot text-danger me-2"></i> Lokasi &amp; Fasilitas Pelaksanaan
                    </h5>
                    <p class="text-muted small">
                        Perlombaan dilaksanakan di <strong><?= e($lomba['lokasi_lomba']) ?></strong>, <?= e($lokasiKegiatan ?: $namaUniversitas) ?>. Area perlombaan dilengkapi dengan dewan juri bersertifikat, pos kesehatan medis, serta panitia lapangan.
                    </p>
                </div>

                <!-- Call to Action Box -->
                <div class="p-4 rounded-4 text-white text-center" style="background: linear-gradient(135deg, #0d5c3a 0%, #073822 100%);">
                    <h4 class="fw-bold mb-2">Ingin Mendaftarkan Kontingen untuk Lomba Ini?</h4>
                    <p class="text-light text-opacity-75 small mb-4">
                        Setiap pangkalan sekolah dapat memilih mata lomba ini saat mengisi formulir pendaftaran peserta kontingen di Portal Sekolah.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <?php if (is_logged_in()): ?>
                            <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-warning fw-bold px-4 py-2">
                                <i class="fa-solid fa-user-plus me-1"></i> Buka Dashboard &amp; Daftarkan Peserta
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('auth/register.php') ?>" class="btn btn-warning fw-bold px-4 py-2">
                                <i class="fa-solid fa-school me-1"></i> Registrasi Akun Sekolah Sekarang
                            </a>
                            <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-light px-4 py-2">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Login Portal Kontingen
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar: Info Summary & Other Competitions -->
            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <!-- Ringkasan Singkat Box -->
                    <div class="custom-card p-4 mb-4">
                        <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-circle-info text-success me-2"></i> Ringkasan Lomba
                        </h5>
                        <ul class="list-unstyled small d-flex flex-column gap-3 mb-0">
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Kode Lomba:</span>
                                <strong class="font-monospace text-dark"><?= e($lomba['kode_lomba']) ?></strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Kategori Peserta:</span>
                                <strong class="text-dark"><?= e($lomba['kategori_lomba']) ?></strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Maksimal per Gudep:</span>
                                <strong class="text-dark"><?= $lomba['max_peserta_per_sekolah'] ?> Orang</strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Jadwal:</span>
                                <strong class="text-dark text-end"><?= e($lomba['jadwal_lomba']) ?></strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Biaya Pendaftaran:</span>
                                <span class="badge bg-success">Gratis / Termasuk Kontingen</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Penanggung Jawab:</span>
                                <strong class="text-dark"><?= e($lomba['penanggung_jawab']) ?></strong>
                            </li>
                        </ul>
                    </div>

                    <!-- Rekomendasi Lomba Aktif Lainnya -->
                    <?php if (!empty($otherLomba)): ?>
                        <div class="custom-card p-4">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fa-solid fa-trophy text-warning me-2"></i> Cabang Lomba Lainnya
                            </h6>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($otherLomba as $ol): ?>
                                    <a href="<?= base_url('lomba-detail.php?id=' . $ol['id']) ?>" class="p-2 px-3 rounded-3 bg-light text-decoration-none d-flex justify-content-between align-items-center hover-card">
                                        <div>
                                            <div class="fw-bold text-dark small"><?= e($ol['nama_lomba']) ?></div>
                                            <small class="text-muted font-monospace"><?= e($ol['kode_lomba']) ?> &bull; Max <?= $ol['max_peserta_per_sekolah'] ?> Org</small>
                                        </div>
                                        <i class="fa-solid fa-angle-right text-muted small"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 pt-2 border-top text-center">
                                <a href="<?= base_url('lomba.php') ?>" class="small text-success fw-bold text-decoration-none">
                                    Lihat Semua Cabang Lomba <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
