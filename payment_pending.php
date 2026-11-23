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

// 2. Helper Sinkronisasi Status Pembayaran ke Database
function applySettlementSuccess($conn, array &$order, array $stData, string $transId, string $payType, string $settleTime, string $midStatus): void {
    $orderIdInt = (int)$order["id"];

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
        $upd->bind_param("ssssi", $transId, $payType, $settleTime, $midStatus, $orderIdInt);
        $upd->execute();
        $upd->close();
    }

    $insLog = $conn->prepare("
        INSERT INTO order_transactions
            (order_id, order_number, midtrans_transaction_id, payment_type, gross_amount, transaction_status, raw_response, settlement_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($insLog) {
        $rawStr = json_encode($stData, JSON_UNESCAPED_UNICODE);
        $amt = (float)$order["total_amount"];
        $insLog->bind_param("isssdsss", $order["id"], $order["order_number"], $transId, $payType, $amt, $midStatus, $rawStr, $settleTime);
        $insLog->execute();
        $insLog->close();
    }

    $order["payment_status"] = "settlement";
    $order["status"] = ($order["status"] === "pending") ? "processing" : $order["status"];
    $order["midtrans_transaction_id"] = $transId;
    $order["payment_type"] = $payType;
    $order["settlement_time"] = $settleTime;
}

// 3. Endpoint AJAX: Cek Status Pembayaran Realtime
if (isset($_GET["action"]) && $_GET["action"] === "check_status") {
    header("Content-Type: application/json; charset=utf-8");

    // Jika di database sudah tercatat lunas
    if (in_array($order["payment_status"], ["settlement", "capture", "lunas"], true)) {
        echo json_encode([
            "success" => true,
            "paid" => true,
            "status" => "settlement",
            "redirect_url" => "payment_success.php?order_id=" . urlencode($orderNumber),
            "message" => "Pembayaran terverifikasi lunas!"
        ]);
        exit;
    }

    // Jika terhubung ke Midtrans, cek status langsung ke API Sandbox
    if (isMidtransConfigured()) {
        $apiStatus = getMidtransTransactionStatus($orderNumber);
        if (!empty($apiStatus["success"]) && isset($apiStatus["data"])) {
            $stData = $apiStatus["data"];
            $midStatus = $stData["transaction_status"] ?? "";
            $fraudStatus = $stData["fraud_status"] ?? "";
            $transId = $stData["transaction_id"] ?? ($order["midtrans_transaction_id"] ?? "");
            $payType = $stData["payment_type"] ?? ($order["payment_type"] ?? "midtrans");
            $settleTime = $stData["settlement_time"] ?? date("Y-m-d H:i:s");

            if ($midStatus === "settlement" || ($midStatus === "capture" && $fraudStatus === "accept")) {
                applySettlementSuccess($conn, $order, $stData, $transId, $payType, $settleTime, $midStatus);

                echo json_encode([
                    "success" => true,
                    "paid" => true,
                    "status" => "settlement",
                    "redirect_url" => "payment_success.php?order_id=" . urlencode($orderNumber),
                    "message" => "Pembayaran berhasil diterima oleh sistem!"
                ]);
                exit;
            } elseif (in_array($midStatus, ["deny", "expire", "cancel"], true)) {
                $updCancel = $conn->prepare("UPDATE orders SET payment_status = ?, status = 'cancelled' WHERE id = ?");
                if ($updCancel) {
                    $orderIdInt = (int)$order["id"];
                    $updCancel->bind_param("si", $midStatus, $orderIdInt);
                    $updCancel->execute();
                    $updCancel->close();
                }

                echo json_encode([
                    "success" => true,
                    "paid" => false,
                    "expired" => true,
                    "status" => $midStatus,
                    "redirect_url" => "payment_failed.php?order_id=" . urlencode($orderNumber),
                    "message" => "Transaksi telah berakhir ({$midStatus})."
                ]);
                exit;
            }
        }
    }

    echo json_encode([
        "success" => true,
        "paid" => false,
        "status" => $order["payment_status"] ?? "pending",
        "message" => "Pembayaran belum terdeteksi. Silakan selesaikan pembayaran sesuai petunjuk lalu klik cek kembali."
    ]);
    exit;
}

// 4. Jika pesanan sudah lunas, langsung arahkan ke halaman sukses
if (in_array($order["payment_status"], ["settlement", "capture", "lunas"], true)) {
    header("Location: payment_success.php?order_id=" . urlencode($orderNumber));
    exit;
}

// 5. Jika pesanan dibatalkan atau kedaluwarsa, arahkan ke payment_failed
if (in_array($order["payment_status"], ["deny", "expire", "cancel"], true)) {
    header("Location: payment_failed.php?order_id=" . urlencode($orderNumber));
    exit;
}

// 6. Cek status ke Midtrans API saat muat halaman pertama kali
$chargeError = null;
if (isMidtransConfigured()) {
    $apiStatus = getMidtransTransactionStatus($orderNumber);
    if (!empty($apiStatus["success"]) && isset($apiStatus["data"])) {
        $stData = $apiStatus["data"];
        $midStatus = $stData["transaction_status"] ?? "";
        $fraudStatus = $stData["fraud_status"] ?? "";
        $transId = $stData["transaction_id"] ?? ($order["midtrans_transaction_id"] ?? "");
        $payType = $stData["payment_type"] ?? ($order["payment_type"] ?? "midtrans");
        $settleTime = $stData["settlement_time"] ?? date("Y-m-d H:i:s");

        if ($midStatus === "settlement" || ($midStatus === "capture" && $fraudStatus === "accept")) {
            applySettlementSuccess($conn, $order, $stData, $transId, $payType, $settleTime, $midStatus);
            header("Location: payment_success.php?order_id=" . urlencode($orderNumber));
            exit;
        } elseif (in_array($midStatus, ["deny", "expire", "cancel"], true)) {
            header("Location: payment_failed.php?order_id=" . urlencode($orderNumber));
            exit;
        }

        // Lengkapi data jika di DB masih kosong namun ada di response status Midtrans
        if (empty($order["payment_code"])) {
            if (!empty($stData["va_numbers"][0]["va_number"])) {
                $order["payment_code"] = $stData["va_numbers"][0]["va_number"];
            } elseif (!empty($stData["bill_key"])) {
                $order["payment_code"] = $stData["bill_key"];
            } elseif (!empty($stData["payment_code"])) {
                $order["payment_code"] = $stData["payment_code"];
            }
        }
        if (empty($order["biller_code"]) && !empty($stData["biller_code"])) {
            $order["biller_code"] = $stData["biller_code"];
        }
        if (empty($order["qr_url"]) && isset($stData["actions"]) && is_array($stData["actions"])) {
            foreach ($stData["actions"] as $act) {
                if (($act["name"] ?? "") === "generate-qr-code") {
                    $order["qr_url"] = $act["url"] ?? null;
                } elseif (($act["name"] ?? "") === "deeplink-redirect") {
                    $order["deeplink_url"] = $act["url"] ?? null;
                }
            }
        }
    }
}

// 7. Jika metode Midtrans tetapi belum ada kode pembayaran/QR (misal saat checkout Midtrans gagal/kunci baru dimasukkan),
// generate transaksi Core API untuk order yang sama tanpa membuat pesanan baru
$requestedRetry = isset($_GET["action"]) && $_GET["action"] === "retry_charge";
$needsChargeGeneration = (empty($order["payment_code"]) && empty($order["qr_url"]) && $order["payment_method"] === "midtrans");

if (($needsChargeGeneration || $requestedRetry) && isMidtransConfigured()) {
    $itStmt = $conn->prepare("SELECT service_id, service_name, quantity, unit_price, subtotal FROM order_items WHERE order_id = ?");
    $itemDetails = [];
    $itemsSum = 0;
    if ($itStmt) {
        $orderIdInt = (int)$order["id"];
        $itStmt->bind_param("i", $orderIdInt);
        $itStmt->execute();
        $res = $itStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $p = (int) round((float)$row["unit_price"]);
            $q = (int) $row["quantity"];
            $itemDetails[] = [
                "id" => (string)$row["service_id"],
                "price" => $p,
                "quantity" => $q,
                "name" => mb_substr((string)$row["service_name"], 0, 50, "UTF-8")
            ];
            $itemsSum += ($p * $q);
        }
        $itStmt->close();
    }

    $grossAmountInt = (int) round((float)$order["total_amount"]);
    if (empty($itemDetails)) {
        $itemDetails[] = [
            "id" => "1",
            "price" => $grossAmountInt,
            "quantity" => 1,
            "name" => "Layanan Balla Fresh"
        ];
        $itemsSum = $grossAmountInt;
    } elseif ($itemsSum !== $grossAmountInt) {
        $diff = $grossAmountInt - $itemsSum;
        $lastIdx = count($itemDetails) - 1;
        $itemDetails[$lastIdx]["price"] += $diff;
    }

    $customerDetails = [
        "first_name" => $order["customer_name"] ?? "Pelanggan",
        "email" => $order["customer_email"] ?? "",
        "phone" => $order["customer_phone"] ?? "",
        "billing_address" => [
            "first_name" => $order["customer_name"] ?? "Pelanggan",
            "address" => mb_substr((string)($order["customer_address"] ?? ""), 0, 200, "UTF-8")
        ]
    ];

    $subMethodToCharge = !empty($order["payment_type"]) ? $order["payment_type"] : "qris";

    $chargeRes = createMidtransCoreCharge(
        $subMethodToCharge,
        $orderNumber,
        (float)$order["total_amount"],
        $customerDetails,
        $itemDetails
    );

    if (!empty($chargeRes["success"])) {
        $order["payment_code"] = $chargeRes["payment_code"] ?? $order["payment_code"];
        $order["biller_code"] = $chargeRes["biller_code"] ?? $order["biller_code"];
        $order["qr_url"] = $chargeRes["qr_url"] ?? $order["qr_url"];
        $order["qr_string"] = $chargeRes["qr_string"] ?? $order["qr_string"];
        $order["deeplink_url"] = $chargeRes["deeplink_url"] ?? $order["deeplink_url"];
        $order["expiry_time"] = !empty($chargeRes["expiry_time"]) ? date("Y-m-d H:i:s", strtotime((string)$chargeRes["expiry_time"])) : $order["expiry_time"];
        $order["midtrans_transaction_id"] = $chargeRes["transaction_id"] ?? $order["midtrans_transaction_id"];

        $updOrder = $conn->prepare("
            UPDATE orders
            SET payment_code = ?,
                biller_code = ?,
                qr_url = ?,
                qr_string = ?,
                deeplink_url = ?,
                expiry_time = ?,
                midtrans_transaction_id = ?,
                midtrans_response = ?
            WHERE id = ?
        ");
        if ($updOrder) {
            $orderIdInt = (int)$order["id"];
            $updOrder->bind_param(
                "ssssssssi",
                $order["payment_code"],
                $order["biller_code"],
                $order["qr_url"],
                $order["qr_string"],
                $order["deeplink_url"],
                $order["expiry_time"],
                $order["midtrans_transaction_id"],
                $chargeRes["raw_response"],
                $orderIdInt
            );
            $updOrder->execute();
            $updOrder->close();
        }

        if ($requestedRetry) {
            header("Location: payment_pending.php?order_id=" . urlencode($orderNumber));
            exit;
        }
    } else {
        $chargeError = $chargeRes["error"] ?? "Gagal mendapatkan kode pembayaran dari Midtrans Sandbox.";
    }
}

// 8. Tentukan metadata sub-metode pembayaran
$subMethod = $order["payment_type"] ?: ($order["payment_method"] === "midtrans" ? "qris" : $order["payment_method"]);
$meta = function_exists("getPaymentSubMethodMeta") ? getPaymentSubMethodMeta($subMethod) : [];
$isMidtrans = ($order["payment_method"] === "midtrans");

// Tentukan simulator URL yang relevan
$simulatorUrl = $meta["simulator_url"] ?? "https://simulator.sandbox.midtrans.com/";
if ($subMethod === "qris" || $subMethod === "shopeepay") {
    $simulatorUrl = "https://simulator.sandbox.midtrans.com/v2/qris/index";
} elseif (in_array($subMethod, ["bca_va", "bni_va", "bri_va", "mandiri_va"], true)) {
    $simulatorUrl = "https://simulator.sandbox.midtrans.com/openapi/va/index";
}

// Persiapkan displayable QR URL
$displayQrUrl = !empty($order["qr_url"]) ? (string)$order["qr_url"] : "";
if (empty($displayQrUrl)) {
    if (!empty($order["qr_string"])) {
        $displayQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=" . urlencode((string)$order["qr_string"]);
    } else {
        $mockQrPayload = "00020101021226670016ID.CO.SHOPEE.WWW01189360091800000000000215" . $order["order_number"] . "520458125303360540" . round((float)$order["total_amount"]) . "5802ID5911Balla Fresh6007Makassar6304ABCD";
        $displayQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=" . urlencode($mockQrPayload);
    }
}

// 9. Hitung countdown waktu kedaluwarsa
$expiryTs = !empty($order["expiry_time"]) ? strtotime((string)$order["expiry_time"]) : (strtotime((string)$order["created_at"]) + 86400);
$nowTs = time();
$remainingSeconds = max(0, $expiryTs - $nowTs);

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
    <title>Menunggu Pembayaran · <?= e($orderNumber) ?> | Balla’Fresh</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --green: #3a6932;
            --green-dark: #24461f;
            --green-soft: #f4f8f2;
            --bg: #f8faf6;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --orange: #f59e0b;
            --orange-dark: #d97706;
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
            max-width: 860px;
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
            max-width: 820px;
            margin: 36px auto 60px;
            padding: 0 20px;
            width: 100%;
        }

        .pending-card {
            background: #ffffff;
            border-radius: 28px;
            border: 1px solid #fed7aa;
            box-shadow: var(--shadow-lg);
            padding: 40px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .pending-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #d97706);
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fef3c7;
            color: #d97706;
            display: grid;
            place-items: center;
            font-size: 34px;
            margin: 0 auto 20px;
            box-shadow: 0 0 0 10px #fffbeb;
            animation: pulseTimer 2.5s infinite;
        }

        @keyframes pulseTimer {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.35); }
            70% { box-shadow: 0 0 0 16px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        .badge-pending {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fffbeb;
            color: #b45309;
            font-weight: 800;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #fde68a;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        h1 {
            font-family: "Manrope", sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .lead-text {
            color: #475569;
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* Countdown Timer Box */
        .timer-card {
            background: #fffdf5;
            border: 1px solid #fde68a;
            border-radius: 18px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .timer-label {
            font-size: 13.5px;
            font-weight: 700;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timer-digits {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: "JetBrains Mono", monospace;
        }

        .timer-unit {
            background: #ffffff;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 18px;
            font-weight: 800;
            color: #b45309;
            min-width: 44px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(217, 119, 6, 0.08);
        }

        .timer-sep {
            font-weight: 800;
            color: #d97706;
            font-size: 16px;
        }

        /* Order Summary Table */
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 22px;
            text-align: left;
            margin-bottom: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0;
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
        .info-value { font-weight: 700; color: #0f172a; text-align: right; }

        /* Payment Channel Display */
        .payment-channel-box {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 22px;
            padding: 26px;
            margin-bottom: 26px;
            text-align: center;
            position: relative;
        }

        .channel-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .channel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 800;
            color: #ffffff;
            background: <?= e($meta["badge_color"] ?? "#3a6932") ?>;
        }

        /* QRIS Styling */
        .qris-card {
            display: inline-block;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
            margin: 10px auto 18px;
            max-width: 320px;
            width: 100%;
        }

        .qris-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .qris-brand-title {
            font-weight: 800;
            font-size: 13px;
            letter-spacing: .06em;
            color: #dc2626;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .qris-img-wrap {
            position: relative;
            background: #ffffff;
            padding: 8px;
            border-radius: 12px;
            display: inline-block;
        }

        .qris-img-wrap img {
            width: 220px;
            height: 220px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .qris-footer-text {
            font-size: 11px;
            color: #64748b;
            margin-top: 10px;
            line-height: 1.4;
        }

        /* Virtual Account / Code Box */
        .code-display-wrap {
            margin: 14px 0 18px;
        }

        .code-display-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            max-width: 480px;
            margin: 0 auto;
        }

        .code-value {
            font-family: "JetBrains Mono", monospace;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: .08em;
            word-break: break-all;
        }

        .code-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 700;
            text-align: left;
        }

        .btn-copy {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-copy:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .btn-copy.copied {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }

        /* Buttons & Actions */
        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-primary-check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--green);
            color: #ffffff;
            padding: 16px 28px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 15px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(58, 105, 50, 0.28);
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-primary-check:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
        }

        .btn-simulator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #0f172a;
            color: #38bdf8;
            padding: 13px 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-simulator:hover {
            background: #1e293b;
            color: #7dd3fc;
            transform: translateY(-1px);
        }

        .btn-deeplink {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #0081a0;
            color: #ffffff;
            padding: 14px 24px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14.5px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0, 129, 160, 0.3);
            transition: all 0.2s ease;
            width: 100%;
            margin-top: 10px;
        }

        .btn-deeplink:hover {
            background: #006b85;
            transform: translateY(-2px);
        }

        /* Instruction Box */
        .guide-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 18px;
            padding: 22px;
            text-align: left;
            margin-top: 24px;
        }

        .guide-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #166534;
            font-weight: 800;
            font-size: 14.5px;
            margin-bottom: 12px;
        }

        .guide-list {
            font-size: 13px;
            color: #334155;
            line-height: 1.7;
            padding-left: 20px;
        }

        .guide-list li {
            margin-bottom: 6px;
        }

        .guide-list a {
            color: #16a34a;
            font-weight: 700;
            text-decoration: underline;
        }

        .guide-list code {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 2px 6px;
            border-radius: 6px;
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            color: #0f172a;
        }

        /* Notification Toast */
        #toastNotice {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
        }

        #toastNotice.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .links-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 24px;
            flex-wrap: wrap;
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
            .pending-card { padding: 28px 18px; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 4px; }
            .info-value { text-align: left; }
            .code-display-card { flex-direction: column; align-items: flex-start; }
            .timer-card { justify-content: center; }
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
    <div class="pending-card">
        <div class="icon-circle">
            <i class="fa-regular fa-clock"></i>
        </div>

        <span class="badge-pending">
            <i class="fa-solid fa-hourglass-half"></i> Menunggu Pembayaran
        </span>

        <h1>Selesaikan Pembayaran Anda</h1>
        <p class="lead-text">
            Pesanan Anda dengan nomor <strong><?= e($order["order_number"]) ?></strong> berhasil dicatat. Silakan lakukan pembayaran sebelum batas waktu berakhir agar layanan segera diproses.
        </p>

        <!-- COUNTDOWN TIMER -->
        <div class="timer-card">
            <div class="timer-label">
                <i class="fa-solid fa-stopwatch" style="color:#d97706;font-size:16px"></i>
                <span>Sisa Waktu Pembayaran:</span>
            </div>
            <div class="timer-digits">
                <div class="timer-unit" id="timerHours">00</div>
                <span class="timer-sep">:</span>
                <div class="timer-unit" id="timerMinutes">00</div>
                <span class="timer-sep">:</span>
                <div class="timer-unit" id="timerSeconds">00</div>
            </div>
        </div>

        <!-- ORDER SUMMARY -->
        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Nomor Pesanan</span>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="info-value" style="font-family:'JetBrains Mono',monospace"><?= e($order["order_number"]) ?></span>
                    <button type="button" class="btn-copy" style="padding:2px 8px;font-size:11px" onclick="copyText('<?= e($order["order_number"]) ?>', this)">
                        <i class="fa-regular fa-copy"></i> Salin
                    </button>
                </div>
            </div>
            <div class="info-row">
                <span class="info-label">Pelanggan &amp; Usaha</span>
                <span class="info-value"><?= e($order["customer_name"] ?? "Mitra") ?> · <?= e($order["business_name"] ?: "Balla' Fresh Mitra") ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Metode Pembayaran</span>
                <span class="info-value">
                    <i class="fa-solid <?= e($meta["icon"] ?? "fa-credit-card") ?>" style="color:<?= e($meta["badge_color"] ?? "#3a6932") ?>"></i>
                    <?= e($meta["label"] ?? strtoupper((string)$order["payment_method"])) ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Tagihan</span>
                <span class="info-value" style="color:#d97706;font-size:18px;font-weight:800"><?= rupiah($order["total_amount"]) ?></span>
            </div>
        </div>

        <!-- PAYMENT METHOD DISPLAY -->
        <div class="payment-channel-box">
            <div class="channel-header">
                <span class="channel-badge">
                    <i class="fa-solid <?= e($meta["icon"] ?? "fa-credit-card") ?>"></i>
                    <?= e($meta["label"] ?? "Pembayaran Midtrans") ?>
                </span>
            </div>

            <?php if ($subMethod === "qris"): ?>
                <!-- 1. TAMPILAN KHUSUS QRIS -->
                <div class="qris-card">
                    <div class="qris-header-bar">
                        <span class="qris-brand-title">
                            <i class="fa-solid fa-qrcode"></i> QRIS
                        </span>
                        <span style="font-size:11px;font-weight:700;color:#64748b">NMID: ID1020021...</span>
                    </div>

                    <div class="qris-img-wrap">
                        <img src="<?= e($displayQrUrl) ?>" alt="QRIS Midtrans Sandbox" id="qrisImage">
                    </div>

                    <div class="qris-footer-text">
                        Scan dengan GoPay, OVO, ShopeePay, DANA, BCA Mobile, atau aplikasi e-wallet apa pun.
                    </div>
                </div>

                <div style="margin: 6px 0 16px; display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="btn-copy" style="padding:10px 18px;font-size:13.5px;background:#f8fafc" onclick="copyText('<?= e($displayQrUrl) ?>', this)">
                        <i class="fa-regular fa-copy"></i> Salin URL Gambar QR untuk Simulator
                    </button>
                    <a href="https://simulator.sandbox.midtrans.com/v2/qris/index" target="_blank" rel="noopener noreferrer" class="btn-copy" style="padding:10px 18px;font-size:13.5px;background:#0f172a;color:#38bdf8;border-color:#1e293b;text-decoration:none">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Simulator QRIS Midtrans
                    </a>
                </div>

            <?php elseif (in_array($subMethod, ["bca_va", "bni_va", "bri_va"], true)): ?>
                <!-- 2. TAMPILAN KHUSUS VIRTUAL ACCOUNT BANK -->
                <div class="code-display-wrap">
                    <div class="code-title">Nomor Virtual Account (<?= e($meta["short_label"] ?? "VA") ?>)</div>
                    <div class="code-display-card">
                        <div class="code-value"><?= e($order["payment_code"] ?: "Menyiapkan nomor VA...") ?></div>
                        <?php if (!empty($order["payment_code"])): ?>
                        <button type="button" class="btn-copy" onclick="copyText('<?= e($order["payment_code"]) ?>', this)">
                            <i class="fa-regular fa-copy"></i> Salin Nomor VA
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($subMethod === "mandiri_va"): ?>
                <!-- 3. TAMPILAN KHUSUS MANDIRI BILL PAYMENT -->
                <div class="code-display-wrap" style="display:grid;gap:12px;max-width:480px;margin:14px auto 18px">
                    <div>
                        <div class="code-title">Kode Perusahaan (Biller Code)</div>
                        <div class="code-display-card" style="margin:0">
                            <div class="code-value"><?= e($order["biller_code"] ?: "70012") ?></div>
                            <button type="button" class="btn-copy" onclick="copyText('<?= e($order["biller_code"] ?: "70012") ?>', this)">
                                <i class="fa-regular fa-copy"></i> Salin Kode
                            </button>
                        </div>
                    </div>
                    <div>
                        <div class="code-title">Kode Pembayaran (Bill Key)</div>
                        <div class="code-display-card" style="margin:0">
                            <div class="code-value"><?= e($order["payment_code"] ?: "-") ?></div>
                            <?php if (!empty($order["payment_code"])): ?>
                            <button type="button" class="btn-copy" onclick="copyText('<?= e($order["payment_code"]) ?>', this)">
                                <i class="fa-regular fa-copy"></i> Salin Kode
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($subMethod === "gopay" || $subMethod === "shopeepay"): ?>
                <!-- 4. TAMPILAN GOPAY / SHOPEEPAY -->
                <?php if (!empty($order["qr_url"])): ?>
                <div class="qris-card">
                    <div class="qris-img-wrap">
                        <img src="<?= e($order["qr_url"]) ?>" alt="<?= e($subMethod) ?> QR">
                    </div>
                </div>
                <div style="margin: 6px 0 12px">
                    <button type="button" class="btn-copy" style="padding:9px 16px;font-size:13px" onclick="copyText('<?= e($order["qr_url"]) ?>', this)">
                        <i class="fa-regular fa-copy"></i> Salin URL Gambar QR
                    </button>
                </div>
                <?php endif; ?>

                <?php if (!empty($order["deeplink_url"])): ?>
                <a href="<?= e($order["deeplink_url"]) ?>" target="_blank" class="btn-deeplink">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Aplikasi <?= e($meta["short_label"] ?? "E-Wallet") ?> / Bayar Langsung
                </a>
                <?php endif; ?>

            <?php else: ?>
                <!-- 5. TAMPILAN TRANSFER MANUAL -->
                <div class="code-display-wrap">
                    <div class="code-title">Nomor Rekening PT Balla Fresh Digital</div>
                    <div class="code-display-card">
                        <div class="code-value">
                            <?= ($subMethod === "mandiri") ? "1520-0098-7654" : (($subMethod === "bri") ? "0341-0100-2345-530" : "7920-1829-33") ?>
                        </div>
                        <button type="button" class="btn-copy" onclick="copyText(this.previousElementSibling.textContent.trim(), this)">
                            <i class="fa-regular fa-copy"></i> Salin Rekening
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- KOTAK PERINGATAN JIKA MIDTRANS ERROR / BELUM DIKONFIGURASI -->
            <?php if (!empty($chargeError)): ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;padding:16px 20px;border-radius:14px;color:#92400e;text-align:left;font-size:13px;line-height:1.6;margin-top:16px">
                    <strong style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:6px">
                        <i class="fa-solid fa-triangle-exclamation" style="color:#d97706"></i> Informasi Midtrans Sandbox
                    </strong>
                    <p style="margin-bottom:8px"><?= e($chargeError) ?></p>
                    <p style="font-size:12px;color:#78350f;margin-bottom:12px">
                        <em>Tips:</em> Buka Dashboard Midtrans Sandbox &rarr; <strong>Settings &gt; Access Keys</strong> &rarr; pastikan <strong>IP Whitelist</strong> kosong (tidak dibatasi) agar charge dari localhost/server diizinkan.
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="payment_pending.php?order_id=<?= urlencode($orderNumber) ?>&action=retry_charge" class="btn-copy" style="background:#fff;border-color:#fde68a;color:#92400e;font-weight:800">
                            <i class="fa-solid fa-rotate-right"></i> Coba Generate Ulang Pembayaran
                        </a>
                    </div>
                </div>
            <?php elseif (!isMidtransConfigured() && $isMidtrans): ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;padding:16px 20px;border-radius:14px;color:#92400e;text-align:left;font-size:13px;line-height:1.6;margin-top:16px">
                    <strong style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:6px">
                        <i class="fa-solid fa-triangle-exclamation" style="color:#d97706"></i> Kunci Midtrans Sandbox Belum Diisi
                    </strong>
                    Masukkan Server Key &amp; Client Key pada file <code>midtrans_config.php</code> untuk mengaktifkan kode pembayaran otomatis.
                </div>
            <?php endif; ?>
        </div>

        <!-- TOMBOL AKSI UTAMA -->
        <div class="actions-group">
            <!-- 1. Tombol Cek Status Pembayaran (AJAX) -->
            <button type="button" class="btn-primary-check" id="btnCheckStatus" onclick="checkPaymentStatus(true)">
                <i class="fa-solid fa-rotate-right" id="checkIcon"></i>
                <span id="checkText">Cek Status Pembayaran</span>
            </button>

            <!-- 2. Tombol Shortcut Simulator Midtrans Sandbox -->
            <?php if ($isMidtrans && !empty($simulatorUrl)): ?>
            <a href="<?= e($simulatorUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-simulator">
                <i class="fa-solid fa-flask-vial"></i> Buka Simulator Midtrans Sandbox (Tab Baru)
            </a>
            <?php endif; ?>
        </div>

        <!-- PANDUAN LANGKAH-LANGKAH PEMBAYARAN -->
        <div class="guide-box">
            <div class="guide-title">
                <i class="fa-solid fa-circle-info" style="color:#16a34a;font-size:18px"></i>
                <span>Panduan Simulasi Pembayaran Sandbox</span>
            </div>

            <?php if ($subMethod === "qris"): ?>
                <ol class="guide-list">
                    <li>Tekan tombol <strong>Salin URL Gambar QR untuk Simulator</strong> pada kartu QR di atas.</li>
                    <li>Buka Simulator QRIS resmi Midtrans Sandbox: <a href="https://simulator.sandbox.midtrans.com/v2/qris/index" target="_blank" rel="noopener noreferrer">https://simulator.sandbox.midtrans.com/v2/qris/index</a>.</li>
                    <li>Tempelkan (Paste) URL gambar QR ke kolom input <strong>QR Code Image Url</strong> pada simulator, lalu klik tombol <strong>Scan QR</strong>.</li>
                    <li>Pada halaman simulator Midtrans, lakukan konfirmasi pembayaran.</li>
                    <li>Setelah berhasil di simulator Midtrans, kembali ke tab ini — halaman akan otomatis mendeteksi status <strong>Lunas</strong> dari server Midtrans (atau Anda dapat menekan tombol <strong>Cek Status Pembayaran</strong>) dan mengalihkan Anda ke halaman sukses.</li>
                </ol>
            <?php elseif (in_array($subMethod, ["bca_va", "bni_va", "bri_va"], true)): ?>
                <ol class="guide-list">
                    <li>Salin nomor <strong>Virtual Account</strong> yang tertera di atas.</li>
                    <li>Buka Simulator VA Midtrans: <a href="https://simulator.sandbox.midtrans.com/openapi/va/index" target="_blank" rel="noopener noreferrer">https://simulator.sandbox.midtrans.com/openapi/va/index</a>.</li>
                    <li>Masukkan nomor Virtual Account pada kolom input simulator, lalu klik <strong>Inquire</strong> dan selesaikan dengan tombol <strong>Pay</strong>.</li>
                    <li>Kembali ke halaman ini dan tekan tombol <strong>Cek Status Pembayaran</strong>.</li>
                </ol>
            <?php elseif ($subMethod === "mandiri_va"): ?>
                <ol class="guide-list">
                    <li>Buka Simulator VA / Bill Payment Midtrans di: <a href="https://simulator.sandbox.midtrans.com/openapi/va/index" target="_blank" rel="noopener noreferrer">https://simulator.sandbox.midtrans.com/openapi/va/index</a>.</li>
                    <li>Pilih Bank Mandiri, masukkan Kode Perusahaan <code><?= e($order["biller_code"] ?: "70012") ?></code> dan Kode Pembayaran (Bill Key) <code><?= e($order["payment_code"] ?: "") ?></code>.</li>
                    <li>Lanjutkan konfirmasi dan klik <strong>Pay</strong>.</li>
                    <li>Kembali ke halaman ini lalu klik <strong>Cek Status Pembayaran</strong>.</li>
                </ol>
            <?php elseif ($subMethod === "gopay" || $subMethod === "shopeepay"): ?>
                <ol class="guide-list">
                    <li>Klik tombol <strong>Buka Aplikasi / Bayar Langsung</strong> di atas atau scan QR Code yang muncul.</li>
                    <li>Konfirmasi pembayaran pada simulator / aplikasi.</li>
                    <li>Setelah selesai, klik tombol <strong>Cek Status Pembayaran</strong> pada halaman ini.</li>
                </ol>
            <?php else: ?>
                <ol class="guide-list">
                    <li>Transfer nominal tepat <strong><?= rupiah($order["total_amount"]) ?></strong> ke rekening di atas.</li>
                    <li>Simpan bukti transfer / struk pembayaran Anda.</li>
                    <li>Kirimkan bukti transfer melalui WhatsApp Admin Balla’ Fresh untuk konfirmasi manual.</li>
                </ol>
            <?php endif; ?>
        </div>

        <div class="links-row">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
            <a href="https://wa.me/6281234567890?text=<?= urlencode("Halo Balla Fresh, saya ingin konfirmasi pembayaran pesanan " . $order["order_number"]) ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa-brands fa-whatsapp"></i> Konfirmasi WhatsApp
            </a>
        </div>
    </div>
