<?php
declare(strict_types=1);

/**
 * Midtrans Webhook / Notification Handler
 * Balla' Fresh Agency
 *
 * Menerima HTTP POST notification dari Midtrans saat status transaksi berubah.
 * Memverifikasi signature SHA-512, memeriksa kesesuaian order_id dan nominal,
 * mengupdate status pesanan, serta mencatat event secara idempoten di order_transactions.
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/midtrans_config.php";
require_once __DIR__ . "/db.php";

// 1. Ambil data mentah JSON
$rawInput = file_get_contents("php://input");
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Payload kosong."], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Format JSON tidak valid."], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId           = trim((string)($data["order_id"] ?? ""));
$statusCode        = trim((string)($data["status_code"] ?? ""));
$grossAmount       = trim((string)($data["gross_amount"] ?? ""));
$signatureKey      = trim((string)($data["signature_key"] ?? ""));
$transactionStatus = trim((string)($data["transaction_status"] ?? ""));
$fraudStatus       = trim((string)($data["fraud_status"] ?? ""));
$paymentType       = trim((string)($data["payment_type"] ?? ""));
$transactionId     = trim((string)($data["transaction_id"] ?? ""));
$transactionTime   = $data["transaction_time"] ?? date("Y-m-d H:i:s");
$settlementTime    = $data["settlement_time"] ?? null;

// 2. Verifikasi Kunci Signature
if (!function_exists('verifyMidtransSignature') || !verifyMidtransSignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Signature key tidak valid."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Cek apakah pesanan ada di database
$findOrder = $conn->prepare("SELECT id, order_number, total_amount, status, payment_status FROM orders WHERE order_number = ? LIMIT 1");
if (!$findOrder) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal menyiapkan query database."], JSON_UNESCAPED_UNICODE);
    exit;
}

$findOrder->bind_param("s", $orderId);
$findOrder->execute();
$order = $findOrder->get_result()->fetch_assoc();
$findOrder->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Nomor pesanan tidak ditemukan di database."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Verifikasi nominal transaksi (dengan toleransi pembulatan desimal)
$orderTotal  = (float)$order["total_amount"];
$notifAmount = (float)$grossAmount;
if (abs($orderTotal - $notifAmount) > 1.0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Nominal transaksi tidak cocok dengan tagihan."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Pencegahan Proses Ganda (Idempoten)
if (!empty($transactionId)) {
    $dupCheck = $conn->prepare("SELECT id FROM order_transactions WHERE midtrans_transaction_id = ? AND transaction_status = ? LIMIT 1");
    if ($dupCheck) {
        $dupCheck->bind_param("ss", $transactionId, $transactionStatus);
        $dupCheck->execute();
        $isDuplicate = $dupCheck->get_result()->fetch_assoc();
        $dupCheck->close();

        if ($isDuplicate) {
            http_response_code(200);
            echo json_encode(["status" => "ok", "message" => "Notifikasi sudah pernah diproses sebelumnya (idempotent)."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// 6. Pemetaan Status Pembayaran & Status Pesanan
$orderIdInt           = (int)$order["id"];
$newOrderStatus       = $order["status"];
$newPaymentStatus     = $order["payment_status"];
$actualSettlementTime = null;

$notifPaymentCode = null;
$notifBillerCode = null;
if (!empty($data["va_numbers"][0]["va_number"])) {
    $notifPaymentCode = (string)$data["va_numbers"][0]["va_number"];
} elseif (!empty($data["bill_key"])) {
    $notifPaymentCode = (string)$data["bill_key"];
} elseif (!empty($data["payment_code"])) {
    $notifPaymentCode = (string)$data["payment_code"];
}
if (!empty($data["biller_code"])) {
    $notifBillerCode = (string)$data["biller_code"];
}

if ($transactionStatus === "capture") {
    if ($fraudStatus === "challenge") {
        $newPaymentStatus = "challenge";
    } elseif ($fraudStatus === "accept") {
        $newPaymentStatus     = "settlement";
        $newOrderStatus       = ($order["status"] === "pending") ? "processing" : $order["status"];
        $actualSettlementTime = $settlementTime ?: date("Y-m-d H:i:s");
    }
} elseif ($transactionStatus === "settlement") {
    $newPaymentStatus     = "settlement";
    $newOrderStatus       = ($order["status"] === "pending") ? "processing" : $order["status"];
    $actualSettlementTime = $settlementTime ?: date("Y-m-d H:i:s");
} elseif ($transactionStatus === "pending") {
    $newPaymentStatus = "menunggu_pembayaran";
} elseif (in_array($transactionStatus, ["deny", "expire", "cancel"], true)) {
    $newPaymentStatus = $transactionStatus;
    $newOrderStatus   = "cancelled";
} elseif (in_array($transactionStatus, ["refund", "partial_refund"], true)) {
    $newPaymentStatus = $transactionStatus;
}

// 7. Catat ke tabel order_transactions (Audit Log)
$insLog = $conn->prepare("
    INSERT INTO order_transactions
        (order_id, order_number, midtrans_transaction_id, payment_type, gross_amount, transaction_status, fraud_status, status_code, signature_key, raw_response, settlement_time)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if ($insLog) {
    $insLog->bind_param(
        "isssdssssss",
        $orderIdInt,
        $orderId,
        $transactionId,
        $paymentType,
        $notifAmount,
        $transactionStatus,
        $fraudStatus,
        $statusCode,
        $signatureKey,
        $rawInput,
        $actualSettlementTime
    );
    $insLog->execute();
    $insLog->close();
}

// 8. Update tabel orders
$updateSql = "
    UPDATE orders
    SET midtrans_transaction_id = ?,
        payment_type = ?,
        payment_code = COALESCE(payment_code, ?),
        biller_code = COALESCE(biller_code, ?),
        gross_amount = ?,
        transaction_status = ?,
        payment_status = ?,
        transaction_time = COALESCE(transaction_time, ?),
        settlement_time = COALESCE(?, settlement_time),
        status = ?
    WHERE id = ?
";
$updStmt = $conn->prepare($updateSql);
if ($updStmt) {
    $updStmt->bind_param(
        "ssssdsssssi",
        $transactionId,
        $paymentType,
        $notifPaymentCode,
        $notifBillerCode,
        $notifAmount,
        $transactionStatus,
        $newPaymentStatus,
        $transactionTime,
        $actualSettlementTime,
        $newOrderStatus,
        $orderIdInt
    );
    $updStmt->execute();
    $updStmt->close();
}

http_response_code(200);
echo json_encode([
    "status" => "ok",
    "message" => "Notifikasi Midtrans berhasil diproses.",
    "order_number" => $orderId,
    "payment_status" => $newPaymentStatus,
    "order_status" => $newOrderStatus
], JSON_UNESCAPED_UNICODE);

