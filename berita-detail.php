<?php
/**
 * Halaman Detail Pengumuman & Berita Resmi
 * Racana Universitas Samawa (UNSA)
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

$slug = trim($_GET['slug'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM berita_pengumuman WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} elseif (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM berita_pengumuman WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->query("SELECT * FROM berita_pengumuman ORDER BY is_pinned DESC, tgl_post DESC LIMIT 1");
}

$berita = $stmt->fetch();

if (!$berita) {
    set_flash('danger', 'Pengumuman atau berita tidak ditemukan.');
    header('Location: ' . base_url('berita.php'));
    exit;
}

$pageTitle = $berita['judul'];

// Related / Other News
$stmtOther = $pdo->prepare("SELECT * FROM berita_pengumuman WHERE id != ? ORDER BY is_pinned DESC, tgl_post DESC LIMIT 5");
$stmtOther->execute([$berita['id']]);
$otherNews = $stmtOther->fetchAll();

$bannerUrl = get_berita_banner_url($berita['gambar']);
$currentUrl = base_url('berita-detail.php?slug=' . urlencode($berita['slug']));

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section with 4 Brand Colors -->
<section class="page-banner-scout py-5">
    <div class="container py-2">
        <div class="mb-3">
            <a href="<?= base_url('berita.php') ?>" class="btn btn-sm btn-outline-light px-3 fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Semua Warta
            </a>
        </div>
        
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge badge-scout-gold px-3 py-2 fs-6"><?= e($berita['kategori']) ?></span>
            <?php if ($berita['is_pinned'] === 'Y'): ?>
                <span class="badge bg-danger text-white px-2 py-2 fw-bold"><i class="fa-solid fa-thumbtack me-1"></i> Pengumuman Utama</span>
            <?php endif; ?>
            <span class="text-white small fw-medium ms-2"><i class="fa-regular fa-calendar text-warning me-1"></i> <?= format_tanggal_indonesia($berita['tgl_post']) ?></span>
        </div>

        <h1 class="display-6 fw-extrabold text-white mb-3" style="line-height: 1.25; letter-spacing: -0.5px;">
            <?= e($berita['judul']) ?>
        </h1>
        
        <div class="d-flex flex-wrap align-items-center gap-3 text-white small pt-2 border-top border-white border-opacity-15">
            <span><i class="fa-solid fa-user-pen text-warning me-1"></i> Penulis: <strong class="text-white"><?= e($berita['author']) ?></strong></span>
            <span class="text-white-50">&bull;</span>
            <span><i class="fa-solid fa-building-columns text-warning me-1"></i> Dewan Racana Universitas Samawa (UNSA)</span>
        </div>
    </div>
</section>

<!-- Content Body -->
<section class="py-5" style="background-color: #f1f5f9; min-height: 80vh;">
    <div class="container py-2">
        <div class="row g-4 g-lg-5">
            
            <!-- MAIN ARTICLE COLUMN -->
            <div class="col-lg-8">
                <div class="custom-card p-4 p-md-5 bg-white border-0 shadow-sm" style="border-radius: 18px;">
                    
                    <!-- Cover Image Banner if exists -->
                    <?php if (!empty($bannerUrl)): ?>
                        <div class="mb-4 rounded-4 overflow-hidden border shadow-sm" style="max-height: 420px;">
                            <img src="<?= $bannerUrl ?>" alt="<?= e($berita['judul']) ?>" class="w-100 h-100 object-fit-cover">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($berita['ringkasan'])): ?>
                        <div class="p-3 mb-4 rounded-3 border-start border-4 border-scout-gold shadow-sm" style="background-color: #fffbeb;">
                            <p class="lead mb-0 fw-semibold" style="font-size: 1.05rem; color: #1e293b; line-height: 1.7;">
                                <em>"<?= e($berita['ringkasan']) ?>"</em>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="article-content" style="line-height: 1.95; font-size: 1.05rem; color: #1e293b;">
                        <?= nl2br(e($berita['isi_konten'])) ?>
                    </div>

                    <!-- Share & Navigation Footer -->
                    <div class="mt-5 pt-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <a href="<?= base_url('berita.php') ?>" class="btn btn-outline-scout-navy fw-semibold px-4 py-2">
                            <i class="fa-solid fa-arrow-left me-1"></i> Semua Pengumuman
                        </a>

                        <div class="d-flex gap-2">
                            <a href="https://wa.me/?text=<?= urlencode($berita['judul'] . "\n\nBaca selengkapnya di:\n" . $currentUrl) ?>" target="_blank" class="btn btn-success fw-bold shadow-sm px-3 py-2">
                                <i class="fa-brands fa-whatsapp me-1"></i> Bagikan ke WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" class="btn btn-outline-primary px-3 py-2" title="Bagikan ke Facebook">
                                <i class="fa-brands fa-facebook"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    
                    <!-- OTHER NEWS WIDGET -->
                    <div class="custom-card p-4 mb-4 bg-white border-0 shadow-sm" style="border-radius: 18px;">
                        <h5 class="fw-bold text-scout-navy mb-3 border-bottom pb-2">
                            <i class="fa-solid fa-bullhorn text-scout-maroon me-2"></i> Pengumuman Terkait
                        </h5>
                        <?php if (empty($otherNews)): ?>
                            <p class="text-muted small mb-0">Belum ada warta lainnya.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($otherNews as $on): ?>
                                    <a href="<?= base_url('berita-detail.php?slug=' . urlencode($on['slug'])) ?>" class="text-decoration-none p-3 rounded-3 border d-block shadow-sm" style="background-color: #f8fafc; transition: all 0.2s ease;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge badge-scout-navy" style="font-size: 0.68rem;"><?= e($on['kategori']) ?></span>
                                            <small class="text-muted fw-medium" style="font-size: 0.72rem;"><i class="fa-regular fa-calendar me-1"></i> <?= format_tanggal_indonesia($on['tgl_post']) ?></small>
                                        </div>
                                        <div class="fw-bold small text-truncate text-scout-navy mt-1" style="font-size: 0.88rem;"><?= e($on['judul']) ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- DOWNLOAD JUKNIS WIDGET -->
                    <div class="custom-card p-4 mb-4 text-white shadow-sm border-0" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 50%, #800000 100%) !important; border-radius: 18px; border-bottom: 3px solid #d97706;">
                        <h6 class="fw-bold mb-2 text-warning"><i class="fa-solid fa-cloud-arrow-down me-2"></i> Berkas &amp; Juknis Resmi</h6>
                        <p class="small mb-3" style="color: rgba(255, 255, 255, 0.90); line-height: 1.6;">
                            Unduh petunjuk teknis perlombaan, format surat permohonan delegasi, dan blangko A.01 kontingen.
                        </p>
                        <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-scout-gold w-100 fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-folder-open me-2"></i> Pusat Unduh Dokumen
                        </a>
                    </div>

                    <!-- REGISTRATION CTA WIDGET -->
                    <div class="custom-card p-4 bg-white text-center border-0 shadow-sm" style="border-radius: 18px;">
                        <div class="rounded-circle bg-scout-maroon text-white d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-sm" style="width: 50px; height: 50px;">
                            <i class="fa-solid fa-school fs-5 text-warning"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Pendaftaran Kontingen Sekolah</h6>
                        <p class="text-muted small mb-3" style="line-height: 1.5;">Daftarkan pangkalan sekolah Anda secara online sebelum batas kuota terpenuhi.</p>
                        <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold w-100 btn-sm fw-bold shadow-sm py-2">
                            <i class="fa-solid fa-user-plus me-1"></i> Registrasi Akun Sekolah
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
