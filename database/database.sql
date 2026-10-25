-- =======================================================
-- SQL Database Setup: Portal & Pendaftaran Online
-- Racana Universitas Samawa (UNSA) Sumbawa
-- =======================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABEL USERS (Akun Admin Panitia & Kontingen Sekolah)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `email` VARCHAR(100) NULL,
  `no_hp` VARCHAR(25) NULL,
  `role` ENUM('admin', 'sekolah') NOT NULL DEFAULT 'sekolah',
  `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL SEKOLAH (Profil Kontingen Sekolah)
CREATE TABLE IF NOT EXISTS `sekolah` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `nama_sekolah` VARCHAR(150) NOT NULL,
  `npsn` VARCHAR(20) NULL,
  `alamat_sekolah` TEXT NOT NULL,
  `gudep_putra` VARCHAR(50) DEFAULT '01.001',
  `gudep_putri` VARCHAR(50) DEFAULT '01.002',
  `nama_kamabigus` VARCHAR(100) NULL,
  `nama_pembina` VARCHAR(100) NULL,
  `no_wa_pembina` VARCHAR(25) NULL,
  `email_sekolah` VARCHAR(100) NULL,
  `status_verifikasi` ENUM('menunggu', 'terverifikasi', 'ditolak') NOT NULL DEFAULT 'menunggu',
  `status_pembayaran` ENUM('belum_bayar', 'menunggu_konfirmasi', 'disetujui', 'ditolak') NOT NULL DEFAULT 'belum_bayar',
  `status_kunci` ENUM('terbuka', 'terkunci') NOT NULL DEFAULT 'terbuka',
  `finalized_at` DATETIME NULL,
  `kunci_catatan` VARCHAR(255) NULL,
  `catatan_verifikasi` TEXT NULL,
  `tanggal_daftar` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL KATEGORI PESERTA (Referensi Kategori Barisan & Bindamping)
CREATE TABLE IF NOT EXISTS `kategori_peserta` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_kategori` VARCHAR(30) NOT NULL UNIQUE,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `gender_default` ENUM('L', 'P') NOT NULL,
  `kuota_max` INT NOT NULL DEFAULT 10,
  `keterangan` TEXT NULL,
  `urutan` INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL MATA LOMBA (Referensi Mata Lomba Dinamis & Detail Juknis)
CREATE TABLE IF NOT EXISTS `mata_lomba` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_lomba` VARCHAR(30) NOT NULL UNIQUE,
  `nama_lomba` VARCHAR(150) NOT NULL,
  `kategori_lomba` ENUM('Putra', 'Putri', 'Campuran / Umum') NOT NULL DEFAULT 'Campuran / Umum',
  `max_peserta_per_sekolah` INT NOT NULL DEFAULT 10,
  `deskripsi` TEXT NULL,
  `juknis_singkat` TEXT NULL,
  `juknis_lengkap` TEXT NULL,
  `kriteria_penilaian` TEXT NULL,
  `alat_bahan` TEXT NULL,
  `jadwal_lomba` VARCHAR(100) DEFAULT 'Hari Ke-2 (16 Nov 2026, 08.00 WITA)',
  `penanggung_jawab` VARCHAR(100) DEFAULT 'Panitia Divisi Lomba',
  `kontak_pj` VARCHAR(30) DEFAULT '0852-3891-2345',
  `lokasi_lomba` VARCHAR(100) DEFAULT 'Bumi Perkemahan UNSA',
  `icon_lomba` VARCHAR(50) DEFAULT 'fa-trophy',
  `status_aktif` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL PESERTA (Data Anggota Sangga & Bindamping Max 24 per Kontingen)
CREATE TABLE IF NOT EXISTS `peserta` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sekolah_id` INT NOT NULL,
  `kategori_id` INT NOT NULL,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `nisn_nta` VARCHAR(50) NULL,
  `kelas` VARCHAR(30) NOT NULL,
  `tempat_lahir` VARCHAR(100) NULL,
  `tanggal_lahir` DATE NOT NULL,
  `jenis_kelamin` ENUM('L', 'P') NOT NULL,
  `no_hp` VARCHAR(25) NULL,
  `riwayat_penyakit` VARCHAR(255) NULL,
  `ukuran_kaos` ENUM('S', 'M', 'L', 'XL', 'XXL') DEFAULT 'L',
  `foto` VARCHAR(255) NULL,
  `qr_token` VARCHAR(64) UNIQUE NULL,
  `status` ENUM('aktif', 'draft', 'batal') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`kategori_id`) REFERENCES `kategori_peserta`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL PESERTA_LOMBA (Relasi Multi Mata Lomba yang Diikuti Peserta)
