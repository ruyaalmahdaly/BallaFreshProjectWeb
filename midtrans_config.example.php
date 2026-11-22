<?php
/**
 * Contoh Template Konfigurasi Midtrans Sandbox Payment Gateway & Core API
 * Balla' Fresh Agency
 *
 * Salin file ini menjadi `midtrans_config.php` lalu masukkan API Key Anda:
 * - Dashboard: https://dashboard.sandbox.midtrans.com/
 * - Menu: Settings -> Access Keys
 */

// Muat Composer autoloader jika tersedia
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Ganti nilai di bawah dengan Server Key & Client Key dari Dashboard Midtrans Sandbox Anda
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-uSrqhi~PXffAUq5oF_Me~w0d');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-AutptVqwYlzg6dwp');
define('MIDTRANS_IS_PRODUCTION', false); // false = Sandbox, true = Production
define('MIDTRANS_IS_SANITIZED', true);
define('MIDTRANS_IS_3DS', true);
