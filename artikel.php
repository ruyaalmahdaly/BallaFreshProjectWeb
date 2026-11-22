<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

// Halaman artikel hanya dapat diakses saat pengguna sudah login/register
if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('artikel.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userName = (string) ($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Mitra Balla’ Fresh');

// Ambil data user untuk avatar navbar
$userAvatar = '';
$userInitial = strtoupper(substr(trim($userName), 0, 1) ?: 'M');
$uStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ? LIMIT 1");
if ($uStmt) {
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    if ($uRow && !empty($uRow['profile_image'])) {
        $userAvatar = (string) $uRow['profile_image'];
    }
    $uStmt->close();
}

// Filter kategori dan pencarian
$selectedCategory = trim((string) ($_GET['category'] ?? 'all'));
$searchQuery = trim((string) ($_GET['q'] ?? ''));

// Daftar kategori
$categories = [
    'all' => 'Semua Topik',
    'Branding & Kemasan' => 'Branding & Kemasan',
    'Budidaya & Teknis' => 'Budidaya & Teknis',
    'Pemasaran & Penjualan' => 'Pemasaran & Penjualan',
    'Manajemen Bisnis' => 'Manajemen Bisnis'
];

// Hitung jumlah artikel per kategori
$catCounts = ['all' => 0];
$countRes = $conn->query("SELECT category, COUNT(*) AS total FROM articles WHERE is_published = 1 GROUP BY category");
if ($countRes) {
    while ($row = $countRes->fetch_assoc()) {
        $catName = (string) $row['category'];
        $catTotal = (int) $row['total'];
        $catCounts[$catName] = $catTotal;
        $catCounts['all'] += $catTotal;
    }
    $countRes->free();
}

// Query artikel unggulan / terpopuler untuk Hero Banner (Gambar 1)
$featuredArticle = null;
$featRes = $conn->query("SELECT * FROM articles WHERE is_published = 1 AND is_featured = 1 ORDER BY updated_at DESC LIMIT 1");
if ($featRes && ($fRow = $featRes->fetch_assoc())) {
    $featuredArticle = $fRow;
    $featRes->free();
} else {
    // Fallback ke artikel terbaru
    $latestRes = $conn->query("SELECT * FROM articles WHERE is_published = 1 ORDER BY created_at DESC LIMIT 1");
    if ($latestRes && ($lRow = $latestRes->fetch_assoc())) {
        $featuredArticle = $lRow;
        $latestRes->free();
    }
}

// Query daftar artikel
$whereClauses = ["is_published = 1"];
$params = [];
$types = "";

if ($selectedCategory !== 'all' && isset($categories[$selectedCategory])) {
    $whereClauses[] = "category = ?";
    $params[] = $selectedCategory;
    $types .= "s";
}

if ($searchQuery !== '') {
    $whereClauses[] = "(title LIKE ? OR excerpt LIKE ? OR category LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$whereSql = implode(' AND ', $whereClauses);
$sql = "SELECT * FROM articles WHERE {$whereSql} ORDER BY is_featured DESC, created_at DESC";

$articlesList = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $articlesList[] = $row;
    }
    $stmt->close();
}

