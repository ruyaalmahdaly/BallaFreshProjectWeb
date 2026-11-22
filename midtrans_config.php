<?php
/**
 * Konfigurasi Midtrans Sandbox Payment Gateway & Core API
 * Balla' Fresh Agency
 */

// Muat Composer autoloader jika tersedia
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Normalisasi API Key agar terhindar dari duplikasi prefix yang tidak disengaja
 * Misalnya 'SB-Mid-server-SB-Mid-server-...' atau 'SB-Mid-client-Mid-client-...'
 */
function normalizeMidtransKey(?string $key, string $type = 'server'): string {
    $clean = trim((string)$key);
    if ($clean === '') {
        return '';
    }

    if ($type === 'server') {
        // Hapus duplikasi prefix berulang jika ada
        while (strpos($clean, 'SB-Mid-server-SB-Mid-server-') === 0) {
            $clean = substr($clean, strlen('SB-Mid-server-'));
        }
        while (strpos($clean, 'Mid-server-Mid-server-') === 0) {
            $clean = substr($clean, strlen('Mid-server-'));
        }
    } else {
        // Client key
        while (strpos($clean, 'SB-Mid-client-SB-Mid-client-') === 0) {
            $clean = substr($clean, strlen('SB-Mid-client-'));
        }
        while (strpos($clean, 'Mid-client-Mid-client-') === 0) {
            $clean = substr($clean, strlen('Mid-client-'));
        }
    }

    return $clean;
}

// --------------------------------------------------------------------------
// Konfigurasi Kunci API Sandbox (Sesuai Akun Midtrans Sandbox BallaFresh)
// --------------------------------------------------------------------------
$localEnv = file_exists(__DIR__ . '/env.local.php') ? (require __DIR__ . '/env.local.php') : [];
$rawServer = getenv('MIDTRANS_SERVER_KEY') ?: ($_ENV['MIDTRANS_SERVER_KEY'] ?? ($localEnv['MIDTRANS_SERVER_KEY'] ?? ''));
$rawClient = getenv('MIDTRANS_CLIENT_KEY') ?: ($_ENV['MIDTRANS_CLIENT_KEY'] ?? ($localEnv['MIDTRANS_CLIENT_KEY'] ?? ''));

if (!defined('MIDTRANS_SERVER_KEY')) {
    define('MIDTRANS_SERVER_KEY', normalizeMidtransKey($rawServer, 'server'));
}

if (!defined('MIDTRANS_CLIENT_KEY')) {
    define('MIDTRANS_CLIENT_KEY', normalizeMidtransKey($rawClient, 'client'));
}

// Environment: FALSE untuk Sandbox, TRUE untuk Production
if (!defined('MIDTRANS_IS_PRODUCTION')) {
    define('MIDTRANS_IS_PRODUCTION', false);
}

// Pengaturan Sanitasi & 3D Secure
if (!defined('MIDTRANS_IS_SANITIZED')) {
    define('MIDTRANS_IS_SANITIZED', true);
}

if (!defined('MIDTRANS_IS_3DS')) {
    define('MIDTRANS_IS_3DS', true);
}

// Endpoint URL Midtrans (Core API & Snap)
if (MIDTRANS_IS_PRODUCTION) {
    define('MIDTRANS_SNAP_JS_URL', 'https://app.midtrans.com/snap/snap.js');
    define('MIDTRANS_SNAP_API_URL', 'https://app.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_API_BASE_URL', 'https://api.midtrans.com/v2');
    define('MIDTRANS_CHARGE_API_URL', 'https://api.midtrans.com/v2/charge');
} else {
    define('MIDTRANS_SNAP_JS_URL', 'https://app.sandbox.midtrans.com/snap/snap.js');
    define('MIDTRANS_SNAP_API_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_API_BASE_URL', 'https://api.sandbox.midtrans.com/v2');
    define('MIDTRANS_CHARGE_API_URL', 'https://api.sandbox.midtrans.com/v2/charge');
}

