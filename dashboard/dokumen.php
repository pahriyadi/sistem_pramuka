<?php
/**
 * Pusat Unduhan Berkas & Juknis Kontingen - Dashboard Sekolah
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Unduh Dokumen & Juknis Kontingen';
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
$namaAcara = get_setting('nama_acara', 'Kegiatan Racana');
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');

// Handle Direct Download with Counter Increment
if (isset($_GET['download_id'])) {
    $docId = (int)$_GET['download_id'];
    $stmtD = $pdo->prepare("SELECT * FROM dokumen_kegiatan WHERE id = ? AND status_aktif = 'Y'");
    $stmtD->execute([$docId]);
    $doc = $stmtD->fetch();

    if ($doc) {
        $pdo->prepare("UPDATE dokumen_kegiatan SET download_count = download_count + 1 WHERE id = ?")->execute([$docId]);
        
        $filePath = dirname(__DIR__) . '/uploads/dokumen/' . $doc['file_path'];
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
            echo "BERKAS RESMI " . $namaAcara . "\r\n";
            echo $namaRacana . "\r\n";
            echo "========================================================================\r\n\r\n";
            echo "Kontingen : " . $sekolah['nama_sekolah'] . "\r\n";
            echo "Dokumen   : " . $doc['judul_dokumen'] . "\r\n";
            echo "Kategori  : " . strtoupper(str_replace('_', ' ', $doc['kategori'])) . "\r\n\r\n";
            echo "Deskripsi : " . $doc['deskripsi'] . "\r\n\r\n";
            echo "Berkas ini diunduh secara resmi melalui Portal Dashboard Kontingen Sekolah.\r\n";
            exit;
        }
    }
}

// Fetch Filtered Documents
$filterKat = trim($_GET['kategori'] ?? '');
$listDokumen = get_dokumen_kegiatan_list($filterKat, true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($sekolah['nama_sekolah']) ?></title>
    
    <link rel="icon" type="image/png" href="<?= e(get_favicon_url()) ?>">
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
                <h4 class="fw-bold mb-0 text-dark">Unduh Dokumen &amp; Juknis</h4>
                <small class="text-muted">Juklak, Juknis, Surat Delegasi, dan Blangko resmi</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" class="btn btn-scout-navy btn-sm px-3" target="_blank">
                <i class="fa-solid fa-file-pdf me-1 text-warning"></i> Rekap A.01
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- Category Filter Buttons -->
    <div class="custom-card p-3 mb-4 bg-white">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-bold text-dark me-2 small"><i class="fa-solid fa-filter text-primary me-1"></i> Kategori:</span>
            <a href="<?= base_url('dashboard/dokumen.php') ?>" class="btn <?= empty($filterKat) ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                Semua Berkas
            </a>
            <a href="<?= base_url('dashboard/dokumen.php?kategori=juklak_juknis') ?>" class="btn <?= $filterKat === 'juklak_juknis' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                Juklak &amp; Juknis
            </a>
            <a href="<?= base_url('dashboard/dokumen.php?kategori=surat_delegasi') ?>" class="btn <?= $filterKat === 'surat_delegasi' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                Surat Delegasi / Mandat
            </a>
            <a href="<?= base_url('dashboard/dokumen.php?kategori=blangko_formulir') ?>" class="btn <?= $filterKat === 'blangko_formulir' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                Blangko &amp; Izin Ortu
            </a>
            <a href="<?= base_url('dashboard/dokumen.php?kategori=dokumen_lainnya') ?>" class="btn <?= $filterKat === 'dokumen_lainnya' ? 'btn-scout-navy' : 'btn-outline-secondary' ?> btn-sm px-3 fw-semibold">
                Denah &amp; Jadwal
            </a>
        </div>
    </div>

    <!-- Documents List Grid -->
    <div class="row g-4">
        <?php foreach ($listDokumen as $doc): 
            $fileType = strtolower($doc['file_type'] ?: 'pdf');
            $isPdf = in_array($fileType, ['pdf']);
            $isWord = in_array($fileType, ['doc', 'docx']);

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
                        <div class="rounded-3 p-3 text-white flex-shrink-0 shadow-sm d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: <?= $isWord ? 'linear-gradient(135deg, #133e87, #0b2545)' : ($isPdf ? 'linear-gradient(135deg, #800000, #550000)' : 'linear-gradient(135deg, #5d3a00, #3e2600)') ?>;">
                            <i class="fa-solid <?= $isWord ? 'fa-file-word' : ($isPdf ? 'fa-file-pdf' : 'fa-file-zipper') ?> fs-4 text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1 lh-sm"><?= e($doc['judul_dokumen']) ?></h5>
                            <small class="text-muted"><i class="fa-solid fa-calendar-day me-1"></i> Terakhir diperbarui: <?= date('d/m/Y', strtotime($doc['updated_at'] ?? 'now')) ?></small>
                        </div>
                    </div>

                    <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.6;">
                        <?= e($doc['deskripsi']) ?>
                    </p>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                        <span class="text-muted small">
                            <i class="fa-solid fa-download me-1 text-primary"></i> Diunduh <strong><?= (int)$doc['download_count'] ?>x</strong>
                        </span>
                        <a href="<?= base_url('dashboard/dokumen.php?download_id=' . $doc['id']) ?>" class="btn btn-scout-navy btn-sm px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-cloud-arrow-down me-1"></i> Unduh File
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const sidebarToggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar-wrapper');
if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
    });
}
</script>
</body>
</html>
