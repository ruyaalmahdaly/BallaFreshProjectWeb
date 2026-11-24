<?php
declare(strict_types=1);

/**
 * Konfigurasi Supabase Balla' Fresh
 */

$localEnv = file_exists(__DIR__ . '/env.local.php') ? (require __DIR__ . '/env.local.php') : [];

if (!function_exists('get_supabase_env')) {
    function get_supabase_env(string $key, $default = '') {
        global $localEnv;
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (!empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $localEnv[$key] ?? $default;
    }
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', (string)get_supabase_env('SUPABASE_URL', 'https://althnabbfrwbnmeqkrrd.supabase.co'));
}

if (!defined('SUPABASE_PUBLISHABLE_KEY')) {
    define('SUPABASE_PUBLISHABLE_KEY', (string)get_supabase_env('SUPABASE_PUBLISHABLE_KEY', ''));
}

if (!defined('SUPABASE_SECRET_KEY')) {
    define('SUPABASE_SECRET_KEY', (string)get_supabase_env('SUPABASE_SECRET_KEY', ''));
}

if (!defined('SUPABASE_JWKS_URL')) {
    define('SUPABASE_JWKS_URL', (string)get_supabase_env('SUPABASE_JWKS_URL', 'https://althnabbfrwbnmeqkrrd.supabase.co/auth/v1/.well-known/jwks.json'));
}

// Konfigurasi Database PostgreSQL Supabase
if (!defined('SUPABASE_DB_HOST')) {
    define('SUPABASE_DB_HOST', (string)get_supabase_env('SUPABASE_DB_HOST', 'aws-0-ap-southeast-2.pooler.supabase.com'));
}

if (!defined('SUPABASE_DB_PORT')) {
    define('SUPABASE_DB_PORT', (int)get_supabase_env('SUPABASE_DB_PORT', 6543));
}

if (!defined('SUPABASE_DB_USER')) {
    define('SUPABASE_DB_USER', (string)get_supabase_env('SUPABASE_DB_USER', 'postgres.althnabbfrwbnmeqkrrd'));
}

if (!defined('SUPABASE_DB_NAME')) {
    define('SUPABASE_DB_NAME', (string)get_supabase_env('SUPABASE_DB_NAME', 'postgres'));
}

if (!defined('SUPABASE_DB_PASS')) {
    define('SUPABASE_DB_PASS', (string)get_supabase_env('SUPABASE_DB_PASS', ''));
}

/**
 * Helper cURL request ke Supabase REST API (PostgREST)
 *
 * @param string $endpoint misal: 'services' atau 'users?select=*'
 * @param string $method GET, POST, PATCH, DELETE
 * @param array|null $payload Data body (untuk POST / PATCH)
 * @param array $headers Header tambahan
 * @return array ['success' => bool, 'status' => int, 'data' => mixed, 'error' => string|null]
 */
function supabaseRestRequest(string $endpoint, string $method = 'GET', ?array $payload = null, array $headers = []): array {
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');

    $apiKey = !empty(SUPABASE_SECRET_KEY) ? SUPABASE_SECRET_KEY : SUPABASE_PUBLISHABLE_KEY;
    $defaultHeaders = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $allHeaders = array_merge($defaultHeaders, $headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($payload !== null && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    if (PHP_VERSION_ID < 80500 && function_exists('curl_close')) {
        @curl_close($ch);
    }
    unset($ch);

    if ($curlErr) {
        return ['success' => false, 'status' => $httpCode, 'data' => null, 'error' => $curlErr];
    }

    $decoded = json_decode((string)$response, true);
    $isSuccess = ($httpCode >= 200 && $httpCode < 300);

    return [
        'success' => $isSuccess,
        'status' => $httpCode,
        'data' => $decoded,
        'error' => $isSuccess ? null : ($decoded['message'] ?? (string)$response)
    ];
}
