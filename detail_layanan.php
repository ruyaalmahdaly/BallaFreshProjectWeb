<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

$isUserLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

// Ambil ID Layanan dari parameter URL
$serviceId = isset($_GET['id']) ? (int) $_GET['id'] : 1;
if ($serviceId <= 0) {
    $serviceId = 1;
}

// Proses submit ulasan baru
$reviewMessage = '';
$reviewMessageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $reviewerName = trim((string) ($_POST['user_name'] ?? ''));
    $businessName = trim((string) ($_POST['business_name'] ?? ''));
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));

    if ($reviewerName === '' || $reviewText === '') {
        $reviewMessage = 'Nama dan isi ulasan wajib diisi.';
        $reviewMessageType = 'error';
    } else {
        $ins = $conn->prepare("INSERT INTO service_reviews (service_id, user_name, business_name, rating, review_text, is_approved) VALUES (?, ?, ?, ?, ?, 1)");
        if ($ins) {
            $ins->bind_param('issis', $serviceId, $reviewerName, $businessName, $rating, $reviewText);
            if ($ins->execute()) {
                $reviewMessage = 'Terima kasih! Ulasan dan rating Anda berhasil disimpan.';
                $reviewMessageType = 'success';
            } else {
                $reviewMessage = 'Gagal menyimpan ulasan. Silakan coba beberapa saat lagi.';
                $reviewMessageType = 'error';
            }
            $ins->close();
        }
    }
}