function art_e(mixed $val): string {
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Artikel &amp; Wawasan Edukasi Hidroponik | Balla’ Fresh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --green: #5b8121;
    --green-dark: #405b18;
    --green-light: #789f31;
    --green-soft: #f0f5e7;
    --lime: #dce9bd;
    --white: #ffffff;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --orange: #e48e1e;
    --radius-sm: 12px;
    --radius: 18px;
    --radius-lg: 26px;
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    --ease: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: "DM Sans", sans-serif;
    background: #fbfdf9;
    color: var(--ink);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
h1, h2, h3, h4 { font-family: "Manrope", sans-serif; line-height: 1.25; color: #1e293b; }

.container { width: min(1180px, 90%); margin: auto; }

/* HEADER & NAVBAR - disamakan dengan index.php */
.site-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(248, 250, 246, 0.85);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.navbar {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
}
.logo {
    width: 145px;
    height: 45px;
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}
.logo img { max-height: 42px; width: auto; object-fit: contain; }
.logo-fallback {
    display: none;
    font-family: "Manrope", sans-serif;
    font-weight: 800;
    font-size: 22px;
    color: var(--green);
    letter-spacing: -0.04em;
}
.nav-menu { display: flex; align-items: center; gap: 32px; }
.nav-link {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    position: relative;
    transition: var(--ease);
}
.nav-link:after {
    content: "";
    position: absolute;
    left: 0;
    bottom: -6px;
    width: 0;
    height: 2.5px;
    background: var(--green);
    transition: var(--ease);
    border-radius: 2px;
}
.nav-link:hover, .nav-link.active { color: var(--green); }
.nav-link:hover:after, .nav-link.active:after { width: 100%; }
.nav-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.cart-btn,
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
}
.cart-btn:hover, .auth-nav-profile:hover {
    background: var(--green-soft);
    border-color: var(--green);
    color: var(--green);
    transform: translateY(-1px);
}
.auth-nav { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.auth-nav-profile i, .auth-nav-logout i { display: grid; place-items: center; font-size: 16px; }
.auth-nav-logout { color: #64748b; }
.auth-nav-logout:hover {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
    transform: translateY(-1px);
}
.menu-toggle { display: none; border: 0; background: none; font-size: 24px; color: var(--green); }

@media (max-width: 980px) {
    .nav-menu { gap: 20px; }
}
@media (max-width: 720px) {
    .navbar { height: 70px; }
    .nav-menu {
        display: none;
        position: absolute;
        left: 4%;
        right: 4%;
        top: 75px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 18px;
        box-shadow: var(--shadow-lg);
    }
    .nav-menu.open { display: grid; gap: 8px; }
    .nav-link { display: block; padding: 10px 8px; }
    .nav-link:after { display: none; }
    .menu-toggle { display: block; }
    .logo { width: 125px; }
    .nav-actions { gap: 8px; }
    .cart-btn, .auth-nav-profile, .auth-nav-logout { width: 38px; height: 38px; font-size: 14px; }
}

/* MAIN CONTENT */
.page-wrap { padding: 40px 0 80px; }

/* PAGE HEADER (Gambar 1 Reference: Browse Our Resources) */
.blog-hero-header {
    margin-bottom: 34px;
}
.blog-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: var(--green);
    background: var(--green-soft);
    border: 1px solid rgba(91,129,33,0.18);
    padding: 5px 12px;
    border-radius: 99px;
    margin-bottom: 12px;
}
.blog-hero-header h1 {
    font-size: clamp(28px, 3.2vw, 38px);
    font-weight: 800;
    letter-spacing: -0.8px;
    color: #1a2712;
    margin-bottom: 8px;
}
.blog-hero-header p {
    font-size: 14.5px;
    color: var(--muted);
    max-width: 680px;
}

/* FEATURED ARTICLE BANNER (Gambar 1: Large Featured Card with Overlay) */
.featured-banner {
    position: relative;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 40px;
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
    background: #1e293b;
    min-height: 420px;
    display: flex;
    align-items: flex-end;
    transition: transform 0.3s ease;
}
.featured-banner:hover {
    transform: translateY(-2px);
}
.featured-banner-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}
.featured-banner:hover .featured-banner-img {
    transform: scale(1.03);
}
.featured-banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.5) 45%, rgba(15,23,42,0.92) 100%);
}
.featured-banner-content {
    position: relative;
    z-index: 2;
    padding: 36px 40px;
    color: #ffffff;
    max-width: 860px;
}
.featured-badge-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.featured-pill {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
    letter-spacing: 0.3px;
}
.featured-banner-title {
    font-size: clamp(22px, 2.6vw, 32px);
    font-weight: 800;
    line-height: 1.25;
    color: #ffffff;
    margin-bottom: 12px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.featured-banner-excerpt {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
    margin-bottom: 20px;
    max-width: 720px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.featured-banner-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding-top: 14px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}
.featured-author {
    display: flex;
    align-items: center;
    gap: 10px;
}
.featured-author-img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ffffff;
}
.featured-author-info strong {
    display: block;
    font-size: 13px;
    color: #ffffff;
}
.featured-author-info small {
    display: block;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.7);
}
.featured-arrow {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #ffffff;
    color: var(--green);
    display: grid;
    place-items: center;
    font-size: 16px;
    transition: var(--ease);
}
.featured-banner:hover .featured-arrow {
    background: var(--green);
    color: #ffffff;
    transform: rotate(45deg);
}

