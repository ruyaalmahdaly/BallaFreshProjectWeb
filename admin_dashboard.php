<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
// Token keamanan untuk proses logout admin.
if (empty($_SESSION['admin_logout_csrf'])) {
    $_SESSION['admin_logout_csrf'] = bin2hex(random_bytes(32));
}

/*
 * Balla' Fresh Admin Dashboard
 * Requires: admins, users, user_addresses (address table is optional).
 * Protect every admin page with the admin session.
 */
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminName = (string) ($_SESSION['admin_name'] ?? 'Administrator');

$adminEmail = '';

$page = (string) ($_GET['page'] ?? 'dashboard');
$allowedPages = ['dashboard', 'orders', 'customers', 'activity', 'agency'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
$pageTitles = [
    'dashboard' => 'Dashboard',
    'orders' => 'Pengelolaan Pesanan',
    'customers' => 'Data Pelanggan',
    'activity' => 'Aktivitas Terbaru',
    'agency' => 'Operasional Agency'
];
$pageTitle = $pageTitles[$page];

// CSRF token untuk aksi pengelolaan pesanan.
if (empty($_SESSION['order_status_csrf'])) {
    $_SESSION['order_status_csrf'] = bin2hex(random_bytes(32));
}

$orderActionMessage = '';
$orderActionType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_status') {
    $postedToken = (string) ($_POST['csrf'] ?? '');
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = (string) ($_POST['status'] ?? '');
    $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];

    if (!hash_equals($_SESSION['order_status_csrf'], $postedToken)) {
        $orderActionMessage = 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.';
        $orderActionType = 'error';
    } elseif (!$orderId || !in_array($newStatus, $allowedStatuses, true)) {
        $orderActionMessage = 'Data perubahan status pesanan tidak valid.';
        $orderActionType = 'error';
    } else {
        $updateOrder = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        if ($updateOrder) {
            $updateOrder->bind_param('si', $newStatus, $orderId);
            if ($updateOrder->execute() && $updateOrder->affected_rows >= 0) {
                $orderActionMessage = 'Status pesanan berhasil diperbarui.';
            } else {
                $orderActionMessage = 'Status pesanan gagal diperbarui.';
                $orderActionType = 'error';
            }
            $updateOrder->close();
        } else {
            $orderActionMessage = 'Fitur perubahan status belum dapat dijalankan. Periksa struktur tabel orders.';
            $orderActionType = 'error';
        }
    }
}


$stmt = $conn->prepare(
    'SELECT name, email, is_approved, is_owner
     FROM admins
     WHERE id = ?
     LIMIT 1'
);
if ($stmt) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $adminRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adminRow || (int) $adminRow['is_approved'] !== 1) {
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_name'],
            $_SESSION['admin_is_owner'],
            $_SESSION['admin_otp_verified']
        );
        header('Location: admin_login.php');
        exit;
    }
    $adminName = (string) $adminRow['name'];
    $adminEmail = (string) $adminRow['email'];
    $_SESSION['admin_is_owner'] = (int) $adminRow['is_owner'];
}

function e(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function scalarQuery(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_row();
    return (int) ($row[0] ?? 0);
}

function tableColumns(mysqli $conn, string $table): array {
    $allowed = ['users', 'user_addresses', 'agency_site_content', 'agency_services', 'services', 'categories', 'service_reviews'];
    if (!in_array($table, $allowed, true)) return [];
    $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if (!$result) return [];
    $columns = [];
    while ($row = $result->fetch_assoc()) $columns[] = (string) $row['Field'];
    $result->free();
    return $columns;
}

$customerCount = scalarQuery($conn, 'SELECT COUNT(*) FROM users');
$adminCount = scalarQuery($conn, 'SELECT COUNT(*) FROM admins');

$addressTableExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'user_addresses'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $addressTableExists = true;
}
$addressCount = $addressTableExists
    ? scalarQuery($conn, 'SELECT COUNT(DISTINCT user_id) FROM user_addresses')
    : 0;

$pendingCount = null;
$pendingTableCheck = $conn->query("SHOW TABLES LIKE 'registration_pending'");
if ($pendingTableCheck && $pendingTableCheck->num_rows > 0) {
    $pendingCount = scalarQuery($conn, 'SELECT COUNT(*) FROM registration_pending');
}


$search = trim((string) ($_GET['q'] ?? ''));
$customers = [];
$searchError = '';
$userColumns = tableColumns($conn, 'users');
$addressColumns = $addressTableExists ? tableColumns($conn, 'user_addresses') : [];
$phoneColumn = '';
foreach (['phone', 'phone_number', 'mobile', 'no_hp', 'telephone'] as $candidate) {
    if (in_array($candidate, $userColumns, true)) { $phoneColumn = $candidate; break; }
}
$createdColumn = in_array('created_at', $userColumns, true) ? 'created_at' : '';
$phoneSelect = $phoneColumn !== '' ? "u.`{$phoneColumn}` AS phone" : "NULL AS phone";
$createdSelect = $createdColumn !== '' ? "u.`{$createdColumn}` AS registered_at" : "NULL AS registered_at";

$addressSelects = [];
foreach (['address_details', 'street', 'address', 'subdistrict', 'district', 'city', 'province', 'postal_code'] as $column) {
    if ($addressTableExists && in_array($column, $addressColumns, true)) {
        $addressSelects[] = "MAX(a.`{$column}`) AS `{$column}`";
    } else {
        $addressSelects[] = "NULL AS `{$column}`";
    }
}
$customerSql = "SELECT u.id, u.name, u.email, {$phoneSelect}, {$createdSelect}, " .
    implode(', ', $addressSelects) . " FROM users u";
if ($addressTableExists) $customerSql .= " LEFT JOIN user_addresses a ON a.user_id = u.id";
$searchWhere = '';
if ($search !== '') {
    $searchWhere = " WHERE (u.name LIKE ? OR u.email LIKE ?" .
        ($phoneColumn !== '' ? " OR u.`{$phoneColumn}` LIKE ?" : "") . ")";
    $customerSql .= $searchWhere;
}
$customerSql .= " GROUP BY u.id ORDER BY u.id DESC";

$customerStmt = $conn->prepare($customerSql);
if ($customerStmt) {
    if ($search !== '') {
        $like = '%' . $search . '%';
        if ($phoneColumn !== '') $customerStmt->bind_param('sss', $like, $like, $like);
        else $customerStmt->bind_param('ss', $like, $like);
    }
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    while ($row = $customerResult->fetch_assoc()) $customers[] = $row;
    $customerStmt->close();
} else {
    $searchError = 'Data pelanggan belum dapat dimuat. Periksa struktur tabel users dan user_addresses.';
}

