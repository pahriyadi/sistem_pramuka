<?php
/**
 * Login Portal Kontingen & Admin Panitia
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Login Portal Kontingen';
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

$error = '';
$username = '';
$waNumber = get_whatsapp_number();
$waHelpText = rawurlencode('Halo Panitia ' . get_setting('nama_brand', 'RACANA UNSA') . ', saya lupa password akun sekolah.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Validasi sesi keamanan (CSRF) gagal. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan Password wajib diisi.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'aktif') {
                        $error = 'Akun Anda sedang dinonaktifkan oleh administrator. Silakan hubungi panitia.';
                    } else {
                        // Successful login
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['role'] = $user['role'];

                        set_flash('success', 'Selamat datang kembali, ' . e($user['nama_lengkap']) . '!');

                        if ($user['role'] === 'admin') {
                            header('Location: ' . base_url('admin/index.php'));
                        } else {
                            header('Location: ' . base_url('dashboard/index.php'));
                        }
                        exit;
                    }
                } else {
                    $error = 'Username atau Password yang Anda masukkan salah.';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="py-5 bg-light min-vh-100 d-flex align-items-center">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-8">
                <div class="custom-card p-4 p-md-5 bg-white shadow-sm border" style="border-radius: 20px;">
                    <div class="text-center mb-4">
                        <div class="text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 58px; height: 58px; background: linear-gradient(135deg, #0b2545 0%, #800000 100%) !important;">
                            <i class="fa-solid fa-campground fs-4 text-warning"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Login Portal</h4>
                        <p class="text-muted small mb-0">Masuk untuk mengelola pendaftaran kontingen sekolah</p>
                    </div>

                    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                            <div class="small"><?= $error ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">
                                <i class="fa-solid fa-user text-success me-1"></i> Username
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username sekolah / admin" value="<?= e($username) ?>" required autofocus autocomplete="username">
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold small text-dark mb-0">
                                    <i class="fa-solid fa-key text-secondary me-1"></i> Password
                                </label>
                                <?php if ($waNumber !== ''): ?>
                                    <a href="https://wa.me/<?= e($waNumber) ?>?text=<?= e($waHelpText) ?>" target="_blank" rel="noopener noreferrer" class="text-muted small text-decoration-none" style="font-size: 0.75rem;">
                                        Lupa Password?
                                    </a>
                                <?php endif; ?>
                            </div>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan password Anda" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-scout-primary w-100 py-2 fs-6 fw-bold">
                            <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk ke Portal
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top small">
                        Belum memiliki akun kontingen sekolah? <br>
                        <a href="<?= base_url('auth/register.php') ?>" class="text-scout-navy fw-bold text-decoration-none">
                            Daftarkan Pangkalan Sekolah Anda Sekarang <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded-3 text-center border">
                        <small class="text-muted d-block fw-semibold mb-1"><i class="fa-solid fa-shield-halved text-success me-1"></i> Akun Demo Pengujian:</small>
                        <small class="text-muted d-block font-monospace">Admin: <code>admin</code> / <code>admin123</code></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
