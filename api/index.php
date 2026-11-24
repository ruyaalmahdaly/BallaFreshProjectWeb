<?php
declare(strict_types=1);

// Ubah working directory ke direktori root proyek agar include dan file path tetap konsisten
chdir(dirname(__DIR__));

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$path = trim($path, '/');

// Deteksi apakah instance / deployment ini berfungsi sebagai SERVER KHUSUS ADMIN DASHBOARD
// Didukung melalui:
// 1. Environment Variable: APP_MODE=admin atau DEPLOY_TARGET=admin (diset di Vercel Settings)
// 2. Hostname/Domain: jika domain mengandung kata 'admin' atau 'dashboard' (contoh: ballafresh-admin.vercel.app)
// 3. Header / Server variable
$isDedicatedAdminPortal = (
    (!empty($_ENV['APP_MODE']) && strtolower((string)$_ENV['APP_MODE']) === 'admin') ||
    (!empty($_SERVER['APP_MODE']) && strtolower((string)$_SERVER['APP_MODE']) === 'admin') ||
    (!empty($_ENV['DEPLOY_TARGET']) && strtolower((string)$_ENV['DEPLOY_TARGET']) === 'admin') ||
    (!empty($_SERVER['DEPLOY_TARGET']) && strtolower((string)$_SERVER['DEPLOY_TARGET']) === 'admin') ||
    (!empty($_SERVER['HTTP_HOST']) && (
        stripos($_SERVER['HTTP_HOST'], 'admin') !== false ||
        stripos($_SERVER['HTTP_HOST'], 'dashboard') !== false
    ))
);

// Clean URL routing untuk /admin atau /dashboard (berlaku di semua deploy)
if ($path === 'admin' || $path === 'admin/' || $path === 'dashboard') {
    require __DIR__ . '/../admin_dashboard.php';
    exit;
}

// 1. Root / Halaman Utama
if ($path === '' || $path === 'index.php' || $path === 'index') {
    if ($isDedicatedAdminPortal) {
        // Pada server khusus admin, akses root langsung membuka Admin Dashboard
        require __DIR__ . '/../admin_dashboard.php';
        exit;
    }
    require __DIR__ . '/../index.php';
    exit;
}

// 2. Clean URL Routing untuk Artikel: /artikel/{slug}
if (preg_match('#^artikel/([^/]+)$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    require __DIR__ . '/../baca_artikel.php';
    exit;
}

// 3. Clean URL Routing untuk Layanan: /layanan/{id}
if (preg_match('#^layanan/([^/]+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../detail_layanan.php';
    exit;
}

// 4. File PHP spesifik (misalnya: checkout.php, admin_dashboard.php, profil.php)
$targetPhp = __DIR__ . '/../' . $path;
if (is_file($targetPhp) && pathinfo($targetPhp, PATHINFO_EXTENSION) === 'php') {
    require $targetPhp;
    exit;
}

if (is_file($targetPhp . '.php')) {
    require $targetPhp . '.php';
    exit;
}

// 5. Default fallback
require __DIR__ . '/../index.php';