CREATE TABLE IF NOT EXISTS `peserta_lomba` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `peserta_id` INT NOT NULL,
  `lomba_id` INT NOT NULL,
  `nomor_peserta_lomba` VARCHAR(30) NULL,
  `nilai_akhir` DECIMAL(5,2) DEFAULT 0.00,
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_peserta_lomba` (`peserta_id`, `lomba_id`),
  FOREIGN KEY (`peserta_id`) REFERENCES `peserta`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lomba_id`) REFERENCES `mata_lomba`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL PENGATURAN PORTAL (Pengaturan Dinamis Sistem)
CREATE TABLE IF NOT EXISTS `pengaturan_portal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(100) NOT NULL UNIQUE,
  `key_value` TEXT NULL,
  `deskripsi` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABEL BERITA & PENGUMUMAN
CREATE TABLE IF NOT EXISTS `berita_pengumuman` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `kategori` VARCHAR(50) NOT NULL DEFAULT 'Pengumuman',
  `ringkasan` TEXT NULL,
  `isi_konten` TEXT NOT NULL,
  `gambar` VARCHAR(255) NULL,
  `author` VARCHAR(100) DEFAULT 'Dewan Racana UNSA',
  `tgl_post` DATE NOT NULL,
  `is_pinned` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABEL PEMBAYARAN (Konfirmasi Transfer Bank & Scan QRIS Kontingen)
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

SET FOREIGN_KEY_CHECKS = 1;

-- =======================================================
-- DATA AWAL (DEFAULT SEED DATA)
-- =======================================================

-- 1. Akun Admin Default (Username: admin | Password: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `no_hp`, `role`, `status`)
VALUES 
(1, 'admin', '$2y$10$fVf0g01d7pkyJzEti7wBdeWjL0p7u2dO.3lSj25d487qX1QjXzPee', 'Administrator Racana UNSA', 'racana@unsa.ac.id', '081234567890', 'admin', 'aktif')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- 2. Master Kategori Peserta (Kuota awal dapat diubah melalui Pengaturan Admin)
INSERT INTO `kategori_peserta` (`id`, `kode_kategori`, `nama_kategori`, `gender_default`, `kuota_max`, `keterangan`, `urutan`)
VALUES
(1, 'BAR_PUTRA', 'Barisan / Sangga Putra', 'L', 11, 'Anggota Regu/Sangga Penegak Putra', 1),
(2, 'BAR_PUTRI', 'Barisan / Sangga Putri', 'P', 11, 'Anggota Regu/Sangga Penegak Putri', 2),
(3, 'BINDAMPING_PA', 'Pembina PA', 'L', 1, 'Pembina Pendamping Kontingen Putra', 3),
(4, 'BINDAMPING_PI', 'Pembina PI', 'P', 1, 'Pembina Pendamping Kontingen Putri', 4)
ON DUPLICATE KEY UPDATE
  `nama_kategori`=VALUES(`nama_kategori`),
  `gender_default`=VALUES(`gender_default`),
  `keterangan`=VALUES(`keterangan`),
  `urutan`=VALUES(`urutan`);

-- 3. Master Mata Lomba Dinamis dengan Juknis Lengkap & Spesifik
INSERT INTO `mata_lomba` (`id`, `kode_lomba`, `nama_lomba`, `kategori_lomba`, `max_peserta_per_sekolah`, `deskripsi`, `juknis_singkat`, `juknis_lengkap`, `kriteria_penilaian`, `alat_bahan`, `jadwal_lomba`, `penanggung_jawab`, `kontak_pj`, `lokasi_lomba`, `icon_lomba`, `status_aktif`)
VALUES
(1, 'PBB-KREASI', 'PBB & Variasi Formasi Tongkat', 'Campuran / Umum', 10, 
 'Kompetisi keterampilan Peraturan Baris Berbaris (PBB) dasar berpadu variasi formasi kreatif menggunakan tongkat pramuka dan kekompakan hentakan langkah.', 
 'Durasi 8 menit di lapangan ukuran 12x15 meter, berseragam pramuka lengkap dengan atribut kedaerahan.', 
 '1. Setiap pangkalan mengirimkan 1 pleton/regu berkekuatan 9-10 orang termasuk 1 orang Danton (Pemimpin Barisan).\n2. Waktu penampilan maksimal 8 menit dihitung sejak Danton memasuki arena lomba hingga keluar lapangan.\n3. Gerakan wajib meliputi: Sikap sempurna, hormat, lencang kanan/depan, hadap kanan/kiri/serong, balik kanan, langkah tegap, langkah biasa, dan variasi buka-tutup barisan.\n4. Gerakan variasi: Formasi kreasi bebas dengan pemanfaatan tongkat standar 160 cm, diiringi yel-yel bertema kearifan lokal Tana Samawa tanpa unsur SARA.', 
 '1. Ketepatan Aba-aba & Penguasaan Lapangan oleh Danton (25%)\n2. Kebenaran & Ketegasan Gerakan Dasar PBB (30%)\n3. Kreativitas & Estetika Formasi Variasi Tongkat (25%)\n4. Kekompakan, Kerapian Seragam & Kedisiplinan Waktu (20%)', 
 'Tongkat pramuka kayu/bambu standar 160 cm, peluit danton, seragam pramuka lengkap tanda atribut.', 
 'Hari Ke-2 (16 November 2026, 08.00 - 12.00 WITA)', 
 'Kak Ahmad Ridwan, S.Pd', '0852-3891-2345', 'Lapangan Utama Kampus UNSA', 'fa-person-walking', 'Y'),

(2, 'PIONEERING', 'Pioneering Kreasi Miniatur & Menara Pandang', 'Campuran / Umum', 6, 
 'Lomba ketangkasan merakit ikatan dan simpul kepramukaan menjadi miniatur konstruksi jembatan atau menara pandang tepat guna yang kokoh dan estetik.', 
 'Menggunakan 30-40 tongkat bambu/kayu standar dan tali pramuka nilon, waktu perakitan 45 menit tanpa paku/baut.', 
 '1. Peserta terdiri dari 4-6 orang per kontingen pangkalan sekolah.\n2. Waktu pengerjaan konstruksi adalah 45 menit di area kavling berukuran 4x4 meter.\n3. Bangunan minimal memiliki fungsi konstruksi (menara pandang berkaki 3 atau 4, jembatan angkat, atau gapura gerbang perkemahan).\n4. Dilarang menggunakan paku, lem perekat, kawat, atau pemotong mekanik. Hanya diperbolehkan simpul (pangkal, jangkar, palang, silang, canggah).', 
 '1. Kekuatan & Kekokohan Bangunan (30%)\n2. Ketepatan Simpul & Kerapian Anyaman Tali (30%)\n3. Fungsionalitas & Nilai Tepat Guna (20%)\n4. Keindahan Bentuk & Proporsionalitas (20%)', 
 '30-40 tongkat bambu standar (panjang 1.5 - 2m), tali pramuka putih/maron ukuran standar, sarung tangan kain.', 
 'Hari Ke-3 (17 November 2026, 08.00 - 11.30 WITA)', 
 'Kak Dimas Pratama', '0819-0782-9912', 'Bumi Perkemahan Sektor A (Area Lapangan Hijau)', 'fa-network-wired', 'Y'),

(3, 'SEMAPHORE-MORSE', 'Sandi, Semaphore & Morse Berantai', 'Campuran / Umum', 4, 
 'Uji kecepatan dan akurasi penerimaan serta pengiriman sandi isyarat bendera semaphore dan morse peluit/cahaya secara berantai melintasi beberapa pos.', 
 'Isi berita berupa sandi dan teks informasi 50 kata, beregu 4 orang secara estafet pos komunikasi darurat.', 
 '1. Tiap pangkalan diwakili 1 tim terdiri dari 4 orang peserta.\n2. Lomba berbentuk pos berantai (Pos 1: Morse Peluit -> Pos 2: Morse Bendera -> Pos 3: Semaphore -> Pos 4: Penerjemah Sandi Balik).\n3. Pesan rahasia terdiri dari 50 karakter alfanumerik mengenai kebencanaan dan sandi kepramukaan.\n4. Waktu penerimaan dan pencatatan maksimal 15 menit per pos.', 
 '1. Akurasi & Ketepatan Hasil Terjemahan Berita (50%)\n2. Kecepatan Waktu Penyelesaian (30%)\n3. Ketepatan Gerakan & Isyarat Standar Internasional (20%)', 
 '2 set bendera semaphore ukuran 45x45 cm, peluit morse standar, papan dada, lembar jawaban, pulpen.', 
 'Hari Ke-2 (16 November 2026, 13.30 - 17.00 WITA)', 
 'Kak Fitriani, M.Pd', '0878-6541-1120', 'Gedung Rektorat Lt. 2 & Balkon Terbuka', 'fa-flag', 'Y'),

(4, 'CERDAS-CERMAT', 'Cerdas Cermat Kepramukaan & Wawasan Samawa', 'Campuran / Umum', 3, 
 'Kompetisi adu wawasan kepramukaan dunia (WOSM), sejarah kepanduan Indonesia, UU Gerakan Pramuka, serta adat istiadat dan kearifan lokal Tana Samawa.', 
 'Babak penyisihan tes Computer-Based Test (CBT) / tertulis, babak semifinal dan final sistem cerdas cermat rebutan.', 
 '1. Peserta terdiri dari 3 orang per tim kontingen.\n2. Babak 1 (Penyisihan): 100 soal tertulis pilihan ganda dan isian singkat (WOSM 30%, Nasional 40%, Muatan Lokal Samawa 30%).\n3. Enam pangkalan dengan nilai tertinggi melaju ke babak Final Rebutan.\n4. Babak Final: Babak amplop wajib (10 soal) dan babak rebutan (15 soal dengan sistem poin minus jika salah).', 
 '1. Akumulasi Poin Jawaban Benar (Babak Wajib: 100 poin/soal)\n2. Kecepatan & Ketepatan Menjawab Babak Rebutan (+100 benar, -50 salah)\n3. Ketertiban dan Sportivitas Tim', 
 'Alat tulis, papan skor elektronik/buzzer tombol bel, modul soal dari dewan juri.', 
 'Hari Ke-3 (17 November 2026, 13.00 - 15.30 WITA)', 
 'Kak Syahrul Maulana, M.Si', '0812-3900-8811', 'Aula Utama Sultan Muhammad Kaharuddin UNSA', 'fa-brain', 'Y'),

(5, 'PENTAS-SENI', 'Pentas Seni Budaya Samawa (Kearifan Lokal)', 'Campuran / Umum', 12, 
 'Pagelaran ekspresi kreasi tari tradisional, musik tradisi Sakai, teater rakyat, atau vokal grup bernuansa adat Sumbawa berpadu jiwa kepanduan penegak.', 
 'Durasi penampilan maksimal 10 menit di panggung terbuka Buper UNSA, menggunakan instrumen akustik/tradisional.', 
 '1. Diikuti oleh 6 - 12 orang per kontingen pangkalan sekolah.\n2. Menampilkan karya seni tradisi (Tari Nguri, Tari Bareng Sere, Rabalas Lawas, Sakeco, atau Musik Tradisi Samawa Modern).\n3. Tata rias dan busana mencerminkan busana adat Sumbawa yang sopan dan anggun.\n4. Tidak diperkenankan memutar rekaman playback lipsync penuh (wajib live performance).', 
 '1. Orisinalitas & Keselarasan Tema Budaya Samawa (30%)\n2. Harmonisasi Irama, Wiraga, & Wirama (30%)\n3. Penjiwaan, Ekspresi Panggung, & Dinamika (25%)\n4. Kerapian Tata Busana Adat & Properti (15%)', 
 'Panggung seni terbuka 10x8 meter, sound system 5000 watt, lighting panggung, alat musik tradisional bawaan peserta.', 
 'Hari Ke-2 & 3 (16-17 November 2026, 19.30 - 22.00 WITA)', 
 'Kak Nurul Hidayah, S.Sn', '0853-3321-7788', 'Panggung Terbuka Seni Budaya UNSA', 'fa-masks-theater', 'Y'),

(6, 'TTG-PRAMUKA', 'Teknologi Tepat Guna (TTG) Lingkungan Hidup', 'Campuran / Umum', 3, 
 'Ajang inovasi rekayasa alat sederhana ramah lingkungan untuk menjawab tantangan krisis air, pengolahan sampah organik, atau energi terbarukan di bumi perkemahan.', 
 'Presentasi prototipe alat dan demonstrasi fungsi di depan panelis dewan juri akademik UNSA.', 
 '1. Peserta terdiri dari 2-3 orang per kontingen.\n2. Prototipe karya alat dibawa ke lokasi pameran teknologi inovasi buper.\n3. Karya harus memanfaatkan minimal 50% material lokal atau bahan daur ulang (low cost & sustainable).\n4. Waktu presentasi karya 7 menit dilanjutkan sesi tanya jawab dewan juri 5 menit.', 
 '1. Nilai Orisinalitas & Kebaruan Gagasan (30%)\n2. Manfaat Aplikatif & Keberlanjutan Lingkungan (35%)\n3. Kualitas Presentasi & Penguasaan Materi (20%)\n4. Efisiensi Biaya & Pemilihan Material (15%)', 
 'Meja display stand pameran 2x2 meter, lembar poster deskripsi karya (ukuran A1), stop kontak listrik 220V.', 
 'Hari Ke-4 (18 November 2026, 13.30 - 16.30 WITA)', 
 'Kak Ir. Indra Gunawan, M.T', '0813-3987-4433', 'Laboratorium Terpadu & Stand Pameran TTG UNSA', 'fa-lightbulb', 'Y'),

(7, 'KULINER-RIMBA', 'Master Chef Kuliner Rimba (Survival Cooking)', 'Campuran / Umum', 3, 
 'Lomba meracik hidangan masakan darurat bergizi tinggi dari bahan alam hutan dan rempah tradisional khas Tana Samawa dengan metode survival perkemahan.', 
 'Waktu memasak 60 menit menggunakan peralatan alam/nesting dan kompor parafin/tungku kayu tanpa penyedap kimia sintetis.', 
 '1. Tiap kontingen diwakili oleh 2 - 3 orang peserta (campuran/putra/putri).\n2. Peserta mengolah 1 menu utama (makanan survival hutan) dan 1 minuman hangat herbal pemulih stamina rimba.\n3. Dilarang menggunakan bumbu instan/MSG sachet komersial, bumbu wajib diracik sendiri dari rempah alami.\n4. Penyajian wajib menggunakan wadah alami (daun pisang, batok kelapa, bambu).', 
 '1. Cita Rasa & Kematangan Masakan (35%)\n2. Kandungan Gizi & Kreativitas Bahan Survival Alam (25%)\n3. Kebersihan, Higienitas, & Manajemen Sampah Masak (20%)\n4. Estetika Penyajian Alami Tradisional (20%)', 
 'Nesting masak TNI/Pramuka, kompor lapangan/parafin/arang, bahan mentah sayur & lauk segar, pisau lipat.', 
 'Hari Ke-3 (17 November 2026, 15.30 - 17.30 WITA)', 
 'Kak Baiq Fatimah, S.Pd', '0877-5432-1199', 'Area Dapur Umum Rimba Buper UNSA', 'fa-fire-burner', 'Y'),

(8, 'POSTER-DIGITAL', 'Desain Poster & Fotografi Jurnalistik Kemah', 'Campuran / Umum', 2, 
 'Lomba karya visual grafis dan tangkapan momen inspiratif aksi bakti kepramukaan penegak bagi kemajuan generasi muda dan kelestarian Tana Samawa.', 
 'Karya orisinil dibuat langsung selama perkemahan, tanpa rekayasa AI generator, resolusi tinggi.', 
 '1. Peserta terdiri dari 1-2 orang per pangkalan sekolah.\n2. Kategori A: Poster Digital Kampanye Sosial Lingkungan (Ukuran A3, 300 DPI).\n3. Kategori B: Foto Cerita Jurnalistik Kemah (Seri 3-5 foto dokumenter human interest di Buper UNSA).\n4. Seluruh karya diunggah ke portal media center lomba sebelum batas waktu penutupan lomba.', 
 '1. Kesesuaian Pesan dengan Tema Kemah Bakti (30%)\n2. Komposisi Visual, Tata Warna, & Typografi (30%)\n3. Nilai Edukasi, Daya Gugah, & Orisinalitas (25%)\n4. Kualitas Resolusi & Keterangan Caption Jurnalistik (15%)', 
 'Kamera digital/smartphone kamera resolusi tinggi, laptop editor (Photoshop/Canva/Illustrator).', 
 'Hari Ke-1 s/d Ke-4 (Pengumpulan Final 18 Nov 2026, 12.00 WITA)', 
 'Kak Fajar Maulana, S.Kom', '0852-3712-4455', 'Media Center Sekretariat Racana UNSA', 'fa-camera-retro', 'Y')
ON DUPLICATE KEY UPDATE 
  `nama_lomba`=VALUES(`nama_lomba`),
  `deskripsi`=VALUES(`deskripsi`),
  `juknis_singkat`=VALUES(`juknis_singkat`),
  `juknis_lengkap`=VALUES(`juknis_lengkap`),
  `kriteria_penilaian`=VALUES(`kriteria_penilaian`),
  `alat_bahan`=VALUES(`alat_bahan`),
  `jadwal_lomba`=VALUES(`jadwal_lomba`),
  `penanggung_jawab`=VALUES(`penanggung_jawab`),
  `kontak_pj`=VALUES(`kontak_pj`),
  `lokasi_lomba`=VALUES(`lokasi_lomba`),
  `icon_lomba`=VALUES(`icon_lomba`);

-- 4. Pengaturan Default Portal
INSERT INTO `pengaturan_portal` (`key_name`, `key_value`, `deskripsi`)
VALUES
('nama_acara', 'Kemah Bakti & Lomba Pramuka Penegak se-Nusa Tenggara Barat 2026', 'Nama Agenda Utama Racana'),
('tema_acara', 'Satyaku Kudarmakan, Darmaku Kubaktikan Demi Kejayaan Tana Samawa', 'Tema Kegiatan'),
('nama_racana', 'Racana Universitas Samawa (Gudep 01.089 - 01.090)', 'Nama Lembaga Racana'),
('nama_brand', 'RACANA UNSA', 'Nama singkat branding portal'),
('nama_universitas', 'Universitas Samawa', 'Nama perguruan tinggi atau pangkalan'),
('gudep_putra', '01.089', 'Nomor Gugus Depan Putra'),
('gudep_putri', '01.090', 'Nomor Gugus Depan Putri'),
('alamat_racana', 'Jl. By Pass Sering, Kerato, Kec. Unter Iwes, Kabupaten Sumbawa, NTB 84316', 'Alamat Sekretariat'),
('kontak_wa', '0852-3891-2345 (Dewan Racana UNSA)', 'Nomor Helpdesk WhatsApp'),
('email_racana', 'racana@unsa.ac.id', 'Email Resmi Racana UNSA'),
('jam_layanan', 'Senin - Sabtu: 08.00 - 21.00 WITA; Minggu: 09.00 - 17.00 WITA', 'Jam layanan panitia'),
('instagram_url', '', 'Tautan Instagram resmi'),
('facebook_url', '', 'Tautan Facebook resmi'),
('youtube_url', '', 'Tautan YouTube resmi'),
('deskripsi_portal', 'Pusat informasi kegiatan perkemahan, petunjuk teknis lomba kepramukaan, dan pendaftaran online kontingen pangkalan sekolah.', 'Deskripsi portal dan tagline'),
('profil_racana', 'Racana Universitas Samawa (UNSA) didirikan sebagai wadah pembinaan bagi para pramuka pandega dan penegak di lingkungan perguruan tinggi Universitas Samawa. Berakar pada kebudayaan luhur Sumbawa dan nilai-nilai Tri Satya serta Dasa Darma Pramuka, Racana UNSA terus berkomitmen menjadi pelopor kegiatan kepanduan yang mengintegrasikan pengabdian masyarakat, riset lingkungan, dan kepemimpinan pemuda.\n\nNama racana diambil dari warisan sejarah dan kearifan lokal Tana Samawa, mencerminkan keteguhan budi pekerti, gotong royong (Basiru), dan keberanian membela kebenaran demi kemaslahatan umat dan bangsa.', 'Profil singkat lembaga'),
('visi_racana', 'Menjadi Gugus Depan Perguruan Tinggi yang unggul, berprestasi, berkarakter, dan berdaya saing di tingkat regional dan nasional berlandaskan kearifan lokal Tana Samawa.', 'Visi Racana'),
('misi_racana', 'Menyelenggarakan pendidikan kepramukaan yang adaptif, inovatif, dan berwawasan iptek.\nMengembangkan potensi kepemimpinan, kemandirian, dan keterampilan hidup (life skills) anggota.\nMelaksanakan program bakti sosial kemasyarakatan dan pelestarian alam lingkungan hidup secara berkelanjutan.\nMenjalin sinergi dan kemitraan strategis dengan kwartir dan institusi terkait.', 'Misi Racana, satu poin per baris'),
('nama_maskot', 'Si Praja UNSA', 'Nama maskot lembaga/kegiatan'),
('deskripsi_maskot', 'Simbol ketangguhan, keceriaan, dan persaudaraan penegak.', 'Deskripsi maskot'),
('rundown_kegiatan', 'Hari Ke-1: Kedatangan dan Upacara Pembukaan | 15 November 2026 | Registrasi ulang, pendirian tenda, dan upacara pembukaan.\nHari Ke-2: Pelaksanaan Kompetisi Sesi I | 16 November 2026 | Lomba PBB, sandi, semaphore, morse, dan pentas seni.\nHari Ke-3: Pelaksanaan Kompetisi Sesi II | 17 November 2026 | Pioneering, cerdas cermat, kuliner rimba, dan api unggun.\nHari Ke-4: Bakti Masyarakat dan Wisata Edukasi | 18 November 2026 | Aksi bakti lingkungan, presentasi teknologi tepat guna, dan temu ramah.\nHari Ke-5: Pengumuman Juara dan Penutupan | 19 November 2026 | Upacara penutupan, pengumuman juara, dan kepulangan kontingen.', 'Rundown kegiatan, satu agenda per baris'),
('kuota_per_kontingen', '24', 'Maksimal total peserta & bindamping per kontingen'),
('status_pendaftaran', 'BUKA', 'Status Pendaftaran: BUKA / DITUTUP'),
('tgl_mulai_daftar', '2026-08-01', 'Tanggal Buka Pendaftaran'),
('tgl_selesai_daftar', '2026-10-31', 'Tanggal Tutup Pendaftaran'),
('tgl_pelaksanaan_kemah', '15 - 19 November 2026', 'Jadwal Pelaksanaan Perkemahan & Lomba'),
('lokasi_kegiatan', 'Bumi Perkemahan Kampus Universitas Samawa (UNSA), Sumbawa Besar', 'Tempat Kegiatan')
ON DUPLICATE KEY UPDATE `key_name`=`key_name`;

-- 5. Berita & Pengumuman Awal
INSERT INTO `berita_pengumuman` (`judul`, `slug`, `kategori`, `ringkasan`, `isi_konten`, `gambar`, `author`, `tgl_post`, `is_pinned`)
VALUES
(
  'Selamat Datang di Portal Resmi Pendaftaran Online Racana Universitas Samawa 2026',
  'selamat-datang-portal-resmi-racana-unsa-2026',
  'Pengumuman',
  'Pendaftaran Kontingen Kemah Bakti & Lomba Pramuka Penegak se-NTB di Kampus Universitas Samawa resmi dibuka.',
  'Salam Pramuka!\n\nDengan penuh semangat kepanduan dan persaudaraan bakti, Dewan Racana Universitas Samawa (Gudep 01.089 - 01.090 Pangkalan Universitas Samawa) mengumumkan pembukaan Pendaftaran Online Kontingen untuk kegiatan "Kemah Bakti & Lomba Pramuka Penegak se-Nusa Tenggara Barat Tahun 2026".\n\nKegiatan akbar ini akan dipusatkan di Bumi Perkemahan Kampus Universitas Samawa (UNSA), Sumbawa Besar pada tanggal 15 s/d 19 November 2026.\n\nSetiap pangkalan Gugus Depan SMA / SMK / MA / Sederajat berhak mendaftarkan satu kontingen resmi sesuai komposisi dan kuota kategori yang tercantum pada Petunjuk Teknis.\n\nSilakan segera membuat akun pangkalan sekolah Anda pada menu Registrasi Akun Sekolah untuk mengamankan kuota kontingen.',
  'banner-pengumuman.jpg',
  'Dewan Racana UNSA',
  '2026-09-01',
  'Y'
),
(
  'Petunjuk Teknis & Ketentuan Upload Foto ID Card Peserta',
  'juknis-dan-ketentuan-foto-id-card-peserta',
  'Juknis',
  'Panduan penting tata cara pengambilan dan pengunggahan pas foto peserta untuk pencetakan Kartu Tanda Peserta resmi.',
  'Diberitahukan kepada seluruh Pembina Pendamping dan Pimpinan Kontingen,\n\nPas foto peserta yang diunggah pada formulir pendaftaran online akan dicetak langsung pada ID Card resmi kegiatan dengan sistem verifikasi barcode digital.\n\nKetentuan Pas Foto:\n1. Menggunakan seragam pramuka lengkap atau pakaian berkerah rapi.\n2. Posisi badan tegak setengah badan dengan wajah menghadap ke depan jelas.\n3. Latar belakang (background) foto disarankan warna polos (merah/biru).\n4. Format file JPG, JPEG, PNG, atau WEBP dengan ukuran maksimal 3 MB.\n\nPastikan foto yang diunggah memenuhi kriteria di atas agar pencetakan ID Card peserta tidak mengalami kendala teknis.',
  'banner-juknis.jpg',
  'Divisi Kesekretariatan',
  '2026-09-10',
  'N'
),
(
  'Jadwal Technical Meeting (TM) Daring & Panduan Lokasi Buper',
  'jadwal-technical-meeting-daring-dan-panduan-buper',
  'Agenda',
  'Pelaksanaan Technical Meeting (TM) Daring untuk seluruh Pembina Pendamping dan Pimpinan Kontingen.',
  'Salam Pramuka!\n\nUntuk menyamakan persepsi mengenai teknis perlombaan, pembagian kavling tenda, serta tata tertib adat perkemahan, Panitia Racana UNSA akan menyelenggarakan:\n\nAgenda: Technical Meeting (TM) Daring\nHari/Tanggal: Sabtu, 5 November 2026\nWaktu: 09.00 - 12.00 WITA\nMedia: Zoom Meeting & Live YouTube Racana UNSA\n\nTautan meeting virtual akan dikirimkan langsung ke nomor WhatsApp Pembina yang terdaftar pada sistem pendaftaran portal kontingen.',
  'banner-tm.jpg',
  'Divisi Acara & Perlombaan',
  '2026-09-20',
  'N'
)
ON DUPLICATE KEY UPDATE `slug`=`slug`;
