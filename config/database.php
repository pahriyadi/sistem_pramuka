<?php
/**
 * Database Configuration & Auto-Connection Helper
 * Racana Universitas Samawa (UNSA)
 */

// Load environment variables from .env if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!empty($key) && getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'db_racana_unsa');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                // First try connecting directly to DB_NAME
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Run lightweight schema sync & column migration
                self::ensureSchemaColumns(self::$instance);
            } catch (PDOException $e) {
                // If database doesn't exist yet, attempt automatic creation and schema setup
                if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                        $pdoInit = new PDO($dsnNoDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                        $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                        
                        // Connect to newly created database
                        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                        self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                        ]);

                        // Run automatic schema initialization
                        self::autoMigrate(self::$instance);
                    } catch (PDOException $initErr) {
                        die("<div style='font-family:sans-serif;padding:20px;background:#fee;color:#c00;border:1px solid #f99;margin:20px;border-radius:8px;'>
                            <h3>Gagal Menginisialisasi Database MySQL</h3>
                            <p>Error: " . htmlspecialchars($initErr->getMessage()) . "</p>
                            <p>Pastikan MySQL pada XAMPP sudah berjalan (Running), atau jalankan file <code>database/database.sql</code> secara manual via phpMyAdmin.</p>
                        </div>");
                    }
                } else {
                    die("<div style='font-family:sans-serif;padding:20px;background:#fee;color:#c00;border:1px solid #f99;margin:20px;border-radius:8px;'>
                        <h3>Koneksi Database Gagal</h3>
                        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
                        <p>Pastikan service MySQL di XAMPP Control Panel sudah dalam kondisi <strong>Start / Running</strong>.</p>
                    </div>");
                }
            }
        }
        return self::$instance;
    }

    /**
     * Auto migration to create tables and default master data
     */
    public static function autoMigrate(PDO $pdo): void {
        $sqlPath = dirname(__DIR__) . '/database/database.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            $pdo->exec($sql);
        }
    }

    /**
     * Ensure new schema columns exist dynamically & migrate missing columns
     */
    private static function ensureSchemaColumns(PDO $pdo): void {
        try {
            // 1. Check & Alter mata_lomba columns
            $stmt = $pdo->query("SHOW COLUMNS FROM `mata_lomba` LIKE 'juknis_lengkap'");
            if (!$stmt->fetch()) {
                $alterQueries = [
                    "ALTER TABLE `mata_lomba` ADD COLUMN `juknis_lengkap` TEXT NULL AFTER `juknis_singkat`",
                    "ALTER TABLE `mata_lomba` ADD COLUMN `kriteria_penilaian` TEXT NULL AFTER `juknis_lengkap`",
                    "ALTER TABLE `mata_lomba` ADD COLUMN `alat_bahan` TEXT NULL AFTER `kriteria_penilaian`",
                    "ALTER TABLE `mata_lomba` ADD COLUMN `jadwal_lomba` VARCHAR(100) DEFAULT 'Hari Ke-2 (16 Nov 2026, 08.00 WITA)' AFTER `alat_bahan`",
                    "ALTER TABLE `mata_lomba` ADD COLUMN `kontak_pj` VARCHAR(30) DEFAULT '0852-3891-2345' AFTER `penanggung_jawab`",
                    "ALTER TABLE `mata_lomba` ADD COLUMN `icon_lomba` VARCHAR(50) DEFAULT 'fa-trophy' AFTER `lokasi_lomba`"
                ];
                foreach ($alterQueries as $q) {
                    try {
                        $pdo->exec($q);
                    } catch (Exception $colErr) {
                        // ignore if already added
                    }
                }
                // Run full SQL to update seed records
                self::autoMigrate($pdo);
            }

            // 2. Check & Alter berita_pengumuman column 'ringkasan'
            $stmtBerita = $pdo->query("SHOW COLUMNS FROM `berita_pengumuman` LIKE 'ringkasan'");
            if (!$stmtBerita->fetch()) {
                try {
                    $pdo->exec("ALTER TABLE `berita_pengumuman` ADD COLUMN `ringkasan` TEXT NULL AFTER `kategori`");
                } catch (Exception $bErr) {
                    // ignore if already added
                }
                self::autoMigrate($pdo);
            }

            // 3. Check & Modify peserta status column to support ENUM('aktif', 'draft', 'batal')
            try {
                $pdo->exec("ALTER TABLE `peserta` MODIFY COLUMN `status` ENUM('aktif', 'draft', 'batal') NOT NULL DEFAULT 'aktif'");
            } catch (Exception $pErr) {
                // ignore if already set
            }

            // 4. Check & Alter sekolah column status_pembayaran & status_kunci
            $stmtSekolahCol = $pdo->query("SHOW COLUMNS FROM `sekolah` LIKE 'status_pembayaran'");
            if (!$stmtSekolahCol->fetch()) {
                try {
                    $pdo->exec("ALTER TABLE `sekolah` ADD COLUMN `status_pembayaran` ENUM('belum_bayar', 'menunggu_konfirmasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'belum_bayar' AFTER `status_verifikasi`");
                } catch (Exception $sErr) {
                    // ignore
                }
            }

            $stmtKunciCol = $pdo->query("SHOW COLUMNS FROM `sekolah` LIKE 'status_kunci'");
            if (!$stmtKunciCol->fetch()) {
                try {
                    $pdo->exec("ALTER TABLE `sekolah` ADD COLUMN `status_kunci` ENUM('terbuka', 'terkunci') NOT NULL DEFAULT 'terbuka' AFTER `status_pembayaran`");
                    $pdo->exec("ALTER TABLE `sekolah` ADD COLUMN `finalized_at` DATETIME NULL AFTER `status_kunci`");
                    $pdo->exec("ALTER TABLE `sekolah` ADD COLUMN `kunci_catatan` VARCHAR(255) NULL AFTER `finalized_at`");
                } catch (Exception $kErr) {
                    // ignore
                }
            }

            // 5. Check & Create Table pembayaran
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `pembayaran` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `sekolah_id` INT NOT NULL,
                  `kode_bayar` VARCHAR(50) NOT NULL UNIQUE,
                  `jumlah_bayar` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                  `metode_bayar` ENUM('transfer_bank', 'qris') NOT NULL DEFAULT 'transfer_bank',
                  `bank_pengirim` VARCHAR(100) NULL,
                  `atas_nama_pengirim` VARCHAR(150) NULL,
                  `tanggal_bayar` DATE NOT NULL,
                  `bukti_transfer` VARCHAR(255) NOT NULL,
                  `status` ENUM('menunggu_konfirmasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu_konfirmasi',
                  `catatan_sekolah` TEXT NULL,
                  `catatan_admin` TEXT NULL,
                  `diverifikasi_oleh` INT NULL,
                  `diverifikasi_pada` DATETIME NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 6. Check & Create Table dokumen_kegiatan (Juklak, Juknis, Surat Delegasi, Form Izin, dll)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `dokumen_kegiatan` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `kategori` ENUM('juklak_juknis', 'surat_delegasi', 'blangko_formulir', 'dokumen_lainnya') NOT NULL DEFAULT 'juklak_juknis',
                  `judul_dokumen` VARCHAR(255) NOT NULL,
                  `deskripsi` TEXT NULL,
                  `file_path` VARCHAR(255) NOT NULL,
                  `file_type` VARCHAR(50) DEFAULT 'pdf',
                  `file_size` VARCHAR(50) DEFAULT '1.5 MB',
                  `download_count` INT DEFAULT 0,
                  `status_aktif` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                  `urutan` INT DEFAULT 1,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 7. Seed Default Dokumen Kegiatan if empty
            $cntDokumen = (int)$pdo->query("SELECT COUNT(*) FROM `dokumen_kegiatan`")->fetchColumn();
            if ($cntDokumen === 0) {
                $defaultDocs = [
                    [
                        'kategori'      => 'juklak_juknis',
                        'judul_dokumen' => 'Petunjuk Pelaksanaan & Petunjuk Teknis (Juklak & Juknis) Kemah Bakti & Lomba Penegak se-NTB 2026',
                        'deskripsi'     => 'Buku pedoman resmi yang memuat ketentuan umum perkemahan, tata tertib bumi perkemahan, rincian teknis seluruh cabang mata lomba, sistem penilaian dewan juri, dan jadwal kegiatan lengkap.',
                        'file_path'     => 'juklak_juknis_kemah_bakti_unsa_2026.pdf',
                        'file_type'     => 'pdf',
                        'file_size'     => '2.4 MB',
                        'urutan'        => 1
                    ],
                    [
                        'kategori'      => 'surat_delegasi',
                        'judul_dokumen' => 'Format Surat Permohonan Delegasi & Surat Mandat Tugas Kontingen Gudep Sekolah',
                        'deskripsi'     => 'Template resmi format surat tugas dan permohonan delegasi kontingen dari Kepala Sekolah / Kamabigus Pangkalan ke Panitia Pelaksana Kemah Bakti Racana UNSA.',
                        'file_path'     => 'format_surat_permohonan_delegasi_kontingen.docx',
                        'file_type'     => 'docx',
                        'file_size'     => '480 KB',
                        'urutan'        => 2
                    ],
                    [
                        'kategori'      => 'blangko_formulir',
                        'judul_dokumen' => 'Blangko Formulir Surat Pernyataan & Izin Orang Tua / Wali Peserta Kemah Bakti',
                        'deskripsi'     => 'Formulir persetujuan orang tua atau wali bagi peserta didik pramuka penegak yang mengikuti perkemahan, dilengkapi form riwayat kesehatan khusus.',
                        'file_path'     => 'formulir_izin_orang_tua_wali_peserta.pdf',
                        'file_type'     => 'pdf',
                        'file_size'     => '350 KB',
                        'urutan'        => 3
                    ],
                    [
                        'kategori'      => 'blangko_formulir',
                        'judul_dokumen' => 'Blangko Rekapitulasi Biodata 24 Anggota Kontingen Sekolah (Formulir A.01 Cetak Manual)',
                        'deskripsi'     => 'Format isian cadangan rekapitulasi data 10 Sangga Putra, 10 Sangga Putri, dan 4 Pembina Pendamping untuk keperluan arsip fisik pangkalan.',
                        'file_path'     => 'blangko_rekap_kontingen_formulir_a01.pdf',
                        'file_type'     => 'pdf',
                        'file_size'     => '520 KB',
                        'urutan'        => 4
                    ],
                    [
                        'kategori'      => 'dokumen_lainnya',
                        'judul_dokumen' => 'Tata Tertib Bumi Perkemahan, Denah Tapak Kemah & Rundown Acara Lengkap',
                        'deskripsi'     => 'Peta lokasi kavling perkemahan putra & putri di Kampus UNSA, jadwal kegiatan harian, aturan kebersihan, dan protokol keamanan buper.',
                        'file_path'     => 'tata_tertib_dan_denah_buper_unsa.pdf',
                        'file_type'     => 'pdf',
                        'file_size'     => '1.8 MB',
                        'urutan'        => 5
                    ]
                ];
                $stmtInDoc = $pdo->prepare("INSERT INTO `dokumen_kegiatan` (`kategori`, `judul_dokumen`, `deskripsi`, `file_path`, `file_type`, `file_size`, `urutan`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaultDocs as $d) {
                    $stmtInDoc->execute([$d['kategori'], $d['judul_dokumen'], $d['deskripsi'], $d['file_path'], $d['file_type'], $d['file_size'], $d['urutan']]);
                }
            }

            // 8. Seed Default Settings (Payment, Branding, Logos, Favicon, Mascot)
            $defaultSettings = [
                'biaya_kontingen'     => '500000',
                'bank_nama'           => 'Bank NTB Syariah',
                'bank_rekening'       => '501.02.001928.01',
                'bank_atas_nama'      => 'Racana Universitas Samawa (Siti Rohana)',
                'foto_qris'           => 'qris-racana-unsa.png',
                'favicon'             => 'favicon.png',
                'logo_utama'          => 'logo-racana-unsa.png',
                'logo_kegiatan'       => 'logo-kemah-bakti.png',
                'maskot_kegiatan'     => 'maskot-racana.png',
                'nama_brand'          => 'RACANA UNSA',
                'nama_universitas'    => 'Universitas Samawa',
                'gudep_putra'         => '01.089',
                'gudep_putri'         => '01.090',
                'jam_layanan'         => 'Senin - Sabtu: 08.00 - 21.00 WITA; Minggu: 09.00 - 17.00 WITA',
                'instagram_url'       => '',
                'facebook_url'        => '',
                'youtube_url'         => '',
                'deskripsi_portal'    => 'Pusat informasi kegiatan perkemahan, petunjuk teknis lomba kepramukaan, dan pendaftaran online kontingen pangkalan sekolah.',
                'profil_racana'       => "Racana Universitas Samawa (UNSA) didirikan sebagai wadah pembinaan bagi para pramuka pandega dan penegak di lingkungan perguruan tinggi Universitas Samawa. Berakar pada kebudayaan luhur Sumbawa dan nilai-nilai Tri Satya serta Dasa Darma Pramuka, Racana UNSA terus berkomitmen menjadi pelopor kegiatan kepanduan yang mengintegrasikan pengabdian masyarakat, riset lingkungan, dan kepemimpinan pemuda.\n\nNama racana diambil dari warisan sejarah dan kearifan lokal Tana Samawa, mencerminkan keteguhan budi pekerti, gotong royong (Basiru), dan keberanian membela kebenaran demi kemaslahatan umat dan bangsa.",
                'visi_racana'         => 'Menjadi Gugus Depan Perguruan Tinggi yang unggul, berprestasi, berkarakter, dan berdaya saing di tingkat regional dan nasional berlandaskan kearifan lokal Tana Samawa.',
                'misi_racana'         => "Menyelenggarakan pendidikan kepramukaan yang adaptif, inovatif, dan berwawasan iptek.\nMengembangkan potensi kepemimpinan, kemandirian, dan keterampilan hidup (life skills) anggota.\nMelaksanakan program bakti sosial kemasyarakatan dan pelestarian alam lingkungan hidup secara berkelanjutan.\nMenjalin sinergi dan kemitraan strategis dengan kwartir dan institusi terkait.",
                'nama_maskot'         => 'Si Praja UNSA',
                'deskripsi_maskot'    => 'Simbol ketangguhan, keceriaan, dan persaudaraan penegak.',
                'rundown_kegiatan'    => "Hari Ke-1: Kedatangan dan Upacara Pembukaan | 15 November 2026 | Registrasi ulang, pendirian tenda, dan upacara pembukaan.\nHari Ke-2: Pelaksanaan Kompetisi Sesi I | 16 November 2026 | Lomba PBB, sandi, semaphore, morse, dan pentas seni.\nHari Ke-3: Pelaksanaan Kompetisi Sesi II | 17 November 2026 | Pioneering, cerdas cermat, kuliner rimba, dan api unggun.\nHari Ke-4: Bakti Masyarakat dan Wisata Edukasi | 18 November 2026 | Aksi bakti lingkungan, presentasi teknologi tepat guna, dan temu ramah.\nHari Ke-5: Pengumuman Juara dan Penutupan | 19 November 2026 | Upacara penutupan, pengumuman juara, dan kepulangan kontingen.",
                'petunjuk_pembayaran' => "1. Lakukan pembayaran pendaftaran kontingen sebesar nominal yang tertera melalui Transfer Bank atau scan QRIS resmi.\n2. Simpan struk/tangkapan layar bukti transfer.\n3. Unggah bukti pembayaran pada formulir di bawah ini.\n4. Panitia akan memverifikasi pembayaran Anda dalam 1x24 jam kerja."
            ];
            $stmtSet = $pdo->prepare("INSERT INTO `pengaturan_portal` (`key_name`, `key_value`, `deskripsi`) VALUES (?, ?, 'Pengaturan Portal') ON DUPLICATE KEY UPDATE `key_name`=`key_name`");
            foreach ($defaultSettings as $k => $v) {
                $stmtSet->execute([$k, $v]);
            }

            // 9. Ensure all upload directories exist
            $baseUploadDir = dirname(__DIR__) . '/uploads';
            foreach (['peserta', 'qris', 'pembayaran', 'branding', 'dokumen'] as $sub) {
                $dir = $baseUploadDir . '/' . $sub;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
            }
        } catch (Exception $e) {
            // If table doesn't exist yet, run autoMigrate
            self::autoMigrate($pdo);
        }
    }
}

// Function helper shortcut
function getDB(): PDO {
    return Database::getConnection();
}
