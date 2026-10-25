<?php
session_start();
require_once __DIR__ . "/db.php";

/*
|--------------------------------------------------------------------------
| CHECKOUT BALLA'FRESH
|--------------------------------------------------------------------------
| Membaca keranjang dari browser melalui fetch().
| Harga layanan selalu diambil ulang dari database.
| Pesanan disimpan menggunakan transaksi MySQL.
*/

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
| PROSES PENYIMPANAN PESANAN
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
    $notes = cleanText($data["notes"] ?? "");
    $items = $data["items"] ?? [];

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $address === ""
    ) {
        sendJson([
            "success" => false,
            "message" => "Nama, email, nomor HP, dan alamat wajib diisi."
        ], 422);
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($name) > 100 ||
        strlen($email) > 150 ||
        strlen($phone) > 20
    ) {
        sendJson([
            "success" => false,
            "message" => "Periksa kembali data pelanggan yang kamu masukkan."
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

    /*
     * Gabungkan ID yang sama dan validasi jumlah.
     * Harga dari browser sengaja tidak digunakan.
     */
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

    $conn->begin_transaction();

    try {
        /*
         * Simpan data pelanggan.
         * Untuk versi awal, setiap pesanan membuat catatan pelanggan baru.
         */
        $customerStmt = $conn->prepare(
            "INSERT INTO customers (name, email, phone, address)
             VALUES (?, ?, ?, ?)"
        );

        $customerStmt->bind_param(
            "ssss",
            $name,
            $email,
            $phone,
            $address
        );

        $customerStmt->execute();
        $customerId = $conn->insert_id;
        $customerStmt->close();

        /*
         * Buat nomor pesanan unik.
         */
        $orderNumber = "BF-" . date("YmdHis") . "-" .
            strtoupper(bin2hex(random_bytes(3)));

        /*
         * Buat pesanan sementara dengan total nol.
         */
        $orderStmt = $conn->prepare(
            "INSERT INTO orders
                (order_number, customer_id, total_amount, status, notes)
             VALUES (?, ?, 0, 'pending', ?)"
        );

        $orderStmt->bind_param(
            "sis",
            $orderNumber,
            $customerId,
            $notes
        );

        $orderStmt->execute();
        $orderId = $conn->insert_id;
        $orderStmt->close();

        /*
         * Ambil layanan dan harga langsung dari database.
         * is_available = 1 memastikan layanan masih tersedia.
         */
        $serviceStmt = $conn->prepare(
            "SELECT id, name, price
             FROM services
             WHERE id = ? AND is_available = 1
             LIMIT 1"
        );

        $itemStmt = $conn->prepare(
            "INSERT INTO order_items
                (order_id, service_id, service_name,
                 quantity, unit_price, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $totalAmount = 0;

        foreach ($quantities as $serviceId => $quantity) {
            $serviceStmt->bind_param("i", $serviceId);
            $serviceStmt->execute();

            $result = $serviceStmt->get_result();
            $service = $result->fetch_assoc();

            if (!$service) {
                throw new Exception(
                    "Salah satu layanan sudah tidak tersedia. Muat ulang katalog dan coba lagi."
                );
            }

            $serviceName = $service["name"];
            $unitPrice = (float) $service["price"];
            $subtotal = $unitPrice * $quantity;

            if (
                !is_finite($unitPrice) ||
                $unitPrice < 0 ||
                !is_finite($subtotal)
            ) {
                throw new Exception("Harga layanan tidak valid.");
            }

            $itemStmt->bind_param(
                "iisidd",
                $orderId,
                $serviceId,
                $serviceName,
                $quantity,
                $unitPrice,
                $subtotal
            );

            $itemStmt->execute();
            $totalAmount += $subtotal;
        }

        $serviceStmt->close();
        $itemStmt->close();

        /*
         * Perbarui total pesanan.
         */
        $updateStmt = $conn->prepare(
            "UPDATE orders SET total_amount = ? WHERE id = ?"
        );

        $updateStmt->bind_param("di", $totalAmount, $orderId);
        $updateStmt->execute();
        $updateStmt->close();

        /*
         * Seluruh penyimpanan berhasil.
         */
        $conn->commit();

        sendJson([
            "success" => true,
            "message" => "Pesanan berhasil disimpan.",
            "order_number" => $orderNumber,
            "total_amount" => $totalAmount
        ]);

    } catch (Throwable $e) {
        $conn->rollback();

        error_log("Checkout BallaFresh: " . $e->getMessage());

        sendJson([
            "success" => false,
            "message" => $e instanceof Exception
                ? $e->getMessage()
                : "Pesanan gagal disimpan. Silakan coba lagi."
        ], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Balla’Fresh</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #246b45;
            --green-dark: #174c31;
            --lime: #d9f99d;
            --bg: #f6f8f2;
            --white: #ffffff;
            --text: #203328;
            --muted: #728075;
            --border: #e2e9df;
            --danger: #b42318;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "DM Sans", sans-serif;
        }

        button, input, textarea {
            font: inherit;
        }

        .topbar {
            padding: 20px 24px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .topbar-inner {
            max-width: 1100px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            font: 800 23px "Manrope", sans-serif;
            color: var(--green);
            text-decoration: none;
        }

        .back-link {
            color: var(--green);
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .page {
            max-width: 1100px;
            margin: 42px auto;
            padding: 0 20px 50px;
        }

        .intro {
            margin-bottom: 26px;
        }

        .eyebrow {
            display: inline-block;
            color: var(--green);
            background: #e8f4e6;
            border-radius: 20px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        h1, h2, h3 {
            font-family: "Manrope", sans-serif;
        }

        h1 {
            margin: 14px 0 8px;
            font-size: clamp(28px, 4vw, 40px);
            letter-spacing: -.04em;
        }

        .intro p {
            color: var(--muted);
            line-height: 1.7;
            margin: 0;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 10px 35px rgba(32, 51, 40, .04);
        }

        .panel h2 {
            font-size: 20px;
            margin: 0 0 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 700;
        }

        input, textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #dce5da;
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            outline: none;
        }

        input:focus, textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(36, 107, 69, .1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .help {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .summary-item {
            padding: 15px 0;
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
        }

        .item-price {
            font-weight: 700;
            white-space: nowrap;
            font-size: 13px;
        }

        .item-detail {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 22px;
        }

        .total-row span {
            color: var(--muted);
            font-size: 14px;
        }

        .total-row strong {
            color: var(--green);
            font: 800 23px "Manrope", sans-serif;
            text-align: right;
        }

        .notice {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #f3f7ed;
            color: #52694f;
            font-size: 12px;
            line-height: 1.7;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 15px 18px;
            background: var(--green);
            color: white;
            font-weight: 800;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .btn:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: .65;
            cursor: wait;
            transform: none;
        }

        .message {
            display: none;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            line-height: 1.6;
            font-size: 14px;
        }

        .message.error {
            display: block;
            color: var(--danger);
            background: #fef0ef;
        }

        .message.success {
            display: block;
            color: #205b38;
            background: #e9f7ec;
        }

        .empty {
            padding: 20px 0;
            color: var(--muted);
            text-align: center;
            line-height: 1.7;
        }

        .footer-note {
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            padding: 25px 20px;
        }

        @media (max-width: 760px) {
            .page {
                margin-top: 28px;
            }

            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .summary-panel {
                order: -1;
            }

            .panel {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .topbar {
                padding: 16px;
            }

            .page {
                padding-left: 14px;
                padding-right: 14px;
            }
        }
    </style>
</head>

<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.html">Balla’Fresh</a>
        <a class="back-link" href="index.html#layanan">← Kembali ke layanan</a>
    </div>
</header>

<main class="page">
    <div class="intro">
        <span class="eyebrow">Pemesanan Layanan</span>
        <h1>Selesaikan pemesananmu</h1>
        <p>Isi informasi kontak dan periksa kembali layanan yang ingin kamu pesan.</p>
    </div>

    <div id="message" class="message" role="status" aria-live="polite"></div>

    <div class="checkout-grid">
        <section class="panel">
            <h2>Informasi pelanggan</h2>

            <form id="checkoutForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nama lengkap *</label>
                        <input id="name" name="name" type="text"
                               maxlength="100" autocomplete="name"
                               placeholder="Nama pemilik usaha" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input id="email" name="email" type="email"
                               maxlength="150" autocomplete="email"
                               placeholder="nama@email.com" required>
                    </div>

                    <div class="form-group full">
                        <label for="phone">Nomor HP / WhatsApp *</label>
                        <input id="phone" name="phone" type="tel"
                               maxlength="20" autocomplete="tel"
                               placeholder="08xxxxxxxxxx" required>
                    </div>

                    <div class="form-group full">
                        <label for="address">Alamat usaha *</label>
                        <textarea id="address" name="address"
                                  maxlength="500" autocomplete="street-address"
                                  placeholder="Masukkan alamat usaha atau domisili" required></textarea>
                    </div>

                    <div class="form-group full">
                        <label for="notes">Catatan pemesanan (opsional)</label>
                        <textarea id="notes" name="notes" maxlength="2000"
                                  placeholder="Ceritakan kebutuhan, target, atau informasi tambahan usaha Anda"></textarea>
                    </div>
                </div>

                <p class="help">Tanda * berarti wajib diisi. Tim Balla’Fresh dapat mengonfirmasi kebutuhan dan detail layanan setelah pesanan diterima.</p>

                <button class="btn" id="submitButton" type="submit">
                    Kirim Pemesanan <span aria-hidden="true">→</span>
                </button>
            </form>
        </section>

        <aside class="panel summary-panel">
            <h2>Ringkasan pesanan</h2>
            <div id="summaryItems">
                <div class="empty">Memuat keranjang...</div>
            </div>

            <div class="total-row">
                <span>Estimasi total</span>
                <strong id="summaryTotal">Rp0</strong>
            </div>

            <div class="notice">
                Harga akan diverifikasi berdasarkan data layanan terbaru. Status awal pesanan adalah menunggu konfirmasi (pending).
            </div>
        </aside>
    </div>
</main>

<footer class="footer-note">
    © 2026 Balla’Fresh · Menjangkau lebih banyak peluang, #DariRumahAja.
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
    return String(value).replace(/[&<>"']/g, char => ({
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
const summaryTotal = document.getElementById("summaryTotal");
const checkoutForm = document.getElementById("checkoutForm");
const submitButton = document.getElementById("submitButton");
const messageBox = document.getElementById("message");

function showMessage(text, type) {
    messageBox.textContent = text;
    messageBox.className = "message " + type;
    messageBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function renderSummary() {
    if (!cart.length) {
        summaryItems.innerHTML = `
            <div class="empty">
                Keranjang kamu kosong.<br>
                <a href="index.html#layanan">Pilih layanan terlebih dahulu</a>.
            </div>
        `;

        summaryTotal.textContent = money(0);
        checkoutForm.querySelector("button[type=submit]").disabled = true;
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

    summaryTotal.textContent = money(total);
}

checkoutForm.addEventListener("submit", async event => {
    event.preventDefault();

    cart = readCart();

    if (!cart.length) {
        showMessage("Keranjang kosong. Silakan kembali ke katalog dan pilih layanan.", "error");
        return;
    }

    const formData = new FormData(checkoutForm);

    const payload = {
        name: String(formData.get("name") || "").trim(),
        email: String(formData.get("email") || "").trim(),
        phone: String(formData.get("phone") || "").trim(),
        address: String(formData.get("address") || "").trim(),
        notes: String(formData.get("notes") || "").trim(),
        items: cart.map(item => ({
            id: Number(item.id),
            qty: Number(item.qty)
        }))
    };

    if (!payload.name || !payload.email || !payload.phone || !payload.address) {
        showMessage("Lengkapi semua kolom wajib terlebih dahulu.", "error");
        return;
    }

    submitButton.disabled = true;
    submitButton.textContent = "Memproses pesanan...";

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

        // Kosongkan keranjang hanya setelah server menyatakan berhasil.
        localStorage.removeItem(CART_KEY);
        cart = [];
        renderSummary();

        checkoutForm.reset();

        messageBox.className = "message success";
        messageBox.textContent =
            "Pesanan berhasil dibuat! Nomor pesanan: " +
            result.order_number +
            ". Estimasi total: " + money(result.total_amount) +
            ". Simpan nomor ini untuk referensi saat menghubungi Balla’Fresh.";

        submitButton.textContent = "Pesanan Berhasil";
        messageBox.scrollIntoView({ behavior: "smooth", block: "start" });

    } catch (error) {
        showMessage(
            error.message || "Terjadi kendala saat mengirim pesanan. Periksa koneksi dan coba lagi.",
            "error"
        );

        submitButton.disabled = false;
        submitButton.innerHTML = 'Kirim Pemesanan <span aria-hidden="true">→</span>';
    }
});

renderSummary();
</script>
</body>
</html>
