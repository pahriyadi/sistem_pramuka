<?php
/**
 * Public Portal Footer
 * Racana Universitas Samawa (UNSA)
 */
$appName = get_setting('nama_racana', 'Racana Universitas Samawa');
$kontakWa = get_setting('kontak_wa', '0852-3891-2345');
$email = get_setting('email_racana', 'racana@unsa.ac.id');
$alamat = get_setting('alamat_racana', 'Jl. By Pass Sering, Kerato, Sumbawa, NTB');
$brandName = get_setting('nama_brand', 'RACANA UNSA');
$universityName = get_setting('nama_universitas', 'Universitas Samawa');
$portalDescription = get_setting('deskripsi_portal', 'Pusat informasi kegiatan dan pendaftaran online.');
$jamLayanan = get_setting('jam_layanan', 'Senin - Sabtu: 08.00 - 21.00 WITA');
$socialLinks = [
    ['url' => get_setting('instagram_url'), 'icon' => 'fa-instagram'],
    ['url' => get_setting('facebook_url'), 'icon' => 'fa-facebook-f'],
    ['url' => get_setting('youtube_url'), 'icon' => 'fa-youtube'],
];
$waNumber = get_whatsapp_number();
?>
</main>

<footer class="text-white pt-5 pb-4 mt-5 border-top border-warning border-opacity-25" style="background: linear-gradient(180deg, #3e2600 0%, #07172b 100%) !important;">
    <div class="container">
        <div class="row g-4 pb-4 border-bottom border-white border-opacity-10">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; background: linear-gradient(135deg, #0b2545 0%, #800000 100%) !important;">
                        <i class="fa-solid fa-campground fs-4 text-warning"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-0"><?= e($appName) ?></h5>
                        <small class="text-warning fw-semibold"><?= e($universityName) ?></small>
                    </div>
                </div>
                <p class="text-white-50 small pe-lg-4"><?= e($portalDescription) ?></p>
                <div class="d-flex gap-2">
                    <?php foreach ($socialLinks as $social): ?>
                        <?php $scheme = strtolower((string)parse_url($social['url'], PHP_URL_SCHEME)); ?>
                        <?php if (filter_var($social['url'], FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true)): ?>
                            <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-light rounded-circle" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;"><i class="fa-brands <?= e($social['icon']) ?>"></i></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($waNumber !== ''): ?>
                        <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-warning text-white rounded-circle" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;"><i class="fa-brands fa-whatsapp text-warning"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-warning fw-bold text-uppercase mb-3 small" style="letter-spacing: 1px;">Navigasi Cepat</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 text-white-50">
                    <li><a href="<?= base_url('index.php') ?>" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-angle-right me-1 text-warning"></i> Beranda Utama</a></li>
                    <li><a href="<?= base_url('tentang.php') ?>" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-angle-right me-1 text-warning"></i> Profil & Struktur Racana</a></li>
                    <li><a href="<?= base_url('petunjuk-teknis.php') ?>" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-angle-right me-1 text-warning"></i> Petunjuk Teknis Lomba</a></li>
                    <li><a href="<?= base_url('auth/register.php') ?>" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-angle-right me-1 text-warning"></i> Pendaftaran Akun Sekolah</a></li>
                    <li><a href="<?= base_url('auth/login.php') ?>" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-angle-right me-1 text-warning"></i> Login Portal Kontingen</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-6">
                <h6 class="text-warning fw-bold text-uppercase mb-3 small" style="letter-spacing: 1px;">Sekretariat Panitia</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 text-white-50">
                    <li class="d-flex gap-2">
                        <i class="fa-solid fa-location-dot text-warning mt-1"></i>
                        <span><?= e($alamat) ?></span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="fa-brands fa-whatsapp text-success mt-1"></i>
                        <span>WhatsApp: <strong><?= e($kontakWa) ?></strong></span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="fa-solid fa-envelope text-info mt-1"></i>
                        <span>Email: <?= e($email) ?></span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="fa-solid fa-clock text-warning mt-1"></i>
                        <span>Layanan Panitia: <?= e($jamLayanan) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center pt-3 small text-white-50">
            <div>
                &copy; <?= date('Y') ?> <strong><?= e($appName) ?></strong>. Gugus Depan <?= e($universityName) ?>. All rights reserved.
            </div>
            <div class="mt-2 mt-md-0">
                <span><?= e(get_setting('nama_acara', 'Portal Pendaftaran Online')) ?></span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
