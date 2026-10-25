<?php
/**
 * Detail Kontingen, Verifikasi Berkas & Anggota Sekolah - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
ob_start();
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

$sekolahId = (int)($_GET['id'] ?? 0);

// If no ID is provided, try picking the first registered school
if ($sekolahId <= 0) {
    $firstId = (int)$pdo->query("SELECT id FROM sekolah ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId > 0) {
        $sekolahId = $firstId;
    } else {
        set_flash('danger', 'Belum ada data kontingen sekolah terdaftar.');
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    }
}

// Handle Form Actions (Update Status, Notes, Reset Password, Edit School, Delete Participant)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Validasi token keamanan gagal.');
    } else {
        $postAction = $_POST['action_type'] ?? '';

        // 1. UPDATE STATUS VERIFIKASI & CATATAN
        if ($postAction === 'update_verifikasi') {
            $statusVerifikasi = $_POST['status_verifikasi'] ?? 'menunggu';
            $catatan = trim($_POST['catatan_verifikasi'] ?? '');

            if (in_array($statusVerifikasi, ['terverifikasi', 'menunggu', 'ditolak'])) {
                $stmtUp = $pdo->prepare("UPDATE sekolah SET status_verifikasi = ?, catatan_verifikasi = ? WHERE id = ?");
                $stmtUp->execute([$statusVerifikasi, $catatan, $sekolahId]);
                set_flash('success', 'Status verifikasi kontingen berhasil diperbarui menjadi: <strong>' . strtoupper($statusVerifikasi) . '</strong>');
            }
        }
        // 1.B UPDATE STATUS PEMBAYARAN OLEH ADMIN
        elseif ($postAction === 'update_pembayaran') {
            $pembayaranId = (int)($_POST['pembayaran_id'] ?? 0);
            $statusBaru = $_POST['status_bayar'] ?? 'disetujui';
            $catatanAdmin = trim($_POST['catatan_admin'] ?? '');
            $adminUser = get_auth_user();
            $adminId = (int)($adminUser['id'] ?? 1);

            if (in_array($statusBaru, ['disetujui', 'ditolak', 'menunggu_konfirmasi'])) {
                if ($pembayaranId > 0) {
                    $stmtUpBayar = $pdo->prepare("
                        UPDATE pembayaran SET status = ?, catatan_admin = ?, diverifikasi_oleh = ?, diverifikasi_pada = NOW() WHERE id = ? AND sekolah_id = ?
                    ");
                    $stmtUpBayar->execute([$statusBaru, $catatanAdmin, $adminId, $pembayaranId, $sekolahId]);
                }
                $statusSekolah = ($statusBaru === 'disetujui') ? 'disetujui' : (($statusBaru === 'ditolak') ? 'ditolak' : 'menunggu_konfirmasi');
                $pdo->prepare("UPDATE sekolah SET status_pembayaran = ? WHERE id = ?")->execute([$statusSekolah, $sekolahId]);
                set_flash('success', 'Status pembayaran kontingen berhasil diubah menjadi: <strong>' . strtoupper($statusBaru) . '</strong>');
            }
        }
        // 1.C UPDATE STATUS KUNCI PENDAFTARAN OLEH ADMIN
        elseif ($postAction === 'update_kunci') {
            $statusKunciBaru = ($_POST['status_kunci'] ?? '') === 'terbuka' ? 'terbuka' : 'terkunci';
            $catatanKunci = trim($_POST['kunci_catatan'] ?? '');
            
            $stmtUpKunci = $pdo->prepare("UPDATE sekolah SET status_kunci = ?, kunci_catatan = ? WHERE id = ?");
            $stmtUpKunci->execute([$statusKunciBaru, $catatanKunci, $sekolahId]);
            
            if ($statusKunciBaru === 'terbuka') {
                set_flash('success', 'Kunci akses pendaftaran kontingen berhasil <strong>DIBUKA</strong>. Sekolah kini dapat mengubah atau melengkapi data peserta kembali.');
            } else {
                set_flash('success', 'Akses pendaftaran kontingen berhasil <strong>DIKUNCI</strong> (Mode Read-Only untuk sekolah).');
            }
        }
        // 2. RESET PASSWORD AKUN SEKOLAH
        elseif ($postAction === 'reset_password') {
            $newPassword = $_POST['new_password'] ?? 'sekolah123';
            if (strlen($newPassword) < 6) {
                set_flash('danger', 'Password baru minimal 6 karakter.');
            } else {
                $stmtSek = $pdo->prepare("SELECT user_id, nama_sekolah FROM sekolah WHERE id = ?");
                $stmtSek->execute([$sekolahId]);
                $sek = $stmtSek->fetch();

                if ($sek && !empty($sek['user_id'])) {
                    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmtUser = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUser->execute([$hashed, $sek['user_id']]);
                    set_flash('success', 'Password akun untuk kontingen <strong>' . e($sek['nama_sekolah']) . '</strong> berhasil di-reset menjadi: <code>' . e($newPassword) . '</code>');
                }
            }
        }
        // 3. EDIT INFORMASI PANGKALAN SEKOLAH
        elseif ($postAction === 'edit_sekolah') {
            $nama_sekolah   = trim($_POST['nama_sekolah'] ?? '');
            $alamat_sekolah = trim($_POST['alamat_sekolah'] ?? '');
            $npsn           = trim($_POST['npsn'] ?? '');
            $gudep_putra    = trim($_POST['gudep_putra'] ?? '');
            $gudep_putri    = trim($_POST['gudep_putri'] ?? '');
            $nama_kamabigus = trim($_POST['nama_kamabigus'] ?? '');
            $nama_pembina   = trim($_POST['nama_pembina'] ?? '');
            $no_wa_pembina  = trim($_POST['no_wa_pembina'] ?? '');
            $email_sekolah  = trim($_POST['email_sekolah'] ?? '');

            if (!empty($nama_sekolah) && !empty($alamat_sekolah)) {
                $stmtUpSek = $pdo->prepare("
                    UPDATE sekolah SET
                        nama_sekolah = ?, alamat_sekolah = ?, npsn = ?,
                        gudep_putra = ?, gudep_putri = ?, nama_kamabigus = ?,
                        nama_pembina = ?, no_wa_pembina = ?, email_sekolah = ?
                    WHERE id = ?
                ");
                $stmtUpSek->execute([
                    $nama_sekolah, $alamat_sekolah, $npsn,
                    $gudep_putra, $gudep_putri, $nama_kamabigus,
                    $nama_pembina, $no_wa_pembina, $email_sekolah, $sekolahId
                ]);
                set_flash('success', 'Informasi profil pangkalan sekolah berhasil diperbarui!');
            }
        }
        // 4. HAPUS PESERTA OLEH ADMIN
        elseif ($postAction === 'delete_peserta') {
            $delPesertaId = (int)($_POST['peserta_id'] ?? 0);
            if ($delPesertaId > 0) {
                // Delete photo file if exists
                $stmtPInfo = $pdo->prepare("SELECT foto FROM peserta WHERE id = ? AND sekolah_id = ?");
                $stmtPInfo->execute([$delPesertaId, $sekolahId]);
                $pFoto = $stmtPInfo->fetchColumn();
                if ($pFoto) {
                    $path = dirname(__DIR__) . '/uploads/peserta/' . $pFoto;
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
                $pdo->prepare("DELETE FROM peserta WHERE id = ? AND sekolah_id = ?")->execute([$delPesertaId, $sekolahId]);
                set_flash('success', 'Peserta berhasil dihapus dari kontingen.');
            }
        }
    }
    header('Location: ' . base_url('admin/kontingen-detail.php?id=' . $sekolahId));
    exit;
}

// Fetch School Info using LEFT JOIN for safety
$stmtSek = $pdo->prepare("
    SELECT s.*, 
           COALESCE(u.username, 'sekolah') as username, 
           COALESCE(u.email, s.email_sekolah) as user_email, 
           u.created_at as tgl_buat_akun 
    FROM sekolah s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmtSek->execute([$sekolahId]);
$sekolah = $stmtSek->fetch();

// If school not found with given ID, fallback to first available school
if (!$sekolah) {
    $stmtFallback = $pdo->query("
        SELECT s.*, 
               COALESCE(u.username, 'sekolah') as username, 
               COALESCE(u.email, s.email_sekolah) as user_email, 
               u.created_at as tgl_buat_akun 
        FROM sekolah s 
        LEFT JOIN users u ON s.user_id = u.id 
        ORDER BY s.id ASC LIMIT 1
    ");
    $sekolah = $stmtFallback->fetch();
    
    if (!$sekolah) {
        set_flash('danger', 'Data kontingen sekolah tidak ditemukan.');
        header('Location: ' . base_url('admin/kontingen.php'));
        exit;
    }
    $sekolahId = (int)$sekolah['id'];
}

// Safe variable extractions
$namaSekolah       = $sekolah['nama_sekolah'] ?? 'Kontingen Sekolah';
$statusVerifikasi  = $sekolah['status_verifikasi'] ?? 'menunggu';
$statusPembayaran  = $sekolah['status_pembayaran'] ?? 'belum_bayar';
$statusKunci       = $sekolah['status_kunci'] ?? 'terbuka';
$finalizedAt       = $sekolah['finalized_at'] ?? null;
$kunciCatatan      = $sekolah['kunci_catatan'] ?? '';
$alamatSekolah     = $sekolah['alamat_sekolah'] ?? '-';
$username          = $sekolah['username'] ?? 'user';
$gudepPutra        = $sekolah['gudep_putra'] ?: '01.001';
$gudepPutri        = $sekolah['gudep_putri'] ?: '01.002';
$namaPembina       = $sekolah['nama_pembina'] ?: '-';
$noWaPembina       = $sekolah['no_wa_pembina'] ?: '-';
$namaKamabigus     = $sekolah['nama_kamabigus'] ?: '-';
$emailSekolah      = $sekolah['email_sekolah'] ?: ($sekolah['user_email'] ?: '-');
$npsn              = $sekolah['npsn'] ?: '-';
$catatanVerifikasi = $sekolah['catatan_verifikasi'] ?? '';

// Fetch Payments for this school
$stmtSchoolPayments = $pdo->prepare("SELECT * FROM pembayaran WHERE sekolah_id = ? ORDER BY id DESC");
$stmtSchoolPayments->execute([$sekolahId]);
$schoolPayments = $stmtSchoolPayments->fetchAll();
$latestPayment = !empty($schoolPayments) ? $schoolPayments[0] : null;

$pageTitle = 'Detail Kontingen ' . $namaSekolah;

// Filter by category if requested
$filterKategori = (int)($_GET['kategori'] ?? 0);

// Fetch Category Stats for this Contingent (Aktif and Draft)
$stmtKatStats = $pdo->prepare("
    SELECT k.*, 
           COUNT(p.id) as total_terisi,
           SUM(CASE WHEN p.status = 'aktif' THEN 1 ELSE 0 END) as total_aktif,
           SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as total_draft
    FROM kategori_peserta k
    LEFT JOIN peserta p ON p.kategori_id = k.id AND p.sekolah_id = ? AND p.status IN ('aktif', 'draft')
    GROUP BY k.id
    ORDER BY k.urutan ASC
");
$stmtKatStats->execute([$sekolahId]);
$kategoriStats = $stmtKatStats->fetchAll();

// Fetch Participants List
$queryPeserta = "
    SELECT p.*, k.nama_kategori, k.kode_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ', ') as daftar_lomba,
           COUNT(pl.id) as total_lomba
    FROM peserta p
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE p.sekolah_id = ? AND p.status IN ('aktif', 'draft')
";
$paramsPeserta = [$sekolahId];

if ($filterKategori > 0) {
    $queryPeserta .= " AND p.kategori_id = ?";
    $paramsPeserta[] = $filterKategori;
}

$queryPeserta .= " GROUP BY p.id ORDER BY k.urutan ASC, p.status ASC, p.id ASC";

$stmtP = $pdo->prepare($queryPeserta);
$stmtP->execute($paramsPeserta);
$pesertaList = $stmtP->fetchAll();

// Calculate total active participants
$totalPeserta = 0;
foreach ($kategoriStats as $ks) {
    $totalPeserta += (int)$ks['total_terisi'];
}
$maxTotalKuota = array_sum(array_map(static fn($kategori) => (int)$kategori['kuota_max'], $kategoriStats));
$progressPersen = $maxTotalKuota > 0 ? min(100, round(($totalPeserta / $maxTotalKuota) * 100)) : 0;
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
    <!-- Top Bar Navigation -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-dark"><?= e($namaSekolah) ?></h4>
                    <span class="badge bg-<?= $statusVerifikasi === 'terverifikasi' ? 'success' : ($statusVerifikasi === 'ditolak' ? 'danger' : 'warning text-dark') ?>" title="Status Berkas">
                        <i class="fa-solid fa-file-check me-1"></i> <?= strtoupper($statusVerifikasi) ?>
                    </span>
                    <span class="badge bg-<?= $statusPembayaran === 'disetujui' ? 'success' : ($statusPembayaran === 'menunggu_konfirmasi' ? 'primary' : ($statusPembayaran === 'ditolak' ? 'danger' : 'secondary')) ?>" title="Status Pembayaran">
                        <i class="fa-solid fa-credit-card me-1"></i> BAYAR: <?= strtoupper(str_replace('_', ' ', $statusPembayaran)) ?>
                    </span>
                    <span class="badge bg-<?= $statusKunci === 'terkunci' ? 'danger' : 'success' ?>" title="Status Kunci Akses Formulir">
                        <i class="fa-solid <?= $statusKunci === 'terkunci' ? 'fa-lock' : 'fa-lock-open' ?> me-1"></i> <?= $statusKunci === 'terkunci' ? 'TERKUNCI (FINAL)' : 'AKSES TERBUKA' ?>
                    </span>
                </div>
                <small class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($alamatSekolah) ?></small>
            </div>
        </div>

        <!-- Quick Document Actions -->
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= base_url('admin/kontingen.php') ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
            </a>
            <a href="<?= base_url('dashboard/cetak-idcard.php?sekolah_id=' . $sekolahId) ?>" target="_blank" class="btn btn-scout-gold btn-sm px-3">
                <i class="fa-solid fa-id-card me-1"></i> Cetak Semua ID Card
            </a>
            <a href="<?= base_url('dashboard/cetak-formulir.php?sekolah_id=' . $sekolahId) ?>" target="_blank" class="btn btn-outline-success btn-sm px-3">
                <i class="fa-solid fa-file-pdf me-1"></i> Cetak Formulir A.01
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditSekolah">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profil
            </button>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- Overview Banner & Verifikasi Form -->
    <div class="row g-4 mb-4">
        <!-- 1. School Info Card -->
        <div class="col-lg-8">
            <div class="custom-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-school text-success me-2"></i> Informasi Pangkalan Kontingen</h5>
                    <button class="btn btn-sm btn-outline-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalResetPassword">
                        <i class="fa-solid fa-key me-1"></i> Reset Password Akun
                    </button>
                </div>

                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Nama Sekolah:</span>
                        <strong class="fs-6 text-dark"><?= e($namaSekolah) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Username Akun Portal:</span>
                        <span class="badge bg-dark font-monospace">@<?= e($username) ?></span>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block">Gudep Pangkalan:</span>
                        <strong>Putra: <?= e($gudepPutra) ?> &bull; Putri: <?= e($gudepPutri) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">NPSN / Identitas Sekolah:</span>
                        <strong><?= e($npsn) ?></strong>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block">Pembina Pendamping (Bindamping):</span>
                        <strong class="text-primary"><?= e($namaPembina) ?></strong>
                        <?php if ($noWaPembina !== '-'): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $noWaPembina) ?>" target="_blank" class="badge bg-success ms-1 text-decoration-none">
                                <i class="fa-brands fa-whatsapp"></i> Chat WA
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Nomor WhatsApp Pembina:</span>
                        <strong class="font-monospace"><?= e($noWaPembina) ?></strong>
                    </div>

                    <div class="col-sm-6">
                        <span class="text-muted d-block">Kepala Sekolah / Kamabigus:</span>
                        <strong><?= e($namaKamabigus) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Email Resmi:</span>
                        <strong><?= e($emailSekolah) ?></strong>
                    </div>

                    <div class="col-12">
                        <span class="text-muted d-block">Alamat Lengkap Sekolah:</span>
                        <div class="p-2 bg-light rounded-2 border"><?= e($alamatSekolah) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Status & Verification & Lock Action Cards -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Verifikasi Panitia -->
            <div class="custom-card p-4 bg-light">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-stamp text-primary me-2"></i> Verifikasi Panitia
                </h5>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="update_verifikasi">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Status Verifikasi Kontingen</label>
                        <select name="status_verifikasi" class="form-select fw-bold text-dark">
                            <option value="menunggu" class="text-warning" <?= $statusVerifikasi === 'menunggu' ? 'selected' : '' ?>>MENUNGGU VERIFIKASI</option>
                            <option value="terverifikasi" class="text-success" <?= $statusVerifikasi === 'terverifikasi' ? 'selected' : '' ?>>TERVERIFIKASI (DITERIMA)</option>
                            <option value="ditolak" class="text-danger" <?= $statusVerifikasi === 'ditolak' ? 'selected' : '' ?>>DITOLAK</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Catatan Panitia untuk Sekolah</label>
                        <textarea name="catatan_verifikasi" class="form-control" rows="3" placeholder="Tuliskan catatan berkas, instruksi daftar ulang, atau alasan penolakan..."><?= e($catatanVerifikasi) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-scout-primary w-100 fw-bold py-2 shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Status Verifikasi
                    </button>
                </form>

                <div class="mt-3 pt-3 border-top small text-muted">
                    <i class="fa-solid fa-circle-info text-info me-1"></i>
                    Status dan catatan ini otomatis tampil di dashboard sekolah kontingen.
                </div>
            </div>

            <!-- Kontrol Kunci Akses Pendaftaran -->
            <div class="custom-card p-4 bg-light border border-<?= $statusKunci === 'terkunci' ? 'danger' : 'success' ?> border-opacity-50">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fa-solid <?= $statusKunci === 'terkunci' ? 'fa-lock text-danger' : 'fa-lock-open text-success' ?> me-2"></i> Akses Formulir
                    </h5>
                    <span class="badge bg-<?= $statusKunci === 'terkunci' ? 'danger' : 'success' ?>">
                        <?= $statusKunci === 'terkunci' ? 'TERKUNCI' : 'TERBUKA' ?>
                    </span>
                </div>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="update_kunci">

                    <div class="mb-3 small">
                        <?php if ($statusKunci === 'terkunci'): ?>
                            <div class="alert alert-danger py-2 px-3 mb-2 small">
                                <i class="fa-solid fa-lock me-1"></i> <strong>Pendaftaran Terkunci</strong><br>
                                Sekolah tidak dapat menambah/mengedit/menghapus data peserta.
                                <?php if ($finalizedAt): ?>
                                    <div class="mt-1 text-muted" style="font-size: 0.75rem;">
                                        Finalisasi resmi: <?= date('d/m/Y H:i', strtotime($finalizedAt)) ?> WIB
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success py-2 px-3 mb-2 small">
                                <i class="fa-solid fa-lock-open me-1"></i> <strong>Akses Terbuka</strong><br>
                                Sekolah dapat leluasa mengedit, menambah, atau merevisi data kontingen.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Catatan Pembukaan / Penguncian (Opsional)</label>
                        <input type="text" name="kunci_catatan" class="form-control form-control-sm" placeholder="Contoh: Diberi izin revisi foto s.d 10 Okt" value="<?= e($kunciCatatan) ?>">
                    </div>

                    <?php if ($statusKunci === 'terkunci'): ?>
                        <input type="hidden" name="status_kunci" value="terbuka">
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm" onclick="return confirm('Buka kunci pendaftaran untuk sekolah ini agar mereka dapat mengedit data kembali?')">
                            <i class="fa-solid fa-lock-open me-1"></i> Buka Kunci Akses Edit
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="status_kunci" value="terkunci">
                        <button type="submit" class="btn btn-danger w-100 fw-bold py-2 shadow-sm" onclick="return confirm('Kunci akses pendaftaran sekolah ini sehingga data tidak dapat diubah lagi?')">
                            <i class="fa-solid fa-lock me-1"></i> Kunci Akses Pendaftaran
                        </button>
                    <?php endif; ?>
                </form>

                <div class="mt-3 pt-2 border-top small text-muted">
                    <i class="fa-solid fa-shield-halved text-warning me-1"></i>
                    Admin memiliki otoritas penuh membuka atau mengunci kembali akses pendaftaran kapan saja.
                </div>
            </div>
        </div>
    </div>

    <!-- 3. PEMBAYARAN KONTINGEN SECTION -->
    <div class="custom-card p-4 mb-4 border-<?= $statusPembayaran === 'disetujui' ? 'success' : ($statusPembayaran === 'menunggu_konfirmasi' ? 'primary' : ($statusPembayaran === 'ditolak' ? 'danger' : 'warning')) ?> border-opacity-25" style="background: linear-gradient(180deg, #fcfdfc 0%, #ffffff 100%);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 border-bottom pb-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-credit-card text-success fs-5"></i>
                <h5 class="fw-bold text-dark mb-0">Status Pembayaran Kontingen</h5>
                <span class="badge bg-<?= $statusPembayaran === 'disetujui' ? 'success' : ($statusPembayaran === 'menunggu_konfirmasi' ? 'primary' : ($statusPembayaran === 'ditolak' ? 'danger' : 'warning text-dark')) ?>">
                    <?= strtoupper(str_replace('_', ' ', $statusPembayaran)) ?>
                </span>
            </div>
            <div>
                <a href="<?= base_url('admin/pembayaran.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-list-check me-1"></i> Buka Semua Pembayaran
                </a>
            </div>
        </div>

        <?php if ($latestPayment): 
            $buktiUrlDetail = base_url('uploads/pembayaran/' . $latestPayment['bukti_transfer']);
            $isPdfDetail = strtolower(pathinfo($latestPayment['bukti_transfer'], PATHINFO_EXTENSION)) === 'pdf';
        ?>
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="p-3 bg-light rounded-3 border small">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Kode Pembayaran:</span>
                                <strong class="font-monospace text-dark"><?= e($latestPayment['kode_bayar']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Jumlah Ditransfer:</span>
                                <strong class="text-success fs-6 font-monospace"><?= format_rupiah($latestPayment['jumlah_bayar']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Metode &amp; Bank Pengirim:</span>
                                <strong><?= strtoupper(str_replace('_', ' ', $latestPayment['metode_bayar'])) ?> &bull; <?= e($latestPayment['bank_pengirim']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Atas Nama Pengirim:</span>
                                <strong><?= e($latestPayment['atas_nama_pengirim']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Tanggal Transfer:</span>
                                <strong><?= format_tanggal_indonesia($latestPayment['tanggal_bayar']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Waktu Unggah:</span>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($latestPayment['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($latestPayment['catatan_sekolah'])): ?>
                                <div class="col-12">
                                    <span class="text-muted d-block">Catatan dari Sekolah:</span>
                                    <div class="p-2 bg-white rounded border fst-italic">"<?= e($latestPayment['catatan_sekolah']) ?>"</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBuktiDetail">
                            <i class="fa-solid <?= $isPdfDetail ? 'fa-file-pdf text-danger' : 'fa-image text-primary' ?> me-1"></i> Lihat Struk Bukti Transfer
                        </button>
                    </div>
                </div>

                <div class="col-lg-5">
                    <form method="POST" action="" class="p-3 bg-white rounded-3 border">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action_type" value="update_pembayaran">
                        <input type="hidden" name="pembayaran_id" value="<?= $latestPayment['id'] ?>">

                        <label class="form-label small fw-semibold text-dark mb-1">Ubah Status Pembayaran</label>
                        <select name="status_bayar" class="form-select form-select-sm mb-2 fw-bold" required>
                            <option value="disetujui" <?= $latestPayment['status'] === 'disetujui' ? 'selected' : '' ?> class="text-success">
                                &#10004; DISETUJUI / LUNAS
                            </option>
                            <option value="ditolak" <?= $latestPayment['status'] === 'ditolak' ? 'selected' : '' ?> class="text-danger">
                                &#10008; DITOLAK (Harap Upload Ulang)
                            </option>
                            <option value="menunggu_konfirmasi" <?= $latestPayment['status'] === 'menunggu_konfirmasi' ? 'selected' : '' ?> class="text-warning">
                                &#9203; MENUNGGU KONFIRMASI
                            </option>
                        </select>

                        <label class="form-label small fw-semibold text-dark mb-1">Catatan Panitia untuk Sekolah</label>
                        <textarea name="catatan_admin" class="form-control form-control-sm mb-3" rows="2" placeholder="Tulis alasan jika ditolak atau catatan verifikasi..."><?= e($latestPayment['catatan_admin'] ?? '') ?></textarea>

                        <button type="submit" class="btn btn-scout-gold btn-sm w-100 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Status Pembayaran
                        </button>
                    </form>
                </div>
            </div>

            <!-- MODAL: PREVIEW BUKTI DI DETAIL -->
            <div class="modal fade" id="modalBuktiDetail" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt text-success me-2"></i> Struk: <?= e($latestPayment['kode_bayar']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-3">
                            <?php if ($isPdfDetail): ?>
                                <iframe src="<?= $buktiUrlDetail ?>" style="width: 100%; height: 500px; border: none;"></iframe>
                            <?php else: ?>
                                <img src="<?= $buktiUrlDetail ?>" alt="Struk Pembayaran" class="img-fluid rounded border" style="max-height: 520px;">
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <a href="<?= $buktiUrlDetail ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-up-right-from-square me-1"></i> Buka File Asli
                            </a>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="p-3 bg-light rounded-3 text-center text-muted small">
                <i class="fa-solid fa-receipt fs-4 d-block mb-1 text-secondary"></i>
                <strong>Belum Ada Bukti Pembayaran yang Diunggah</strong>
                <p class="mb-0 text-muted">Kontingen sekolah ini belum mengirimkan konfirmasi transfer pembayaran pendaftaran.</p>
            </div>
        <?php endif; ?>
    </div>


    <!-- Quota Progress Breakdown -->
    <div class="custom-card p-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-viewfinder text-success me-2"></i> Kuota Kontingen (Maksimal <?= $maxTotalKuota ?> Orang)</h5>
                <small class="text-muted">Rincian pengisian kuota anggota Barisan Putra, Putri, dan Pembina Pendamping</small>
            </div>
            <div class="mt-2 mt-md-0">
                <span class="fs-5 fw-bold text-success"><?= $totalPeserta ?> / <?= $maxTotalKuota ?> Orang</span>
                <span class="badge bg-secondary ms-1"><?= $progressPersen ?>% Terisi</span>
            </div>
        </div>

        <div class="progress mb-4" style="height: 10px;">
            <div class="progress-bar bg-success progress-bar-striped" role="progressbar" style="width: <?= $progressPersen ?>%;"></div>
        </div>

        <div class="row g-3">
            <?php foreach ($kategoriStats as $ks): 
                $sisa = $ks['kuota_max'] - $ks['total_terisi'];
                $isFull = ($ks['total_terisi'] >= $ks['kuota_max']);
            ?>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="small text-muted mb-1 text-truncate"><?= e($ks['nama_kategori']) ?></div>
                        <div class="d-flex justify-content-between align-items-baseline">
                            <h4 class="fw-bold text-dark mb-0"><?= $ks['total_terisi'] ?> <span class="fs-6 text-muted fw-normal">/ <?= $ks['kuota_max'] ?></span></h4>
                            <span class="badge <?= $isFull ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 0.7rem;">
                                <?= $isFull ? 'Lengkap' : 'Sisa ' . $sisa ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Category Filter Tabs for Participants -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= base_url('admin/kontingen-detail.php?id=' . $sekolahId) ?>" class="btn btn-sm <?= $filterKategori === 0 ? 'btn-success' : 'btn-outline-secondary' ?>">
            Semua Anggota <span class="badge bg-dark ms-1"><?= $totalPeserta ?></span>
        </a>
        <?php foreach ($kategoriStats as $kat): ?>
            <a href="<?= base_url('admin/kontingen-detail.php?id=' . $sekolahId . '&kategori=' . $kat['id']) ?>" class="btn btn-sm <?= $filterKategori == $kat['id'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                <?= e($kat['nama_kategori']) ?> <span class="badge bg-secondary ms-1"><?= $kat['total_terisi'] ?>/<?= $kat['kuota_max'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Participants Table -->
    <div class="custom-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Daftar Anggota Kontingen (<?= count($pesertaList) ?> Peserta)</h5>
            <small class="text-muted">Klik ikon ID card untuk mencetak kartu peserta resmi</small>
        </div>

        <?php if (empty($pesertaList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-users-slash fs-1 mb-2"></i>
                <h6 class="fw-bold">Belum Ada Peserta yang Didaftarkan</h6>
                <p class="small">Pangkalan sekolah ini belum mengunggah biodata anggota pada kategori ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th>Status</th>
                            <th>Foto</th>
                            <th>Nama Lengkap</th>
                            <th>Kategori</th>
                            <th>L/P</th>
                            <th>Kelas</th>
                            <th>Tgl Lahir / Umur</th>
                            <th>Mata Lomba yang Diikuti</th>
                            <?php if (is_field_enabled('ukuran_kaos')): ?>
                                <th>Kaos</th>
                            <?php endif; ?>
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($pesertaList as $p): 
                            $u = hitung_umur($p['tanggal_lahir']);
                            $fotoUrl = !empty($p['foto']) ? base_url('uploads/peserta/' . $p['foto']) : null;
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <span class="badge bg-success">RESMI</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">DRAF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fotoUrl): ?>
                                        <a href="<?= $fotoUrl ?>" target="_blank" title="Lihat foto penuh">
                                            <img src="<?= $fotoUrl ?>" alt="" class="rounded" style="width: 40px; height: 50px; object-fit: cover; border: 2px solid #0d5c3a;">
                                        </a>
                                    <?php else: ?>
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted border" style="width: 40px; height: 50px;" title="Belum upload foto">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['nama_lengkap']) ?></div>
                                    <small class="text-muted font-monospace">NTA: <?= e($p['nisn_nta'] ?: '-') ?></small>
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-15 text-dark border"><?= e($p['nama_kategori']) ?></span></td>
                                <td><?= $p['jenis_kelamin'] === 'L' ? '<span class="text-primary fw-bold">L</span>' : '<span class="text-danger fw-bold">P</span>' ?></td>
                                <td><?= e($p['kelas']) ?></td>
                                <td>
                                    <div><?= format_tanggal_indonesia($p['tanggal_lahir']) ?></div>
                                    <span class="badge bg-success bg-opacity-15 text-success font-monospace" style="font-size: 0.7rem;"><?= $u['teks'] ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($p['daftar_lomba'])): ?>
                                        <div class="fw-semibold text-dark"><?= e($p['daftar_lomba']) ?></div>
                                        <small class="text-muted">(<?= $p['total_lomba'] ?> Lomba)</small>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>-</em></span>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_field_enabled('ukuran_kaos')): ?>
                                    <td><span class="badge bg-light text-dark border"><?= e($p['ukuran_kaos']) ?></span></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('dashboard/cetak-idcard.php?id=' . $p['id']) ?>" target="_blank" class="btn btn-outline-success" title="Cetak ID Card">
                                            <i class="fa-solid fa-id-card"></i>
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('Hapus peserta <?= e(addslashes($p['nama_lengkap'])) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="delete_peserta">
                                            <input type="hidden" name="peserta_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus Peserta">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- MODAL: EDIT PROFIL SEKOLAH OLEH ADMIN -->
<div class="modal fade" id="modalEditSekolah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="edit_sekolah">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-success me-2"></i> Edit Informasi Pangkalan Sekolah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" name="nama_sekolah" class="form-control" value="<?= e($namaSekolah) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">NPSN</label>
                            <input type="text" name="npsn" class="form-control" value="<?= e($npsn !== '-' ? $npsn : '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Alamat Sekolah <span class="text-danger">*</span></label>
                            <textarea name="alamat_sekolah" class="form-control" rows="2" required><?= e($alamatSekolah) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Gudep Putra</label>
                            <input type="text" name="gudep_putra" class="form-control" value="<?= e($gudepPutra) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Gudep Putri</label>
                            <input type="text" name="gudep_putri" class="form-control" value="<?= e($gudepPutri) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Kamabigus / Kepala Sekolah</label>
                            <input type="text" name="nama_kamabigus" class="form-control" value="<?= e($namaKamabigus !== '-' ? $namaKamabigus : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Sekolah</label>
                            <input type="email" name="email_sekolah" class="form-control" value="<?= e($emailSekolah !== '-' ? $emailSekolah : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Pembina Pendamping</label>
                            <input type="text" name="nama_pembina" class="form-control" value="<?= e($namaPembina !== '-' ? $namaPembina : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">No. WhatsApp Pembina</label>
                            <input type="text" name="no_wa_pembina" class="form-control" value="<?= e($noWaPembina !== '-' ? $noWaPembina : '') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-primary btn-sm fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: RESET PASSWORD AKUN SEKOLAH -->
<div class="modal fade" id="modalResetPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="reset_password">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-key text-warning me-2"></i> Reset Password Akun Kontingen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Gunakan fitur ini jika pembina sekolah lupa password login akun mereka.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username Akun</label>
                        <input type="text" class="form-control bg-light font-monospace" value="<?= e($username) ?>" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Baru <span class="text-danger">*</span></label>
                        <input type="text" name="new_password" class="form-control font-monospace" value="sekolah123" required>
                        <div class="form-text small">Default: <code>sekolah123</code> (bisa Anda ubah).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold">Terapkan Password Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
