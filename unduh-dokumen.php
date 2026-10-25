<?php
/**
 * Pusat Unduhan Berkas & Dokumen Resmi Kegiatan
 * Juklak & Juknis, Surat Delegasi, Blangko Izin, Tata Tertib
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Pusat Unduh Dokumen Resmi & Juknis';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$namaAcara = get_setting('nama_acara', 'Kegiatan Racana');
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$lokasiKegiatan = get_setting('lokasi_kegiatan', get_setting('alamat_racana', ''));

// Handle Direct Download with Counter Increment
$downloadId = (int)($_GET['download_id'] ?? $_GET['download'] ?? $_GET['id'] ?? 0);
if ($downloadId > 0 && (isset($_GET['download_id']) || isset($_GET['download']) || isset($_GET['id']))) {
    $docId = $downloadId;
    $stmtD = $pdo->prepare("SELECT * FROM dokumen_kegiatan WHERE id = ? AND status_aktif = 'Y'");
    $stmtD->execute([$docId]);
    $doc = $stmtD->fetch();

    if ($doc) {
        $pdo->prepare("UPDATE dokumen_kegiatan SET download_count = download_count + 1 WHERE id = ?")->execute([$docId]);
        
        $filePath = __DIR__ . '/uploads/dokumen/' . $doc['file_path'];
        if (file_exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $contentType = 'application/octet-stream';
            if ($ext === 'pdf') $contentType = 'application/pdf';
            elseif ($ext === 'docx' || $ext === 'doc') $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            elseif ($ext === 'zip') $contentType = 'application/zip';

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . basename($doc['file_path']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            // Fallback sample download if file not yet uploaded by admin
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['judul_dokumen']) . '.txt"');
            echo "========================================================================\r\n";
            echo "DOKUMEN RESMI " . $namaAcara . "\r\n";
            echo $namaRacana . "\r\n";
            echo "========================================================================\r\n\r\n";
            echo "Judul Dokumen : " . $doc['judul_dokumen'] . "\r\n";
            echo "Kategori      : " . strtoupper(str_replace('_', ' ', $doc['kategori'])) . "\r\n";
            echo "Deskripsi     : " . $doc['deskripsi'] . "\r\n\r\n";
            echo "Dokumen ini diterbitkan resmi oleh " . $namaRacana . ".\r\n";
            echo "Untuk informasi atau berkas fisik selengkapnya, silakan hubungi Sekretariat Panitia di " . $lokasiKegiatan . ".\r\n";
            exit;
        }
    }
}

// Fetch Filtered Documents
$filterKat = trim($_GET['kategori'] ?? '');
$listDokumen = get_dokumen_kegiatan_list($filterKat, true);

// Stats
$totalDocs = count($listDokumen);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-cloud-arrow-down me-1"></i> DOKUMEN RESMI</span>
        <h1 class="mb-2">Pusat Unduh Dokumen</h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Juklak, Juknis, Surat Delegasi, dan Blangko resmi administrasi kontingen sekolah.
        </p>
    </div>
</section>

<!-- Filter Navigation -->
<section class="py-4 bg-light border-bottom">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn <?= empty($filterKat) ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-layer-group me-1"></i> Semua Dokumen
                </a>
                <a href="<?= base_url('unduh-dokumen.php?kategori=juklak_juknis') ?>" class="btn <?= $filterKat === 'juklak_juknis' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-book-bookmark me-1"></i> Juklak &amp; Juknis
                </a>
                <a href="<?= base_url('unduh-dokumen.php?kategori=surat_delegasi') ?>" class="btn <?= $filterKat === 'surat_delegasi' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-file-signature me-1"></i> Surat Delegasi &amp; Mandat
                </a>
                <a href="<?= base_url('unduh-dokumen.php?kategori=blangko_formulir') ?>" class="btn <?= $filterKat === 'blangko_formulir' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-file-lines me-1"></i> Blangko &amp; Izin Ortu
                </a>
                <a href="<?= base_url('unduh-dokumen.php?kategori=dokumen_lainnya') ?>" class="btn <?= $filterKat === 'dokumen_lainnya' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-map-location-dot me-1"></i> Denah &amp; Rundown
                </a>
            </div>

            <div>
                <a href="<?= base_url('petunjuk-teknis.php') ?>" class="btn btn-outline-navy btn-sm px-3">
                    <i class="fa-solid fa-eye me-1"></i> Baca Juknis Online
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Documents Grid Section -->
<section class="py-5 bg-white">
    <div class="container py-3">
        <?php if (empty($listDokumen)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                <h5 class="fw-bold">Belum Ada Dokumen pada Kategori Ini</h5>
                <p class="small">Silakan pilih kategori dokumen lainnya atau hubungi Panitia Admin.</p>
                <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-scout-navy btn-sm px-4">Tampilkan Semua Dokumen</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($listDokumen as $doc): 
                    $fileType = strtolower($doc['file_type'] ?: 'pdf');
                    $isPdf = in_array($fileType, ['pdf']);
                    $isWord = in_array($fileType, ['doc', 'docx']);
                    $isZip = in_array($fileType, ['zip', 'rar']);

                    $badgeClass = 'badge-scout-navy';
                    $badgeIcon = 'fa-file-shield';
                    $badgeText = 'JUKLAK / JUKNIS';
                    if ($doc['kategori'] === 'surat_delegasi') {
                        $badgeClass = 'badge-scout-gold';
                        $badgeIcon = 'fa-file-signature';
                        $badgeText = 'SURAT DELEGASI';
                    } elseif ($doc['kategori'] === 'blangko_formulir') {
                        $badgeClass = 'badge-scout-maroon';
                        $badgeIcon = 'fa-file-invoice';
                        $badgeText = 'BLANGKO FORMULIR';
                    } elseif ($doc['kategori'] === 'dokumen_lainnya') {
                        $badgeClass = 'badge-scout-brown';
                        $badgeIcon = 'fa-map-pin';
                        $badgeText = 'INFORMASI BUPER';
                    }
                ?>
                    <div class="col-lg-6">
                        <div class="custom-card p-4 h-100 d-flex flex-column border-start border-4 border-<?= $doc['kategori'] === 'surat_delegasi' ? 'warning' : ($doc['kategori'] === 'blangko_formulir' ? 'danger' : 'primary') ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge <?= $badgeClass ?> small">
                                    <i class="fa-solid <?= $badgeIcon ?> me-1"></i> <?= $badgeText ?>
                                </span>
                                <span class="badge bg-light text-dark border font-monospace small">
                                    <?= strtoupper($fileType) ?> &bull; <?= e($doc['file_size'] ?: '1.2 MB') ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="rounded-3 p-3 text-white flex-shrink-0 shadow-sm d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: <?= $isWord ? 'linear-gradient(135deg, #133e87, #0b2545)' : ($isPdf ? 'linear-gradient(135deg, #800000, #550000)' : 'linear-gradient(135deg, #5d3a00, #3e2600)') ?>;">
                                    <i class="fa-solid <?= $isWord ? 'fa-file-word' : ($isPdf ? 'fa-file-pdf' : 'fa-file-zipper') ?> fs-4 text-warning"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-1 lh-sm"><?= e($doc['judul_dokumen']) ?></h5>
                                    <small class="text-muted"><i class="fa-solid fa-calendar-day me-1"></i> Diperbarui: <?= date('d/m/Y', strtotime($doc['updated_at'] ?? 'now')) ?></small>
                                </div>
                            </div>

                            <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.65;">
                                <?= e($doc['deskripsi']) ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                                <span class="text-muted small">
                                    <i class="fa-solid fa-download me-1 text-primary"></i> Telah diunduh <strong><?= (int)$doc['download_count'] ?> kali</strong>
                                </span>
                                <a href="<?= base_url('unduh-dokumen.php?download_id=' . $doc['id']) ?>" class="btn btn-scout-navy btn-sm px-4 fw-bold shadow-sm">
                                    <i class="fa-solid fa-cloud-arrow-down me-1"></i> Unduh Berkas
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Helpdesk Contact Info Banner -->
        <div class="mt-5 p-4 rounded-4 text-white text-center shadow-lg" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 45%, #800000 85%, #3e2600 100%);">
            <h4 class="fw-bold mb-2">Memerlukan Berkas Fisik atau Format Khusus?</h4>
            <p class="text-light text-opacity-75 mb-4">Tim Panitia Pelaksana siap membantu jika Anda memerlukan surat permohonan delegasi resmi bertanda tangan basah atau stempel organisasi.</p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', get_setting('kontak_wa', '085238912345')) ?>?text=Halo%20Sekretariat%20Racana%20UNSA,%20kami%20ingin%20berkonsultasi%20mengenai%20dokumen%20pendaftaran%20kontingen." target="_blank" class="btn btn-scout-gold fw-bold px-4 py-2">
                    <i class="fa-brands fa-whatsapp me-2"></i> Hubungi Sekretariat Panitia
                </a>
                <a href="<?= base_url('auth/register.php') ?>" class="btn btn-outline-light px-4 py-2">
                    <i class="fa-solid fa-school me-2"></i> Daftarkan Kontingen Sekolah
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
