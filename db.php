<?php
declare(strict_types=1);

/*
 * Koneksi Database Balla' Fresh
 * Mendukung Supabase PostgreSQL & MariaDB / MySQL
 */

require_once __DIR__ . '/supabase_config.php';
require_once __DIR__ . '/supabase_adapter.php';

$conn = null;
$connectedToSupabase = false;

// 1. Coba koneksi ke Supabase PostgreSQL jika password database Supabase diatur
if (defined('SUPABASE_DB_PASS') && SUPABASE_DB_PASS !== '' && extension_loaded('pdo_pgsql')) {
    try {
        $pgHost = SUPABASE_DB_HOST;
        $pgPort = SUPABASE_DB_PORT;
        $pgUser = SUPABASE_DB_USER;
        $pgDb   = SUPABASE_DB_NAME;
        $pgPass = SUPABASE_DB_PASS;

        $dsn = "pgsql:host={$pgHost};port={$pgPort};dbname={$pgDb};sslmode=require";
        $pdo = new PDO($dsn, $pgUser, $pgPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);

        $conn = new SupabaseMysqliAdapter($pdo);
        $connectedToSupabase = true;
    } catch (Throwable $e) {
        error_log('Koneksi Supabase PostgreSQL gagal, beralih ke MariaDB: ' . $e->getMessage());
        $conn = null;
    }
}

// 2. Fallback ke MariaDB / MySQL (Docker / Lokal)
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