// Inisialisasi Midtrans PHP SDK jika class tersedia
if (class_exists('\Midtrans\Config')) {
    \Midtrans\Config::$serverKey    = MIDTRANS_SERVER_KEY;
    \Midtrans\Config::$clientKey    = MIDTRANS_CLIENT_KEY;
    \Midtrans\Config::$isProduction = MIDTRANS_IS_PRODUCTION;
    \Midtrans\Config::$isSanitized  = MIDTRANS_IS_SANITIZED;
    \Midtrans\Config::$is3ds        = MIDTRANS_IS_3DS;
}

/**
 * Cek apakah kredensial Midtrans sudah diatur (bukan placeholder default)
 *
 * @return bool
 */
function isMidtransConfigured(): bool {
    $serverKey = defined('MIDTRANS_SERVER_KEY') ? MIDTRANS_SERVER_KEY : '';
    $clientKey = defined('MIDTRANS_CLIENT_KEY') ? MIDTRANS_CLIENT_KEY : '';

    if (empty($serverKey) || empty($clientKey)) {
        return false;
    }
    if (strpos($serverKey, 'MASUKKAN_') !== false || strpos($clientKey, 'MASUKKAN_') !== false) {
        return false;
    }
    return true;
}

/**
 * Metadata saluran pembayaran Midtrans Core API
 *
 * @param string $subMethod
 * @return array
 */
function getPaymentSubMethodMeta(string $subMethod): array {
    $map = [
        'qris' => [
            'label' => 'QRIS (Semua Bank & E-Wallet)',
            'short_label' => 'QRIS',
            'type' => 'qris',
            'icon' => 'fa-solid fa-qrcode',
            'badge_color' => '#16a34a',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/v2/qris/index',
            'simulator_name' => 'Simulator QRIS Midtrans',
            'guide_type' => 'qris'
        ],
        'bca_va' => [
            'label' => 'BCA Virtual Account',
            'short_label' => 'BCA VA',
            'type' => 'bank_transfer',
            'bank' => 'bca',
            'icon' => 'fa-solid fa-building-columns',
            'badge_color' => '#005baa',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/openapi/va/index',
            'simulator_name' => 'Simulator Virtual Account Midtrans',
            'guide_type' => 'va_bca'
        ],
        'bni_va' => [
            'label' => 'BNI Virtual Account',
            'short_label' => 'BNI VA',
            'type' => 'bank_transfer',
            'bank' => 'bni',
            'icon' => 'fa-solid fa-building-columns',
            'badge_color' => '#e05a26',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/openapi/va/index',
            'simulator_name' => 'Simulator Virtual Account Midtrans',
            'guide_type' => 'va_bni'
        ],
        'bri_va' => [
            'label' => 'BRI Virtual Account',
            'short_label' => 'BRI VA',
            'type' => 'bank_transfer',
            'bank' => 'bri',
            'icon' => 'fa-solid fa-building-columns',
            'badge_color' => '#00529c',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/openapi/va/index',
            'simulator_name' => 'Simulator Virtual Account Midtrans',
            'guide_type' => 'va_bri'
        ],
        'mandiri_va' => [
            'label' => 'Mandiri Virtual Account (Bill Payment)',
            'short_label' => 'Mandiri Bill',
            'type' => 'echannel',
            'icon' => 'fa-solid fa-building-columns',
            'badge_color' => '#003d79',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/openapi/va/index',
            'simulator_name' => 'Simulator Mandiri Bill Midtrans',
            'guide_type' => 'mandiri'
        ],
        'gopay' => [
            'label' => 'GoPay (QR & Aplikasi)',
            'short_label' => 'GoPay',
            'type' => 'gopay',
            'icon' => 'fa-solid fa-wallet',
            'badge_color' => '#0081a0',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/gopay/partner/app/payment-pin',
            'simulator_name' => 'Simulator GoPay Midtrans',
            'guide_type' => 'gopay'
        ],
        'shopeepay' => [
            'label' => 'ShopeePay (QR & Aplikasi)',
            'short_label' => 'ShopeePay',
            'type' => 'shopeepay',
            'icon' => 'fa-solid fa-wallet',
            'badge_color' => '#ee4d2d',
            'simulator_url' => 'https://simulator.sandbox.midtrans.com/openapi/qris/index',
            'simulator_name' => 'Simulator ShopeePay / QRIS Midtrans',
            'guide_type' => 'shopeepay'
        ]
    ];

    return $map[$subMethod] ?? [
        'label' => 'Midtrans Payment',
        'short_label' => 'Midtrans',
        'type' => 'midtrans',
        'icon' => 'fa-solid fa-credit-card',
        'badge_color' => '#3a6932',
        'simulator_url' => 'https://simulator.sandbox.midtrans.com/',
        'simulator_name' => 'Simulator Midtrans',
        'guide_type' => 'generic'
    ];
}

