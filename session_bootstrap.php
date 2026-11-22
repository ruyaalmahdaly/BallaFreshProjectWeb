<?php

// Tentukan path session yang kompatibel untuk Windows (XAMPP) maupun Linux / Vercel (serverless)
$sessionPath = null;
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $sessionPath = 'C:\\xampp\\session_data';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
} else {
    // Linux / Vercel / Cloud serverless
    $sessionPath = sys_get_temp_dir() . '/session_data';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    if (!empty($sessionPath) && is_dir($sessionPath) && is_writable($sessionPath)) {
        @session_save_path($sessionPath);
    }

    // Session berlaku 7 hari (604800 detik) agar pengguna tidak perlu login ulang
    ini_set('session.gc_maxlifetime', '604800');
    ini_set('session.cookie_lifetime', '604800');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}
