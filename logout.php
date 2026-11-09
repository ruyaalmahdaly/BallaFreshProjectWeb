<?php

// Gunakan konfigurasi session utama.
require_once __DIR__ . '/session_bootstrap.php';

// Hapus seluruh data session.
$_SESSION = [];

// Hapus cookie session dari browser.
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]
    );
}

// Hancurkan session.
session_destroy();

// Kembali ke halaman login.
header('Location: login.php');
exit;