/**
 * Buat transaksi langsung menggunakan Midtrans Core API (Charge API)
 *
 * @param string $subMethod Jenis saluran: qris, bca_va, bni_va, bri_va, mandiri_va, gopay, shopeepay
 * @param string $orderNumber Nomor pesanan (misal: BF-20261116...)
 * @param float $grossAmount Nominal tagihan
 * @param array $customerDetails Informasi nama, email, no HP, alamat
 * @param array $itemDetails Daftar item layanan
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function createMidtransCoreCharge(
    string $subMethod,
    string $orderNumber,
    float $grossAmount,
    array $customerDetails = [],
    array $itemDetails = []
): array {
    if (!isMidtransConfigured()) {
        return [
            'success' => false,
            'error' => 'Kunci Midtrans Sandbox belum diatur. Silakan periksa MIDTRANS_SERVER_KEY dan CLIENT_KEY pada midtrans_config.php.'
        ];
    }

    $grossAmountInt = (int) round($grossAmount);

    // Pastikan total item presisi dengan gross_amount
    if (!empty($itemDetails)) {
        $sumItems = 0;
        foreach ($itemDetails as $it) {
            $sumItems += ((int)$it['price'] * (int)$it['quantity']);
        }
        if ($sumItems !== $grossAmountInt) {
            $diff = $grossAmountInt - $sumItems;
            $lastIdx = count($itemDetails) - 1;
            $itemDetails[$lastIdx]['price'] += $diff;
        }
    }

    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $finishUrl = "{$scheme}://{$host}/balla-fresh/payment_success.php?order_id={$orderNumber}";

    // Susun payload sesuai dokumentasi Core API Midtrans
    $payload = [
        'transaction_details' => [
            'order_id' => $orderNumber,
            'gross_amount' => $grossAmountInt
        ],
        'customer_details' => $customerDetails,
        'item_details' => $itemDetails
    ];

    switch ($subMethod) {
        case 'qris':
            $payload['payment_type'] = 'qris';
            $payload['qris'] = [
                'acquirer' => 'gopay'
            ];
            break;

        case 'bca_va':
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = [
                'bank' => 'bca'
            ];
            break;

        case 'bni_va':
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = [
                'bank' => 'bni'
            ];
            break;

        case 'bri_va':
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = [
                'bank' => 'bri'
            ];
            break;

        case 'mandiri_va':
            $payload['payment_type'] = 'echannel';
            $payload['echannel'] = [
                'bill_info1' => 'Pembayaran:',
                'bill_info2' => 'Balla Fresh ' . mb_substr($orderNumber, 0, 18)
            ];
            break;

        case 'gopay':
            $payload['payment_type'] = 'gopay';
            $payload['gopay'] = [
                'enable_callback' => true,
                'callback_url' => $finishUrl
            ];
            break;

        case 'shopeepay':
            $payload['payment_type'] = 'shopeepay';
            $payload['shopeepay'] = [
                'callback_url' => $finishUrl
            ];
            break;

        default:
            return [
                'success' => false,
                'error' => "Metode pembayaran Midtrans '{$subMethod}' tidak didukung."
            ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'Ekstensi PHP cURL tidak aktif pada server.'
        ];
    }

    $ch = curl_init();
    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    curl_setopt($ch, CURLOPT_URL, MIDTRANS_CHARGE_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BallaFresh-CoreAPI/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'success' => false,
            'error' => 'Koneksi cURL ke Midtrans gagal: ' . $curlErr
        ];
    }

    $respData = json_decode((string)$rawResponse, true);

    if ($httpCode >= 200 && $httpCode < 300 && is_array($respData)) {
        // Ekstraksi data spesifik saluran
        $transId        = $respData['transaction_id'] ?? null;
        $paymentType    = $respData['payment_type'] ?? $subMethod;
        $transStatus    = $respData['transaction_status'] ?? 'pending';
        $expiryTime     = $respData['expiry_time'] ?? null;
        $paymentCode    = null;
        $billerCode     = null;
        $qrUrl          = null;
        $qrString       = $respData['qr_string'] ?? null;
        $deeplinkUrl    = null;

        // 1. QRIS & GoPay QR
        if (isset($respData['actions']) && is_array($respData['actions'])) {
            foreach ($respData['actions'] as $act) {
                $actName = $act['name'] ?? '';
                $actUrl  = $act['url'] ?? '';

                if ($actName === 'generate-qr-code') {
                    $qrUrl = $actUrl;
                } elseif ($actName === 'deeplink-redirect') {
                    $deeplinkUrl = $actUrl;
                }
            }
        }

        // 2. Bank Transfer VA (BCA, BNI, BRI, Permata)
        if (isset($respData['va_numbers']) && is_array($respData['va_numbers']) && !empty($respData['va_numbers'][0]['va_number'])) {
            $paymentCode = $respData['va_numbers'][0]['va_number'];
        } elseif (!empty($respData['permata_va_number'])) {
            $paymentCode = $respData['permata_va_number'];
        }

        // 3. Mandiri Bill / Echannel
        if (!empty($respData['biller_code']) && !empty($respData['bill_key'])) {
            $billerCode  = $respData['biller_code'];
            $paymentCode = $respData['bill_key'];
        }

        // 4. Fallback payment_code langsung
        if (empty($paymentCode) && !empty($respData['payment_code'])) {
            $paymentCode = $respData['payment_code'];
        }

        return [
            'success' => true,
            'order_id' => $orderNumber,
            'transaction_id' => $transId,
            'payment_type' => $paymentType,
            'sub_method' => $subMethod,
            'transaction_status' => $transStatus,
            'expiry_time' => $expiryTime,
            'payment_code' => $paymentCode,
            'biller_code' => $billerCode,
            'qr_url' => $qrUrl,
            'qr_string' => $qrString,
            'deeplink_url' => $deeplinkUrl,
            'raw_response' => $rawResponse,
            'parsed' => $respData
        ];
    }

    // Penanganan error Midtrans
    $errMsg = "Gagal memproses transaksi di Midtrans (HTTP {$httpCode})";
    if ($httpCode === 403) {
        $errMsg = "Akses Midtrans ditolak (HTTP 403 Forbidden). Pastikan Server Key Anda di Midtrans Dashboard (Settings -> Access Keys) memiliki izin transaksi 'Charge' dan tidak terblokir oleh batasan IP Whitelist.";
    } elseif (isset($respData['error_messages']) && is_array($respData['error_messages'])) {
        $errMsg .= ': ' . implode(', ', $respData['error_messages']);
    } elseif (!empty($respData['status_message'])) {
        $errMsg .= ': ' . $respData['status_message'];
    } elseif (!empty($respData['error'])) {
        $errMsg .= ': ' . $respData['error'];
    }

    return [
        'success' => false,
        'http_code' => $httpCode,
        'error' => $errMsg,
        'raw_response' => $rawResponse
    ];
}

/**
 * Cek status transaksi langsung dari Midtrans API v2
 *
 * @param string $orderId
 * @return array
 */
