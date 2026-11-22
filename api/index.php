<?php
declare(strict_types=1);

// Ubah working directory ke direktori root proyek agar include dan file path tetap konsisten
chdir(dirname(__DIR__));

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$path = trim($path, '/');

// 1. Root / Halaman Utama
if ($path === '' || $path === 'index.php' || $path === 'index') {
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
