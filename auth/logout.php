<?php
/**
 * Logout Handler
 * Racana Universitas Samawa (UNSA)
 */
require_once dirname(__DIR__) . '/config/app.php';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start a fresh session to set flash message
session_start();
set_flash('info', 'Anda telah berhasil keluar (logout) dari portal.');
header('Location: ' . base_url('auth/login.php'));
exit;
