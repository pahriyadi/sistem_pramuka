<?php
/**
 * Dashboard Utama Kontingen Sekolah
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Dashboard Kontingen';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();

if (!$sekolah) {
    die("Data profil sekolah tidak ditemukan. Silakan hubungi admin.");
}

$sekolahId = (int)$sekolah['id'];

// Get Participant Counts by Category
$stmtCounts = $pdo->prepare("
    SELECT 
        k.kode_kategori,
        k.nama_kategori,
        k.kuota_max,
        COUNT(p.id) as total_terisi
    FROM kategori_peserta k
    LEFT JOIN peserta p ON p.kategori_id = k.id AND p.sekolah_id = ? AND p.status = 'aktif'
    GROUP BY k.id
    ORDER BY k.urutan ASC
");
$stmtCounts->execute([$sekolahId]);
$kategoriStats = $stmtCounts->fetchAll();

// Total Peserta Aktif & Draft
$totalPeserta = 0;
$maxTotalKuota = array_sum(array_map(static fn($stat) => (int)$stat['kuota_max'], $kategoriStats));
$totalPutra = 0;
$totalPutri = 0;
$totalBindamping = 0;

foreach ($kategoriStats as $stat) {
    $totalPeserta += (int)$stat['total_terisi'];
    if (strpos($stat['kode_kategori'], 'BAR_PUTRA') !== false) {
        $totalPutra += (int)$stat['total_terisi'];
    } elseif (strpos($stat['kode_kategori'], 'BAR_PUTRI') !== false) {
        $totalPutri += (int)$stat['total_terisi'];
    } elseif (strpos($stat['kode_kategori'], 'BINDAMPING') !== false) {
        $totalBindamping += (int)$stat['total_terisi'];
    }
}

// Count Draft records
$stmtDraftCount = $pdo->prepare("SELECT COUNT(*) FROM peserta WHERE sekolah_id = ? AND status = 'draft'");
$stmtDraftCount->execute([$sekolahId]);
$totalDraftHome = (int)$stmtDraftCount->fetchColumn();

// Payment Info and Settings
$statusPembayaran = $sekolah['status_pembayaran'] ?? 'belum_bayar';
$statusKunci = $sekolah['status_kunci'] ?? 'terbuka';
$isLocked = ($statusKunci === 'terkunci');
$biayaKontingenSetting = get_setting('biaya_kontingen', '500000');

$stmtLatestPay = $pdo->prepare("SELECT * FROM pembayaran WHERE sekolah_id = ? ORDER BY id DESC LIMIT 1");
$stmtLatestPay->execute([$sekolahId]);
$latestBayarHome = $stmtLatestPay->fetch();

// Recent Participants (Both Aktif and Draft)
$stmtRecent = $pdo->prepare("
    SELECT p.*, k.nama_kategori,
           GROUP_CONCAT(m.nama_lomba SEPARATOR ', ') as daftar_lomba
    FROM peserta p
    JOIN kategori_peserta k ON p.kategori_id = k.id
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    LEFT JOIN mata_lomba m ON pl.lomba_id = m.id
    WHERE p.sekolah_id = ? AND p.status IN ('aktif', 'draft')
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 6
");
$stmtRecent->execute([$sekolahId]);
$recentPeserta = $stmtRecent->fetchAll();

$progressPersen = $maxTotalKuota > 0 ? min(100, round(($totalPeserta / $maxTotalKuota) * 100)) : 0;
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
    <!-- Top Header Navigation Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Dashboard Kontingen</h4>
                <small class="text-muted"><i class="fa-solid fa-school text-scout-navy me-1"></i> <?= e($sekolah['nama_sekolah']) ?></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn <?= $isLocked ? 'btn-outline-scout-navy' : 'btn-scout-gold' ?> btn-sm px-3 shadow-sm">
                <i class="fa-solid <?= $isLocked ? 'fa-eye' : 'fa-user-plus' ?> me-1"></i> <?= $isLocked ? 'Lihat Formulir Kontingen' : 'Tambah Peserta' ?>
            </a>
            <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" class="btn btn-outline-scout-navy btn-sm px-3">
                <i class="fa-solid fa-id-card me-1"></i> Cetak ID Card
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- REGISTRATION LOCKED NOTICE -->
    <?php if ($isLocked): ?>
        <div class="alert alert-secondary border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #dc3545 !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-6">Pendaftaran Kontingen Telah Difinalisasi &amp; Terkunci</div>
                    <small class="text-muted">Data anggota kontingen sekolah telah dikunci resmi. Akses edit/hapus dinonaktifkan. Hubungi Panitia jika memerlukan perubahan.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-outline-danger btn-sm px-3 fw-bold">
                    <i class="fa-solid fa-file-lines me-1"></i> Lihat Data Terkunci
                </a>
            </div>
        </div>
    <?php elseif ($totalDraftHome > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-bookmark fs-3 text-warning"></i>
                <div>
                    <div class="fw-bold text-dark">Ada <?= $totalDraftHome ?> Anggota Kontingen Berstatus DRAF</div>
                    <small class="text-muted">Data berstatus Draf belum masuk ke rekap resmi. Silakan buka Form Multi-Batch untuk melengkapi dan memfinalisasi data.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-dark btn-sm px-3 fw-bold">
                    <i class="fa-solid fa-layer-group me-1"></i> Buka Form Multi-Batch &amp; Finalisasi
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- STATUS PEMBAYARAN KONTINGEN WIDGET -->
    <?php if ($statusPembayaran === 'disetujui'): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #198754 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                <div>
                    <div class="fw-bold text-success fs-6">Pembayaran Kontingen: LUNAS &amp; TERVERIFIKASI</div>
                    <small class="text-muted">Administrasi pendaftaran kontingen Anda telah disahkan. Kontingen siap mengikuti seluruh rangkaian perlombaan.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="btn btn-outline-success btn-sm fw-bold">
                    <i class="fa-solid fa-receipt me-1"></i> Rincian Pembayaran
                </a>
            </div>
        </div>
    <?php elseif ($statusPembayaran === 'menunggu_konfirmasi'): ?>
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #0dcaf0 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-hourglass-half fs-2 text-primary"></i>
                <div>
                    <div class="fw-bold text-primary fs-6">Pembayaran Kontingen: Menunggu Verifikasi Panitia</div>
                    <small class="text-muted">Bukti pembayaran telah terkirim (Kode: <?= e($latestBayarHome['kode_bayar'] ?? '-') ?>). Mohon tunggu verifikasi dalam 1x24 jam.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="btn btn-primary btn-sm fw-bold">
                    <i class="fa-solid fa-eye me-1"></i> Pantau Status
                </a>
            </div>
        </div>
    <?php elseif ($statusPembayaran === 'ditolak'): ?>
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #dc3545 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-circle-exclamation fs-2 text-danger"></i>
                <div>
                    <div class="fw-bold text-danger fs-6">Pembayaran Kontingen Ditolak: Harap Upload Ulang</div>
                    <small class="text-muted">Catatan Panitia: "<?= e($latestBayarHome['catatan_admin'] ?? 'Bukti transfer tidak valid') ?>". Silakan upload ulang bukti yang sah.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="btn btn-danger btn-sm fw-bold">
                    <i class="fa-solid fa-upload me-1"></i> Upload Ulang Bukti
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" style="border-left: 5px solid #ffc107 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-credit-card fs-2 text-warning"></i>
                <div>
                    <div class="fw-bold text-dark fs-6">Kontingen Belum Melakukan Pembayaran Pendaftaran</div>
                    <small class="text-muted">Biaya pendaftaran: <strong><?= format_rupiah($biayaKontingenSetting) ?></strong>. Pembayaran dapat dilakukan via Transfer Bank atau Scan QRIS.</small>
                </div>
            </div>
            <div>
                <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="btn btn-scout-gold btn-sm fw-bold shadow-sm">
                    <i class="fa-solid fa-wallet me-1"></i> Bayar Sekarang
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Banner Status Kontingen -->
    <div class="custom-card p-4 mb-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #0d5c3a 0%, #073822 100%);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-warning text-dark fw-bold small mb-3">
                    <i class="fa-solid fa-circle-check"></i> Status Verifikasi Berkas: <?= strtoupper($sekolah['status_verifikasi']) ?>
                </div>
                <h3 class="fw-bold text-white mb-2"><?= e($sekolah['nama_sekolah']) ?></h3>
                <p class="text-light text-opacity-75 mb-3 small">
                    <i class="fa-solid fa-location-dot me-1 text-warning"></i> <?= e($sekolah['alamat_sekolah']) ?> &bull; 
                    <i class="fa-solid fa-user-tie me-1 text-warning ms-2"></i> Pembina: <?= e($sekolah['nama_pembina'] ?: 'Belum diisi') ?> (<?= e($sekolah['no_wa_pembina'] ?: '-') ?>)
                </p>

                <!-- Quota Progress Bar -->
                <div class="mb-2 d-flex justify-content-between align-items-center small">
                    <span class="text-white fw-semibold">Progres Pengisian Kuota Kontingen (Maksimal <?= $maxTotalKuota ?> Orang):</span>
                    <span class="fw-bold text-warning"><?= $totalPeserta ?> / <?= $maxTotalKuota ?> Orang (<?= $progressPersen ?>%)</span>
                </div>
                <div class="progress" style="height: 12px; background: rgba(255,255,255,0.2);">
                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $progressPersen ?>%;" aria-valuenow="<?= $progressPersen ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <div class="bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-10 text-center text-lg-end">
                    <div class="small text-white-50">Sisa Kuota Pendaftaran:</div>
                    <div class="display-6 fw-bold text-warning"><?= max(0, $maxTotalKuota - $totalPeserta) ?></div>
                    <small class="text-white-50">Orang Tersedia</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Widgets (4 Kategori Kontingen) -->
    <div class="row g-3 mb-4">
        <?php foreach ($kategoriStats as $stat): 
            $color = 'green';
            $icon = 'fa-user-group';
            if (strpos($stat['kode_kategori'], 'BAR_PUTRA') !== false) {
                $color = 'blue';
                $icon = 'fa-person';
            } elseif (strpos($stat['kode_kategori'], 'BAR_PUTRI') !== false) {
                $color = 'purple';
                $icon = 'fa-person-dress';
            } elseif (strpos($stat['kode_kategori'], 'BINDAMPING') !== false) {
                $color = 'gold';
                $icon = 'fa-user-tie';
            }
        ?>
            <div class="col-xl-3 col-md-6">
                <div class="stat-widget <?= $color ?> h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted small fw-semibold d-block"><?= e($stat['nama_kategori']) ?></span>
                            <h3 class="fw-bold mb-0 text-dark"><?= $stat['total_terisi'] ?> <span class="text-muted fs-6 fw-normal">/ <?= $stat['kuota_max'] ?></span></h3>
                        </div>
                        <div class="feature-icon-wrapper icon-<?= $color ?> mb-0" style="width: 45px; height: 45px;">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                    </div>
                    <div class="small text-muted">
                        <?= ($stat['total_terisi'] >= $stat['kuota_max']) ? '<span class="text-success fw-bold"><i class="fa-solid fa-check"></i> Kuota Penuh</span>' : 'Sisa Kuota: ' . ($stat['kuota_max'] - $stat['total_terisi']) . ' orang' ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Action Cards & Recent Participants -->
    <div class="row g-4 mb-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="custom-card p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-bolt text-warning me-2"></i> Aksi Cepat Kontingen</h5>
                
                <div class="d-flex flex-column gap-2">
                    <a href="<?= base_url('dashboard/pembayaran.php') ?>" class="btn btn-outline-success text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-wallet me-2 text-success"></i> Pembayaran (Rekening &amp; QRIS)</span>
                        <?php if ($statusPembayaran === 'disetujui'): ?>
                            <span class="badge bg-success">Lunas</span>
                        <?php elseif ($statusPembayaran === 'menunggu_konfirmasi'): ?>
                            <span class="badge bg-primary">Menunggu</span>
                        <?php elseif ($statusPembayaran === 'ditolak'): ?>
                            <span class="badge bg-danger">Ditolak</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum Bayar</span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-outline-success text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-layer-group me-2 text-warning"></i> Tambah Peserta Multi-Batch</span>
                        <i class="fa-solid fa-angle-right text-muted"></i>
                    </a>
                    <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-primary text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-users me-2 text-primary"></i> Kelola Data Peserta</span>
                        <i class="fa-solid fa-angle-right text-muted"></i>
                    </a>
                    <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" class="btn btn-outline-warning text-dark text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-id-card me-2 text-warning"></i> Cetak ID Card (Siap Print)</span>
                        <i class="fa-solid fa-angle-right text-muted"></i>
                    </a>
                    <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" class="btn btn-outline-secondary text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-file-pdf me-2 text-danger"></i> Unduh Formulir A.01 &amp; B.01</span>
                        <i class="fa-solid fa-angle-right text-muted"></i>
                    </a>
                    <a href="<?= base_url('dashboard/sekolah.php') ?>" class="btn btn-outline-info text-dark text-start py-2 px-3 d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-pen-to-square me-2 text-info"></i> Edit Profil &amp; Gudep Sekolah</span>
                        <i class="fa-solid fa-angle-right text-muted"></i>
                    </a>
                </div>


                <div class="mt-4 p-3 bg-light rounded-3 small text-muted">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i>
                    Pastikan seluruh peserta mengunggah <strong>Pas Foto</strong> agar ID Card dapat tercetak dengan sempurna.
                </div>
            </div>
        </div>

        <!-- Recent Participants Table -->
        <div class="col-lg-8">
            <div class="custom-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-line text-success me-2"></i> Peserta yang Baru Ditambahkan</h5>
                    <a href="<?= base_url('dashboard/peserta.php') ?>" class="small text-success fw-bold text-decoration-none">
                        Lihat Semua (<?= $totalPeserta ?>) <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($recentPeserta)): ?>
                    <div class="text-center py-5">
                        <div class="feature-icon-wrapper icon-green mx-auto mb-3">
                            <i class="fa-solid fa-user-slash fs-3"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Belum Ada Peserta Terdaftar</h6>
                        <p class="text-muted small mb-3">Silakan mulai menambahkan anggota Barisan Putra, Putri, atau Bindamping.</p>
                        <a href="<?= base_url('dashboard/peserta-tambah.php') ?>" class="btn btn-scout-primary btn-sm px-4">
                            <i class="fa-solid fa-user-plus me-1"></i> Tambah Peserta Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th>Foto</th>
                                    <th>Nama Peserta</th>
                                    <th>Kategori</th>
                                    <th>Umur</th>
                                    <th>Mata Lomba</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPeserta as $p): 
                                    $umurData = hitung_umur($p['tanggal_lahir']);
                                    $fotoUrl = !empty($p['foto']) ? base_url('uploads/peserta/' . $p['foto']) : null;
                                ?>
                                    <tr>
                                        <td style="width: 50px;">
                                            <?php if ($fotoUrl): ?>
                                                <img src="<?= $fotoUrl ?>" alt="<?= e($p['nama_lengkap']) ?>" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover; border: 2px solid #0d5c3a;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-muted border" style="width: 38px; height: 38px;">
                                                    <i class="fa-solid fa-user text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($p['nama_lengkap']) ?></div>
                                            <small class="text-muted">Kelas: <?= e($p['kelas']) ?> &bull; <?= $p['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                                <?= e($p['nama_kategori']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-15 text-success font-monospace">
                                                <?= $umurData['teks'] ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 180px;" class="text-truncate">
                                            <?= e($p['daftar_lomba'] ?: '-') ?>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('dashboard/peserta-edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit Peserta">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
