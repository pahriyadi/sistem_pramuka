<?php
/**
 * Master Data Referensi Mata Lomba - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Kelola Referensi Mata Lomba';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

$errors = [];
$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// Handle Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $stmtDel = $pdo->prepare("DELETE FROM mata_lomba WHERE id = ?");
        $stmtDel->execute([$editId]);
        set_flash('success', 'Mata lomba berhasil dihapus dari sistem.');
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus mata lomba: ' . $e->getMessage());
    }
    header('Location: ' . base_url('admin/mata-lomba.php'));
    exit;
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi CSRF token tidak sah.';
    } else {
        $kode_lomba              = strtoupper(trim($_POST['kode_lomba'] ?? ''));
        $nama_lomba              = trim($_POST['nama_lomba'] ?? '');
        $kategori_lomba          = $_POST['kategori_lomba'] ?? 'Campuran / Umum';
        $max_peserta_per_sekolah = (int)($_POST['max_peserta_per_sekolah'] ?? 10);
        $deskripsi               = trim($_POST['deskripsi'] ?? '');
        $juknis_singkat          = trim($_POST['juknis_singkat'] ?? '');
        $penanggung_jawab        = trim($_POST['penanggung_jawab'] ?? '');
        $lokasi_lomba            = trim($_POST['lokasi_lomba'] ?? 'Bumi Perkemahan UNSA');
        $status_aktif            = $_POST['status_aktif'] ?? 'Y';
        $formId                  = (int)($_POST['id'] ?? 0);

        if (empty($kode_lomba)) $errors[] = 'Kode Lomba wajib diisi.';
        if (empty($nama_lomba)) $errors[] = 'Nama Lomba wajib diisi.';

        if (empty($errors)) {
            try {
                if ($formId > 0) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE mata_lomba SET
                            kode_lomba = ?,
                            nama_lomba = ?,
                            kategori_lomba = ?,
                            max_peserta_per_sekolah = ?,
                            deskripsi = ?,
                            juknis_singkat = ?,
                            penanggung_jawab = ?,
                            lokasi_lomba = ?,
                            status_aktif = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $kode_lomba, $nama_lomba, $kategori_lomba,
                        $max_peserta_per_sekolah, $deskripsi, $juknis_singkat,
                        $penanggung_jawab, $lokasi_lomba, $status_aktif, $formId
                    ]);
                    set_flash('success', 'Mata lomba <strong>' . e($nama_lomba) . '</strong> berhasil diperbarui!');
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO mata_lomba (
                            kode_lomba, nama_lomba, kategori_lomba,
                            max_peserta_per_sekolah, deskripsi, juknis_singkat,
                            penanggung_jawab, lokasi_lomba, status_aktif
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $kode_lomba, $nama_lomba, $kategori_lomba,
                        $max_peserta_per_sekolah, $deskripsi, $juknis_singkat,
                        $penanggung_jawab, $lokasi_lomba, $status_aktif
                    ]);
                    set_flash('success', 'Mata lomba baru <strong>' . e($nama_lomba) . '</strong> berhasil ditambahkan!');
                }
                header('Location: ' . base_url('admin/mata-lomba.php'));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
            }
        }
    }
}

// Fetch single for edit if requested
$editData = null;
if ($action === 'edit' && $editId > 0) {
    $stmtE = $pdo->prepare("SELECT * FROM mata_lomba WHERE id = ?");
    $stmtE->execute([$editId]);
    $editData = $stmtE->fetch();
}

// Fetch All Lomba with Total Participants
$stmtLomba = $pdo->query("
    SELECT m.*, COUNT(pl.id) as total_peserta
    FROM mata_lomba m
    LEFT JOIN peserta_lomba pl ON m.id = pl.lomba_id
    GROUP BY m.id
    ORDER BY m.id ASC
");
$listLomba = $stmtLomba->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Admin Racana</title>
    
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
                <h4 class="fw-bold mb-0 text-dark">Kelola Cabang Lomba</h4>
                <small class="text-muted">Master cabang lomba kepramukaan dan kuota peserta</small>
            </div>
        </div>
        <div>
            <a href="<?= base_url('admin/mata-lomba.php?action=add') ?>" class="btn btn-scout-gold btn-sm px-3">
                <i class="fa-solid fa-plus me-1"></i> Tambah Lomba
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Add/Edit Column -->
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="col-lg-5">
                <div class="custom-card p-4">
                    <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-trophy text-warning me-2"></i> <?= $action === 'edit' ? 'Edit Mata Lomba' : 'Tambah Mata Lomba Baru' ?>
                    </h5>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $editData['id'] ?? 0 ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Kode Lomba (Unik) <span class="text-danger">*</span></label>
                            <input type="text" name="kode_lomba" class="form-control font-monospace" placeholder="Contoh: PBB-KREASI" value="<?= e($editData['kode_lomba'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Nama Mata Lomba <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lomba" class="form-control" placeholder="Contoh: PBB & Variasi Tongkat" value="<?= e($editData['nama_lomba'] ?? '') ?>" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-dark">Kategori</label>
                                <select name="kategori_lomba" class="form-select">
                                    <option value="Campuran / Umum" <?= ($editData['kategori_lomba'] ?? '') === 'Campuran / Umum' ? 'selected' : '' ?>>Campuran / Umum</option>
                                    <option value="Putra" <?= ($editData['kategori_lomba'] ?? '') === 'Putra' ? 'selected' : '' ?>>Putra</option>
                                    <option value="Putri" <?= ($editData['kategori_lomba'] ?? '') === 'Putri' ? 'selected' : '' ?>>Putri</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-dark">Max Peserta / Gudep</label>
                                <input type="number" name="max_peserta_per_sekolah" class="form-control" value="<?= e($editData['max_peserta_per_sekolah'] ?? 10) ?>" min="1" max="24">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Deskripsi Lomba</label>
                            <textarea name="deskripsi" class="form-control" rows="2" placeholder="Penjelasan singkat cabang lomba"><?= e($editData['deskripsi'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Petunjuk Teknis / Juknis Singkat</label>
                            <textarea name="juknis_singkat" class="form-control" rows="2" placeholder="Ketentuan waktu, alat, durasi..."><?= e($editData['juknis_singkat'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-dark">Penanggung Jawab (PJ)</label>
                                <input type="text" name="penanggung_jawab" class="form-control" placeholder="Kak Ahmad" value="<?= e($editData['penanggung_jawab'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-dark">Lokasi Lomba</label>
                                <input type="text" name="lokasi_lomba" class="form-control" placeholder="Lapangan Utama" value="<?= e($editData['lokasi_lomba'] ?? 'Bumi Perkemahan UNSA') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-dark">Status Aktif</label>
                            <select name="status_aktif" class="form-select">
                                <option value="Y" <?= ($editData['status_aktif'] ?? 'Y') === 'Y' ? 'selected' : '' ?>>Aktif (Dapat Dipilih)</option>
                                <option value="N" <?= ($editData['status_aktif'] ?? '') === 'N' ? 'selected' : '' ?>>Nonaktif (Disembunyikan)</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-scout-gold flex-grow-1 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Data Lomba
                            </button>
                            <a href="<?= base_url('admin/mata-lomba.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Table Column -->
        <div class="<?= ($action === 'add' || $action === 'edit') ? 'col-lg-7' : 'col-12' ?>">
            <div class="custom-card p-4">
                <h5 class="fw-bold text-dark mb-3">Daftar Master Cabang Lomba (<?= count($listLomba) ?> Lomba)</h5>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Lomba</th>
                                <th>Maks. Kuota</th>
                                <th>Lokasi</th>
                                <th>Peminat</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listLomba as $l): ?>
                                <tr>
                                    <td><span class="badge bg-dark font-monospace"><?= e($l['kode_lomba']) ?></span></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($l['nama_lomba']) ?></div>
                                        <small class="text-muted"><?= e($l['penanggung_jawab'] ?: '-') ?></small>
                                    </td>
                                    <td><?= $l['max_peserta_per_sekolah'] ?> Orang</td>
                                    <td><?= e($l['lokasi_lomba']) ?></td>
                                    <td><span class="badge bg-success"><?= $l['total_peserta'] ?> Peserta</span></td>
                                    <td>
                                        <span class="badge bg-<?= $l['status_aktif'] === 'Y' ? 'success' : 'secondary' ?>">
                                            <?= $l['status_aktif'] === 'Y' ? 'AKTIF' : 'NONAKTIF' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('admin/mata-lomba.php?action=edit&id=' . $l['id']) ?>" class="btn btn-outline-primary" title="Edit Lomba">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="<?= base_url('admin/mata-lomba.php?action=delete&id=' . $l['id']) ?>" class="btn btn-outline-danger" title="Hapus Lomba" onclick="return confirm('Hapus mata lomba <?= e(addslashes($l['nama_lomba'])) ?>?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
