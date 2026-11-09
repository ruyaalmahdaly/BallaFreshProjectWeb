<?php
declare(strict_types=1);

require_once __DIR__ . "/session_bootstrap.php";
require_once __DIR__ . "/db.php";

function sendJson($data, $status = 200)
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanText($value)
{
    return trim((string) $value);
}

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function rupiah($value)
{
    return "Rp" . number_format((float) $value, 0, ",", ".");
}

/*
|--------------------------------------------------------------------------
| PROSES PENYIMPANAN PESANAN (API POST)
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        sendJson([
            "success" => false,
            "message" => "Data checkout tidak valid."
        ], 400);
    }

    $name = cleanText($data["name"] ?? "");
    $email = cleanText($data["email"] ?? "");
    $phone = cleanText($data["phone"] ?? "");
    $address = cleanText($data["address"] ?? "");
    $businessName = cleanText($data["business_name"] ?? "");
    $socialMedia = cleanText($data["social_media"] ?? "");
    $websiteUrl = cleanText($data["website_url"] ?? "");
    $paymentMethod = cleanText($data["payment_method"] ?? "midtrans");
    $notes = cleanText($data["notes"] ?? "");
    $items = $data["items"] ?? [];

    $allowedPaymentMethods = ["midtrans", "bca", "mandiri", "bri"];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        $paymentMethod = "midtrans";
    }

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $address === ""
    ) {
        sendJson([
            "success" => false,
            "message" => "Nama, email, nomor HP/WhatsApp, dan alamat wajib diisi."
        ], 422);
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($name) > 100 ||
        strlen($email) > 150 ||
        strlen($phone) > 25 ||
        strlen($address) > 500 ||
        strlen($businessName) > 150 ||
        strlen($socialMedia) > 255 ||
        strlen($websiteUrl) > 255 ||
        strlen($notes) > 2000
    ) {
        sendJson([
            "success" => false,
            "message" => "Periksa kembali data yang Anda masukkan."
        ], 422);
    }

    if (
        !is_array($items) ||
        count($items) < 1 ||
        count($items) > 50
    ) {
        sendJson([
            "success" => false,
            "message" => "Keranjang kosong atau jumlah layanan tidak valid."
        ], 422);
    }

    // Gabungkan ID yang sama dan validasi jumlah
    $quantities = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            sendJson([
                "success" => false,
                "message" => "Format layanan tidak valid."
            ], 422);
        }

        $id = filter_var(
            $item["id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        $qty = filter_var(
            $item["qty"] ?? 1,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1, "max_range" => 20]]
        );

        if ($id === false || $qty === false) {
            sendJson([
                "success" => false,
                "message" => "ID atau jumlah layanan tidak valid."
            ], 422);
        }

        $quantities[$id] = ($quantities[$id] ?? 0) + $qty;

        if ($quantities[$id] > 20) {
            sendJson([
                "success" => false,
                "message" => "Maksimal 20 untuk setiap layanan."
            ], 422);
        }
    }

    try {
        $conn->begin_transaction();

        // 1. Simpan atau perbarui data customer
        $findCustomer = $conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $findCustomer->bind_param("s", $email);
        $findCustomer->execute();
        $existingCustomer = $findCustomer->get_result()->fetch_assoc();
        $findCustomer->close();

        if ($existingCustomer) {
            $customerId = (int) $existingCustomer["id"];
            $customerStmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
            $customerStmt->bind_param("sssi", $name, $phone, $address, $customerId);
            $customerStmt->execute();
            $customerStmt->close();
        } else {
            $customerStmt = $conn->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
            $customerStmt->bind_param("ssss", $name, $email, $phone, $address);
            $customerStmt->execute();
            $customerId = (int) $conn->insert_id;
            $customerStmt->close();
        }

        // 2. Buat nomor pesanan unik
        $orderNumber = "BF-" . date("YmdHis") . "-" . strtoupper(bin2hex(random_bytes(3)));

        // 3. Susun catatan pesanan lengkap
        $paymentLabels = [
            "midtrans" => "Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)",
            "bca" => "Transfer Bank BCA (No. Rek: 7920-1829-33 a.n. PT Balla Fresh Digital)",
            "mandiri" => "Transfer Bank Mandiri (No. Rek: 1520-0098-7654 a.n. PT Balla Fresh Digital)",
            "bri" => "Transfer Bank BRI (No. Rek: 0341-0100-2345-530 a.n. PT Balla Fresh Digital)"
        ];
        $selectedPaymentLabel = $paymentLabels[$paymentMethod] ?? $paymentMethod;

        $notesList = [];
        if ($businessName !== "") {
            $notesList[] = "Nama Usaha: " . $businessName;
        }
        if ($socialMedia !== "") {
            $notesList[] = "Sosial Media: " . $socialMedia;
        }
        if ($websiteUrl !== "") {
            $notesList[] = "Website: " . $websiteUrl;
        }
        $notesList[] = "Metode Pembayaran: " . $selectedPaymentLabel;
        if ($notes !== "") {
            $notesList[] = "Catatan Tambahan: " . $notes;
        }
        $finalNotes = implode("\n", $notesList);

        // 4. Hitung total dan validasi ketersediaan layanan
        $serviceStmt = $conn->prepare("SELECT id, name, price FROM services WHERE id = ? AND is_available = 1 LIMIT 1");
        $totalAmount = 0.0;
        $orderItemsData = [];

        foreach ($quantities as $serviceId => $quantity) {
            $serviceStmt->bind_param("i", $serviceId);
            $serviceStmt->execute();
            $res = $serviceStmt->get_result();
            $service = $res->fetch_assoc();

            if (!$service) {
                throw new Exception("Salah satu layanan sudah tidak tersedia. Muat ulang katalog dan coba lagi.");
            }

            $serviceName = $service["name"];
            $unitPrice = (float) $service["price"];
            $subtotal = $unitPrice * $quantity;

            if (!is_finite($unitPrice) || $unitPrice < 0 || !is_finite($subtotal)) {
                throw new Exception("Harga layanan tidak valid.");
            }

            $totalAmount += $subtotal;
            $orderItemsData[] = [
                "service_id" => $serviceId,
                "name" => $serviceName,
                "quantity" => $quantity,
                "unit_price" => $unitPrice,
                "subtotal" => $subtotal
            ];
        }
        $serviceStmt->close();

        // 5. Simpan pesanan ke tabel orders
        $orderStmt = $conn->prepare("
            INSERT INTO orders
                (order_number, customer_id, total_amount, status, notes, payment_method, business_name, social_media, website_url)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?)
        ");
        $orderStmt->bind_param(
            "sidsssss",
            $orderNumber,
            $customerId,
            $totalAmount,
            $finalNotes,
            $paymentMethod,
            $businessName,
            $socialMedia,
            $websiteUrl
        );
        $orderStmt->execute();
        $orderId = (int) $conn->insert_id;
        $orderStmt->close();

        // 6. Simpan rincian layanan ke order_items
        $itemStmt = $conn->prepare("
            INSERT INTO order_items
                (order_id, service_id, service_name, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($orderItemsData as $it) {
            $itemStmt->bind_param(
                "iisidd",
                $orderId,
                $it["service_id"],
                $it["name"],
                $it["quantity"],
                $it["unit_price"],
                $it["subtotal"]
            );
            $itemStmt->execute();
        }
        $itemStmt->close();

        $conn->commit();

        sendJson([
            "success" => true,
            "message" => "Pesanan berhasil disimpan.",
            "order_number" => $orderNumber,
            "total_amount" => $totalAmount,
            "payment_method" => $paymentMethod,
            "payment_label" => $selectedPaymentLabel,
            "business_name" => $businessName
        ]);

    } catch (Throwable $e) {
        $conn->rollback();
        error_log("Checkout BallaFresh: " . $e->getMessage());

        sendJson([
            "success" => false,
            "message" => in_array($e->getMessage(), [
                "Salah satu layanan sudah tidak tersedia. Muat ulang katalog dan coba lagi.",
                "Harga layanan tidak valid."
            ], true) ? $e->getMessage() : "Pesanan gagal disimpan. Silakan coba lagi."
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PEMILIK AKUN YANG SEDANG LOGIN (AUTO-FILL)
|--------------------------------------------------------------------------
*/

