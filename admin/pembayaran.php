<?php
/**
 * Kelola & Verifikasi Pembayaran Kontingen - Admin Panel
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Verifikasi Pembayaran Kontingen';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_admin();

$pdo = getDB();
$adminUser = get_auth_user();
$adminId = (int)($adminUser['id'] ?? 1);

// Handle Verification Action (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        set_flash('danger', 'Validasi keamanan form (CSRF) tidak sah.');
    } else {
        $pembayaranId = (int)($_POST['pembayaran_id'] ?? 0);
        $statusBaru   = $_POST['status'] ?? 'disetujui';
        $catatanAdmin = trim($_POST['catatan_admin'] ?? '');

        if ($pembayaranId > 0 && in_array($statusBaru, ['disetujui', 'ditolak', 'menunggu_konfirmasi'])) {
            try {
                $pdo->beginTransaction();

                // 1. Get sekolah_id
                $stmtCheck = $pdo->prepare("SELECT sekolah_id, kode_bayar FROM pembayaran WHERE id = ?");
                $stmtCheck->execute([$pembayaranId]);
                $bayar = $stmtCheck->fetch();

                if ($bayar) {
                    $sekolahId = (int)$bayar['sekolah_id'];

                    // 2. Update pembayaran record
                    $stmtUpBayar = $pdo->prepare("
                        UPDATE pembayaran SET
                            status = ?,
                            catatan_admin = ?,
                            diverifikasi_oleh = ?,
                            diverifikasi_pada = NOW()
                        WHERE id = ?
                    ");
                    $stmtUpBayar->execute([$statusBaru, $catatanAdmin, $adminId, $pembayaranId]);

                    // 3. Sync to sekolah.status_pembayaran
                    $statusSekolah = ($statusBaru === 'disetujui') ? 'disetujui' : (($statusBaru === 'ditolak') ? 'ditolak' : 'menunggu_konfirmasi');
                    $stmtUpSek = $pdo->prepare("UPDATE sekolah SET status_pembayaran = ? WHERE id = ?");
                    $stmtUpSek->execute([$statusSekolah, $sekolahId]);

                    $pdo->commit();

                    $statusTeks = ($statusBaru === 'disetujui') ? 'DISETUJUI (LUNAS)' : (($statusBaru === 'ditolak') ? 'DITOLAK' : 'MENJADI MENUNGGU');
                    set_flash('success', 'Status transaksi <strong>' . e($bayar['kode_bayar']) . '</strong> berhasil diubah menjadi: <span class="badge bg-' . ($statusBaru === 'disetujui' ? 'success' : 'danger') . '">' . $statusTeks . '</span>');
                } else {
                    $pdo->rollBack();
                    set_flash('danger', 'Data pembayaran tidak ditemukan.');
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Gagal memproses verifikasi: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . base_url('admin/pembayaran.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '')));
    exit;
}

// Fetch Filter and Search parameters
$filterStatus = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// Statistics
$totalMasuk = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran")->fetchColumn();
$totalMenunggu = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'menunggu_konfirmasi'")->fetchColumn();
$totalDisetujui = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'disetujui'")->fetchColumn();
$totalDitolak = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'ditolak'")->fetchColumn();
$totalDanaLunas = (float)$pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE status = 'disetujui'")->fetchColumn();

// Build Query
$sql = "
    SELECT p.*, 
           s.nama_sekolah, s.npsn, s.gudep_putra, s.gudep_putri, s.nama_pembina, s.no_wa_pembina,
           u.username as akun_sekolah,
           adm.nama_lengkap as nama_verifikator
    FROM pembayaran p
    JOIN sekolah s ON p.sekolah_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN users adm ON p.diverifikasi_oleh = adm.id
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'all' && in_array($filterStatus, ['menunggu_konfirmasi', 'disetujui', 'ditolak'])) {
    $sql .= " AND p.status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.kode_bayar LIKE ? OR s.nama_sekolah LIKE ? OR p.bank_pengirim LIKE ? OR p.atas_nama_pengirim LIKE ?)";
    $qParam = '%' . $searchQuery . '%';
    $params[] = $qParam;
    $params[] = $qParam;
    $params[] = $qParam;
    $params[] = $qParam;
}

$sql .= " ORDER BY (p.status = 'menunggu_konfirmasi') DESC, p.id DESC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$pembayaranList = $stmtList->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Admin Racana</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/custom.css') ?>">
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <!-- Top Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Verifikasi Pembayaran</h4>
                <small class="text-muted">Kelola dan sahkan bukti transfer pendaftaran kontingen</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('admin/pengaturan.php') ?>" class="btn btn-outline-scout-navy btn-sm px-3">
                <i class="fa-solid fa-gear me-1"></i> Rekening
            </a>
            <a href="<?= base_url('admin/cetak-rekap.php') ?>" class="btn btn-scout-gold btn-sm px-3">
                <i class="fa-solid fa-print me-1"></i> Cetap Rekap
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <!-- STAT SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-widget green h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Dana Terverifikasi</span>
                        <h4 class="fw-bold mb-0 text-success font-monospace"><?= format_rupiah($totalDanaLunas) ?></h4>
                    </div>
                    <div class="feature-icon-wrapper icon-green mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="small text-muted"><?= $totalDisetujui ?> kontingen telah lunas</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget blue h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Menunggu Verifikasi</span>
                        <h3 class="fw-bold mb-0 text-primary"><?= $totalMenunggu ?> <span class="fs-6 text-muted fw-normal">berkas</span></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-blue mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="small <?= $totalMenunggu > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                    <?= $totalMenunggu > 0 ? 'Perlu tindakan verifikasi' : 'Semua transaksi sudah diproses' ?>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget gold h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Total Konfirmasi Masuk</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= $totalMasuk ?> <span class="fs-6 text-muted fw-normal">transaksi</span></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-gold mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div class="small text-muted"><?= $totalDisetujui ?> disetujui &bull; <?= $totalDitolak ?> ditolak</div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-widget purple h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-muted small fw-semibold d-block">Pembayaran Ditolak</span>
                        <h3 class="fw-bold mb-0 text-danger"><?= $totalDitolak ?> <span class="fs-6 text-muted fw-normal">transaksi</span></h3>
                    </div>
                    <div class="feature-icon-wrapper icon-purple mb-0" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                </div>
                <div class="small text-muted">Struk tidak valid / diunggah ulang</div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE CARD -->
    <div class="custom-card p-4">
        <!-- Filter Tabs & Search Bar -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('admin/pembayaran.php') ?>" class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-success' : 'btn-outline-secondary' ?>">
                    Semua (<?= $totalMasuk ?>)
                </a>
                <a href="<?= base_url('admin/pembayaran.php?status=menunggu_konfirmasi') ?>" class="btn btn-sm <?= $filterStatus === 'menunggu_konfirmasi' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fa-solid fa-clock me-1"></i> Menunggu Verifikasi
                    <?php if ($totalMenunggu > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $totalMenunggu ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= base_url('admin/pembayaran.php?status=disetujui') ?>" class="btn btn-sm <?= $filterStatus === 'disetujui' ? 'btn-success' : 'btn-outline-success' ?>">
                    <i class="fa-solid fa-check-circle me-1"></i> Disetujui (<?= $totalDisetujui ?>)
                </a>
                <a href="<?= base_url('admin/pembayaran.php?status=ditolak') ?>" class="btn btn-sm <?= $filterStatus === 'ditolak' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    <i class="fa-solid fa-ban me-1"></i> Ditolak (<?= $totalDitolak ?>)
                </a>
            </div>

            <form method="GET" action="" class="d-flex gap-2" style="max-width: 320px;">
                <?php if ($filterStatus !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
                <?php endif; ?>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari kode, sekolah, nama..." value="<?= e($searchQuery) ?>">
                <button type="submit" class="btn btn-sm btn-dark px-3">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>

        <?php if (empty($pembayaranList)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-receipt fs-1 mb-2"></i>
                <h6 class="fw-bold">Tidak Ada Data Pembayaran Ditemukan</h6>
                <p class="small mb-0">Belum ada konfirmasi pembayaran dari kontingen sekolah sesuai kriteria pencarian ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th>Kode Bayar</th>
                            <th>Kontingen Sekolah</th>
                            <th>Metode &amp; Bank</th>
                            <th>Pengirim</th>
                            <th>Nominal</th>
                            <th>Tgl Bayar</th>
                            <th>Status</th>
                            <th>Bukti</th>
                            <th class="text-center" style="width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($pembayaranList as $p): 
                            $buktiUrl = base_url('uploads/pembayaran/' . $p['bukti_transfer']);
                            $isPdf = strtolower(pathinfo($p['bukti_transfer'], PATHINFO_EXTENSION)) === 'pdf';
                        ?>
                            <tr class="<?= $p['status'] === 'menunggu_konfirmasi' ? 'table-warning bg-opacity-25' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td>
                                    <span class="font-monospace fw-bold text-dark d-block"><?= e($p['kode_bayar']) ?></span>
                                    <small class="text-muted"><?= date('H:i, d/m/Y', strtotime($p['created_at'])) ?></small>
                                </td>
                                <td>
                                    <a href="<?= base_url('admin/kontingen-detail.php?id=' . $p['sekolah_id']) ?>" class="fw-bold text-success text-decoration-none d-block">
                                        <?= e($p['nama_sekolah']) ?>
                                    </a>
                                    <small class="text-muted">
                                        Gudep: <?= e($p['gudep_putra']) ?>/<?= e($p['gudep_putri']) ?> &bull; 
                                        Pembina: <?= e($p['nama_pembina']) ?> (<?= e($p['no_wa_pembina'] ?: '-') ?>)
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border d-inline-block mb-1">
                                        <?= strtoupper(str_replace('_', ' ', $p['metode_bayar'])) ?>
                                    </span>
                                    <div class="fw-semibold text-dark"><?= e($p['bank_pengirim']) ?></div>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($p['atas_nama_pengirim']) ?></strong>
                                    <?php if (!empty($p['catatan_sekolah'])): ?>
                                        <small class="text-muted fst-italic text-truncate d-block" style="max-width: 150px;" title="<?= e($p['catatan_sekolah']) ?>">
                                            "<?= e($p['catatan_sekolah']) ?>"
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-success font-monospace"><?= format_rupiah($p['jumlah_bayar']) ?></span>
                                </td>
                                <td><?= format_tanggal_indonesia($p['tanggal_bayar']) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'disetujui'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> DISETUJUI</span>
                                    <?php elseif ($p['status'] === 'ditolak'): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> DITOLAK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> MENUNGGU</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBukti_<?= $p['id'] ?>" title="Lihat Bukti Transfer">
                                        <i class="fa-solid <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-image text-primary' ?>"></i> Struk
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm <?= $p['status'] === 'menunggu_konfirmasi' ? 'btn-scout-gold fw-bold' : 'btn-outline-dark' ?>" data-bs-toggle="modal" data-bs-target="#modalVerifikasi_<?= $p['id'] ?>">
                                        <i class="fa-solid fa-stamp me-1"></i> Verifikasi
                                    </button>
                                </td>
                            </tr>

                            <!-- MODAL: VIEW BUKTI TRANSFER -->
                            <div class="modal fade" id="modalBukti_<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fa-solid fa-receipt text-success me-2"></i> Struk: <?= e($p['kode_bayar']) ?> - <?= e($p['nama_sekolah']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center p-3">
                                            <?php if ($isPdf): ?>
                                                <iframe src="<?= $buktiUrl ?>" style="width: 100%; height: 500px; border: none;"></iframe>
                                            <?php else: ?>
                                                <img src="<?= $buktiUrl ?>" alt="Bukti Transfer" class="img-fluid rounded border" style="max-height: 520px;">
                                            <?php endif; ?>

                                            <div class="mt-3 p-3 bg-light rounded-3 text-start small border">
                                                <div class="row g-2">
                                                    <div class="col-sm-4"><strong>Bank / Pengirim:</strong> <?= e($p['bank_pengirim']) ?></div>
                                                    <div class="col-sm-4"><strong>Atas Nama:</strong> <?= e($p['atas_nama_pengirim']) ?></div>
                                                    <div class="col-sm-4"><strong>Nominal:</strong> <span class="text-success font-monospace fw-bold"><?= format_rupiah($p['jumlah_bayar']) ?></span></div>
                                                    <div class="col-sm-4"><strong>Tgl Transfer:</strong> <?= format_tanggal_indonesia($p['tanggal_bayar']) ?></div>
                                                    <div class="col-sm-8"><strong>Catatan Sekolah:</strong> <?= e($p['catatan_sekolah'] ?: '-') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?= $buktiUrl ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fa-solid fa-up-right-from-square me-1"></i> Buka File Asli
                                            </a>
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL: VERIFIKASI PEMBAYARAN -->
                            <div class="modal fade" id="modalVerifikasi_<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="pembayaran_id" value="<?= $p['id'] ?>">

                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fa-solid fa-stamp text-success me-2"></i> Verifikasi Pembayaran
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="p-3 bg-light rounded-3 mb-3 border small">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Kontingen Sekolah:</span>
                                                        <strong class="text-dark"><?= e($p['nama_sekolah']) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Kode Pembayaran:</span>
                                                        <span class="font-monospace fw-bold"><?= e($p['kode_bayar']) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Jumlah Transfer:</span>
                                                        <strong class="text-success font-monospace"><?= format_rupiah($p['jumlah_bayar']) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Bank &amp; Pengirim:</span>
                                                        <span><?= e($p['bank_pengirim']) ?> (<?= e($p['atas_nama_pengirim']) ?>)</span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Keputusan Status Pembayaran <span class="text-danger">*</span></label>
                                                    <select name="status" class="form-select fw-bold" required>
                                                        <option value="disetujui" <?= $p['status'] === 'disetujui' ? 'selected' : '' ?> class="text-success">
                                                            &#10004; DISETUJUI / LUNAS (Sah &amp; Diterima)
                                                        </option>
                                                        <option value="ditolak" <?= $p['status'] === 'ditolak' ? 'selected' : '' ?> class="text-danger">
                                                            &#10008; DITOLAK (Struk Tidak Valid / Belum Masuk)
                                                        </option>
                                                        <option value="menunggu_konfirmasi" <?= $p['status'] === 'menunggu_konfirmasi' ? 'selected' : '' ?> class="text-warning">
                                                            &#9203; MENUNGGU KONFIRMASI (Pending)
                                                        </option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Catatan Panitia untuk Sekolah (Opsional)</label>
                                                    <textarea name="catatan_admin" class="form-control" rows="3" placeholder="Contoh jika ditolak: Nominal tidak sesuai tagihan Rp 500.000 atau struk blur."><?= e($p['catatan_admin'] ?? '') ?></textarea>
                                                    <div class="form-text small">Catatan ini akan langsung tampil di dashboard sekolah kontingen bersangkutan.</div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-scout-primary btn-sm fw-bold">
                                                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Status Verifikasi
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
