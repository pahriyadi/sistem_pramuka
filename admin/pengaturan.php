<?php
/**
 * Pengaturan Portal, Branding Visual, Pembayaran & Form Visibility - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Pengaturan Portal & Branding';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();
$pdo = getDB();

$errors = [];
$quotaCategories = $pdo->query("SELECT id, kode_kategori, nama_kategori, kuota_max FROM kategori_peserta ORDER BY urutan ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi CSRF token tidak sah.';
    } else {
        $settings = [
            'nama_acara'            => trim($_POST['nama_acara'] ?? ''),
            'tema_acara'            => trim($_POST['tema_acara'] ?? ''),
            'nama_racana'           => trim($_POST['nama_racana'] ?? ''),
            'nama_brand'            => trim($_POST['nama_brand'] ?? ''),
            'nama_universitas'      => trim($_POST['nama_universitas'] ?? ''),
            'gudep_putra'           => trim($_POST['gudep_putra'] ?? ''),
            'gudep_putri'           => trim($_POST['gudep_putri'] ?? ''),
            'alamat_racana'         => trim($_POST['alamat_racana'] ?? ''),
            'kontak_wa'             => trim($_POST['kontak_wa'] ?? ''),
            'email_racana'          => trim($_POST['email_racana'] ?? ''),
            'jam_layanan'           => trim($_POST['jam_layanan'] ?? ''),
            'instagram_url'         => trim($_POST['instagram_url'] ?? ''),
            'facebook_url'          => trim($_POST['facebook_url'] ?? ''),
            'youtube_url'           => trim($_POST['youtube_url'] ?? ''),
            'deskripsi_portal'      => trim($_POST['deskripsi_portal'] ?? ''),
            'profil_racana'         => trim($_POST['profil_racana'] ?? ''),
            'visi_racana'           => trim($_POST['visi_racana'] ?? ''),
            'misi_racana'           => trim($_POST['misi_racana'] ?? ''),
            'nama_maskot'           => trim($_POST['nama_maskot'] ?? ''),
            'deskripsi_maskot'      => trim($_POST['deskripsi_maskot'] ?? ''),
            'rundown_kegiatan'      => trim($_POST['rundown_kegiatan'] ?? ''),
            'status_pendaftaran'    => $_POST['status_pendaftaran'] ?? 'BUKA',
            'tgl_pelaksanaan_kemah' => trim($_POST['tgl_pelaksanaan_kemah'] ?? ''),
            'lokasi_kegiatan'       => trim($_POST['lokasi_kegiatan'] ?? ''),
            'tgl_selesai_daftar'    => trim($_POST['tgl_selesai_daftar'] ?? ''),
            // Payment Configurations
            'biaya_kontingen'       => trim($_POST['biaya_kontingen'] ?? '500000'),
            'bank_nama'             => trim($_POST['bank_nama'] ?? 'Bank NTB Syariah'),
            'bank_rekening'         => trim($_POST['bank_rekening'] ?? '501.02.001928.01'),
            'bank_atas_nama'        => trim($_POST['bank_atas_nama'] ?? 'Racana Universitas Samawa'),
            'petunjuk_pembayaran'   => trim($_POST['petunjuk_pembayaran'] ?? ''),
            // Field Visibility Settings (Kolom Formulir Biodata Peserta)
            'field_ukuran_kaos'      => isset($_POST['field_ukuran_kaos']) ? 'Y' : 'N',
            'field_nisn_nta'         => isset($_POST['field_nisn_nta']) ? 'Y' : 'N',
            'field_riwayat_penyakit' => isset($_POST['field_riwayat_penyakit']) ? 'Y' : 'N',
            'field_tempat_lahir'     => isset($_POST['field_tempat_lahir']) ? 'Y' : 'N',
            'field_no_hp'            => isset($_POST['field_no_hp']) ? 'Y' : 'N',
            'field_foto'             => isset($_POST['field_foto']) ? 'Y' : 'N',
            'default_ukuran_kaos'    => in_array($_POST['default_ukuran_kaos'] ?? '', ['S', 'M', 'L', 'XL', 'XXL']) ? $_POST['default_ukuran_kaos'] : 'L',
        ];

        foreach (['instagram_url', 'facebook_url', 'youtube_url'] as $urlKey) {
            if ($settings[$urlKey] !== '' && (!filter_var($settings[$urlKey], FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($settings[$urlKey], PHP_URL_SCHEME)), ['http', 'https'], true))) {
                $errors[] = 'Tautan media sosial harus berupa URL http atau https yang valid.';
            }
        }

        $quotaUpdates = [];
        $quotaPost = $_POST['kuota_kategori'] ?? [];
        if (!is_array($quotaPost)) {
            $errors[] = 'Format pengaturan kuota tidak valid.';
        } else {
            foreach ($quotaCategories as $kategori) {
                $code = $kategori['kode_kategori'];
                $value = $quotaPost[$code] ?? null;
                if (!is_string($value) && !is_int($value)) {
                    $errors[] = 'Kuota untuk <strong>' . e($kategori['nama_kategori']) . '</strong> wajib diisi dengan bilangan bulat.';
                    continue;
                }
                $value = (string)$value;
                if (!preg_match('/^\d+$/', $value) || (int)$value > 100) {
                    $errors[] = 'Kuota untuk <strong>' . e($kategori['nama_kategori']) . '</strong> harus berupa bilangan bulat antara 0 dan 100.';
                    continue;
                }
                $quotaUpdates[$code] = (int)$value;
            }
        }

        if (empty($errors) && count($quotaUpdates) !== count($quotaCategories)) {
            $errors[] = 'Pengaturan kuota tidak lengkap. Muat ulang halaman dan coba lagi.';
        }

        if (empty($errors)) {
            $stmtCount = $pdo->prepare("
                SELECT s.nama_sekolah, COUNT(p.id) AS jumlah
                FROM sekolah s
                JOIN peserta p ON p.sekolah_id = s.id AND p.status IN ('aktif', 'draft')
                JOIN kategori_peserta k ON k.id = p.kategori_id
                WHERE k.kode_kategori = ?
                GROUP BY s.id
                HAVING COUNT(p.id) > ?
                ORDER BY jumlah DESC
                LIMIT 1
            ");
            foreach ($quotaUpdates as $code => $quota) {
                $stmtCount->execute([$code, $quota]);
                $overQuotaSchool = $stmtCount->fetch();
                if ($overQuotaSchool) {
                    $categoryName = '';
                    foreach ($quotaCategories as $kategori) {
                        if ($kategori['kode_kategori'] === $code) {
                            $categoryName = $kategori['nama_kategori'];
                            break;
                        }
                    }
                    $errors[] = 'Kuota <strong>' . e($categoryName) . '</strong> tidak dapat diturunkan menjadi ' . $quota . ' karena pangkalan <strong>' . e($overQuotaSchool['nama_sekolah']) . '</strong> sudah memiliki ' . (int)$overQuotaSchool['jumlah'] . ' anggota aktif/draf pada kategori tersebut.';
                }
            }
        }

        $settings['kuota_per_kontingen'] = (string)array_sum($quotaUpdates);

        // Handle QRIS Photo Upload
        if (!empty($_FILES['foto_qris']['name']) && $_FILES['foto_qris']['error'] === UPLOAD_ERR_OK) {
            $uploadQris = upload_foto_qris($_FILES['foto_qris']);
            if ($uploadQris['status']) {
                $settings['foto_qris'] = $uploadQris['filename'];
            } else {
                $errors[] = $uploadQris['message'];
            }
        }

        // Handle Favicon Upload
        if (!empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $uploadFav = upload_branding_file($_FILES['favicon'], 'favicon');
            if ($uploadFav['status']) {
                $settings['favicon'] = $uploadFav['filename'];
            } else {
                $errors[] = $uploadFav['message'];
            }
        }

        // Handle Logo Utama Upload
        if (!empty($_FILES['logo_utama']['name']) && $_FILES['logo_utama']['error'] === UPLOAD_ERR_OK) {
            $uploadLogo = upload_branding_file($_FILES['logo_utama'], 'logo_utama');
            if ($uploadLogo['status']) {
                $settings['logo_utama'] = $uploadLogo['filename'];
            } else {
                $errors[] = $uploadLogo['message'];
            }
        }

        // Handle Logo Kegiatan Upload
        if (!empty($_FILES['logo_kegiatan']['name']) && $_FILES['logo_kegiatan']['error'] === UPLOAD_ERR_OK) {
            $uploadLogoK = upload_branding_file($_FILES['logo_kegiatan'], 'logo_kegiatan');
            if ($uploadLogoK['status']) {
                $settings['logo_kegiatan'] = $uploadLogoK['filename'];
            } else {
                $errors[] = $uploadLogoK['message'];
            }
        }

        // Handle Maskot Kegiatan Upload
        if (!empty($_FILES['maskot_kegiatan']['name']) && $_FILES['maskot_kegiatan']['error'] === UPLOAD_ERR_OK) {
            $uploadMaskot = upload_branding_file($_FILES['maskot_kegiatan'], 'maskot');
            if ($uploadMaskot['status']) {
                $settings['maskot_kegiatan'] = $uploadMaskot['filename'];
            } else {
                $errors[] = $uploadMaskot['message'];
            }
        }

        // Handle Branding Resets
        if (!empty($_POST['reset_favicon'])) {
            $settings['favicon'] = '';
        }
        if (!empty($_POST['reset_logo_utama'])) {
            $settings['logo_utama'] = '';
        }
        if (!empty($_POST['reset_logo_kegiatan'])) {
            $settings['logo_kegiatan'] = '';
        }
        if (!empty($_POST['reset_maskot_kegiatan'])) {
            $settings['maskot_kegiatan'] = '';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO pengaturan_portal (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                foreach ($settings as $k => $v) {
                    $stmt->execute([$k, $v]);
                }
                $stmtQuota = $pdo->prepare("UPDATE kategori_peserta SET kuota_max = ? WHERE kode_kategori = ?");
                foreach ($quotaUpdates as $code => $quota) {
                    $stmtQuota->execute([$quota, $code]);
                }
                $pdo->commit();
                set_flash('success', 'Pengaturan portal, kuota kontingen, branding, pembayaran, dan formulir berhasil disimpan.');
                header('Location: ' . base_url('admin/pengaturan.php'));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            }
        }
    }
}

// Fetch All Settings
$stmtAll = $pdo->query("SELECT key_name, key_value FROM pengaturan_portal");
$rawSettings = $stmtAll->fetchAll(PDO::FETCH_KEY_PAIR);
$totalKuotaKontingen = array_sum(array_map(static fn($kategori) => (int)$kategori['kuota_max'], $quotaCategories));

// URLs for Preview
$faviconUrl      = get_favicon_url();
$logoUrl         = get_logo_url();
$logoKegiatanUrl = get_logo_kegiatan_url();
$maskotUrl       = get_maskot_url();

$fotoQris = $rawSettings['foto_qris'] ?? 'qris-racana-unsa.png';
$qrisPath = dirname(__DIR__) . '/uploads/qris/' . $fotoQris;
$qrisExists = (!empty($fotoQris) && file_exists($qrisPath));
$qrisUrl = $qrisExists ? base_url('uploads/qris/' . $fotoQris) : ("https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=" . urlencode("QRIS: RACANA UNIVERSITAS SAMAWA"));

// Field settings values
$fieldUkuranKaos      = ($rawSettings['field_ukuran_kaos'] ?? 'N') === 'Y';
$fieldNisnNta         = ($rawSettings['field_nisn_nta'] ?? 'Y') === 'Y';
$fieldRiwayatPenyakit = ($rawSettings['field_riwayat_penyakit'] ?? 'Y') === 'Y';
$fieldTempatLahir     = ($rawSettings['field_tempat_lahir'] ?? 'Y') === 'Y';
$fieldNoHp            = ($rawSettings['field_no_hp'] ?? 'Y') === 'Y';
$fieldFoto            = ($rawSettings['field_foto'] ?? 'Y') === 'Y';
$defaultUkuranKaos    = $rawSettings['default_ukuran_kaos'] ?? 'L';
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
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-scout-navy">Pengaturan Portal &amp; Branding</h4>
                <small class="text-muted">Kelola kuota kontingen, logo, rekening, dan kolom formulir</small>
            </div>
        </div>
        <div>
            <a href="<?= base_url('admin/dokumen.php') ?>" class="btn btn-scout-gold btn-sm fw-bold shadow-sm">
                <i class="fa-solid fa-folder-open me-1"></i> Kelola Dokumen
            </a>
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

    <form method="POST" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- LEFT COLUMN: MAIN FORMS -->
            <div class="col-lg-8">

                <!-- SECTION 1: PENGATURAN KOLOM FORMULIR BIODATA PESERTA (FIELD VISIBILITY) -->
                <div class="custom-card p-4 p-md-5 mb-4 border-scout-navy border-opacity-25 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                        <div>
                            <h5 class="fw-bold text-scout-navy mb-1">
                                <i class="fa-solid fa-users-gear text-scout-gold me-2"></i> Pengaturan Kuota Kontingen
                            </h5>
                            <small class="text-muted">Tentukan batas anggota per kategori. Total kuota dihitung otomatis dari seluruh kategori.</small>
                        </div>
                        <span class="badge bg-scout-navy text-white px-3 py-2 fw-bold">Total <span id="totalKuotaPreview"><?= $totalKuotaKontingen ?></span> Orang</span>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($quotaCategories as $kategori): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-scout-navy" for="quota_<?= e($kategori['kode_kategori']) ?>"><?= e($kategori['nama_kategori']) ?></label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        id="quota_<?= e($kategori['kode_kategori']) ?>"
                                        name="kuota_kategori[<?= e($kategori['kode_kategori']) ?>]"
                                        class="form-control quota-category-input"
                                        value="<?= (int)$kategori['kuota_max'] ?>"
                                        required min="0" max="100" step="1">
                                    <span class="input-group-text">orang</span>
                                </div>
                                <div class="form-text small">Bilangan bulat 0–100. Kuota tidak bisa diturunkan di bawah jumlah anggota aktif/draf pangkalan.</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SECTION 2: PENGATURAN KOLOM FORMULIR BIODATA PESERTA (FIELD VISIBILITY) -->
                <div class="custom-card p-4 p-md-5 mb-4 border-scout-gold border-opacity-50 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                        <div>
                            <h5 class="fw-bold text-scout-brown mb-1">
                                <i class="fa-solid fa-sliders text-scout-gold me-2"></i> Pengaturan Kolom Formulir Pendaftaran Peserta
                            </h5>
                            <small class="text-muted">Atur kolom apa saja yang tampil dan wajib diisi sekolah (kolom yang dinonaktifkan akan disembunyikan tanpa merusak database)</small>
                        </div>
                        <span class="badge bg-scout-gold text-dark px-3 py-2 fw-bold">Field Settings</span>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="row g-3">
                            
                            <!-- UKURAN KAOS TOGGLE -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_ukuran_kaos" value="Y" id="swUkuranKaos" <?= $fieldUkuranKaos ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swUkuranKaos">
                                            <i class="fa-solid fa-shirt text-scout-maroon me-1"></i> Kolom Ukuran Kaos Kegiatan
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-2" style="font-size: 0.75rem;">
                                        Jika dinonaktifkan, kolom ukuran kaos tidak akan tampil di formulir pendaftaran sekolah dan otomatis tersimpan sebagai nilai default.
                                    </p>
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0 small text-muted">Nilai Default:</label>
                                        <select name="default_ukuran_kaos" class="form-select form-select-sm" style="width: 90px;">
                                            <option value="S" <?= $defaultUkuranKaos === 'S' ? 'selected' : '' ?>>S</option>
                                            <option value="M" <?= $defaultUkuranKaos === 'M' ? 'selected' : '' ?>>M</option>
                                            <option value="L" <?= $defaultUkuranKaos === 'L' ? 'selected' : '' ?>>L</option>
                                            <option value="XL" <?= $defaultUkuranKaos === 'XL' ? 'selected' : '' ?>>XL</option>
                                            <option value="XXL" <?= $defaultUkuranKaos === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- NISN / NTA PRAMUKA -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_nisn_nta" value="Y" id="swNisnNta" <?= $fieldNisnNta ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swNisnNta">
                                            <i class="fa-solid fa-id-card text-scout-navy me-1"></i> Kolom NISN / NTA Pramuka
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        Nomor Induk Siswa Nasional atau Nomor Tanda Anggota Gerakan Pramuka peserta.
                                    </p>
                                </div>
                            </div>

                            <!-- RIWAYAT PENYAKIT -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_riwayat_penyakit" value="Y" id="swRiwayat" <?= $fieldRiwayatPenyakit ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swRiwayat">
                                            <i class="fa-solid fa-notes-medical text-danger me-1"></i> Kolom Riwayat Penyakit
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        Catatan medis/alergi penting peserta untuk antisipasi tim medis perkemahan di buper.
                                    </p>
                                </div>
                            </div>

                            <!-- TEMPAT LAHIR -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_tempat_lahir" value="Y" id="swTempatLahir" <?= $fieldTempatLahir ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swTempatLahir">
                                            <i class="fa-solid fa-location-dot text-success me-1"></i> Kolom Tempat Lahir
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        Nama kota / kabupaten tempat kelahiran peserta didik kontingen.
                                    </p>
                                </div>
                            </div>

                            <!-- NO HP / WA PESERTA -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_no_hp" value="Y" id="swNoHp" <?= $fieldNoHp ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swNoHp">
                                            <i class="fa-brands fa-whatsapp text-success me-1"></i> Kolom No. WhatsApp / HP Peserta
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        Kontak nomor telepon atau WhatsApp peserta/bindamping untuk koordinasi perlombaan.
                                    </p>
                                </div>
                            </div>

                            <!-- PAS FOTO PESERTA -->
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="field_foto" value="Y" id="swFoto" <?= $fieldFoto ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="swFoto">
                                            <i class="fa-solid fa-image text-primary me-1"></i> Kolom Upload Pas Foto ID Card
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        Unggah pas foto berseragam pramuka untuk dicetak pada Kartu Tanda Peserta resmi.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- SECTION 2: IDENTITAS VISUAL & BRANDING -->
                <div class="custom-card p-4 p-md-5 mb-4 border-scout-navy border-opacity-25 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                        <div>
                            <h5 class="fw-bold text-scout-navy mb-1">
                                <i class="fa-solid fa-palette text-scout-gold me-2"></i> Identitas Visual &amp; Logo Branding
                            </h5>
                            <small class="text-muted">Upload Favicon, Logo Utama, Logo Kegiatan, dan Maskot Resmi (PNG, JPG, SVG, WEBP)</small>
                        </div>
                        <span class="badge bg-scout-navy text-white px-3 py-2">4 Warna Resmi</span>
                    </div>

                    <div class="row g-4">
                        <!-- Favicon Upload -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label fw-bold small text-scout-navy d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-bookmark text-scout-maroon me-1"></i> Favicon Website</span>
                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">Tab Browser (1:1)</span>
                                </label>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="p-2 bg-white rounded border text-center shadow-sm" style="width: 60px; height: 60px; display: grid; place-items: center;">
                                        <img src="<?= $faviconUrl ?>" alt="Favicon" style="max-width: 44px; max-height: 44px; object-fit: contain;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" name="favicon" class="form-control form-control-sm" accept="image/png,image/x-icon,image/svg+xml,image/webp">
                                        <div class="form-text" style="font-size: 0.72rem;">Ukuran ideal: 64x64 px atau SVG transparan.</div>
                                    </div>
                                </div>
                                <?php if (!empty($rawSettings['favicon'])): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reset_favicon" value="1" id="resetFav">
                                        <label class="form-check-label text-danger small" for="resetFav">Reset ke Favicon Default</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Logo Utama Racana -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label fw-bold small text-scout-navy d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-shield-halved text-scout-gold me-1"></i> Logo Utama Racana</span>
                                    <span class="badge bg-scout-navy" style="font-size: 0.65rem;">Navbar &amp; Header</span>
                                </label>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="p-2 bg-white rounded border text-center shadow-sm" style="width: 80px; height: 60px; display: grid; place-items: center;">
                                        <img src="<?= $logoUrl ?>" alt="Logo Utama" style="max-width: 70px; max-height: 48px; object-fit: contain;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" name="logo_utama" class="form-control form-control-sm" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                        <div class="form-text" style="font-size: 0.72rem;">Logo resmi Gudep / Racana UNSA.</div>
                                    </div>
                                </div>
                                <?php if (!empty($rawSettings['logo_utama'])): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reset_logo_utama" value="1" id="resetLogo">
                                        <label class="form-check-label text-danger small" for="resetLogo">Reset ke Logo Default</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Logo Kegiatan Kemah -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label fw-bold small text-scout-navy d-flex justify-content-between align-items-center">
                                    <span><i class="fa-solid fa-campground text-scout-brown me-1"></i> Logo Kegiatan Kemah</span>
                                    <span class="badge bg-scout-maroon" style="font-size: 0.65rem;">Badge Giat 2026</span>
                                </label>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="p-2 bg-white rounded border text-center shadow-sm" style="width: 80px; height: 60px; display: grid; place-items: center;">
                                        <img src="<?= $logoKegiatanUrl ?>" alt="Logo Kegiatan" style="max-width: 70px; max-height: 48px; object-fit: contain;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" name="logo_kegiatan" class="form-control form-control-sm" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                        <div class="form-text" style="font-size: 0.72rem;">Logo emblem khusus perkemahan.</div>
                                    </div>
                                </div>
                                <?php if (!empty($rawSettings['logo_kegiatan'])): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="reset_logo_kegiatan" value="1" id="resetLogoK">
                                        <label class="form-check-label text-danger small" for="resetLogoK">Reset ke Logo Kegiatan Default</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Maskot Resmi Kegiatan -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label fw-bold small text-scout-navy d-flex justify-content-between align-items-center mb-2">
                                    <span><i class="fa-solid fa-dragon text-scout-maroon me-1"></i> Maskot Karakter Kegiatan</span>
                                    <span class="badge bg-scout-gold text-dark" style="font-size: 0.65rem;"><?= e($rawSettings['nama_maskot'] ?? 'Si Praja UNSA') ?></span>
                                </label>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="p-2 bg-white rounded border text-center shadow-sm" style="width: 80px; height: 60px; display: grid; place-items: center;">
                                        <img src="<?= $maskotUrl ?>" alt="Maskot" style="max-width: 70px; max-height: 52px; object-fit: contain;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" name="maskot_kegiatan" class="form-control form-control-sm" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                        <div class="form-text" style="font-size: 0.72rem;">Unggah ilustrasi maskot (rekomendasi format PNG transparan).</div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-scout-navy mb-1" style="font-size: 0.78rem;">Nama Maskot Karakter</label>
                                    <input type="text" name="nama_maskot" class="form-control form-control-sm" value="<?= e($rawSettings['nama_maskot'] ?? 'Si Praja UNSA') ?>" placeholder="Contoh: Si Praja UNSA" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-scout-navy mb-1" style="font-size: 0.78rem;">Deskripsi &amp; Filosofi Maskot</label>
                                    <input type="text" name="deskripsi_maskot" class="form-control form-control-sm" value="<?= e($rawSettings['deskripsi_maskot'] ?? '') ?>" placeholder="Contoh: Simbol ketangguhan, keceriaan, dan persaudaraan penegak.">
                                </div>
                                <?php if (!empty($rawSettings['maskot_kegiatan'])): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="reset_maskot_kegiatan" value="1" id="resetMaskot">
                                        <label class="form-check-label text-danger small" for="resetMaskot">Reset ke Maskot Default</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: INFORMASI PEMBAYARAN & REKENING BANK / QRIS -->
                <div class="custom-card p-4 p-md-5 mb-4 border-scout-maroon border-opacity-25 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                        <div>
                            <h5 class="fw-bold text-scout-maroon mb-1">
                                <i class="fa-solid fa-credit-card text-scout-gold me-2"></i> Pengaturan Rekening Bank &amp; QRIS Pembayaran
                            </h5>
                            <small class="text-muted">Konfigurasi nominal pendaftaran dan rekening tujuan transfer kontingen</small>
                        </div>
                        <span class="badge bg-scout-gold text-dark px-3 py-2 fw-bold">Tampil di Dashboard Sekolah</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Biaya Pendaftaran per Kontingen (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold bg-light">Rp</span>
                                <input type="number" name="biaya_kontingen" class="form-control fw-bold text-scout-navy" value="<?= e($rawSettings['biaya_kontingen'] ?? '500000') ?>" required min="0" step="1000">
                            </div>
                            <div class="form-text small">Biaya resmi wajib per pangkalan sekolah.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nama Bank Tujuan Transfer <span class="text-danger">*</span></label>
                            <input type="text" name="bank_nama" class="form-control" value="<?= e($rawSettings['bank_nama'] ?? 'Bank NTB Syariah') ?>" placeholder="Contoh: Bank NTB Syariah / BRI" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor Rekening Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_rekening" class="form-control font-monospace fw-bold text-scout-navy" value="<?= e($rawSettings['bank_rekening'] ?? '501.02.001928.01') ?>" placeholder="Contoh: 501.02.001928.01" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Atas Nama Pemilik Rekening <span class="text-danger">*</span></label>
                            <input type="text" name="bank_atas_nama" class="form-control" value="<?= e($rawSettings['bank_atas_nama'] ?? 'Racana Universitas Samawa') ?>" placeholder="Contoh: Racana Universitas Samawa" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Upload / Ganti Foto QRIS Panitia</label>
                            <div class="row align-items-center g-3">
                                <div class="col-md-8">
                                    <input type="file" name="foto_qris" class="form-control" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text small">Format JPG, JPEG, PNG, atau WEBP. Maksimal 3 MB. QRIS ini akan tampil otomatis di portal sekolah.</div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <?php if ($qrisUrl): ?>
                                        <div class="p-2 bg-white rounded border d-inline-block shadow-sm">
                                            <img src="<?= $qrisUrl ?>" alt="QRIS Aktif" style="max-height: 65px; max-width: 90px; object-fit: contain;">
                                            <div class="badge bg-scout-navy d-block mt-1" style="font-size: 0.65rem;">QRIS Aktif</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Petunjuk Pembayaran untuk Kontingen Sekolah</label>
                            <textarea name="petunjuk_pembayaran" class="form-control" rows="4" placeholder="Tuliskan petunjuk pembayaran..."><?= e($rawSettings['petunjuk_pembayaran'] ?? "1. Lakukan pembayaran pendaftaran kontingen melalui Transfer Rekening atau Scan QRIS resmi di atas.\n2. Simpan struk/bukti transfer yang jelas.\n3. Unggah bukti pembayaran melalui form konfirmasi pada dashboard sekolah.\n4. Panitia akan memverifikasi pembayaran Anda maksimal 1x24 jam kerja.") ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: INFORMASI AGENDA UTAMA -->
                <div class="custom-card p-4 p-md-5 mb-4 border-scout-gold border-opacity-25 shadow-sm">
                    <h5 class="fw-bold text-scout-brown mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-bullhorn text-scout-gold me-2"></i> Informasi Agenda Utama &amp; Pelaksanaan
                    </h5>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Nama Kegiatan / Agenda</label>
                            <input type="text" name="nama_acara" class="form-control" value="<?= e($rawSettings['nama_acara'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Tema Kegiatan</label>
                            <input type="text" name="tema_acara" class="form-control" value="<?= e($rawSettings['tema_acara'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Tanggal Pelaksanaan</label>
                            <input type="text" name="tgl_pelaksanaan_kemah" class="form-control" value="<?= e($rawSettings['tgl_pelaksanaan_kemah'] ?? '15 - 19 November 2026') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Batas Akhir Pendaftaran</label>
                            <input type="text" name="tgl_selesai_daftar" class="form-control" value="<?= e($rawSettings['tgl_selesai_daftar'] ?? '31 Oktober 2026') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Lokasi Kegiatan / Bumi Perkemahan</label>
                            <input type="text" name="lokasi_kegiatan" class="form-control" value="<?= e($rawSettings['lokasi_kegiatan'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Rundown / Agenda Harian</label>
                            <textarea name="rundown_kegiatan" class="form-control" rows="7" placeholder="Satu agenda per baris"><?= e($rawSettings['rundown_kegiatan'] ?? '') ?></textarea>
                            <div class="form-text">Tulis satu agenda per baris. Isi ini ditampilkan di jadwal kontingen.</div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: KONTAK & IDENTITAS LEMBAGA -->
                <div class="custom-card p-4 p-md-5 mb-4 shadow-sm">
                    <h5 class="fw-bold text-scout-navy mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-address-book text-scout-maroon me-2"></i> Kontak &amp; Identitas Lembaga
                    </h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nama Lembaga Racana</label>
                            <input type="text" name="nama_racana" class="form-control" value="<?= e($rawSettings['nama_racana'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nama Singkat / Branding Portal</label>
                            <input type="text" name="nama_brand" class="form-control" value="<?= e($rawSettings['nama_brand'] ?? 'RACANA UNSA') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nama Perguruan Tinggi / Pangkalan</label>
                            <input type="text" name="nama_universitas" class="form-control" value="<?= e($rawSettings['nama_universitas'] ?? 'Universitas Samawa') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor Gudep Putra</label>
                            <input type="text" name="gudep_putra" class="form-control" value="<?= e($rawSettings['gudep_putra'] ?? '01.089') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Nomor Gudep Putri</label>
                            <input type="text" name="gudep_putri" class="form-control" value="<?= e($rawSettings['gudep_putri'] ?? '01.090') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Status Pendaftaran Online</label>
                            <select name="status_pendaftaran" class="form-select">
                                <option value="BUKA" <?= ($rawSettings['status_pendaftaran'] ?? '') === 'BUKA' ? 'selected' : '' ?>>BUKA (Pendaftaran Aktif)</option>
                                <option value="DITUTUP" <?= ($rawSettings['status_pendaftaran'] ?? '') === 'DITUTUP' ? 'selected' : '' ?>>DITUTUP (Pendaftaran Ditutup)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">No. WhatsApp Helpdesk Panitia</label>
                            <input type="text" name="kontak_wa" class="form-control" value="<?= e($rawSettings['kontak_wa'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Email Resmi</label>
                            <input type="email" name="email_racana" class="form-control" value="<?= e($rawSettings['email_racana'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Alamat Sekretariat</label>
                            <textarea name="alamat_racana" class="form-control" rows="2"><?= e($rawSettings['alamat_racana'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Jam Layanan Panitia</label>
                            <input type="text" name="jam_layanan" class="form-control" value="<?= e($rawSettings['jam_layanan'] ?? 'Senin - Sabtu: 08.00 - 21.00 WITA; Minggu: 09.00 - 17.00 WITA') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Deskripsi Portal / Tagline</label>
                            <textarea name="deskripsi_portal" class="form-control" rows="2"><?= e($rawSettings['deskripsi_portal'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Tautan Instagram, Facebook, dan YouTube</label>
                            <div class="row g-2">
                                <div class="col-md-4"><input type="url" name="instagram_url" class="form-control" placeholder="https://instagram.com/..." value="<?= e($rawSettings['instagram_url'] ?? '') ?>"></div>
                                <div class="col-md-4"><input type="url" name="facebook_url" class="form-control" placeholder="https://facebook.com/..." value="<?= e($rawSettings['facebook_url'] ?? '') ?>"></div>
                                <div class="col-md-4"><input type="url" name="youtube_url" class="form-control" placeholder="https://youtube.com/..." value="<?= e($rawSettings['youtube_url'] ?? '') ?>"></div>
                            </div>
                            <div class="form-text">Kosongkan tautan jika akun belum tersedia. Hanya URL http/https yang diterima.</div>
                        </div>
                        <div class="col-12 border-top pt-3 mt-2">
                            <h6 class="fw-bold text-scout-navy">Konten Profil Lembaga</h6>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-scout-navy">Profil Singkat</label>
                            <textarea name="profil_racana" class="form-control" rows="4"><?= e($rawSettings['profil_racana'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Visi Racana</label>
                            <textarea name="visi_racana" class="form-control" rows="4"><?= e($rawSettings['visi_racana'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-scout-navy">Misi Racana</label>
                            <textarea name="misi_racana" class="form-control" rows="4" placeholder="Satu poin misi per baris"><?= e($rawSettings['misi_racana'] ?? '') ?></textarea>
                            <div class="form-text">Tulis satu poin misi per baris.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-scout-gold px-5 py-3 fw-bold shadow">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Seluruh Pengaturan
                    </button>
                    <a href="<?= base_url('admin/index.php') ?>" class="btn btn-outline-secondary px-4 py-3">
                        Batal
                    </a>
                </div>

            </div>

            <!-- RIGHT COLUMN: LIVE BRANDING & PAYMENT PREVIEWS -->
            <div class="col-lg-4">
                
                <!-- BRANDING PREVIEW CARD -->
                <div class="custom-card p-4 mb-4 border-scout-navy border-opacity-25 shadow-sm" style="background: linear-gradient(180deg, #f0f7ff 0%, #ffffff 100%);">
                    <div class="d-flex align-items-center gap-2 mb-3 text-scout-navy">
                        <i class="fa-solid fa-eye fs-5"></i>
                        <h6 class="fw-bold mb-0">Pratinjau Branding Aktif</h6>
                    </div>

                    <!-- Logo & Favicon Showcase -->
                    <div class="p-3 bg-white rounded-3 border text-center mb-3">
                        <small class="text-muted d-block mb-1">Logo Utama &amp; Kegiatan:</small>
                        <div class="d-flex justify-content-center align-items-center gap-3 my-2">
                            <img src="<?= $logoUrl ?>" alt="Logo Racana" style="max-height: 48px; max-width: 110px; object-fit: contain;">
                            <div class="vr"></div>
                            <img src="<?= $logoKegiatanUrl ?>" alt="Logo Giat" style="max-height: 48px; max-width: 110px; object-fit: contain;">
                        </div>
                    </div>

                    <!-- Mascot Showcase -->
                    <div class="p-3 bg-white rounded-3 border text-center mb-3">
                        <small class="text-muted d-block mb-1">Maskot Resmi:</small>
                        <img src="<?= $maskotUrl ?>" alt="Maskot" class="img-fluid my-1" style="max-height: 120px; object-fit: contain;">
                        <div class="badge bg-scout-navy d-block mt-2"><?= e($rawSettings['nama_maskot'] ?? 'Si Praja UNSA') ?></div>
                    </div>

                    <!-- 4 Official Colors Palette Indicator -->
                    <div class="p-3 bg-white rounded-3 border">
                        <small class="text-muted d-block mb-2 fw-semibold">Palet 4 Warna Resmi Racana:</small>
                        <div class="d-flex gap-2">
                            <div class="flex-fill text-center p-2 rounded text-white" style="background: var(--scout-navy); font-size: 0.65rem;">
                                <strong>Biru Laut</strong><br>#0b2545
                            </div>
                            <div class="flex-fill text-center p-2 rounded text-white" style="background: var(--scout-maroon); font-size: 0.65rem;">
                                <strong>Maron</strong><br>#800000
                            </div>
                            <div class="flex-fill text-center p-2 rounded text-dark fw-bold" style="background: var(--scout-gold); font-size: 0.65rem;">
                                <strong>Kuning</strong><br>#d97706
                            </div>
                            <div class="flex-fill text-center p-2 rounded text-white" style="background: var(--scout-brown); font-size: 0.65rem;">
                                <strong>Coklat</strong><br>#5d3a00
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT PREVIEW CARD -->
                <div class="custom-card p-4 mb-4 border-scout-maroon border-opacity-25 shadow-sm" style="background: linear-gradient(180deg, #fff9f9 0%, #ffffff 100%);">
                    <div class="d-flex align-items-center gap-2 mb-3 text-scout-maroon">
                        <i class="fa-solid fa-money-bill-wave fs-5"></i>
                        <h6 class="fw-bold mb-0">Pratinjau Pembayaran</h6>
                    </div>
                    
                    <div class="p-3 bg-white rounded-3 border mb-3">
                        <small class="text-muted d-block">Biaya per Kontingen:</small>
                        <div class="fs-4 fw-bold text-scout-maroon font-monospace">
                            <?= format_rupiah($rawSettings['biaya_kontingen'] ?? '500000') ?>
                        </div>
                    </div>

                    <div class="p-3 bg-white rounded-3 border mb-3">
                        <small class="text-muted d-block">Rekening Bank Panitia:</small>
                        <strong class="text-scout-navy d-block"><?= e($rawSettings['bank_nama'] ?? 'Bank NTB Syariah') ?></strong>
                        <span class="badge bg-scout-navy font-monospace fs-6 mt-1"><?= e($rawSettings['bank_rekening'] ?? '501.02.001928.01') ?></span>
                        <small class="text-muted d-block mt-1">a.n <?= e($rawSettings['bank_atas_nama'] ?? 'Racana Universitas Samawa') ?></small>
                    </div>

                    <?php if ($qrisUrl): ?>
                        <div class="p-3 bg-white rounded-3 border text-center">
                            <small class="text-muted d-block mb-2">QRIS Panitia Aktif:</small>
                            <img src="<?= $qrisUrl ?>" alt="QRIS" class="img-fluid rounded border p-1" style="max-height: 160px;">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- QUICK DOCS MANAGEMENT CARD -->
                <div class="custom-card p-4 shadow-sm border-0" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 50%, #800000 100%); border-radius: 14px; border-bottom: 3px solid #d97706; color: white;">
                    <h6 class="fw-bold mb-2 text-warning"><i class="fa-solid fa-file-arrow-down me-2"></i> Pusat Dokumen &amp; Juknis</h6>
                    <p class="small mb-3" style="color: rgba(255,255,255,0.90); line-height: 1.6;">
                        Upload berkas Juklak, Juknis lomba, Surat Permohonan Delegasi, dan Blangko A.01 untuk diunduh oleh sekolah pendaftar.
                    </p>
                    <a href="<?= base_url('admin/dokumen.php') ?>" class="btn btn-scout-gold w-100 fw-bold shadow-sm">
                        <i class="fa-solid fa-folder-open me-2"></i> Buka Manajemen Dokumen
                    </a>
                </div>

            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
document.querySelectorAll('.quota-category-input').forEach(input => {
    input.addEventListener('input', () => {
        const total = Array.from(document.querySelectorAll('.quota-category-input'))
            .reduce((sum, field) => sum + (Number.parseInt(field.value, 10) || 0), 0);
        document.getElementById('totalKuotaPreview').textContent = total;
    });
});
</script>
</body>
</html>