/* FILTER TABS & SEARCH BAR (Gambar 1: Navigation Row) */
.filter-search-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 32px;
    flex-wrap: wrap;
    border-bottom: 1.5px solid var(--line);
    padding-bottom: 12px;
}
.category-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.cat-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 99px;
    font-size: 12.5px;
    font-weight: 700;
    color: #64748b;
    background: transparent;
    border: 1px solid transparent;
    transition: var(--ease);
    white-space: nowrap;
}
.cat-tab-btn:hover {
    color: var(--green);
    background: var(--green-soft);
}
.cat-tab-btn.active {
    color: var(--green);
    background: var(--green-soft);
    border-color: rgba(91, 129, 33, 0.25);
}
.cat-badge-count {
    font-size: 10px;
    background: #ffffff;
    color: var(--green);
    padding: 1px 6px;
    border-radius: 99px;
    border: 1px solid rgba(91,129,33,0.2);
    font-weight: 800;
}
.cat-tab-btn.active .cat-badge-count {
    background: var(--green);
    color: #ffffff;
}

.search-box-form {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid var(--line);
    border-radius: 99px;
    padding: 2px 14px;
    width: min(100%, 280px);
    transition: var(--ease);
}
.search-box-form:focus-within {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(91, 129, 33, 0.1);
}
.search-box-form input {
    border: 0;
    outline: none;
    font-size: 12.5px;
    width: 100%;
    padding: 8px 4px;
    background: transparent;
    color: var(--ink);
}
.search-box-form button {
    border: 0;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 13px;
}

/* ARTICLES 3-COLUMN GRID (Gambar 1 Reference) */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
}
@media (max-width: 992px) {
    .articles-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .articles-grid { grid-template-columns: 1fr; }
    .featured-banner-content { padding: 24px; }
}

