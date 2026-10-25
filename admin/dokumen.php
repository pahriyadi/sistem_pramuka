<?php
/**
 * Kelola Berkas & Dokumen Kegiatan - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Kelola Dokumen Unduhan';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

$errors = [];
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Handle Actions (Delete / Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi token keamanan CSRF tidak valid.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        // TAMBAH DOKUMEN BARU
        if ($postAction === 'create') {
            $kategori     = trim($_POST['kategori'] ?? 'JUKLAK_JUKNIS');
            $judulDokumen = trim($_POST['judul_dokumen'] ?? '');
            $deskripsi    = trim($_POST['deskripsi'] ?? '');
            $urutan       = (int)($_POST['urutan'] ?? 1);
            $statusAktif  = ($_POST['status_aktif'] ?? 'Y') === 'Y' ? 'Y' : 'N';

            if (empty($judulDokumen)) {
                $errors[] = 'Judul dokumen wajib diisi.';
            }

            $fileName = '';
            $fileType = 'pdf';
            $fileSize = '0 KB';

            if (!empty($_FILES['dokumen_file']['name']) && $_FILES['dokumen_file']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = upload_dokumen_kegiatan($_FILES['dokumen_file']);
                if ($uploadRes['status']) {
                    $fileName = $uploadRes['filename'];
                    $fileType = $uploadRes['file_type'];
                    $fileSize = $uploadRes['file_size'];
                } else {
                    $errors[] = $uploadRes['message'];
                }
            } else {
                // If no file uploaded, use standard placeholder name based on category
                $extMap = [
                    'JUKLAK_JUKNIS'    => 'pdf',
                    'SURAT_DELEGASI'   => 'docx',
                    'FORMULIR_BLANGKO' => 'docx',
                    'TATA_TERTIB'      => 'pdf',
                    'LAINNYA'          => 'pdf'
                ];
                $fileType = $extMap[$kategori] ?? 'pdf';
                $cleanTitle = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $judulDokumen));
                $fileName = 'dokumen_' . $cleanTitle . '.' . $fileType;
                $fileSize = '500 KB';
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO dokumen_kegiatan (kategori, judul_dokumen, deskripsi, file_path, file_type, file_size, status_aktif, urutan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$kategori, $judulDokumen, $deskripsi, $fileName, $fileType, $fileSize, $statusAktif, $urutan]);
                    set_flash('success', 'Dokumen "' . $judulDokumen . '" berhasil ditambahkan!');
                    header('Location: ' . base_url('admin/dokumen.php'));
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Gagal menyimpan dokumen: ' . $e->getMessage();
                }
            }
        }

        // EDIT DOKUMEN
        if ($postAction === 'update') {
            $docId        = (int)($_POST['doc_id'] ?? 0);
            $kategori     = trim($_POST['kategori'] ?? 'JUKLAK_JUKNIS');
            $judulDokumen = trim($_POST['judul_dokumen'] ?? '');
            $deskripsi    = trim($_POST['deskripsi'] ?? '');
            $urutan       = (int)($_POST['urutan'] ?? 1);
            $statusAktif  = ($_POST['status_aktif'] ?? 'Y') === 'Y' ? 'Y' : 'N';

            if (empty($judulDokumen) || $docId <= 0) {
                $errors[] = 'Data dokumen tidak lengkap.';
            }

            // Check if existing
            $stmtEx = $pdo->prepare("SELECT * FROM dokumen_kegiatan WHERE id = ?");
            $stmtEx->execute([$docId]);
            $existing = $stmtEx->fetch();

            if (!$existing) {
                $errors[] = 'Dokumen tidak ditemukan.';
            }

            if (empty($errors)) {
                $fileName = $existing['file_path'];
                $fileType = $existing['file_type'];
                $fileSize = $existing['file_size'];

                // Check if replacing file
                if (!empty($_FILES['dokumen_file']['name']) && $_FILES['dokumen_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadRes = upload_dokumen_kegiatan($_FILES['dokumen_file']);
                    if ($uploadRes['status']) {
                        // Remove old physical file if exists
                        $oldPath = dirname(__DIR__) . '/uploads/dokumen/' . $existing['file_path'];
                        if (file_exists($oldPath) && is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                        $fileName = $uploadRes['filename'];
                        $fileType = $uploadRes['file_type'];
                        $fileSize = $uploadRes['file_size'];
                    } else {
                        $errors[] = $uploadRes['message'];
                    }
                }

                if (empty($errors)) {
                    try {
                        $stmtUp = $pdo->prepare("UPDATE dokumen_kegiatan SET kategori = ?, judul_dokumen = ?, deskripsi = ?, file_path = ?, file_type = ?, file_size = ?, status_aktif = ?, urutan = ? WHERE id = ?");
                        $stmtUp->execute([$kategori, $judulDokumen, $deskripsi, $fileName, $fileType, $fileSize, $statusAktif, $urutan, $docId]);
                        set_flash('success', 'Dokumen "' . $judulDokumen . '" berhasil diperbarui!');
                        header('Location: ' . base_url('admin/dokumen.php'));
                        exit;
                    } catch (Exception $e) {
                        $errors[] = 'Gagal memperbarui dokumen: ' . $e->getMessage();
                    }
                }
            }
        }

        // HAPUS DOKUMEN
        if ($postAction === 'delete') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $stmtEx = $pdo->prepare("SELECT * FROM dokumen_kegiatan WHERE id = ?");
            $stmtEx->execute([$docId]);
            $existing = $stmtEx->fetch();

            if ($existing) {
                // Delete physical file
                $oldPath = dirname(__DIR__) . '/uploads/dokumen/' . $existing['file_path'];
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $pdo->prepare("DELETE FROM dokumen_kegiatan WHERE id = ?")->execute([$docId]);
                set_flash('success', 'Dokumen "' . $existing['judul_dokumen'] . '" berhasil dihapus.');
            }
            header('Location: ' . base_url('admin/dokumen.php'));
            exit;
        }

        // TOGGLE STATUS
        if ($postAction === 'toggle_status') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $stmtEx = $pdo->prepare("SELECT status_aktif FROM dokumen_kegiatan WHERE id = ?");
            $stmtEx->execute([$docId]);
            $cur = $stmtEx->fetchColumn();

            if ($cur !== false) {
                $newStatus = ($cur === 'Y') ? 'N' : 'Y';
                $pdo->prepare("UPDATE dokumen_kegiatan SET status_aktif = ? WHERE id = ?")->execute([$newStatus, $docId]);
                set_flash('info', 'Status dokumen berhasil diubah menjadi ' . ($newStatus === 'Y' ? 'Aktif' : 'Nonaktif'));
            }
            header('Location: ' . base_url('admin/dokumen.php'));
            exit;
        }
    }
}

// Filter and Fetch Documents
$filterKategori = $_GET['kategori'] ?? '';
$sql = "SELECT * FROM dokumen_kegiatan WHERE 1=1";
$params = [];
if (!empty($filterKategori)) {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}
$sql .= " ORDER BY urutan ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dokumenList = $stmt->fetchAll();

// Metrics
$totalDocs = count($dokumenList);
$stmtStats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN kategori = 'JUKLAK_JUKNIS' THEN 1 ELSE 0 END) as total_juklak,
    SUM(CASE WHEN kategori = 'SURAT_DELEGASI' THEN 1 ELSE 0 END) as total_delegasi,
    SUM(download_count) as total_download
    FROM dokumen_kegiatan");
$stats = $stmtStats->fetch() ?: ['total' => 0, 'total_juklak' => 0, 'total_delegasi' => 0, 'total_download' => 0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Admin Racana</title>
    <link rel="icon" href="<?= get_favicon_url() ?>" type="image/x-icon">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-scout-navy">Kelola Dokumen Unduhan</h4>
                <small class="text-muted">Master berkas Juklak, Juknis, Surat Delegasi, dan Formulir</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('unduh-dokumen.php') ?>" target="_blank" class="btn btn-outline-scout-navy btn-sm fw-semibold">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Publik
            </a>
            <button class="btn btn-scout-gold btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahDokumen">
                <i class="fa-solid fa-plus me-1"></i> Tambah Dokumen
            </button>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4 shadow-sm">
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Stat Metric Cards (4 Brand Colors) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-navy shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Total Berkas</div>
                        <h3 class="fw-bold text-scout-navy mb-0"><?= (int)($stats['total'] ?? 0) ?></h3>
                    </div>
                    <div class="p-3 bg-scout-navy bg-opacity-10 text-scout-navy rounded-circle">
                        <i class="fa-solid fa-file-lines fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-maroon shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Juklak &amp; Juknis</div>
                        <h3 class="fw-bold text-scout-maroon mb-0"><?= (int)($stats['total_juklak'] ?? 0) ?></h3>
                    </div>
                    <div class="p-3 bg-scout-maroon bg-opacity-10 text-scout-maroon rounded-circle">
                        <i class="fa-solid fa-book-bookmark fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-gold shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Surat &amp; Blangko</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= (int)($stats['total_delegasi'] ?? 0) ?></h3>
                    </div>
                    <div class="p-3 bg-scout-gold bg-opacity-20 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-envelope-open-text fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-brown shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Total Diunduh</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= number_format((int)($stats['total_download'] ?? 0)) ?>x</h3>
                    </div>
                    <div class="p-3 bg-scout-brown bg-opacity-10 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-download fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Filter Tabs -->
    <div class="custom-card p-3 mb-4 shadow-sm">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="btn-group flex-wrap" role="group">
                <a href="<?= base_url('admin/dokumen.php') ?>" class="btn btn-sm <?= empty($filterKategori) ? 'btn-scout-navy' : 'btn-outline-scout-navy' ?> fw-semibold">
                    Semua (<?= $stats['total'] ?>)
                </a>
                <a href="<?= base_url('admin/dokumen.php?kategori=JUKLAK_JUKNIS') ?>" class="btn btn-sm <?= $filterKategori === 'JUKLAK_JUKNIS' ? 'btn-scout-maroon text-white' : 'btn-outline-scout-maroon' ?> fw-semibold">
                    Juklak &amp; Juknis
                </a>
                <a href="<?= base_url('admin/dokumen.php?kategori=SURAT_DELEGASI') ?>" class="btn btn-sm <?= $filterKategori === 'SURAT_DELEGASI' ? 'btn-scout-navy' : 'btn-outline-scout-navy' ?> fw-semibold">
                    Surat Delegasi / Mandat
                </a>
                <a href="<?= base_url('admin/dokumen.php?kategori=FORMULIR_BLANGKO') ?>" class="btn btn-sm <?= $filterKategori === 'FORMULIR_BLANGKO' ? 'btn-scout-gold text-dark' : 'btn-outline-secondary' ?> fw-semibold">
                    Formulir &amp; Blangko
                </a>
                <a href="<?= base_url('admin/dokumen.php?kategori=TATA_TERTIB') ?>" class="btn btn-sm <?= $filterKategori === 'TATA_TERTIB' ? 'btn-scout-brown text-white' : 'btn-outline-scout-brown' ?> fw-semibold">
                    Tata Tertib Buper
                </a>
            </div>
            <div class="text-muted small">
                Menampilkan <strong><?= count($dokumenList) ?></strong> berkas dokumen
            </div>
        </div>
    </div>

    <!-- Document List Table -->
    <div class="custom-card p-0 shadow-sm overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-scout-navy text-white">
                    <tr>
                        <th class="ps-4" style="width: 50px;">Urut</th>
                        <th>Dokumen / Berkas</th>
                        <th>Kategori</th>
                        <th>Tipe / Ukuran</th>
                        <th class="text-center">Total Unduh</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4" style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dokumenList)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-1 text-secondary opacity-50 d-block mb-3"></i>
                                Belum ada dokumen yang ditambahkan pada kategori ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dokumenList as $doc): ?>
                            <?php
                            $ext = strtolower($doc['file_type'] ?? 'pdf');
                            $badgeColor = 'secondary';
                            $iconClass  = 'fa-file-pdf text-danger';
                            if (in_array($ext, ['doc', 'docx'])) {
                                $iconClass = 'fa-file-word text-primary';
                            } elseif (in_array($ext, ['zip', 'rar'])) {
                                $iconClass = 'fa-file-zipper text-warning';
                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                $iconClass = 'fa-file-excel text-success';
                            }

                            $catBadge = 'badge bg-secondary';
                            $catLabel = $doc['kategori'];
                            if ($doc['kategori'] === 'JUKLAK_JUKNIS') {
                                $catBadge = 'badge bg-scout-maroon text-white';
                                $catLabel = 'Juklak & Juknis';
                            } elseif ($doc['kategori'] === 'SURAT_DELEGASI') {
                                $catBadge = 'badge bg-scout-navy text-white';
                                $catLabel = 'Surat Delegasi';
                            } elseif ($doc['kategori'] === 'FORMULIR_BLANGKO') {
                                $catBadge = 'badge bg-scout-gold text-dark';
                                $catLabel = 'Blangko & Form';
                            } elseif ($doc['kategori'] === 'TATA_TERTIB') {
                                $catBadge = 'badge bg-scout-brown text-white';
                                $catLabel = 'Tata Tertib';
                            }
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?= (int)$doc['urutan'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fa-solid <?= $iconClass ?> fs-3"></i>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?= e($doc['judul_dokumen']) ?></div>
                                            <small class="text-muted d-block text-truncate" style="max-width: 320px;">
                                                <?= e($doc['deskripsi'] ?: 'Tidak ada keterangan tambahan.') ?>
                                            </small>
                                            <code class="text-muted" style="font-size: 0.72rem;"><?= e($doc['file_path']) ?></code>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="<?= $catBadge ?>"><?= $catLabel ?></span></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace text-uppercase"><?= e($doc['file_type']) ?></span>
                                    <small class="text-muted d-block mt-1"><?= e($doc['file_size'] ?: '-') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-15 text-primary fw-bold font-monospace px-2 py-1">
                                        <?= (int)$doc['download_count'] ?> kali
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="post_action" value="toggle_status">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $doc['status_aktif'] === 'Y' ? 'btn-success' : 'btn-outline-danger' ?> py-1 px-2" style="font-size: 0.75rem;" title="Klik untuk mengubah status">
                                            <i class="fa-solid <?= $doc['status_aktif'] === 'Y' ? 'fa-check' : 'fa-xmark' ?> me-1"></i>
                                            <?= $doc['status_aktif'] === 'Y' ? 'Aktif' : 'Nonaktif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('unduh-dokumen.php?id=' . $doc['id'] . '&download=1') ?>" class="btn btn-outline-primary" title="Unduh Berkas">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-warning btn-edit-doc" 
                                            data-id="<?= $doc['id'] ?>"
                                            data-kategori="<?= e($doc['kategori']) ?>"
                                            data-judul="<?= e($doc['judul_dokumen']) ?>"
                                            data-deskripsi="<?= e($doc['deskripsi']) ?>"
                                            data-urutan="<?= $doc['urutan'] ?>"
                                            data-status="<?= $doc['status_aktif'] ?>"
                                            data-filepath="<?= e($doc['file_path']) ?>"
                                            title="Edit Dokumen">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-delete-doc" 
                                            data-id="<?= $doc['id'] ?>"
                                            data-judul="<?= e($doc['judul_dokumen']) ?>"
                                            title="Hapus Dokumen">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH DOKUMEN -->
<div class="modal fade" id="modalTambahDokumen" tabindex="-1" aria-labelledby="modalTambahLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="create">

                <div class="modal-header bg-scout-navy text-white">
                    <h5 class="modal-title fw-bold" id="modalTambahLabel">
                        <i class="fa-solid fa-file-circle-plus me-2 text-scout-gold"></i> Tambah Dokumen Unduhan Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Kategori Dokumen <span class="text-danger">*</span></label>
                            <select name="kategori" class="form-select" required>
                                <option value="JUKLAK_JUKNIS">Juklak &amp; Juknis Perlombaan</option>
                                <option value="SURAT_DELEGASI">Surat Permohonan Delegasi / Mandat</option>
                                <option value="FORMULIR_BLANGKO">Formulir &amp; Blangko Pendaftaran (A.01)</option>
                                <option value="TATA_TERTIB">Tata Tertib &amp; Panduan Bumi Perkemahan</option>
                                <option value="LAINNYA">Dokumen Pendukung Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor Urut Tampilan</label>
                            <input type="number" name="urutan" class="form-control" value="1" min="1" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Judul Dokumen Resmi <span class="text-danger">*</span></label>
                            <input type="text" name="judul_dokumen" class="form-control" placeholder="Contoh: Petunjuk Pelaksanaan & Petunjuk Teknis Kemah 2026" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Deskripsi / Catatan Petunjuk</label>
                            <textarea name="deskripsi" class="form-control" rows="3" placeholder="Jelaskan ringkasan isi dokumen, target peserta, atau petunjuk pengisian..."></textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-scout-navy">Upload Berkas Dokumen</label>
                            <input type="file" name="dokumen_file" class="form-control" accept=".pdf,.doc,.docx,.zip,.rar,.xls,.xlsx">
                            <div class="form-text small">Mendukung format PDF, DOCX, DOC, XLS, XLSX, ZIP. Maksimal 25 MB.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-scout-navy">Status Publikasi</label>
                            <select name="status_aktif" class="form-select">
                                <option value="Y">Aktif (Dapat Diunduh)</option>
                                <option value="N">Nonaktif (Disembunyikan)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-gold px-4 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT DOKUMEN -->
<div class="modal fade" id="modalEditDokumen" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="update">
                <input type="hidden" name="doc_id" id="edit_doc_id" value="">

                <div class="modal-header bg-scout-maroon text-white">
                    <h5 class="modal-title fw-bold" id="modalEditLabel">
                        <i class="fa-solid fa-pen-to-square me-2 text-scout-gold"></i> Edit Dokumen Unduhan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Kategori Dokumen <span class="text-danger">*</span></label>
                            <select name="kategori" id="edit_kategori" class="form-select" required>
                                <option value="JUKLAK_JUKNIS">Juklak &amp; Juknis Perlombaan</option>
                                <option value="SURAT_DELEGASI">Surat Permohonan Delegasi / Mandat</option>
                                <option value="FORMULIR_BLANGKO">Formulir &amp; Blangko Pendaftaran (A.01)</option>
                                <option value="TATA_TERTIB">Tata Tertib &amp; Panduan Bumi Perkemahan</option>
                                <option value="LAINNYA">Dokumen Pendukung Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor Urut Tampilan</label>
                            <input type="number" name="urutan" id="edit_urutan" class="form-control" required min="1">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Judul Dokumen Resmi <span class="text-danger">*</span></label>
                            <input type="text" name="judul_dokumen" id="edit_judul_dokumen" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Deskripsi / Catatan Petunjuk</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-scout-navy">Ganti Berkas Dokumen (Opsional)</label>
                            <input type="file" name="dokumen_file" class="form-control" accept=".pdf,.doc,.docx,.zip,.rar,.xls,.xlsx">
                            <div class="form-text small">Kosongkan jika tidak ingin mengganti berkas yang sudah ada.</div>
                            <small class="text-muted d-block mt-1">Berkas saat ini: <code id="edit_current_file"></code></small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-scout-navy">Status Publikasi</label>
                            <select name="status_aktif" id="edit_status_aktif" class="form-select">
                                <option value="Y">Aktif (Dapat Diunduh)</option>
                                <option value="N">Nonaktif (Disembunyikan)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-maroon px-4 fw-bold text-white">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Perbarui Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalDeleteDokumen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="delete">
                <input type="hidden" name="doc_id" id="delete_doc_id" value="">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Dokumen Unduhan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fa-solid fa-trash-can text-danger fs-1 mb-3"></i>
                    <h6 class="fw-bold text-dark">Apakah Anda yakin ingin menghapus dokumen ini?</h6>
                    <p class="text-muted small mb-0" id="delete_doc_name"></p>
                    <p class="text-danger small mt-2 fw-semibold">Tindakan ini permanen dan berkas fisik akan dihapus dari server.</p>
                </div>
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal Binding
    const editModal = new bootstrap.Modal(document.getElementById('modalEditDokumen'));
    document.querySelectorAll('.btn-edit-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_doc_id').value = this.dataset.id;
            document.getElementById('edit_kategori').value = this.dataset.kategori;
            document.getElementById('edit_judul_dokumen').value = this.dataset.judul;
            document.getElementById('edit_deskripsi').value = this.dataset.deskripsi;
            document.getElementById('edit_urutan').value = this.dataset.urutan;
            document.getElementById('edit_status_aktif').value = this.dataset.status;
            document.getElementById('edit_current_file').innerText = this.dataset.filepath;
            editModal.show();
        });
    });

    // Delete Modal Binding
    const deleteModal = new bootstrap.Modal(document.getElementById('modalDeleteDokumen'));
    document.querySelectorAll('.btn-delete-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_doc_id').value = this.dataset.id;
            document.getElementById('delete_doc_name').innerText = '"' + this.dataset.judul + '"';
            deleteModal.show();
        });
    });
});
</script>
</body>
</html>
