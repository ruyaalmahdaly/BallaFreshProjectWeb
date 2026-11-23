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
$databaseUrl = getenv('DATABASE_URL') ?: (getenv('POSTGRES_URL') ?: '');

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
        // Prioritaskan port 5432 (session pooler) lalu 6543 (transaction pooler)
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

// 3. Jika di Vercel (lingkungan Cloud), database WAJIB menggunakan Supabase (karena Docker lokal tidak tersedia di cloud)
if ($isVercel && $conn === null) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Database Supabase di Vercel - Balla' Fresh</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; padding: 30px 20px; line-height: 1.6; }
            .card { max-width: 680px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
            h2 { color: #166534; margin-top: 0; display: flex; align-items: center; gap: 10px; }
            .badge { background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 700; }
            .code-box { background: #0f172a; color: #38bdf8; padding: 14px 18px; border-radius: 12px; font-family: monospace; font-size: 13px; overflow-x: auto; margin: 16px 0; }
            ol { padding-left: 20px; }
            li { margin-bottom: 12px; }
            a { color: #16a34a; font-weight: 700; text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2><span>🌿</span> Setup Database Supabase Vercel</h2>
            <p><span class="badge">Perhatian</span> Website Anda sudah berhasil ter-deploy di Vercel! Namun koneksi database cloud (Supabase) belum terhubung.</p>

            <?php if (!empty($supabaseError)): ?>
                <div style="background:#fff1f2;border:1px solid #fecdd3;padding:12px 16px;border-radius:10px;color:#9f1239;font-size:13px;margin:16px 0;">
                    <strong>Error Koneksi:</strong> <?= htmlspecialchars($supabaseError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <p><strong>Langkah Mengaktifkan Database Supabase di Vercel:</strong></p>
            <ol>
                <li>Buka Dashboard Vercel proyek Anda &rarr; <strong>Settings</strong> &rarr; <strong>Environment Variables</strong>.</li>
                <li>Tambahkan variable berikut:
                    <div class="code-box">
Key: SUPABASE_DB_PASS<br>
Value: [Password database proyek Supabase Anda]
                    </div>
                    <em>Atau tambahkan variable <code>DATABASE_URL</code> dengan URI connection string dari Supabase.</em>
                </li>
                <li>Pastikan juga Anda sudah menjalankan file <code>supabase_migration.sql</code> di menu <strong>SQL Editor</strong> Supabase agar tabel-tabel data sudah terbentuk.</li>
                <li>Setelah mengisi Environment Variable di Vercel, lakukan <strong>Redeploy</strong> pada Vercel Dashboard. Website Balla' Fresh Anda akan langsung aktif online!</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 4. Fallback ke MariaDB / MySQL Lokal (Saat dijalankan di komputer / XAMPP)
if ($conn === null) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'GANTI_DENGAN_PASSWORD_KUAT123';
    $database = getenv('DB_NAME') ?: 'ballafresh_db';
    $port = (int) (getenv('DB_PORT') ?: 3309);

    try {
        $conn = new mysqli(
            $host,
            $username,
            $password,
            $database,
            $port
        );

        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('Koneksi database Balla Fresh gagal: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Periksa konfigurasi db.php dan pastikan container database Docker sedang berjalan.');
    }
}
?>