</main>

<div id="toastNotice">
    <i class="fa-solid fa-circle-check" style="color:#22c55e"></i>
    <span id="toastMsg">Teks berhasil disalin!</span>
</div>

<script>
// 1. Salin Teks ke Clipboard
function copyText(text, btnElement) {
    if (!text) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(showSuccess).catch(fallbackCopy);
    } else {
        fallbackCopy();
    }

    function fallbackCopy() {
        const temp = document.createElement("input");
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        try {
            document.execCommand("copy");
            showSuccess();
        } catch (e) {
            alert("Gagal menyalin otomatis. Silakan salin manual: " + text);
        }
        document.body.removeChild(temp);
    }

    function showSuccess() {
        if (btnElement) {
            const origHtml = btnElement.innerHTML;
            btnElement.classList.add("copied");
            btnElement.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
            setTimeout(function() {
                btnElement.classList.remove("copied");
                btnElement.innerHTML = origHtml;
            }, 2000);
        }

        const toast = document.getElementById("toastNotice");
        const msg = document.getElementById("toastMsg");
        msg.textContent = "Berhasil disalin ke clipboard!";
        toast.classList.add("show");
        setTimeout(function() {
            toast.classList.remove("show");
        }, 2500);
    }
}

// 2. Countdown Timer
let remainingSeconds = <?= (int)$remainingSeconds ?>;
const elHours = document.getElementById("timerHours");
const elMinutes = document.getElementById("timerMinutes");
const elSeconds = document.getElementById("timerSeconds");

