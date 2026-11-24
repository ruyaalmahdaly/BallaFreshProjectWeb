<?php
declare(strict_types=1);

/*
 * Koneksi Database Balla' Fresh
 * Mendukung Supabase PostgreSQL (Cloud / Vercel) & MariaDB / MySQL (Lokal)
 */

require_once __DIR__ . '/supabase_config.php';
require_once __DIR__ . '/supabase_adapter.php';

$conn = null;
$connectedToSupabase = false;
$supabaseError = null;

$isVercel = !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) || getenv('VERCEL') !== false;

// 1. Cek apakah ada DATABASE_URL atau POSTGRES_URL lengkap dari Vercel / Supabase
$databaseUrl = get_supabase_env('DATABASE_URL', get_supabase_env('POSTGRES_URL', ''));

$pgHost = SUPABASE_DB_HOST;
$pgPort = SUPABASE_DB_PORT;
$pgUser = SUPABASE_DB_USER;
$pgDb   = SUPABASE_DB_NAME;
$pgPass = SUPABASE_DB_PASS;

if (!empty($databaseUrl)) {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed) && !empty($parsed['host'])) {
        $pgHost = $parsed['host'];
        $pgPort = (int)($parsed['port'] ?? 5432);
        $pgUser = urldecode($parsed['user'] ?? 'postgres');
        $pgPass = urldecode($parsed['pass'] ?? '');
        $pgDb   = ltrim($parsed['path'] ?? '/postgres', '/');
    }
}

// 2. Hubungkan ke Supabase PostgreSQL jika password / connection string tersedia
if (!empty($pgPass) && extension_loaded('pdo_pgsql')) {
    $hostsToTry = [];
    if (!empty($databaseUrl)) {
        $hostsToTry[] = ['host' => $pgHost, 'port' => $pgPort, 'user' => $pgUser];
        if ($pgPort === 6543) {
            $hostsToTry[] = ['host' => $pgHost, 'port' => 5432, 'user' => $pgUser];
        }
    } else {
        // Prioritaskan direct Supabase host lalu pooler
        $hostsToTry[] = ['host' => 'db.althnabbfrwbnmeqkrrd.supabase.co', 'port' => 5432, 'user' => 'postgres'];
        $hostsToTry[] = ['host' => $pgHost, 'port' => ($pgPort === 6543 ? 5432 : $pgPort), 'user' => $pgUser];
        $hostsToTry[] = ['host' => $pgHost, 'port' => 6543, 'user' => $pgUser];
        $hostsToTry[] = ['host' => 'aws-0-ap-southeast-1.pooler.supabase.com', 'port' => 5432, 'user' => $pgUser];
    }

    foreach ($hostsToTry as $target) {
        try {
            $dsn = "pgsql:host={$target['host']};port={$target['port']};dbname={$pgDb};sslmode=require";
            $pdo = new PDO($dsn, $target['user'], $pgPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 4,
                PDO::ATTR_EMULATE_PREPARES => true
            ]);

            // Sinkronisasi kolom opsional jika belum ada pada database Supabase
            try {
                $pdo->exec('
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "business_name" VARCHAR(150);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "social_media" VARCHAR(255);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "website_url" VARCHAR(255);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "user_id" INT;
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "payment_type" VARCHAR(50);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "payment_code" VARCHAR(100);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "biller_code" VARCHAR(50);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "qr_url" TEXT;
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "qr_string" TEXT;
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "deeplink_url" TEXT;
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "gross_amount" NUMERIC(12,2);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "transaction_status" VARCHAR(50);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "payment_status" VARCHAR(50) DEFAULT \'menunggu_pembayaran\';
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "expiry_time" TIMESTAMPTZ;
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "midtrans_transaction_id" VARCHAR(100);
                    ALTER TABLE public."orders" ADD COLUMN IF NOT EXISTS "midtrans_response" TEXT;
                    ALTER TABLE public."users" ADD COLUMN IF NOT EXISTS "business_name" VARCHAR(150);
                    ALTER TABLE public."users" ADD COLUMN IF NOT EXISTS "social_media" VARCHAR(255);
                    ALTER TABLE public."users" ADD COLUMN IF NOT EXISTS "website_url" VARCHAR(255);
                    ALTER TABLE public."users" ADD COLUMN IF NOT EXISTS "business_address" TEXT;
                ');
            } catch (Throwable $alterIgnored) {}

            $conn = new SupabaseMysqliAdapter($pdo);
            $connectedToSupabase = true;
            $supabaseError = null;
            break;
        } catch (Throwable $e) {
            $supabaseError = $e->getMessage();
            error_log("Koneksi Supabase PostgreSQL ke {$target['host']}:{$target['port']} gagal: " . $supabaseError);
            $conn = null;
        }
    }
}

// 3. Fallback ke MariaDB / MySQL Lokal (Saat dijalankan di komputer / XAMPP lokal)
if ($conn === null && !$isVercel) {
    mysqli_report(MYSQLI_REPORT_OFF);

    $host = get_supabase_env('DB_HOST', '127.0.0.1');
    $username = get_supabase_env('DB_USER', 'root');
    $password = get_supabase_env('DB_PASS', 'GANTI_DENGAN_PASSWORD_KUAT123');
    $database = get_supabase_env('DB_NAME', 'ballafresh_db');
    $port = (int) get_supabase_env('DB_PORT', 3309);

    try {
        $conn = @new mysqli(
            $host,
            $username,
            $password,
            $database,
            $port
        );

        if ($conn->connect_error) {
            $conn = null;
        } else {
            $conn->set_charset('utf8mb4');
        }
    } catch (Throwable $e) {
        $conn = null;
    }
}

// 4. Fallback ke Supabase REST API jika direct PostgreSQL belum terhubung tetapi SUPABASE_URL & keys tersedia
if ($conn === null && !empty(SUPABASE_URL) && (!empty(SUPABASE_SECRET_KEY) || !empty(SUPABASE_PUBLISHABLE_KEY))) {
    $conn = new SupabaseRestAdapter();
    $connectedToSupabase = true;
    $supabaseError = null;
}

// 5. Jika di Vercel dan seluruh koneksi belum terhubung, siapkan safe adapter agar API tidak 500
if ($conn === null) {
    if (!empty(SUPABASE_URL)) {
        $conn = new SupabaseRestAdapter();
    }
}
?>
