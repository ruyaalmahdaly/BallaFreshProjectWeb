<?php
declare(strict_types=1);

require_once __DIR__ . "/session_bootstrap.php";
require_once __DIR__ . "/db.php";

$orderNumber = trim((string)($_GET["order_id"] ?? $_GET["order"] ?? ""));

$order = null;
if ($orderNumber !== "") {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $orderNumber);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
}

function rupiah($num): string {
    return "Rp" . number_format((float)$num, 0, ",", ".");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Belum Berhasil | Balla’Fresh</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --green: #3a6932;
            --green-dark: #24461f;
            --bg: #f8faf6;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --shadow-lg: 0 20px 35px -8px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
        }

        .topbar-inner {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-link img {
            height: 42px;
            width: auto;
            object-fit: contain;
        }

        .main-container {
            max-width: 760px;
            margin: 40px auto 60px;
            padding: 0 20px;
            width: 100%;
        }

        .failed-card {
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid #fecaca;
            box-shadow: var(--shadow-lg);
            padding: 44px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .failed-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #ef4444, #dc2626, #b91c1c);
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: #fee2e2;
            color: #ef4444;
            display: grid;
            place-items: center;
            font-size: 40px;
            margin: 0 auto 24px;
            box-shadow: 0 0 0 12px #fef2f2;
        }

        .badge-failed {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            color: #991b1b;
            font-weight: 800;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #fecaca;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        h1 {
            font-family: "Manrope", sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .lead-text {
            color: #475569;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 26px;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            text-align: left;
            margin-bottom: 26px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-label { color: #64748b; font-weight: 500; }
        .info-value { font-weight: 700; color: #0f172a; }

        .btn-retry {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--green);
            color: #fff;
            padding: 14px 24px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-retry:hover {
            background: var(--green-dark);
        }

        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .links-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 24px;
        }

        .links-row a {
            color: var(--green);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand-link" href="index.php">
            <img src="logo-balla-fresh.png" onerror="this.style.display='none'">
        </a>
        <a href="index.php" style="color:var(--muted);text-decoration:none;font-size:13px;font-weight:700">
            <i class="fa-solid fa-house"></i> Beranda
        </a>
    </div>
</header>

<main class="main-container">
    <div class="failed-card">
        <div class="icon-circle">
            <i class="fa-solid fa-xmark"></i>
        </div>

        <span class="badge-failed">
            <i class="fa-solid fa-circle-exclamation"></i> Pembayaran Belum Berhasil
        </span>

        <h1>Transaksi Dibatalkan atau Kedaluwarsa</h1>
        <p class="lead-text">
            Sistem pembayaran tidak mendeteksi penyelesaian transaksi dalam batas waktu yang ditentukan atau transaksi telah dibatalkan.
        </p>

        <?php if ($order): ?>
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Nomor Pesanan</span>
                <span class="info-value" style="font-family:monospace"><?= e($order["order_number"]) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Tagihan</span>
                <span class="info-value"><?= rupiah($order["total_amount"]) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status Terkini</span>
                <span class="info-value" style="color:#ef4444"><?= e(strtoupper((string)($order["transaction_status"] ?: $order["payment_status"] ?: "Batal"))) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="actions-group">
            <a href="checkout.php" class="btn-retry">
                <i class="fa-solid fa-rotate-left"></i> Coba Pembayaran Lagi di Checkout
            </a>
        </div>

        <div class="links-row">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Halaman Utama</a>
            <a href="https://wa.me/6281234567890?text=<?= urlencode("Halo Balla Fresh, saya mengalami kendala pembayaran pesanan " . ($orderNumber ?: "")) ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-whatsapp"></i> Hubungi Bantuan CS
            </a>
        </div>
    </div>
</main>

</body>
</html>