// Ekspor seluruh hasil pencarian pelanggan ke CSV yang kompatibel dengan Excel/Google Sheets.
if ($page === 'customers' && ($_GET['export'] ?? '') === 'customers') {
    $filename = 'data-pelanggan-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Nama Lengkap', 'Email', 'Nomor HP', 'Detail Alamat', 'RT/RW', 'Kelurahan/Kecamatan', 'Kota', 'Provinsi', 'Kode Pos', 'Tanggal Daftar']);
    foreach ($customers as $customer) {
        $address = trim(implode(', ', array_filter([
            $customer['address_details'] ?? '', $customer['street'] ?? '', $customer['address'] ?? ''
        ], static fn($value) => trim((string) $value) !== '')));
        fputcsv($output, [
            $customer['id'], $customer['name'], $customer['email'], $customer['phone'] ?? '',
            $address, $customer['rt_rw'] ?? '', $customer['subdistrict'] ?? ($customer['district'] ?? ''),
            $customer['city'] ?? '', $customer['province'] ?? '',
            $customer['postal_code'] ?? '', $customer['registered_at'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$latestUsers = [];
$stmt = $conn->prepare('SELECT id, name, email FROM users ORDER BY id DESC LIMIT 5');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $latestUsers[] = $row;
    }
    $stmt->close();
}


// Ringkasan dan daftar pesanan dari tabel orders, customers, dan order_items.
$orderCounts = [
    'all' => 0,
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$orderStatsResult = $conn->query(
    "SELECT status, COUNT(*) AS total FROM orders GROUP BY status"
);
if ($orderStatsResult) {
    while ($stat = $orderStatsResult->fetch_assoc()) {
        $statusKey = (string) $stat['status'];
        if (array_key_exists($statusKey, $orderCounts)) {
            $orderCounts[$statusKey] = (int) $stat['total'];
        }
        $orderCounts['all'] += (int) $stat['total'];
    }
    $orderStatsResult->free();
}

$orders = [];
$orderQuery = "
    SELECT
        o.id,
        o.order_number,
        o.customer_id,
        o.total_amount,
        o.status,
        o.notes,
        o.created_at,
        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        c.address AS customer_address,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity,
        GROUP_CONCAT(
            CONCAT(oi.service_name, ' (', oi.quantity, 'x)')
            ORDER BY oi.id ASC SEPARATOR '||'
        ) AS ordered_services
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    GROUP BY
        o.id, o.order_number, o.customer_id, o.total_amount, o.status,
        o.notes, o.created_at, c.name, c.email, c.phone, c.address
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 100
";
$orderResult = $conn->query($orderQuery);
$orderLoadError = '';
if ($orderResult) {
    while ($row = $orderResult->fetch_assoc()) {
        $orders[] = $row;
    }
    $orderResult->free();
} else {
    $orderLoadError = 'Data pesanan belum dapat dimuat. Pastikan tabel orders, customers, dan order_items memiliki struktur yang sesuai.';
}

function orderStatusLabel(string $status): string {
    return [
        'pending' => 'Menunggu',
        'processing' => 'Diproses',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ][$status] ?? ucfirst($status);
}

function orderStatusClass(string $status): string {
    return [
        'pending' => 'pending',
        'processing' => 'processing',
        'completed' => 'completed',
        'cancelled' => 'cancelled'
    ][$status] ?? 'pending';
}


// CMS ringan untuk mengelola konten website dari dashboard.
// Tabel terpisah agar tidak mengubah struktur tabel transaksi yang sudah ada.
$conn->query("CREATE TABLE IF NOT EXISTS agency_site_content (
    id INT NOT NULL PRIMARY KEY,
    hero_title VARCHAR(255) NOT NULL DEFAULT '',
    hero_description TEXT NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(50) NULL,
    hero_image VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("INSERT IGNORE INTO agency_site_content (id, hero_title, hero_description) VALUES (1, 'Balla’ Fresh', 'Solusi layanan terbaik untuk kebutuhan Anda.')");
$conn->query("CREATE TABLE IF NOT EXISTS agency_services (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$agencyMessage = '';
$agencyMessageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agency_action'])) {
    $postedCsrf = (string) ($_POST['agency_csrf'] ?? '');
    if (!hash_equals($_SESSION['order_status_csrf'], $postedCsrf)) {
        $agencyMessage = 'Sesi formulir tidak valid. Muat ulang halaman.';
        $agencyMessageType = 'error';
    } else {
        $agencyAction = (string) $_POST['agency_action'];
        if ($agencyAction === 'save_content') {
            $heroTitle = trim((string) ($_POST['hero_title'] ?? ''));
            $heroDescription = trim((string) ($_POST['hero_description'] ?? ''));
            $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
            $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
            $imagePath = null;
            if (!empty($_FILES['hero_image']['name']) && is_uploaded_file($_FILES['hero_image']['tmp_name'])) {
                $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = mime_content_type($_FILES['hero_image']['tmp_name']);
                if (!isset($allowedMime[$mime]) || (int) $_FILES['hero_image']['size'] > 4 * 1024 * 1024) {
                    $agencyMessage = 'Gambar harus JPG, PNG, atau WEBP dan maksimal 4 MB.';
                    $agencyMessageType = 'error';
                } else {
                    $uploadDir = __DIR__ . '/uploads/agency';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fileName = 'hero-' . bin2hex(random_bytes(8)) . '.' . $allowedMime[$mime];
                    if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $uploadDir . '/' . $fileName)) {
                        $imagePath = 'uploads/agency/' . $fileName;
                    } else {
                        $agencyMessage = 'Gagal menyimpan gambar. Periksa izin folder uploads/agency.';
                        $agencyMessageType = 'error';
                    }
                }
            }
            if ($agencyMessageType === 'success') {
                if ($imagePath !== null) {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_title=?, hero_description=?, contact_email=?, contact_phone=?, hero_image=? WHERE id=1');
                    $stmt->bind_param('sssss', $heroTitle, $heroDescription, $contactEmail, $contactPhone, $imagePath);
                } else {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_title=?, hero_description=?, contact_email=?, contact_phone=? WHERE id=1');
                    $stmt->bind_param('ssss', $heroTitle, $heroDescription, $contactEmail, $contactPhone);
                }
                if ($stmt && $stmt->execute()) $agencyMessage = 'Konten berhasil disimpan ke database.';
                else { $agencyMessage = 'Konten gagal disimpan.'; $agencyMessageType = 'error'; }
                if ($stmt) $stmt->close();
            }
        } elseif ($agencyAction === 'save_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 1);
            $name = trim((string) ($_POST['service_name'] ?? ''));
            $description = trim((string) ($_POST['service_description'] ?? ''));
            $features = trim((string) ($_POST['service_features'] ?? ''));
            $price = (float) ($_POST['service_price'] ?? 0);
            $estimatedDays = max(1, (int) ($_POST['estimated_days'] ?? 3));
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $serviceImage = trim((string) ($_POST['service_image'] ?? ''));

            if (!empty($_FILES['service_image_file']['name']) && is_uploaded_file($_FILES['service_image_file']['tmp_name'])) {
                $serviceMime = mime_content_type($_FILES['service_image_file']['tmp_name']);
                $serviceTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($serviceTypes[$serviceMime]) || (int) $_FILES['service_image_file']['size'] > 4 * 1024 * 1024) {
                    $agencyMessage = 'Gambar layanan harus JPG, PNG, atau WEBP dan maksimal 4 MB.';
                    $agencyMessageType = 'error';
                } else {
                    $serviceUploadDir = __DIR__ . '/uploads/services';
                    if (!is_dir($serviceUploadDir)) mkdir($serviceUploadDir, 0755, true);
                    $serviceFileName = 'service-' . bin2hex(random_bytes(8)) . '.' . $serviceTypes[$serviceMime];
                    if (move_uploaded_file($_FILES['service_image_file']['tmp_name'], $serviceUploadDir . '/' . $serviceFileName)) {
                        $serviceImage = 'uploads/services/' . $serviceFileName;
                    } else {
                        $agencyMessage = 'Gagal mengunggah gambar layanan.';
                        $agencyMessageType = 'error';
                    }
                }
            }
            if ($agencyMessageType === 'error') {
                // Pesan validasi unggahan sudah disiapkan di atas.
            } elseif ($name === '' || $price < 0) {
                $agencyMessage = 'Nama layanan wajib diisi dan harga tidak boleh negatif.';
                $agencyMessageType = 'error';
            } else {
                if ($serviceId > 0) {
                    $stmt = $conn->prepare('UPDATE services SET category_id=?, name=?, description=?, features=?, price=?, image=?, estimated_days=?, is_available=? WHERE id=?');
                    $stmt->bind_param('isssdsiii', $categoryId, $name, $description, $features, $price, $serviceImage, $estimatedDays, $isAvailable, $serviceId);
                } else {
                    $stmt = $conn->prepare('INSERT INTO services (category_id, name, description, features, price, image, estimated_days, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isssdsii', $categoryId, $name, $description, $features, $price, $serviceImage, $estimatedDays, $isAvailable);
                }
                if ($stmt && $stmt->execute()) $agencyMessage = 'Data layanan berhasil disimpan langsung ke database website.';
                else { $agencyMessage = 'Data layanan gagal disimpan.'; $agencyMessageType = 'error'; }
                if ($stmt) $stmt->close();
            }
        } elseif ($agencyAction === 'delete_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM services WHERE id=?');
            if ($stmt) {
                $stmt->bind_param('i', $serviceId);
                $agencyMessage = $stmt->execute() ? 'Layanan berhasil dihapus dari website.' : 'Layanan gagal dihapus.';
                if ($stmt->errno) $agencyMessageType = 'error';
                $stmt->close();
            }
        } elseif ($agencyAction === 'toggle_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE service_reviews SET is_approved = IF(is_approved = 1, 0, 1) WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $reviewId);
                $agencyMessage = $stmt->execute() ? 'Status tayang ulasan berhasil diubah.' : 'Gagal mengubah status ulasan.';
                $stmt->close();
            }
        } elseif ($agencyAction === 'delete_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM service_reviews WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $reviewId);
                $agencyMessage = $stmt->execute() ? 'Ulasan berhasil dihapus.' : 'Gagal menghapus ulasan.';
                $stmt->close();
            }
        }
    }
}
$siteContent = ['hero_title' => '', 'hero_description' => '', 'contact_email' => '', 'contact_phone' => '', 'hero_image' => ''];
$contentResult = $conn->query('SELECT * FROM agency_site_content WHERE id=1 LIMIT 1');
if ($contentResult && $contentRow = $contentResult->fetch_assoc()) $siteContent = array_merge($siteContent, $contentRow);

