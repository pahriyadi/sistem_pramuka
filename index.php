<?php
/**
 * Portal Utama - Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Portal Informasi & Pendaftaran Online';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();

// 1. Mata Lomba Aktif (Ambil Ringkas untuk Kartu Lomba Aktif di Index)
$stmtLomba = $pdo->query("
    SELECT m.*, COUNT(pl.id) as total_peserta_lomba
    FROM mata_lomba m
    LEFT JOIN peserta_lomba pl ON m.id = pl.lomba_id
    WHERE m.status_aktif = 'Y' 
    GROUP BY m.id
    ORDER BY m.id ASC
");
$daftarLomba = $stmtLomba->fetchAll();

// 2. Dokumen Unduhan Utama (Juklak, Juknis, Surat Delegasi, Blangko A.01)
$daftarDokumen = get_dokumen_kegiatan_list('', true);
$ringkasanDokumen = array_slice($daftarDokumen, 0, 4);

// 3. Berita & Pengumuman Terbaru
$stmtBerita = $pdo->query("SELECT * FROM berita_pengumuman ORDER BY is_pinned DESC, tgl_post DESC LIMIT 3");
$daftarBerita = $stmtBerita->fetchAll();

// 4. Statistik Singkat Real-Time
$totalSekolah = (int)$pdo->query("SELECT COUNT(*) FROM sekolah")->fetchColumn();
$totalPeserta = (int)$pdo->query("SELECT COUNT(*) FROM peserta WHERE status = 'aktif'")->fetchColumn();
$totalLomba = count($daftarLomba);
$quotaRows = $pdo->query("SELECT kode_kategori, nama_kategori, kuota_max FROM kategori_peserta ORDER BY urutan ASC")->fetchAll();
$quotaByCode = [];
foreach ($quotaRows as $quotaRow) {
    $quotaByCode[$quotaRow['kode_kategori']] = [
        'name' => $quotaRow['nama_kategori'],
        'quota' => (int)$quotaRow['kuota_max'],
    ];
}
$totalKuota = array_sum(array_column($quotaByCode, 'quota'));
$kuotaPutra = $quotaByCode['BAR_PUTRA']['quota'] ?? 0;
$kuotaPutri = $quotaByCode['BAR_PUTRI']['quota'] ?? 0;
$kuotaPembinaPa = $quotaByCode['BINDAMPING_PA']['quota'] ?? 0;
$kuotaPembinaPi = $quotaByCode['BINDAMPING_PI']['quota'] ?? 0;

$statusPendaftaran = get_setting('status_pendaftaran', 'BUKA');
$namaAcara = get_setting('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-NTB 2026');
$temaAcara = get_setting('tema_acara', 'Satyaku Kudarmakan, Darmaku Kubaktikan Demi Kejayaan Tana Samawa');
$tglKemah = get_setting('tgl_pelaksanaan_kemah', '15 - 19 November 2026');
$lokasiBuper = get_setting('lokasi_kegiatan', 'Bumi Perkemahan Kampus Universitas Samawa (UNSA)');
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$namaUniversitas = get_setting('nama_universitas', 'Universitas Samawa');
$gudep = get_setting('gudep_putra', '01.089') . ' - ' . get_setting('gudep_putri', '01.090');
$waNumber = get_whatsapp_number();
$deskripsiPortal = get_setting('deskripsi_portal', 'Pusat informasi kegiatan perkemahan, petunjuk teknis lomba kepramukaan, dan pendaftaran online kontingen pangkalan sekolah.');
$profilRacana = get_setting('profil_racana', '');
$visiRacana = get_setting('visi_racana', '');
$misiRacana = preg_split('/\r\n|\r|\n/', get_setting('misi_racana', '')) ?: [];
$namaMaskot = get_setting('nama_maskot', 'Si Praja UNSA');
$deskripsiMaskot = get_setting('deskripsi_maskot', '');

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION: KEMAH BAKTI & LOMBA PRAMUKA PENEGAK -->
<section class="hero-portal">
    <div class="hero-glow hero-glow-1"></div>
    <div class="hero-glow hero-glow-2"></div>
    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="badge-hero mb-3 shadow-sm" style="background: rgba(217, 119, 6, 0.28) !important; color: #ffd700 !important; border: 1.5px solid #d97706 !important; font-weight: 800 !important; font-size: 0.85rem !important;">
                    <i class="fa-solid fa-circle-check text-warning"></i>
                    <span style="color: #ffffff !important; letter-spacing: 0.5px;">STATUS PENDAFTARAN: <strong style="color: #ffd700 !important;"><?= strtoupper($statusPendaftaran) ?></strong></span>
                </div>
                <h1 class="display-5 fw-extrabold text-white mb-2" style="letter-spacing: -0.5px; line-height: 1.2;">
                    <?= e($namaAcara) ?>
                </h1>
                <p class="text-warning fw-semibold mb-3" style="font-size: 1.05rem;">
                    "<?= e($temaAcara) ?>"
                </p>
                <p class="mb-4 pe-lg-3 small" style="line-height: 1.7; color: rgba(255, 255, 255, 0.92) !important;">
                    <?= e($deskripsiPortal) ?>
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php if (!is_logged_in()): ?>
                        <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-user-plus me-1"></i> Daftar Akun Sekolah
                        </a>
                        <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-light px-3 py-2 fw-semibold">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login Portal
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('dashboard/index.php') ?>" class="btn btn-scout-gold px-4 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-gauge me-1"></i> Dashboard Kontingen
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-outline-warning text-white border-warning border-opacity-50 px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-file-arrow-down me-1 text-warning"></i> Unduh Juklak
                    </a>
                </div>

                <!-- Event Details Mini Bar -->
                <div class="row g-3 pt-3 border-top border-white border-opacity-15">
                    <div class="col-sm-4 col-6">
                        <small class="d-block fw-medium" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.85) !important;">Jadwal Kegiatan</small>
                        <span class="fw-bold text-warning small"><?= e($tglKemah) ?></span>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="d-block fw-medium" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.85) !important;">Lokasi Buper</small>
                        <span class="fw-bold text-white small"><?= e($lokasiBuper) ?></span>
                    </div>
                    <div class="col-sm-4 col-12">
                        <small class="d-block fw-medium" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.85) !important;">Kuota Kontingen</small>
                        <span class="fw-bold text-white small"><?= $totalKuota ?> Orang (<?= $kuotaPutra ?> Putra + <?= $kuotaPutri ?> Putri + <?= $kuotaPembinaPa ?> Pembina PA + <?= $kuotaPembinaPi ?> Pembina PI)</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card p-4 shadow-lg border-0" style="border-radius: 22px; background-color: #ffffff !important; color: #1e293b !important; box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.45) !important;">
                    
                    <!-- Card Header: Status Pendaftaran -->
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom" style="border-color: #e2e8f0 !important;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 rounded-3 shadow-sm d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #0b2545 0%, #800000 100%); width: 44px; height: 44px;">
                                <i class="fa-solid fa-clipboard-check text-warning fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0" style="color: #0b2545 !important; font-size: 1.15rem; letter-spacing: -0.3px;">Status Pendaftaran</h5>
                                <div class="fw-bold" style="color: #800000 !important; font-size: 0.76rem; letter-spacing: 0.3px;"><?= e($namaAcara) ?></div>
                            </div>
                        </div>
                        <span class="badge px-3 py-2 fw-bold text-white shadow-sm" style="background-color: <?= $statusPendaftaran === 'BUKA' ? '#16a34a' : '#dc2626' ?> !important; font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-circle-dot me-1"></i> <?= $statusPendaftaran === 'BUKA' ? 'ONLINE / BUKA' : 'DITUTUP' ?>
                        </span>
                    </div>

                    <!-- 3 Metric Boxes -->
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded-3 border" style="background-color: #f0f7ff !important; border-color: #bae6fd !important; border-bottom: 3px solid #0b2545 !important;">
                                <div class="fs-4 fw-extrabold mb-0" style="color: #0b2545 !important; line-height: 1.2; font-weight: 800;"><?= $totalSekolah ?></div>
                                <div class="fw-bold text-uppercase" style="color: #0369a1 !important; font-size: 0.72rem; letter-spacing: 0.5px;">Sekolah</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 border" style="background-color: #fffbeb !important; border-color: #fde68a !important; border-bottom: 3px solid #d97706 !important;">
                                <div class="fs-4 fw-extrabold mb-0" style="color: #78350f !important; line-height: 1.2; font-weight: 800;"><?= $totalPeserta ?></div>
                                <div class="fw-bold text-uppercase" style="color: #b45309 !important; font-size: 0.72rem; letter-spacing: 0.5px;">Peserta</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 border" style="background-color: #fef2f2 !important; border-color: #fecaca !important; border-bottom: 3px solid #800000 !important;">
                                <div class="fs-4 fw-extrabold mb-0" style="color: #800000 !important; line-height: 1.2; font-weight: 800;"><?= $totalLomba ?></div>
                                <div class="fw-bold text-uppercase" style="color: #991b1b !important; font-size: 0.72rem; letter-spacing: 0.5px;">Lomba</div>
                            </div>
                        </div>
                    </div>

                    <!-- Formation Info Box -->
                    <div class="p-3 rounded-3 mb-3 border shadow-sm" style="background-color: #f8fafc !important; border-color: #cbd5e1 !important; border-left: 4px solid #0b2545 !important;">
                        <div class="fw-bold mb-1 small d-flex align-items-center gap-2" style="color: #07172b !important;">
                            <i class="fa-solid fa-circle-check text-success fs-6"></i>
                            <span style="color: #07172b !important; font-weight: 700;">Ketentuan Kuota (<?= $totalKuota ?> Orang per Gudep):</span>
                        </div>
                        <ul class="list-unstyled small mb-0 d-flex flex-column gap-1" style="font-size: 0.82rem;">
                            <li style="color: #1e293b !important;">
                                <i class="fa-solid fa-user-group text-primary me-2"></i><strong style="color: #0b2545 !important;"><?= $kuotaPutra ?> Putra</strong> &bull; <strong style="color: #0b2545 !important;"><?= $kuotaPutri ?> Putri</strong> (Peserta Penegak)
                            </li>
                            <li style="color: #1e293b !important;">
                                <i class="fa-solid fa-user-tie text-danger me-2"></i><strong style="color: #800000 !important;"><?= $kuotaPembinaPa ?> Pembina PA</strong> &bull; <strong style="color: #800000 !important;"><?= $kuotaPembinaPi ?> Pembina PI</strong> (Pendamping)
                            </li>
                        </ul>
                    </div>

                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold w-100 py-2 fw-bold shadow-sm" style="font-size: 0.95rem;">
                        <i class="fa-solid fa-school me-1"></i> Daftar Akun Sekolah
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PUSAT UNDUH DOKUMEN & JUKNIS -->
<section class="py-5 bg-white border-bottom" id="unduh-dokumen">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="section-tag"><i class="fa-solid fa-download me-1"></i> DOKUMEN RESMI</span>
                <h2 class="section-heading mb-1" style="font-size: 1.8rem;">Berkas &amp; Petunjuk Teknis</h2>
                <p class="text-muted mb-0 small">Unduh Juklak, Juknis, dan formulir kelengkapan administrasi kontingen sekolah.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-scout-navy btn-sm fw-bold px-3 py-2">
                    <i class="fa-solid fa-folder-open me-1"></i> Semua Berkas
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($ringkasanDokumen)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-4">
                        <i class="fa-solid fa-info-circle me-1"></i> Dokumen kegiatan sedang dipersiapkan oleh Panitia <?= e(get_setting('nama_brand', 'Racana')) ?>.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($ringkasanDokumen as $doc): ?>
                    <?php
                    $ext = strtolower($doc['file_type'] ?? 'pdf');
                    $iconClass = 'fa-file-pdf text-danger';
                    if (in_array($ext, ['doc', 'docx'])) {
                        $iconClass = 'fa-file-word text-primary';
                    } elseif (in_array($ext, ['zip', 'rar'])) {
                        $iconClass = 'fa-file-zipper text-warning';
                    }

                    $badgeCat = 'badge bg-scout-navy';
                    $labelCat = 'Dokumen Resmi';
                    if ($doc['kategori'] === 'JUKLAK_JUKNIS') {
                        $badgeCat = 'badge bg-scout-maroon';
                        $labelCat = 'Juklak & Juknis';
                    } elseif ($doc['kategori'] === 'SURAT_DELEGASI') {
                        $badgeCat = 'badge bg-scout-navy';
                        $labelCat = 'Surat Delegasi';
                    } elseif ($doc['kategori'] === 'FORMULIR_BLANGKO') {
                        $badgeCat = 'badge bg-scout-gold text-dark';
                        $labelCat = 'Blangko & Form';
                    } elseif ($doc['kategori'] === 'TATA_TERTIB') {
                        $badgeCat = 'badge bg-scout-brown text-white';
                        $labelCat = 'Tata Tertib';
                    }
                    ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="custom-card p-4 h-100 d-flex flex-column border shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="<?= $badgeCat ?>"><?= $labelCat ?></span>
                                <span class="badge bg-light text-muted border font-monospace text-uppercase" style="font-size: 0.72rem;"><?= e($doc['file_type']) ?></span>
                            </div>

                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="p-2 rounded bg-light border text-center flex-shrink-0" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                    <i class="fa-solid <?= $iconClass ?> fs-4"></i>
                                </div>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem; line-height: 1.3;"><?= e($doc['judul_dokumen']) ?></h6>
                            </div>

                            <p class="text-muted small flex-grow-1 mb-3" style="font-size: 0.8rem; line-height: 1.5;">
                                <?= e($doc['deskripsi'] ?: 'Petunjuk dan format resmi panitia kegiatan.') ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto mb-3 text-muted" style="font-size: 0.75rem;">
                                <span><i class="fa-solid fa-hard-drive me-1"></i> <?= e($doc['file_size']) ?></span>
                                <span><i class="fa-solid fa-download me-1 text-scout-navy"></i> <?= (int)$doc['download_count'] ?>x unduh</span>
                            </div>

                            <a href="<?= base_url('unduh-dokumen.php?id=' . $doc['id'] . '&download=1') ?>" class="btn btn-outline-scout-navy btn-sm w-100 fw-bold py-2">
                                <i class="fa-solid fa-download me-1"></i> Unduh Berkas Sekarang
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- PROFILE RACANA UNIVERSITAS SAMAWA (RINGKASAN, NILAI & MASKOT) -->
<section class="py-5 bg-light" id="profil">
    <div class="container py-3">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7">
                <span class="section-tag"><i class="fa-solid fa-shield-halved me-1"></i> PROFIL PANGKALAN</span>
                <h2 class="section-heading mb-1" style="font-size: 1.8rem;"><?= e($namaRacana) ?></h2>
                <h6 class="text-scout-maroon fw-bold mb-3">Gugus Depan <?= e($gudep) ?> &bull; <?= e($namaUniversitas) ?></h6>
                
                <p class="text-muted mb-3 small" style="line-height: 1.7;">
                    <?= nl2br(e($profilRacana)) ?>
                </p>

                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3 border-start border-4 border-scout-navy shadow-sm h-100">
                            <h6 class="fw-bold text-scout-navy mb-1 small"><i class="fa-solid fa-bullseye text-scout-gold me-1"></i> Visi Utama</h6>
                            <p class="text-muted small mb-0" style="font-size: 0.78rem;"><?= e($visiRacana) ?></p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3 border-start border-4 border-scout-maroon shadow-sm h-100">
                            <h6 class="fw-bold text-scout-maroon mb-1 small"><i class="fa-solid fa-handshake-angle text-scout-gold me-1"></i> Misi Bakti</h6>
                            <p class="text-muted small mb-0" style="font-size: 0.78rem;"><?= e($misiRacana[0] ?? '') ?></p>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?= base_url('tentang.php') ?>" class="btn btn-outline-scout-navy btn-sm px-3 py-2 fw-semibold">
                        <i class="fa-solid fa-book-open me-1"></i> Profil Selengkapnya <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <!-- Mascot & Logo Showcase Card -->
            <div class="col-lg-5">
                <div class="custom-card p-4 text-center bg-white shadow-sm border-0" style="border-radius: 20px;">
                    <div class="position-relative d-inline-block my-1">
                        <img src="<?= get_maskot_url() ?>" alt="Maskot <?= e($namaMaskot) ?>" class="img-fluid" style="max-height: 160px; object-fit: contain;">
                    </div>
                    <h6 class="fw-bold text-scout-navy mt-2 mb-1"><?= e($namaMaskot) ?></h6>
                    <p class="text-muted small mb-2" style="font-size: 0.78rem;"><?= e($deskripsiMaskot ?: $namaAcara) ?></p>
                    
                    <div class="d-flex justify-content-center align-items-center gap-3 p-2 bg-light rounded-3 border">
                        <img src="<?= get_logo_url() ?>" alt="Logo Racana" style="max-height: 35px; max-width: 90px; object-fit: contain;">
                        <div class="vr"></div>
                        <img src="<?= get_logo_kegiatan_url() ?>" alt="Logo Giat" style="max-height: 35px; max-width: 90px; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MATA LOMBA AKTIF -->
<section class="py-5 bg-white" id="lomba">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="section-tag"><i class="fa-solid fa-trophy me-1"></i> KOMPETISI</span>
                <h2 class="section-heading mb-1" style="font-size: 1.8rem;">Cabang Mata Lomba</h2>
                <p class="text-muted mb-0 small">Pilih cabang lomba untuk melihat petunjuk teknis dan jadwal kegiatan.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= base_url('lomba.php') ?>" class="btn btn-scout-navy btn-sm fw-bold px-3 py-2">
                    <i class="fa-solid fa-list-check me-1"></i> Semua Lomba (<?= $totalLomba ?>)
                </a>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($daftarLomba as $index => $lomba): 
                $iconClass = !empty($lomba['icon_lomba']) ? $lomba['icon_lomba'] : 'fa-trophy';
            ?>
                <div class="col-lg-3 col-md-6">
                    <div class="custom-card p-3 h-100 d-flex flex-column border shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge badge-scout-navy font-monospace" style="font-size: 0.72rem;">
                                <?= e($lomba['kode_lomba']) ?>
                            </span>
                            <span class="badge bg-warning bg-opacity-25 text-dark small fw-semibold" style="font-size: 0.72rem;">
                                Max <?= $lomba['max_peserta_per_sekolah'] ?> Org
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="text-white rounded-2 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 32px; height: 32px; background: linear-gradient(135deg, #0b2545, #800000) !important;">
                                <i class="fa-solid <?= $iconClass ?> text-warning" style="font-size: 0.85rem;"></i>
                            </div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.92rem;"><?= e($lomba['nama_lomba']) ?></h6>
                        </div>

                        <p class="text-muted small flex-grow-1 mb-2" style="font-size: 0.78rem; line-height: 1.5;">
                            <?= e($lomba['deskripsi']) ?>
                        </p>
                        
                        <div class="pt-2 border-top mt-auto mb-2 text-muted" style="font-size: 0.74rem;">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fa-solid fa-location-pin text-danger me-1"></i> Lokasi:</span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 120px;"><?= e($lomba['lokasi_lomba']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fa-solid fa-calendar-day text-primary me-1"></i> Jadwal:</span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 120px;"><?= e($lomba['jadwal_lomba'] ?: 'Hari Ke-2') ?></span>
                            </div>
                        </div>

                        <a href="<?= base_url('lomba-detail.php?id=' . $lomba['id']) ?>" class="btn btn-outline-scout-navy btn-sm w-100 fw-bold py-1" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-circle-info me-1"></i> Detail Juknis <i class="fa-solid fa-angle-right ms-1"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ALUR PENDAFTARAN KONTINGEN (4 LANGKAH MUDAH) -->
<section class="py-5 bg-light">
    <div class="container py-3">
        <div class="text-center mb-4">
            <span class="section-tag"><i class="fa-solid fa-route me-1"></i> ALUR PENDAFTARAN</span>
            <h2 class="section-heading mb-1" style="font-size: 1.8rem;">4 Langkah Pendaftaran Kontingen</h2>
            <p class="text-muted small mb-0">Proses pendaftaran kontingen berlangsung secara online dan terintegrasi.</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="p-3 rounded-3 bg-white text-center h-100 border-start border-4 border-scout-navy shadow-sm">
                    <div class="badge rounded-circle p-2 mb-2 fs-6 text-white" style="width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; background-color: #0b2545 !important;">1</div>
                    <h6 class="fw-bold mb-1 text-scout-navy">Registrasi Akun</h6>
                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">Buat akun pangkalan dengan Nama Sekolah, Alamat, dan No. WhatsApp.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="p-3 rounded-3 bg-white text-center h-100 border-start border-4 border-scout-gold shadow-sm">
                    <div class="badge rounded-circle p-2 mb-2 fs-6 text-dark" style="width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; background-color: #d97706 !important;">2</div>
                    <h6 class="fw-bold mb-1 text-scout-brown">Autofill Data</h6>
                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">Informasi sekolah otomatis terisi pada formulir pendaftaran peserta.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="p-3 rounded-3 bg-white text-center h-100 border-start border-4 border-scout-maroon shadow-sm">
                    <div class="badge rounded-circle p-2 mb-2 fs-6 text-white" style="width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; background-color: #800000 !important;">3</div>
                    <h6 class="fw-bold mb-1 text-scout-maroon">Input <?= $totalKuota ?> Anggota</h6>
                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">Lengkapi biodata Barisan Putra, Putri, Bindamping, dan pilih lomba.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="p-3 rounded-3 bg-white text-center h-100 border-start border-4 border-scout-brown shadow-sm">
                    <div class="badge rounded-circle p-2 mb-2 fs-6 text-white" style="width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; background-color: #5d3a00 !important;">4</div>
                    <h6 class="fw-bold mb-1 text-scout-brown">Cetak ID Card</h6>
                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">Cetak ID Card foto dan Formulir Rekap Kontingen A.01 resmi.</p>
                </div>
            </div>
        </div>

        <div class="mt-4 p-4 rounded-4 text-white text-center shadow-sm" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 45%, #800000 85%, #3e2600 100%);">
            <h5 class="fw-bold mb-1">Siap Mendaftarkan Kontingen Pangkalan Anda?</h5>
            <p class="text-light text-opacity-75 mb-3 small">Daftarkan peserta penegak berprestasi sebelum batas kuota terpenuhi.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold btn-sm fw-bold px-3 py-2">
                    <i class="fa-solid fa-user-plus me-1"></i> Daftar Akun Sekolah
                </a>
                <?php if ($waNumber !== ''): ?>
                    <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm px-3 py-2">
                        <i class="fa-brands fa-whatsapp me-1 text-warning"></i> Hubungi Helpdesk
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- PENGUMUMAN & BERITA RESMI -->
<?php if (!empty($daftarBerita)): ?>
<section class="py-5 bg-white" id="berita">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="section-tag"><i class="fa-solid fa-bullhorn me-1"></i> INFORMASI</span>
                <h2 class="section-heading mb-0" style="font-size: 1.8rem;">Pengumuman Terbaru</h2>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= base_url('berita.php') ?>" class="btn btn-outline-scout-navy btn-sm fw-bold px-3 py-2">
                    <i class="fa-solid fa-newspaper me-1"></i> Semua Pengumuman
                </a>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($daftarBerita as $berita): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="custom-card p-3 h-100 bg-white d-flex flex-column border shadow-sm">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="badge badge-scout-navy" style="font-size: 0.72rem;"><?= e($berita['kategori']) ?></span>
                            <small class="text-muted" style="font-size: 0.74rem;"><i class="fa-regular fa-calendar me-1"></i> <?= format_tanggal_indonesia($berita['tgl_post']) ?></small>
                        </div>
                        <h6 class="fw-bold mb-2 text-dark" style="font-size: 0.95rem;"><?= e($berita['judul']) ?></h6>
                        <p class="text-muted small flex-grow-1 mb-2" style="font-size: 0.78rem; line-height: 1.5;">
                            <?= e($berita['ringkasan'] ?: substr(strip_tags($berita['isi_konten']), 0, 100) . '...') ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto" style="font-size: 0.75rem;">
                            <small class="text-muted"><i class="fa-solid fa-user-pen me-1 text-warning"></i> <?= e($berita['author']) ?></small>
                            <a href="<?= base_url('berita-detail.php?id=' . $berita['id']) ?>" class="btn btn-sm btn-link text-scout-navy fw-bold p-0 text-decoration-none">
                                Baca <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
