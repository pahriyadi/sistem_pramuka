<?php
/**
 * Quick Database Installer & Verifier
 * Racana Universitas Samawa (UNSA)
 */
require_once __DIR__ . '/config/app.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getDB();
        
        if ($_POST['action'] === 'migrate' || $_POST['action'] === 'install') {
            // First run specific alters if tables exist
            $alterQueries = [
                "ALTER TABLE `mata_lomba` ADD COLUMN `juknis_lengkap` TEXT NULL AFTER `juknis_singkat`",
                "ALTER TABLE `mata_lomba` ADD COLUMN `kriteria_penilaian` TEXT NULL AFTER `juknis_lengkap`",
                "ALTER TABLE `mata_lomba` ADD COLUMN `alat_bahan` TEXT NULL AFTER `kriteria_penilaian`",
                "ALTER TABLE `mata_lomba` ADD COLUMN `jadwal_lomba` VARCHAR(100) DEFAULT 'Hari Ke-2 (16 Nov 2026, 08.00 WITA)' AFTER `alat_bahan`",
                "ALTER TABLE `mata_lomba` ADD COLUMN `kontak_pj` VARCHAR(30) DEFAULT '0852-3891-2345' AFTER `penanggung_jawab`",
                "ALTER TABLE `mata_lomba` ADD COLUMN `icon_lomba` VARCHAR(50) DEFAULT 'fa-trophy' AFTER `lokasi_lomba`",
                "ALTER TABLE `berita_pengumuman` ADD COLUMN `ringkasan` TEXT NULL AFTER `kategori`"
            ];
            foreach ($alterQueries as $q) {
                try {
                    $pdo->exec($q);
                } catch (Exception $e) {
                    // Ignore column already exists
                }
            }

            $sqlPath = __DIR__ . '/database/database.sql';
            if (file_exists($sqlPath)) {
                $sql = file_get_contents($sqlPath);
                $pdo->exec($sql);
                $status = 'success';
                $message = 'Skema database dan seluruh data juknis lomba berhasil diperbarui!';
            }
        } elseif ($_POST['action'] === 'reset') {
            // Reset tables cleanly
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $tablesToDrop = ['peserta_lomba', 'peserta', 'sekolah', 'mata_lomba', 'kategori_peserta', 'pengaturan_portal', 'berita_pengumuman', 'users'];
            foreach ($tablesToDrop as $t) {
                $pdo->exec("DROP TABLE IF EXISTS `$t`;");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $sqlPath = __DIR__ . '/database/database.sql';
            if (file_exists($sqlPath)) {
                $sql = file_get_contents($sqlPath);
                $pdo->exec($sql);
                $status = 'success';
                $message = 'Database berhasil di-reset ulang ke kondisi awal!';
            }
        }
    } catch (Exception $e) {
        $status = 'danger';
        $message = 'Error: ' . $e->getMessage();
    }
}

// Check database table count
$tableCount = 0;
$tables = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
} catch (Exception $e) {
    //
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Verifikasi - Racana Universitas Samawa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0d2818 0%, #04140b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f0fdf4;
            padding: 20px;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 680px;
            width: 100%;
            padding: 35px;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: rgba(234, 179, 8, 0.15);
            color: #facc15;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="setup-card text-center">
    <div class="brand-badge">
        <i class="fa-solid fa-campground"></i> RACANA UNIVERSITAS SAMAWA
    </div>
    <h2 class="fw-bold mb-2 text-white">Inisialisasi &amp; Migrasi Database</h2>
    <p class="text-white-50 small mb-4">Sistem Pendaftaran Online Kemah Bakti &amp; Lomba Pramuka Penegak se-NTB</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $status ?> text-start d-flex align-items-center mb-4">
            <i class="fa-solid <?= $status === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2 fs-5"></i>
            <div class="small"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="bg-dark bg-opacity-50 p-3 rounded-3 text-start mb-4 border border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-white-50 small">Status Database:</span>
            <span class="badge <?= $tableCount > 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= $tableCount > 0 ? "$tableCount Tabel Terdeteksi (Siap Digunakan)" : "Belum Ada Tabel" ?>
            </span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <span class="text-white-50 small">Nama Database:</span>
            <span class="font-monospace text-warning">db_racana_unsa</span>
        </div>
        <?php if (!empty($tables)): ?>
            <div class="mt-3 pt-2 border-top border-secondary border-opacity-25">
                <small class="text-white-50 d-block mb-1">Tabel yang aktif:</small>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($tables as $t): ?>
                        <span class="badge bg-secondary bg-opacity-50 font-monospace" style="font-size: 0.72rem;"><?= $t ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center mb-3">
        <form method="POST" class="flex-grow-1">
            <input type="hidden" name="action" value="migrate">
            <button type="submit" class="btn btn-warning px-3 py-2 fw-semibold w-100">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Update / Sinkronisasi Skema
            </button>
        </form>
        <a href="<?= base_url('index.php') ?>" class="btn btn-outline-light px-3 py-2 flex-grow-1">
            <i class="fa-solid fa-globe me-1"></i> Buka Portal Utama
        </a>
        <a href="<?= base_url('lomba.php') ?>" class="btn btn-outline-success text-white px-3 py-2 flex-grow-1">
            <i class="fa-solid fa-trophy me-1 text-warning"></i> Buka Katalog Lomba
        </a>
    </div>

    <form method="POST" onsubmit="return confirm('Peringatan: Reset database akan mengembalikan seluruh tabel ke kondisi awal. Lanjutkan?')">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn btn-outline-danger btn-sm px-3">
            <i class="fa-solid fa-trash-can me-1"></i> Reset Ulang Database ke Data Awal
        </button>
    </form>

    <div class="mt-4 pt-3 border-top border-white border-opacity-10 text-white-50 small">
        Default Login Admin: <code>admin</code> / Password: <code>admin123</code>
    </div>
</div>

</body>
</html>
