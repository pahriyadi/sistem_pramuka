<?php
/**
 * Application Core Helpers & Global Settings
 * Racana Universitas Samawa (UNSA)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Route Mapping: Disguised clean URLs (Masking system - No .php and masked paths)
function get_route_map(): array {
    return [
        // Public pages
        'index.php'                     => 'beranda',
        'tentang.php'                   => 'profil-racana',
        'lomba.php'                     => 'mata-kompetisi',
        'lomba-detail.php'              => 'rincian-lomba',
        'petunjuk-teknis.php'           => 'panduan-teknis',
        'unduh-dokumen.php'             => 'unduh-berkas',
        'berita.php'                    => 'warta-berita',
        'berita-detail.php'             => 'baca-warta',
        'kontak.php'                    => 'layanan-bantuan',
        'install.php'                   => 'sistem-setup',

        // Auth pages
        'auth/login.php'                => 'masuk-portal',
        'auth/register.php'             => 'registrasi-gudep',
        'auth/logout.php'               => 'keluar-portal',

        // Dashboard (Sekolah) pages
        'dashboard/index.php'           => 'portal/ringkasan',
        'dashboard/peserta.php'         => 'portal/data-anggota',
        'dashboard/peserta-tambah.php'  => 'portal/registrasi-kolektif',
        'dashboard/peserta-edit.php'    => 'portal/perbarui-anggota',
        'dashboard/peserta-hapus.php'   => 'portal/hapus-anggota',
        'dashboard/pembayaran.php'      => 'portal/konfirmasi-kas',
        'dashboard/sekolah.php'         => 'portal/profil-gudep',
        'dashboard/dokumen.php'         => 'portal/arsip-berkas',
        'dashboard/jadwal-kegiatan.php' => 'portal/rundown-acara',
        'dashboard/cetak-idcard.php'    => 'portal/kartu-tanda-peserta',
        'dashboard/cetak-formulir.php'  => 'portal/lembar-biodata',

        // Admin pages
        'admin/index.php'               => 'panel/pusat-kendali',
        'admin/kontingen.php'           => 'panel/kontingen-pangkalan',
        'admin/kontingen-detail.php'    => 'panel/rincian-pangkalan',
        'admin/peserta-all.php'         => 'panel/master-peserta',
        'admin/mata-lomba.php'          => 'panel/jadwal-kompetisi',
        'admin/pembayaran.php'          => 'panel/validasi-transaksi',
        'admin/dokumen.php'             => 'panel/kelola-arsip',
        'admin/berita.php'              => 'panel/publikasi-warta',
        'admin/users.php'               => 'panel/otoritas-pengguna',
        'admin/pengaturan.php'          => 'panel/preferensi-sistem',
        'admin/cetak-rekap.php'         => 'panel/rekapitulasi-cetak',
    ];
}

// Helper: Convert physical file path to disguised clean route without .php
function resolve_masked_path(string $path): string {
    $path = ltrim($path, '/');
    if ($path === '') {
        return '';
    }
    
    // Extract query string if any
    $parts = explode('?', $path, 2);
    $clean = $parts[0];
    $query = isset($parts[1]) ? '?' . $parts[1] : '';

    // Ignore assets, uploads, and static resource folders
    if (strncmp($clean, 'assets/', 7) === 0 || strncmp($clean, 'uploads/', 8) === 0) {
        return $path;
    }

    $map = get_route_map();
    if (isset($map[$clean])) {
        $clean = $map[$clean];
    } else {
        // If ends with .php, remove extension
        $len = strlen($clean);
        if ($len >= 4 && substr($clean, -4) === '.php') {
            $clean = substr($clean, 0, -4);
        }
    }

    return $clean . $query;
}

// Helper: Accurately resolve project root relative to web server document root (Supports Windows XAMPP & Linux Hosting)
function get_project_root(): string {
    // 1. Try comparing DOCUMENT_ROOT with application root directory (case-insensitive for Windows)
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    
    if (!empty($docRoot) && !empty($appRoot)) {
        $docRoot = rtrim($docRoot, '/');
        $appRoot = rtrim($appRoot, '/');
        
        // Case-insensitive comparison for Windows drive letters & Linux paths
        if (stripos($appRoot, $docRoot) === 0) {
            $rel = substr($appRoot, strlen($docRoot));
            $cleanRel = '/' . trim($rel, '/');
            if ($cleanRel !== '/') {
                return $cleanRel;
            }
            return '';
        }
    }
    
    // 2. Fallback using SCRIPT_NAME and app folder name (for local XAMPP subdirectories)
    $appFolderName = basename(dirname(__DIR__));
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    
    if (!empty($appFolderName) && !empty($scriptName)) {
        $needle = '/' . $appFolderName . '/';
        $pos = stripos($scriptName, $needle);
        if ($pos !== false) {
            return substr($scriptName, 0, $pos + strlen($needle) - 1);
        }
        if (strcasecmp($scriptName, '/' . $appFolderName) === 0) {
            return '/' . $appFolderName;
        }
    }
    
    return '';
}

// Dynamically determine Base URL with automatic route masking (Supports Root Domain, Subdomain, & Subdirectory)
function base_url(string $path = ''): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $projectRoot = get_project_root();
    $url = $protocol . $host . $projectRoot;
    $masked = resolve_masked_path($path);
    return rtrim($url, '/') . ($masked !== '' ? '/' . $masked : '');
}

// Helper for named route generation
function route_url(string $name, array $params = []): string {
    $map = get_route_map();
    $target = $map[$name] ?? $name;
    if (strlen($target) >= 4 && substr($target, -4) === '.php') {
        $target = substr($target, 0, -4);
    }
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    return base_url($target . $query);
}

// Helper for assets
function asset(string $path): string {
    return base_url('assets/' . ltrim($path, '/'));
}

// XSS Sanitizer for display
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Protection Token
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Flash Message Alerts
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Accurate Age Calculation Function
function hitung_umur(string $tanggal_lahir): array {
    if (empty($tanggal_lahir) || $tanggal_lahir === '0000-00-00') {
        return ['tahun' => 0, 'bulan' => 0, 'hari' => 0, 'teks' => '-'];
    }
    try {
        $bday = new DateTime($tanggal_lahir);
        $today = new DateTime('today');
        $diff = $today->diff($bday);
        return [
            'tahun' => $diff->y,
            'bulan' => $diff->m,
            'hari'  => $diff->d,
            'teks'  => "{$diff->y} Thn {$diff->m} Bln"
        ];
    } catch (Exception $e) {
        return ['tahun' => 0, 'bulan' => 0, 'hari' => 0, 'teks' => '-'];
    }
}

// Indonesian Date Formatter
function format_tanggal_indonesia(?string $date): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date);
    $hari = date('d', $timestamp);
    $bulan = $bulanIndo[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    return "$hari $bulan $tahun";
}

// Get Setting from Database
function get_setting(string $key, string $default = ''): string {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT key_value FROM pengaturan_portal WHERE key_name = ? LIMIT 1");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return $res ? $res['key_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }

}

function get_whatsapp_number(): string {
    $number = preg_replace('/\D+/', '', get_setting('kontak_wa', '0852-3891-2345'));
    if (strncmp($number, '0', 1) === 0) {
        return '62' . substr($number, 1);
    }
    if (strncmp($number, '8', 1) === 0) {
        return '62' . $number;
    }
    return $number;
}

// Format Currency IDR (Rupiah)
function format_rupiah($nominal): string {
    $num = is_numeric($nominal) ? (float)$nominal : 0;
    return 'Rp ' . number_format($num, 0, ',', '.');
}

// Generate unique payment code
function generate_kode_bayar(int $sekolahId): string {
    return 'PAY-' . date('Ymd') . '-' . str_pad((string)$sekolahId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -4));
}

// Secure Image / Photo Upload Handler for Peserta
function upload_foto_peserta(array $file, string $prefix = 'peserta_'): array {
    $uploadDir = dirname(__DIR__) . '/uploads/peserta/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah file. Kode: ' . $file['error']];
    }

    // Max 3MB
    $maxSize = 3 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran foto maksimal adalah 3 MB.'];
    }

    // MIME and Extension Whitelist
    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        return ['status' => false, 'message' => 'Format file tidak didukung. Harap upload format JPG, JPEG, PNG, atau WEBP.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $cleanExt = strtolower($ext);
    if (!in_array($cleanExt, ['jpg', 'jpeg', 'png', 'webp'])) {
        $cleanExt = 'jpg';
    }

    $fileName = $prefix . uniqid() . '_' . time() . '.' . $cleanExt;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filename' => $fileName];
    }

    return ['status' => false, 'message' => 'Gagal memindahkan file yang diunggah ke direktori tujuan.'];
}

// Secure Payment Proof Upload Handler (Struk Transfer / Bukti QRIS)
function upload_bukti_pembayaran(array $file, string $prefix = 'bukti_bayar_'): array {
    $uploadDir = dirname(__DIR__) . '/uploads/pembayaran/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah berkas bukti bayar. Kode: ' . $file['error']];
    }

    // Max 4MB
    $maxSize = 4 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran file bukti pembayaran maksimal adalah 4 MB.'];
    }

    // MIME and Extension Whitelist (Images + PDF)
    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        return ['status' => false, 'message' => 'Format file tidak didukung. Harap upload gambar (JPG, PNG, WEBP) atau dokumen PDF.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $cleanExt = strtolower($ext);
    if (!in_array($cleanExt, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
        $cleanExt = ($mime === 'application/pdf') ? 'pdf' : 'jpg';
    }

    $fileName = $prefix . uniqid() . '_' . time() . '.' . $cleanExt;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filename' => $fileName, 'mime' => $mime];
    }

    return ['status' => false, 'message' => 'Gagal menyimpan bukti transfer ke server.'];
}

// Secure QRIS Image Upload Handler (Admin Config)
function upload_foto_qris(array $file): array {
    $uploadDir = dirname(__DIR__) . '/uploads/qris/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah foto QRIS. Kode: ' . $file['error']];
    }

    // Max 3MB
    $maxSize = 3 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran foto QRIS maksimal adalah 3 MB.'];
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) {
        return ['status' => false, 'message' => 'Format foto QRIS harus gambar (JPG, PNG, atau WEBP).'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $cleanExt = strtolower($ext);
    if (!in_array($cleanExt, ['jpg', 'jpeg', 'png', 'webp'])) {
        $cleanExt = 'png';
    }

    $fileName = 'qris_racana_' . time() . '.' . $cleanExt;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filename' => $fileName];
    }

    return ['status' => false, 'message' => 'Gagal menyimpan foto QRIS ke folder uploads.'];
}

// Secure Branding File Upload Handler (Favicon, Logo Utama, Logo Kegiatan, Maskot)
function upload_branding_file(array $file, string $type = 'logo'): array {
    $uploadDir = dirname(__DIR__) . '/uploads/branding/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah berkas branding. Kode: ' . $file['error']];
    }

    // Max 5MB
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran berkas gambar maksimal adalah 5 MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'];
    if (!in_array($ext, $allowedExts)) {
        return ['status' => false, 'message' => 'Format file tidak didukung. Harap upload format PNG, JPG, WEBP, ICO, atau SVG.'];
    }

    $fileName = $type . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filename' => $fileName];
    }

    return ['status' => false, 'message' => 'Gagal menyimpan berkas branding ke server.'];
}

// Secure News & Announcement Banner Image Upload Handler
function upload_banner_berita(array $file): array {
    $uploadDir = dirname(__DIR__) . '/uploads/berita/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah cover/banner berita. Kode: ' . $file['error']];
    }

    // Max 5MB
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran gambar cover berita maksimal adalah 5 MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        return ['status' => false, 'message' => 'Format gambar tidak didukung. Harap upload format PNG, JPG, JPEG, atau WEBP.'];
    }

    $fileName = 'berita_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['status' => true, 'filename' => $fileName];
    }

    return ['status' => false, 'message' => 'Gagal menyimpan gambar cover berita ke server.'];
}

// Helper: Get News Banner URL with dynamic fallback
function get_berita_banner_url(?string $imageName): string {
    if (!empty($imageName) && file_exists(dirname(__DIR__) . '/uploads/berita/' . $imageName)) {
        return base_url('uploads/berita/' . $imageName);
    }
    return '';
}

// Secure Activity Documents Upload Handler (Juklak, Juknis, Surat Delegasi, Form Izin)
function upload_dokumen_kegiatan(array $file, string $prefix = 'dokumen_'): array {
    $uploadDir = dirname(__DIR__) . '/uploads/dokumen/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Terjadi kesalahan saat mengunggah dokumen. Kode: ' . $file['error']];
    }

    // Max 25MB
    $maxSize = 25 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran dokumen maksimal adalah 25 MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'doc', 'docx', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx'];
    if (!in_array($ext, $allowedExts)) {
        return ['status' => false, 'message' => 'Format dokumen tidak didukung. Harap upload format PDF, DOC, DOCX, XLS, XLSX, atau ZIP.'];
    }

    // Clean original name without special characters
    $originalBaseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalBaseName);
    $cleanName = substr($cleanName, 0, 40);

    $fileName = $prefix . $cleanName . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $formattedSize = format_bytes($file['size']);
        return [
            'status'   => true, 
            'filename' => $fileName, 
            'file_type'=> $ext,
            'file_size'=> $formattedSize
        ];
    }

    return ['status' => false, 'message' => 'Gagal menyimpan dokumen ke direktori uploads.'];
}

// Helper: Format Bytes to Human Readable Size
function format_bytes(int $bytes, int $precision = 1): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Helper: Get Favicon URL (Static or Uploaded)
function get_favicon_url(): string {
    $fav = get_setting('favicon', '');
    if (!empty($fav) && file_exists(dirname(__DIR__) . '/uploads/branding/' . $fav)) {
        return base_url('uploads/branding/' . $fav);
    }
    if (file_exists(dirname(__DIR__) . '/assets/img/favicon.svg')) {
        return base_url('assets/img/favicon.svg');
    }
    return base_url('assets/img/favicon.png');
}

// Helper: Get Logo Utama Racana URL
function get_logo_url(): string {
    $logo = get_setting('logo_utama', '');
    if (!empty($logo) && file_exists(dirname(__DIR__) . '/uploads/branding/' . $logo)) {
        return base_url('uploads/branding/' . $logo);
    }
    if (file_exists(dirname(__DIR__) . '/assets/img/logo-racana.svg')) {
        return base_url('assets/img/logo-racana.svg');
    }
    return base_url('assets/img/logo-racana.png');
}

// Helper: Get Logo Kegiatan URL
function get_logo_kegiatan_url(): string {
    $logoK = get_setting('logo_kegiatan', '');
    if (!empty($logoK) && file_exists(dirname(__DIR__) . '/uploads/branding/' . $logoK)) {
        return base_url('uploads/branding/' . $logoK);
    }
    if (file_exists(dirname(__DIR__) . '/assets/img/logo-kegiatan.svg')) {
        return base_url('assets/img/logo-kegiatan.svg');
    }
    return base_url('assets/img/logo-kegiatan.png');
}

// Helper: Get Maskot Kegiatan URL
function get_maskot_url(): string {
    $maskot = get_setting('maskot_kegiatan', '');
    if (!empty($maskot) && file_exists(dirname(__DIR__) . '/uploads/branding/' . $maskot)) {
        return base_url('uploads/branding/' . $maskot);
    }
    if (file_exists(dirname(__DIR__) . '/assets/img/maskot-racana.svg')) {
        return base_url('assets/img/maskot-racana.svg');
    }
    return base_url('assets/img/maskot-racana.png');
}

// Helper: Fetch List of Active Activity Documents
function get_dokumen_kegiatan_list(string $kategori = '', bool $onlyActive = true): array {
    try {
        $pdo = getDB();
        $sql = "SELECT * FROM dokumen_kegiatan WHERE 1=1";
        $params = [];
        if ($onlyActive) {
            $sql .= " AND status_aktif = 'Y'";
        }
        if (!empty($kategori)) {
            $sql .= " AND kategori = ?";
            $params[] = $kategori;
        }
        $sql .= " ORDER BY urutan ASC, id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Helper: Check if a Participant Registration Field is Enabled/Displayed
function is_field_enabled(string $field): bool {
    // Default configuration:
    // field_ukuran_kaos defaults to 'N' as requested by the user
    $defaults = [
        'ukuran_kaos'      => 'N',
        'nisn_nta'         => 'Y',
        'riwayat_penyakit' => 'Y',
        'tempat_lahir'     => 'Y',
        'no_hp'            => 'Y',
        'foto'             => 'Y',
    ];
    $val = get_setting('field_' . $field, $defaults[$field] ?? 'Y');
    return ($val === 'Y' || $val === '1' || $val === 'true');
}

// Helper: Get Default T-Shirt Size when hidden
function get_default_ukuran_kaos(): string {
    $size = get_setting('default_ukuran_kaos', 'L');
    return in_array($size, ['S', 'M', 'L', 'XL', 'XXL']) ? $size : 'L';
}

// Canonical URL Redirect: Automatically disguise any direct .php request in browser address bar
function enforce_masked_url_redirect(): void {
    if (PHP_SAPI === 'cli' || empty($_SERVER['REQUEST_URI'])) {
        return;
    }
    
    // Only handle GET and HEAD requests to prevent interrupting POST forms
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'];
    $parsed = parse_url($uri, PHP_URL_PATH);
    if (!$parsed) {
        return;
    }

    // Check if the requested path directly contains '.php'
    if (strpos($parsed, '.php') !== false) {
        $projectRoot = get_project_root();
        
        // Remove projectRoot from parsed path to get relative script path
        $relPath = $parsed;
        if ($projectRoot !== '' && strncmp($relPath, $projectRoot, strlen($projectRoot)) === 0) {
            $relPath = substr($relPath, strlen($projectRoot));
        }
        $relPath = ltrim($relPath, '/');

        // Check if there is a query string
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $fullRel = $relPath . (!empty($queryString) ? '?' . $queryString : '');

        $masked = resolve_masked_path($fullRel);
        // If the path was transformed and no longer contains .php
        if (strpos($masked, '.php') === false && $masked !== $relPath) {
            $targetUrl = base_url($masked);
            header("Location: " . $targetUrl, true, 301);
            exit;
        }
    }
}

// Automatically enforce clean masked URLs
enforce_masked_url_redirect();