.article-card {
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 14px rgba(0,0,0,0.02);
    transition: var(--ease);
}
.article-card:hover {
    border-color: #cbd8bc;
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(91, 129, 33, 0.08);
}
.article-card-thumb-wrap {
    position: relative;
    width: 100%;
    padding-top: 60%;
    overflow: hidden;
    background: #e2e8f0;
}
.article-card-thumb {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.article-card:hover .article-card-thumb {
    transform: scale(1.05);
}
.article-card-cat-badge {
    position: absolute;
    top: 14px;
    left: 14px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
    color: var(--green);
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.article-card-body {
    padding: 20px 22px 22px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.article-card-cat-text {
    font-size: 11px;
    font-weight: 700;
    color: var(--green);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.article-card-title {
    font-size: 16.5px;
    font-weight: 800;
    line-height: 1.35;
    margin-bottom: 8px;
    color: #1e293b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.article-card:hover .article-card-title {
    color: var(--green);
}
.article-card-excerpt {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.55;
    margin-bottom: 18px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}
.article-card-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.author-pill {
    display: flex;
    align-items: center;
    gap: 8px;
}
.author-pill-img {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--green-soft);
}
.author-pill-name {
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}
.read-time {
    font-size: 11px;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* FOOTER - disamakan dengan index.php */
.site-footer {
    background: #0b140c;
    color: rgba(255, 255, 255, 0.7);
    padding: 64px 0 28px;
    font-size: 13px;
    border-top: 0;
}
.footer-grid {
    display: grid;
    grid-template-columns: 1.3fr 0.7fr 0.8fr;
    gap: 48px;
    padding-bottom: 40px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.footer-brand img {
    max-height: 40px;
    max-width: 180px;
    margin-bottom: 18px;
    background: #fff;
    padding: 6px 10px;
    border-radius: 10px;
}
.footer-brand p { font-size: 13px; max-width: 400px; line-height: 1.7; }
.site-footer h4 {
    color: #fff;
    font-size: 14px;
    margin-bottom: 16px;
    letter-spacing: -0.01em;
}
.footer-links { display: grid; gap: 10px; font-size: 13px; }
.footer-links a { color: rgba(255, 255, 255, 0.7); transition: var(--ease); }
.footer-links a:hover { color: #fff; }
.footer-bottom {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding-top: 24px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.5);
}
@media (max-width: 720px) {
    .footer-grid { grid-template-columns: 1fr; gap: 30px; }
}
</style>
</head>
<body>

<!-- SITE NAVBAR - mengikuti struktur navbar index.php -->
<header class="site-header">
    <div class="container navbar">
        <a class="logo" href="index.php#beranda" aria-label="Balla’ Fresh">
            <img src="logo-balla-fresh.png" alt="Logo Balla’ Fresh"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span class="logo-fallback">Balla’ Fresh</span>
        </a>

        <nav class="nav-menu" id="navMenu">
            <a href="index.php#beranda" class="nav-link">Beranda</a>
            <a href="index.php#tentang-kami" class="nav-link">Tentang Kami</a>
            <a href="index.php#layanan" class="nav-link">Layanan</a>
            <a href="artikel.php" class="nav-link active" aria-current="page">Artikel</a>
            <a href="index.php#portfolio" class="nav-link">Portofolio</a>
            <a href="index.php#konsultasi" class="nav-link">Konsultasi</a>
        </nav>

        <div class="nav-actions">
            <a href="checkout.php" class="cart-btn" aria-label="Keranjang Belanja" title="Keranjang Belanja">
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
            </a>
            <div class="auth-nav">
                <a href="profil.php" class="auth-nav-profile" title="Profil Saya" aria-label="Profil Saya">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                </a>
                <a href="logout.php" class="auth-nav-logout" title="Keluar / Logout" aria-label="Keluar / Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </a>
            </div>
            <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</header>

<main class="page-wrap">
    <div class="container">

        <!-- HEADER SECTION (Browse Our Resources) -->
        <div class="blog-hero-header">
            <span class="blog-eyebrow"><i class="fa-solid fa-leaf"></i> Edukasi &amp; Wawasan Agensi</span>
            <h1>Jelajahi Wawasan &amp; Artikel Hidroponik</h1>
            <p>Panduan praktis, strategi branding kemasan, wawasan pemasaran digital, dan manajemen bisnis dari para spesialis Balla’ Fresh untuk kemajuan UMKM hidroponik.</p>
        </div>

        <!-- 1. HERO FEATURED BANNER (Gambar 1 Referensi) -->
        <?php if ($featuredArticle):
            $fImage = !empty($featuredArticle['image']) ? $featuredArticle['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1000&q=80';
            $fSlug = $featuredArticle['slug'];
            $fAuthorAvatar = !empty($featuredArticle['author_avatar']) ? $featuredArticle['author_avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
        ?>
            <a href="baca_artikel.php?slug=<?= urlencode($fSlug) ?>" class="featured-banner">
                <img class="featured-banner-img" src="<?= art_e($fImage) ?>" alt="<?= art_e($featuredArticle['title']) ?>">
                <div class="featured-banner-overlay"></div>
                <div class="featured-banner-content">
                    <div class="featured-badge-row">
                        <span class="featured-pill"><i class="fa-solid fa-star" style="color:#f59e0b;font-size:10px"></i> Artikel Populer</span>
                        <span class="featured-pill"><?= art_e($featuredArticle['category']) ?></span>
                        <span class="featured-pill"><i class="fa-regular fa-clock" style="font-size:10px"></i> <?= art_e($featuredArticle['read_time']) ?></span>
                    </div>
                    <h2 class="featured-banner-title"><?= art_e($featuredArticle['title']) ?></h2>
                    <p class="featured-banner-excerpt"><?= art_e($featuredArticle['excerpt']) ?></p>
                    <div class="featured-banner-meta">
                        <div class="featured-author">
                            <img class="featured-author-img" src="<?= art_e($fAuthorAvatar) ?>" alt="<?= art_e($featuredArticle['author_name']) ?>">
                            <div class="featured-author-info">
                                <strong><?= art_e($featuredArticle['author_name']) ?></strong>
                                <small><?= date('d M Y', strtotime((string)$featuredArticle['created_at'])) ?> · <?= art_e($featuredArticle['author_role']) ?></small>
                            </div>
                        </div>
                        <div class="featured-arrow">
                            <i class="fa-solid fa-arrow-up-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        <?php endif; ?>

        <!-- 2. FILTER TABS & SEARCH BAR (Gambar 1 Referensi) -->
        <div class="filter-search-row">
            <div class="category-tabs">
                <?php foreach ($categories as $catKey => $catLabel):
                    $isActive = ($selectedCategory === $catKey);
                    $countVal = $catCounts[$catKey] ?? 0;
                ?>
                    <a href="artikel.php?category=<?= urlencode($catKey) ?><?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>" class="cat-tab-btn <?= $isActive ? 'active' : '' ?>">
                        <span><?= art_e($catLabel) ?></span>
                        <span class="cat-badge-count"><?= (int)$countVal ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <form class="search-box-form" method="get" action="artikel.php">
                <?php if ($selectedCategory !== 'all'): ?>
                    <input type="hidden" name="category" value="<?= art_e($selectedCategory) ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= art_e($searchQuery) ?>" placeholder="Cari artikel hidroponik...">
                <button type="submit" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>

        <!-- 3. ARTICLE CARDS GRID (Gambar 1 Referensi) -->
        <?php if (empty($articlesList)): ?>
            <div style="text-align:center;padding:60px 20px;background:#ffffff;border:1px dashed #cbd5e1;border-radius:18px">
                <i class="fa-regular fa-newspaper" style="font-size:38px;color:#94a3b8;margin-bottom:12px"></i>
                <h3 style="font-size:18px;margin-bottom:6px">Tidak ada artikel yang cocok</h3>
                <p style="font-size:13px;color:var(--muted);max-width:380px;margin:auto">Coba ubah kata kunci pencarian atau pilih kategori lain di atas.</p>
                <div style="margin-top:16px">
                    <a href="artikel.php" class="btn-nav-icon" style="width:auto;height:auto;padding:8px 18px;border-radius:99px;font-size:12px;font-weight:700">Lihat Semua Artikel</a>
                </div>
            </div>
        <?php else: ?>
            <div class="articles-grid">
                <?php foreach ($articlesList as $art):
                    $cardImg = !empty($art['image']) ? $art['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80';
                    $authorAvatar = !empty($art['author_avatar']) ? $art['author_avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80';
                ?>
                    <article class="article-card">
                        <a href="baca_artikel.php?slug=<?= urlencode($art['slug']) ?>" class="article-card-thumb-wrap">
                            <img class="article-card-thumb" src="<?= art_e($cardImg) ?>" alt="<?= art_e($art['title']) ?>" loading="lazy">
                            <span class="article-card-cat-badge"><?= art_e($art['category']) ?></span>
                        </a>
                        <div class="article-card-body">
                            <span class="article-card-cat-text"><?= art_e($art['category']) ?></span>
                            <h3 class="article-card-title">
                                <a href="baca_artikel.php?slug=<?= urlencode($art['slug']) ?>">
                                    <?= art_e($art['title']) ?>
                                </a>
                            </h3>
                            <p class="article-card-excerpt"><?= art_e($art['excerpt']) ?></p>
                            <div class="article-card-foot">
                                <div class="author-pill">
                                    <img class="author-pill-img" src="<?= art_e($authorAvatar) ?>" alt="<?= art_e($art['author_name']) ?>">
                                    <span class="author-pill-name"><?= art_e($art['author_name']) ?></span>
                                </div>
                                <span class="read-time"><i class="fa-regular fa-clock"></i> <?= art_e($art['read_time']) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- FOOTER - mengikuti struktur footer index.php -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <img src="logo-balla-fresh.png" alt="Balla’ Fresh"
                     onerror="this.style.display='none'">
                <p>Digital marketing agency yang berfokus membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan secara digital.</p>
            </div>

            <div>
                <h4>Jelajahi</h4>
                <div class="footer-links">
                    <a href="index.php#tentang-kami">Tentang Kami</a>
                    <a href="index.php#solusi">Solusi</a>
                    <a href="index.php#layanan">Layanan</a>
                    <a href="index.php#portfolio">Portofolio</a>
                    <a href="index.php#alur">Alur Kerja</a>
                    <a href="artikel.php">Artikel &amp; Edukasi</a>
                </div>
            </div>

            <div>
                <h4>Hubungi</h4>
                <div class="footer-links">
                    <a href="index.php#konsultasi">Konsultasi Mitra</a>
                    <a href="mailto:ballafresh01@gmail.com">ballafresh01@gmail.com</a>
                    <a href="https://wa.me/6281213725107" target="_blank" rel="noopener noreferrer">WhatsApp: 081213725107</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© 2026 Balla’ Fresh. All rights reserved.</span>
            <span>Menjangkau lebih banyak peluang, #DariRumahAja.</span>
        </div>
    </div>
</footer>


<script>
// Navigasi mobile, mengikuti perilaku navbar halaman Beranda.
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

menuToggle?.addEventListener('click', () => {
    const isOpen = navMenu?.classList.toggle('open') ?? false;
    menuToggle.setAttribute('aria-expanded', String(isOpen));
    menuToggle.setAttribute(
        'aria-label',
        isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'
    );
    const icon = menuToggle.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-bars', !isOpen);
        icon.classList.toggle('fa-xmark', isOpen);
    }
});

navMenu?.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        navMenu.classList.remove('open');
        menuToggle?.setAttribute('aria-expanded', 'false');
        const icon = menuToggle?.querySelector('i');
        icon?.classList.add('fa-bars');
        icon?.classList.remove('fa-xmark');
    });
});
</script>

</body>
</html>

