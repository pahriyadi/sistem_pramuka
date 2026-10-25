<?php
/**
 * Dashboard & Admin Sidebar Navigation
 * Racana Universitas Samawa (UNSA)
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$userRole = get_user_role();
$user = get_auth_user();
$namaBrand = get_setting('nama_brand', 'RACANA UNSA');
$sekolah = ($userRole === 'sekolah') ? get_current_sekolah() : null;
?>
<aside class="sidebar-wrapper">
    <div class="sidebar-header">
        <div class="d-flex align-items-center gap-2">
            <div class="text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; background: linear-gradient(135deg, #0b2545 0%, #800000 100%) !important;">
                <i class="fa-solid fa-campground text-warning fs-5"></i>
            </div>
            <div>
                <div class="fw-bold text-white fs-6"><?= e($namaBrand) ?></div>
                <small class="text-warning fw-semibold d-block" style="font-size: 0.72rem;">
                    <?= $userRole === 'admin' ? 'PANEL ADMINISTRATOR' : 'PORTAL KONTINGEN' ?>
                </small>
            </div>
        </div>

        <!-- User Quick Card -->
        <div class="mt-3 p-2 rounded-3 bg-dark bg-opacity-50 border border-white border-opacity-10 d-flex align-items-center gap-2">
            <div class="rounded-circle bg-secondary bg-opacity-25 text-white d-flex align-items-center justify-content-center" style="width:34px; height:34px;">
                <i class="fa-solid <?= $userRole === 'admin' ? 'fa-user-shield text-warning' : 'fa-school text-warning' ?>"></i>
            </div>
            <div class="overflow-hidden">
                <div class="text-white text-truncate fw-semibold small">
                    <?= e($sekolah['nama_sekolah'] ?? $user['nama_lengkap'] ?? $user['username'] ?? 'User') ?>
                </div>
                <div class="text-white-50" style="font-size: 0.7rem;">
                    <?= $userRole === 'admin' ? 'Panitia Pelaksana' : 'Gudep Sekolah' ?>
                </div>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if ($userRole === 'admin'): ?>
            <!-- ADMIN MENU -->
            <li class="menu-category">Menu Utama Panitia</li>
            <li class="nav-item">
                <a href="<?= base_url('admin/index.php') ?>" class="nav-link <?= ($currentPage === 'index.php' && $currentDir === 'admin') ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard Panitia
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/pembayaran.php') ?>" class="nav-link <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-credit-card text-warning"></i> Verifikasi Pembayaran
                    <?php 
                        try {
                            $cntPending = (int)getDB()->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'menunggu_konfirmasi'")->fetchColumn();
                            if ($cntPending > 0) {
                                echo '<span class="badge bg-danger ms-auto">' . $cntPending . '</span>';
                            }
                        } catch (Exception $e) {}
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/kontingen.php') ?>" class="nav-link <?= $currentPage === 'kontingen.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-school"></i> Data Kontingen Sekolah
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/peserta-all.php') ?>" class="nav-link <?= $currentPage === 'peserta-all.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i> Semua Data Peserta
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/users.php') ?>" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear text-warning"></i> Kelola Pengguna (Users)
                </a>
            </li>

            <li class="menu-category">Master Data Referensi</li>
            <li class="nav-item">
                <a href="<?= base_url('admin/mata-lomba.php') ?>" class="nav-link <?= $currentPage === 'mata-lomba.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-trophy text-warning"></i> Kelola Mata Lomba
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/dokumen.php') ?>" class="nav-link <?= $currentPage === 'dokumen.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-folder-open text-warning"></i> Kelola Dokumen Unduhan
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/berita.php') ?>" class="nav-link <?= $currentPage === 'berita.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-newspaper text-warning"></i> Kelola Berita &amp; Pengumuman
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/cetak-rekap.php') ?>" class="nav-link <?= $currentPage === 'cetak-rekap.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-print"></i> Cetak Rekapitulasi
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('admin/pengaturan.php') ?>" class="nav-link <?= $currentPage === 'pengaturan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders text-info"></i> Pengaturan &amp; Branding
                </a>
            </li>
        <?php else: ?>
            <!-- SEKOLAH / KONTINGEN MENU -->
            <li class="menu-category">Pendaftaran Kontingen</li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/index.php') ?>" class="nav-link <?= ($currentPage === 'index.php' && $currentDir === 'dashboard') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i> Ringkasan Kontingen
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="nav-link <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-wallet text-warning"></i> Pembayaran Kontingen
                    <?php 
                        $stBayar = $sekolah['status_pembayaran'] ?? 'belum_bayar';
                        if ($stBayar === 'disetujui') {
                            echo '<span class="badge bg-success ms-auto" style="font-size: 0.65rem;">Lunas</span>';
                        } elseif ($stBayar === 'menunggu_konfirmasi') {
                            echo '<span class="badge bg-primary ms-auto" style="font-size: 0.65rem;">Menunggu</span>';
                        } elseif ($stBayar === 'ditolak') {
                            echo '<span class="badge bg-danger ms-auto" style="font-size: 0.65rem;">Ditolak</span>';
                        } else {
                            echo '<span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem;">Belum</span>';
                        }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/sekolah.php') ?>" class="nav-link <?= $currentPage === 'sekolah.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-school-flag"></i> Data Profil Sekolah
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/peserta.php') ?>" class="nav-link <?= $currentPage === 'peserta.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-line"></i> Data Peserta
                    <?php if (($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci'): ?>
                        <span class="badge bg-danger ms-auto" style="font-size: 0.65rem;"><i class="fa-solid fa-lock"></i> Final</span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="nav-link <?= $currentPage === 'peserta-tambah.php' ? 'active' : '' ?>">
                    <i class="fa-solid <?= (($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci') ? 'fa-eye text-info' : 'fa-layer-group text-warning' ?>"></i> <?= (($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci') ? 'Lihat Formulir' : 'Pendaftaran Multi-Batch' ?>
                </a>
            </li>

            <li class="menu-category">Dokumen & Cetak</li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/dokumen.php') ?>" class="nav-link <?= $currentPage === 'dokumen.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-cloud-arrow-down text-warning"></i> Unduh Dokumen &amp; Juknis
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" class="nav-link <?= $currentPage === 'cetak-idcard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-id-card-clip text-info"></i> Cetak ID Card Peserta
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" class="nav-link <?= $currentPage === 'cetak-formulir.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice text-success"></i> Cetak Formulir Rekap (A.01)
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= base_url('dashboard/jadwal-kegiatan.php') ?>" class="nav-link <?= $currentPage === 'jadwal-kegiatan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i> Jadwal Pelaksanaan
                </a>
            </li>
        <?php endif; ?>

        <li class="menu-category">Lainnya</li>
        <li class="nav-item">
            <a href="<?= base_url('index.php') ?>" class="nav-link" target="_blank">
                <i class="fa-solid fa-globe"></i> Lihat Portal Publik <i class="fa-solid fa-arrow-up-right-from-square small ms-auto text-white-50"></i>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= base_url('auth/logout.php') ?>" class="nav-link text-danger">
                <i class="fa-solid fa-right-from-bracket"></i> Keluar / Logout
            </a>
        </li>
    </ul>
</aside>
