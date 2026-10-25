<?php
/**
 * Edit Data Peserta Kontingen
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Edit Data Peserta';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();
if (!$sekolah) {
    die("Data sekolah tidak ditemukan.");
}

$sekolahId = (int)$sekolah['id'];
$pesertaId = (int)($_GET['id'] ?? 0);

// Check if registration is locked
if (($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci') {
    set_flash('warning', 'Data pendaftaran kontingen Anda telah <strong>DIFINALISASI &amp; DIKUNCI</strong>. Anda tidak dapat mengedit data peserta kecuali Admin Panitia membuka kunci akses pengeditan.');
    header('Location: ' . base_url('dashboard/peserta.php'));
    exit;
}

if (!$pesertaId) {
    set_flash('danger', 'ID Peserta tidak valid.');
    header('Location: ' . base_url('dashboard/peserta.php'));
    exit;
}

// Fetch Peserta & verify ownership
$stmtP = $pdo->prepare("SELECT * FROM peserta WHERE id = ? AND sekolah_id = ? LIMIT 1");
$stmtP->execute([$pesertaId, $sekolahId]);
$peserta = $stmtP->fetch();

if (!$peserta) {
    set_flash('danger', 'Data peserta tidak ditemukan atau Anda tidak memiliki hak akses.');
    header('Location: ' . base_url('dashboard/peserta.php'));
    exit;
}

// Fetch Selected Lomba IDs
$stmtPL = $pdo->prepare("SELECT lomba_id FROM peserta_lomba WHERE peserta_id = ?");
$stmtPL->execute([$pesertaId]);
$selectedLombaIds = $stmtPL->fetchAll(PDO::FETCH_COLUMN);

// Fetch All Categories
$stmtKategori = $pdo->prepare("
    SELECT k.*, 
           COUNT(p.id) as current_count
    FROM kategori_peserta k
    LEFT JOIN peserta p ON p.kategori_id = k.id AND p.sekolah_id = ? AND p.status IN ('aktif', 'draft') AND p.id != ?
    GROUP BY k.id
    ORDER BY k.urutan ASC
");
$stmtKategori->execute([$sekolahId, $pesertaId]);
$daftarKategori = $stmtKategori->fetchAll();

// Fetch Active Mata Lomba
$stmtLomba = $pdo->query("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' ORDER BY id ASC");
$daftarLomba = $stmtLomba->fetchAll();

// Fetch Lomba enrollment counts for other participants from the same school
$stmtLombaCounts = $pdo->prepare("
    SELECT pl.lomba_id, COUNT(p.id) as cnt
    FROM peserta_lomba pl
    JOIN peserta p ON pl.peserta_id = p.id
    WHERE p.sekolah_id = ? AND p.id != ? AND p.status IN ('aktif', 'draft')
    GROUP BY pl.lomba_id
");
$stmtLombaCounts->execute([$sekolahId, $pesertaId]);
$otherParticipantsLombaCounts = $stmtLombaCounts->fetchAll(PDO::FETCH_KEY_PAIR); // [lomba_id => count]

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi token CSRF gagal. Silakan muat ulang halaman.';
    }

    $kategori_id      = (int)($_POST['kategori_id'] ?? 0);
    $nama_lengkap     = trim($_POST['nama_lengkap'] ?? '');
    $nisn_nta         = is_field_enabled('nisn_nta') ? trim($_POST['nisn_nta'] ?? '') : (trim($_POST['nisn_nta'] ?? '') ?: ($peserta['nisn_nta'] ?? '-'));
    $kelas            = trim($_POST['kelas'] ?? '');
    $tempat_lahir     = is_field_enabled('tempat_lahir') ? trim($_POST['tempat_lahir'] ?? '') : (trim($_POST['tempat_lahir'] ?? '') ?: ($peserta['tempat_lahir'] ?? 'Sumbawa'));
    $tanggal_lahir    = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin    = $_POST['jenis_kelamin'] ?? 'L';
    $no_hp            = is_field_enabled('no_hp') ? trim($_POST['no_hp'] ?? '') : (trim($_POST['no_hp'] ?? '') ?: ($peserta['no_hp'] ?? '-'));
    $ukuran_kaos      = is_field_enabled('ukuran_kaos') && in_array($_POST['ukuran_kaos'] ?? '', ['S', 'M', 'L', 'XL', 'XXL']) 
                          ? $_POST['ukuran_kaos'] 
                          : (in_array($_POST['ukuran_kaos'] ?? '', ['S', 'M', 'L', 'XL', 'XXL']) ? $_POST['ukuran_kaos'] : ($peserta['ukuran_kaos'] ?: get_default_ukuran_kaos()));
    $riwayat_penyakit = is_field_enabled('riwayat_penyakit') ? trim($_POST['riwayat_penyakit'] ?? '') : (trim($_POST['riwayat_penyakit'] ?? '') ?: ($peserta['riwayat_penyakit'] ?? '-'));
    $status           = in_array($_POST['status'] ?? '', ['aktif', 'draft']) ? $_POST['status'] : 'aktif';
    $lomba_ids        = isset($_POST['lomba_ids']) && is_array($_POST['lomba_ids']) ? $_POST['lomba_ids'] : [];

    // Validations
    if (empty($kategori_id)) {
        $errors[] = 'Kategori Peserta wajib dipilih.';
    }
    if (empty($nama_lengkap)) {
        $errors[] = 'Nama Lengkap Peserta wajib diisi.';
    }
    if (empty($kelas)) {
        $errors[] = 'Kelas / Tingkatan wajib diisi.';
    }
    if (empty($tanggal_lahir)) {
        $errors[] = 'Tanggal Lahir wajib diisi.';
    }

    $selectedCategory = null;
    foreach ($daftarKategori as $kategori) {
        if ((int)$kategori['id'] === $kategori_id) {
            $selectedCategory = $kategori;
            break;
        }
    }
    if (!$selectedCategory) {
        $errors[] = 'Kategori peserta tidak valid.';
    } elseif ((int)$selectedCategory['current_count'] + 1 > (int)$selectedCategory['kuota_max']) {
        $errors[] = 'Kuota kategori <strong>' . e($selectedCategory['nama_kategori']) . '</strong> sudah penuh (maksimal ' . (int)$selectedCategory['kuota_max'] . ' orang per pangkalan).';
    }

    // Quota Validation per Mata Lomba for this school
    foreach ($lomba_ids as $lId) {
        $lId = (int)$lId;
        $otherCount = (int)($otherParticipantsLombaCounts[$lId] ?? 0);
        $stmtLInfo = $pdo->prepare("SELECT nama_lomba, max_peserta_per_sekolah FROM mata_lomba WHERE id = ?");
        $stmtLInfo->execute([$lId]);
        $lInfo = $stmtLInfo->fetch();
        if ($lInfo && ($otherCount + 1) > $lInfo['max_peserta_per_sekolah']) {
            $errors[] = "Cabang lomba <strong>" . e($lInfo['nama_lomba']) . "</strong> sudah mencapai batas maksimal kuota kontingen! (Maksimal <strong>{$lInfo['max_peserta_per_sekolah']} peserta</strong> per pangkalan sekolah).";
        }
    }

    // Photo Replacement Handler
    $uploadedPhotoName = $peserta['foto'];
    if (isset($_FILES['foto_peserta']) && $_FILES['foto_peserta']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = upload_foto_peserta($_FILES['foto_peserta'], 'idcard_' . $sekolahId . '_');
        if ($uploadResult['status']) {
            // Unlink old photo if exists
            if (!empty($peserta['foto'])) {
                $oldPath = dirname(__DIR__) . '/uploads/peserta/' . $peserta['foto'];
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $uploadedPhotoName = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("
                UPDATE peserta SET
                    kategori_id = ?,
                    nama_lengkap = ?,
                    nisn_nta = ?,
                    kelas = ?,
                    tempat_lahir = ?,
                    tanggal_lahir = ?,
                    jenis_kelamin = ?,
                    no_hp = ?,
                    riwayat_penyakit = ?,
                    ukuran_kaos = ?,
                    foto = ?,
                    status = ?
                WHERE id = ? AND sekolah_id = ?
            ");
            $stmtUpdate->execute([
                $kategori_id,
                $nama_lengkap,
                $nisn_nta,
                $kelas,
                $tempat_lahir,
                $tanggal_lahir,
                $jenis_kelamin,
                $no_hp,
                $riwayat_penyakit,
                $ukuran_kaos,
                $uploadedPhotoName,
                $status,
                $pesertaId,
                $sekolahId
            ]);

            // Sync Pivot Table peserta_lomba
            $pdo->prepare("DELETE FROM peserta_lomba WHERE peserta_id = ?")->execute([$pesertaId]);
            if (!empty($lomba_ids)) {
                $stmtInsertLomba = $pdo->prepare("INSERT INTO peserta_lomba (peserta_id, lomba_id) VALUES (?, ?)");
                foreach ($lomba_ids as $lId) {
                    $stmtInsertLomba->execute([$pesertaId, (int)$lId]);
                }
            }

            $pdo->commit();

            set_flash('success', 'Data peserta <strong>' . e($nama_lengkap) . '</strong> berhasil diperbarui!');
            header('Location: ' . base_url('dashboard/peserta.php'));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
} else {
    $kategori_id      = $peserta['kategori_id'];
    $nama_lengkap     = $peserta['nama_lengkap'];
    $nisn_nta         = $peserta['nisn_nta'];
    $kelas            = $peserta['kelas'];
    $tempat_lahir     = $peserta['tempat_lahir'];
    $tanggal_lahir    = $peserta['tanggal_lahir'];
    $jenis_kelamin    = $peserta['jenis_kelamin'];
    $no_hp            = $peserta['no_hp'];
    $ukuran_kaos      = $peserta['ukuran_kaos'];
    $riwayat_penyakit = $peserta['riwayat_penyakit'];
    $status           = $peserta['status'];
    $lomba_ids        = $selectedLombaIds;
}

$currentFotoUrl = !empty($peserta['foto']) ? base_url('uploads/peserta/' . $peserta['foto']) : null;
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
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Edit Data Peserta</h4>
                <small class="text-muted">Perbarui biodata, foto ID card, dan pilihan cabang mata lomba</small>
            </div>
        </div>
        <div>
            <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
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
            <div class="col-lg-8">
                <!-- 1. DATA SEKOLAH (AUTOFILL) -->
                <div class="custom-card p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-school text-success me-2"></i> 1. Informasi Sekolah (Autofill Otomatis)
                        </h5>
                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-magic me-1"></i> Terisi Otomatis</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">Nama Sekolah Pangkalan</label>
                            <input type="text" class="form-control bg-light" value="<?= e($sekolah['nama_sekolah']) ?>" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">Nomor Gudep Pangkalan</label>
                            <input type="text" class="form-control bg-light" value="Putra: <?= e($sekolah['gudep_putra'] ?: '01.001') ?> / Putri: <?= e($sekolah['gudep_putri'] ?: '01.002') ?>" readonly disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Alamat Sekolah Pangkalan</label>
                            <textarea class="form-control bg-light" rows="2" readonly disabled><?= e($sekolah['alamat_sekolah']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 2. DATA BIODATA PESERTA -->
                <div class="custom-card p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                        <i class="fa-solid fa-user-check text-primary me-2"></i> 2. Biodata Peserta / Bindamping
                    </h5>

                    <div class="row g-3">
                        <!-- KATEGORI PESERTA -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Kategori Kontingen <span class="text-danger">*</span>
                            </label>
                            <select name="kategori_id" id="kategori_id" class="form-select" required>
                                <?php foreach ($daftarKategori as $kat): 
                                    $sisa = $kat['kuota_max'] - $kat['current_count'];
                                    $disabled = ($sisa <= 0 && $kategori_id != $kat['id']) ? 'disabled' : '';
                                ?>
                                    <option value="<?= $kat['id'] ?>" 
                                            data-gender="<?= $kat['gender_default'] ?>"
                                            <?= $kategori_id == $kat['id'] ? 'selected' : '' ?>
                                            <?= $disabled ?>>
                                        <?= e($kat['nama_kategori']) ?> (Tersedia: <?= max(0, $sisa) ?> Kuota)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- KELAS / TINGKATAN -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">
                                Kelas / Tingkatan <span class="text-danger">*</span>
                            </label>
                            <select name="kelas" class="form-select" required>
                                <option value="Kelas X (Sepuluh)" <?= $kelas === 'Kelas X (Sepuluh)' ? 'selected' : '' ?>>Kelas X (Sepuluh)</option>
                                <option value="Kelas XI (Sebelas)" <?= $kelas === 'Kelas XI (Sebelas)' ? 'selected' : '' ?>>Kelas XI (Sebelas)</option>
                                <option value="Kelas XII (Dua Belas)" <?= $kelas === 'Kelas XII (Dua Belas)' ? 'selected' : '' ?>>Kelas XII (Dua Belas)</option>
                                <option value="Pembina Pendamping" <?= $kelas === 'Pembina Pendamping' ? 'selected' : '' ?>>Pembina Pendamping (Bindamping)</option>
                            </select>
                        </div>

                        <!-- NAMA LENGKAP -->
                        <div class="<?= is_field_enabled('nisn_nta') ? 'col-md-8' : 'col-12' ?>">
                            <label class="form-label fw-semibold small text-dark">
                                Nama Lengkap Peserta <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= e($nama_lengkap) ?>" required>
                        </div>

                        <!-- NISN / NTA -->
                        <?php if (is_field_enabled('nisn_nta')): ?>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small text-dark">NISN / NTA Pramuka</label>
                                <input type="text" name="nisn_nta" class="form-control" value="<?= e($nisn_nta) ?>">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="nisn_nta" value="<?= e($nisn_nta) ?>">
                        <?php endif; ?>

                        <!-- TEMPAT & TANGGAL LAHIR (HITUNG UMUR OTOMATIS) -->
                        <?php if (is_field_enabled('tempat_lahir')): ?>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold small text-dark">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" value="<?= e($tempat_lahir) ?>">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="tempat_lahir" value="<?= e($tempat_lahir) ?>">
                        <?php endif; ?>

                        <div class="<?= is_field_enabled('tempat_lahir') ? 'col-md-4' : 'col-md-6' ?>">
                            <label class="form-label fw-semibold small text-dark">
                                Tanggal Lahir <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-control" value="<?= e($tanggal_lahir) ?>" required>
                        </div>
                        <div class="<?= is_field_enabled('tempat_lahir') ? 'col-md-3' : 'col-md-6' ?>">
                            <label class="form-label fw-semibold small text-dark">Kalkulasi Umur</label>
                            <div id="display_umur" class="pt-1">
                                <?php $u = hitung_umur($tanggal_lahir); ?>
                                <span class="badge bg-success py-2 px-3"><i class="fa-solid fa-cake-candles me-1"></i> <?= $u['teks'] ?></span>
                            </div>
                        </div>

                        <!-- JENIS KELAMIN (AUTOFILL) -->
                        <div class="<?= (!is_field_enabled('ukuran_kaos') && !is_field_enabled('no_hp')) ? 'col-12' : 'col-md-6' ?>">
                            <label class="form-label fw-semibold small text-dark d-block">
                                Jenis Kelamin <span class="text-danger">* (Autofill dari Kategori)</span>
                            </label>
                            <div class="d-flex gap-4 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="gender_l" value="L" <?= $jenis_kelamin === 'L' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="gender_l">
                                        <i class="fa-solid fa-mars text-primary me-1"></i> Laki-laki (Putra)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="gender_p" value="P" <?= $jenis_kelamin === 'P' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="gender_p">
                                        <i class="fa-solid fa-venus text-danger me-1"></i> Perempuan (Putri)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- UKURAN KAOS & NO HP -->
                        <?php if (is_field_enabled('ukuran_kaos')): ?>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small text-dark">Ukuran Kaos Kegiatan</label>
                                <select name="ukuran_kaos" class="form-select">
                                    <option value="S" <?= $ukuran_kaos === 'S' ? 'selected' : '' ?>>S</option>
                                    <option value="M" <?= $ukuran_kaos === 'M' ? 'selected' : '' ?>>M</option>
                                    <option value="L" <?= $ukuran_kaos === 'L' ? 'selected' : '' ?>>L</option>
                                    <option value="XL" <?= $ukuran_kaos === 'XL' ? 'selected' : '' ?>>XL</option>
                                    <option value="XXL" <?= $ukuran_kaos === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="ukuran_kaos" value="<?= e($ukuran_kaos) ?>">
                        <?php endif; ?>

                        <?php if (is_field_enabled('no_hp')): ?>
                            <div class="<?= is_field_enabled('ukuran_kaos') ? 'col-md-3' : 'col-md-6' ?>">
                                <label class="form-label fw-semibold small text-dark">No. WhatsApp</label>
                                <input type="text" name="no_hp" class="form-control" value="<?= e($no_hp) ?>">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="no_hp" value="<?= e($no_hp) ?>">
                        <?php endif; ?>

                        <!-- RIWAYAT PENYAKIT -->
                        <?php if (is_field_enabled('riwayat_penyakit')): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold small text-dark">Catatan Khusus / Riwayat Alergi &amp; Penyakit</label>
                                <input type="text" name="riwayat_penyakit" class="form-control" value="<?= e($riwayat_penyakit) ?>">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="riwayat_penyakit" value="<?= e($riwayat_penyakit) ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. MATA LOMBA YANG DIIKUTI (MULTI-SELECT) -->
                <div class="custom-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-trophy text-warning me-2"></i> 3. Mata Lomba yang Diikuti
                        </h5>
                        <span class="badge bg-primary px-3 py-2" id="selected_lomba_count">0 Mata Lomba Dipilih</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($daftarLomba as $l): 
                            $isChecked = in_array($l['id'], $lomba_ids) ? 'checked' : '';
                            $otherCount = (int)($otherParticipantsLombaCounts[$l['id']] ?? 0);
                            $maxPerSekolah = (int)$l['max_peserta_per_sekolah'];
                            $isFull = (!$isChecked && $otherCount >= $maxPerSekolah);
                        ?>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 <?= $isFull ? 'bg-light opacity-75 border-danger border-opacity-25' : 'bg-light' ?> h-100">
                                    <div class="form-check">
                                        <input class="form-check-input checkbox-lomba" 
                                               type="checkbox" 
                                               name="lomba_ids[]" 
                                               value="<?= $l['id'] ?>" 
                                               id="lomba_<?= $l['id'] ?>" 
                                               <?= $isChecked ?> 
                                               <?= $isFull ? 'disabled' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark d-block <?= $isFull ? 'text-muted' : '' ?> cursor-pointer" for="lomba_<?= $l['id'] ?>">
                                             <?= e($l['nama_lomba']) ?>
                                            <?php if ($isFull): ?>
                                                <span class="badge bg-danger ms-1">Penuh (<?= $otherCount ?>/<?= $maxPerSekolah ?>)</span>
                                            <?php elseif ($isChecked): ?>
                                                <span class="badge bg-success ms-1">Terpilih (<?= $otherCount + 1 ?>/<?= $maxPerSekolah ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border ms-1">Sisa <?= max(0, $maxPerSekolah - $otherCount) ?> Kuota</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1 ps-4" style="font-size: 0.76rem;">
                                        <?= e($l['deskripsi']) ?>
                                        <?php if ($isFull): ?>
                                            <div class="text-danger fw-semibold mt-1">
                                                <i class="fa-solid fa-lock me-1"></i> Tidak bisa memilih: Kuota kontingen pangkalan Anda untuk mata lomba ini sudah terisi semua (Maksimal <?= $maxPerSekolah ?> peserta).
                                            </div>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: PHOTO UPLOAD -->
            <div class="col-lg-4">
                <?php if (is_field_enabled('foto')): ?>
                    <div class="custom-card p-4 mb-4 text-center">
                        <h5 class="fw-bold mb-2 text-dark">
                            <i class="fa-solid fa-id-card-clip text-success me-2"></i> Foto Peserta (ID Card)
                        </h5>
                        <p class="text-muted small mb-3">Klik untuk mengganti foto peserta.</p>

                        <div class="d-flex justify-content-center mb-3">
                            <div class="photo-upload-box" id="preview_foto" onclick="document.getElementById('foto_peserta').click()">
                                <?php if ($currentFotoUrl): ?>
                                    <img src="<?= $currentFotoUrl ?>" alt="Foto Peserta">
                                <?php else: ?>
                                    <i class="fa-solid fa-cloud-arrow-up fs-2 text-muted mb-2"></i>
                                    <span class="small text-muted fw-semibold">Pilih Foto</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <input type="file" name="foto_peserta" id="foto_peserta" class="d-none" accept="image/jpeg,image/png,image/jpg,image/webp">

                        <button type="button" class="btn btn-outline-success btn-sm mb-3 w-100" onclick="document.getElementById('foto_peserta').click()">
                            <i class="fa-solid fa-camera me-1"></i> Ganti File Foto
                        </button>
                    </div>
                <?php endif; ?>

                <!-- STATUS PENDAFTARAN -->
                <div class="custom-card p-4 mb-4 bg-white">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-flag text-warning me-1"></i> Status Keanggotaan</h6>
                    <select name="status" class="form-select form-select-sm mb-3">
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Resmi / Aktif (Final)</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draf Sementara</option>
                    </select>
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Pilih "Resmi / Aktif" untuk mengikutsertakan peserta ke rekap dan pencetakan ID Card.</small>
                </div>

                <div class="custom-card p-4 text-center bg-light">
                    <h6 class="fw-bold text-dark mb-3">Simpan Perubahan</h6>
                    <button type="submit" class="btn btn-scout-gold w-100 py-2 fs-6 fw-bold mb-2 shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Perubahan Peserta
                    </button>
                    <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary w-100 btn-sm">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
