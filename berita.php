<?php
/**
 * Halaman Publik Arsip Pengumuman & Berita Terbaru
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Pengumuman & Berita Terbaru';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

$filterKategori = trim($_GET['kategori'] ?? '');
$searchQuery    = trim($_GET['q'] ?? '');
$namaBrand = get_setting('nama_brand', 'RACANA UNSA');
$waNumber = get_whatsapp_number();

$sql = "SELECT * FROM berita_pengumuman WHERE 1=1";
$params = [];

if (!empty($filterKategori)) {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}
if (!empty($searchQuery)) {
    $sql .= " AND (judul LIKE ? OR isi_konten LIKE ? OR author LIKE ?)";
    $likeQ = '%' . $searchQuery . '%';
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
}

$sql .= " ORDER BY is_pinned DESC, tgl_post DESC, id DESC";
$stmtBerita = $pdo->prepare($sql);
$stmtBerita->execute($params);
$daftarBerita = $stmtBerita->fetchAll();

// Categories count for badges
$stmtCat = $pdo->query("SELECT kategori, COUNT(*) as total FROM berita_pengumuman GROUP BY kategori");
$catCounts = $stmtCat->fetchAll(PDO::FETCH_KEY_PAIR);
$totalAll = array_sum($catCounts);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section with 4 Brand Colors -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-bullhorn me-1"></i> INFORMASI RESMI</span>
        <h1 class="mb-2">Pengumuman &amp; Berita</h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Surat edaran, agenda kegiatan, petunjuk teknis, dan warta <?= e($namaBrand) ?>.
        </p>
    </div>
</section>

<!-- Main Content -->
<section class="py-5 bg-light min-vh-100">
    <div class="container py-3">

        <!-- Search & Filter Bar -->
        <div class="custom-card p-4 mb-4 bg-white shadow-sm border">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-lg-6 col-md-12">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari judul berita, materi juknis, atau pengumuman..." value="<?= e($searchQuery) ?>">
                        <?php if (!empty($searchQuery) || !empty($filterKategori)): ?>
                            <a href="<?= base_url('berita.php') ?>" class="btn btn-outline-secondary" title="Reset Pencarian">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4 col-md-8">
                    <select name="kategori" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kategori (<?= $totalAll ?>)</option>
                        <option value="Pengumuman" <?= $filterKategori === 'Pengumuman' ? 'selected' : '' ?>>Pengumuman Resmi (<?= $catCounts['Pengumuman'] ?? 0 ?>)</option>
                        <option value="Juknis" <?= $filterKategori === 'Juknis' ? 'selected' : '' ?>>Petunjuk Teknis / Juknis (<?= $catCounts['Juknis'] ?? 0 ?>)</option>
                        <option value="Agenda" <?= $filterKategori === 'Agenda' ? 'selected' : '' ?>>Agenda &amp; Jadwal TM (<?= $catCounts['Agenda'] ?? 0 ?>)</option>
                        <option value="Berita" <?= $filterKategori === 'Berita' ? 'selected' : '' ?>>Berita Kegiatan (<?= $catCounts['Berita'] ?? 0 ?>)</option>
                        <option value="Informasi" <?= $filterKategori === 'Informasi' ? 'selected' : '' ?>>Informasi Umum (<?= $catCounts['Informasi'] ?? 0 ?>)</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <button type="submit" class="btn btn-scout-navy w-100 fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- News Grid -->
        <div class="row g-4">
            <?php if (empty($daftarBerita)): ?>
                <div class="col-12">
                    <div class="custom-card p-5 text-center bg-white shadow-sm">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-4 mb-3" style="width: 80px; height: 80px;">
                            <i class="fa-solid fa-newspaper fs-1 text-muted"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Tidak Ada Pengumuman yang Ditemukan</h4>
                        <p class="text-muted">Coba ubah kata kunci pencarian atau pilih kategori pengumuman lainnya.</p>
                        <a href="<?= base_url('berita.php') ?>" class="btn btn-scout-navy px-4 py-2 mt-2">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Tampilkan Semua Pengumuman
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($daftarBerita as $b): ?>
                    <?php
                    $isPinned = ($b['is_pinned'] === 'Y');
                    $catBadge = 'badge bg-scout-navy';
                    if ($b['kategori'] === 'Juknis') {
                        $catBadge = 'badge bg-scout-maroon';
                    } elseif ($b['kategori'] === 'Agenda') {
                        $catBadge = 'badge bg-scout-brown';
                    } elseif ($b['kategori'] === 'Pengumuman') {
                        $catBadge = 'badge bg-scout-gold text-dark';
                    }

                    $bannerUrl = get_berita_banner_url($b['gambar']);
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="custom-card p-0 h-100 bg-white d-flex flex-column border shadow-sm overflow-hidden <?= $isPinned ? 'border-warning border-2' : '' ?>">
                            
                            <!-- Card Image Banner / Fallback Graphic -->
                            <div class="position-relative" style="height: 180px; overflow: hidden; background: linear-gradient(135deg, #07172b 0%, #0b2545 50%, #800000 100%);">
                                <?php if (!empty($bannerUrl)): ?>
                                    <img src="<?= $bannerUrl ?>" alt="<?= e($b['judul']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white text-center p-4">
                                        <div>
                                            <i class="fa-solid fa-bullhorn fs-1 text-warning mb-2"></i>
                                            <div class="fw-bold small text-white"><?= e($namaBrand) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Category Badge Overlay -->
                                <div class="position-absolute top-0 start-0 m-3 d-flex gap-2">
                                    <span class="<?= $catBadge ?> px-3 py-2 shadow-sm"><?= e($b['kategori']) ?></span>
                                    <?php if ($isPinned): ?>
                                        <span class="badge bg-danger text-white px-2 py-2 shadow-sm"><i class="fa-solid fa-thumbtack me-1"></i> Utama</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center text-muted small mb-2" style="font-size: 0.78rem;">
                                    <span><i class="fa-solid fa-user-pen text-warning me-1"></i> <?= e($b['author']) ?></span>
                                    <span><i class="fa-regular fa-calendar me-1"></i> <?= format_tanggal_indonesia($b['tgl_post']) ?></span>
                                </div>

                                <h5 class="fw-bold mb-2 text-dark" style="font-size: 1.1rem; line-height: 1.4;">
                                    <a href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>" class="text-dark text-decoration-none hover-primary">
                                        <?= e($b['judul']) ?>
                                    </a>
                                </h5>

                                <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.6; font-size: 0.88rem;">
                                    <?= e($b['ringkasan']) ?>
                                </p>

                                <div class="pt-3 border-top mt-auto d-flex justify-content-between align-items-center">
                                    <a href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>" class="btn btn-scout-navy btn-sm fw-bold px-3 py-2">
                                        Baca Selengkapnya <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                    <a href="https://wa.me/?text=<?= urlencode($b['judul'] . ' - ' . base_url('berita-detail.php?slug=' . $b['slug'])) ?>" target="_blank" class="btn btn-outline-success btn-sm" title="Bagikan ke WhatsApp">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Helpdesk & Info Box -->
        <div class="mt-5 p-4 rounded-4 text-white text-center shadow-lg" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 45%, #800000 85%, #3e2600 100%);">
            <h4 class="fw-bold mb-2">Butuh Informasi Tambahan Terkait Kegiatan?</h4>
            <p class="text-light text-opacity-75 mb-4">Tim Panitia Kesekretariatan <?= e($namaBrand) ?> siap melayani pertanyaan seputar pendaftaran, juknis, dan administrasi kontingen.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-scout-gold fw-bold px-4 py-2">
                    <i class="fa-solid fa-file-arrow-down me-1"></i> Unduh Berkas Juknis &amp; Delegasi
                </a>
                <?php if ($waNumber !== ''): ?>
                    <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light px-4 py-2">
                        <i class="fa-brands fa-whatsapp me-1 text-warning"></i> Chat Helpdesk WhatsApp
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