$categoriesList = [];
$catResult = $conn->query('SELECT id, name FROM categories ORDER BY id ASC');
if ($catResult) {
    while ($catRow = $catResult->fetch_assoc()) $categoriesList[] = $catRow;
    $catResult->free();
}

$allServices = [];
$serviceResult = $conn->query('
    SELECT s.*, c.name AS category_name,
           (SELECT COUNT(*) FROM service_reviews WHERE service_id = s.id) AS review_count
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.id
    ORDER BY s.id ASC
');
if ($serviceResult) {
    while ($serviceRow = $serviceResult->fetch_assoc()) $allServices[] = $serviceRow;
    $serviceResult->free();
}

$allReviews = [];
$revResult = $conn->query('
    SELECT r.*, s.name AS service_name
    FROM service_reviews r
    LEFT JOIN services s ON r.service_id = s.id
    ORDER BY r.created_at DESC
    LIMIT 25
');
if ($revResult) {
    while ($revRow = $revResult->fetch_assoc()) $allReviews[] = $revRow;
    $revResult->free();
}

$initials = 'BF';
$nameParts = preg_split('/\s+/', trim($adminName)) ?: [];
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} elseif (!empty($nameParts[0])) {
    $initials = strtoupper(substr($nameParts[0], 0, 2));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Admin Balla’ Fresh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --green-950: #5b8121;
            --green-900: #e48e1e;
            --green-800: #3c5d28;
            --green-700: #527832;
            --green-600: #668c3d;
            --green-100: #edf4e5;
            --green-50: #f6f9f1;
            --cream: #fbfcf8;
            --ink: #263222;
            --muted: #78816f;
            --line: #e7ecdf;
            --white: #fff;
            --amber: #c28b2c;
            --shadow: 0 10px 30px rgba(36, 58, 25, .055);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #f7f9f3;
            font-family: "DM Sans", sans-serif;
            font-size: 14px;
        }
        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }
        .app { min-height: 100vh; display: grid; grid-template-columns: 250px minmax(0, 1fr); }
        .sidebar {
            position: sticky; top: 0; height: 100vh; padding: 25px 17px;
            display: flex; flex-direction: column; background: var(--green-950); color: #eaf2e2;
        }
        .brand { display: flex; align-items: center; gap: 11px; padding: 0 10px 29px; }
        .brand-mark {
            width: 43px; height: 43px; border-radius: 14px; background: #fff;
            display: grid; place-items: center; overflow: hidden; flex: 0 0 auto;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .brand-fallback { color: var(--green-800); font-family: "Manrope", sans-serif; font-weight: 800; font-size: 11px; display: none; }
        .brand-name { font: 800 18px/1.1 "Manrope", sans-serif; letter-spacing: -.55px; color: #ffff; }
        .brand-name span { color: #ffff; }
        .brand-caption { color: #b3cba8; font-size: 10px; margin-top: 5px; letter-spacing: 1.45px; text-transform: uppercase; }
        .nav-label { color: #94aa87; font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; padding: 0 13px; margin: 5px 0 10px; font-weight: 700; }
        .side-nav { display: grid; gap: 6px; }
        .side-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 13px;
            border-radius: 12px; color: #d2dfca; font-weight: 600; transition: .2s;
        }
        .side-link i { width: 18px; text-align: center; font-size: 15px; color: #a8c18f; }
        .side-link:hover, .side-link.active { color: #fff; background: rgba(255,255,255,.11); }
        .side-link.active { box-shadow: inset 3px 0 #b7d88b; }
        .side-spacer { flex: 1; }
        .side-help { padding: 16px; margin: 22px 3px 15px; border: 1px solid rgba(255,255,255,.12); border-radius: 15px; background: rgba(255,255,255,.055); }
        .side-help i { color: #b8d891; font-size: 17px; }
        .side-help strong { display: block; margin: 10px 0 5px; font-size: 12px; }
        .side-help p { color: #b7c7ae; font-size: 11px; line-height: 1.6; margin: 0; }
        .side-bottom { border-top: 1px solid rgba(255,255,255,.12); padding: 16px 8px 0; display: flex; align-items: center; gap: 10px; }
        .avatar { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 13px; background: #dcebc9; color: var(--green-900); font-weight: 800; flex: 0 0 auto; }
        .side-user { min-width: 0; flex: 1; }
        .side-user strong { display: block; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .side-user small { display: block; color: #adc1a2; font-size: 11px; margin-top: 4px; }
        .side-logout { color: #c4d3bc; padding: 8px; border-radius: 9px; }
        .side-logout:hover { background: rgba(255,255,255,.1); color: #fff; }
        .main { min-width: 0; }
        .topbar {
            height: 78px; padding: 0 34px; display: flex; align-items: center; justify-content: space-between;
            gap: 18px; background: rgba(255,255,255,.88); border-bottom: 1px solid var(--line);
        }
        .crumb { color: var(--muted); font-size: 12px; }
        .crumb strong { color: var(--green-800); font-weight: 700; }
        .top-actions { display: flex; align-items: center; gap: 10px; }
        .date-chip { display: inline-flex; align-items: center; gap: 8px; color: #5e6b54; background: var(--green-50); border: 1px solid var(--line); padding: 10px 12px; border-radius: 11px; font-size: 12px; }
        .top-avatar { display: flex; align-items: center; gap: 9px; padding-left: 13px; border-left: 1px solid var(--line); }
        .top-avatar .avatar { width: 35px; height: 35px; border-radius: 50%; }
        .top-avatar strong { display: block; font-size: 12px; }
        .top-avatar small { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }
        .content { max-width: 1600px; margin: 0 auto; padding: 30px 34px 42px; }
        .welcome-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 25px; }
        .eyebrow { color: var(--green-700); font-size: 10px; letter-spacing: 1.8px; font-weight: 800; text-transform: uppercase; }
        h1 { font: 800 clamp(25px, 2.2vw, 32px)/1.2 "Manrope", sans-serif; letter-spacing: -1px; margin: 9px 0 8px; color: #e48e1e; }
        .welcome-row p { margin: 0; color: var(--muted); line-height: 1.65; }
        .welcome-tag { display: inline-flex; align-items: center; gap: 8px; border-radius: 100px; padding: 9px 13px; background: var(--green-100); color: var(--green-800); font-size: 11px; font-weight: 700; white-space: nowrap; }
        .welcome-tag span { width: 7px; height: 7px; background: #7aa84c; border-radius: 50%; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 15px; margin-bottom: 22px; }
        .stat-card { background: var(--white); border: 1px solid var(--line); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); min-width: 0; }
        .stat-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .stat-icon { display: grid; place-items: center; width: 43px; height: 43px; border-radius: 14px; background: var(--green-100); color: var(--green-700); font-size: 17px; }
        .stat-icon.amber { background: #fbf1df; color: #ad7b25; }
        .stat-icon.sage { background: #e9f1ed; color: #4f7968; }
        .stat-icon.lilac { background: #f0edf8; color: #7865a4; }
        .stat-kicker { color: var(--muted); font-size: 11px; font-weight: 600; }
        .stat-value { margin-top: 17px; font: 800 29px/1 "Manrope", sans-serif; color: #e48e1e;; letter-spacing: -.8px; }
        .stat-foot { margin-top: 9px; font-size: 11px; color: var(--muted); line-height: 1.5; }
        .stat-foot i { color: var(--green-600); margin-right: 4px; }
        .panel-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(280px, .85fr); gap: 20px; align-items: start; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); min-width: 0; }
        .panel-head { padding: 21px 22px 17px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #eff2e9; }
        .panel-head h2 { font: 800 15px/1.35 "Manrope", sans-serif; margin: 0; color: var(--green-950); }
        .panel-head p { margin: 5px 0 0; color: var(--muted); font-size: 11px; }
        .panel-body { padding: 19px 22px 22px; }
        .search-form { display: flex; gap: 8px; width: min(100%, 390px); }
        .search-box { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; border: 1px solid var(--line); border-radius: 10px; padding: 0 11px; background: #fcfdf9; }
        .search-box i { color: #8b9780; font-size: 12px; }
        .search-box input { width: 100%; min-width: 0; padding: 10px 0; border: 0; outline: 0; background: transparent; color: var(--ink); font-size: 12px; }
        .btn { border: 0; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 10px; padding: 10px 13px; background: var(--green-700); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
        .btn:hover { background: var(--green-900); }
        .btn-light { background: var(--green-50); color: var(--green-800); border: 1px solid var(--line); }
        .btn-light:hover { background: var(--green-100); }
        .table-wrap { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; min-width: 570px; }
        th { padding: 11px 12px; text-align: left; color: #849078; background: #fafbf7; font-size: 10px; text-transform: uppercase; letter-spacing: .8px; font-weight: 800; white-space: nowrap; }
        td { padding: 14px 12px; border-top: 1px solid #f0f2eb; font-size: 12px; vertical-align: top; }
        td:first-child, th:first-child { padding-left: 22px; }
        td:last-child, th:last-child { padding-right: 22px; }
        .customer-cell { display: flex; align-items: center; gap: 10px; min-width: 170px; }
        .customer-avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 11px; background: var(--green-100); color: var(--green-800); font-size: 11px; font-weight: 800; flex: 0 0 auto; }
        .customer-cell strong { display: block; font-size: 12px; color: #34412d; }
        .customer-cell small { display: block; color: #87907e; font-size: 10px; margin-top: 4px; }
        .muted-cell { color: #77816d; max-width: 180px; line-height: 1.5; }
        .status-pill { display: inline-flex; align-items: center; gap: 5px; border-radius: 100px; padding: 6px 9px; background: #edf5e7; color: #557d2d; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .status-pill i { font-size: 7px; }
        .empty { padding: 32px 20px; text-align: center; color: var(--muted); line-height: 1.7; }
        .empty i { display: block; margin-bottom: 10px; color: #a9bb96; font-size: 25px; }
        .notice { margin: 0 22px 16px; border: 1px solid #f0dfb8; background: #fff9eb; color: #805e20; padding: 11px 13px; border-radius: 10px; font-size: 12px; line-height: 1.5; }
        .activity-list { display: grid; }
        .activity-item { display: flex; align-items: flex-start; gap: 11px; padding: 13px 0; border-bottom: 1px solid #f0f2eb; }
        .activity-item:first-child { padding-top: 0; }
        .activity-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .activity-icon { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 11px; background: var(--green-100); color: var(--green-700); }
        .activity-item strong { display: block; font-size: 12px; line-height: 1.5; }
        .activity-item p { margin: 4px 0 0; color: var(--muted); font-size: 11px; line-height: 1.55; overflow-wrap: anywhere; }
        .activity-id { margin-left: auto; color: #8a9480; font-size: 10px; white-space: nowrap; }
        .ops-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }
        .ops-card { border: 1px solid var(--line); background: #fcfdf9; border-radius: 14px; padding: 15px; }
        .ops-card i { color: var(--green-700); font-size: 16px; }
        .ops-card strong { display: block; margin: 10px 0 5px; font-size: 12px; }
        .ops-card p { color: var(--muted); font-size: 11px; line-height: 1.6; margin: 0 0 10px; }
        .coming { display: inline-block; padding: 5px 7px; background: #f3f0e6; color: #92753d; border-radius: 6px; font-size: 9px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; }
        .footnote { margin-top: 20px; padding: 14px 17px; display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e5eddb; border-radius: 13px; background: #f3f8ec; color: #617452; font-size: 11px; line-height: 1.7; }
        .footnote i { margin-top: 2px; color: var(--green-700); }
        .mobile-menu { display: none; border: 1px solid var(--line); background: #fff; color: var(--green-800); width: 40px; height: 40px; border-radius: 11px; }
        .mobile-overlay { display: none; }
        @media (max-width: 1180px) {
            .stats-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .panel-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .app { display: block; }
            .sidebar { position: fixed; z-index: 100; left: 0; top: 0; width: 260px; transform: translateX(-105%); transition: transform .22s ease; box-shadow: 20px 0 60px rgba(0,0,0,.15); }
            .sidebar.open { transform: translateX(0); }
            .mobile-overlay.show { display: block; position: fixed; inset: 0; z-index: 90; background: rgba(20,35,16,.4); }
            .topbar { height: 68px; padding: 0 17px; }
            .mobile-menu { display: inline-grid; place-items: center; }
            .crumb { margin-right: auto; }
            .date-chip { display: none; }
            .top-avatar { padding-left: 0; border-left: 0; }
            .top-avatar .avatar { width: 32px; height: 32px; }
            .top-avatar .top-user-copy { display: none; }
            .content { padding: 24px 16px 32px; }
            .welcome-row { align-items: flex-start; flex-direction: column; }
            .stats-grid { gap: 10px; }
            .stat-card { padding: 15px; border-radius: 15px; }
            .stat-icon { width: 37px; height: 37px; border-radius: 12px; }
            .stat-value { font-size: 25px; }
            .panel-head { padding: 17px 16px; align-items: flex-start; flex-direction: column; }
            .panel-body { padding: 16px; }
            .notice { margin: 0 16px 14px; }
            .search-form { width: 100%; }
            .ops-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 390px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px; }
            .stat-value { font-size: 22px; }
            .ops-grid { grid-template-columns: 1fr; }
            .welcome-tag { white-space: normal; }
        }

        /* ===== POPUP KONFIRMASI LOGOUT ADMIN ===== */

.side-logout {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
}

.logout-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(25, 38, 20, 0.55);
    backdrop-filter: blur(5px);

    opacity: 0;
    visibility: hidden;
    transition: .25s ease;
}

.logout-modal.show {
    opacity: 1;
    visibility: visible;
}

.logout-dialog {
    width: 100%;
    max-width: 400px;
    padding: 32px 27px;
    border-radius: 22px;
    background: #fff;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.16);

    transform: translateY(15px) scale(.97);
    transition: .25s ease;
}

.logout-modal.show .logout-dialog {
    transform: translateY(0) scale(1);
}

.logout-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 19px;
    display: grid;
    place-items: center;
    border-radius: 20px;
    background: #edf4e5;
    color: #5b8121;
    font-size: 25px;
}

.logout-dialog h2 {
    margin: 0 0 10px;
    color: #34452b;
    font: 800 20px "Manrope", sans-serif;
}

.logout-dialog p {
    margin: 0;
    color: #78816f;
    font-size: 13px;
    line-height: 1.7;
}

.logout-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.logout-actions button {
    flex: 1;
    padding: 12px 10px;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.logout-cancel {
    border: 1px solid #e5ebdf;
    background: #f7f9f3;
    color: #526447;
}

.logout-cancel:hover {
    background: #edf4e5;
}

.logout-confirm {
    border: 1px solid #5b8121;
    background: #5b8121;
    color: white;
}

.logout-confirm:hover {
    background: #45671a;
}

@media(max-width: 420px) {
    .logout-dialog {
        padding: 27px 20px;
    }

    .logout-actions {
        flex-direction: column-reverse;
    }
}
.logout-actions form {
    flex: 1;
    display: flex;
}

.logout-actions form button {
    width: 100%;
}

        .order-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding:18px 22px 0}
        .order-mini-stat{border:1px solid var(--line);background:#fcfdf9;border-radius:13px;padding:13px}
        .order-mini-stat strong{display:block;font:800 23px "Manrope",sans-serif;color:var(--ink);margin-top:7px}
        .order-mini-stat span{font-size:11px;color:var(--muted)}
        .order-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .order-filter{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:10px;padding:10px 12px;font:inherit;font-size:12px}
        .order-table{min-width:900px}
        .order-number{font-weight:800;color:var(--green-800);display:block}
        .order-sub{display:block;color:var(--muted);font-size:10px;margin-top:4px;line-height:1.5}
        .order-services{max-width:240px;white-space:normal;line-height:1.6;color:#5f6c56}
        .order-total{font-weight:800;white-space:nowrap}
        .status-pill.pending{background:#fff4df;color:#966719}
        .status-pill.processing{background:#eaf2fb;color:#3d6e9e}
        .status-pill.completed{background:#e8f5e9;color:#347448}
        .status-pill.cancelled{background:#fcebea;color:#a34b45}
        .status-form{display:flex;gap:6px;align-items:center;min-width:185px}
        .status-form select{min-width:105px;border:1px solid var(--line);border-radius:8px;padding:8px;background:#fff;color:var(--ink);font:inherit;font-size:11px}
        .status-form button{border:0;border-radius:8px;background:var(--green-700);color:#fff;padding:8px 10px;font-size:11px;font-weight:700;cursor:pointer}
        .order-notice{margin:15px 22px 0;padding:11px 13px;border-radius:10px;font-size:12px;line-height:1.5}
        .order-notice.success{background:#edf7e9;border:1px solid #d8e9ca;color:#42652f}
        .order-notice.error{background:#fff0ee;border:1px solid #f0d3cf;color:#99443b}
        .order-detail{font-size:11px;color:var(--muted);line-height:1.6;margin-top:5px;max-width:220px;overflow-wrap:anywhere}
        @media(max-width:900px){.order-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.order-summary-grid{padding:15px 16px 0}.order-notice{margin:14px 16px 0}}

    </style>
</head>
<body class="page-<?= e($page) ?>">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark">
                <img src="logo-balla-fresh.png" alt="Logo Balla’ Fresh"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <span class="brand-fallback">BF</span>
            </span>
            <span>
                <span class="brand-name">Balla’ <span>Fresh</span></span>
                <span class="brand-caption">Agency workspace</span>
            </span>
        </a>

        <div class="nav-label">Workspace</div>
        <nav class="side-nav" aria-label="Navigasi admin">
            <a href="admin_dashboard.php?page=dashboard" class="side-link <?= $page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="admin_dashboard.php?page=orders" class="side-link <?= $page === 'orders' ? 'active' : '' ?>"><i class="fa-solid fa-cart-shopping"></i> Pengelolaan Pesanan</a>
            <a href="admin_dashboard.php?page=customers" class="side-link <?= $page === 'customers' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Data Pelanggan</a>
            <a href="admin_dashboard.php?page=activity" class="side-link <?= $page === 'activity' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas Terbaru</a>
            <a href="admin_dashboard.php?page=agency" class="side-link <?= $page === 'agency' ? 'active' : '' ?>"><i class="fa-solid fa-seedling"></i> Operasional Agency</a>
        </nav>

        <div class="side-spacer"></div>
        <div class="side-help">
            <i class="fa-regular fa-circle-check"></i>
            <strong>Workspace Balla’ Fresh</strong>
            <p>Pantau data pelanggan dan status sistem dari satu tempat.</p>
        </div>
        <div class="side-bottom">
            <span class="avatar"><?= e($initials) ?></span>
            <span class="side-user">
                <strong><?= e($adminName) ?></strong>
                <small>Administrator</small>
            </span>
            <button
            type="button"
            class="side-logout"
            id="logoutButton"
            title="Logout"
            aria-label="Logout"
>
    <i class="fa-solid fa-arrow-right-from-bracket"></i>
</button>
        </div>
    </aside>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <main class="main">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
            <div class="crumb">Workspace <span aria-hidden="true">/</span> <strong><?= e($pageTitle) ?></strong></div>
            <div class="top-actions">
                <div class="date-chip"><i class="fa-regular fa-calendar"></i> <span id="todayLabel"></span></div>
                <div class="top-avatar">
                    <span class="avatar"><?= e($initials) ?></span>
                    <span class="top-user-copy">
                        <strong><?= e($adminName) ?></strong>
                        <small>Admin Agency</small>
                    </span>
                </div>
            </div>
        </header>


        <div class="content" id="overview">
        <?php if ($page === 'dashboard'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · ADMIN PANEL</span>
                    <h1>Dashboard Overview</h1>
                    <p>Selamat datang, <?= e($adminName) ?>. Pantau ringkasan bisnis dan aktivitas website dari satu tempat.</p>
                </div>
                <span class="welcome-tag"><span></span> Sistem terhubung ke database</span>
            </section>
            <section class="stats-grid" aria-label="Ringkasan data">
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Total Pelanggan</span><span class="stat-icon"><i class="fa-solid fa-users"></i></span></div><div class="stat-value"><?= number_format($customerCount, 0, ',', '.') ?></div><div class="stat-foot"><i class="fa-solid fa-database"></i> Akun tersimpan</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Total Pesanan</span><span class="stat-icon amber"><i class="fa-solid fa-cart-shopping"></i></span></div><div class="stat-value"><?= number_format($orderCounts['all'], 0, ',', '.') ?></div><div class="stat-foot">Seluruh pesanan masuk</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Pesanan Menunggu</span><span class="stat-icon sage"><i class="fa-regular fa-clock"></i></span></div><div class="stat-value"><?= number_format($orderCounts['pending'], 0, ',', '.') ?></div><div class="stat-foot">Perlu ditindaklanjuti</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Administrator</span><span class="stat-icon lilac"><i class="fa-solid fa-user-shield"></i></span></div><div class="stat-value"><?= number_format($adminCount, 0, ',', '.') ?></div><div class="stat-foot">Akun pengelola sistem</div></article>
            </section>
            <section class="panel-grid">
                <article class="panel">
                    <div class="panel-head"><div><h2>Pesanan Terbaru</h2><p>Ringkasan pesanan yang baru masuk.</p></div><a class="btn btn-light" href="admin_dashboard.php?page=orders">Lihat semua</a></div>
                    <div class="table-wrap"><table><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead><tbody>
                    <?php if (!$orders): ?><tr><td colspan="4"><div class="empty">Belum ada pesanan.</div></td></tr>
                    <?php else: foreach (array_slice($orders, 0, 5) as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong></td><td><?= e($order['customer_name']) ?></td><td>Rp<?= number_format((float)$order['total_amount'],0,',','.') ?></td><td><span class="status-pill <?= e(orderStatusClass((string)$order['status'])) ?>"><?= e(orderStatusLabel((string)$order['status'])) ?></span></td></tr><?php endforeach; endif; ?>
                    </tbody></table></div>
                </article>
                <article class="panel"><div class="panel-head"><div><h2>Aktivitas Terbaru</h2><p>Akun pelanggan terbaru.</p></div><a class="btn btn-light" href="admin_dashboard.php?page=activity">Lihat aktivitas</a></div><div class="panel-body"><div class="activity-list">
                    <?php if (!$latestUsers): ?><div class="empty">Belum ada aktivitas pelanggan.</div><?php else: foreach (array_slice($latestUsers,0,4) as $latest): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-user-plus"></i></span><div><strong>Akun pelanggan terdaftar</strong><p><?= e($latest['name']) ?> · <?= e($latest['email']) ?></p></div></div><?php endforeach; endif; ?>
                </div></div></article>
            </section>

        <?php elseif ($page === 'orders'): ?>
            <section class="welcome-row"><div><span class="eyebrow">BALLA’ FRESH · TRANSAKSI</span><h1>Pengelolaan Pesanan</h1><p>Kelola pesanan, informasi pelanggan, rincian layanan, dan progres pengerjaan.</p></div><span class="welcome-tag"><span></span> Data dari database pesanan</span></section>
            <section class="panel" id="orders" style="margin-bottom:20px">
                <div class="panel-head">
                    <div>
                        <h2>Pengelolaan Pesanan</h2>
                        <p>Pantau pesanan layanan dan perbarui progres pengerjaan pelanggan.</p>
                    </div>
                    <span class="welcome-tag"><span></span> Data pesanan terbaru</span>
                </div>

                <div class="order-summary-grid">
                    <div class="order-mini-stat"><span>Total Pesanan</span><strong><?= number_format($orderCounts['all'], 0, ',', '.') ?></strong></div>
                    <div class="order-mini-stat"><span>Menunggu</span><strong><?= number_format($orderCounts['pending'], 0, ',', '.') ?></strong></div>
                    <div class="order-mini-stat"><span>Diproses</span><strong><?= number_format($orderCounts['processing'], 0, ',', '.') ?></strong></div>
                    <div class="order-mini-stat"><span>Selesai</span><strong><?= number_format($orderCounts['completed'], 0, ',', '.') ?></strong></div>
                </div>

                <?php if ($orderActionMessage !== ''): ?>
                    <div class="order-notice <?= e($orderActionType) ?>"><?= e($orderActionMessage) ?></div>
                <?php endif; ?>
                <?php if ($orderLoadError !== ''): ?>
                    <div class="notice"><?= e($orderLoadError) ?></div>
                <?php endif; ?>

                <div class="panel-body">
                    <div class="order-tools" style="justify-content:space-between;margin-bottom:14px">
                        <span class="help" style="color:var(--muted);font-size:11px">Menampilkan maksimal 100 pesanan terbaru.</span>
                        <select class="order-filter" id="orderStatusFilter" aria-label="Filter status pesanan">
                            <option value="all">Semua status</option>
                            <option value="pending">Menunggu</option>
                            <option value="processing">Diproses</option>
                            <option value="completed">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>

                    <div class="table-wrap">
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Nomor Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Layanan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Ubah Status</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                            <?php if (!$orders): ?>
                                <tr><td colspan="6">
                                    <div class="empty">
                                        <i class="fa-solid fa-bag-shopping"></i>
                                        Belum ada pesanan yang masuk.
                                    </div>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                        $status = (string) $order['status'];
                                        $serviceList = $order['ordered_services'] ?? '';
                                        $serviceNames = $serviceList !== '' ? explode('||', $serviceList) : [];
                                        $createdLabel = !empty($order['created_at'])
                                            ? date('d M Y, H:i', strtotime((string) $order['created_at']))
                                            : '-';
                                    ?>
                                    <tr data-order-status="<?= e($status) ?>">
                                        <td>
                                            <span class="order-number"><?= e($order['order_number']) ?></span>
                                            <span class="order-sub"><?= e($createdLabel) ?> · #<?= (int) $order['id'] ?></span>
                                        </td>
                                        <td>
                                            <strong><?= e($order['customer_name']) ?></strong>
                                            <span class="order-sub"><?= e($order['customer_email']) ?></span>
                                            <span class="order-sub"><?= e($order['customer_phone']) ?></span>
                                            <div class="order-detail"><?= e($order['customer_address']) ?></div>
                                        </td>
                                        <td class="order-services">
                                            <?php if ($serviceNames): ?>
                                                <?php foreach ($serviceNames as $serviceName): ?>
                                                    <div><?= e($serviceName) ?></div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="order-sub">Rincian layanan tidak tersedia</span>
                                            <?php endif; ?>
                                            <?php if (!empty($order['notes'])): ?>
                                                <div class="order-detail"><strong>Catatan:</strong> <?= e($order['notes']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="order-total">Rp<?= number_format((float) $order['total_amount'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="status-pill <?= e(orderStatusClass($status)) ?>">
                                                <i class="fa-solid fa-circle"></i><?= e(orderStatusLabel($status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" action="admin_dashboard.php?page=orders#orders" class="status-form">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                <select name="status" aria-label="Status baru untuk <?= e($order['order_number']) ?>">
                                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                                    <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Diproses</option>
                                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
                                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                                </select>
                                                <button type="submit">Simpan</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        <?php elseif ($page === 'customers'): ?>
            <section class="welcome-row"><div><span class="eyebrow">BALLA’ FRESH · CUSTOMER DATABASE</span><h1>Data Pelanggan</h1><p>Informasi akun pelanggan yang terdaftar di website, termasuk kontak dan alamat yang tersedia.</p></div><a class="btn" href="admin_dashboard.php?page=customers&amp;export=customers&amp;q=<?= rawurlencode($search) ?>"><i class="fa-solid fa-file-arrow-down"></i> Unduh CSV / Excel</a></section>
            <section class="stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))"><article class="stat-card"><div class="stat-kicker">Total akun pelanggan</div><div class="stat-value"><?= number_format($customerCount,0,',','.') ?></div><div class="stat-foot">Data dari tabel users</div></article><article class="stat-card"><div class="stat-kicker">Pelanggan dengan alamat</div><div class="stat-value"><?= number_format($addressCount,0,',','.') ?></div><div class="stat-foot">Data dari tabel user_addresses jika tersedia</div></article></section>
            <article class="panel">
                <div class="panel-head"><div><h2>Database Pelanggan</h2><p>Menampilkan seluruh data yang tersedia di database.</p></div>
                    <form class="search-form" method="get" action="admin_dashboard.php"><input type="hidden" name="page" value="customers"><label class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama, email, atau nomor HP..."></label><button class="btn" type="submit">Cari</button></form>
                </div>
                <?php if ($searchError !== ''): ?><div class="notice"><?= e($searchError) ?></div><?php endif; ?>
                <div class="table-wrap"><table style="min-width:900px"><thead><tr><th>Nama Lengkap</th><th>Email</th><th>Nomor HP</th><th>Alamat</th><th>Tanggal Daftar</th><th>ID</th></tr></thead><tbody>
                <?php if (!$customers): ?><tr><td colspan="6"><div class="empty"><i class="fa-solid fa-user-group"></i><?= $search !== '' ? 'Tidak ada pelanggan yang cocok.' : 'Belum ada data pelanggan.' ?></div></td></tr>
                <?php else: foreach ($customers as $customer):
                    $customerAddress = trim(implode(', ', array_filter([$customer['address_details'] ?? '', $customer['street'] ?? '', $customer['address'] ?? '', $customer['rt_rw'] ?? '', $customer['subdistrict'] ?? ($customer['district'] ?? ''), $customer['city'] ?? '', $customer['province'] ?? '', $customer['postal_code'] ?? ''])));
                ?><tr><td><div class="customer-cell"><span class="customer-avatar"><?= e(strtoupper(substr((string)$customer['name'],0,1))) ?></span><span><strong><?= e($customer['name']) ?></strong><small>Pelanggan terdaftar</small></span></div></td><td><?= e($customer['email']) ?></td><td><?= e($customer['phone'] ?? '') ?: 'Belum tersedia di database' ?></td><td class="muted-cell"><?= e($customerAddress) ?: 'Alamat belum tersedia' ?></td><td><?= !empty($customer['registered_at']) ? e(date('d M Y',strtotime((string)$customer['registered_at']))) : '—' ?></td><td>#<?= (int)$customer['id'] ?></td></tr><?php endforeach; endif; ?>
                </tbody></table></div>
                <div class="panel-body"><p style="margin:0;color:var(--muted);font-size:11px">Data ditampilkan langsung dari tabel users dan user_addresses. Unduhan mengikuti kata pencarian yang sedang aktif. File CSV berformat UTF-8 dan dapat dibuka di Excel atau diimpor ke Google Sheets.</p></div>
            </article>

        <?php elseif ($page === 'activity'): ?>
            <section class="welcome-row"><div><span class="eyebrow">BALLA’ FRESH · MONITORING</span><h1>Aktivitas Terbaru</h1><p>Pantau aktivitas akun dan pesanan terbaru yang tercatat di database.</p></div></section>
            <section class="panel-grid">
                <article class="panel"><div class="panel-head"><div><h2>Registrasi Pelanggan Terbaru</h2><p>5 akun terakhir yang tersimpan.</p></div></div><div class="panel-body"><div class="activity-list"><?php if (!$latestUsers): ?><div class="empty">Belum ada registrasi.</div><?php else: foreach ($latestUsers as $latest): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-user-plus"></i></span><div><strong>Pelanggan baru terdaftar</strong><p><?= e($latest['name']) ?> · <?= e($latest['email']) ?></p></div><span class="activity-id">#<?= (int)$latest['id'] ?></span></div><?php endforeach; endif; ?></div></div></article>
                <article class="panel"><div class="panel-head"><div><h2>Pesanan Terbaru</h2><p>Aktivitas transaksi terbaru.</p></div></div><div class="panel-body"><div class="activity-list"><?php if (!$orders): ?><div class="empty">Belum ada pesanan.</div><?php else: foreach (array_slice($orders,0,8) as $order): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-cart-shopping"></i></span><div><strong>Pesanan <?= e($order['order_number']) ?></strong><p><?= e($order['customer_name']) ?> · Rp<?= number_format((float)$order['total_amount'],0,',','.') ?> · <?= e(orderStatusLabel((string)$order['status'])) ?></p></div></div><?php endforeach; endif; ?></div></div></article>
            </section>

        <?php elseif ($page === 'agency'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · WEBSITE CMS</span>
                    <h1>Katalog Layanan & Konten Website</h1>
                    <p>Kelola katalog layanan, harga, ulasan mitra, dan konten website langsung dari dashboard. Semua data terhubung langsung ke database utama dan halaman publik.</p>
                </div>
                <span class="welcome-tag"><span></span> Terhubung ke Database Publik</span>
            </section>

            <?php if ($agencyMessage !== ''): ?>
                <div class="order-notice <?= e($agencyMessageType) ?>"><?= e($agencyMessage) ?></div>
            <?php endif; ?>

            <!-- DAFTAR LAYANAN PUBLIK -->
            <article class="panel" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Daftar Layanan Aktif di Website</h2>
                        <p>Layanan yang tampil pada katalog dan halaman detail layanan publik.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table style="min-width:820px">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Layanan &amp; Kategori</th>
                                <th>Harga</th>
                                <th>Estimasi</th>
                                <th>Ulasan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$allServices): ?>
                                <tr><td colspan="7"><div class="empty">Belum ada layanan di database. Tambahkan melalui formulir di bawah.</div></td></tr>
                            <?php else: foreach ($allServices as $srv): ?>
                                <tr>
                                    <td style="width:70px">
                                        <div style="width:56px;height:56px;border-radius:12px;overflow:hidden;background:#f1f5f9;border:1px solid var(--line)">
                                            <img src="<?= e($srv['image'] ?: 'https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=200&q=80') ?>"
                                                 alt="<?= e($srv['name']) ?>" style="width:100%;height:100%;object-fit:cover"
                                                 onerror="this.src='logo-balla-fresh.png'">
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= e($srv['name']) ?></strong>
                                        <div style="font-size:11px;color:var(--green);font-weight:700"><?= e($srv['category_name'] ?: 'Layanan') ?></div>
                                        <small style="color:var(--muted);display:block;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= e($srv['description'] ?: 'Tidak ada deskripsi') ?>
                                        </small>
                                    </td>
                                    <td style="font-weight:800;color:#e48e1e;white-space:nowrap">
                                        Rp<?= number_format((float)$srv['price'], 0, ',', '.') ?>
                                    </td>
                                    <td><?= (int)$srv['estimated_days'] ?> hari</td>
                                    <td>
                                        <span style="font-size:12px;color:var(--muted)">
                                            <i class="fa-solid fa-star" style="color:#f59e0b"></i> <?= (int)$srv['review_count'] ?> ulasan
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)$srv['is_available'] === 1): ?>
                                            <span class="status-pill status-completed"><i class="fa-solid fa-circle"></i> Tayang</span>
                                        <?php else: ?>
                                            <span class="status-pill status-cancelled"><i class="fa-solid fa-circle"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <a class="btn btn-light" href="detail_layanan.php?id=<?= (int)$srv['id'] ?>" target="_blank" title="Lihat Halaman Publik">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Web
                                            </a>
                                            <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan ini dari database?')">
                                                <input type="hidden" name="agency_action" value="delete_service">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
                                                <button class="btn btn-light" type="submit" style="color:#dc2626"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <!-- FORM EDIT CEPAT LAYANAN -->
                                <tr>
                                    <td colspan="7" style="background:#fafbf8;padding:12px 16px;border-bottom:2px solid var(--line)">
                                        <details style="font-size:12px">
                                            <summary style="cursor:pointer;font-weight:700;color:var(--green)">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit Data Layanan #<?= (int)$srv['id'] ?> (<?= e($srv['name']) ?>)
                                            </summary>
                                            <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px">
                                                <input type="hidden" name="agency_action" value="save_service">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
                                                
                                                <label style="display:grid;gap:4px">Kategori
                                                    <select class="order-filter" name="category_id">
                                                        <?php foreach ($categoriesList as $cat): ?>
                                                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$srv['category_id'] ? 'selected' : '' ?>>
                                                                <?= e($cat['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>

                                                <label style="display:grid;gap:4px">Nama Layanan
                                                    <input class="order-filter" name="service_name" value="<?= e($srv['name']) ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">Harga (Rp)
                                                    <input class="order-filter" type="number" min="0" step="1000" name="service_price" value="<?= e($srv['price']) ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">Estimasi Hari
                                                    <input class="order-filter" type="number" min="1" max="60" name="estimated_days" value="<?= (int)$srv['estimated_days'] ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">URL Gambar
                                                    <input class="order-filter" name="service_image" value="<?= e($srv['image']) ?>" placeholder="https://... atau path gambar">
                                                </label>

                                                <label style="display:grid;gap:4px">Atau Unggah Gambar Baru
                                                    <input class="order-filter" type="file" name="service_image_file" accept="image/jpeg,image/png,image/webp">
                                                </label>

                                                <label style="grid-column:1 / -1;display:grid;gap:4px">Fitur Deliverables (Pisahkan dengan tanda pipa |)
                                                    <input class="order-filter" name="service_features" value="<?= e($srv['features']) ?>" placeholder="Contoh: Konsep visual | Logo utama & variasi | File siap pakai">
                                                </label>

                                                <label style="grid-column:1 / -1;display:grid;gap:4px">Deskripsi Lengkap
                                                    <textarea class="order-filter" name="service_description" rows="2"><?= e($srv['description']) ?></textarea>
                                                </label>

                                                <div style="grid-column:1 / -1;display:flex;align-items:center;justify-content:space-between">
                                                    <label style="font-size:12px;font-weight:700">
                                                        <input type="checkbox" name="is_available" <?= (int)$srv['is_available'] === 1 ? 'checked' : '' ?>> Tampilkan di Katalog Website
                                                    </label>
                                                    <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <!-- FORM TAMBAH LAYANAN BARU -->
            <article class="panel" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Tambah Layanan Baru</h2>
                        <p>Tambahkan paket atau layanan baru ke katalog publik Balla’ Fresh.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" enctype="multipart/form-data" style="display:grid;gap:14px">
                        <input type="hidden" name="agency_action" value="save_service">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                        <input type="hidden" name="service_id" value="0">

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Kategori Layanan
                                <select class="order-filter" name="category_id">
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Nama Layanan
                                <input class="order-filter" name="service_name" placeholder="Contoh: Paket Desain Kemasan Botol" required>
                            </label>
                        </div>

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Harga (Rp)
                                <input class="order-filter" type="number" min="0" step="1000" name="service_price" placeholder="450000" required>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Estimasi Waktu Pengerjaan (Hari)
                                <input class="order-filter" type="number" min="1" max="60" name="estimated_days" value="3" required>
                            </label>
                        </div>

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">URL Gambar (Opsional)
                                <input class="order-filter" name="service_image" placeholder="https://... atau path gambar lokal">
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Atau Unggah File Gambar (JPG/PNG/WEBP, maks 4 MB)
                                <input class="order-filter" type="file" name="service_image_file" accept="image/jpeg,image/png,image/webp">
                            </label>
                        </div>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Fitur / Deliverables (Gunakan tanda | sebagai pemisah poin)
                            <input class="order-filter" name="service_features" placeholder="Contoh: 3 Konsep Desain | File Master AI/PSD | Mockup 3D | Revisi Bebas">
                        </label>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Deskripsi Lengkap Layanan
                            <textarea class="order-filter" name="service_description" rows="3" placeholder="Jelaskan manfaat dan detail pengerjaan layanan ini untuk mitra hidroponik..."></textarea>
                        </label>

                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
                            <label style="font-size:13px;font-weight:700">
                                <input type="checkbox" name="is_available" checked> Tampilkan langsung di katalog publik website
                            </label>
                            <button class="btn" type="submit"><i class="fa-solid fa-plus"></i> Tambah Layanan ke Website</button>
                        </div>
                    </form>
                </div>
            </article>

            <!-- KELOLA ULASAN & RATING MITRA -->
            <article class="panel" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Ulasan &amp; Rating Mitra Pelanggan</h2>
                        <p>Moderasi ulasan yang diberikan oleh mitra bisnis pada halaman detail layanan.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table style="min-width:780px">
                        <thead>
                            <tr>
                                <th>Mitra &amp; Usaha</th>
                                <th>Layanan</th>
                                <th>Rating</th>
                                <th>Isi Ulasan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$allReviews): ?>
                                <tr><td colspan="7"><div class="empty">Belum ada ulasan yang masuk dari mitra.</div></td></tr>
                            <?php else: foreach ($allReviews as $rev): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($rev['user_name']) ?></strong>
                                        <small style="display:block;color:var(--muted)"><?= e($rev['business_name'] ?: 'Mitra Hidroponik') ?></small>
                                    </td>
                                    <td>
                                        <span style="font-size:12px;font-weight:700;color:var(--green)">
                                            <?= e($rev['service_name'] ?: 'Layanan #' . $rev['service_id']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color:#f59e0b;font-weight:800;white-space:nowrap">
                                            <?= str_repeat('★', (int)$rev['rating']) ?> (<?= (int)$rev['rating'] ?>)
                                        </span>
                                    </td>
                                    <td>
                                        <p style="font-size:12px;margin:0;max-width:280px;line-height:1.5">
                                            "<?= e($rev['review_text']) ?>"
                                        </p>
                                    </td>
                                    <td style="font-size:11px;color:var(--muted);white-space:nowrap">
                                        <?= date('d M Y', strtotime((string)$rev['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$rev['is_approved'] === 1): ?>
                                            <span class="status-pill status-completed"><i class="fa-solid fa-circle"></i> Tampil</span>
                                        <?php else: ?>
                                            <span class="status-pill status-cancelled"><i class="fa-solid fa-circle"></i> Disembunyikan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <form method="post">
                                                <input type="hidden" name="agency_action" value="toggle_review">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <button class="btn btn-light" type="submit" title="Ubah status tampil">
                                                    <?= (int)$rev['is_approved'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?>
                                                </button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Hapus ulasan ini?')">
                                                <input type="hidden" name="agency_action" value="delete_review">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <button class="btn btn-light" type="submit" style="color:#dc2626"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <!-- KONTEN HERO & WEBSITE -->
            <article class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Konten Informasi Website</h2>
                        <p>Ubah judul hero, deskripsi, dan kontak narahubung website.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" enctype="multipart/form-data" style="display:grid;gap:14px">
                        <input type="hidden" name="agency_action" value="save_content">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">

                        <label style="display:grid;gap:7px;font-size:12px;font-weight:700">Judul utama website
                            <input class="order-filter" type="text" name="hero_title" value="<?= e($siteContent['hero_title']) ?>" required>
                        </label>

                        <label style="display:grid;gap:7px;font-size:12px;font-weight:700">Deskripsi utama
                            <textarea class="order-filter" name="hero_description" rows="3"><?= e($siteContent['hero_description']) ?></textarea>
                        </label>

                        <div class="ops-grid">
                            <label style="display:grid;gap:7px;font-size:12px;font-weight:700">Email kontak
                                <input class="order-filter" type="email" name="contact_email" value="<?= e($siteContent['contact_email']) ?>">
                            </label>
                            <label style="display:grid;gap:7px;font-size:12px;font-weight:700">Nomor kontak WhatsApp
                                <input class="order-filter" type="text" name="contact_phone" value="<?= e($siteContent['contact_phone']) ?>">
                            </label>
                        </div>

                        <label style="display:grid;gap:7px;font-size:12px;font-weight:700">Gambar hero (JPG/PNG/WEBP, maks. 4 MB)
                            <input class="order-filter" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
                        </label>
                        <?php if (!empty($siteContent['hero_image'])): ?>
                            <img src="<?= e($siteContent['hero_image']) ?>" alt="Gambar hero saat ini" style="max-width:220px;border-radius:12px">
                        <?php endif; ?>

                        <div>
                            <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Konten Website</button>
                        </div>
                    </form>
                </div>
            </article>
        <?php endif; ?>
        </div>
    </main>
</div>
<!-- POPUP KONFIRMASI LOGOUT ADMIN -->
<div
    class="logout-modal"
    id="logoutModal"
    aria-hidden="true"
>
    <div
        class="logout-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutTitle"
        aria-describedby="logoutDescription"
    >

        <div class="logout-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>

        <h2 id="logoutTitle">Yakin ingin keluar?</h2>

        <p id="logoutDescription">
            Apakah kamu yakin ingin mengakhiri sesi administrator
            Balla’ Fresh? Kamu perlu login kembali untuk mengakses
            dashboard admin.
        </p>

        <div class="logout-actions">

            <button
                type="button"
                class="logout-cancel"
                id="cancelLogout"
            >
                Tetap di Dashboard
            </button>

            <form action="admin_logout.php" method="POST">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['admin_logout_csrf']) ?>"
                >

                <button
                    type="submit"
                    class="logout-confirm"
                >
                    Ya, Keluar
                </button>
            </form>

        </div>
    </div>
</div>

<script>
const orderStatusFilter = document.getElementById('orderStatusFilter');
if (orderStatusFilter) {
    orderStatusFilter.addEventListener('change', function () {
        const chosen = this.value;
        document.querySelectorAll('#ordersTableBody tr[data-order-status]').forEach(row => {
            row.style.display = chosen === 'all' || row.dataset.orderStatus === chosen ? '' : 'none';
        });
    });
}

const todayLabel = document.getElementById('todayLabel');
if (todayLabel) {
    todayLabel.textContent = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    }).format(new Date());
}

const sidebar = document.getElementById('sidebar');
const mobileMenu = document.getElementById('mobileMenu');
const mobileOverlay = document.getElementById('mobileOverlay');

function closeMobileMenu() {
    sidebar?.classList.remove('open');
    mobileOverlay?.classList.remove('show');
}
mobileMenu?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    mobileOverlay?.classList.toggle('show');
});
mobileOverlay?.addEventListener('click', closeMobileMenu);
document.querySelectorAll('.side-link').forEach(link => {
    link.addEventListener('click', () => {
        document.querySelectorAll('.side-link').forEach(item => item.classList.remove('active'));
        link.classList.add('active');
        closeMobileMenu();
    });
});
// ===== POPUP LOGOUT ADMIN =====

const logoutButton = document.getElementById('logoutButton');
const logoutModal = document.getElementById('logoutModal');
const cancelLogout = document.getElementById('cancelLogout');

function openLogoutModal() {
    logoutModal.classList.add('show');
    logoutModal.setAttribute('aria-hidden', 'false');
    cancelLogout.focus();
}

function closeLogoutModal() {
    logoutModal.classList.remove('show');
    logoutModal.setAttribute('aria-hidden', 'true');
    logoutButton.focus();
}

// Klik tombol logout.
logoutButton.addEventListener('click', openLogoutModal);

// Klik tetap di dashboard.
cancelLogout.addEventListener('click', closeLogoutModal);

// Klik area luar popup.
logoutModal.addEventListener('click', function(event) {
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});

// Tekan Escape untuk menutup popup.
document.addEventListener('keydown', function(event) {
    if (
        event.key === 'Escape' &&
        logoutModal.classList.contains('show')
    ) {
        closeLogoutModal();
    }
});
</script>
</body>
</html>