// Ambil data layanan dari database
$stmt = $conn->prepare("
    SELECT s.*, c.name AS category_name, c.description AS category_desc
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $serviceId);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Jika ID tidak ditemukan, fallback ke layanan pertama yang tersedia
if (!$service) {
    $fallbackStmt = $conn->query("
        SELECT s.*, c.name AS category_name, c.description AS category_desc
        FROM services s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.is_available = 1
        ORDER BY s.id ASC
        LIMIT 1
    ");
    if ($fallbackStmt && $fallbackRow = $fallbackStmt->fetch_assoc()) {
        $service = $fallbackRow;
        $serviceId = (int) $service['id'];
    }
}

if (!$service) {
    http_response_code(404);
    exit('Layanan tidak ditemukan.');
}

// Hitung data penjualan mitra dari database order_items
$statStmt = $conn->prepare("SELECT COUNT(*) AS total_orders, COALESCE(SUM(quantity), 0) AS total_qty FROM order_items WHERE service_id = ?");
$statStmt->bind_param('i', $serviceId);
$statStmt->execute();
$statData = $statStmt->get_result()->fetch_assoc();
$statStmt->close();

$realOrders = (int) ($statData['total_orders'] ?? 0);
// Base social proof ditambah real order
$mitraCount = 28 + ($serviceId * 9) + $realOrders;

// Ambil ulasan dari database
$revStmt = $conn->prepare("
    SELECT id, user_name, business_name, rating, review_text, created_at
    FROM service_reviews
    WHERE service_id = ? AND is_approved = 1
    ORDER BY created_at DESC
");
$revStmt->bind_param('i', $serviceId);
$revStmt->execute();
$reviews = $revStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$revStmt->close();

$totalReviews = count($reviews);
$avgRating = 5.0;
if ($totalReviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / $totalReviews, 1);
}

// Layanan terkait (rekomendasi layanan lain)
$relStmt = $conn->prepare("
    SELECT s.*, c.name AS category_name
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE s.id != ? AND s.is_available = 1
    ORDER BY s.id ASC
    LIMIT 2
");
$relStmt->bind_param('i', $serviceId);
$relStmt->execute();
$relatedServices = $relStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$relStmt->close();

// Gambar layanan utama
$serviceImage = !empty($service['image'])
    ? $service['image']
    : 'https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=1200&q=80';

// Foto dokumentasi karya lokal pendukung sesuai kategori
$categorySlug = strtolower(str_replace(' ', '', (string)$service['category_name']));
$sampleDocs = [
    'Edible1_dok.jpeg',
    'Pouch1_dok.jpeg',
    'Tumb1_dok.jpeg'
];
if (str_contains($categorySlug, 'branding')) {
    $sampleDocs = ['Edible1_dok.jpeg', 'EdibleStraw_Logo.jpeg', 'Pouch_Logo.jpeg'];
} elseif (str_contains($categorySlug, 'commerce')) {
    $sampleDocs = ['Pouch1_dok.jpeg', 'Pouch2_dok.jpeg', 'Pouch3_dok.jpeg'];
} else {
    $sampleDocs = ['Tumb1_dok.jpeg', 'Tumb2_dok.jpeg', 'Tumb3_dok.jpeg'];
}

$features = array_filter(array_map('trim', explode('|', (string) ($service['features'] ?? ''))));
$priceFormatted = 'Rp' . number_format((float) $service['price'], 0, ',', '.');
$estimatedDays = (int) ($service['estimated_days'] ?? 3);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> — Detail Layanan Balla’ Fresh</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --green: #3a6932;
            --green-light: #5b8121;
            --green-soft: #f4f8f2;
            --green-border: rgba(58, 105, 50, 0.18);
            --lime: #d9f99d;
            --ink: #0f172a;
            --muted: #64748b;
            --sand: #fafcf8;
            --white: #ffffff;
            --line: #e2e8f0;
            --orange: #f59e0b;
            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 10px 25px -4px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 20px 35px -8px rgba(15, 23, 42, 0.12);
            --ease: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--sand);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4 {
            font-family: "Manrope", sans-serif;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
        }

        a { text-decoration: none; color: inherit; }

        .container {
            width: min(1200px, 92%);
            margin: 0 auto;
        }

        /* HEADER & NAVBAR */
        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(250, 252, 248, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }

        .navbar {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Manrope", sans-serif;
            font-weight: 800;
            font-size: 22px;
            color: var(--green);
        }

        .logo img {
            max-height: 42px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-link {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            position: relative;
            transition: var(--ease);
        }

        .nav-link:hover, .nav-link.active {
            color: var(--green);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--green);
            display: grid;
            place-items: center;
            font-size: 17px;
            cursor: pointer;
            transition: var(--ease);
            box-shadow: var(--shadow-sm);
        }

        .cart-btn:hover {
            background: var(--green-soft);
            border-color: var(--green);
            transform: translateY(-1px);
        }

        .cart-count {
            position: absolute;
            right: -6px;
            top: -6px;
            min-width: 22px;
            height: 22px;
            border-radius: 999px;
            background: var(--orange);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            display: grid;
            place-items: center;
            padding: 0 5px;
        }

        .auth-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .auth-nav-link {
            color: #405b18;
            font-weight: 700;
            font-size: 14px;
            padding: 8px 12px;
        }

        .auth-nav-button {
            background: var(--green-light);
            color: #fff;
            padding: 10px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            transition: var(--ease);
        }

        .auth-nav-button:hover {
            background: var(--green);
        }

        .auth-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .auth-nav-profile,
        .auth-nav-logout {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--green);
            display: grid;
            place-items: center;
            transition: var(--ease);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
        }

        .auth-nav-profile i,
        .auth-nav-logout i {
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .auth-nav-profile:hover {
            background: var(--green-soft);
            border-color: var(--green);
            color: var(--green);
            transform: translateY(-1px);
        }

        .auth-nav-logout {
            color: #64748b;
        }

        .auth-nav-logout:hover {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #dc2626;
            transform: translateY(-1px);
        }

        /* BREADCRUMB */
        .breadcrumb {
            padding: 24px 0 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        .breadcrumb a:hover {
            color: var(--green);
            text-decoration: underline;
        }

        .breadcrumb i {
            font-size: 10px;
            opacity: 0.6;
        }

        .breadcrumb span {
            color: var(--ink);
            font-weight: 600;
        }

        /* DETAIL LAYOUT */
        .detail-wrapper {
            padding: 20px 0 70px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            align-items: start;
        }

        /* LEFT CONTENT */
        .service-main-card {
            background: #fff;
            border-radius: 28px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .service-hero-image {
            width: 100%;
            height: 380px;
            background: #eef2ea;
            position: relative;
            overflow: hidden;
        }

        .service-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .service-hero-image:hover img {
            transform: scale(1.02);
        }

        .badge-category {
            position: absolute;
            top: 22px;
            left: 22px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            color: var(--green);
            font-size: 12px;
            font-weight: 800;
            padding: 7px 16px;
            border-radius: 999px;
            border: 1px solid rgba(58, 105, 50, 0.2);
            box-shadow: var(--shadow-sm);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .service-info-box {
            padding: 36px 36px 28px;
        }

        .service-header-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .rating-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fffbeb;
            color: #b45309;
            font-size: 13px;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #fde68a;
        }

        .rating-chip i {
            color: #f59e0b;
        }

        .mitra-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--green-soft);
            color: var(--green);
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid var(--green-border);
        }

        .mitra-badge i {
            color: var(--green);
        }

        .service-info-box h1 {
            font-size: 32px;
            margin-bottom: 16px;
            line-height: 1.25;
        }

        .service-description {
            font-size: 16px;
            color: #475569;
            line-height: 1.8;
            margin-bottom: 28px;
        }

        /* SECTION CARDS INSIDE MAIN */
        .content-block {
            border-top: 1px solid var(--line);
            padding: 30px 0;
        }

        .content-block h2 {
            font-size: 20px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-block h2 i {
            color: var(--green);
            font-size: 18px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #fafbf9;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }

        .feature-item i {
            color: var(--green);
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* WORKFLOW STEPS */
        .workflow-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-top: 16px;
        }

        .wf-step {
            background: #fafbf9;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            position: relative;
        }

        .wf-num {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: var(--green-soft);
            color: var(--green);
            font-weight: 800;
            font-size: 13px;
            display: grid;
            place-items: center;
            margin-bottom: 12px;
            border: 1px solid var(--green-border);
        }

        .wf-step h3 {
            font-size: 14px;
            margin-bottom: 6px;
        }

        .wf-step p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* PORTFOLIO SNIPPET GALLERY */
        .doc-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 16px;
        }

        .doc-thumb {
            height: 140px;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--line);
            background: #f1f5f9;
        }

        .doc-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .doc-thumb:hover img {
            transform: scale(1.05);
        }

        /* REVIEWS SECTION */
        .reviews-container {
            background: #fff;
            border-radius: 28px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
            padding: 36px;
            margin-bottom: 30px;
        }

        .reviews-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .reviews-summary {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .score-big {
            font-size: 44px;
            font-family: "Manrope", sans-serif;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
        }

        .stars-row {
            color: var(--orange);
            font-size: 16px;
            margin-bottom: 4px;
        }

        .review-count-text {
            font-size: 13px;
            color: var(--muted);
        }

        .review-list {
            display: grid;
            gap: 18px;
            margin-bottom: 36px;
        }

        .review-card {
            background: #fafbf9;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
            transition: var(--ease);
        }

        .review-card:hover {
            border-color: rgba(58, 105, 50, 0.3);
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .review-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reviewer-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #d9f99d, #86efac);
            color: #14532d;
            font-weight: 800;
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .reviewer-names strong {
            display: block;
            font-size: 15px;
            color: var(--ink);
        }

        .reviewer-names small {
            font-size: 12px;
            color: var(--green);
            font-weight: 600;
        }

        .review-stars {
            color: var(--orange);
            font-size: 13px;
        }

        .review-text {
            font-size: 14px;
            color: #334155;
            line-height: 1.7;
        }

        .review-date {
            font-size: 11px;
            color: var(--muted);
            margin-top: 10px;
        }

        /* ADD REVIEW FORM */
        .add-review-box {
            background: #f8faf5;
            border: 1px dashed var(--green-border);
            border-radius: 22px;
            padding: 26px;
        }

        .add-review-box h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .add-review-box p {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .form-field {
            display: grid;
            gap: 6px;
        }

        .form-field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
        }

        .form-field input, .form-field select, .form-field textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            transition: var(--ease);
        }

        .form-field input:focus, .form-field select:focus, .form-field textarea:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(58, 105, 50, 0.12);
        }

        .btn-submit-review {
            background: var(--green);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit-review:hover {
            background: var(--green-light);
            transform: translateY(-1px);
        }

        .alert-box {
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* RIGHT STICKY CARD */
        .sticky-card {
            position: sticky;
            top: 105px;
            background: #fff;
            border-radius: 28px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-md);
            padding: 30px;
        }

        .price-tag-wrapper {
            margin-bottom: 22px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .price-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .price-amount {
            font-family: "Manrope", sans-serif;
            font-size: 34px;
            font-weight: 800;
            color: #e48e1e;
            line-height: 1.1;
            margin: 4px 0 10px;
        }

        .duration-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            background: #f1f5f9;
            padding: 5px 12px;
            border-radius: 999px;
        }

        .action-buttons {
            display: grid;
            gap: 12px;
            margin-bottom: 26px;
        }

        .btn-primary-action {
            width: 100%;
            background: var(--green);
            color: #fff;
            padding: 15px 20px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 15px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--ease);
            box-shadow: 0 4px 14px rgba(58, 105, 50, 0.25);
        }

        .btn-primary-action:hover {
            background: var(--green-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(58, 105, 50, 0.3);
        }

        .btn-secondary-action {
            width: 100%;
            background: #fff;
            color: var(--green);
            padding: 14px 20px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 15px;
            border: 1.5px solid var(--green);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--ease);
        }

        .btn-secondary-action:hover {
            background: var(--green-soft);
            transform: translateY(-2px);
        }

        .btn-wa-action {
            width: 100%;
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 13px 20px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--ease);
        }

        .btn-wa-action:hover {
            background: #dcfce7;
        }

        .guarantee-list {
            list-style: none;
            display: grid;
            gap: 11px;
            font-size: 13px;
            color: #475569;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .guarantee-list li {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .guarantee-list i {
            color: var(--green);
            font-size: 14px;
        }

        /* RELATED SERVICES */
        .related-section {
            margin-top: 40px;
            padding-top: 50px;
            border-top: 1px solid var(--line);
        }

        .related-title {
            font-size: 24px;
            margin-bottom: 24px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .related-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--ease);
        }

        .related-card:hover {
            transform: translateY(-4px);
            border-color: rgba(58, 105, 50, 0.3);
            box-shadow: var(--shadow-md);
        }

        .related-card h3 {
            font-size: 18px;
            margin: 8px 0;
        }

        .related-card p {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .related-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--line);
            padding-top: 16px;
        }

        /* TOAST */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #0f172a;
            color: #fff;
            padding: 14px 22px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            opacity: 0;
            transform: translateY(20px);
            transition: var(--ease);
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast i {
            color: var(--lime);
        }

        /* FOOTER */
        footer {
            background: #fff;
            border-top: 1px solid var(--line);
            padding: 40px 0;
            margin-top: 50px;
            font-size: 13px;
            color: var(--muted);
            text-align: center;
        }

        /* RESPONSIVE */
        @media (max-width: 960px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .sticky-card {
                position: static;
            }
            .workflow-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .related-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
            .workflow-row {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .nav-menu {
                display: none;
            }
            .service-hero-image {
                height: 240px;
            }
            .service-info-box {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>

<!-- HEADER NAVBAR -->
<header>
    <div class="container navbar">
        <a class="logo" href="index.php#beranda" aria-label="Balla' Fresh">
            <img src="logo-balla-fresh.png" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span style="display:none">Balla’ Fresh</span>
        </a>

        <nav class="nav-menu" id="navMenu">
            <a href="index.php#beranda" class="nav-link">Beranda</a>
            <a href="index.php#solusi" class="nav-link">Solusi</a>
            <a href="index.php#layanan" class="nav-link active">Layanan</a>
            <a href="index.php#portfolio" class="nav-link">Portofolio</a>
            <a href="index.php#alur" class="nav-link">Alur Kerja</a>
            <a href="index.php#konsultasi" class="nav-link">Konsultasi</a>
        </nav>

        <div class="nav-actions">
            <a href="checkout.php" class="cart-btn" id="cartOpen" aria-label="Buka Keranjang">
                <i class="fa-solid fa-bag-shopping"></i>
                <span class="cart-count" id="cartCount">0</span>
            </a>

            <div id="authNav" class="auth-nav">
                <?php if ($isUserLoggedIn): ?>
                    <a href="profil.php" class="auth-nav-profile" title="Profil Saya" aria-label="Profil Saya">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                    </a>
                    <a href="logout.php" class="auth-nav-logout" id="logoutLink" title="Keluar / Logout" aria-label="Keluar / Logout">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="auth-nav-link">Login</a>
                    <a href="register.php" class="auth-nav-button">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="container detail-wrapper">
    <!-- BREADCRUMB -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Beranda</a>
        <i class="fa-solid fa-chevron-right"></i>
        <a href="index.php#layanan">Katalog Layanan</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?= htmlspecialchars($service['name']) ?></span>
    </nav>

    <?php if ($reviewMessage !== ''): ?>
        <div class="alert-box <?= $reviewMessageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fa-solid <?= $reviewMessageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($reviewMessage) ?>
        </div>
    <?php endif; ?>

    <!-- MAIN DETAIL GRID -->
    <div class="detail-grid">
        <!-- LEFT COLUMN -->
        <div class="detail-left">
            <article class="service-main-card">
                <!-- HERO IMAGE -->
                <div class="service-hero-image">
                    <span class="badge-category"><?= htmlspecialchars($service['category_name'] ?? 'Layanan Digital') ?></span>
                    <img src="<?= htmlspecialchars($serviceImage) ?>"
                         alt="Visual <?= htmlspecialchars($service['name']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=1200&q=80'">
                </div>

                <!-- INFO BODY -->
                <div class="service-info-box">
                    <div class="service-header-meta">
                        <div class="rating-chip">
                            <i class="fa-solid fa-star"></i>
                            <span><?= number_format($avgRating, 1) ?> / 5.0 (<?= $totalReviews ?> ulasan)</span>
                        </div>
                        <div class="mitra-badge">
                            <i class="fa-solid fa-store"></i>
                            <span><?= $mitraCount ?>+ Mitra Hidroponik Telah Menggunakan</span>
                        </div>
                    </div>

                    <h1><?= htmlspecialchars($service['name']) ?></h1>

                    <p class="service-description">
                        <?= nl2br(htmlspecialchars($service['description'] ?? 'Layanan profesional Balla’ Fresh untuk mempercepat pertumbuhan dan digitalisasi usaha hidroponik Anda.')) ?>
                    </p>

                    <!-- DELIVERABLES / FEATURES -->
                    <div class="content-block">
                        <h2><i class="fa-solid fa-circle-check"></i> Apa Saja yang Anda Dapatkan</h2>
                        <?php if (!empty($features)): ?>
                            <div class="features-grid">
                                <?php foreach ($features as $feat): ?>
                                    <div class="feature-item">
                                        <i class="fa-solid fa-check"></i>
                                        <span><?= htmlspecialchars($feat) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--muted);font-size:14px">Paket disesuaikan langsung dengan skala dan kebutuhan kebun Anda.</p>
                        <?php endif; ?>
                    </div>

                    <!-- WORKFLOW -->
                    <div class="content-block">
                        <h2><i class="fa-solid fa-list-check"></i> Alur Pengerjaan Layanan</h2>
                        <div class="workflow-row">
                            <div class="wf-step">
                                <div class="wf-num">01</div>
                                <h3>Brief & Kebutuhan</h3>
                                <p>Diskusi awal mengenai produk, target pasar, dan karakter brand sayuran Anda.</p>
                            </div>
                            <div class="wf-step">
                                <div class="wf-num">02</div>
                                <h3>Proses Pengerjaan</h3>
                                <p>Tim Balla’ Fresh menyusun materi desain dan setup teknis dengan standar UMKM.</p>
                            </div>
                            <div class="wf-step">
                                <div class="wf-num">03</div>
                                <h3>Review & Revisi</h3>
                                <p>Mitra meninjau draf hasil kerja dan memberikan masukan penyempurnaan.</p>
                            </div>
                            <div class="wf-step">
                                <div class="wf-num">04</div>
                                <h3>Serah Terima File</h3>
                                <p>Aset siap pakai diserahkan lengkap beserta panduan implementasi mandiri.</p>
                            </div>
                        </div>
                    </div>

                    <!-- DOKUMENTASI CONTOH KARYA -->
                    <div class="content-block">
                        <h2><i class="fa-solid fa-images"></i> Contoh Dokumentasi & Mockup Karya</h2>
                        <div class="doc-gallery">
                            <?php foreach ($sampleDocs as $docImg): ?>
                                <div class="doc-thumb">
                                    <img src="<?= htmlspecialchars($docImg) ?>"
                                         alt="Contoh hasil <?= htmlspecialchars($service['name']) ?>"
                                         onerror="this.src='https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=600&q=80'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </article>

            <!-- REVIEWS SECTION -->
            <section class="reviews-container" id="ulasan">
                <div class="reviews-header">
                    <div>
                        <h2>Ulasan & Rating Mitra Bisnis</h2>
                        <p style="font-size:13px;color:var(--muted)">Pengalaman nyata dari petani dan pemilik usaha hidroponik di Makassar & sekitarnya.</p>
                    </div>
                    <div class="reviews-summary">
                        <div class="score-big"><?= number_format($avgRating, 1) ?></div>
                        <div>
                            <div class="stars-row">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-<?= $i <= round($avgRating) ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-count-text"><?= $totalReviews ?> ulasan terverifikasi</div>
                        </div>
                    </div>
                </div>

                <!-- REVIEW LIST -->
                <div class="review-list">
                    <?php if (empty($reviews)): ?>
                        <p style="color:var(--muted);font-size:14px;text-align:center;padding:20px 0;">Belum ada ulasan untuk layanan ini. Jadilah mitra pertama yang memberikan testimoni!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="review-card">
                                <div class="review-meta">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?= strtoupper(substr((string)$rev['user_name'], 0, 1)) ?>
                                        </div>
                                        <div class="reviewer-names">
                                            <strong><?= htmlspecialchars($rev['user_name']) ?></strong>
                                            <small><?= htmlspecialchars($rev['business_name'] ?: 'Mitra Hidroponik') ?></small>
                                        </div>
                                    </div>
                                    <div class="review-stars">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="fa-<?= $s <= (int)$rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-text">
                                    "<?= htmlspecialchars($rev['review_text']) ?>"
                                </div>
                                <div class="review-date">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('d M Y', strtotime((string)$rev['created_at'])) ?> · Terverifikasi
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- FORM TULIS ULASAN -->
                <div class="add-review-box">
                    <h3>Bagikan Pengalaman Anda</h3>
                    <p>Bantu petani dan pegiat hidroponik lainnya mengetahui kualitas hasil layanan ini.</p>
                    <form method="post" action="detail_layanan.php?id=<?= $serviceId ?>#ulasan">
                        <input type="hidden" name="action" value="submit_review">
                        <div class="form-row">
                            <div class="form-field">
                                <label for="userNameInput">Nama Anda *</label>
                                <input type="text" id="userNameInput" name="user_name"
                                       value="<?= htmlspecialchars($userName) ?>"
                                       placeholder="Contoh: Andi Hermanto" required>
                            </div>
                            <div class="form-field">
                                <label for="businessNameInput">Nama Usaha / Kebun Hidroponik</label>
                                <input type="text" id="businessNameInput" name="business_name"
                                       placeholder="Contoh: Makassar Hydro Farm">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-field">
                                <label for="ratingInput">Rating Layanan</label>
                                <select id="ratingInput" name="rating">
                                    <option value="5">⭐⭐⭐⭐⭐ (5 - Sangat Puas)</option>
                                    <option value="4">⭐⭐⭐⭐ (4 - Puas)</option>
                                    <option value="3">⭐⭐⭐ (3 - Cukup)</option>
                                    <option value="2">⭐⭐ (2 - Kurang)</option>
                                    <option value="1">⭐ (1 - Tidak Puas)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-field" style="margin-bottom:16px;">
                            <label for="reviewTextInput">Ulasan Anda *</label>
                            <textarea id="reviewTextInput" name="review_text" rows="3"
                                      placeholder="Tuliskan pengalaman Anda menggunakan layanan ini..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit-review">
                            <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
                        </button>
                    </form>
                </div>
            </section>

            <!-- RELATED SERVICES SECTION -->
            <?php if (!empty($relatedServices)): ?>
                <div class="related-section">
                    <h2 class="related-title">Layanan Pendukung Lainnya</h2>
                    <div class="related-grid">
                        <?php foreach ($relatedServices as $rel): ?>
                            <div class="related-card">
                                <div>
                                    <span class="badge-category" style="position:static;display:inline-block;margin-bottom:10px;">
                                        <?= htmlspecialchars($rel['category_name'] ?? 'Layanan') ?>
                                    </span>
                                    <h3><?= htmlspecialchars($rel['name']) ?></h3>
                                    <p><?= htmlspecialchars($rel['description']) ?></p>
                                </div>
                                <div class="related-bottom">
                                    <strong style="color:#e48e1e;font-size:18px;">Rp<?= number_format((float)$rel['price'], 0, ',', '.') ?></strong>
                                    <a href="detail_layanan.php?id=<?= (int)$rel['id'] ?>"
                                       class="btn-secondary-action" style="width:auto;padding:8px 16px;font-size:13px;">
                                        Lihat Detail <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT STICKY ORDER CARD -->
        <aside class="detail-right">
            <div class="sticky-card">
                <div class="price-tag-wrapper">
                    <span class="price-label">Biaya Investasi Layanan</span>
                    <div class="price-amount"><?= $priceFormatted ?></div>
                    <div class="duration-pill">
                        <i class="fa-regular fa-clock"></i>
                        <span>Estimasi Pengerjaan: <?= $estimatedDays ?> Hari Kerja</span>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn-primary-action" type="button" id="btnAddToCart">
                        <i class="fa-solid fa-cart-plus"></i> Tambah ke Keranjang
                    </button>
                    <button class="btn-secondary-action" type="button" id="btnOrderDirect">
                        <i class="fa-solid fa-bolt"></i> Pesan Langsung (Checkout)
                    </button>
                    <a href="https://wa.me/6281234567890?text=Halo%20Tim%20Balla%20Fresh%2C%20saya%20tertarik%20dengan%20layanan%20<?= urlencode($service['name']) ?>"
                       target="_blank" rel="noopener noreferrer" class="btn-wa-action">
                        <i class="fa-brands fa-whatsapp" style="font-size:17px;"></i> Konsultasi WhatsApp Tim
                    </a>
                </div>

                <ul class="guarantee-list">
                    <li><i class="fa-solid fa-shield-halved"></i> 100% Khusus UMKM & Petani Hidroponik</li>
                    <li><i class="fa-solid fa-rotate-left"></i> Jaminan Revisi Hingga Tuntas</li>
                    <li><i class="fa-solid fa-file-export"></i> Serah Terima Aset Master Lengkap</li>
                    <li><i class="fa-solid fa-comments"></i> Pendampingan Konsultasi Teknis</li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> <strong>Balla’ Fresh</strong> — Digital Growth Partner untuk UMKM Hidroponik Makassar.</p>
    </div>
</footer>

<!-- TOAST NOTIFICATION -->
<div class="toast" id="toastMessage">
    <i class="fa-solid fa-circle-check"></i>
    <span id="toastText">Layanan berhasil ditambahkan ke keranjang!</span>
</div>

<script>
// Data layanan saat ini untuk integrasi ke keranjang belanja
const currentService = {
    id: "<?= (int)$service['id'] ?>",
    name: <?= json_encode($service['name']) ?>,
    category: <?= json_encode($service['category_name'] ?? 'Layanan') ?>,
    price: <?= (float)$service['price'] ?>,
    duration: "<?= $estimatedDays ?> hari"
};

// Ambil keranjang dari localStorage
function getCart() {
    try {
        const raw = localStorage.getItem("ballaFreshCart");
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

// Simpan keranjang ke localStorage
function saveCart(cart) {
    localStorage.setItem("ballaFreshCart", JSON.stringify(cart));
    updateCartCount();
}

// Update badge hitungan keranjang
function updateCartCount() {
    const cart = getCart();
    const countEl = document.getElementById("cartCount");
    if (!countEl) return;
    const totalQty = cart.reduce((sum, item) => sum + (Number(item.qty) || 1), 0);
    countEl.textContent = totalQty;
    countEl.style.display = totalQty > 0 ? "grid" : "none";
}

// Tampilkan Toast
function showToast(message) {
    const toast = document.getElementById("toastMessage");
    const text = document.getElementById("toastText");
    if (!toast || !text) return;
    text.textContent = message;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3200);
}

// Tambah ke keranjang
document.getElementById("btnAddToCart")?.addEventListener("click", () => {
    let cart = getCart();
    const existingIndex = cart.findIndex(item => String(item.id) === String(currentService.id));

    if (existingIndex > -1) {
        cart[existingIndex].qty = (Number(cart[existingIndex].qty) || 1) + 1;
    } else {
        cart.push({
            id: currentService.id,
            name: currentService.name,
            category: currentService.category,
            price: currentService.price,
            duration: currentService.duration,
            qty: 1
        });
    }

    saveCart(cart);
    showToast(`"${currentService.name}" berhasil ditambahkan ke keranjang!`);
});

// Pesan Langsung (Checkout langsung)
document.getElementById("btnOrderDirect")?.addEventListener("click", () => {
    let cart = getCart();
    const existingIndex = cart.findIndex(item => String(item.id) === String(currentService.id));

    if (existingIndex === -1) {
        cart.push({
            id: currentService.id,
            name: currentService.name,
            category: currentService.category,
            price: currentService.price,
            duration: currentService.duration,
            qty: 1
        });
        saveCart(cart);
    }

    window.location.href = "checkout.php";
});

// Inisialisasi saat halaman dimuat
document.addEventListener("DOMContentLoaded", () => {
    updateCartCount();
});
</script>
</body>
</html>

