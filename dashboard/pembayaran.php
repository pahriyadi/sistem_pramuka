<?php
/**
 * Konfirmasi & Pembayaran Kontingen Sekolah
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Pembayaran Kontingen';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();
$namaBrand = get_setting('nama_brand', 'RACANA UNSA');

if (!$sekolah) {
    die("Data profil sekolah tidak ditemukan.");
}

$sekolahId = (int)$sekolah['id'];
$errors = [];

// Fetch Dynamic Payment Settings from Admin
$stmtSettings = $pdo->query("SELECT key_name, key_value FROM pengaturan_portal");
$settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$biayaKontingen    = $settings['biaya_kontingen'] ?? '500000';
$bankNama          = $settings['bank_nama'] ?? 'Bank NTB Syariah';
$bankRekening      = $settings['bank_rekening'] ?? '501.02.001928.01';
$bankAtasNama      = $settings['bank_atas_nama'] ?? 'Racana Universitas Samawa (Siti Rohana)';
$fotoQris          = $settings['foto_qris'] ?? 'qris-racana-unsa.png';
$petunjukBayar     = $settings['petunjuk_pembayaran'] ?? "1. Lakukan pembayaran pendaftaran kontingen sebesar nominal yang tertera melalui Transfer Rekening atau Scan QRIS resmi.\n2. Simpan struk/tangkapan layar bukti transfer yang jelas.\n3. Unggah bukti pembayaran pada formulir konfirmasi di bawah ini.\n4. Panitia akan memverifikasi pembayaran Anda maksimal 1x24 jam kerja.";

$qrisPath = dirname(__DIR__) . '/uploads/qris/' . $fotoQris;
if (!empty($fotoQris) && file_exists($qrisPath)) {
    $qrisUrl = base_url('uploads/qris/' . $fotoQris);
} else {
    // Default QRIS graphic fallback
    $qrisUrl = "https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=" . urlencode("QRIS: RACANA UNIVERSITAS SAMAWA - BIAYA PENDAFTARAN KONTINGEN: Rp 500.000");
}

// Handle Form Submission (Upload Proof of Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi keamanan form (CSRF) tidak sah. Silakan muat ulang halaman.';
    } else {
        $metodeBayar       = $_POST['metode_bayar'] ?? 'transfer_bank';
        $bankPengirim      = trim($_POST['bank_pengirim'] ?? '');
        $atasNamaPengirim  = trim($_POST['atas_nama_pengirim'] ?? '');
        $tanggalBayar      = trim($_POST['tanggal_bayar'] ?? date('Y-m-d'));
        $jumlahBayar       = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah_bayar'] ?? $biayaKontingen);
        $catatanSekolah    = trim($_POST['catatan_sekolah'] ?? '');

        if (!in_array($metodeBayar, ['transfer_bank', 'qris'])) {
            $metodeBayar = 'transfer_bank';
        }

        if (empty($bankPengirim)) {
            $errors[] = 'Nama bank / e-wallet pengirim wajib diisi.';
        }
        if (empty($atasNamaPengirim)) {
            $errors[] = 'Nama pengirim / pemilik rekening wajib diisi.';
        }
        if ($jumlahBayar <= 0) {
            $errors[] = 'Jumlah pembayaran tidak valid.';
        }

        // Validate File Upload
        if (empty($_FILES['bukti_transfer']['name']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Wajib mengunggah foto / file struk bukti transfer pembayaran.';
        } else {
            $uploadResult = upload_bukti_pembayaran($_FILES['bukti_transfer']);
            if (!$uploadResult['status']) {
                $errors[] = $uploadResult['message'];
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $kodeBayar = generate_kode_bayar($sekolahId);
                $buktiFile = $uploadResult['filename'];

                // 1. Insert into pembayaran table
                $stmtInsert = $pdo->prepare("
                    INSERT INTO pembayaran (
                        sekolah_id, kode_bayar, jumlah_bayar, metode_bayar,
                        bank_pengirim, atas_nama_pengirim, tanggal_bayar,
                        bukti_transfer, status, catatan_sekolah
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, 'menunggu_konfirmasi', ?
                    )
                ");
                $stmtInsert->execute([
                    $sekolahId, $kodeBayar, $jumlahBayar, $metodeBayar,
                    $bankPengirim, $atasNamaPengirim, $tanggalBayar,
                    $buktiFile, $catatanSekolah
                ]);

                // 2. Update status_pembayaran in sekolah table
                $stmtUpSek = $pdo->prepare("UPDATE sekolah SET status_pembayaran = 'menunggu_konfirmasi' WHERE id = ?");
                $stmtUpSek->execute([$sekolahId]);

                $pdo->commit();

                set_flash('success', 'Konfirmasi pembayaran berhasil dikirim! Kode Pembayaran Anda: <strong>' . $kodeBayar . '</strong>. Panitia akan segera memverifikasi berkas Anda.');
                header('Location: ' . base_url('dashboard/pembayaran.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Terjadi kesalahan sistem saat menyimpan pembayaran: ' . $e->getMessage();
            }
        }
    }
}

// Re-fetch latest school info
$stmtSekRefresh = $pdo->prepare("SELECT * FROM sekolah WHERE id = ?");
$stmtSekRefresh->execute([$sekolahId]);
$sekolah = $stmtSekRefresh->fetch();

$statusBayar = $sekolah['status_pembayaran'] ?? 'belum_bayar';

// Fetch Payment History
$stmtHistory = $pdo->prepare("
    SELECT * FROM pembayaran 
    WHERE sekolah_id = ? 
    ORDER BY id DESC
");
$stmtHistory->execute([$sekolahId]);
$riwayatPembayaran = $stmtHistory->fetchAll();

// Latest submission
$latestPembayaran = !empty($riwayatPembayaran) ? $riwayatPembayaran[0] : null;
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
    <style>
        .bank-card {
            background: linear-gradient(135deg, #07172b 0%, #0b2545 60%, #800000 100%);
            border-radius: 16px;
            color: #fff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .bank-card::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(217, 119, 6, 0.2);
        }
        .qris-box {
            background: #ffffff;
            border: 2px dashed #0b2545;
            border-radius: 16px;
            text-align: center;
            padding: 20px;
        }
        .status-hero-banner {
            border-radius: 16px;
            padding: 20px 24px;
        }
    </style>
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <!-- Header Navigation -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Pembayaran Kontingen</h4>
                <small class="text-muted"><i class="fa-solid fa-school text-success me-1"></i> <?= e($sekolah['nama_sekolah']) ?></small>
            </div>
        </div>
        <div>
            <a href="<?= base_url('dashboard/index.php') ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4 shadow-sm border-0">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Perhatian:</h6>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- REALTIME STATUS HERO BANNER -->
    <?php if ($statusBayar === 'disetujui'): ?>
        <div class="status-hero-banner bg-success bg-opacity-10 border border-success mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-success mb-1">Pembayaran Telah Disetujui &amp; LUNAS</h5>
                    <p class="text-muted small mb-0">
                        Kontingen <strong><?= e($sekolah['nama_sekolah']) ?></strong> telah menyelesaikan administrasi pendaftaran. Seluruh anggota berhak mengikuti rangkaian kegiatan dan lomba.
                    </p>
                </div>
            </div>
            <div>
                <span class="badge bg-success fs-6 px-3 py-2"><i class="fa-solid fa-shield-check me-1"></i> LUNAS TERVERIFIKASI</span>
            </div>
        </div>
    <?php elseif ($statusBayar === 'menunggu_konfirmasi'): ?>
        <div class="status-hero-banner bg-primary bg-opacity-10 border border-primary mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-primary mb-1">Menunggu Konfirmasi &amp; Verifikasi Admin</h5>
                    <p class="text-muted small mb-0">
                        Bukti pembayaran telah berhasil dikirim pada tanggal <strong><?= format_tanggal_indonesia($latestPembayaran['tanggal_bayar'] ?? date('Y-m-d')) ?></strong> dan sedang diverifikasi oleh panitia (maksimal 1x24 jam).
                    </p>
                </div>
            </div>
            <div>
                <span class="badge bg-primary fs-6 px-3 py-2"><i class="fa-solid fa-spinner fa-spin me-1"></i> SEDANG DIPROSES</span>
            </div>
        </div>
    <?php elseif ($statusBayar === 'ditolak'): ?>
        <div class="status-hero-banner bg-danger bg-opacity-10 border border-danger mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-danger mb-1">Pembayaran Ditolak oleh Panitia</h5>
                    <p class="text-muted small mb-0">
                        Catatan Panitia: <em>"<?= e($latestPembayaran['catatan_admin'] ?? 'Bukti transfer tidak terbaca atau nominal tidak sesuai.') ?>"</em>. Silakan periksa kembali dan unggah ulang bukti yang benar.
                    </p>
                </div>
            </div>
            <div>
                <span class="badge bg-danger fs-6 px-3 py-2"><i class="fa-solid fa-rotate me-1"></i> HARAP UPLOAD ULANG</span>
            </div>
        </div>
    <?php else: ?>
        <div class="status-hero-banner bg-warning bg-opacity-15 border border-warning mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-1">Status: Belum Melakukan Pembayaran</h5>
                    <p class="text-muted small mb-0">
                        Silakan selesaikan biaya pendaftaran kontingen sebesar <strong><?= format_rupiah($biayaKontingen) ?></strong> melalui Transfer Rekening Bank atau Scan QRIS resmi di bawah ini.
                    </p>
                </div>
            </div>
            <div>
                <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> BELUM BAYAR</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- LEFT COLUMN: STATIC REKENING BANK & QRIS VIEW -->
        <div class="col-lg-5">
            <!-- 1. Bank Account Card -->
            <div class="bank-card p-4 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-building-columns text-warning fs-5"></i>
                        <span class="fw-bold text-white fs-6"><?= e($bankNama) ?></span>
                    </div>
                    <span class="badge bg-warning text-dark fw-bold">TRANSFER BANK</span>
                </div>

                <div class="mb-3">
                    <small class="text-white text-opacity-75 d-block" style="font-size: 0.75rem;">NOMOR REKENING TUJUAN:</small>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="fs-4 fw-bold font-monospace text-white" id="rekText"><?= e($bankRekening) ?></span>
                        <button type="button" class="btn btn-sm btn-light py-1 px-2 text-dark fw-bold" onclick="copyRekening()" title="Salin Nomor Rekening">
                            <i class="fa-solid fa-copy me-1"></i> Salin
                        </button>
                    </div>
                    <div id="copyAlert" class="small text-warning mt-1" style="display: none;"><i class="fa-solid fa-check"></i> Nomor rekening berhasil disalin!</div>
                </div>

                <div class="d-flex justify-content-between align-items-end pt-2 border-top border-white border-opacity-10">
                    <div>
                        <small class="text-white text-opacity-75 d-block" style="font-size: 0.72rem;">ATAS NAMA:</small>
                        <strong class="text-white small"><?= e($bankAtasNama) ?></strong>
                    </div>
                    <div class="text-end">
                        <small class="text-white text-opacity-75 d-block" style="font-size: 0.72rem;">TAGIHAN KONTINGEN:</small>
                        <strong class="text-warning fs-6 font-monospace"><?= format_rupiah($biayaKontingen) ?></strong>
                    </div>
                </div>
            </div>

            <!-- 2. QRIS Code Box -->
            <div class="custom-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-qrcode text-success me-2"></i> Scan QRIS Resmi Panitia
                    </h6>
                    <span class="badge bg-success bg-opacity-15 text-success">Semua E-Wallet &amp; Bank</span>
                </div>

                <div class="qris-box mb-3">
                    <?php if ($qrisUrl): ?>
                        <img src="<?= $qrisUrl ?>" alt="QRIS <?= e($namaBrand) ?>" class="img-fluid rounded mb-2" style="max-height: 220px; object-fit: contain;">
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalZoomQris">
                                <i class="fa-solid fa-magnifying-glass-plus me-1"></i> Perbesar QRIS
                            </button>
                            <a href="<?= $qrisUrl ?>" download="QRIS-Racana-UNSA.png" class="btn btn-scout-primary btn-sm">
                                <i class="fa-solid fa-download me-1"></i> Simpan Gambar
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="py-4 text-muted">
                            <i class="fa-solid fa-qrcode fs-1 mb-2 text-secondary"></i>
                            <div class="small fw-semibold">Foto QRIS Sedang Dipersiapkan oleh Admin</div>
                            <small class="text-muted">Silakan gunakan metode transfer rekening bank di atas.</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="small text-muted">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Mendukung BCA Mobile, Livin Mandiri, BRImo, BNI Mobile, GoPay, OVO, ShopeePay, DANA, LinkAja.
                </div>
            </div>

            <!-- 3. Instructions Card -->
            <div class="custom-card p-4 bg-light">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-info text-primary me-2"></i> Petunjuk Pembayaran</h6>
                <div class="small text-muted" style="white-space: pre-line; line-height: 1.6;">
                    <?= e($petunjukBayar) ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: FORM KONFIRMASI UPLOAD / STATUS SUBMISSION -->
        <div class="col-lg-7">
            <!-- Upload Form -->
            <div class="custom-card p-4 p-md-5">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Formulir Konfirmasi Pembayaran
                        </h5>
                        <small class="text-muted">Isi data transfer dan unggah foto/berkas struk pembayaran</small>
                    </div>
                    <?php if ($statusBayar === 'disetujui'): ?>
                        <span class="badge bg-success fs-6"><i class="fa-solid fa-check-double me-1"></i> Lunas</span>
                    <?php elseif ($statusBayar === 'menunggu_konfirmasi'): ?>
                        <span class="badge bg-primary fs-6"><i class="fa-solid fa-clock me-1"></i> Menunggu</span>
                    <?php endif; ?>
                </div>

                <?php if ($statusBayar === 'disetujui'): ?>
                    <!-- If already approved, show congratulations & payment voucher detail -->
                    <div class="p-4 bg-success bg-opacity-10 rounded-4 border border-success text-center mb-4">
                        <i class="fa-solid fa-circle-check text-success display-4 mb-2"></i>
                        <h5 class="fw-bold text-success mb-1">Pembayaran Kontingen Berhasil Diverifikasi!</h5>
                        <p class="text-muted small mb-3">Terima kasih, pembayaran pendaftaran kontingen Anda telah disahkan oleh Bendahara Panitia.</p>

                        <?php if ($latestPembayaran): ?>
                            <div class="p-3 bg-white rounded-3 border text-start small mb-3">
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Kode Transaksi:</span>
                                        <strong class="font-monospace text-dark"><?= e($latestPembayaran['kode_bayar']) ?></strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Nominal Dibayar:</span>
                                        <strong class="text-success fw-bold font-monospace"><?= format_rupiah($latestPembayaran['jumlah_bayar']) ?></strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Metode &amp; Pengirim:</span>
                                        <strong><?= strtoupper(str_replace('_', ' ', $latestPembayaran['metode_bayar'])) ?> &bull; <?= e($latestPembayaran['bank_pengirim']) ?></strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Atas Nama Pengirim:</span>
                                        <strong><?= e($latestPembayaran['atas_nama_pengirim']) ?></strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Tanggal Transfer:</span>
                                        <strong><?= format_tanggal_indonesia($latestPembayaran['tanggal_bayar']) ?></strong>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="text-muted d-block">Status Verifikasi:</span>
                                        <span class="badge bg-success">DISETUJUI</span>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalBuktiLatest">
                                <i class="fa-solid fa-eye me-1"></i> Lihat Struk Bukti Transfer Anda
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Form will be available if not yet paid, rejected, or if user wants to upload another receipt -->
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="metode_bayar" class="form-select" required>
                                <option value="transfer_bank">Transfer Rekening Bank</option>
                                <option value="qris">Scan QRIS Panitia</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Jumlah yang Ditransfer (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="jumlah_bayar" class="form-control fw-bold font-monospace" value="<?= e($biayaKontingen) ?>" required min="1000" step="1000">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Nama Bank / E-Wallet Pengirim <span class="text-danger">*</span></label>
                            <input type="text" name="bank_pengirim" class="form-control" placeholder="Contoh: Bank BRI / Mandiri / GoPay" value="<?= e($latestPembayaran['bank_pengirim'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Atas Nama Pemilik Rekening / Pengirim <span class="text-danger">*</span></label>
                            <input type="text" name="atas_nama_pengirim" class="form-control" placeholder="Contoh: Ahmad Yani / Pembina Gudep" value="<?= e($latestPembayaran['atas_nama_pengirim'] ?? ($sekolah['nama_pembina'] ?? '')) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Unggah Struk / Bukti Transfer <span class="text-danger">*</span></label>
                            <input type="file" name="bukti_transfer" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                            <div class="form-text small">Format JPG, JPEG, PNG, WEBP, atau PDF (Max 4MB).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-dark">Catatan Tambahan (Opsional)</label>
                            <textarea name="catatan_sekolah" class="form-control" rows="2" placeholder="Contoh: Pembayaran pendaftaran kontingen SMAN 1 Sumbawa lunas."></textarea>
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-scout-gold w-100 py-3 fw-bold fs-6 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-2"></i> <?= ($statusBayar === 'ditolak') ? 'Kirim Ulang Bukti Pembayaran' : 'Kirim Konfirmasi Pembayaran' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PAYMENT HISTORY TABLE -->
    <div class="custom-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Riwayat Konfirmasi Pembayaran
            </h5>
            <small class="text-muted">Total <?= count($riwayatPembayaran) ?> transaksi</small>
        </div>

        <?php if (empty($riwayatPembayaran)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-receipt fs-1 mb-2"></i>
                <h6 class="fw-bold">Belum Ada Riwayat Pembayaran</h6>
                <p class="small mb-0">Formulir di atas dapat Anda gunakan untuk mengirimkan konfirmasi bukti transfer.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Bayar</th>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>Bank &amp; Pengirim</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Bukti Transfer</th>
                            <th>Catatan Panitia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayatPembayaran as $row): 
                            $buktiUrl = base_url('uploads/pembayaran/' . $row['bukti_transfer']);
                            $isPdf = strtolower(pathinfo($row['bukti_transfer'], PATHINFO_EXTENSION)) === 'pdf';
                        ?>
                            <tr>
                                <td class="font-monospace fw-bold text-dark"><?= e($row['kode_bayar']) ?></td>
                                <td><?= format_tanggal_indonesia($row['tanggal_bayar']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= strtoupper(str_replace('_', ' ', $row['metode_bayar'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($row['bank_pengirim']) ?></div>
                                    <small class="text-muted">a.n <?= e($row['atas_nama_pengirim']) ?></small>
                                </td>
                                <td class="fw-bold text-success font-monospace"><?= format_rupiah($row['jumlah_bayar']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'disetujui'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> DISETUJUI</span>
                                    <?php elseif ($row['status'] === 'ditolak'): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> DITOLAK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> MENUNGGU</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isPdf): ?>
                                        <a href="<?= $buktiUrl ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-file-pdf me-1"></i> Lihat PDF
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $buktiUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fa-solid fa-image me-1"></i> Lihat Foto
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($row['catatan_admin']) ? '<span class="text-danger small">' . e($row['catatan_admin']) . '</span>' : '<span class="text-muted">-</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: ZOOM QRIS -->
<?php if ($qrisUrl): ?>
<div class="modal fade" id="modalZoomQris" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-qrcode text-success me-2"></i> QRIS Resmi <?= e($namaBrand) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="<?= $qrisUrl ?>" alt="QRIS Penuh" class="img-fluid rounded border mb-3" style="max-height: 400px;">
                <h6 class="fw-bold text-dark mb-1"><?= e($bankAtasNama) ?></h6>
                <div class="text-success fw-bold font-monospace fs-5"><?= format_rupiah($biayaKontingen) ?></div>
            </div>
            <div class="modal-footer">
                <a href="<?= $qrisUrl ?>" download="QRIS-Racana-UNSA.png" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-download me-1"></i> Unduh Gambar QRIS
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: PREVIEW LATEST BUKTI -->
<?php if ($latestPembayaran): 
    $buktiUrlLatest = base_url('uploads/pembayaran/' . $latestPembayaran['bukti_transfer']);
    $isPdfLatest = strtolower(pathinfo($latestPembayaran['bukti_transfer'], PATHINFO_EXTENSION)) === 'pdf';
?>
<div class="modal fade" id="modalBuktiLatest" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt text-success me-2"></i> Struk Bukti Transfer: <?= e($latestPembayaran['kode_bayar']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <?php if ($isPdfLatest): ?>
                    <iframe src="<?= $buktiUrlLatest ?>" style="width: 100%; height: 500px; border: none;"></iframe>
                <?php else: ?>
                    <img src="<?= $buktiUrlLatest ?>" alt="Struk Pembayaran" class="img-fluid rounded border" style="max-height: 500px;">
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="<?= $buktiUrlLatest ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-up-right-from-square me-1"></i> Buka di Tab Baru
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
function copyRekening() {
    const rek = document.getElementById('rekText').innerText.trim();
    navigator.clipboard.writeText(rek).then(() => {
        const alertEl = document.getElementById('copyAlert');
        alertEl.style.display = 'block';
        setTimeout(() => {
            alertEl.style.display = 'none';
        }, 3000);
    });
}
</script>
</body>
</html>
