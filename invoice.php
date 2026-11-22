<?php
declare(strict_types=1);

require_once __DIR__ . "/session_bootstrap.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/midtrans_config.php";

$orderNumber = trim((string)($_GET["order"] ?? $_GET["order_id"] ?? ""));

if ($orderNumber === "") {
    die("Nomor pesanan tidak ditentukan.");
}

// 1. Ambil data pesanan
$stmt = $conn->prepare("
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

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("s", $orderNumber);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// 2. Ambil item layanan
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

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
}

function rupiah($num): string {
    return "Rp" . number_format((float)$num, 0, ",", ".");
}

$isPaid = in_array($order["payment_status"], ["settlement", "capture", "lunas"], true);
$statusText = $isPaid ? "LUNAS" : "MENUNGGU PEMBAYARAN";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($order["order_number"]) ?> | Balla’Fresh Digital</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --green: #3a6932;
            --green-dark: #24461f;
            --green-soft: #f4f8f2;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #f1f5f9;
            color: var(--text);
            font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 30px 15px;
        }

        .no-print-bar {
            max-width: 840px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #fff;
            padding: 12px 20px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green);
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-print:hover {
            background: var(--green-dark);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .invoice-sheet {
            max-width: 840px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 44px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 28px;
        }

        .brand-box img {
            height: 48px;
            width: auto;
            margin-bottom: 10px;
        }

        .agency-meta {
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.6;
        }

        .inv-title-box {
            text-align: right;
        }

        .inv-title {
            font-family: "Manrope", sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: .02em;
        }

        .inv-number {
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            margin-top: 4px;
        }

        .inv-status-pill {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-unpaid {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .info-card {
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
        }

        .info-card h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 800;
        }

        .info-card strong {
            display: block;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .info-card p {
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
        }

        .table-invoice {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .table-invoice th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 12px 14px;
            border-bottom: 2px solid #cbd5e1;
            text-align: left;
        }

        .table-invoice td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
            color: #334155;
        }

        .table-invoice tr:last-child td {
            border-bottom: none;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .summary-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-top: 14px;
            border-top: 2px solid #e2e8f0;
            gap: 20px;
        }

        .payment-meta-box {
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.7;
            max-width: 440px;
        }

        .payment-meta-box strong {
            color: #0f172a;
        }

        .calculation-box {
            min-width: 260px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13.5px;
            color: #475569;
        }

        .calc-total {
            border-top: 2px solid #0f172a;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
        }

        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #94a3b8;
        }

        .stamp-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid <?= $isPaid ? '#16a34a' : '#d97706' ?>;
            color: <?= $isPaid ? '#16a34a' : '#d97706' ?>;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: .08em;
            text-transform: uppercase;
            transform: rotate(-3deg);
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-bar { display: none !important; }
            .invoice-sheet { box-shadow: none; border: none; padding: 0; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <a href="javascript:history.back()" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
    <div style="display:flex;gap:10px">
        <button type="button" class="btn-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Cetak Invoice / Simpan PDF
        </button>
    </div>
</div>

<div class="invoice-sheet">
    <div class="inv-header">
        <div class="brand-box">
            <img src="logo-balla-fresh.png" onerror="this.style.display='none'">
            <div class="agency-meta">
                <strong>Balla’ Fresh Digital Agency</strong><br>
                Solusi Pemasaran &amp; Digitalisasi Bisnis Hidroponik<br>
                WhatsApp: +62 812-3456-7890 · Email: info@ballafresh.id
            </div>
        </div>

        <div class="inv-title-box">
            <div class="inv-title">INVOICE RESMI</div>
            <div class="inv-number"><?= e($order["order_number"]) ?></div>
            <span class="inv-status-pill <?= $isPaid ? 'status-paid' : 'status-unpaid' ?>">
                <i class="fa-solid <?= $isPaid ? 'fa-circle-check' : 'fa-hourglass-half' ?>"></i>
                <?= $statusText ?>
            </span>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <h4>Diterbitkan Untuk</h4>
            <strong><?= e($order["customer_name"]) ?></strong>
            <p>
                <?= !empty($order["business_name"]) ? "Usaha: " . e($order["business_name"]) . "<br>" : "" ?>
                <?= !empty($order["customer_email"]) ? "Email: " . e($order["customer_email"]) . "<br>" : "" ?>
                <?= !empty($order["customer_phone"]) ? "Telepon/WA: " . e($order["customer_phone"]) . "<br>" : "" ?>
                <?= !empty($order["customer_address"]) ? e($order["customer_address"]) : "" ?>
            </p>
        </div>

        <div class="info-card">
            <h4>Rincian Transaksi</h4>
            <p>
                <strong>Tanggal Pemesanan:</strong> <?= date("d F Y, H:i", strtotime((string)$order["created_at"])) ?> WIB<br>
                <strong>Metode Pembayaran:</strong> <?= e(strtoupper((string)($order["payment_type"] ?: $order["payment_method"]))) ?><br>
                <?php if (!empty($order["midtrans_transaction_id"])): ?>
                    <strong>ID Transaksi Midtrans:</strong> <span style="font-family:monospace"><?= e($order["midtrans_transaction_id"]) ?></span><br>
                <?php endif; ?>
                <?php if (!empty($order["settlement_time"])): ?>
                    <strong>Waktu Penyelesaian:</strong> <?= date("d F Y, H:i", strtotime((string)$order["settlement_time"])) ?> WIB<br>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <table class="table-invoice">
        <thead>
            <tr>
                <th style="width:40px" class="text-center">No</th>
                <th>Deskripsi Layanan Digital</th>
                <th style="width:90px" class="text-center">Qty</th>
                <th style="width:140px" class="text-right">Harga Satuan</th>
                <th style="width:150px" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td>
                            <strong><?= e($item["service_name"]) ?></strong>
                        </td>
                        <td class="text-center"><?= (int)$item["quantity"] ?></td>
                        <td class="text-right"><?= rupiah($item["unit_price"]) ?></td>
                        <td class="text-right"><strong><?= rupiah($item["subtotal"]) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center" style="padding:20px;color:var(--muted)">Layanan pemesanan digital</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="summary-wrap">
        <div class="payment-meta-box">
            <?php if ($isPaid): ?>
                <div class="stamp-box">
                    <i class="fa-solid fa-stamp"></i> LUNAS · MIDTRANS VERIFIED
                </div>
                <p style="margin-top:10px">
                    Bukti pembayaran ini sah dan diterbitkan secara elektronik oleh sistem transaksi Balla’ Fresh Digital bekerja sama dengan payment gateway Midtrans Sandbox.
                </p>
            <?php else: ?>
                <div class="stamp-box">
                    <i class="fa-solid fa-clock"></i> MENUNGGU PEMBAYARAN
                </div>
                <p style="margin-top:10px">
                    Tagihan ini menunggu konfirmasi pembayaran melalui sistem Midtrans. Setelah pembayaran berhasil, status invoice akan otomatis menjadi LUNAS.
                </p>
            <?php endif; ?>
        </div>

        <div class="calculation-box">
            <div class="calc-row">
                <span>Subtotal Layanan</span>
                <span><?= rupiah($order["total_amount"]) ?></span>
            </div>
            <div class="calc-row">
                <span>Pajak &amp; Biaya Admin</span>
                <span>Rp0</span>
            </div>
            <div class="calc-row calc-total">
                <span>Total Investasi</span>
                <span style="color:var(--green)"><?= rupiah($order["total_amount"]) ?></span>
            </div>
        </div>
    </div>

    <div class="invoice-footer">
        <div>Balla’ Fresh Digital Agency · Dokumen Sah Elektronik</div>
        <div>Dicetak pada: <?= date("d/m/Y H:i") ?> WIB</div>
    </div>
</div>

</body>
</html>

