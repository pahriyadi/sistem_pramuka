<?php
/**
 * Petunjuk Teknis & Ketentuan Pendaftaran
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Petunjuk Teknis (Juknis) & Ketentuan';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$stmtLomba = $pdo->query("SELECT * FROM mata_lomba WHERE status_aktif = 'Y' ORDER BY id ASC");
$daftarLomba = $stmtLomba->fetchAll();
$quotaRows = $pdo->query("SELECT kode_kategori, kuota_max FROM kategori_peserta ORDER BY urutan ASC")->fetchAll();
$quotaByCode = [];
foreach ($quotaRows as $quotaRow) {
    $quotaByCode[$quotaRow['kode_kategori']] = (int)$quotaRow['kuota_max'];
}
$totalKuota = array_sum($quotaByCode);
$namaBrand = get_setting('nama_brand', 'RACANA UNSA');
$lokasiKegiatan = get_setting('lokasi_kegiatan', 'lokasi kegiatan');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-file-shield me-1"></i> PEDOMAN RESMI</span>
        <h1 class="mb-2">Petunjuk Teknis Kontingen</h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Ketentuan komposisi <?= $totalKuota ?> anggota, berkas pendaftaran, pas foto ID Card, dan juknis mata lomba.
        </p>
    </div>
</section>

<!-- Main Juknis Content -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="row g-4 g-lg-5">
            <!-- Left Navigation / Highlights -->
            <div class="col-lg-4">
                <div class="sticky-sidebar">
                    <div class="custom-card p-4 mb-3 border shadow-sm">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-check text-scout-navy me-2"></i> Daftar Isi Juknis</h6>
                        <ul class="list-unstyled d-flex flex-column gap-2 small mb-0">
                            <li><a href="#komposisi" class="text-decoration-none text-dark fw-semibold"><i class="fa-solid fa-angle-right text-scout-gold me-1"></i> 1. Komposisi Kontingen</a></li>
                            <li><a href="#syarat" class="text-decoration-none text-dark fw-semibold"><i class="fa-solid fa-angle-right text-scout-gold me-1"></i> 2. Syarat Pendaftaran</a></li>
                            <li><a href="#fotoidcard" class="text-decoration-none text-dark fw-semibold"><i class="fa-solid fa-angle-right text-scout-gold me-1"></i> 3. Foto ID Card</a></li>
                            <li><a href="#lomba" class="text-decoration-none text-dark fw-semibold"><i class="fa-solid fa-angle-right text-scout-gold me-1"></i> 4. Cabang Mata Lomba</a></li>
                            <li><a href="#administrasi" class="text-decoration-none text-dark fw-semibold"><i class="fa-solid fa-angle-right text-scout-gold me-1"></i> 5. Daftar Ulang di Buper</a></li>
                        </ul>
                    </div>

                    <div class="custom-card p-4 text-white shadow-sm" style="background: linear-gradient(135deg, #07172b 0%, #0b2545 100%);">
                        <h6 class="fw-bold mb-2 text-warning"><i class="fa-solid fa-cloud-arrow-down me-2"></i> Unduh Berkas Juknis</h6>
                        <p class="small mb-3" style="color: rgba(255, 255, 255, 0.92); font-size: 0.85rem; line-height: 1.5;">Unduh dokumen Petunjuk Pelaksanaan (Juklak) dan Juknis resmi format PDF.</p>
                        <a href="<?= base_url('unduh-dokumen.php') ?>" class="btn btn-scout-gold w-100 fw-bold btn-sm mb-2">
                            <i class="fa-solid fa-download me-1"></i> Pusat Unduh Berkas
                        </a>
                        <button class="btn btn-outline-light w-100 btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Cetak Halaman Ini
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Detailed Content -->
            <div class="col-lg-8">
                <!-- 1. KOMPOSISI KONTINGEN -->
                <div id="komposisi" class="mb-5">
                    <span class="section-tag"><i class="fa-solid fa-users-viewfinder me-1"></i> STRUKTUR KONTINGEN</span>
                    <h3 class="fw-bold text-dark mb-3">1. Komposisi Kontingen (Maksimal <?= $totalKuota ?> Orang)</h3>
                    <p class="text-muted">
                        Setiap Pangkalan Gugus Depan SMA / SMK / MA / Sederajat yang mendaftar berhak mengirimkan 1 kontingen resmi yang terdiri dari maksimal <strong><?= $totalKuota ?> orang</strong> dengan formasi sebagai berikut:
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Unsur Kontingen</th>
                                    <th>Kategori Gender</th>
                                    <th>Jumlah Kuota</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td><strong>Barisan / Sangga Putra</strong></td>
                                    <td><span class="badge bg-primary">Laki-laki</span></td>
                                    <td><strong><?= $quotaByCode['BAR_PUTRA'] ?? 0 ?> Orang</strong></td>
                                    <td>Peserta Penegak Aktif Putra</td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td><strong>Barisan / Sangga Putri</strong></td>
                                    <td><span class="badge bg-danger">Perempuan</span></td>
                                    <td><strong><?= $quotaByCode['BAR_PUTRI'] ?? 0 ?> Orang</strong></td>
                                    <td>Peserta Penegak Aktif Putri</td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td><strong>Pembina Pendamping Putra (Bindamping PA)</strong></td>
                                    <td><span class="badge bg-primary">Laki-laki</span></td>
                                    <td><strong><?= $quotaByCode['BINDAMPING_PA'] ?? 0 ?> Orang</strong></td>
                                    <td>Pembina / Guru Pendamping PA</td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td><strong>Pembina Pendamping Putri (Bindamping PI)</strong></td>
                                    <td><span class="badge bg-danger">Perempuan</span></td>
                                    <td><strong><?= $quotaByCode['BINDAMPING_PI'] ?? 0 ?> Orang</strong></td>
                                    <td>Pembina / Guru Pendamping PI</td>
                                </tr>
                                <tr class="table-success fw-bold">
                                    <td colspan="3" class="text-end">TOTAL MAKSIMAL PER KONTINGEN:</td>
                                    <td colspan="2"><span class="badge bg-success fs-6"><?= $totalKuota ?> Orang</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. SYARAT PESERTA -->
                <div id="syarat" class="mb-5 pt-3 border-top">
                    <span class="section-tag"><i class="fa-solid fa-id-card me-1"></i> PERSYARATAN ADMINISTRASI</span>
                    <h3 class="fw-bold text-dark mb-3">2. Syarat &amp; Ketentuan Peserta</h3>
                    <ul class="text-muted ps-3">
                        <li class="mb-2">Merupakan anggota Pramuka Penegak aktif di pangkalan Gugus Depan SMA/SMK/MA sederajat yang dibuktikan dengan Surat Tugas / Mandat dari Kamabigus.</li>
                        <li class="mb-2">Terdaftar secara online pada portal resmi <?= e($namaBrand) ?> (biodata lengkap, tanggal lahir, foto, dan cabang mata lomba yang diikuti).</li>
                        <li class="mb-2">Membawa Surat Keterangan Sehat dari dokter/puskesmas atau mengisi riwayat kesehatan pada formulir pendaftaran.</li>
                        <li class="mb-2">Mematuhi seluruh tata tertib adat perkemahan dan menjunjung tinggi nilai sportivitas serta Dasa Darma Pramuka.</li>
                    </ul>
                </div>

                <!-- 3. KETENTUAN FOTO ID CARD -->
                <div id="fotoidcard" class="mb-5 pt-3 border-top">
                    <span class="section-tag"><i class="fa-solid fa-camera me-1"></i> TATA CARA FOTO ID CARD</span>
                    <h3 class="fw-bold text-dark mb-3">3. Ketentuan Pas Foto ID Card Peserta</h3>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-8">
                            <p class="text-muted">
                                Foto yang diunggah pada sistem pendaftaran akan otomatis disematkan dan dicetak pada Kartu Tanda Peserta (ID Card / KTA). Harap memperhatikan kriteria berikut:
                            </p>
                            <ul class="text-muted small ps-3">
                                <li>Format file foto: <strong>JPG, JPEG, PNG, atau WEBP</strong> (Maksimal 3 MB).</li>
                                <li>Foto setengah badan tegak, berpakaian seragam pramuka lengkap atau pakaian rapi berkerah.</li>
                                <li>Wajah tampak jelas, tidak menggunakan kacamata hitam atau penutup wajah.</li>
                                <li>Latar belakang (background) foto disarankan polos (merah/biru/putih).</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="id-card-photo-container p-0 mb-2">
                                    <div class="id-card-photo d-flex align-items-center justify-content-center bg-white text-muted mx-auto" style="width:100px; height:130px;">
                                        <i class="fa-solid fa-user fs-1 text-secondary"></i>
                                    </div>
                                </div>
                                <small class="text-muted d-block fw-semibold">Contoh Layout ID Card</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. RINCIAN CABANG MATA LOMBA -->
                <div id="lomba" class="mb-5 pt-3 border-top">
                    <span class="section-tag"><i class="fa-solid fa-trophy me-1"></i> CABANG KOMPETISI</span>
                    <h3 class="fw-bold text-dark mb-3">4. Petunjuk Teknis Mata Lomba</h3>
                    
                    <div class="accordion" id="accordionLomba">
                        <?php foreach ($daftarLomba as $idx => $l): ?>
                            <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                                <h2 class="accordion-header" id="heading<?= $l['id'] ?>">
                                    <button class="accordion-button <?= $idx !== 0 ? 'collapsed' : '' ?> fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $l['id'] ?>">
                                        <span class="badge bg-success bg-opacity-10 text-success me-2 font-monospace"><?= e($l['kode_lomba']) ?></span>
                                        <?= e($l['nama_lomba']) ?>
                                    </button>
                                </h2>
                                <div id="collapse<?= $l['id'] ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#accordionLomba">
                                    <div class="accordion-body bg-light">
                                        <p class="text-muted mb-2"><strong>Deskripsi:</strong> <?= e($l['deskripsi']) ?></p>
                                        <p class="text-muted mb-2"><strong>Petunjuk Singkat:</strong> <?= e($l['juknis_singkat'] ?: 'Mengikuti juknis standar kepramukaan.') ?></p>
                                        <div class="row g-2 small pt-2 border-top">
                                            <div class="col-sm-6">
                                                <i class="fa-solid fa-users text-primary me-1"></i> Kuota per Sekolah: <strong><?= $l['max_peserta_per_sekolah'] ?> Orang</strong>
                                            </div>
                                            <div class="col-sm-6">
                                                <i class="fa-solid fa-location-dot text-danger me-1"></i> Lokasi: <strong><?= e($l['lokasi_lomba']) ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 5. ALUR ADMINISTRASI -->
                <div id="administrasi" class="pt-3 border-top">
                    <span class="section-tag"><i class="fa-solid fa-clipboard-check me-1"></i> TAHAP AKHIR</span>
                    <h3 class="fw-bold text-dark mb-3">5. Registrasi Ulang di Lokasi (Daftar Ulang)</h3>
                    <p class="text-muted">
                        Pada hari kedatangan di <?= e($lokasiKegiatan) ?>, Pimpinan Kontingen / Pembina Pendamping wajib menyerahkan berkas administrasi berikut ke Sekretariat Panitia:
                    </p>
                    <ul class="text-muted ps-3 mb-4">
                        <li>Cetak Formulir A.01 (Rekapitulasi Kontingen) yang diunduh dari Dashboard.</li>
                        <li>Cetak Formulir B.01 (Biodata Lengkap Peserta &amp; Bindamping).</li>
                        <li>Surat Mandat / Tugas dari Kepala Sekolah (Kamabigus).</li>
                        <li>Membawa ID Card yang telah dicetak untuk diverifikasi barcode / stempel basah panitia.</li>
                    </ul>

                    <div class="p-3 bg-warning bg-opacity-10 border border-warning rounded-3">
                        <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                        <span class="text-dark small">Pendaftaran online ditutup pada <strong><?= e(get_setting('tgl_selesai_daftar', '31 Oktober 2026')) ?></strong>. Harap menyelesaikan penginputan anggota kontingen sebelum batas waktu.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
