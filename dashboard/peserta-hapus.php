<?php
/**
 * Hapus Peserta Kontingen
 * Racana Universitas Samawa (UNSA)
 */
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
    set_flash('warning', 'Data pendaftaran kontingen telah <strong>DIFINALISASI &amp; DIKUNCI</strong>. Anda tidak dapat menghapus peserta kecuali Admin Panitia membuka kunci akses.');
    header('Location: ' . base_url('dashboard/peserta.php'));
    exit;
}

if ($pesertaId > 0) {
    try {
        // Fetch to delete photo file
        $stmtP = $pdo->prepare("SELECT foto, nama_lengkap FROM peserta WHERE id = ? AND sekolah_id = ? LIMIT 1");
        $stmtP->execute([$pesertaId, $sekolahId]);
        $p = $stmtP->fetch();

        if ($p) {
            if (!empty($p['foto'])) {
                $photoPath = dirname(__DIR__) . '/uploads/peserta/' . $p['foto'];
                if (file_exists($photoPath)) {
                    @unlink($photoPath);
                }
            }

            // Delete Participant
            $stmtDel = $pdo->prepare("DELETE FROM peserta WHERE id = ? AND sekolah_id = ?");
            $stmtDel->execute([$pesertaId, $sekolahId]);

            set_flash('success', 'Peserta <strong>' . e($p['nama_lengkap']) . '</strong> berhasil dihapus dari kontingen.');
        } else {
            set_flash('danger', 'Data peserta tidak ditemukan atau tidak memiliki akses.');
        }
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus peserta: ' . $e->getMessage());
    }
}

header('Location: ' . base_url('dashboard/peserta.php'));
exit;