function updateTimer() {
    if (remainingSeconds <= 0) {
        elHours.textContent = "00";
        elMinutes.textContent = "00";
        elSeconds.textContent = "00";
        return;
    }

    const h = Math.floor(remainingSeconds / 3600);
    const m = Math.floor((remainingSeconds % 3600) / 60);
    const s = remainingSeconds % 60;

    elHours.textContent = String(h).padStart(2, "0");
    elMinutes.textContent = String(m).padStart(2, "0");
    elSeconds.textContent = String(s).padStart(2, "0");

    remainingSeconds--;
}

updateTimer();
const timerInterval = setInterval(updateTimer, 1000);

// 3. Cek Status Pembayaran (AJAX)
let isChecking = false;

function checkPaymentStatus(isUserClick = false) {
    if (isChecking) return;
    isChecking = true;

    const btn = document.getElementById("btnCheckStatus");
    const icon = document.getElementById("checkIcon");
    const text = document.getElementById("checkText");

    if (icon) icon.className = "fa-solid fa-spinner fa-spin";
    if (text) text.textContent = "Memverifikasi ke Midtrans...";

    fetch("payment_pending.php?order_id=<?= urlencode($orderNumber) ?>&action=check_status", {
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        isChecking = false;
        if (icon) icon.className = "fa-solid fa-rotate-right";
        if (text) text.textContent = "Cek Status Pembayaran";

        if (data.paid && data.redirect_url) {
            const toast = document.getElementById("toastNotice");
            const msg = document.getElementById("toastMsg");
            msg.textContent = "Pembayaran Berhasil! Mengalihkan...";
            toast.classList.add("show");
            setTimeout(function() {
                window.location.href = data.redirect_url;
            }, 800);
        } else if (data.expired && data.redirect_url) {
            window.location.href = data.redirect_url;
        } else if (isUserClick) {
            const toast = document.getElementById("toastNotice");
            const msg = document.getElementById("toastMsg");
            msg.innerHTML = '<i class="fa-solid fa-clock" style="color:#f59e0b"></i> ' + (data.message || "Pembayaran belum terdeteksi.");
            toast.classList.add("show");
            setTimeout(function() {
                toast.classList.remove("show");
            }, 3500);
        }
    })
    .catch(function(err) {
        isChecking = false;
        if (icon) icon.className = "fa-solid fa-rotate-right";
        if (text) text.textContent = "Cek Status Pembayaran";
        if (isUserClick) {
            alert("Gagal memverifikasi status. Silakan muat ulang halaman.");
        }
    });
}

// 4. Polling Otomatis Setiap 4 Detik (Realtime mendeteksi konfirmasi dari Midtrans Sandbox)
const pollInterval = setInterval(function() {
    checkPaymentStatus(false);
}, 4000);
</script>

</body>
</html>
