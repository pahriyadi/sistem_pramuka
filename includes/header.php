<?php
/**
 * Public Portal Header
 * Racana Universitas Samawa (UNSA)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$appName = get_setting('nama_racana', 'Racana Universitas Samawa');
$brandName = get_setting('nama_brand', 'RACANA UNSA');
$universityName = get_setting('nama_universitas', 'Universitas Samawa');
$portalDescription = get_setting('deskripsi_portal', 'Pusat informasi kegiatan dan pendaftaran online.');
$userRole = get_user_role();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= e($appName) ?></title>
    
    <!-- Meta SEO -->
    <meta name="description" content="<?= e($portalDescription) ?>">
    <meta name="keywords" content="<?= e($brandName . ', ' . $universityName . ', Pramuka, kegiatan') ?>">
    <meta name="author" content="<?= e($appName) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= e(get_favicon_url()) ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body>

<!-- Top Notification Bar -->
<div class="bg-dark text-white py-2 px-3 small border-bottom border-warning border-opacity-25 d-none d-md-block" style="background-color: #07172b !important;">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4">
            <span><i class="fa-solid fa-location-dot text-warning me-1"></i> <?= e(get_setting('lokasi_kegiatan', get_setting('alamat_racana', ''))) ?></span>
            <span><i class="fa-solid fa-calendar-check text-warning me-1"></i> <?= e(get_setting('tgl_pelaksanaan_kemah', '15 - 19 November 2026')) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span><i class="fa-brands fa-whatsapp text-success me-1"></i> Helpdesk: <strong><?= e(get_setting('kontak_wa', '0852-3891-2345')) ?></strong></span>
        </div>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-scout sticky-top py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('index.php') ?>">
            <?php 
                $customLogo = get_setting('logo_utama', '');
                $customLogoPath = dirname(__DIR__) . '/uploads/branding/' . $customLogo;
                if (!empty($customLogo) && file_exists($customLogoPath)): 
            ?>
                <img src="<?= base_url('uploads/branding/' . $customLogo) ?>" alt="Logo Racana" style="height: 40px; width: auto; object-fit: contain;">
            <?php else: ?>
                <div class="text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; background: linear-gradient(135deg, #0b2545 0%, #800000 100%) !important;">
                    <i class="fa-solid fa-campground fs-5 text-warning"></i>
                </div>
            <?php endif; ?>
            <div>
                <div class="brand-title"><?= e($brandName) ?></div>
                <div class="brand-subtitle"><?= e($universityName) ?></div>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarPortal" aria-controls="navbarPortal" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarPortal">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= base_url('index.php') ?>">
                        <i class="fa-solid fa-house me-1 opacity-75"></i> Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= $currentPage === 'tentang.php' ? 'active' : '' ?>" href="<?= base_url('tentang.php') ?>">
                        <i class="fa-solid fa-circle-info me-1 opacity-75"></i> Profil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= ($currentPage === 'lomba.php' || $currentPage === 'lomba-detail.php') ? 'active' : '' ?>" href="<?= base_url('lomba.php') ?>">
                        <i class="fa-solid fa-trophy me-1 opacity-75"></i> Lomba
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= $currentPage === 'petunjuk-teknis.php' ? 'active' : '' ?>" href="<?= base_url('petunjuk-teknis.php') ?>">
                        <i class="fa-solid fa-file-shield me-1 opacity-75"></i> Juknis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= $currentPage === 'unduh-dokumen.php' ? 'active' : '' ?>" href="<?= base_url('unduh-dokumen.php') ?>">
                        <i class="fa-solid fa-cloud-arrow-down me-1 opacity-75"></i> Dokumen
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= ($currentPage === 'berita.php' || $currentPage === 'berita-detail.php') ? 'active' : '' ?>" href="<?= base_url('berita.php') ?>">
                        <i class="fa-solid fa-bullhorn me-1 opacity-75"></i> Warta
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= $currentPage === 'kontak.php' ? 'active' : '' ?>" href="<?= base_url('kontak.php') ?>">
                        <i class="fa-solid fa-headset me-1 opacity-75"></i> Kontak
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php if (is_logged_in()): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="<?= base_url('admin/index.php') ?>" class="btn btn-scout-gold btn-sm px-3 fw-semibold">
                            <i class="fa-solid fa-gauge-high me-1"></i> Admin
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('dashboard/index.php') ?>" class="btn btn-scout-primary btn-sm px-3 fw-semibold">
                            <i class="fa-solid fa-id-card me-1"></i> Dashboard
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-outline-danger btn-sm" title="Keluar">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline-success btn-sm px-3 fw-semibold">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                    </a>
                    <a href="<?= base_url('auth/register.php') ?>" class="btn btn-scout-gold btn-sm px-3 fw-semibold">
                        <i class="fa-solid fa-school me-1"></i> Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main>