function getMidtransTransactionStatus(string $orderId): array {
    if (!isMidtransConfigured()) {
        return ['success' => false, 'error' => 'Kredensial Midtrans belum diatur.'];
    }

    $url = MIDTRANS_API_BASE_URL . '/' . rawurlencode($orderId) . '/status';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => $curlErr];
    }

    $data = json_decode((string)$response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }

    return ['success' => false, 'http_code' => $httpCode, 'data' => $data];
}

/**
 * Buat Snap Token via Midtrans Snap API
 * Flow: Backend kirim request ke Snap API → dapat snap_token → Frontend panggil window.snap.pay(token)
 *
 * @param string $orderNumber   ID pesanan unik
 * @param float  $grossAmount   Total tagihan (integer rupiah)
 * @param array  $customerDetails  ['first_name', 'email', 'phone']
 * @param array  $itemDetails   [['id','price','quantity','name'], ...]
 * @return array ['success'=>bool, 'snap_token'=>string, 'redirect_url'=>string, 'error'=>string]
 */
function createMidtransSnapToken(
    string $orderNumber,
    float  $grossAmount,
    array  $customerDetails,
    array  $itemDetails
): array {
    if (!isMidtransConfigured()) {
        return ['success' => false, 'error' => 'Kredensial Midtrans belum diatur.'];
    }

    $grossAmountInt = (int) round($grossAmount);

    // Payload Snap API
    $payload = [
        'transaction_details' => [
            'order_id'     => $orderNumber,
            'gross_amount' => $grossAmountInt,
        ],
        'customer_details' => [
            'first_name' => mb_substr((string)($customerDetails['first_name'] ?? ''), 0, 100),
            'email'      => (string)($customerDetails['email'] ?? ''),
            'phone'      => (string)($customerDetails['phone'] ?? ''),
        ],
        'item_details' => array_map(function($item) {
            return [
                'id'       => (string)($item['id'] ?? 'ITEM'),
                'price'    => (int)($item['price'] ?? 0),
                'quantity' => (int)($item['quantity'] ?? 1),
                'name'     => mb_substr((string)($item['name'] ?? 'Layanan'), 0, 50),
            ];
        }, $itemDetails),
        // Aktifkan payment method yang diinginkan di Snap popup
        'enabled_payments' => [
            'credit_card', 'qris', 'gopay', 'shopeepay', 'other_qris',
        ],
        'credit_card' => [
            'secure' => true,
        ],
        'callbacks' => [
            'finish' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/balla-fresh/payment_success.php',
        ],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MIDTRANS_SNAP_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $respData = json_decode((string)$rawResponse, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($respData['token'])) {
        return [
            'success'      => true,
            'snap_token'   => $respData['token'],
            'redirect_url' => $respData['redirect_url'] ?? '',
            'raw_response' => $rawResponse,
        ];
    }

    // Susun pesan error
    $errMsg = "Gagal membuat Snap Token (HTTP {$httpCode})";
    if ($httpCode === 403) {
        $errMsg = 'Akses Midtrans ditolak (HTTP 403). Pastikan Server Key valid dan IP Whitelist di dashboard Midtrans sudah dikosongkan.';
    } elseif (!empty($respData['error_messages'])) {
        $errMsg .= ': ' . implode(', ', (array)$respData['error_messages']);
    } elseif (!empty($respData['status_message'])) {
        $errMsg .= ': ' . $respData['status_message'];
    }

    return [
        'success'      => false,
        'http_code'    => $httpCode,
        'error'        => $errMsg,
        'raw_response' => $rawResponse,
    ];
}

/**
 * Verifikasi signature key dari notifikasi Midtrans
 * Formula: SHA512(order_id + status_code + gross_amount + ServerKey)
 *
 * @param string $orderId
 * @param string $statusCode
 * @param string|float $grossAmount
 * @param string $signatureKey
 * @return bool
 */
function verifyMidtransSignature(string $orderId, string $statusCode, $grossAmount, string $signatureKey): bool {
    $serverKey = MIDTRANS_SERVER_KEY;

    // Midtrans gross_amount pada webhook kadang berupa "10000.00" atau "10000"
    $hash1 = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    if (hash_equals($hash1, $signatureKey)) {
        return true;
    }

    // Coba format 2 desimal jika grossAmount numerik
    if (is_numeric($grossAmount)) {
        $formatted = number_format((float)$grossAmount, 2, '.', '');
        $hash2 = hash('sha512', $orderId . $statusCode . $formatted . $serverKey);
        if (hash_equals($hash2, $signatureKey)) {
            return true;
        }

        $formattedInt = (string)((int)$grossAmount);
        $hash3 = hash('sha512', $orderId . $statusCode . $formattedInt . $serverKey);
        if (hash_equals($hash3, $signatureKey)) {
            return true;
        }
    }

    return false;
}
