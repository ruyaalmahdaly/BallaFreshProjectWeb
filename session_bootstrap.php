<?php
declare(strict_types=1);

/**
 * Session Bootstrap Balla' Fresh
 * Mendukung persistensi session multi-container di lingkungan Serverless (Vercel)
 * melalui Signed Stateless Session Cookie (HMAC-SHA256) serta fallback session file lokal.
 */

require_once __DIR__ . '/supabase_config.php';

// Kunci rahasia untuk menandatangani HMAC cookie session (agar data tidak dapat dimanipulasi client)
$sessionSecret = function_exists('get_supabase_env')
    ? (string)get_supabase_env('SUPABASE_SECRET_KEY', 'ballafresh_secure_session_hmac_secret_2026_salt')
    : (getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? ($_SERVER['SUPABASE_SECRET_KEY'] ?? 'ballafresh_secure_session_hmac_secret_2026_salt')));

if (empty($sessionSecret)) {
    $sessionSecret = 'ballafresh_secure_session_hmac_secret_2026_salt';
}

function get_session_secret(): string {
    global $sessionSecret;
    if (!empty($sessionSecret)) {
        return (string)$sessionSecret;
    }
    if (function_exists('get_supabase_env')) {
        return (string)get_supabase_env('SUPABASE_SECRET_KEY', 'ballafresh_secure_session_hmac_secret_2026_salt');
    }
    return getenv('SUPABASE_SECRET_KEY')
        ?: ($_ENV['SUPABASE_SECRET_KEY']
        ?? ($_SERVER['SUPABASE_SECRET_KEY']
        ?? 'ballafresh_secure_session_hmac_secret_2026_salt'));
}

/**
 * Generate stateless HMAC-signed CSRF token
 */
function generate_csrf_token(): string {
    $random = bin2hex(random_bytes(16));
    $timestamp = time();
    $data = $random . '|' . $timestamp;
    $sig = hash_hmac('sha256', $data, get_session_secret());
    return $data . '|' . $sig;
}

/**
 * Verify CSRF token (supports stateless HMAC token & session register_csrf)
 */
function verify_csrf_token(?string $token, int $maxAge = 7200): bool {
    if (empty($token) || !is_string($token)) {
        return false;
    }

    // 1. Cek kecocokan session register_csrf jika ada
    if (!empty($_SESSION['register_csrf']) && hash_equals((string)$_SESSION['register_csrf'], $token)) {
        return true;
    }

    // 2. Cek token HMAC stateless
    $parts = explode('|', $token);
    if (count($parts) === 3) {
        [$random, $timestamp, $sig] = $parts;
        $time = (int)$timestamp;
        if ($time > 0 && (time() - $time) <= $maxAge && (time() - $time) >= -300) {
            $expectedSig = hash_hmac('sha256', $random . '|' . $timestamp, get_session_secret());
            if (hash_equals($expectedSig, $sig)) {
                return true;
            }
        }
    }

    return false;
}

// Tentukan path session lokal (untuk fallback)
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
    // Linux / Vercel
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

    // Session berlaku 7 hari (604800 detik)
    ini_set('session.gc_maxlifetime', '604800');
    ini_set('session.cookie_lifetime', '604800');
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    @session_start();
}

/**
 * Sinkronkan session ke signed cookie.
 * Dipanggil secara otomatis saat shutdown dan dapat dipanggil langsung sebelum header redirect.
 */
function sync_session_cookie(): void {
    global $sessionSecret;
    if (headers_sent()) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || !empty($_ENV['VERCEL'])
        || !empty($_SERVER['VERCEL']);

    $persistKeys = [
        'user_id', 'user_name', 'user_email',
        'admin_id', 'admin_name', 'admin_email',
        'temp_register', 'registration_pending_id',
        'register_csrf', 'login_csrf', 'admin_login_csrf',
        'cart'
    ];

    $dataToPersist = [];
    foreach ($persistKeys as $key) {
        if (isset($_SESSION[$key])) {
            $dataToPersist[$key] = $_SESSION[$key];
        }
    }

    $cookieName = 'bf_session_state';

    if (empty($dataToPersist)) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', [
                'expires'  => time() - 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE[$cookieName]);
        }
        return;
    }

    $payload = [
        'data' => $dataToPersist,
        'exp'  => time() + (7 * 86400)
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $encoded = base64_encode($json);
    $signature = hash_hmac('sha256', $encoded, $sessionSecret);
    $cookieValue = $encoded . '.' . $signature;

    setcookie($cookieName, $cookieValue, [
        'expires'  => time() + (7 * 86400),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Pulihkan data session dari signed cookie jika container serverless belum memiliki data di /tmp
 */
function restore_session_from_cookie(): void {
    global $sessionSecret;
    $cookieName = 'bf_session_state';

    if (empty($_COOKIE[$cookieName]) || !is_string($_COOKIE[$cookieName])) {
        return;
    }

    $parts = explode('.', $_COOKIE[$cookieName], 2);
    if (count($parts) !== 2) {
        return;
    }

    [$encoded, $signature] = $parts;
    $expectedSig = hash_hmac('sha256', $encoded, $sessionSecret);

    if (!hash_equals($expectedSig, $signature)) {
        return;
    }

    $json = base64_decode($encoded, true);
    if ($json === false) {
        return;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
        return;
    }

    if (!empty($payload['exp']) && $payload['exp'] < time()) {
        return;
    }

    // Pulihkan ke $_SESSION jika di instance ini datanya belum ada
    foreach ($payload['data'] as $k => $v) {
        if (!isset($_SESSION[$k])) {
            $_SESSION[$k] = $v;
        }
    }
}

// Pulihkan session dari cookie pada setiap request
restore_session_from_cookie();

// Daftarkan sync session saat skrip selesai dieksekusi
register_shutdown_function('sync_session_cookie');

