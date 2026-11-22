<?php
declare(strict_types=1);

require_once __DIR__ . "/session_bootstrap.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/midtrans_config.php";

$orderNumber = trim((string)($_GET["order_id"] ?? $_GET["order"] ?? ""));

if ($orderNumber === "") {
    header("Location: index.php");
    exit;
}

// 1. Ambil data pesanan dari database
$orderStmt = $conn->prepare("
    SELECT
        o.*,
        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        c.address AS customer_address
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.order_number = ?
    LIMIT 1
");

if (!$orderStmt) {
    die("Database error.");
}

$orderStmt->bind_param("s", $orderNumber);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header("Location: index.php");
    exit;
}

// 2. Verifikasi status transaksi langsung ke Midtrans API jika di database belum 'settlement'
$isPaid = in_array($order["payment_status"], ["settlement", "capture", "lunas"], true);

if (!$isPaid && isMidtransConfigured()) {
    $apiStatus = getMidtransTransactionStatus($orderNumber);
    if (!empty($apiStatus["success"]) && isset($apiStatus["data"])) {
        $stData = $apiStatus["data"];
        $midStatus = $stData["transaction_status"] ?? "";
        $fraudStatus = $stData["fraud_status"] ?? "";
        $transId = $stData["transaction_id"] ?? ($order["midtrans_transaction_id"] ?? "");
        $payType = $stData["payment_type"] ?? ($order["payment_type"] ?? "midtrans");
        $settleTime = $stData["settlement_time"] ?? date("Y-m-d H:i:s");

        if ($midStatus === "settlement" || ($midStatus === "capture" && $fraudStatus === "accept")) {
            // Update database menjadi lunas
            $upd = $conn->prepare("
                UPDATE orders
                SET payment_status = 'settlement',
                    status = IF(status = 'pending', 'processing', status),
                    midtrans_transaction_id = ?,
                    payment_type = ?,
                    settlement_time = COALESCE(settlement_time, ?),
                    transaction_status = ?
                WHERE id = ?
            ");
            if ($upd) {
                $orderIdInt = (int)$order["id"];
                $upd->bind_param("ssssi", $transId, $payType, $settleTime, $midStatus, $orderIdInt);
                $upd->execute();
                $upd->close();
            }

            // Catat log
            $insLog = $conn->prepare("
                INSERT INTO order_transactions
                    (order_id, order_number, midtrans_transaction_id, payment_type, gross_amount, transaction_status, raw_response, settlement_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($insLog) {
                $rawStr = json_encode($stData);
                $amt = (float)$order["total_amount"];
                $insLog->bind_param("isssdsss", $order["id"], $orderNumber, $transId, $payType, $amt, $midStatus, $rawStr, $settleTime);
                $insLog->execute();
                $insLog->close();
            }

            $order["payment_status"] = "settlement";
            $order["status"] = ($order["status"] === "pending") ? "processing" : $order["status"];
            $order["midtrans_transaction_id"] = $transId;
            $order["payment_type"] = $payType;
            $order["settlement_time"] = $settleTime;
            $isPaid = true;
        } elseif (in_array($midStatus, ["deny", "expire", "cancel"], true)) {
            header("Location: payment_failed.php?order_id=" . urlencode($orderNumber));
            exit;
        }
    }
}

// Jika setelah cek tetap belum dibayar, arahkan ke payment_pending.php
if (!$isPaid && $order["payment_method"] === "midtrans") {
    header("Location: payment_pending.php?order_id=" . urlencode($orderNumber));
    exit;
}

// 3. Ambil rincian item pesanan
$itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$items = [];
if ($itemsStmt) {
    $itemsStmt->bind_param("i", $order["id"]);
    $itemsStmt->execute();
    $res = $itemsStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $itemsStmt->close();
}

$subMeta = function_exists("getPaymentSubMethodMeta") ? getPaymentSubMethodMeta((string)($order["payment_type"] ?: $order["payment_method"])) : [];

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
    <title>Pembayaran Berhasil · <?= e($orderNumber) ?> | Balla’Fresh</title>

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
            --green-light: #5b8121;
            --green-soft: #f4f8f2;
            --lime: #d9f99d;
            --bg: #f8faf6;
            --white: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --orange: #f59e0b;
            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 10px 25px -4px rgba(15, 23, 42, 0.08);
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

        .brand-link {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
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

        .success-card {
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid #bbf7d0;
            box-shadow: var(--shadow-lg);
            padding: 44px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #10b981, #22c55e, #3a6932);
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: #dcfce7;
            color: #16a34a;
            display: grid;
            place-items: center;
            font-size: 40px;
            margin: 0 auto 24px;
            box-shadow: 0 0 0 12px #f0fdf4;
            animation: pulseSuccess 2s infinite;
        }

        @keyframes pulseSuccess {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 18px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            color: #166534;
            font-weight: 800;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #bbf7d0;
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
            margin-bottom: 30px;
        }

        .receipt-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            text-align: left;
            margin-bottom: 30px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .receipt-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .receipt-row:first-child {
            padding-top: 0;
        }

        .receipt-label {
            color: #64748b;
            font-weight: 500;
        }

        .receipt-value {
            font-weight: 700;
            color: #0f172a;
            text-align: right;
        }

        .total-highlight {
            color: #166534;
            font-size: 18px;
            font-weight: 800;
        }

        .items-list {
            margin: 16px 0 0;
            padding-top: 14px;
            border-top: 1px solid #cbd5e1;
        }

        .items-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 8px;
        }

        .item-line {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            padding: 4px 0;
            color: #334155;
        }

        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-primary {
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
            box-shadow: 0 4px 14px rgba(58, 105, 50, 0.3);
        }

        .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #fff;
            color: #334155;
            padding: 12px 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .links-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .links-row a {
            color: var(--green);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .links-row a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .success-card {
                padding: 30px 20px;
            }
            .receipt-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .receipt-value {
                text-align: left;
            }
            .links-row {
                flex-direction: column;
                gap: 10px;
            }
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
    <div class="success-card">
        <div class="icon-circle">
            <i class="fa-solid fa-check"></i>
        </div>

        <span class="badge-verified">
            <i class="fa-solid fa-circle-check"></i> Pembayaran Terverifikasi Midtrans
        </span>

        <h1>Pembayaran Sukses!</h1>
        <p class="lead-text">
            Terima kasih, <strong><?= e($order["customer_name"] ?? "Mitra") ?></strong>. Pesanan layanan digital untuk <strong><?= e($order["business_name"] ?: "Usaha Anda") ?></strong> telah terbayar lunas dan sedang diproses oleh tim spesialis Balla’ Fresh.
        </p>

        <div class="receipt-box">
            <div class="receipt-row">
                <span class="receipt-label">Nomor Pesanan</span>
                <span class="receipt-value" style="font-family:monospace;letter-spacing:0.04em"><?= e($order["order_number"]) ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Metode Pembayaran</span>
                <span class="receipt-value">
                    <i class="fa-solid <?= e($subMeta["icon"] ?? "fa-bolt") ?>" style="color:<?= e($subMeta["badge_color"] ?? "var(--orange)") ?>"></i>
                    <?= e($subMeta["label"] ?? strtoupper((string)($order["payment_type"] ?: $order["payment_method"]))) ?> (Midtrans Sandbox)
                </span>
            </div>
            <?php if (!empty($order["payment_code"])): ?>
            <div class="receipt-row">
                <span class="receipt-label">Kode / No. VA</span>
                <span class="receipt-value" style="font-family:monospace;font-size:13px">
                    <?= (!empty($order["biller_code"]) ? e($order["biller_code"]) . " / " : "") . e($order["payment_code"]) ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($order["midtrans_transaction_id"])): ?>
            <div class="receipt-row">
                <span class="receipt-label">ID Transaksi Midtrans</span>
                <span class="receipt-value" style="font-family:monospace;font-size:12.5px"><?= e($order["midtrans_transaction_id"]) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="receipt-label">Waktu Pembayaran</span>
                <span class="receipt-value">
                    <?= !empty($order["settlement_time"]) ? date("d M Y, H:i", strtotime((string)$order["settlement_time"])) : date("d M Y, H:i") ?> WIB
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Total Pembayaran (Lunas)</span>
                <span class="receipt-value total-highlight"><?= rupiah($order["total_amount"]) ?></span>
            </div>

            <?php if (!empty($items)): ?>
            <div class="items-list">
                <div class="items-title">Rincian Layanan Digital</div>
                <?php foreach ($items as $item): ?>
                    <div class="item-line">
                        <span><?= e($item["service_name"]) ?> × <?= (int)$item["quantity"] ?></span>
                        <strong><?= rupiah($item["subtotal"]) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="actions-group">
            <a href="invoice.php?order=<?= urlencode($order["order_number"]) ?>" target="_blank" class="btn-primary">
                <i class="fa-solid fa-file-invoice"></i> Lihat &amp; Cetak Bukti Pembayaran (Invoice)
            </a>
            <a href="profil.php#riwayat" class="btn-secondary">
                <i class="fa-regular fa-user"></i> Buka Profil &amp; Riwayat Pesanan
            </a>
        </div>

        <div class="links-row">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
            <a href="https://wa.me/6281234567890?text=<?= urlencode("Halo Balla Fresh, saya sudah bayar pesanan " . $order["order_number"]) ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-whatsapp"></i> Hubungi Admin Balla’ Fresh
            </a>
        </div>
    </div>
</main>

</body>
</html>