$isUserLoggedIn = isset($_SESSION['user_id']);
$autoName = '';
$autoEmail = '';
$autoPhone = '';
$autoAddress = '';

if ($isUserLoggedIn) {
    $userId = (int) $_SESSION['user_id'];
    $uStmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.phone,
               a.address_details, a.subdistrict, a.city, a.postal_code, a.rt_rw
        FROM users u
        LEFT JOIN user_addresses a ON a.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");

    if ($uStmt) {
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $userData = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();

        if ($userData) {
            $autoName = (string) ($userData['name'] ?? '');
            $autoEmail = (string) ($userData['email'] ?? '');
            $autoPhone = (string) ($userData['phone'] ?? '');

            $addrParts = array_filter([
                $userData['address_details'] ?? '',
                !empty($userData['rt_rw']) && $userData['rt_rw'] !== '-' ? 'RT/RW ' . $userData['rt_rw'] : '',
                $userData['subdistrict'] ?? '',
                $userData['city'] ?? '',
                $userData['postal_code'] ?? ''
            ]);
            $autoAddress = implode(', ', $addrParts);
        }
    }

    // Jika nomor HP atau alamat di tabel users kosong, coba cari dari riwayat customers
    if ($autoEmail !== '' && ($autoPhone === '' || $autoAddress === '')) {
        $cStmt = $conn->prepare("SELECT phone, address FROM customers WHERE email = ? ORDER BY id DESC LIMIT 1");
        if ($cStmt) {
            $cStmt->bind_param("s", $autoEmail);
            $cStmt->execute();
            if ($cRow = $cStmt->get_result()->fetch_assoc()) {
                if ($autoPhone === '' && !empty($cRow['phone'])) {
                    $autoPhone = (string) $cRow['phone'];
                }
                if ($autoAddress === '' && !empty($cRow['address'])) {
                    $autoAddress = (string) $cRow['address'];
                }
            }
            $cStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout &amp; Pembayaran | Balla’Fresh</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --green: #3a6932;
            --green-dark: #24461f;
            --green-light: #5b8121;
            --green-soft: #f4f8f2;
            --green-border: rgba(58, 105, 50, 0.18);
            --lime: #d9f99d;
            --bg: #f8faf6;
            --white: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #b91c1c;
            --orange: #f59e0b;
            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 10px 25px -4px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 20px 35px -8px rgba(15, 23, 42, 0.12);
            --ease: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4 {
            font-family: "Manrope", sans-serif;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        button, input, textarea, select {
            font: inherit;
        }

        /* TOPBAR NAVBAR */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 16px 24px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
        }

        .topbar-inner {
            max-width: 1140px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .brand-link img {
            max-height: 38px;
            width: auto;
        }

        .brand-text {
            font: 800 22px "Manrope", sans-serif;
            color: var(--green);
        }

        .nav-right-tools {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--green);
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: var(--ease);
        }

        .back-link:hover {
            color: var(--green-light);
            transform: translateX(-2px);
        }

        /* PAGE CONTAINER */
        .page {
            max-width: 1140px;
            margin: 36px auto 60px;
            padding: 0 20px;
        }

        .intro {
            margin-bottom: 28px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--green);
            background: var(--green-soft);
            border: 1px solid var(--green-border);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        h1 {
            margin: 12px 0 6px;
            font-size: clamp(26px, 3.5vw, 36px);
            letter-spacing: -.03em;
        }

        .intro p {
            color: var(--muted);
            font-size: 15px;
            max-width: 650px;
        }

        /* BANNER AUTOLOGIN */
        .autofill-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 13px 18px;
            border-radius: 14px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .autofill-banner-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .autofill-banner i {
            font-size: 17px;
            color: #059669;
        }

        .login-prompt-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 13px 18px;
            border-radius: 14px;
            background: #fefce8;
            border: 1px solid #fef08a;
            color: #854d0e;
            font-size: 13.5px;
            margin-bottom: 24px;
        }

        .login-prompt-banner a {
            color: #a16207;
            font-weight: 800;
            text-decoration: underline;
        }

        /* MAIN GRID */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 28px;
            align-items: start;
        }

        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .panel-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .panel h2 {
            font-size: 19px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel h2 i {
            color: var(--green);
            font-size: 17px;
        }

        .badge-status-autofill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            color: #065f46;
            background: #ecfdf5;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #a7f3d0;
        }

        /* FORMS */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .optional-tag {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            color: var(--text);
            outline: none;
            font-size: 14px;
            transition: var(--ease);
        }

        input:focus, textarea:focus, select:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(58, 105, 50, 0.12);
        }

        input.is-autofilled {
            background-color: #fbfdf9;
            border-color: #cde0c9;
        }

        textarea {
            resize: vertical;
            min-height: 85px;
        }

        .help {
            color: var(--muted);
            font-size: 11.5px;
            line-height: 1.5;
        }

        /* PAYMENT METHOD SELECTOR UI */
        .payment-methods-grid {
            display: grid;
            gap: 14px;
        }

        .payment-card {
            border: 1.5px solid var(--border);
            border-radius: 18px;
            padding: 18px 20px;
            cursor: pointer;
            transition: var(--ease);
            background: #fff;
            position: relative;
        }

        .payment-card:hover {
            border-color: rgba(58, 105, 50, 0.4);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .payment-card.selected {
            border-color: var(--green);
            background: #fafcf8;
            box-shadow: 0 0 0 2px rgba(58, 105, 50, 0.15);
        }

        .payment-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .payment-radio-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            display: grid;
            place-items: center;
            transition: var(--ease);
            flex-shrink: 0;
        }

        .custom-radio::after {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--green);
            transform: scale(0);
            transition: var(--ease);
        }

        .payment-card.selected .custom-radio {
            border-color: var(--green);
        }

        .payment-card.selected .custom-radio::after {
            transform: scale(1);
        }

        .payment-title-group h3 {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-recommended {
            background: #fef3c7;
            color: #92400e;
            font-size: 10.5px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid #fde68a;
            letter-spacing: .02em;
        }

        .payment-desc {
            font-size: 12px;
            color: var(--muted);
        }

        .payment-logos {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .channel-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 26px;
            padding: 0 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            border: 1px solid var(--border);
            background: #fff;
            letter-spacing: .03em;
        }

        .channel-qris {
            background: #1e293b;
            color: #fff;
            border-color: #1e293b;
        }

        .channel-gopay {
            background: #00a5cf;
            color: #fff;
            border-color: #00a5cf;
        }

        .channel-shopee {
            background: #ee4d2d;
            color: #fff;
            border-color: #ee4d2d;
        }

        .channel-midtrans {
            background: #002b49;
            color: #00c2ff;
            border-color: #002b49;
            font-weight: 800;
        }

        .payment-details-box {
            display: none;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border);
            font-size: 12.5px;
            color: #334155;
            line-height: 1.6;
        }

        .payment-card.selected .payment-details-box {
            display: block;
        }

        /* 3 BANK CARDS */
        .bank-options-list {
            display: grid;
            gap: 10px;
            margin-top: 10px;
        }

        .bank-sub-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            transition: var(--ease);
        }

        .bank-sub-card:hover {
            border-color: var(--green);
            background: var(--green-soft);
        }

        .bank-sub-card.bank-active {
            border-color: var(--green);
            background: #f3f8f1;
            box-shadow: 0 0 0 1px var(--green);
        }

        .bank-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bank-logo-box {
            width: 68px;
            height: 34px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 14px;
            letter-spacing: .05em;
            color: #fff;
            box-shadow: var(--shadow-sm);
        }

        .bca-logo {
            background: #005aa9;
        }

        .mandiri-logo {
            background: #002d62;
            color: #f7b928;
        }

        .bri-logo {
            background: #00529b;
        }

        .bank-info strong {
            display: block;
            font-size: 13px;
            color: var(--text);
        }

        .bank-info span {
            font-size: 11.5px;
            color: var(--muted);
            font-family: monospace;
            font-weight: 600;
        }

        .btn-copy {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            color: var(--green);
            cursor: pointer;
            transition: var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-copy:hover {
            background: var(--green-soft);
            border-color: var(--green);
        }

        /* RIGHT SUMMARY SIDEBAR */
        .summary-panel {
            position: sticky;
            top: 90px;
        }

        .summary-panel h2 {
            font-size: 18px;
            margin-bottom: 18px;
        }

        .summary-item {
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .summary-item:first-child {
            padding-top: 0;
        }

        .item-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .item-name {
            font-weight: 800;
            font-size: 14px;
            line-height: 1.35;
        }

        .item-price {
            font-weight: 800;
            white-space: nowrap;
            font-size: 13.5px;
            color: #e48e1e;
        }

        .item-detail {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .pricing-breakdown {
            margin-top: 18px;
            display: grid;
            gap: 10px;
            font-size: 13px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
        }

        .price-row.free-badge {
            color: var(--green);
            font-weight: 700;
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px dashed var(--border);
        }

        .total-row span {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .total-row strong {
            color: #e48e1e;
            font: 800 24px "Manrope", sans-serif;
            text-align: right;
        }

        /* PREVIEW METODE PEMBAYARAN DI RINGKASAN */
        .summary-payment-preview {
            margin-top: 18px;
            background: #fafcf8;
            border: 1px solid var(--green-border);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 12px;
        }

        .summary-payment-preview small {
            display: block;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-size: 10.5px;
        }

        .summary-payment-preview strong {
            color: var(--green);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            border: 0;
            border-radius: 16px;
            padding: 16px 20px;
            background: var(--green);
            color: white;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            margin-top: 20px;
            transition: var(--ease);
            box-shadow: 0 4px 14px rgba(58, 105, 50, 0.25);
        }

        .btn-submit:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(58, 105, 50, 0.32);
        }

        .btn-submit:disabled {
            opacity: .6;
            cursor: wait;
            transform: none;
        }

        .security-guarantees {
            list-style: none;
            display: grid;
            gap: 8px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 11.5px;
            color: var(--muted);
        }

        .security-guarantees li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-guarantees i {
            color: var(--green);
            font-size: 13px;
        }

        /* ALERT MESSAGES */
        .message {
            display: none;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 22px;
            line-height: 1.6;
            font-size: 14px;
        }

        .message.error {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: var(--danger);
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .message.success {
            display: block;
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }

        .success-box {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #a7f3d0;
            margin-top: 12px;
            color: var(--text);
        }

        .success-box h3 {
            font-size: 18px;
            color: #065f46;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-badge-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 12px 0 18px;
            flex-wrap: wrap;
        }

        .order-pill {
            font-family: monospace;
            font-size: 15px;
            font-weight: 800;
            background: #f1f5f9;
            color: #0f172a;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        .btn-wa-confirm {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #25d366;
            color: #fff;
            padding: 12px 20px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            text-decoration: none;
            margin-top: 14px;
            transition: var(--ease);
        }

        .btn-wa-confirm:hover {
            background: #1eb956;
            transform: translateY(-1px);
        }

        .empty {
            padding: 24px 0;
            color: var(--muted);
            text-align: center;
            line-height: 1.7;
            font-size: 13.5px;
        }

        .footer-note {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            padding: 30px 20px;
            border-top: 1px solid var(--border);
            background: #fff;
            margin-top: 40px;
        }

        @media (max-width: 900px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            .summary-panel {
                position: static;
                order: -1;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full {
                grid-column: auto;
            }
            .panel {
                padding: 20px;
            }
            .payment-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand-link" href="index.php">
            <img src="logo-balla-fresh.png" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span class="brand-text" style="display:none">Balla’Fresh</span>
        </a>

        <div class="nav-right-tools">
            <a class="back-link" href="index.php#layanan">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Layanan
            </a>
        </div>
    </div>
</header>

<main class="page">
    <div class="intro">
        <span class="eyebrow"><i class="fa-solid fa-bag-shopping"></i> Pemesanan Layanan Digital</span>
        <h1>Selesaikan Pemesanan Mitra</h1>
        <p>Data akun Anda telah disesuaikan secara otomatis. Lengkapi profil usaha dan pilih metode pembayaran yang diinginkan.</p>
    </div>

    <!-- NOTIFIKASI AUTO-FILL AKUN -->
    <?php if ($isUserLoggedIn && !empty($autoName)): ?>
        <div class="autofill-banner">
            <div class="autofill-banner-left">
                <i class="fa-solid fa-circle-check"></i>
                <span>Halo, <strong><?= escapeHtml($autoName) ?></strong>! Formulir pemesanan ini telah otomatis diisi dengan data registrasi akun Anda.</span>
            </div>
            <span class="badge-status-autofill"><i class="fa-solid fa-bolt"></i> Terisi Otomatis</span>
        </div>
    <?php elseif (!$isUserLoggedIn): ?>
        <div class="login-prompt-banner">
            <div>
                <i class="fa-regular fa-user" style="font-size:16px;margin-right:6px"></i>
                Sudah memiliki akun terdaftar? <a href="login.php">Masuk / Login sekarang</a> agar data diri dan riwayat pemesanan terisi otomatis.
            </div>
            <a href="login.php" style="background:#ca8a04;color:#fff;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none">Login Akun</a>
        </div>
    <?php endif; ?>

    <div id="message" class="message" role="status" aria-live="polite"></div>

    <form id="checkoutForm">
        <div class="checkout-grid">
            <!-- LEFT COLUMN: FORM DETAILS -->
            <div class="form-columns-container">
                <!-- 1. DATA DIRI PEMESAN -->
                <section class="panel">
                    <div class="panel-header-row">
                        <h2><i class="fa-solid fa-user-check"></i> 1. Informasi Kontak Pemesan</h2>
                        <?php if ($isUserLoggedIn && !empty($autoName)): ?>
                            <span class="badge-status-autofill"><i class="fa-solid fa-check"></i> Akun Terhubung</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nama Lengkap *</label>
                            <input id="name" name="name" type="text"
                                   maxlength="100" autocomplete="name"
                                   placeholder="Nama pemilik usaha"
                                   class="<?= !empty($autoName) ? 'is-autofilled' : '' ?>"
                                   value="<?= escapeHtml($autoName) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Alamat Email *</label>
                            <input id="email" name="email" type="email"
                                   maxlength="150" autocomplete="email"
                                   placeholder="nama@email.com"
                                   class="<?= !empty($autoEmail) ? 'is-autofilled' : '' ?>"
                                   value="<?= escapeHtml($autoEmail) ?>" required>
                        </div>

                        <div class="form-group full">
                            <label for="phone">Nomor HP / WhatsApp Aktif *</label>
                            <input id="phone" name="phone" type="tel"
                                   maxlength="25" autocomplete="tel"
                                   placeholder="08xxxxxxxxxx"
                                   class="<?= !empty($autoPhone) ? 'is-autofilled' : '' ?>"
                                   value="<?= escapeHtml($autoPhone) ?>" required>
                            <span class="help">Nomor ini akan digunakan tim Balla’ Fresh untuk konfirmasi hasil pengerjaan.</span>
                        </div>

                        <div class="form-group full">
                            <label for="address">Alamat Domisili / Lokasi Usaha *</label>
                            <textarea id="address" name="address"
                                      maxlength="500" autocomplete="street-address"
                                      class="<?= !empty($autoAddress) ? 'is-autofilled' : '' ?>"
                                      placeholder="Contoh: Jl. Pendidikan 1 No. 27, Tidung, Kec. Rappocini, Kota Makassar" required><?= escapeHtml($autoAddress) ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- 2. DETAIL PROFIL USAHA MITRA (CUSTOM FIELDS BARU) -->
                <section class="panel">
                    <div class="panel-header-row">
                        <h2><i class="fa-solid fa-store"></i> 2. Profil Usaha &amp; Kustomisasi Layanan</h2>
                        <span style="font-size:12px;color:var(--muted)">Informasi Brand</span>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="businessName">Nama Bisnis / Brand Hidroponik *</label>
                            <input id="businessName" name="business_name" type="text"
                                   maxlength="150"
                                   placeholder="Contoh: Makassar Hydro Farm / Celebes Greens" required>
                            <span class="help">Nama brand atau kebun hidroponik yang akan digunakan dalam desain dan layanan.</span>
                        </div>

                        <div class="form-group">
                            <label for="socialMedia">Link Media Sosial <span class="optional-tag">(Opsional)</span></label>
                            <input id="socialMedia" name="social_media" type="url"
                                   maxlength="255"
                                   placeholder="https://instagram.com/nama_kebun">
                            <span class="help">Akun Instagram / TikTok / Facebook bisnis Anda jika sudah ada.</span>
                        </div>

                        <div class="form-group">
                            <label for="websiteUrl">Link Website Customer <span class="optional-tag">(Opsional)</span></label>
                            <input id="websiteUrl" name="website_url" type="url"
                                   maxlength="255"
                                   placeholder="https://kebunhidroponik.com">
                            <span class="help">Sertakan jika bisnis Anda saat ini sudah memiliki website.</span>
                        </div>

                        <div class="form-group full">
                            <label for="notes">Catatan &amp; Arahan Khusus <span class="optional-tag">(Opsional)</span></label>
                            <textarea id="notes" name="notes" maxlength="2000"
                                      placeholder="Ceritakan preferensi warna, gaya desain, atau target pasar sayuran yang ingin Anda capai..."></textarea>
                        </div>
                    </div>
                </section>

                <!-- 3. PEMILIHAN METODE PEMBAYARAN -->
                <section class="panel">
                    <div class="panel-header-row">
                        <h2><i class="fa-solid fa-credit-card"></i> 3. Metode Pembayaran</h2>
                        <span style="font-size:12px;color:var(--green);font-weight:700"><i class="fa-solid fa-shield-halved"></i> Transaksi Aman</span>
                    </div>

                    <div class="payment-methods-grid">
                        <!-- OPSI 1: MIDTRANS PAYMENT GATEWAY (DIREKOMENDASIKAN) -->
                        <div class="payment-card selected" id="optMidtrans" onclick="selectPaymentMethod('midtrans')">
                            <div class="payment-card-header">
                                <div class="payment-radio-wrap">
                                    <div class="custom-radio"></div>
                                    <div class="payment-title-group">
                                        <h3>
                                            Payment Gateway Midtrans
                                            <span class="badge-recommended">Disarankan</span>
                                        </h3>
                                        <p class="payment-desc">Bayar instan dengan verifikasi otomatis tanpa konfirmasi manual</p>
                                    </div>
                                </div>
                                <div class="payment-logos">
                                    <span class="channel-pill channel-midtrans">midtrans</span>
                                    <span class="channel-pill channel-qris">QRIS</span>
                                    <span class="channel-pill channel-gopay">GoPay</span>
                                    <span class="channel-pill channel-shopee">ShopeePay</span>
                                </div>
                            </div>
                            <div class="payment-details-box">
                                <div style="display:flex;align-items:flex-start;gap:10px;">
                                    <i class="fa-solid fa-bolt" style="color:var(--orange);font-size:16px;margin-top:2px;"></i>
                                    <div>
                                        <strong>Mendukung Pembayaran Instan:</strong>
                                        <p style="color:var(--muted);margin-top:2px;">
                                            Scan QRIS (BCA, Mandiri, BRI, BNI, Dana, OVO, LinkAja), E-Wallet langsung, dan Kartu Kredit/Debit. Status pesanan akan terverifikasi secara otomatis oleh sistem gateway.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OPSI 2: TRANSFER BANK MANUAL -->
                        <div class="payment-card" id="optBank" onclick="selectPaymentMethod('bca')">
                            <div class="payment-card-header">
                                <div class="payment-radio-wrap">
                                    <div class="custom-radio"></div>
                                    <div class="payment-title-group">
                                        <h3>Transfer Bank Manual</h3>
                                        <p class="payment-desc">Pilihan 3 rekening bank resmi Balla’ Fresh Digital</p>
                                    </div>
                                </div>
                                <div class="payment-logos">
                                    <span class="channel-pill" style="font-weight:800;color:#005aa9;">BCA</span>
                                    <span class="channel-pill" style="font-weight:800;color:#002d62;">MANDIRI</span>
                                    <span class="channel-pill" style="font-weight:800;color:#00529b;">BRI</span>
                                </div>
                            </div>

                            <div class="payment-details-box">
                                <p style="margin-bottom:10px;font-weight:600;color:var(--text)">
                                    Pilih rekening bank tujuan transfer Anda:
                                </p>

                                <div class="bank-options-list">
                                    <!-- BANK BCA -->
                                    <div class="bank-sub-card bank-active" id="bankSubBCA" onclick="chooseBank(event, 'bca')">
                                        <div class="bank-left">
                                            <div class="bank-logo-box BCA-logo">BCA</div>
                                            <div class="bank-info">
                                                <strong>Bank BCA</strong>
                                                <span>7920 1829 33 · a.n. PT Balla Fresh Digital</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-copy" onclick="copyText(event, '7920182933')">
                                            <i class="fa-regular fa-copy"></i> Salin
                                        </button>
                                    </div>

                                    <!-- BANK MANDIRI -->
                                    <div class="bank-sub-card" id="bankSubMandiri" onclick="chooseBank(event, 'mandiri')">
                                        <div class="bank-left">
                                            <div class="bank-logo-box mandiri-logo">MANDIRI</div>
                                            <div class="bank-info">
                                                <strong>Bank Mandiri</strong>
                                                <span>1520 0098 7654 · a.n. PT Balla Fresh Digital</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-copy" onclick="copyText(event, '152000987654')">
                                            <i class="fa-regular fa-copy"></i> Salin
                                        </button>
                                    </div>

                                    <!-- BANK BRI -->
                                    <div class="bank-sub-card" id="bankSubBRI" onclick="chooseBank(event, 'bri')">
                                        <div class="bank-left">
                                            <div class="bank-logo-box bri-logo">BRI</div>
                                            <div class="bank-info">
                                                <strong>Bank Rakyat Indonesia (BRI)</strong>
                                                <span>0341 0100 2345 530 · a.n. PT Balla Fresh Digital</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-copy" onclick="copyText(event, '034101002345530')">
                                            <i class="fa-regular fa-copy"></i> Salin
                                        </button>
                                    </div>
                                </div>

                                <div style="margin-top:12px;background:#f8faf5;padding:10px 12px;border-radius:10px;font-size:11.5px;color:var(--muted)">
                                    <i class="fa-solid fa-info-circle" style="color:var(--green)"></i>
                                    Setelah melakukan transfer, Anda dapat mengunggah bukti atau langsung mengonfirmasi via WhatsApp Tim Balla’ Fresh.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden input to hold current selected method -->
                    <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="midtrans">
                </section>
            </div>

            <!-- RIGHT COLUMN: SUMMARY & SUBMIT -->
            <aside class="panel summary-panel">
                <h2><i class="fa-solid fa-receipt"></i> Ringkasan Pesanan</h2>

                <div id="summaryItems">
                    <div class="empty">Memuat rincian keranjang...</div>
                </div>

                <div class="pricing-breakdown">
                    <div class="price-row">
                        <span>Subtotal Layanan</span>
                        <span id="summarySubtotal">Rp0</span>
                    </div>
                    <div class="price-row free-badge">
                        <span>Biaya Platform &amp; Konsultasi</span>
                        <span>GRATIS (Rp0)</span>
                    </div>
                </div>

                <div class="total-row">
                    <span>Total Tagihan</span>
                    <strong id="summaryTotal">Rp0</strong>
                </div>

                <!-- PREVIEW METODE YANG DIPILIH -->
                <div class="summary-payment-preview" id="summaryPaymentPreview">
                    <small>Metode Pembayaran Dipilih</small>
                    <strong id="previewPaymentText"><i class="fa-solid fa-bolt" style="color:var(--orange)"></i> Midtrans (QRIS &amp; E-Wallet)</strong>
                </div>

                <button class="btn-submit" id="submitButton" type="submit">
                    <span>Bayar via Midtrans</span> <i class="fa-solid fa-arrow-right"></i>
                </button>

                <ul class="security-guarantees">
                    <li><i class="fa-solid fa-shield-halved"></i> Garansi Pengerjaan &amp; Revisi Tuntas</li>
                    <li><i class="fa-solid fa-lock"></i> Enkripsi Data Transaksi Aman 256-Bit</li>
                    <li><i class="fa-solid fa-comments"></i> Konsultasi Pendampingan Langsung</li>
                </ul>
            </aside>
        </div>
    </form>
</main>

<footer class="footer-note">
    © <?= date('Y') ?> <strong>Balla’Fresh</strong> · Menjangkau lebih banyak peluang, #DariRumahAja.
</footer>

<script>
"use strict";

const CART_KEY = "ballaFreshCart";

const money = value => new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0
}).format(value);

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[char]);
}

function readCart() {
    try {
        const parsed = JSON.parse(localStorage.getItem(CART_KEY) || "[]");
        if (!Array.isArray(parsed)) return [];

        return parsed.filter(item =>
            item &&
            Number.isInteger(Number(item.id)) &&
            Number(item.id) > 0 &&
            Number.isInteger(Number(item.qty)) &&
            Number(item.qty) > 0
        );
    } catch {
        return [];
    }
}

let cart = readCart();

const summaryItems = document.getElementById("summaryItems");
const summarySubtotal = document.getElementById("summarySubtotal");
const summaryTotal = document.getElementById("summaryTotal");
const checkoutForm = document.getElementById("checkoutForm");
const submitButton = document.getElementById("submitButton");
const messageBox = document.getElementById("message");
const paymentInput = document.getElementById("selectedPaymentMethod");
const previewPaymentText = document.getElementById("previewPaymentText");

// Pilihan Metode Pembayaran
let currentPaymentMethod = "midtrans";

function selectPaymentMethod(method) {
    currentPaymentMethod = method;
    paymentInput.value = method;

    const optMidtrans = document.getElementById("optMidtrans");
    const optBank = document.getElementById("optBank");

    if (method === "midtrans") {
        optMidtrans.classList.add("selected");
        optBank.classList.remove("selected");
        previewPaymentText.innerHTML = '<i class="fa-solid fa-bolt" style="color:var(--orange)"></i> Midtrans (QRIS &amp; E-Wallet)';
        submitButton.innerHTML = '<span>Bayar via Midtrans</span> <i class="fa-solid fa-arrow-right"></i>';
    } else {
        optMidtrans.classList.remove("selected");
        optBank.classList.add("selected");
        updateBankPreview(method);
    }
}

function chooseBank(event, bankCode) {
    if (event) event.stopPropagation();
    currentPaymentMethod = bankCode;
    paymentInput.value = bankCode;

    document.querySelectorAll(".bank-sub-card").forEach(c => c.classList.remove("bank-active"));
    const activeCard = document.getElementById("bankSub" + bankCode.toUpperCase());
    if (activeCard) activeCard.classList.add("bank-active");

    const optMidtrans = document.getElementById("optMidtrans");
    const optBank = document.getElementById("optBank");
    optMidtrans.classList.remove("selected");
    optBank.classList.add("selected");

    updateBankPreview(bankCode);
}

function updateBankPreview(bankCode) {
    const bankNames = {
        bca: "Transfer Bank BCA (7920 1829 33)",
        mandiri: "Transfer Bank Mandiri (1520 0098 7654)",
        bri: "Transfer Bank BRI (0341 0100 2345 530)"
    };
    const name = bankNames[bankCode] || "Transfer Bank";
    previewPaymentText.innerHTML = '<i class="fa-solid fa-building-columns" style="color:var(--green)"></i> ' + name;
    submitButton.innerHTML = '<span>Konfirmasi Pesanan Transfer Bank</span> <i class="fa-solid fa-arrow-right"></i>';
}

function copyText(event, text) {
    if (event) event.stopPropagation();
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target.closest("button");
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
            btn.style.color = "#059669";
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.color = "";
            }, 2000);
        }
    }).catch(() => {
        alert("Nomor rekening: " + text);
    });
}

