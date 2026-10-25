<?php
/**
 * Profil & Struktur Racana Universitas Samawa
 */
$pageTitle = 'Profil & Struktur Racana';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$namaBrand = get_setting('nama_brand', 'RACANA UNSA');
$namaUniversitas = get_setting('nama_universitas', 'Universitas Samawa');
$gudep = get_setting('gudep_putra', '01.089') . ' - ' . get_setting('gudep_putri', '01.090');
$profilRacana = preg_split('/\r\n|\r|\n/', get_setting('profil_racana', '')) ?: [];
$visiRacana = get_setting('visi_racana', '');
$misiRacana = preg_split('/\r\n|\r|\n/', get_setting('misi_racana', '')) ?: [];
$namaMaskot = get_setting('nama_maskot', 'Si Praja UNSA');
$deskripsiMaskot = get_setting('deskripsi_maskot', '');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section with 4 Brand Colors -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-tree me-1"></i> GUDEP <?= e($gudep) ?></span>
        <h1 class="mb-2"><?= e($namaRacana) ?></h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Pangkalan Pramuka Perguruan Tinggi <?= e($namaUniversitas) ?>.
        </p>
    </div>
</section>

<!-- Content Section -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="row g-4 g-lg-5 align-items-center mb-5">
            <div class="col-lg-7">
                <span class="section-tag"><i class="fa-solid fa-landmark me-1"></i> SEJARAH &amp; FILOSOFI</span>
                <h2 class="section-heading mb-2" style="font-size: 1.8rem;">Latar Belakang &amp; Nilai Dasar</h2>
                <?php foreach ($profilRacana as $paragraf): ?>
                    <?php if (trim($paragraf) !== ''): ?><p class="text-muted"><?= nl2br(e(trim($paragraf))) ?></p><?php endif; ?>
                <?php endforeach; ?>
                <div class="p-3 bg-light rounded-3 border-start border-4 border-scout-navy">
                    <p class="mb-0 fw-semibold text-scout-navy fst-italic">
                        "Mendidik kaum muda menjadi manusia yang berkepribadian, berwatak, berbudi pekerti luhur, beriman dan bertakwa kepada Tuhan Yang Maha Esa, serta berguna bagi nusa dan bangsa."
                    </p>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Visual Identity Card -->
                <div class="custom-card p-4 text-center bg-white shadow-lg border border-scout-navy border-opacity-25" style="border-radius: 20px;">
                    <img src="<?= get_logo_url() ?>" alt="Logo <?= e($namaBrand) ?>" class="img-fluid mb-3" style="max-height: 90px; object-fit: contain;">
                    <h5 class="fw-bold text-scout-navy mb-1">Gugus Depan <?= e($gudep) ?></h5>
                    <p class="text-muted small mb-3">Pangkalan <?= e($namaUniversitas) ?></p>
                    
                    <div class="p-3 bg-light rounded-3 text-start border">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="<?= get_maskot_url() ?>" alt="Maskot" style="height: 55px; width: auto; object-fit: contain;">
                            <div>
                                <strong class="text-scout-maroon d-block" style="font-size: 0.88rem;">Maskot Resmi: <?= e($namaMaskot) ?></strong>
                                <small class="text-muted" style="font-size: 0.75rem;"><?= e($deskripsiMaskot) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="custom-card p-4 bg-light h-100 border-start border-4 border-scout-navy">
                    <h4 class="fw-bold mb-3 text-scout-navy"><i class="fa-solid fa-bullseye text-scout-gold me-2"></i> Visi Racana UNSA</h4>
                    <p class="text-muted">
                        <?= nl2br(e($visiRacana)) ?>
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="custom-card p-4 bg-light h-100 border-start border-4 border-scout-maroon">
                    <h4 class="fw-bold mb-3 text-scout-maroon"><i class="fa-solid fa-handshake-angle text-scout-gold me-2"></i> Misi Racana UNSA</h4>
                    <ul class="text-muted small ps-3 mb-0">
                        <?php foreach ($misiRacana as $misi): ?>
                            <?php if (trim($misi) !== ''): ?><li class="mb-2"><?= e(trim($misi)) ?></li><?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Panca Dharma Racana / Pilar Keunggulan -->
        <div class="text-center my-5">
            <span class="section-tag"><i class="fa-solid fa-gem me-1"></i> PILAR KEUNGGULAN</span>
            <h2 class="section-heading">4 Pilar Utama Pembinaan</h2>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="custom-card p-4 text-center h-100 border-top border-4 border-scout-navy shadow-sm">
                    <div class="feature-icon-wrapper icon-blue mx-auto mb-3">
                        <i class="fa-solid fa-hands-praying"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-scout-navy">Religius &amp; Berakhlak</h5>
                    <p class="text-muted small">Ketakwaan kepada Tuhan YME sebagai landasan moral setiap tindakan dan baktiku.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card p-4 text-center h-100 border-top border-4 border-scout-gold shadow-sm">
                    <div class="feature-icon-wrapper icon-amber mx-auto mb-3">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-scout-brown">Intelektual Muda</h5>
                    <p class="text-muted small">Berwawasan luas, kritis, dan mampu beradaptasi dengan kemajuan teknologi digital.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card p-4 text-center h-100 border-top border-4 border-scout-maroon shadow-sm">
                    <div class="feature-icon-wrapper icon-green mx-auto mb-3">
                        <i class="fa-solid fa-people-carry-box"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-scout-maroon">Bakti Masyarakat</h5>
                    <p class="text-muted small">Turun langsung membantu masyarakat melalui program kemanusiaan &amp; relawan bencana.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="custom-card p-4 text-center h-100 border-top border-4 border-scout-brown shadow-sm">
                    <div class="feature-icon-wrapper icon-purple mx-auto mb-3">
                        <i class="fa-solid fa-mountain-sun"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-scout-brown">Cinta Alam Samawa</h5>
                    <p class="text-muted small">Mencintai dan melestarikan kekayaan alam lingkungan hidup Tana Samawa.</p>
                </div>
            </div>
        </div>

        <!-- Call To Action -->
        <div class="mt-5 p-4 rounded-4 text-white text-center shadow-lg" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 45%, #800000 85%, #3e2600 100%);">
            <h4 class="fw-bold mb-2 text-white">Tertarik Bergabung atau Mendaftarkan Sekolah Anda?</h4>
            <p class="text-white-50 mb-4">Gugus Depan <?= e($namaUniversitas) ?> selalu terbuka untuk silaturahmi dan kemitraan kepramukaan.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold fw-bold px-4 py-2">
                    <i class="fa-solid fa-school me-1"></i> Registrasi Kontingen Sekolah
                </a>
                <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-outline-light px-4 py-2">
                    <i class="fa-solid fa-file-arrow-down me-1 text-warning"></i> Unduh Dokumen &amp; Juknis
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
