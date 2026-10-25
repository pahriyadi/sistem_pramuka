<?php
/**
 * Registrasi Akun Sekolah / Pangkalan Kontingen
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Registrasi Akun Sekolah';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

// If already logged in, redirect
if (is_logged_in()) {
    if (get_user_role() === 'admin') {
        header('Location: ' . base_url('admin/index.php'));
    } else {
        header('Location: ' . base_url('dashboard/index.php'));
    }
    exit;
}

$errors = [];
$nama_sekolah = '';
$alamat_sekolah = '';
$username = '';
$nama_pembina = '';
$no_wa_pembina = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi sesi keamanan (CSRF) gagal. Silakan coba kembali.';
    }

    $nama_sekolah   = trim($_POST['nama_sekolah'] ?? '');
    $alamat_sekolah = trim($_POST['alamat_sekolah'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $nama_pembina   = trim($_POST['nama_pembina'] ?? '');
    $no_wa_pembina  = trim($_POST['no_wa_pembina'] ?? '');

    // Validations
    if (empty($nama_sekolah)) {
        $errors[] = 'Nama Sekolah wajib diisi.';
    }
    if (empty($alamat_sekolah)) {
        $errors[] = 'Alamat Sekolah wajib diisi.';
    }
    if (empty($username)) {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $errors[] = 'Username hanya boleh berupa huruf, angka, dan underscore (_) dengan panjang 4-30 karakter.';
    }
    if (empty($password)) {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal terdiri dari 6 karakter.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDB();

            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                $errors[] = "Username <strong>" . e($username) . "</strong> sudah terdaftar. Silakan pilih username lain.";
            } else {
                // Begin Transaction
                $pdo->beginTransaction();

                // 1. Insert User
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtUser = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, no_hp, role, status) VALUES (?, ?, ?, ?, 'sekolah', 'aktif')");
                $stmtUser->execute([$username, $hashedPassword, $nama_sekolah, $no_wa_pembina]);
                $userId = (int)$pdo->lastInsertId();

                // 2. Insert Sekolah
                $stmtSekolah = $pdo->prepare("INSERT INTO sekolah (user_id, nama_sekolah, alamat_sekolah, nama_pembina, no_wa_pembina, status_verifikasi) VALUES (?, ?, ?, ?, ?, 'menunggu')");
                $stmtSekolah->execute([$userId, $nama_sekolah, $alamat_sekolah, $nama_pembina, $no_wa_pembina]);

                $pdo->commit();

                // Set session auto-login
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['nama_lengkap'] = $nama_sekolah;
                $_SESSION['role'] = 'sekolah';

                set_flash('success', 'Pendaftaran akun sekolah berhasil! Selamat datang di Portal Kontingen Racana UNSA.');
                header('Location: ' . base_url('dashboard/index.php'));
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="py-5 bg-light min-vh-100 d-flex align-items-center">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="custom-card p-4 p-md-5 bg-white shadow-sm border" style="border-radius: 20px;">
                    <div class="text-center mb-4">
                        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 58px; height: 58px; background: linear-gradient(135deg, #0b2545 0%, #800000 100%) !important;">
                            <i class="fa-solid fa-school fs-4 text-warning"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Registrasi Akun Sekolah</h4>
                        <p class="text-muted small mb-0">Daftarkan pangkalan sekolah untuk pendaftaran kontingen online</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= $err ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <!-- NAMA SEKOLAH -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-building-columns text-success me-1"></i> Nama Sekolah / Pangkalan <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="nama_sekolah" class="form-control" placeholder="Contoh: SMA Negeri 1 Sumbawa Besar" value="<?= e($nama_sekolah) ?>" required>
                                <div class="form-text small">Nama resmi SMA/SMK/MA pangkalan Gugus Depan Anda.</div>
                            </div>

                            <!-- ALAMAT SEKOLAH -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i> Alamat Sekolah <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat_sekolah" class="form-control" rows="2" placeholder="Contoh: Jl. Garuda No. 10, Sumbawa Besar, Kab. Sumbawa, NTB" required><?= e($alamat_sekolah) ?></textarea>
                                <div class="form-text small">Alamat lengkap sekolah (akan di-autofill otomatis ke formulir pendaftaran peserta).</div>
                            </div>

                            <!-- NAMA PEMBINA & NO WA -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-user-tie text-primary me-1"></i> Nama Pembina Pendamping
                                </label>
                                <input type="text" name="nama_pembina" class="form-control" placeholder="Contoh: Kak Herman, S.Pd" value="<?= e($nama_pembina) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> No. WhatsApp Pembina
                                </label>
                                <input type="text" name="no_wa_pembina" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e($no_wa_pembina) ?>">
                            </div>

                            <div class="col-12"><hr class="my-2 text-muted"></div>

                            <!-- USERNAME -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-user-lock text-warning me-1"></i> Username Akun Login <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="username" class="form-control font-monospace" placeholder="Contoh: sman1sumbawa" value="<?= e($username) ?>" required autocomplete="off">
                                <div class="form-text small">Gunakan kombinasi huruf/angka tanpa spasi untuk masuk ke portal.</div>
                            </div>

                            <!-- PASSWORD & CONFIRMATION -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-key text-secondary me-1"></i> Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark">
                                    <i class="fa-solid fa-lock-open text-secondary me-1"></i> Ulangi Password <span class="text-danger">*</span>
                                </label>
                                <input type="password" name="password_confirm" class="form-control" placeholder="Ketik ulang password" required>
                            </div>

                            <!-- SUBMIT -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-scout-gold w-100 py-2 fs-6 fw-bold">
                                    <i class="fa-solid fa-user-plus me-2"></i> Buat Akun &amp; Lanjutkan ke Pendaftaran
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top small">
                        Sudah memiliki akun pangkalan sekolah? 
                        <a href="<?= base_url('auth/login.php') ?>" class="text-success fw-bold text-decoration-none">
                            Login Portal di Sini <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
