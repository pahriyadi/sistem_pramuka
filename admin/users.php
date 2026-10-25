<?php
/**
 * Kelola Pengguna Sistem (Users) - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Kelola Pengguna (Users)';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();
$currentAuthUser = get_auth_user();
$totalKuotaKontingen = (int)$pdo->query("SELECT COALESCE(SUM(kuota_max), 0) FROM kategori_peserta")->fetchColumn();

$errors = [];
$successMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi sesi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        // 1. TAMBAH PENGGUNA BARU
        if ($postAction === 'create_user') {
            $role         = trim($_POST['role'] ?? 'sekolah');
            $username     = trim($_POST['username'] ?? '');
            $password     = $_POST['password'] ?? '';
            $namaLengkap  = trim($_POST['nama_lengkap'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $noHp         = trim($_POST['no_hp'] ?? '');
            $status       = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';

            // Additional fields for Sekolah
            $namaSekolah   = trim($_POST['nama_sekolah'] ?? '');
            $alamatSekolah = trim($_POST['alamat_sekolah'] ?? '');
            $npsn          = trim($_POST['npsn'] ?? '');
            $gudepPa       = trim($_POST['gudep_putra'] ?? '01.085');
            $gudepPi       = trim($_POST['gudep_putri'] ?? '01.086');
            $namaPembina   = trim($_POST['nama_pembina'] ?? '');
            $noWaPembina   = trim($_POST['no_wa_pembina'] ?? '');

            if (empty($username)) {
                $errors[] = 'Username wajib diisi.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $errors[] = 'Username hanya boleh huruf, angka, dan underscore (3-30 karakter).';
            }

            if (empty($password) || strlen($password) < 6) {
                $errors[] = 'Password minimal terdiri dari 6 karakter.';
            }

            if (empty($namaLengkap)) {
                $errors[] = 'Nama Lengkap / Penanggung Jawab wajib diisi.';
            }

            if ($role === 'sekolah' && empty($namaSekolah)) {
                $errors[] = 'Nama Sekolah wajib diisi untuk pengguna dengan role Sekolah.';
            }

            // Check if username already exists
            if (empty($errors)) {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $checkStmt->execute([$username]);
                if ($checkStmt->fetch()) {
                    $errors[] = "Username <strong>" . e($username) . "</strong> sudah digunakan. Silakan gunakan username lain.";
                }
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                    $stmtInUser = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_hp, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtInUser->execute([$username, $hashedPassword, $namaLengkap, $email, $noHp, $role, $status]);
                    $newUserId = (int)$pdo->lastInsertId();

                    if ($role === 'sekolah') {
                        $stmtInSekolah = $pdo->prepare("INSERT INTO sekolah (user_id, nama_sekolah, npsn, alamat_sekolah, gudep_putra, gudep_putri, nama_pembina, no_wa_pembina, status_verifikasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'terverifikasi')");
                        $stmtInSekolah->execute([
                            $newUserId,
                            $namaSekolah ?: $namaLengkap,
                            $npsn,
                            $alamatSekolah ?: 'Kabupaten Sumbawa',
                            $gudepPa,
                            $gudepPi,
                            $namaPembina ?: $namaLengkap,
                            $noWaPembina ?: $noHp
                        ]);
                    }

                    $pdo->commit();
                    set_flash('success', 'Pengguna baru <strong>' . e($username) . '</strong> (' . strtoupper($role) . ') berhasil ditambahkan!');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal menambahkan pengguna: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT PENGGUNA
        if ($postAction === 'update_user') {
            $userId       = (int)($_POST['user_id'] ?? 0);
            $namaLengkap  = trim($_POST['nama_lengkap'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $noHp         = trim($_POST['no_hp'] ?? '');
            $role         = trim($_POST['role'] ?? 'sekolah');
            $status       = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';

            // Additional fields for Sekolah
            $namaSekolah   = trim($_POST['nama_sekolah'] ?? '');
            $alamatSekolah = trim($_POST['alamat_sekolah'] ?? '');
            $npsn          = trim($_POST['npsn'] ?? '');
            $gudepPa       = trim($_POST['gudep_putra'] ?? '01.085');
            $gudepPi       = trim($_POST['gudep_putri'] ?? '01.086');
            $namaPembina   = trim($_POST['nama_pembina'] ?? '');
            $noWaPembina   = trim($_POST['no_wa_pembina'] ?? '');

            if ($userId <= 0) {
                $errors[] = 'ID Pengguna tidak valid.';
            }
            if (empty($namaLengkap)) {
                $errors[] = 'Nama Lengkap tidak boleh kosong.';
            }

            // Prevent self de-activation or changing own role
            if ($userId === (int)($currentAuthUser['id'] ?? 0)) {
                $status = 'aktif';
                $role   = 'admin';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    $stmtUpUser = $pdo->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, role = ?, status = ? WHERE id = ?");
                    $stmtUpUser->execute([$namaLengkap, $email, $noHp, $role, $status, $userId]);

                    if ($role === 'sekolah') {
                        // Check if sekolah record exists
                        $chkSekolah = $pdo->prepare("SELECT id FROM sekolah WHERE user_id = ?");
                        $chkSekolah->execute([$userId]);
                        $sekId = $chkSekolah->fetchColumn();

                        if ($sekId) {
                            $stmtUpSek = $pdo->prepare("UPDATE sekolah SET nama_sekolah = ?, npsn = ?, alamat_sekolah = ?, gudep_putra = ?, gudep_putri = ?, nama_pembina = ?, no_wa_pembina = ? WHERE id = ?");
                            $stmtUpSek->execute([$namaSekolah ?: $namaLengkap, $npsn, $alamatSekolah, $gudepPa, $gudepPi, $namaPembina, $noWaPembina ?: $noHp, $sekId]);
                        } else {
                            $stmtInSek = $pdo->prepare("INSERT INTO sekolah (user_id, nama_sekolah, npsn, alamat_sekolah, gudep_putra, gudep_putri, nama_pembina, no_wa_pembina, status_verifikasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'terverifikasi')");
                            $stmtInSek->execute([$userId, $namaSekolah ?: $namaLengkap, $npsn, $alamatSekolah, $gudepPa, $gudepPi, $namaPembina, $noWaPembina ?: $noHp]);
                        }
                    }

                    $pdo->commit();
                    set_flash('success', 'Data pengguna berhasil diperbarui!');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal memperbarui pengguna: ' . $e->getMessage();
                }
            }
        }

        // 3. GANTI / RESET PASSWORD
        if ($postAction === 'reset_password') {
            $userId   = (int)($_POST['user_id'] ?? 0);
            $newPass  = $_POST['new_password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';

            if ($userId <= 0) {
                $errors[] = 'Pengguna tidak ditemukan.';
            }
            if (empty($newPass) || strlen($newPass) < 6) {
                $errors[] = 'Password baru minimal harus 6 karakter.';
            }
            if ($newPass !== $confPass) {
                $errors[] = 'Konfirmasi password tidak cocok.';
            }

            if (empty($errors)) {
                try {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmtPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtPass->execute([$hashed, $userId]);

                    set_flash('success', 'Password pengguna berhasil diubah!');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Gagal mereset password: ' . $e->getMessage();
                }
            }
        }

        // 4. TOGGLE STATUS AKTIF / NONAKTIF
        if ($postAction === 'toggle_status') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)($currentAuthUser['id'] ?? 0)) {
                $errors[] = 'Anda tidak dapat menonaktifkan akun yang sedang digunakan saat ini.';
            } else {
                $stmtCur = $pdo->prepare("SELECT status, username FROM users WHERE id = ?");
                $stmtCur->execute([$userId]);
                $uData = $stmtCur->fetch();
                if ($uData) {
                    $newSt = ($uData['status'] === 'aktif') ? 'nonaktif' : 'aktif';
                    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newSt, $userId]);
                    set_flash('info', 'Status akun <strong>' . e($uData['username']) . '</strong> diubah menjadi ' . strtoupper($newSt));
                }
                header('Location: ' . base_url('admin/users.php'));
                exit;
            }
        }

        // 5. HAPUS PENGGUNA
        if ($postAction === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)($currentAuthUser['id'] ?? 0)) {
                $errors[] = 'Anda tidak dapat menghapus akun Anda sendiri.';
            } else {
                $stmtEx = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtEx->execute([$userId]);
                $uName = $stmtEx->fetchColumn();

                if ($uName) {
                    // Delete cascades to sekolah and peserta because of foreign keys
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    set_flash('success', 'Pengguna <strong>' . e($uName) . '</strong> beserta data pendaftarannya berhasil dihapus.');
                }
                header('Location: ' . base_url('admin/users.php'));
                exit;
            }
        }

        // 6. KOSONGKAN DATABASE (RESET DATA TRANSAKSIONAL)
        if ($postAction === 'clean_database_transactions') {
            $confirmText = trim($_POST['confirm_clean_text'] ?? '');
            $cleanScope   = $_POST['clean_scope'] ?? 'transactions_only'; // 'transactions_only' or 'all_except_users'

            if ($confirmText !== 'KOSONGKAN') {
                $errors[] = 'Konfirmasi pembatalan salah. Anda harus mengetikkan kata "<strong>KOSONGKAN</strong>" secara tepat untuk melanjutkan.';
            } else {
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

                    // Always truncate transactional data
                    $pdo->exec("TRUNCATE TABLE `peserta_lomba`;");
                    $pdo->exec("TRUNCATE TABLE `peserta`;");
                    $pdo->exec("TRUNCATE TABLE `pembayaran`;");

                    if ($cleanScope === 'all_except_users') {
                        // Reset all school records back to default or remove non-primary school submissions
                        $pdo->exec("UPDATE `sekolah` SET 
                            `status_verifikasi` = 'menunggu',
                            `status_pembayaran` = 'belum_bayar',
                            `status_kunci` = 'terbuka',
                            `finalized_at` = NULL,
                            `kunci_catatan` = NULL,
                            `catatan_verifikasi` = NULL;
                        ");
                    } else {
                        // Reset school submission statuses while keeping profiles
                        $pdo->exec("UPDATE `sekolah` SET 
                            `status_verifikasi` = 'menunggu',
                            `status_pembayaran` = 'belum_bayar',
                            `status_kunci` = 'terbuka',
                            `finalized_at` = NULL,
                            `kunci_catatan` = NULL,
                            `catatan_verifikasi` = NULL;
                        ");
                    }

                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

                    set_flash('success', 'Database berhasil dibersihkan! Seluruh data peserta (24 orang), peserta lomba, dan transaksi pembayaran telah dikosongkan. Seluruh akun Admin dan Pengguna Sekolah tetap dipertahankan.');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                } catch (Exception $e) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                    $errors[] = 'Gagal mengosongkan database: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Filter & Search Parameters
$filterRole   = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery  = trim($_GET['q'] ?? '');

$sql = "
    SELECT u.*, 
           s.id as sekolah_id,
           s.nama_sekolah,
           s.npsn,
           s.alamat_sekolah,
           s.gudep_putra,
           s.gudep_putri,
           s.nama_pembina,
           s.no_wa_pembina,
           s.status_verifikasi,
           s.status_pembayaran,
           s.status_kunci,
           COUNT(p.id) as total_peserta_terdaftar
    FROM users u
    LEFT JOIN sekolah s ON u.id = s.user_id
    LEFT JOIN peserta p ON s.id = p.sekolah_id AND p.status = 'aktif'
    WHERE 1=1
";
$params = [];

if (!empty($filterRole)) {
    $sql .= " AND u.role = ?";
    $params[] = $filterRole;
}
if (!empty($filterStatus)) {
    $sql .= " AND u.status = ?";
    $params[] = $filterStatus;
}
if (!empty($searchQuery)) {
    $sql .= " AND (u.username LIKE ? OR u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.no_hp LIKE ? OR s.nama_sekolah LIKE ?)";
    $likeQ = '%' . $searchQuery . '%';
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
}

$sql .= " GROUP BY u.id ORDER BY u.role ASC, u.id DESC";

$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$usersList = $stmtUsers->fetchAll();

// Metrics Statistics
$stmtMetrics = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admin,
        SUM(CASE WHEN role = 'sekolah' THEN 1 ELSE 0 END) as total_sekolah,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as total_nonaktif
    FROM users
");
$metrics = $stmtMetrics->fetch() ?: ['total_users' => 0, 'total_admin' => 0, 'total_sekolah' => 0, 'total_nonaktif' => 0];
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
                <h4 class="fw-bold mb-0 text-scout-navy">Kelola Pengguna</h4>
                <small class="text-muted">Manajemen akun Admin dan Kontingen Sekolah</small>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-danger btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalCleanDatabase">
                <i class="fa-solid fa-broom me-1"></i> Bersihkan Data
            </button>
            <button class="btn btn-scout-gold btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
                <i class="fa-solid fa-user-plus me-1"></i> Tambah User
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
                        <div class="text-muted small fw-semibold">Total Pengguna</div>
                        <h3 class="fw-bold text-scout-navy mb-0"><?= (int)$metrics['total_users'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-navy bg-opacity-10 text-scout-navy rounded-circle">
                        <i class="fa-solid fa-users-gear fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-maroon shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Admin Panitia</div>
                        <h3 class="fw-bold text-scout-maroon mb-0"><?= (int)$metrics['total_admin'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-maroon bg-opacity-10 text-scout-maroon rounded-circle">
                        <i class="fa-solid fa-user-shield fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-gold shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Akun Sekolah</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= (int)$metrics['total_sekolah'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-gold bg-opacity-20 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-school fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="custom-card p-3 border-start border-4 border-scout-brown shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Akun Nonaktif</div>
                        <h3 class="fw-bold text-scout-brown mb-0"><?= (int)$metrics['total_nonaktif'] ?></h3>
                    </div>
                    <div class="p-3 bg-scout-brown bg-opacity-10 text-scout-brown rounded-circle">
                        <i class="fa-solid fa-user-slash fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="custom-card p-3 mb-4 shadow-sm">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-lg-5 col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari username, nama, sekolah, email, no HP..." value="<?= e($searchQuery) ?>">
                    <?php if (!empty($searchQuery)): ?>
                        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-outline-secondary" title="Reset Pencarian">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-4">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Role (Admin &amp; Sekolah)</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Role: Administrator</option>
                    <option value="sekolah" <?= $filterRole === 'sekolah' ? 'selected' : '' ?>>Role: Kontingen Sekolah</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Status: Aktif</option>
                    <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Status: Nonaktif</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <button type="submit" class="btn btn-scout-navy w-100 fw-bold">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="custom-card p-0 shadow-sm overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-scout-navy text-white">
                    <tr>
                        <th class="ps-4" style="width: 60px;">ID</th>
                        <th>Pengguna &amp; Username</th>
                        <th>Role &amp; Pangkalan Sekolah</th>
                        <th>Kontak &amp; Email</th>
                        <th class="text-center">Peserta</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4" style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-user-xmark fs-1 text-secondary opacity-50 d-block mb-3"></i>
                                Tidak ditemukan data pengguna sesuai kriteria pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <?php 
                            $isAdmin = ($u['role'] === 'admin');
                            $isSelf  = ((int)$u['id'] === (int)($currentAuthUser['id'] ?? 0));
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?= (int)$u['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px; background: <?= $isAdmin ? 'linear-gradient(135deg, #800000, #5d3a00)' : 'linear-gradient(135deg, #0b2545, #d97706)' ?>;">
                                            <i class="fa-solid <?= $isAdmin ? 'fa-user-shield' : 'fa-school' ?> fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0">
                                                <?= e($u['nama_lengkap']) ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Akun Anda</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="font-monospace text-muted small">
                                                <i class="fa-solid fa-at text-warning me-1"></i><?= e($u['username']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($isAdmin): ?>
                                        <span class="badge bg-scout-maroon text-white px-2 py-1"><i class="fa-solid fa-shield-halved me-1"></i> Administrator</span>
                                        <small class="text-muted d-block mt-1">Panitia Pelaksana Racana UNSA</small>
                                    <?php else: ?>
                                        <span class="badge bg-scout-gold text-dark px-2 py-1 fw-bold"><i class="fa-solid fa-school me-1"></i> Kontingen Sekolah</span>
                                        <div class="fw-semibold text-scout-navy small mt-1">
                                            <?= e($u['nama_sekolah'] ?: 'Gudep Sekolah') ?>
                                        </div>
                                        <?php if (!empty($u['gudep_putra']) || !empty($u['gudep_putri'])): ?>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Gudep: <?= e($u['gudep_putra']) ?> / <?= e($u['gudep_putri']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="small">
                                        <?php if (!empty($u['email'])): ?>
                                            <div class="text-truncate" style="max-width: 200px;">
                                                <i class="fa-solid fa-envelope text-muted me-1"></i> <?= e($u['email']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($u['no_hp'])): ?>
                                            <div>
                                                <i class="fa-brands fa-whatsapp text-success me-1"></i> <?= e($u['no_hp']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($u['email']) && empty($u['no_hp'])): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <?php if (!$isAdmin): ?>
                                        <span class="badge bg-info bg-opacity-15 text-primary fw-bold font-monospace px-2 py-1">
                                            <?= (int)$u['total_peserta_terdaftar'] ?> / 24
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-check me-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="post_action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $u['status'] === 'aktif' ? 'btn-success' : 'btn-outline-danger' ?> py-1 px-2" style="font-size: 0.75rem;" title="Klik untuk mengubah status aktif/nonaktif">
                                                <i class="fa-solid <?= $u['status'] === 'aktif' ? 'fa-check' : 'fa-ban' ?> me-1"></i>
                                                <?= $u['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit User Button -->
                                        <button type="button" class="btn btn-outline-primary btn-edit-user"
                                            data-id="<?= $u['id'] ?>"
                                            data-username="<?= e($u['username']) ?>"
                                            data-nama="<?= e($u['nama_lengkap']) ?>"
                                            data-email="<?= e($u['email']) ?>"
                                            data-nohp="<?= e($u['no_hp']) ?>"
                                            data-role="<?= e($u['role']) ?>"
                                            data-status="<?= e($u['status']) ?>"
                                            data-sekolah="<?= e($u['nama_sekolah']) ?>"
                                            data-alamat="<?= e($u['alamat_sekolah']) ?>"
                                            data-npsn="<?= e($u['npsn']) ?>"
                                            data-gudeppa="<?= e($u['gudep_putra']) ?>"
                                            data-gudeppi="<?= e($u['gudep_putri']) ?>"
                                            data-pembina="<?= e($u['nama_pembina']) ?>"
                                            data-nowapembina="<?= e($u['no_wa_pembina']) ?>"
                                            title="Edit Data Pengguna">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Reset Password Button -->
                                        <button type="button" class="btn btn-outline-warning btn-reset-pass"
                                            data-id="<?= $u['id'] ?>"
                                            data-username="<?= e($u['username']) ?>"
                                            title="Reset Password">
                                            <i class="fa-solid fa-key"></i>
                                        </button>

                                        <?php if (!$isSelf): ?>
                                            <!-- Delete Button -->
                                            <button type="button" class="btn btn-outline-danger btn-delete-user"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= e($u['username']) ?>"
                                                data-nama="<?= e($u['nama_lengkap']) ?>"
                                                title="Hapus Pengguna">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
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
<!-- MODAL 1: TAMBAH PENGGUNA BARU              -->
<!-- ========================================== -->
<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-labelledby="modalTambahUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="create_user">

                <div class="modal-header bg-scout-navy text-white">
                    <h5 class="modal-title fw-bold" id="modalTambahUserLabel">
                        <i class="fa-solid fa-user-plus me-2 text-scout-gold"></i> Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Role / Hak Akses <span class="text-danger">*</span></label>
                            <select name="role" id="add_role_select" class="form-select" required>
                                <option value="sekolah" selected>Kontingen Sekolah (Peserta)</option>
                                <option value="admin">Administrator (Panitia Pelaksana)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Status Akun <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="aktif">Aktif (Dapat Login)</option>
                                <option value="nonaktif">Nonaktif (Dibekukan)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control font-monospace" placeholder="Contoh: sman1sumbawa" required autocomplete="off">
                            <div class="form-text small">Huruf, angka, dan underscore tanpa spasi.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Password Login <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Nama Lengkap / Nama Penanggungjawab <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Pembina SMA Negeri 1 Sumbawa / Kak Ahmad" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Alamat Email</label>
                            <input type="email" name="email" class="form-control" placeholder="nama@sekolah.sch.id">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor WhatsApp / HP</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>

                        <!-- SECTION KHUSUS SEKOLAH -->
                        <div id="add_sekolah_section" class="col-12 p-3 bg-light rounded-3 border mt-3">
                            <h6 class="fw-bold text-scout-navy mb-3"><i class="fa-solid fa-school text-scout-gold me-2"></i> Informasi Pangkalan Sekolah</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold small text-scout-navy">Nama Sekolah / Pangkalan</label>
                                    <input type="text" name="nama_sekolah" class="form-control" placeholder="Contoh: SMA Negeri 1 Sumbawa Besar">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-scout-navy">NPSN</label>
                                    <input type="text" name="npsn" class="form-control" placeholder="5020xxxx">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-scout-navy">Alamat Lengkap Sekolah</label>
                                    <textarea name="alamat_sekolah" class="form-control" rows="2" placeholder="Jl. Garuda No. 10, Sumbawa Besar"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-scout-navy">Nomor Gudep Putra</label>
                                    <input type="text" name="gudep_putra" class="form-control" value="01.085">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-scout-navy">Nomor Gudep Putri</label>
                                    <input type="text" name="gudep_putri" class="form-control" value="01.086">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-gold px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-user-plus me-1"></i> Simpan Pengguna Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 2: EDIT DATA PENGGUNA               -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-labelledby="modalEditUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id" value="">

                <div class="modal-header bg-scout-maroon text-white">
                    <h5 class="modal-title fw-bold" id="modalEditUserLabel">
                        <i class="fa-solid fa-user-pen me-2 text-scout-gold"></i> Edit Data Pengguna
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Username (Tetap)</label>
                            <input type="text" id="edit_username" class="form-control font-monospace bg-light" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-scout-navy">Role / Hak Akses</label>
                            <select name="role" id="edit_role_select" class="form-select">
                                <option value="sekolah">Sekolah</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-scout-navy">Status Akun</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">No. WhatsApp / HP</label>
                            <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                        </div>

                        <!-- SECTION KHUSUS SEKOLAH DI EDIT -->
                        <div id="edit_sekolah_section" class="col-12 p-3 bg-light rounded-3 border mt-3">
                            <h6 class="fw-bold text-scout-navy mb-3"><i class="fa-solid fa-school text-scout-gold me-2"></i> Informasi Pangkalan Sekolah</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold small text-scout-navy">Nama Sekolah</label>
                                    <input type="text" name="nama_sekolah" id="edit_nama_sekolah" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold small text-scout-navy">NPSN</label>
                                    <input type="text" name="npsn" id="edit_npsn" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-scout-navy">Alamat Lengkap Sekolah</label>
                                    <textarea name="alamat_sekolah" id="edit_alamat_sekolah" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-scout-navy">Gudep Putra</label>
                                    <input type="text" name="gudep_putra" id="edit_gudep_putra" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-scout-navy">Gudep Putri</label>
                                    <input type="text" name="gudep_putri" id="edit_gudep_putri" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-scout-maroon px-4 fw-bold text-white shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Perbarui Data Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 3: RESET PASSWORD PENGGUNA          -->
<!-- ========================================== -->
<div class="modal fade" id="modalResetPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="reset_password">
                <input type="hidden" name="user_id" id="pass_user_id" value="">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-key me-2"></i> Reset Password Pengguna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Ganti password untuk pengguna: <strong id="pass_username" class="text-dark font-monospace"></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-scout-navy">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-scout-navy">Ulangi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ketik ulang password baru" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">
                        <i class="fa-solid fa-key me-1"></i> Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 4: HAPUS PENGGUNA                   -->
<!-- ========================================== -->
<div class="modal fade" id="modalDeleteUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id" value="">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Akun Pengguna
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fa-solid fa-user-xmark text-danger fs-1 mb-3"></i>
                    <h6 class="fw-bold text-dark">Apakah Anda yakin ingin menghapus akun ini?</h6>
                    <div class="p-3 bg-light rounded-3 my-2">
                        <strong id="delete_username" class="font-monospace text-danger d-block"></strong>
                        <small id="delete_nama" class="text-muted"></small>
                    </div>
                    <p class="text-danger small mt-2 fw-semibold">
                        Peringatan: Seluruh data kontingen dan peserta yang terafiliasi dengan akun ini akan terhapus permanen.
                    </p>
                </div>
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL 5: KOSONGKAN DATABASE (DATA TRANSAKSI)-->
<!-- ========================================== -->
<div class="modal fade" id="modalCleanDatabase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="clean_database_transactions">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-broom me-2 text-warning"></i> Kosongkan Data Transaksional Database
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger mb-3">
                        <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> PERHATIAN &amp; KEBIJAKAN PEMBERSIHAN DATA:</h6>
                        <p class="small mb-0">
                            Fitur ini akan <strong>MENGOSONGKAN SELURUH DATA TRANSAKSIONAL</strong> kegiatan (data pendaftaran peserta, cabang lomba yang diikuti, dan transaksi pembayaran).
                        </p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark mb-2">Tabel yang akan dikosongkan (TRUNCATE):</h6>
                            <ul class="list-group small mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-users text-danger me-2"></i> Tabel <code>peserta</code> (Data maksimal <?= $totalKuotaKontingen ?> orang per kontingen)</span>
                                    <span class="badge bg-danger">Dikosongkan</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-trophy text-danger me-2"></i> Tabel <code>peserta_lomba</code> (Relasi Partisipasi Mata Lomba Peserta)</span>
                                    <span class="badge bg-danger">Dikosongkan</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-credit-card text-danger me-2"></i> Tabel <code>pembayaran</code> (Konfirmasi Transfer Bank &amp; QRIS)</span>
                                    <span class="badge bg-danger">Dikosongkan</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                    <span><i class="fa-solid fa-user-check text-success me-2"></i> Tabel <code>users</code> (Akun Admin &amp; Akun Kontingen Sekolah)</span>
                                    <span class="badge bg-success">TETAP DISIMPAN &amp; AMAN</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                    <span><i class="fa-solid fa-sliders text-success me-2"></i> Tabel Master (<code>mata_lomba</code>, <code>dokumen_kegiatan</code>, <code>pengaturan_portal</code>)</span>
                                    <span class="badge bg-success">TETAP DISIMPAN &amp; AMAN</span>
                                </li>
                            </ul>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">
                                Ketik kata <span class="text-danger font-monospace fs-6">KOSONGKAN</span> untuk konfirmasi: <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="confirm_clean_text" class="form-control font-monospace border-danger text-center fw-bold" placeholder="Ketik KOSONGKAN di sini" required autocomplete="off">
                            <div class="form-text text-danger small">Tindakan ini tidak dapat dibatalkan setelah dieksekusi.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-broom me-1"></i> Eksekusi Pengosongan Database
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
    // Add User Role Toggle
    const addRoleSelect = document.getElementById('add_role_select');
    const addSekolahSection = document.getElementById('add_sekolah_section');
    if (addRoleSelect && addSekolahSection) {
        addRoleSelect.addEventListener('change', function() {
            addSekolahSection.style.display = (this.value === 'sekolah') ? 'block' : 'none';
        });
    }

    // Edit User Role Toggle
    const editRoleSelect = document.getElementById('edit_role_select');
    const editSekolahSection = document.getElementById('edit_sekolah_section');
    if (editRoleSelect && editSekolahSection) {
        editRoleSelect.addEventListener('change', function() {
            editSekolahSection.style.display = (this.value === 'sekolah') ? 'block' : 'none';
        });
    }

    // Edit User Modal Binding
    const editUserModal = new bootstrap.Modal(document.getElementById('modalEditUser'));
    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const d = this.dataset;
            document.getElementById('edit_user_id').value = d.id;
            document.getElementById('edit_username').value = d.username;
            document.getElementById('edit_nama_lengkap').value = d.nama;
            document.getElementById('edit_email').value = d.email;
            document.getElementById('edit_no_hp').value = d.nohp;
            document.getElementById('edit_role_select').value = d.role;
            document.getElementById('edit_status').value = d.status;

            document.getElementById('edit_nama_sekolah').value = d.sekolah || d.nama;
            document.getElementById('edit_alamat_sekolah').value = d.alamat || '';
            document.getElementById('edit_npsn').value = d.npsn || '';
            document.getElementById('edit_gudep_putra').value = d.gudeppa || '01.085';
            document.getElementById('edit_gudep_putri').value = d.gudeppi || '01.086';

            if (editSekolahSection) {
                editSekolahSection.style.display = (d.role === 'sekolah') ? 'block' : 'none';
            }

            editUserModal.show();
        });
    });

    // Reset Password Modal Binding
    const resetPassModal = new bootstrap.Modal(document.getElementById('modalResetPassword'));
    document.querySelectorAll('.btn-reset-pass').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('pass_user_id').value = this.dataset.id;
            document.getElementById('pass_username').innerText = this.dataset.username;
            resetPassModal.show();
        });
    });

    // Delete User Modal Binding
    const deleteUserModal = new bootstrap.Modal(document.getElementById('modalDeleteUser'));
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_user_id').value = this.dataset.id;
            document.getElementById('delete_username').innerText = '@' + this.dataset.username;
            document.getElementById('delete_nama').innerText = this.dataset.nama;
            deleteUserModal.show();
        });
    });
});
</script>
</body>
</html>
