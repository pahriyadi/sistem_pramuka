<?php
/**
 * Kelola Data Kontingen Sekolah - Admin Racana UNSA
 */
$pageTitle = 'Kelola Kontingen Sekolah';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

// Handle Status Verification Change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $targetId = (int)$_GET['id'];

    if (in_array($action, ['terverifikasi', 'menunggu', 'ditolak'])) {
        $stmtUpdate = $pdo->prepare("UPDATE sekolah SET status_verifikasi = ? WHERE id = ?");
        $stmtUpdate->execute([$action, $targetId]);
        set_flash('success', 'Status verifikasi kontingen berhasil diubah menjadi: <strong>' . strtoupper($action) . '</strong>');
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    } elseif ($action === 'buka_kunci') {
        $pdo->prepare("UPDATE sekolah SET status_kunci = 'terbuka' WHERE id = ?")->execute([$targetId]);
        set_flash('success', 'Kunci akses edit untuk kontingen berhasil <strong>DIBUKA</strong>. Sekolah kini dapat mengubah atau melengkapi data kembali.');
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    } elseif ($action === 'kunci') {
        $pdo->prepare("UPDATE sekolah SET status_kunci = 'terkunci', finalized_at = COALESCE(finalized_at, NOW()) WHERE id = ?")->execute([$targetId]);
        set_flash('success', 'Akses pendaftaran kontingen berhasil <strong>DIKUNCI</strong>.');
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    } elseif ($action === 'delete') {
        // Find user_id to delete user and cascade
        $stmtSek = $pdo->prepare("SELECT user_id, nama_sekolah FROM sekolah WHERE id = ?");
        $stmtSek->execute([$targetId]);
        $sek = $stmtSek->fetch();
        if ($sek) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$sek['user_id']]);
            set_flash('success', 'Kontingen <strong>' . e($sek['nama_sekolah']) . '</strong> berhasil dihapus.');
        }
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    }
}

// Fetch All Schools with Counts
$stmt = $pdo->query("
    SELECT s.*, u.username, u.email as user_email,
           COUNT(p.id) as total_peserta,
           SUM(CASE WHEN p.jenis_kelamin = 'L' THEN 1 ELSE 0 END) as total_putra,
           SUM(CASE WHEN p.jenis_kelamin = 'P' THEN 1 ELSE 0 END) as total_putri
    FROM sekolah s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN peserta p ON s.id = p.sekolah_id AND p.status = 'aktif'
    GROUP BY s.id
    ORDER BY s.id DESC
");
$sekolahList = $stmt->fetchAll();
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
                <h4 class="fw-bold mb-0 text-dark">Data Kontingen Sekolah</h4>
                <small class="text-muted">Kelola akun pangkalan, status peserta, dan verifikasi</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('admin/cetak-rekap.php') ?>" class="btn btn-outline-secondary btn-sm px-3" target="_blank">
                <i class="fa-solid fa-print me-1"></i> Cetak Rekap
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <div class="custom-card p-4">
        <?php if (empty($sekolahList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-school fs-1 mb-2"></i>
                <h5>Belum Ada Sekolah yang Mendaftar</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th>Nama Sekolah &amp; Alamat</th>
                            <th>Username Akun</th>
                            <th>Gudep</th>
                            <th>Pembina / Kontak</th>
                            <th>Peserta</th>
                            <th>Status Berkas</th>
                            <th>Status Bayar</th>
                            <th>Akses Kunci</th>
                            <th class="text-center" style="width: 170px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($sekolahList as $s): 
                            $stBayar = $s['status_pembayaran'] ?? 'belum_bayar';
                            $stKunci = $s['status_kunci'] ?? 'terbuka';
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($s['nama_sekolah']) ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= e($s['alamat_sekolah']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">@<?= e($s['username']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted d-block">Pa: <?= e($s['gudep_putra'] ?: '-') ?></small>
                                    <small class="text-muted d-block">Pi: <?= e($s['gudep_putri'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?= e($s['nama_pembina'] ?: '-') ?></div>
                                    <small class="text-success"><?= e($s['no_wa_pembina'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold fs-6 text-dark"><?= $s['total_peserta'] ?> / 24 Org</div>
                                    <small class="text-muted"><?= $s['total_putra'] ?> Pa &bull; <?= $s['total_putri'] ?> Pi</small>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-<?= $s['status_verifikasi'] === 'terverifikasi' ? 'success' : ($s['status_verifikasi'] === 'ditolak' ? 'danger' : 'warning text-dark') ?> dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <?= strtoupper($s['status_verifikasi']) ?>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item text-success" href="<?= base_url('admin/kontingen.php?action=terverifikasi&id=' . $s['id']) ?>"><i class="fa-solid fa-check me-1"></i> Verifikasi (Diterima)</a></li>
                                            <li><a class="dropdown-item text-warning" href="<?= base_url('admin/kontingen.php?action=menunggu&id=' . $s['id']) ?>"><i class="fa-solid fa-clock me-1"></i> Set Menunggu</a></li>
                                            <li><a class="dropdown-item text-danger" href="<?= base_url('admin/kontingen.php?action=ditolak&id=' . $s['id']) ?>"><i class="fa-solid fa-xmark me-1"></i> Tolak Pendaftaran</a></li>
                                        </ul>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($stBayar === 'disetujui'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i> LUNAS</span>
                                    <?php elseif ($stBayar === 'menunggu_konfirmasi'): ?>
                                        <a href="<?= base_url('admin/pembayaran.php?status=menunggu_konfirmasi') ?>" class="badge bg-primary text-decoration-none" title="Klik untuk verifikasi">
                                            <i class="fa-solid fa-clock me-1"></i> MENUNGGU
                                        </a>
                                    <?php elseif ($stBayar === 'ditolak'): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-ban me-1"></i> DITOLAK</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-hourglass-start me-1"></i> BELUM</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($stKunci === 'terkunci'): ?>
                                        <a href="<?= base_url('admin/kontingen.php?action=buka_kunci&id=' . $s['id']) ?>" class="badge bg-danger text-decoration-none" title="Klik untuk Buka Kunci Akses Sekolah" onclick="return confirm('Buka kunci akses edit untuk kontingen <?= e(addslashes($s['nama_sekolah'])) ?>?')">
                                            <i class="fa-solid fa-lock me-1"></i> TERKUNCI
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('admin/kontingen.php?action=kunci&id=' . $s['id']) ?>" class="badge bg-success text-decoration-none" title="Klik untuk Kunci Akses Sekolah" onclick="return confirm('Kunci akses pendaftaran untuk kontingen <?= e(addslashes($s['nama_sekolah'])) ?>?')">
                                            <i class="fa-solid fa-lock-open me-1"></i> TERBUKA
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('admin/kontingen-detail.php?id=' . $s['id']) ?>" class="btn btn-outline-primary" title="Lihat Detail & Data Peserta">
                                            <i class="fa-solid fa-eye"></i> Detail
                                        </a>
                                        <a href="<?= base_url('dashboard/cetak-formulir.php?sekolah_id=' . $s['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Formulir A.01">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </a>
                                        <a href="<?= base_url('admin/kontingen.php?action=delete&id=' . $s['id']) ?>" class="btn btn-outline-danger" title="Hapus Kontingen" onclick="return confirm('Apakah Anda yakin ingin menghapus kontingen <?= e(addslashes($s['nama_sekolah'])) ?> berserta seluruh peserta?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
