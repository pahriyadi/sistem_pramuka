<?php
/**
 * Profil & Informasi Pangkalan Sekolah
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Profil Pangkalan Sekolah';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();
$user = get_auth_user();
$totalKuotaKontingen = (int)$pdo->query("SELECT COALESCE(SUM(kuota_max), 0) FROM kategori_peserta")->fetchColumn();

if (!$sekolah) {
    die("Data sekolah tidak ditemukan.");
}

$errors = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi CSRF gagal. Silakan muat ulang halaman.';
    } else {
        $nama_sekolah   = trim($_POST['nama_sekolah'] ?? '');
        $alamat_sekolah = trim($_POST['alamat_sekolah'] ?? '');
        $npsn           = trim($_POST['npsn'] ?? '');
        $gudep_putra    = trim($_POST['gudep_putra'] ?? '');
        $gudep_putri    = trim($_POST['gudep_putri'] ?? '');
        $nama_kamabigus = trim($_POST['nama_kamabigus'] ?? '');
        $nama_pembina   = trim($_POST['nama_pembina'] ?? '');
        $no_wa_pembina  = trim($_POST['no_wa_pembina'] ?? '');
        $email_sekolah  = trim($_POST['email_sekolah'] ?? '');

        if (empty($nama_sekolah)) {
            $errors[] = 'Nama Sekolah wajib diisi.';
        }
        if (empty($alamat_sekolah)) {
            $errors[] = 'Alamat Sekolah wajib diisi.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE sekolah SET
                        nama_sekolah = ?,
                        alamat_sekolah = ?,
                        npsn = ?,
                        gudep_putra = ?,
                        gudep_putri = ?,
                        nama_kamabigus = ?,
                        nama_pembina = ?,
                        no_wa_pembina = ?,
                        email_sekolah = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nama_sekolah,
                    $alamat_sekolah,
                    $npsn,
                    $gudep_putra,
                    $gudep_putri,
                    $nama_kamabigus,
                    $nama_pembina,
                    $no_wa_pembina,
                    $email_sekolah,
                    $sekolah['id']
                ]);

                // Update users table name as well
                $stmtUser = $pdo->prepare("UPDATE users SET nama_lengkap = ?, no_hp = ? WHERE id = ?");
                $stmtUser->execute([$nama_sekolah, $no_wa_pembina, $user['id']]);

                $_SESSION['nama_lengkap'] = $nama_sekolah;

                // Refresh $sekolah
                $sekolah = get_current_sekolah();
                set_flash('success', 'Profil dan informasi pangkalan sekolah berhasil diperbarui!');
                header('Location: ' . base_url('dashboard/sekolah.php'));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($sekolah['nama_sekolah']) ?></title>
    
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
                <h4 class="fw-bold mb-0 text-dark">Data Profil Sekolah</h4>
                <small class="text-muted">Informasi Pangkalan Kontingen yang di-autofill ke formulir pendaftaran</small>
            </div>
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
        <div class="col-lg-8">
            <div class="custom-card p-4 p-md-5">
                <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">
                    <i class="fa-solid fa-school-flag text-success me-2"></i> Formulir Informasi Pangkalan
                </h5>

                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-dark">Nama Sekolah / Pangkalan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_sekolah" class="form-control" value="<?= e($sekolah['nama_sekolah']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-dark">NPSN Sekolah</label>
                            <input type="text" name="npsn" class="form-control" placeholder="Contoh: 5020xxxx" value="<?= e($sekolah['npsn'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Alamat Lengkap Sekolah <span class="text-danger">*</span></label>
                            <textarea name="alamat_sekolah" class="form-control" rows="2" required><?= e($sekolah['alamat_sekolah']) ?></textarea>
                            <div class="form-text small">Alamat ini otomatis di-autofill pada seluruh formulir pendaftaran peserta dan lembar rekap.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Nomor Gudep Putra</label>
                            <input type="text" name="gudep_putra" class="form-control" placeholder="Contoh: 01.001" value="<?= e($sekolah['gudep_putra'] ?? '01.001') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Nomor Gudep Putri</label>
                            <input type="text" name="gudep_putri" class="form-control" placeholder="Contoh: 01.002" value="<?= e($sekolah['gudep_putri'] ?? '01.002') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Nama Kamabigus / Kepala Sekolah</label>
                            <input type="text" name="nama_kamabigus" class="form-control" placeholder="Nama Kepala Sekolah beserta gelar" value="<?= e($sekolah['nama_kamabigus'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Email Resmi Sekolah</label>
                            <input type="email" name="email_sekolah" class="form-control" placeholder="sekolah@sch.id" value="<?= e($sekolah['email_sekolah'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Nama Pembina Pendamping (Bindamping)</label>
                            <input type="text" name="nama_pembina" class="form-control" value="<?= e($sekolah['nama_pembina'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">No. WhatsApp Pembina</label>
                            <input type="text" name="no_wa_pembina" class="form-control" value="<?= e($sekolah['no_wa_pembina'] ?? '') ?>">
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-scout-primary px-4 py-2 fw-semibold">
                                <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="custom-card p-4 bg-light mb-4">
                <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-circle-check text-success me-2"></i> Status Verifikasi</h6>
                <div class="p-3 bg-white rounded-3 border mb-3">
                    <div class="small text-muted mb-1">Status Pendaftaran:</div>
                    <span class="badge bg-<?= $sekolah['status_verifikasi'] === 'terverifikasi' ? 'success' : 'warning text-dark' ?> fs-6">
                        <?= strtoupper($sekolah['status_verifikasi']) ?>
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    Setelah mengisi data kontingen dan peserta sesuai kuota <?= $totalKuotaKontingen ?> orang, panitia akan memverifikasi berkas pangkalan Anda.
                </p>
            </div>

            <div class="custom-card p-4">
                <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-lightbulb text-warning me-2"></i> Fitur Autofill</h6>
                <p class="text-muted small mb-0">
                    Sistem secara otomatis mengisikan <strong>Nama Sekolah</strong> dan <strong>Alamat Sekolah</strong> ketika Anda menambahkan atau mengedit data peserta kontingen.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
