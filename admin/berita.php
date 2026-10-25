<?php
/**
 * Kelola Pengumuman & Berita Terbaru - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Kelola Pengumuman & Berita';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();
$defaultAuthor = 'Dewan ' . get_setting('nama_brand', 'Racana');

$errors = [];

// Handle Actions (Create, Update, Delete, Toggle Pin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi sesi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        // 1. TAMBAH BERITA / PENGUMUMAN BARU
        if ($postAction === 'create') {
            $judul     = trim($_POST['judul'] ?? '');
            $kategori  = trim($_POST['kategori'] ?? 'Pengumuman');
            $ringkasan = trim($_POST['ringkasan'] ?? '');
            $isiKonten = trim($_POST['isi_konten'] ?? '');
            $author    = trim($_POST['author'] ?? $defaultAuthor);
            $tglPost   = trim($_POST['tgl_post'] ?? date('Y-m-d'));
            $isPinned  = ($_POST['is_pinned'] ?? 'N') === 'Y' ? 'Y' : 'N';

            if (empty($judul)) {
                $errors[] = 'Judul berita atau pengumuman wajib diisi.';
            }
            if (empty($isiKonten)) {
                $errors[] = 'Isi konten berita wajib diisi.';
            }

            // Generate unique slug
            $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul), '-'));
            if (empty($baseSlug)) {
                $baseSlug = 'berita-' . time();
            }
            $slug = $baseSlug;
            $counter = 1;
            while (true) {
                $chkSlug = $pdo->prepare("SELECT id FROM berita_pengumuman WHERE slug = ? LIMIT 1");
                $chkSlug->execute([$slug]);
                if (!$chkSlug->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . (++$counter);
            }

            // Handle image upload if provided
            $gambarName = '';
            if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = upload_banner_berita($_FILES['gambar']);
                if ($uploadRes['status']) {
                    $gambarName = $uploadRes['filename'];
                } else {
                    $errors[] = $uploadRes['message'];
                }
            }

            if (empty($ringkasan)) {
                $ringkasan = substr(strip_tags($isiKonten), 0, 160) . '...';
            }

            if (empty($errors)) {
                try {
                    $stmtIn = $pdo->prepare("INSERT INTO berita_pengumuman (judul, slug, kategori, ringkasan, isi_konten, gambar, author, tgl_post, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtIn->execute([$judul, $slug, $kategori, $ringkasan, $isiKonten, $gambarName, $author, $tglPost, $isPinned]);
                    set_flash('success', 'Pengumuman / Berita baru "' . e($judul) . '" berhasil diterbitkan!');
                    header('Location: ' . base_url('admin/berita.php'));
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Gagal menyimpan berita: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT BERITA / PENGUMUMAN
        if ($postAction === 'update') {
            $beritaId  = (int)($_POST['berita_id'] ?? 0);
            $judul     = trim($_POST['judul'] ?? '');
            $kategori  = trim($_POST['kategori'] ?? 'Pengumuman');
            $ringkasan = trim($_POST['ringkasan'] ?? '');
            $isiKonten = trim($_POST['isi_konten'] ?? '');
            $author    = trim($_POST['author'] ?? $defaultAuthor);
            $tglPost   = trim($_POST['tgl_post'] ?? date('Y-m-d'));
            $isPinned  = ($_POST['is_pinned'] ?? 'N') === 'Y' ? 'Y' : 'N';

            if ($beritaId <= 0 || empty($judul)) {
                $errors[] = 'Data berita tidak lengkap.';
            }
            if (empty($isiKonten)) {
                $errors[] = 'Isi konten berita tidak boleh kosong.';
            }

            // Check existing
            $stmtEx = $pdo->prepare("SELECT * FROM berita_pengumuman WHERE id = ?");
            $stmtEx->execute([$beritaId]);
            $existing = $stmtEx->fetch();

            if (!$existing) {
                $errors[] = 'Data berita tidak ditemukan.';
            }

            if (empty($errors)) {
                $gambarName = $existing['gambar'];

                // Handle replacement image upload
                if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $uploadRes = upload_banner_berita($_FILES['gambar']);
                    if ($uploadRes['status']) {
                        // Remove old image if exists
                        if (!empty($existing['gambar'])) {
                            $oldPath = dirname(__DIR__) . '/uploads/berita/' . $existing['gambar'];
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $gambarName = $uploadRes['filename'];
                    } else {
                        $errors[] = $uploadRes['message'];
                    }
                }

                if (empty($ringkasan)) {
                    $ringkasan = substr(strip_tags($isiKonten), 0, 160) . '...';
                }

                if (empty($errors)) {
                    try {
                        $stmtUp = $pdo->prepare("UPDATE berita_pengumuman SET judul = ?, kategori = ?, ringkasan = ?, isi_konten = ?, gambar = ?, author = ?, tgl_post = ?, is_pinned = ? WHERE id = ?");
                        $stmtUp->execute([$judul, $kategori, $ringkasan, $isiKonten, $gambarName, $author, $tglPost, $isPinned, $beritaId]);
                        set_flash('success', 'Pengumuman / Berita "' . e($judul) . '" berhasil diperbarui!');
                        header('Location: ' . base_url('admin/berita.php'));
                        exit;
                    } catch (Exception $e) {
                        $errors[] = 'Gagal memperbarui berita: ' . $e->getMessage();
                    }
                }
            }
        }

        // 3. TOGGLE PIN STATUS
        if ($postAction === 'toggle_pin') {
            $beritaId = (int)($_POST['berita_id'] ?? 0);
            $stmtCur = $pdo->prepare("SELECT is_pinned, judul FROM berita_pengumuman WHERE id = ?");
            $stmtCur->execute([$beritaId]);
            $bData = $stmtCur->fetch();
            if ($bData) {
                $newPin = ($bData['is_pinned'] === 'Y') ? 'N' : 'Y';
                $pdo->prepare("UPDATE berita_pengumuman SET is_pinned = ? WHERE id = ?")->execute([$newPin, $beritaId]);
                set_flash('info', 'Status sematan berita "' . e($bData['judul']) . '" diubah menjadi ' . ($newPin === 'Y' ? 'Disematkan (Pinned)' : 'Standar'));
            }
            header('Location: ' . base_url('admin/berita.php'));
            exit;
        }

        // 4. HAPUS BERITA
        if ($postAction === 'delete') {
            $beritaId = (int)($_POST['berita_id'] ?? 0);
            $stmtEx = $pdo->prepare("SELECT * FROM berita_pengumuman WHERE id = ?");
            $stmtEx->execute([$beritaId]);
            $existing = $stmtEx->fetch();

            if ($existing) {
                if (!empty($existing['gambar'])) {
                    $oldPath = dirname(__DIR__) . '/uploads/berita/' . $existing['gambar'];
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $pdo->prepare("DELETE FROM berita_pengumuman WHERE id = ?")->execute([$beritaId]);
                set_flash('success', 'Pengumuman / Berita "' . e($existing['judul']) . '" berhasil dihapus.');
            }
            header('Location: ' . base_url('admin/berita.php'));
            exit;
        }
    }
}

// Fetch Filter & Search Parameters
$filterKategori = $_GET['kategori'] ?? '';
$searchQuery    = trim($_GET['q'] ?? '');

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

// Statistics Metrics
$stmtStats = $pdo->query("
    SELECT 
        COUNT(*) as total_berita,
        SUM(CASE WHEN is_pinned = 'Y' THEN 1 ELSE 0 END) as total_pinned,
        SUM(CASE WHEN kategori = 'Pengumuman' THEN 1 ELSE 0 END) as total_pengumuman,
        SUM(CASE WHEN kategori = 'Juknis' THEN 1 ELSE 0 END) as total_juknis
    FROM berita_pengumuman
");
$stats = $stmtStats->fetch() ?: ['total_berita' => 0, 'total_pinned' => 0, 'total_pengumuman' => 0, 'total_juknis' => 0];
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
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-scout-navy">Kelola Berita &amp; Pengumuman</h4>
                <small class="text-muted">Publikasi informasi resmi, surat edaran, dan warta kegiatan</small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('berita.php') ?>" target="_blank" class="btn btn-outline-scout-navy btn-sm fw-semibold">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Publik
            </a>
            <button class="btn btn-scout-gold btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahBerita">
                <i class="fa-solid fa-plus me-1"></i> Buat Berita
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
                        <div class="text-muted small fw-semibold">Total Publikasi</div>
                        <h3 class="fw-bold text-scout-navy mb-0"><?= (int)$stats['total_berita'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-navy bg-opacity-10 text-scout-navy rounded-circle">
                        <i class="fa-solid fa-bullhorn fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-maroon shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Disematkan (Pin)</div>
                        <h3 class="fw-bold text-scout-maroon mb-0"><?= (int)$stats['total_pinned'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-maroon bg-opacity-10 text-scout-maroon rounded-circle">
                        <i class="fa-solid fa-thumbtack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-gold shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Kategori Pengumuman</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= (int)$stats['total_pengumuman'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-gold bg-opacity-20 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-newspaper fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-brown shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Juknis &amp; Agenda</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= (int)$stats['total_juknis'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-brown bg-opacity-10 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-calendar-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="custom-card p-3 mb-4 shadow-sm">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-lg-6 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari judul, isi pengumuman, atau nama penulis..." value="<?= e($searchQuery) ?>">
                    <?php if (!empty($searchQuery)): ?>
                        <a href="<?= base_url('admin/berita.php') ?>" class="btn btn-outline-secondary" title="Reset Pencarian">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4 col-md-8">
                <select name="kategori" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <option value="Pengumuman" <?= $filterKategori === 'Pengumuman' ? 'selected' : '' ?>>Kategori: Pengumuman</option>
                    <option value="Juknis" <?= $filterKategori === 'Juknis' ? 'selected' : '' ?>>Kategori: Juknis</option>
                    <option value="Agenda" <?= $filterKategori === 'Agenda' ? 'selected' : '' ?>>Kategori: Agenda</option>
                    <option value="Berita" <?= $filterKategori === 'Berita' ? 'selected' : '' ?>>Kategori: Berita</option>
                    <option value="Informasi" <?= $filterKategori === 'Informasi' ? 'selected' : '' ?>>Kategori: Informasi</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <button type="submit" class="btn btn-scout-navy w-100 fw-bold">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- News Table -->
    <div class="custom-card p-0 shadow-sm overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-scout-navy text-white">
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Judul &amp; Ringkasan Berita</th>
                        <th>Kategori</th>
                        <th>Penulis &amp; Tanggal</th>
                        <th class="text-center">Sematkan</th>
                        <th class="text-end pe-4" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarBerita)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-newspaper fs-1 text-secondary opacity-50 d-block mb-3"></i>
                                Belum ada pengumuman atau berita yang ditambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftarBerita as $idx => $b): ?>
                            <?php
                            $catBadge = 'badge bg-scout-navy';
                            if ($b['kategori'] === 'Juknis') {
                                $catBadge = 'badge bg-scout-maroon';
                            } elseif ($b['kategori'] === 'Agenda') {
                                $catBadge = 'badge bg-scout-brown';
                            } elseif ($b['kategori'] === 'Pengumuman') {
                                $catBadge = 'badge bg-scout-gold text-dark';
                            }
                            ?>
                            <tr class="<?= $b['is_pinned'] === 'Y' ? 'table-warning bg-opacity-25' : '' ?>">
                                <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($b['gambar']) && file_exists(dirname(__DIR__) . '/uploads/berita/' . $b['gambar'])): ?>
                                            <img src="<?= base_url('uploads/berita/' . $b['gambar']) ?>" alt="Banner" class="rounded border flex-shrink-0" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 50px; height: 50px; background: linear-gradient(135deg, #0b2545, #800000);">
                                                <i class="fa-solid fa-bullhorn text-warning fs-5"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-dark mb-0">
                                                <?= e($b['judul']) ?>
                                                <?php if ($b['is_pinned'] === 'Y'): ?>
                                                    <span class="badge bg-danger ms-1" style="font-size: 0.65rem;"><i class="fa-solid fa-thumbtack me-1"></i> Disematkan</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted d-block text-truncate" style="max-width: 400px;">
                                                <?= e($b['ringkasan']) ?>
                                            </small>
                                            <code class="text-muted" style="font-size: 0.72rem;">slug: <?= e($b['slug']) ?></code>
                                        </div>
                                    </div>
                                </td>

                                <td><span class="<?= $catBadge ?>"><?= e($b['kategori']) ?></span></td>

                                <td>
                                    <div class="small fw-semibold text-dark"><i class="fa-solid fa-user-pen text-warning me-1"></i> <?= e($b['author']) ?></div>
                                    <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i> <?= format_tanggal_indonesia($b['tgl_post']) ?></small>
                                </td>

                                <td class="text-center">
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="post_action" value="toggle_pin">
                                        <input type="hidden" name="berita_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $b['is_pinned'] === 'Y' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?> py-1 px-2" style="font-size: 0.75rem;" title="Klik untuk menyematkan/melepas pin">
                                            <i class="fa-solid fa-thumbtack me-1"></i>
                                            <?= $b['is_pinned'] === 'Y' ? 'Pinned' : 'Biasa' ?>
                                        </button>
                                    </form>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('berita-detail.php?id=' . $b['id']) ?>" target="_blank" class="btn btn-outline-info" title="Lihat Tampilan Publik">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary btn-edit-berita"
                                            data-id="<?= $b['id'] ?>"
                                            data-judul="<?= e($b['judul']) ?>"
                                            data-kategori="<?= e($b['kategori']) ?>"
                                            data-ringkasan="<?= e($b['ringkasan']) ?>"
                                            data-konten="<?= e($b['isi_konten']) ?>"
                                            data-author="<?= e($b['author']) ?>"
                                            data-tgl="<?= e($b['tgl_post']) ?>"
                                            data-pinned="<?= e($b['is_pinned']) ?>"
                                            data-gambar="<?= e($b['gambar']) ?>"
                                            title="Edit Berita">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-delete-berita"
                                            data-id="<?= $b['id'] ?>"
                                            data-judul="<?= e($b['judul']) ?>"
                                            title="Hapus Berita">
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

<!-- ========================================== -->
<!-- MODAL TAMBAH BERITA / PENGUMUMAN           -->
<!-- ========================================== -->
<div class="modal fade" id="modalTambahBerita" tabindex="-1" aria-labelledby="modalTambahBeritaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="create">

                <div class="modal-header bg-scout-navy text-white">
                    <h5 class="modal-title fw-bold" id="modalTambahBeritaLabel">
                        <i class="fa-solid fa-newspaper me-2 text-scout-gold"></i> Terbitkan Pengumuman &amp; Berita Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Judul Pengumuman / Berita <span class="text-danger">*</span></label>
                            <input type="text" name="judul" class="form-control" placeholder="Contoh: Jadwal Technical Meeting Daring dan Pembagian Kavling Buper" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Kategori Publikasi <span class="text-danger">*</span></label>
                            <select name="kategori" class="form-select" required>
                                <option value="Pengumuman" selected>Pengumuman Resmi</option>
                                <option value="Juknis">Petunjuk Teknis (Juknis)</option>
                                <option value="Agenda">Agenda &amp; Jadwal</option>
                                <option value="Berita">Berita Kegiatan</option>
                                <option value="Informasi">Informasi Umum</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Penulis / Penerbit (Author)</label>
                            <input type="text" name="author" class="form-control" value="<?= e($defaultAuthor) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Tanggal Publikasi</label>
                            <input type="date" name="tgl_post" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Sematkan ke Paling Atas (Pin)</label>
                            <select name="is_pinned" class="form-select">
                                <option value="N">Tidak (Urutan Standar)</option>
                                <option value="Y">Ya, Sematkan di Paling Atas (Pinned)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Upload Gambar Banner / Cover (Opsional)</label>
                            <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Format JPG, PNG, atau WEBP. Maksimal 5 MB.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Ringkasan Singkat (Excerpt)</label>
                            <textarea name="ringkasan" class="form-control" rows="2" placeholder="Tuliskan 1-2 kalimat ringkasan yang akan tampil di kartu berita beranda..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Isi Lengkap Pengumuman / Berita <span class="text-danger">*</span></label>
                            <textarea name="isi_konten" class="form-control" rows="7" placeholder="Tuliskan isi pengumuman secara lengkap, petunjuk teknis, kontak panitia, dan ketentuan detail..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-gold px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i> Terbitkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL EDIT BERITA / PENGUMUMAN             -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditBerita" tabindex="-1" aria-labelledby="modalEditBeritaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="update">
                <input type="hidden" name="berita_id" id="edit_berita_id" value="">

                <div class="modal-header bg-scout-maroon text-white">
                    <h5 class="modal-title fw-bold" id="modalEditBeritaLabel">
                        <i class="fa-solid fa-pen-to-square me-2 text-scout-gold"></i> Edit Pengumuman &amp; Berita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Judul Pengumuman / Berita <span class="text-danger">*</span></label>
                            <input type="text" name="judul" id="edit_judul" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Kategori Publikasi <span class="text-danger">*</span></label>
                            <select name="kategori" id="edit_kategori" class="form-select" required>
                                <option value="Pengumuman">Pengumuman Resmi</option>
                                <option value="Juknis">Petunjuk Teknis (Juknis)</option>
                                <option value="Agenda">Agenda &amp; Jadwal</option>
                                <option value="Berita">Berita Kegiatan</option>
                                <option value="Informasi">Informasi Umum</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Penulis / Penerbit (Author)</label>
                            <input type="text" name="author" id="edit_author" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Tanggal Publikasi</label>
                            <input type="date" name="tgl_post" id="edit_tgl_post" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Sematkan ke Paling Atas (Pin)</label>
                            <select name="is_pinned" id="edit_is_pinned" class="form-select">
                                <option value="N">Tidak (Urutan Standar)</option>
                                <option value="Y">Ya, Sematkan di Paling Atas (Pinned)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Ganti Gambar Banner / Cover (Opsional)</label>
                            <input type="file" name="gambar" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small">Kosongkan jika tidak ingin mengubah gambar banner saat ini.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Ringkasan Singkat (Excerpt)</label>
                            <textarea name="ringkasan" id="edit_ringkasan" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Isi Lengkap Pengumuman / Berita <span class="text-danger">*</span></label>
                            <textarea name="isi_konten" id="edit_isi_konten" class="form-control" rows="7" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-maroon px-4 fw-bold text-white shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Perbarui Publikasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL HAPUS BERITA                         -->
<!-- ========================================== -->
<div class="modal fade" id="modalDeleteBerita" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="delete">
                <input type="hidden" name="berita_id" id="delete_berita_id" value="">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Pengumuman / Berita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fa-solid fa-trash-can text-danger fs-1 mb-3"></i>
                    <h6 class="fw-bold text-dark">Apakah Anda yakin ingin menghapus berita ini?</h6>
                    <p class="text-muted small mb-0" id="delete_berita_judul"></p>
                    <p class="text-danger small mt-2 fw-semibold">Tindakan ini permanen dan berkas cover fisik akan dihapus.</p>
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
    const editModal = new bootstrap.Modal(document.getElementById('modalEditBerita'));
    document.querySelectorAll('.btn-edit-berita').forEach(btn => {
        btn.addEventListener('click', function() {
            const d = this.dataset;
            document.getElementById('edit_berita_id').value = d.id;
            document.getElementById('edit_judul').value = d.judul;
            document.getElementById('edit_kategori').value = d.kategori;
            document.getElementById('edit_author').value = d.author;
            document.getElementById('edit_tgl_post').value = d.tgl;
            document.getElementById('edit_is_pinned').value = d.pinned;
            document.getElementById('edit_ringkasan').value = d.ringkasan;
            document.getElementById('edit_isi_konten').value = d.konten;
            editModal.show();
        });
    });

    // Delete Modal Binding
    const deleteModal = new bootstrap.Modal(document.getElementById('modalDeleteBerita'));
    document.querySelectorAll('.btn-delete-berita').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_berita_id').value = this.dataset.id;
            document.getElementById('delete_berita_judul').innerText = '"' + this.dataset.judul + '"';
            deleteModal.show();
        });
    });
});
</script>
</body>
</html>
