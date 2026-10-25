-- =========================================================================
-- SQL Script: KOSONGKAN DATABASE TRANSAKSIONAL (CLEAN DATA)
-- Portal Kegiatan & Pendaftaran Online Racana Universitas Samawa (UNSA)
-- =========================================================================
-- FUNGSI:
-- Script ini mengosongkan seluruh data transaksional pendaftaran kontingen:
-- 1. Data 24 Orang Peserta & Pembina Pendamping (Tabel `peserta`)
-- 2. Data Partisipasi Mata Lomba Peserta (Tabel `peserta_lomba`)
-- 3. Data Konfirmasi & Verifikasi Pembayaran (Tabel `pembayaran`)
-- 
-- DATA YANG TETAP DIPERTAHANKAN (AMAN & TIDAK DIHAPUS):
-- 1. Tabel `users` (Seluruh akun pengguna Role 'admin' dan Role 'sekolah')
-- 2. Tabel `sekolah` (Profil pangkalan sekolah tetap ada, status pendaftaran direset ke awal)
-- 3. Tabel Master `mata_lomba` (Daftar cabang lomba & juknis lengkap)
-- 4. Tabel Master `kategori_peserta` (Kategori Barisan Putra, Putri & Bindamping)
-- 5. Tabel Master `dokumen_kegiatan` (Berkas Juklak, Juknis, Surat Delegasi, Blangko A.01)
-- 6. Tabel Master `pengaturan_portal` (Konfigurasi branding, rekening transfer & QRIS)
-- 7. Tabel Master `berita_pengumuman` (Publikasi berita & pengumuman resmi)
-- =========================================================================

USE `db_racana_unsa`;

-- Matikan pemeriksaan foreign key sementara untuk operasi truncate
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Kosongkan Tabel Peserta Lomba (Relasi peserta dengan cabang lomba)
TRUNCATE TABLE `peserta_lomba`;

-- 2. Kosongkan Tabel Peserta (Biodata 24 anggota kontingen putra/putri/bindamping)
TRUNCATE TABLE `peserta`;

-- 3. Kosongkan Tabel Pembayaran (Transaksi transfer bank & scan QRIS)
TRUNCATE TABLE `pembayaran`;

-- 4. Reset Status Pendaftaran Seluruh Kontingen Sekolah ke Kondisi Awal
-- (Akun sekolah tetap bisa login dan mengisi ulang peserta dari awal)
UPDATE `sekolah` SET 
  `status_verifikasi` = 'menunggu',
  `status_pembayaran` = 'belum_bayar',
  `status_kunci`      = 'terbuka',
  `finalized_at`      = NULL,
  `kunci_catatan`     = NULL,
  `catatan_verifikasi`= NULL;

-- 5. Pastikan User Default Admin dan User Default Sekolah Tetap Tersedia
-- Akun Admin: username = admin | password = admin123
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `no_hp`, `role`, `status`)
VALUES 
(1, 'admin', '$2y$10$fVf0g01d7pkyJzEti7wBdeWjL0p7u2dO.3lSj25d487qX1QjXzPee', 'Administrator Racana UNSA', 'racana@unsa.ac.id', '081234567890', 'admin', 'aktif')
ON DUPLICATE KEY UPDATE `status` = 'aktif';

-- Hidupkan kembali pemeriksaan foreign key
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- HASIL: Database bersih siap digunakan untuk musim pendaftaran baru!
-- =========================================================================