function showMessage(text, type) {
    messageBox.textContent = text;
    messageBox.className = "message " + type;
    messageBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function renderSummary() {
    if (!cart.length) {
        summaryItems.innerHTML = `
            <div class="empty">
                <i class="fa-solid fa-bag-shopping" style="font-size:24px;margin-bottom:8px;display:block;opacity:0.4"></i>
                Keranjang Anda masih kosong.<br>
                <a href="index.php#layanan" style="color:var(--green);font-weight:700">Pilih layanan di katalog</a>.
            </div>
        `;

        summarySubtotal.textContent = money(0);
        summaryTotal.textContent = money(0);
        submitButton.disabled = true;
        return;
    }

    let total = 0;

    summaryItems.innerHTML = cart.map(item => {
        const qty = Number(item.qty);
        const price = Number(item.price) || 0;
        const subtotal = price * qty;
        total += subtotal;

        return `
            <div class="summary-item">
                <div class="item-top">
                    <div class="item-name">${escapeHTML(item.name || "Layanan Balla’Fresh")}</div>
                    <div class="item-price">${money(subtotal)}</div>
                </div>
                <div class="item-detail">${qty} × ${money(price)}</div>
            </div>
        `;
    }).join("");

    summarySubtotal.textContent = money(total);
    summaryTotal.textContent = money(total);
    submitButton.disabled = false;
}

checkoutForm.addEventListener("submit", async event => {
    event.preventDefault();

    cart = readCart();

    if (!cart.length) {
        showMessage("Keranjang Anda kosong. Silakan kembali ke katalog dan pilih layanan.", "error");
        return;
    }

    const formData = new FormData(checkoutForm);

    const payload = {
        name: String(formData.get("name") || "").trim(),
        email: String(formData.get("email") || "").trim(),
        phone: String(formData.get("phone") || "").trim(),
        address: String(formData.get("address") || "").trim(),
        business_name: String(formData.get("business_name") || "").trim(),
        social_media: String(formData.get("social_media") || "").trim(),
        website_url: String(formData.get("website_url") || "").trim(),
        payment_method: String(formData.get("payment_method") || currentPaymentMethod).trim(),
        notes: String(formData.get("notes") || "").trim(),
        items: cart.map(item => ({
            id: Number(item.id),
            qty: Number(item.qty)
        }))
    };

    if (!payload.name || !payload.email || !payload.phone || !payload.address || !payload.business_name) {
        showMessage("Harap lengkapi semua kolom wajib bertanda bintang (*) terlebih dahulu.", "error");
        return;
    }

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses Pesanan...';

    try {
        const response = await fetch("checkout.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Pesanan belum berhasil disimpan.");
        }

        // Kosongkan keranjang di localStorage setelah server berhasil menyimpan
        localStorage.removeItem(CART_KEY);
        cart = [];
        renderSummary();

        // Tampilkan Tampilan Berhasil yang Sangat Informatif
        const isMidtrans = result.payment_method === "midtrans";
        const bankAccounts = {
            bca: { name: "BCA", norek: "7920 1829 33", an: "PT Balla Fresh Digital" },
            mandiri: { name: "Mandiri", norek: "1520 0098 7654", an: "PT Balla Fresh Digital" },
            bri: { name: "BRI", norek: "0341 0100 2345 530", an: "PT Balla Fresh Digital" }
        };
        const currentBank = bankAccounts[result.payment_method] || bankAccounts.bca;

        const waText = encodeURIComponent(
            `Halo Tim Balla Fresh, saya ingin konfirmasi pesanan layanan:\n` +
            `• No. Pesanan: ${result.order_number}\n` +
            `• Nama Mitra: ${payload.name}\n` +
            `• Usaha: ${payload.business_name}\n` +
            `• Total: ${money(result.total_amount)}\n` +
            `• Metode: ${result.payment_label}`
        );

        messageBox.className = "message success";
        messageBox.innerHTML = `
            <div class="success-box">
                <h3><i class="fa-solid fa-circle-check"></i> Pesanan Berhasil Dibuat!</h3>
                <p>Terima kasih <strong>${escapeHTML(payload.name)}</strong>. Pesanan layanan digital untuk brand <strong>${escapeHTML(payload.business_name)}</strong> telah tersimpan di sistem.</p>
                
                <div class="order-badge-row">
                    <div>
                        <span style="font-size:11px;color:var(--muted);display:block;text-transform:uppercase;font-weight:700">Nomor Pesanan</span>
                        <span class="order-pill">${escapeHTML(result.order_number)}</span>
                    </div>
                    <div>
                        <span style="font-size:11px;color:var(--muted);display:block;text-transform:uppercase;font-weight:700">Total Investasi</span>
                        <strong style="color:#e48e1e;font-size:18px;">${money(result.total_amount)}</strong>
                    </div>
                </div>

                ${isMidtrans ? `
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:14px;border-radius:12px;margin:14px 0;">
                        <strong style="color:#166534;display:flex;align-items:center;gap:6px">
                            <i class="fa-solid fa-bolt"></i> Tagihan Midtrans Disiapkan
                        </strong>
                        <p style="font-size:12.5px;color:#1e293b;margin-top:4px;">
                            Instruksi pembayaran QRIS / E-Wallet otomatis dikirimkan ke email <strong>${escapeHTML(payload.email)}</strong> dan WhatsApp Anda.
                        </p>
                    </div>
                ` : `
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:14px;border-radius:12px;margin:14px 0;">
                        <strong style="color:#166534;display:flex;align-items:center;gap:6px">
                            <i class="fa-solid fa-building-columns"></i> Instruksi Transfer Bank ${currentBank.name}
                        </strong>
                        <p style="font-size:13px;color:#1e293b;margin-top:4px;">
                            Silakan transfer sejumlah <strong>${money(result.total_amount)}</strong> ke rekening:<br>
                            <strong>Bank ${currentBank.name}: ${currentBank.norek}</strong> (a.n. ${currentBank.an})
                        </p>
                    </div>
                `}

                <p style="font-size:12px;color:var(--muted);margin-top:8px;">
                    Simpan nomor pesanan ini. Anda dapat mengonfirmasi langsung kepada narahubung kami melalui WhatsApp berikut:
                </p>

                <a href="https://wa.me/6281234567890?text=${waText}" target="_blank" rel="noopener noreferrer" class="btn-wa-confirm">
                    <i class="fa-brands fa-whatsapp" style="font-size:18px"></i> Konfirmasi Pesanan via WhatsApp
                </a>
                
                <a href="index.php" style="margin-left:12px;font-size:13px;color:var(--green);font-weight:700;text-decoration:none">
                    Kembali ke Beranda →
                </a>
            </div>
        `;

        checkoutForm.style.display = "none";
        messageBox.scrollIntoView({ behavior: "smooth", block: "start" });

    } catch (error) {
        showMessage(
            error.message || "Terjadi kendala saat mengirim pesanan. Periksa koneksi dan coba lagi.",
            "error"
        );

        submitButton.disabled = false;
        submitButton.innerHTML = '<span>Kirim Pemesanan</span> <i class="fa-solid fa-arrow-right"></i>';
    }
});

renderSummary();
</script>
</body>
</html>
