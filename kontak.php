<?php
/**
 * Halaman Kontak & Lokasi Sekretariat
 * Racana Universitas Samawa (UNSA)
 */
$pageTitle = 'Kontak & Lokasi';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
$namaRacana = get_setting('nama_racana', 'Racana Universitas Samawa');
$kontakWa = get_setting('kontak_wa', '0852-3891-2345');
$waNumber = get_whatsapp_number();
$jamLayanan = preg_split('/\r\n|\r|\n|;/', get_setting('jam_layanan', 'Senin - Sabtu: 08.00 - 21.00 WITA; Minggu: 09.00 - 17.00 WITA')) ?: [];

$sentSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulated contact message
    $sentSuccess = true;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<section class="page-banner-scout">
    <div class="container text-center">
        <span class="badge-hero mb-2"><i class="fa-solid fa-headset me-1"></i> LAYANAN INFORMASI</span>
        <h1 class="mb-2">Hubungi Panitia</h1>
        <p class="lead max-w-700 mx-auto small mb-0">
            Layanan helpdesk dan informasi pendaftaran <?= e(get_setting('nama_acara', 'kegiatan Racana')) ?>.
        </p>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5">
                <span class="section-tag"><i class="fa-solid fa-address-book me-1"></i> SEKRETARIAT</span>
                <h3 class="fw-bold text-dark mb-4"><?= e($namaRacana) ?></h3>
                
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="bg-scout-navy bg-opacity-10 text-scout-navy p-3 rounded-3 flex-shrink-0" style="width: 46px; height: 46px; display: grid; place-items: center;">
                            <i class="fa-solid fa-map-location-dot fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">Alamat Kampus:</h6>
                            <p class="text-muted small mb-0"><?= e(get_setting('alamat_racana', 'Jl. By Pass Sering, Kerato, Kec. Unter Iwes, Kabupaten Sumbawa, Nusa Tenggara Barat 84316')) ?></p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 align-items-start">
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3 flex-shrink-0" style="width: 46px; height: 46px; display: grid; place-items: center;">
                            <i class="fa-brands fa-whatsapp fs-5 text-dark"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">WhatsApp Helpdesk:</h6>
                            <p class="text-muted small mb-1"><?= e($kontakWa) ?></p>
                            <?php if ($waNumber !== ''): ?>
                                <a href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-scout-gold py-1 px-3">
                                    <i class="fa-brands fa-whatsapp me-1"></i> Chat WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-3 align-items-start">
                        <div class="bg-scout-maroon bg-opacity-10 text-scout-maroon p-3 rounded-3 flex-shrink-0" style="width: 46px; height: 46px; display: grid; place-items: center;">
                            <i class="fa-solid fa-envelope fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">Email Resmi:</h6>
                            <p class="text-muted small mb-0"><?= e(get_setting('email_racana', 'racana@unsa.ac.id')) ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold mb-1 text-scout-navy small"><i class="fa-solid fa-clock text-scout-gold me-1"></i> Jam Pelayanan Sekretariat</h6>
                    <?php foreach ($jamLayanan as $index => $jam): ?>
                        <?php if (trim($jam) !== ''): ?>
                            <p class="text-muted small <?= $index === array_key_last($jamLayanan) ? 'mb-0' : 'mb-1' ?>" style="font-size: 0.78rem;">&bull; <?= e(trim($jam)) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="custom-card p-4 p-md-5">
                    <h4 class="fw-bold mb-2">Kirim Pesan / Pertanyaan</h4>
                    <p class="text-muted small mb-4">Jika ada kendala dalam pendaftaran atau pertanyaan seputar juknis lomba, silakan kirimkan pesan Anda melalui formulir di bawah ini.</p>

                    <?php if ($sentSuccess): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="fa-solid fa-circle-check fs-5 me-2"></i>
                            <div>Terima kasih! Pesan Anda telah kami terima dan akan segera direspon oleh panitia via WhatsApp/Email.</div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nama Lengkap / Nama Pembina <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" placeholder="Contoh: Kak Herman" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Asal Sekolah / Pangkalan <span class="text-danger">*</span></label>
                                <input type="text" name="sekolah" class="form-control" placeholder="Contoh: SMAN 1 Sumbawa" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">No. WhatsApp Aktif <span class="text-danger">*</span></label>
                                <input type="text" name="whatsapp" class="form-control" placeholder="08xxxxxxxxxx" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Topik Pertanyaan</label>
                                <select name="topik" class="form-select">
                                    <option>Pendaftaran Akun Sekolah</option>
                                    <option>Petunjuk Teknis Lomba</option>
                                    <option>Upload Foto &amp; ID Card</option>
                                    <option>Akomodasi &amp; Tenda Buper</option>
                                    <option>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Isi Pesan / Pertanyaan <span class="text-danger">*</span></label>
                                <textarea name="pesan" rows="4" class="form-control" placeholder="Tuliskan pertanyaan atau kendala yang Anda alami..." required></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-scout-primary w-100 py-2 fw-semibold">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Kirim Pesan ke Panitia
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
