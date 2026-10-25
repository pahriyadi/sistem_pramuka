<?php
/**
 * Authentication & Authorization Middleware
 * Racana Universitas Samawa (UNSA)
 */

require_once __DIR__ . '/app.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function get_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function get_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function get_auth_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function get_current_sekolah(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM sekolah WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function require_login(array $allowedRoles = []): void {
    if (!is_logged_in()) {
        set_flash('danger', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }

    if (!empty($allowedRoles)) {
        $role = get_user_role();
        if (!in_array($role, $allowedRoles)) {
            set_flash('danger', 'Akses ditolak. Anda tidak memiliki izin untuk membuka halaman tersebut.');
            if ($role === 'admin') {
                header('Location: ' . base_url('admin/index.php'));
            } else {
                header('Location: ' . base_url('dashboard/index.php'));
            }
            exit;
        }
    }
}

function require_admin(): void {
    require_login(['admin']);
}

function require_sekolah(): void {
    require_login(['sekolah']);
}
