<?php
/**
 * Pendaftaran Multi-Batch Peserta & Bindamping Kontingen
 * Tab Barisan Putra, Barisan Putri, Bindamping Putra, Bindamping Putri
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Pendaftaran Peserta Multi-Batch';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/auth.php';

require_sekolah();

$pdo = getDB();
$sekolah = get_current_sekolah();
if (!$sekolah) {
    die("Data sekolah tidak ditemukan.");
}

$sekolahId = (int)$sekolah['id'];
$isLocked = ($sekolah['status_kunci'] ?? 'terbuka') === 'terkunci';

// Fetch configured participant categories and quotas.
$stmtKategori = $pdo->query("SELECT * FROM kategori_peserta ORDER BY urutan ASC");
$daftarKategori = $stmtKategori->fetchAll();
$quotaByCategoryId = [];
foreach ($daftarKategori as $kategori) {
    $quotaByCategoryId[(int)$kategori['id']] = (int)$kategori['kuota_max'];
}
$totalQuota = array_sum($quotaByCategoryId);

// Fetch Active Mata Lomba
$stmtLomba = $pdo->query("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' ORDER BY id ASC");
$daftarLomba = $stmtLomba->fetchAll();

$errors = [];

// Handle POST Submission (Draft or Final)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLocked) {
        $errors[] = 'Akses pendaftaran telah dikunci setelah finalisasi resmi. Perubahan data tidak dapat disimpan kecuali Admin Panitia membuka akses pengeditan.';
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Validasi CSRF token tidak sah. Silakan muat ulang halaman.';
    }

    $saveAction = ($_POST['save_action'] ?? '') === 'draft' ? 'draft' : 'aktif';
    $pesertaPost = $_POST['peserta'] ?? [];

    // Enforce each category quota on the server, not only through the rendered slots.
    foreach ($daftarKategori as $kategori) {
        $katId = (int)$kategori['id'];
        $quota = (int)$kategori['kuota_max'];
        $filledSlots = 0;
        $submittedSlots = $pesertaPost[$katId] ?? [];
        foreach (is_array($submittedSlots) ? $submittedSlots : [] as $slotData) {
            if (!is_array($slotData)) {
                continue;
            }
            if (!empty(trim($slotData['nama_lengkap'] ?? '')) && empty($slotData['is_delete'])) {
                $filledSlots++;
            }
        }
        if ($filledSlots > $quota) {
            $errors[] = "Kategori <strong>" . e($kategori['nama_kategori']) . "</strong> melebihi kuota maksimal <strong>{$quota} orang</strong>.";
        }
    }

    // Pre-validate Quotas per Mata Lomba for this school
    $lombaChosenCounts = [];
    foreach ($pesertaPost as $katIdCheck => $slotsCheck) {
        foreach ($slotsCheck as $slotIdxCheck => $dataCheck) {
            $namaLengkapCheck = trim($dataCheck['nama_lengkap'] ?? '');
            $isDeleteCheck = !empty($dataCheck['is_delete']);
            if (!empty($namaLengkapCheck) && !$isDeleteCheck && !empty($dataCheck['lomba_ids']) && is_array($dataCheck['lomba_ids'])) {
                foreach ($dataCheck['lomba_ids'] as $lIdCheck) {
                    $lIdCheck = (int)$lIdCheck;
                    $lombaChosenCounts[$lIdCheck] = ($lombaChosenCounts[$lIdCheck] ?? 0) + 1;
                }
            }
        }
    }

    foreach ($daftarLomba as $lCheck) {
        $lId = (int)$lCheck['id'];
        $chosen = (int)($lombaChosenCounts[$lId] ?? 0);
        $maxAllowed = (int)$lCheck['max_peserta_per_sekolah'];
        if ($chosen > $maxAllowed) {
            $errors[] = "Cabang lomba <strong>" . e($lCheck['nama_lomba']) . "</strong> melebihi batas kuota kontingen! Maksimal hanya boleh diikuti oleh <strong>{$maxAllowed} peserta</strong> per pangkalan sekolah, namun formulir Anda memilih <strong>{$chosen} peserta</strong>.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $totalSaved = 0;
            $totalDeleted = 0;

            foreach ($daftarKategori as $kat) {
                $katId = (int)$kat['id'];
                $katGender = $kat['gender_default'];
                $slots = $pesertaPost[$katId] ?? [];

                foreach ($slots as $slotIdx => $data) {
                    $pesertaId = (int)($data['id'] ?? 0);
                    $namaLengkap = trim($data['nama_lengkap'] ?? '');
                    $isDelete = !empty($data['is_delete']);

                    // 1. Delete requested
                    if ($isDelete && $pesertaId > 0) {
                        $stmtP = $pdo->prepare("SELECT foto FROM peserta WHERE id = ? AND sekolah_id = ?");
                        $stmtP->execute([$pesertaId, $sekolahId]);
                        $oldFoto = $stmtP->fetchColumn();
                        if ($oldFoto && file_exists(dirname(__DIR__) . '/uploads/peserta/' . $oldFoto)) {
                            @unlink(dirname(__DIR__) . '/uploads/peserta/' . $oldFoto);
                        }
                        $pdo->prepare("DELETE FROM peserta WHERE id = ? AND sekolah_id = ?")->execute([$pesertaId, $sekolahId]);
                        $totalDeleted++;
                        continue;
                    }

                    // 2. If name is completely empty
                    if (empty($namaLengkap)) {
                        if ($pesertaId > 0) {
                            // If previously existed but now blanked out, delete it
                            $stmtP = $pdo->prepare("SELECT foto FROM peserta WHERE id = ? AND sekolah_id = ?");
                            $stmtP->execute([$pesertaId, $sekolahId]);
                            $oldFoto = $stmtP->fetchColumn();
                            if ($oldFoto && file_exists(dirname(__DIR__) . '/uploads/peserta/' . $oldFoto)) {
                                @unlink(dirname(__DIR__) . '/uploads/peserta/' . $oldFoto);
                            }
                            $pdo->prepare("DELETE FROM peserta WHERE id = ? AND sekolah_id = ?")->execute([$pesertaId, $sekolahId]);
                            $totalDeleted++;
                        }
                        continue;
                    }

                    // 3. Process filled row
                    $nisnNta = is_field_enabled('nisn_nta') ? trim($data['nisn_nta'] ?? '') : (trim($data['nisn_nta'] ?? '') ?: '-');
                    $kelas = trim($data['kelas'] ?? '');
                    $tempatLahir = is_field_enabled('tempat_lahir') ? trim($data['tempat_lahir'] ?? 'Sumbawa') : (trim($data['tempat_lahir'] ?? '') ?: 'Sumbawa');
                    $tanggalLahir = trim($data['tanggal_lahir'] ?? '');
                    $noHp = is_field_enabled('no_hp') ? trim($data['no_hp'] ?? '') : (trim($data['no_hp'] ?? '') ?: '-');
                    $ukuranKaos = is_field_enabled('ukuran_kaos') && in_array($data['ukuran_kaos'] ?? '', ['S', 'M', 'L', 'XL', 'XXL']) 
                                    ? $data['ukuran_kaos'] 
                                    : (in_array($data['ukuran_kaos'] ?? '', ['S', 'M', 'L', 'XL', 'XXL']) ? $data['ukuran_kaos'] : get_default_ukuran_kaos());
                    $riwayatPenyakit = is_field_enabled('riwayat_penyakit') ? trim($data['riwayat_penyakit'] ?? '') : (trim($data['riwayat_penyakit'] ?? '') ?: '-');
                    $lombaIds = isset($data['lomba_ids']) && is_array($data['lomba_ids']) ? $data['lomba_ids'] : [];

                    // Validation if Final Mode
                    if ($saveAction === 'aktif') {
                        if (empty($tanggalLahir)) {
                            $errors[] = "Slot {$kat['nama_kategori']} (#" . ($slotIdx + 1) . " - {$namaLengkap}): Tanggal Lahir wajib diisi untuk status RESMI.";
                        }
                        if (empty($kelas)) {
                            $errors[] = "Slot {$kat['nama_kategori']} (#" . ($slotIdx + 1) . " - {$namaLengkap}): Kelas / Jabatan wajib diisi.";
                        }
                    } else {
                        // Draft mode defaults
                        if (empty($tanggalLahir)) {
                            $tanggalLahir = '2008-01-01'; // Default valid date for draft
                        }
                        if (empty($kelas)) {
                            $kelas = ($katId >= 3) ? 'Pembina Pendamping' : 'Kelas X (Sepuluh)';
                        }
                    }

                    // File upload for this slot
                    $fileKey = "foto_{$katId}_{$slotIdx}";
                    $uploadedPhoto = !empty($data['current_foto']) ? $data['current_foto'] : null;
                    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                        $uploadRes = upload_foto_peserta($_FILES[$fileKey], 'idcard_' . $sekolahId . '_');
                        if ($uploadRes['status']) {
                            if (!empty($uploadedPhoto) && file_exists(dirname(__DIR__) . '/uploads/peserta/' . $uploadedPhoto)) {
                                @unlink(dirname(__DIR__) . '/uploads/peserta/' . $uploadedPhoto);
                            }
                            $uploadedPhoto = $uploadRes['filename'];
                        } else {
                            $errors[] = "Foto {$namaLengkap}: " . $uploadRes['message'];
                        }
                    }

                    if (empty($errors)) {
                        if ($pesertaId > 0) {
                            // Update
                            $stmtUp = $pdo->prepare("
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
                            $stmtUp->execute([
                                $katId, $namaLengkap, $nisnNta, $kelas,
                                $tempatLahir, $tanggalLahir, $katGender, $noHp,
                                $riwayatPenyakit, $ukuranKaos, $uploadedPhoto,
                                $saveAction, $pesertaId, $sekolahId
                            ]);
                            $savedId = $pesertaId;
                        } else {
                            // Insert
                            $qrToken = bin2hex(random_bytes(16));
                            $stmtIn = $pdo->prepare("
                                INSERT INTO peserta (
                                    sekolah_id, kategori_id, nama_lengkap, nisn_nta, kelas,
                                    tempat_lahir, tanggal_lahir, jenis_kelamin, no_hp,
                                    riwayat_penyakit, ukuran_kaos, foto, qr_token, status
                                ) VALUES (
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?
                                )
                            ");
                            $stmtIn->execute([
                                $sekolahId, $katId, $namaLengkap, $nisnNta, $kelas,
                                $tempatLahir, $tanggalLahir, $katGender, $noHp,
                                $riwayatPenyakit, $ukuranKaos, $uploadedPhoto,
                                $qrToken, $saveAction
                            ]);
                            $savedId = (int)$pdo->lastInsertId();
                        }

                        // Sync Pivot Table peserta_lomba
                        $pdo->prepare("DELETE FROM peserta_lomba WHERE peserta_id = ?")->execute([$savedId]);
                        if (!empty($lombaIds)) {
                            $stmtPL = $pdo->prepare("INSERT INTO peserta_lomba (peserta_id, lomba_id) VALUES (?, ?)");
                            foreach ($lombaIds as $lId) {
                                $stmtPL->execute([$savedId, (int)$lId]);
                            }
                        }
                        $totalSaved++;
                    }
                }
            }

            if (empty($errors)) {
                if ($saveAction === 'aktif') {
                    // Lock registration on official finalization
                    $pdo->prepare("UPDATE sekolah SET status_kunci = 'terkunci', finalized_at = NOW() WHERE id = ?")->execute([$sekolahId]);
                    $pdo->commit();
                    set_flash('success', "Pendaftaran batch berhasil! <strong>{$totalSaved}</strong> data anggota kontingen berhasil disimpan dan <strong>DIFINALISASI (STATUS TERKUNCI)</strong>. Akses formulir telah dikunci untuk menjaga validitas data perlombaan. Silakan hubungi Panitia Admin jika terdapat perubahan data mendesak.");
                } else {
                    $pdo->commit();
                    set_flash('success', "Pendaftaran batch berhasil! <strong>{$totalSaved}</strong> data anggota kontingen berhasil disimpan sebagai <strong>DRAF SEMENTARA</strong>. Anda dapat kembali mengedit dan melengkapi data kapan saja sebelum difinalisasi.");
                }
                header('Location: ' . base_url('dashboard/peserta.php'));
                exit;
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal memproses pendaftaran batch: ' . $e->getMessage();
        }
    }
}

// Fetch Existing Participants for mapping into slots
$stmtExisting = $pdo->prepare("
    SELECT p.*, GROUP_CONCAT(pl.lomba_id) as raw_lomba_ids
    FROM peserta p
    LEFT JOIN peserta_lomba pl ON p.id = pl.peserta_id
    WHERE p.sekolah_id = ? AND p.status IN ('aktif', 'draft')
    GROUP BY p.id
    ORDER BY p.kategori_id ASC, p.id ASC
");
$stmtExisting->execute([$sekolahId]);
$existingRows = $stmtExisting->fetchAll();

// Group existing by kategori_id
$kategoriParticipants = [1 => [], 2 => [], 3 => [], 4 => []];
$totalActiveCount = 0;
$totalDraftCount = 0;

foreach ($existingRows as $row) {
    $kId = (int)$row['kategori_id'];
    $row['selected_lomba'] = !empty($row['raw_lomba_ids']) ? explode(',', $row['raw_lomba_ids']) : [];
    $kategoriParticipants[$kId][] = $row;
    if ($row['status'] === 'aktif') {
        $totalActiveCount++;
    } else {
        $totalDraftCount++;
    }
}

$totalFilledCount = $totalActiveCount + $totalDraftCount;
$countPutra = count($kategoriParticipants[1]);
$countPutri = count($kategoriParticipants[2]);
$countBindampingPa = count($kategoriParticipants[3]);
$countBindampingPi = count($kategoriParticipants[4]);
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
        .sticky-action-bar {
            position: sticky;
            top: 15px;
            z-index: 990;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(13, 92, 58, 0.2);
            border-radius: 16px;
            padding: 14px 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }
        .tab-badge {
            font-size: 0.72rem;
            padding: 4px 8px;
            border-radius: 50px;
        }
        .lomba-pill-checkbox {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.2s ease;
        }
        .lomba-pill-checkbox:hover:not(.lomba-full-disabled) {
            border-color: #0d5c3a;
            background-color: #f7fdf9;
        }
        .lomba-full-disabled {
            opacity: 0.6;
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            cursor: not-allowed !important;
        }
        .lomba-full-disabled label {
            cursor: not-allowed !important;
            color: #64748b !important;
        }
        .lomba-quota-badge {
            font-size: 0.68rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <!-- Header Title -->
    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-scout-navy d-lg-none" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="fw-bold mb-0 text-dark">Formulir Anggota Kontingen</h4>
                <small class="text-muted"><?= $quotaByCategoryId[1] ?? 0 ?> Putra &bull; <?= $quotaByCategoryId[2] ?? 0 ?> Putri &bull; <?= $quotaByCategoryId[3] ?? 0 ?> Pembina PA &bull; <?= $quotaByCategoryId[4] ?? 0 ?> Pembina PI</small>
            </div>
        </div>
        <div>
            <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/alerts.php'; ?>

    <?php if ($isLocked): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3" style="border-left: 5px solid #dc3545 !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-danger">Formulir Terkunci (Sudah Difinalisasi Resmi)</h5>
                    <p class="mb-0 small text-muted">
                        Pendaftaran kontingen sekolah Anda telah <strong>difinalisasi resmi</strong> pada <strong><?= format_tanggal_indonesia($sekolah['finalized_at'] ?? date('Y-m-d')) ?></strong>. Formulir dalam mode <strong>Hanya Baca (Read-Only)</strong>. Jika terdapat perubahan mendesak, silakan hubungi <strong>Panitia Admin <?= e(get_setting('nama_brand', 'Racana')) ?></strong> untuk membuka akses pengeditan.
                    </p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" target="_blank" class="btn btn-scout-gold btn-sm px-3 shadow-sm">
                    <i class="fa-solid fa-id-card me-1"></i> Cetak ID Card
                </a>
                <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" target="_blank" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Rekap A.01
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4 shadow-sm border-0">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i> Periksa Kesalahan Pengisian:</div>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- SCHOOL AUTOFILL INFO CARD -->
    <div class="custom-card p-3 mb-4 bg-white">
        <div class="row align-items-center g-3">
            <div class="col-lg-6">
                <div class="d-flex align-items-center gap-3">
                    <div class="feature-icon-wrapper icon-green mb-0" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-school fs-4"></i>
                    </div>
                    <div>
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold mb-1"><i class="fa-solid fa-magic me-1"></i> Pangkalan Terdaftar</span>
                        <h5 class="fw-bold mb-0 text-dark"><?= e($sekolah['nama_sekolah']) ?></h5>
                        <small class="text-muted">Gudep Pa: <?= e($sekolah['gudep_putra'] ?: '01.001') ?> / Pi: <?= e($sekolah['gudep_putri'] ?: '01.002') ?> &bull; <?= e($sekolah['alamat_sekolah']) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-wrap align-items-center justify-content-lg-end gap-2">
                    <div class="bg-light px-3 py-2 rounded-3 border text-center">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">TOTAL KUOTA</small>
                        <span class="fw-bold fs-6 text-dark" id="stat_total_filled"><?= $totalFilledCount ?> / <?= $totalQuota ?></span>
                    </div>
                    <div class="bg-light px-3 py-2 rounded-3 border text-center">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">STATUS RESMI</small>
                        <span class="badge bg-success" id="stat_total_active"><?= $totalActiveCount ?> Aktif</span>
                    </div>
                    <div class="bg-light px-3 py-2 rounded-3 border text-center">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">STATUS DRAF</small>
                        <span class="badge bg-warning text-dark" id="stat_total_draft"><?= $totalDraftCount ?> Draf</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN MULTI-BATCH FORM -->
    <form method="POST" action="" enctype="multipart/form-data" id="multiBatchForm">
        <?= csrf_field() ?>

        <!-- STICKY ACTION BAR -->
        <div class="sticky-action-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark"><i class="fa-solid fa-layer-group text-success me-1"></i> Aksi Form Batch:</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnToggleAllSlots">
                    <i class="fa-solid fa-up-down me-1"></i> Buka/Tutup Semua Slot
                </button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- SAVE DRAFT BUTTON -->
                <button type="submit" name="save_action" value="draft" class="btn btn-warning text-dark fw-bold px-3 shadow-sm" title="Simpan data ke dalam DRAF tanpa validasi ketat">
                    <i class="fa-solid fa-bookmark me-1"></i> Simpan Sebagai DRAF
                </button>
                <!-- SAVE FINAL BUTTON -->
                <button type="submit" name="save_action" value="final" class="btn btn-scout-gold px-4 fw-bold shadow-sm" title="Simpan dan finalisasi pendaftaran resmi">
                    <i class="fa-solid fa-circle-check me-1"></i> Simpan &amp; Finalisasi (RESMI)
                </button>
            </div>
        </div>

        <!-- 4 TABS NAVIGATION -->
        <ul class="nav nav-tabs batch-tab-nav mb-4" id="batchTabs" role="tablist">
            <!-- TAB 1: PUTRA -->
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-putra-btn" data-bs-toggle="tab" data-bs-target="#tab-putra" type="button" role="tab" aria-controls="tab-putra" aria-selected="true">
                    <i class="fa-solid fa-mars text-primary fs-5"></i>
                    <span>Barisan Putra</span>
                    <span class="badge bg-primary bg-opacity-20 text-primary tab-badge" id="badge_count_1" data-quota="<?= $quotaByCategoryId[1] ?? 11 ?>"><?= $countPutra ?>/<?= $quotaByCategoryId[1] ?? 11 ?></span>
                </button>
            </li>
            <!-- TAB 2: PUTRI -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-putri-btn" data-bs-toggle="tab" data-bs-target="#tab-putri" type="button" role="tab" aria-controls="tab-putri" aria-selected="false">
                    <i class="fa-solid fa-venus text-danger fs-5"></i>
                    <span>Barisan Putri</span>
                    <span class="badge bg-danger bg-opacity-20 text-danger tab-badge" id="badge_count_2" data-quota="<?= $quotaByCategoryId[2] ?? 11 ?>"><?= $countPutri ?>/<?= $quotaByCategoryId[2] ?? 11 ?></span>
                </button>
            </li>
            <!-- TAB 3: BINDAMPING PUTRA -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-bindamping-pa-btn" data-bs-toggle="tab" data-bs-target="#tab-bindamping-pa" type="button" role="tab" aria-controls="tab-bindamping-pa" aria-selected="false">
                    <i class="fa-solid fa-user-tie text-success fs-5"></i>
                    <span>Pembina PA</span>
                    <span class="badge bg-success bg-opacity-20 text-success tab-badge" id="badge_count_3" data-quota="<?= $quotaByCategoryId[3] ?? 1 ?>"><?= $countBindampingPa ?>/<?= $quotaByCategoryId[3] ?? 1 ?></span>
                </button>
            </li>
            <!-- TAB 4: BINDAMPING PUTRI -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-bindamping-pi-btn" data-bs-toggle="tab" data-bs-target="#tab-bindamping-pi" type="button" role="tab" aria-controls="tab-bindamping-pi" aria-selected="false">
                    <i class="fa-solid fa-user-shield text-info fs-5"></i>
                    <span>Pembina PI</span>
                    <span class="badge bg-info bg-opacity-20 text-info tab-badge" id="badge_count_4" data-quota="<?= $quotaByCategoryId[4] ?? 1 ?>"><?= $countBindampingPi ?>/<?= $quotaByCategoryId[4] ?? 1 ?></span>
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT CONTAINER -->
        <fieldset <?= $isLocked ? 'disabled' : '' ?> style="border:none;padding:0;margin:0;">
        <div class="tab-content" id="batchTabsContent">
            
            <?php 
            $tabConfigs = [
                1 => ['id' => 'tab-putra', 'title' => 'Barisan / Sangga Putra', 'icon' => 'fa-mars text-primary', 'gender' => 'L', 'quota' => $quotaByCategoryId[1] ?? 11, 'is_active' => true],
                2 => ['id' => 'tab-putri', 'title' => 'Barisan / Sangga Putri', 'icon' => 'fa-venus text-danger', 'gender' => 'P', 'quota' => $quotaByCategoryId[2] ?? 11, 'is_active' => false],
                3 => ['id' => 'tab-bindamping-pa', 'title' => 'Pembina Pendamping PA', 'icon' => 'fa-user-tie text-success', 'gender' => 'L', 'quota' => $quotaByCategoryId[3] ?? 1, 'is_active' => false],
                4 => ['id' => 'tab-bindamping-pi', 'title' => 'Pembina Pendamping PI', 'icon' => 'fa-user-shield text-info', 'gender' => 'P', 'quota' => $quotaByCategoryId[4] ?? 1, 'is_active' => false]
            ];

            foreach ($tabConfigs as $katId => $cfg): 
                $existingInKat = $kategoriParticipants[$katId] ?? [];
            ?>
                <div class="tab-pane fade <?= $cfg['is_active'] ? 'show active' : '' ?>" id="<?= $cfg['id'] ?>" role="tabpanel">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-white border rounded-3">
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">
                                <i class="fa-solid <?= $cfg['icon'] ?> me-2"></i> <?= $cfg['title'] ?>
                            </h5>
                            <small class="text-muted">Kuota Maksimal: <strong><?= $cfg['quota'] ?> Orang</strong> &bull; Jenis Kelamin: <strong><?= $cfg['gender'] === 'L' ? 'Laki-laki (Putra)' : 'Perempuan (Putri)' ?></strong> (Terkunci Otomatis)</small>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark border px-3 py-2">
                                <i class="fa-solid fa-users me-1 text-success"></i> Slot Tersedia: <?= $cfg['quota'] ?> Kuota
                            </span>
                        </div>
                    </div>

                    <!-- SLOTS GRID -->
                    <div class="row g-3">
                        <?php for ($idx = 0; $idx < $cfg['quota']; $idx++): 
                            $p = $existingInKat[$idx] ?? null;
                            $hasData = !empty($p);
                            $pId = $hasData ? (int)$p['id'] : 0;
                            $pNama = $hasData ? $p['nama_lengkap'] : '';
                            $pNTA = $hasData ? $p['nisn_nta'] : '';
                            $pKelas = $hasData ? $p['kelas'] : (($katId >= 3) ? 'Pembina Pendamping' : 'Kelas X (Sepuluh)');
                            $pTempat = $hasData ? $p['tempat_lahir'] : 'Sumbawa';
                            $pTgl = $hasData ? $p['tanggal_lahir'] : '';
                            $pHp = $hasData ? $p['no_hp'] : '';
                            $pKaos = $hasData ? ($p['ukuran_kaos'] ?: get_default_ukuran_kaos()) : get_default_ukuran_kaos();
                            $pRiwayat = $hasData ? $p['riwayat_penyakit'] : '';
                            $pFoto = $hasData ? $p['foto'] : '';
                            $pStatus = $hasData ? $p['status'] : 'empty';
                            $pSelectedLomba = $hasData ? $p['selected_lomba'] : [];

                            $cardClass = 'is-empty';
                            if ($pStatus === 'aktif') $cardClass = 'is-filled';
                            elseif ($pStatus === 'draft') $cardClass = 'is-draft';

                            $collapseId = "collapse_{$katId}_{$idx}";
                            $isOpen = ($idx === 0 || $hasData);
                        ?>
                            <div class="col-12">
                                <div class="slot-card <?= $cardClass ?>" id="slot_card_<?= $katId ?>_<?= $idx ?>">
                                    <!-- Hidden Inputs -->
                                    <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][id]" value="<?= $pId ?>">
                                    <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][kategori_id]" value="<?= $katId ?>">
                                    <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][jenis_kelamin]" value="<?= $cfg['gender'] ?>">
                                    <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][current_foto]" value="<?= e($pFoto) ?>">
                                    <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][is_delete]" id="is_delete_<?= $katId ?>_<?= $idx ?>" value="0">

                                    <!-- SLOT HEADER -->
                                    <div class="slot-header" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge bg-dark px-2 py-1">Slot #<?= ($idx + 1) ?></span>
                                            
                                            <!-- STATUS BADGE -->
                                            <span id="slot_badge_status_<?= $katId ?>_<?= $idx ?>">
                                                <?php if ($pStatus === 'aktif'): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> AKTIF (RESMI)</span>
                                                <?php elseif ($pStatus === 'draft'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-bookmark me-1"></i> DRAF</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border"><i class="fa-regular fa-circle me-1"></i> Kosong</span>
                                                <?php endif; ?>
                                            </span>

                                            <span class="fw-bold text-dark slot-title-preview" id="slot_title_<?= $katId ?>_<?= $idx ?>">
                                                <?= $hasData ? e($pNama) : '<span class="text-muted fw-normal">Klik untuk mengisi data anggota</span>' ?>
                                            </span>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted small d-none d-md-inline" id="slot_summary_<?= $katId ?>_<?= $idx ?>">
                                                <?php if ($hasData): 
                                                    $u = hitung_umur($pTgl);
                                                ?>
                                                    <?= e($pKelas) ?> &bull; <?= $u['teks'] ?> &bull; <?= count($pSelectedLomba) ?> Lomba
                                                <?php endif; ?>
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="Kosongkan / Hapus Slot" onclick="resetSlot(event, <?= $katId ?>, <?= $idx ?>)">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                            <i class="fa-solid fa-chevron-down text-muted transition-icon"></i>
                                        </div>
                                    </div>

                                    <!-- SLOT BODY -->
                                    <div class="collapse <?= $isOpen ? 'show' : '' ?> slot-collapse-item" id="<?= $collapseId ?>">
                                        <div class="slot-body bg-white">
                                            <div class="row g-3">
                                                <?php if (is_field_enabled('foto')): ?>
                                                    <!-- FOTO ID CARD (MINI UPLOAD BOX) -->
                                                    <div class="col-md-2 col-sm-3 text-center">
                                                        <label class="form-label small fw-bold text-muted d-block">Foto ID Card</label>
                                                        <div class="slot-photo-thumb mx-auto mb-2" id="preview_thumb_<?= $katId ?>_<?= $idx ?>" onclick="document.getElementById('foto_<?= $katId ?>_<?= $idx ?>').click()">
                                                            <?php if (!empty($pFoto)): ?>
                                                                <img src="<?= base_url('uploads/peserta/' . $pFoto) ?>" alt="Foto">
                                                            <?php else: ?>
                                                                <i class="fa-solid fa-camera text-muted fs-5 mb-1"></i>
                                                                <span style="font-size: 0.65rem;" class="text-muted">Pilih</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <input type="file" name="foto_<?= $katId ?>_<?= $idx ?>" id="foto_<?= $katId ?>_<?= $idx ?>" class="d-none" accept="image/*" onchange="previewSlotPhoto(this, <?= $katId ?>, <?= $idx ?>)">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 w-100" style="font-size: 0.72rem;" onclick="document.getElementById('foto_<?= $katId ?>_<?= $idx ?>').click()">
                                                            <i class="fa-solid fa-upload me-1"></i> Unggah
                                                        </button>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- MAIN DATA FIELDS -->
                                                <div class="<?= is_field_enabled('foto') ? 'col-md-10 col-sm-9' : 'col-12' ?>">
                                                    <div class="row g-2">
                                                        <!-- NAMA LENGKAP -->
                                                        <div class="<?= is_field_enabled('nisn_nta') ? 'col-md-5' : 'col-md-8' ?>">
                                                            <label class="form-label small fw-bold text-dark mb-1">Nama Lengkap <span class="text-danger">*</span></label>
                                                            <input type="text" name="peserta[<?= $katId ?>][<?= $idx ?>][nama_lengkap]" class="form-control form-control-sm slot-input-nama" value="<?= e($pNama) ?>" placeholder="Nama lengkap sesuai ijazah/KTA" oninput="updateSlotHeader(<?= $katId ?>, <?= $idx ?>)">
                                                        </div>

                                                        <!-- NISN / NTA -->
                                                        <?php if (is_field_enabled('nisn_nta')): ?>
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-bold text-dark mb-1">NISN / NTA</label>
                                                                <input type="text" name="peserta[<?= $katId ?>][<?= $idx ?>][nisn_nta]" class="form-control form-control-sm" value="<?= e($pNTA) ?>" placeholder="Nomor Induk/NTA">
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][nisn_nta]" value="<?= e($pNTA) ?>">
                                                        <?php endif; ?>

                                                        <!-- KELAS / JABATAN -->
                                                        <div class="col-md-4">
                                                            <label class="form-label small fw-bold text-dark mb-1">Kelas / Tingkatan <span class="text-danger">*</span></label>
                                                            <select name="peserta[<?= $katId ?>][<?= $idx ?>][kelas]" class="form-select form-select-sm">
                                                                <?php if ($katId <= 2): ?>
                                                                    <option value="Kelas X (Sepuluh)" <?= $pKelas === 'Kelas X (Sepuluh)' ? 'selected' : '' ?>>Kelas X (Sepuluh)</option>
                                                                    <option value="Kelas XI (Sebelas)" <?= $pKelas === 'Kelas XI (Sebelas)' ? 'selected' : '' ?>>Kelas XI (Sebelas)</option>
                                                                    <option value="Kelas XII (Dua Belas)" <?= $pKelas === 'Kelas XII (Dua Belas)' ? 'selected' : '' ?>>Kelas XII (Dua Belas)</option>
                                                                <?php else: ?>
                                                                    <option value="Pembina Pendamping" <?= $pKelas === 'Pembina Pendamping' ? 'selected' : '' ?>>Pembina Pendamping</option>
                                                                    <option value="Pembina Gugus Depan" <?= $pKelas === 'Pembina Gugus Depan' ? 'selected' : '' ?>>Pembina Gugus Depan</option>
                                                                    <option value="Pelatih Pembina" <?= $pKelas === 'Pelatih Pembina' ? 'selected' : '' ?>>Pelatih Pembina</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>

                                                        <!-- TEMPAT LAHIR -->
                                                        <?php if (is_field_enabled('tempat_lahir')): ?>
                                                            <div class="col-md-3">
                                                                <label class="form-label small fw-bold text-dark mb-1">Tempat Lahir</label>
                                                                <input type="text" name="peserta[<?= $katId ?>][<?= $idx ?>][tempat_lahir]" class="form-control form-control-sm" value="<?= e($pTempat) ?>" placeholder="Kota/Kab">
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][tempat_lahir]" value="<?= e($pTempat ?: 'Sumbawa') ?>">
                                                        <?php endif; ?>

                                                        <!-- TANGGAL LAHIR (DENGAN HITUNG UMUR OTOMATIS) -->
                                                        <div class="<?= is_field_enabled('tempat_lahir') ? 'col-md-3' : 'col-md-4' ?>">
                                                            <label class="form-label small fw-bold text-dark mb-1">Tanggal Lahir <span class="text-danger">*</span></label>
                                                            <input type="date" name="peserta[<?= $katId ?>][<?= $idx ?>][tanggal_lahir]" class="form-control form-control-sm slot-input-tgl" value="<?= e($pTgl) ?>" onchange="updateSlotAgeLive(this, <?= $katId ?>, <?= $idx ?>)">
                                                        </div>

                                                        <!-- LIVE AGE BADGE -->
                                                        <div class="<?= (is_field_enabled('ukuran_kaos') && is_field_enabled('no_hp')) ? 'col-md-2' : 'col-md-3' ?>">
                                                            <label class="form-label small fw-bold text-dark mb-1">Umur Live</label>
                                                            <div id="display_age_<?= $katId ?>_<?= $idx ?>">
                                                                <?php if (!empty($pTgl)): 
                                                                    $u = hitung_umur($pTgl);
                                                                ?>
                                                                    <span class="badge bg-success bg-opacity-15 text-success font-monospace py-2 w-100"><i class="fa-solid fa-cake-candles me-1"></i> <?= $u['teks'] ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-light text-muted border py-2 w-100">-</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <!-- UKURAN KAOS -->
                                                        <?php if (is_field_enabled('ukuran_kaos')): ?>
                                                            <div class="col-md-2">
                                                                <label class="form-label small fw-bold text-dark mb-1">Ukuran Kaos</label>
                                                                <select name="peserta[<?= $katId ?>][<?= $idx ?>][ukuran_kaos]" class="form-select form-select-sm">
                                                                    <option value="S" <?= $pKaos === 'S' ? 'selected' : '' ?>>S</option>
                                                                    <option value="M" <?= $pKaos === 'M' ? 'selected' : '' ?>>M</option>
                                                                    <option value="L" <?= $pKaos === 'L' ? 'selected' : '' ?>>L</option>
                                                                    <option value="XL" <?= $pKaos === 'XL' ? 'selected' : '' ?>>XL</option>
                                                                    <option value="XXL" <?= $pKaos === 'XXL' ? 'selected' : '' ?>>XXL</option>
                                                                </select>
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][ukuran_kaos]" value="<?= e($pKaos ?: get_default_ukuran_kaos()) ?>">
                                                        <?php endif; ?>

                                                        <!-- NO HP / WA -->
                                                        <?php if (is_field_enabled('no_hp')): ?>
                                                            <div class="<?= is_field_enabled('ukuran_kaos') ? 'col-md-2' : 'col-md-3' ?>">
                                                                <label class="form-label small fw-bold text-dark mb-1">No. WhatsApp</label>
                                                                <input type="text" name="peserta[<?= $katId ?>][<?= $idx ?>][no_hp]" class="form-control form-control-sm" value="<?= e($pHp) ?>" placeholder="08xxxxxxxx">
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][no_hp]" value="<?= e($pHp) ?>">
                                                        <?php endif; ?>

                                                        <!-- RIWAYAT PENYAKIT -->
                                                        <?php if (is_field_enabled('riwayat_penyakit')): ?>
                                                            <div class="col-12">
                                                                <label class="form-label small fw-bold text-dark mb-1">Catatan Khusus / Riwayat Alergi &amp; Penyakit</label>
                                                                <input type="text" name="peserta[<?= $katId ?>][<?= $idx ?>][riwayat_penyakit]" class="form-control form-control-sm" value="<?= e($pRiwayat) ?>" placeholder="Contoh: Asma, alergi debu, atau isi 'Tidak Ada'">
                                                            </div>
                                                        <?php else: ?>
                                                            <input type="hidden" name="peserta[<?= $katId ?>][<?= $idx ?>][riwayat_penyakit]" value="<?= e($pRiwayat) ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- MATA LOMBA SELECTION (MULTI-SELECT COMPACT PILLS) -->
                                                <div class="col-12 border-top pt-2 mt-2">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <span class="small fw-bold text-dark">
                                                            <i class="fa-solid fa-trophy text-warning me-1"></i> Cabang Mata Lomba yang Diikuti:
                                                        </span>
                                                        <span class="text-muted" style="font-size: 0.72rem;">(Bisa pilih lebih dari 1 cabang kompetisi)</span>
                                                    </div>
                                                    <div class="row g-2">
                                                        <?php foreach ($daftarLomba as $l): 
                                                            $isCheck = in_array($l['id'], $pSelectedLomba) ? 'checked' : '';
                                                            $maxPerSchool = (int)$l['max_peserta_per_sekolah'];
                                                        ?>
                                                            <div class="col-md-3 col-sm-6">
                                                                <div class="lomba-pill-checkbox" id="pill_<?= $katId ?>_<?= $idx ?>_<?= $l['id'] ?>">
                                                                    <div class="form-check mb-0">
                                                                        <input class="form-check-input check-lomba-item" 
                                                                               type="checkbox" 
                                                                               name="peserta[<?= $katId ?>][<?= $idx ?>][lomba_ids][]" 
                                                                               value="<?= $l['id'] ?>" 
                                                                               id="lomba_<?= $katId ?>_<?= $idx ?>_<?= $l['id'] ?>" 
                                                                               data-lomba-id="<?= $l['id'] ?>" 
                                                                               data-lomba-max="<?= $maxPerSchool ?>" 
                                                                               data-lomba-name="<?= e($l['nama_lomba']) ?>" 
                                                                               <?= $isCheck ?>>
                                                                        <label class="form-check-label d-block text-truncate cursor-pointer" 
                                                                               for="lomba_<?= $katId ?>_<?= $idx ?>_<?= $l['id'] ?>" 
                                                                               title="<?= e($l['nama_lomba']) ?> (Maksimal <?= $maxPerSchool ?> peserta/sekolah)">
                                                                            <?= e($l['nama_lomba']) ?>
                                                                            <span class="lomba-quota-badge ms-1 badge bg-light text-muted border" id="badge_lomba_<?= $katId ?>_<?= $idx ?>_<?= $l['id'] ?>">Maks <?= $maxPerSchool ?></span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
        </fieldset>

        <!-- BOTTOM STICKY ACTION BAR -->
        <?php if ($isLocked): ?>
            <div class="custom-card p-4 mt-4 text-center bg-white shadow-sm border border-danger border-opacity-25">
                <div class="d-inline-flex align-items-center justify-content-center p-3 bg-danger bg-opacity-10 text-danger rounded-circle mb-3">
                    <i class="fa-solid fa-lock fs-3"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Formulir Pendaftaran Telah Terkunci (Final Resmi)</h5>
                <p class="text-muted small mb-3">
                    Data peserta pangkalan sekolah Anda telah difinalisasi resmi. Pengubahan data formulir dinonaktifkan.
                </p>
                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary px-4 py-2">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Peserta
                    </a>
                    <a href="<?= base_url('dashboard/cetak-idcard.php') ?>" target="_blank" class="btn btn-scout-gold px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-id-card me-1"></i> Cetak ID Card Kontingen
                    </a>
                    <a href="<?= base_url('dashboard/cetak-formulir.php') ?>" target="_blank" class="btn btn-scout-navy px-4 py-2">
                        <i class="fa-solid fa-file-pdf me-1 text-warning"></i> Cetak Formulir Rekap A.01
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="custom-card p-4 mt-4 text-center bg-white shadow-sm border">
                <h5 class="fw-bold text-dark mb-2">Konfirmasi Penyimpanan Data Batch Kontingen</h5>
                <p class="text-muted small mb-4">
                    Pilih <strong>Simpan Sebagai DRAF</strong> jika pengisian belum lengkap dan ingin dilanjutkan nanti, atau pilih <strong>Simpan &amp; Finalisasi (RESMI)</strong> jika seluruh data peserta sudah fiks (data akan langsung dikunci setelah finalisasi).
                </p>
                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="<?= base_url('dashboard/peserta.php') ?>" class="btn btn-outline-secondary px-4 py-2">
                        <i class="fa-solid fa-xmark me-1"></i> Batal / Kembali
                    </a>
                    <button type="submit" name="save_action" value="draft" class="btn btn-scout-gold px-4 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-bookmark me-1"></i> Simpan Sebagai DRAF
                    </button>
                    <button type="submit" name="save_action" value="final" class="btn btn-scout-navy px-5 py-2 fw-bold shadow-sm fs-6" onclick="return confirm('PERINGATAN FINALISASI:\n\nSetelah melakukan Simpan & Finalisasi (RESMI), data kontingen akan langsung DIKUNCI dan Anda tidak dapat lagi mengubah data kecuali melalui persetujuan Admin Panitia.\n\nApakah Anda yakin ingin memfinalisasi data sekarang?')">
                        <i class="fa-solid fa-lock me-1"></i> Simpan &amp; Finalisasi (RESMI)
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Calculate Age Function
function calculateAge(dobString) {
    if (!dobString) return null;
    const dob = new Date(dobString);
    const today = new Date();
    if (isNaN(dob.getTime())) return null;

    let years = today.getFullYear() - dob.getFullYear();
    let months = today.getMonth() - dob.getMonth();
    let days = today.getDate() - dob.getDate();

    if (days < 0) {
        months--;
    }
    if (months < 0) {
        years--;
        months += 12;
    }
    return { years, months };
}

// Live Age Updater on Date Change
function updateSlotAgeLive(inputElem, katId, slotIdx) {
    const val = inputElem.value;
    const container = document.getElementById(`display_age_${katId}_${slotIdx}`);
    if (!container) return;

    if (val) {
        const age = calculateAge(val);
        if (age && age.years >= 0) {
            container.innerHTML = `<span class="badge bg-success bg-opacity-15 text-success font-monospace py-2 w-100"><i class="fa-solid fa-cake-candles me-1"></i> ${age.years} Thn ${age.months} Bln</span>`;
        } else {
            container.innerHTML = `<span class="badge bg-danger py-2 w-100">Invalid</span>`;
        }
    } else {
        container.innerHTML = `<span class="badge bg-light text-muted border py-2 w-100">-</span>`;
    }
}

// Live Slot Header & Card Class Updater
function updateSlotHeader(katId, slotIdx) {
    const card = document.getElementById(`slot_card_${katId}_${slotIdx}`);
    const input = card.querySelector('.slot-input-nama');
    const titleElem = document.getElementById(`slot_title_${katId}_${slotIdx}`);
    const badgeElem = document.getElementById(`slot_badge_status_${katId}_${slotIdx}`);

    if (input && titleElem) {
        const val = input.value.trim();
        if (val.length > 0) {
            titleElem.textContent = val;
            titleElem.classList.remove('fw-normal');
            if (!card.classList.contains('is-filled')) {
                card.classList.remove('is-empty');
                card.classList.add('is-draft');
                if (badgeElem && !badgeElem.innerHTML.includes('AKTIF')) {
                    badgeElem.innerHTML = `<span class="badge bg-warning text-dark"><i class="fa-solid fa-bookmark me-1"></i> DRAF</span>`;
                }
            }
        } else {
            titleElem.innerHTML = `<span class="text-muted fw-normal">Klik untuk mengisi data anggota</span>`;
            card.classList.remove('is-filled', 'is-draft');
            card.classList.add('is-empty');
            if (badgeElem) {
                badgeElem.innerHTML = `<span class="badge bg-light text-muted border"><i class="fa-regular fa-circle me-1"></i> Kosong</span>`;
            }
        }
    }
    updateTabCounters();
}

// Photo Preview per Slot
function previewSlotPhoto(inputElem, katId, slotIdx) {
    const file = inputElem.files[0];
    const thumbContainer = document.getElementById(`preview_thumb_${katId}_${slotIdx}`);
    if (file && thumbContainer) {
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran foto terlalu besar! Maksimal 3 MB.');
            inputElem.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            thumbContainer.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(file);
    }
}

// Reset / Clear Slot
function resetSlot(e, katId, slotIdx) {
    e.stopPropagation();
    if (!confirm('Apakah Anda yakin ingin mengosongkan / menghapus data pada slot ini?')) {
        return;
    }
    const card = document.getElementById(`slot_card_${katId}_${slotIdx}`);
    if (!card) return;

    // Mark as delete if has existing ID
    const isDeleteInput = document.getElementById(`is_delete_${katId}_${slotIdx}`);
    if (isDeleteInput) {
        isDeleteInput.value = '1';
    }

    // Clear inputs
    card.querySelectorAll('input[type="text"], input[type="date"]').forEach(inp => inp.value = '');
    card.querySelectorAll('input[type="checkbox"]').forEach(chk => chk.checked = false);
    card.querySelectorAll('input[type="file"]').forEach(f => f.value = '');

    const thumb = document.getElementById(`preview_thumb_${katId}_${slotIdx}`);
    if (thumb) {
        thumb.innerHTML = `<i class="fa-solid fa-camera text-muted fs-5 mb-1"></i><span style="font-size: 0.65rem;" class="text-muted">Pilih</span>`;
    }

    const ageCont = document.getElementById(`display_age_${katId}_${slotIdx}`);
    if (ageCont) {
        ageCont.innerHTML = `<span class="badge bg-light text-muted border py-2 w-100">-</span>`;
    }

    updateSlotHeader(katId, slotIdx);
    updateAllLombaQuotas();
}

// Live quota counters for all configured participant categories.
function updateAllLombaQuotas() {
    const lombaLimits = {};
    const lombaCounts = {};

    // 1. Gather all unique lomba IDs, max quotas, and active checked counts
    document.querySelectorAll('.check-lomba-item').forEach(chk => {
        const lId = chk.dataset.lombaId;
        const maxQuota = parseInt(chk.dataset.lombaMax, 10) || 999;
        lombaLimits[lId] = maxQuota;
        if (!lombaCounts[lId]) lombaCounts[lId] = 0;

        if (chk.checked) {
            // Only count if slot is not marked as deleted
            const card = chk.closest('.slot-item-card');
            const isDel = card ? card.querySelector('input[id^="is_delete_"]') : null;
            if (!isDel || isDel.value !== '1') {
                lombaCounts[lId]++;
            }
        }
    });

    // 2. Iterate through all checkboxes and dynamically enforce quota restrictions
    document.querySelectorAll('.check-lomba-item').forEach(chk => {
        const lId = chk.dataset.lombaId;
        const maxQuota = lombaLimits[lId] || 999;
        const currentCount = lombaCounts[lId] || 0;
        const pill = chk.closest('.lomba-pill-checkbox');
        const badge = pill ? pill.querySelector('.lomba-quota-badge') : null;

        if (chk.checked) {
            // Currently checked in this slot
            chk.disabled = false;
            if (pill) {
                pill.classList.remove('lomba-full-disabled');
                pill.title = `Terpilih untuk peserta ini (${currentCount}/${maxQuota} kuota kontingen terpakai)`;
            }
            if (badge) {
                badge.className = 'lomba-quota-badge ms-1 badge bg-success text-white';
                badge.textContent = `${currentCount}/${maxQuota}`;
            }
        } else {
            // Unchecked slot
            if (currentCount >= maxQuota) {
                // Quota full! Disable checkbox and show warning badge
                chk.disabled = true;
                if (pill) {
                    pill.classList.add('lomba-full-disabled');
                    pill.title = `Tidak bisa memilih, kuota kontingen untuk lomba ini sudah terisi penuh (Maksimal ${maxQuota} peserta per sekolah)`;
                }
                if (badge) {
                    badge.className = 'lomba-quota-badge ms-1 badge bg-danger text-white';
                    badge.textContent = `Penuh (${maxQuota}/${maxQuota})`;
                }
            } else {
                // Quota still available
                chk.disabled = false;
                if (pill) {
                    pill.classList.remove('lomba-full-disabled');
                    pill.title = `Tersedia ${maxQuota - currentCount} kuota lagi dari total ${maxQuota}`;
                }
                if (badge) {
                    badge.className = 'lomba-quota-badge ms-1 badge bg-light text-muted border';
                    badge.textContent = `Sisa ${maxQuota - currentCount}`;
                }
            }
        }
    });
}

// Update Tab Badges Count Dynamically
function updateTabCounters() {
    [1, 2, 3, 4].forEach(kId => {
        const cards = document.querySelectorAll(`[id^="slot_card_${kId}_"]`);
        let filled = 0;
        cards.forEach(c => {
            const namaInp = c.querySelector('.slot-input-nama');
            if (namaInp && namaInp.value.trim() !== '') {
                filled++;
            }
        });
        const badge = document.getElementById(`badge_count_${kId}`);
        const max = badge ? Number(badge.dataset.quota) : (kId <= 2 ? 11 : 1);
        if (badge) {
            badge.textContent = `${filled}/${max}`;
        }
    });
}

// Expand / Collapse All Slots
let allOpen = true;
const btnToggle = document.getElementById('btnToggleAllSlots');
if (btnToggle) {
    btnToggle.addEventListener('click', function() {
        allOpen = !allOpen;
        document.querySelectorAll('.slot-collapse-item').forEach(el => {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            if (allOpen) {
                bsCollapse.show();
            } else {
                bsCollapse.hide();
            }
        });
    });
}

// Mobile sidebar toggle
const sidebarToggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar-wrapper');
if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
    });
}

// Initialize on page load and attach listeners to all lomba checkboxes
document.addEventListener('DOMContentLoaded', function() {
    updateAllLombaQuotas();
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('check-lomba-item')) {
            updateAllLombaQuotas();
        }
    });
});
</script>
</body>
</html>
