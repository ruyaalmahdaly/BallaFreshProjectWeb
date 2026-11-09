<?php
declare(strict_types=1);

session_start();

// Logout hanya boleh dilakukan melalui POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

// Periksa token keamanan.
$csrf = $_POST['csrf'] ?? '';
$sessionToken = $_SESSION['admin_logout_csrf'] ?? '';

if (
    !is_string($csrf) ||
    !is_string($sessionToken) ||
    $csrf === '' ||
    !hash_equals($sessionToken, $csrf)
) {
    http_response_code(403);
    exit('Permintaan logout tidak valid. Silakan login kembali.');
}

// Hapus seluruh data sesi.
$_SESSION = [];

// Hapus cookie sesi.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan sesi.
session_destroy();

// Kembali ke halaman login khusus admin.
header('Location: admin_login.php?logout=success');
exit;
