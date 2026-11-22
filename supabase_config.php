<?php
declare(strict_types=1);

/**
 * Konfigurasi Supabase Balla' Fresh
 */

$localEnv = file_exists(__DIR__ . '/env.local.php') ? (require __DIR__ . '/env.local.php') : [];

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', getenv('SUPABASE_URL') ?: ($localEnv['SUPABASE_URL'] ?? 'https://althnabbfrwbnmeqkrrd.supabase.co'));
}

if (!defined('SUPABASE_PUBLISHABLE_KEY')) {
    define('SUPABASE_PUBLISHABLE_KEY', getenv('SUPABASE_PUBLISHABLE_KEY') ?: ($localEnv['SUPABASE_PUBLISHABLE_KEY'] ?? ''));
}

if (!defined('SUPABASE_SECRET_KEY')) {
    define('SUPABASE_SECRET_KEY', getenv('SUPABASE_SECRET_KEY') ?: ($localEnv['SUPABASE_SECRET_KEY'] ?? ''));
}

if (!defined('SUPABASE_JWKS_URL')) {
    define('SUPABASE_JWKS_URL', getenv('SUPABASE_JWKS_URL') ?: ($localEnv['SUPABASE_JWKS_URL'] ?? 'https://althnabbfrwbnmeqkrrd.supabase.co/auth/v1/.well-known/jwks.json'));
}

// Konfigurasi Database PostgreSQL Supabase
if (!defined('SUPABASE_DB_HOST')) {
    define('SUPABASE_DB_HOST', getenv('SUPABASE_DB_HOST') ?: ($localEnv['SUPABASE_DB_HOST'] ?? 'aws-0-ap-southeast-2.pooler.supabase.com'));
}

if (!defined('SUPABASE_DB_PORT')) {
    define('SUPABASE_DB_PORT', (int)(getenv('SUPABASE_DB_PORT') ?: ($localEnv['SUPABASE_DB_PORT'] ?? 6543)));
}

if (!defined('SUPABASE_DB_USER')) {
    define('SUPABASE_DB_USER', getenv('SUPABASE_DB_USER') ?: ($localEnv['SUPABASE_DB_USER'] ?? 'postgres.althnabbfrwbnmeqkrrd'));
}

if (!defined('SUPABASE_DB_NAME')) {
    define('SUPABASE_DB_NAME', getenv('SUPABASE_DB_NAME') ?: ($localEnv['SUPABASE_DB_NAME'] ?? 'postgres'));
}

if (!defined('SUPABASE_DB_PASS')) {
    define('SUPABASE_DB_PASS', getenv('SUPABASE_DB_PASS') ?: ($localEnv['SUPABASE_DB_PASS'] ?? ''));
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

    $defaultHeaders = [
        'apikey: ' . SUPABASE_SECRET_KEY,
        'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
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
    curl_close($ch);

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
