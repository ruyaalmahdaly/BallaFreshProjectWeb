<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

// Halaman membaca artikel hanya dapat diakses saat pengguna sudah login/register
if (empty($_SESSION['user_id'])) {
    $redirectUrl = 'baca_artikel.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: login.php?redirect=' . urlencode($redirectUrl));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userName = (string) ($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Mitra Balla’ Fresh');

// Ambil data user untuk navbar
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

// Ambil artikel berdasarkan slug atau id
$slug = trim((string) ($_GET['slug'] ?? ''));
$articleId = (int) ($_GET['id'] ?? 0);

$article = null;
if ($slug !== '') {
    $stmt = $conn->prepare("SELECT * FROM articles WHERE slug = ? AND is_published = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $article = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$article && $articleId > 0) {
    $stmt = $conn->prepare("SELECT * FROM articles WHERE id = ? AND is_published = 1 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $articleId);
        $stmt->execute();
        $article = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Jika tidak ditemukan atau belum dipublikasikan, redirect ke katalog artikel
if (!$article) {
    header('Location: artikel.php');
    exit;
}

// Tambah counter pembaca (views)
$conn->query("UPDATE articles SET views_count = views_count + 1 WHERE id = " . (int)$article['id']);
$article['views_count'] = (int)($article['views_count'] ?? 0) + 1;

// Ambil 3 artikel terkait (kategori sama atau artikel terbaru lainnya)
$relatedArticles = [];
$relStmt = $conn->prepare("
    SELECT id, title, slug, category, image, created_at, read_time
    FROM articles 
    WHERE id != ? AND is_published = 1 
    ORDER BY (category = ?) DESC, created_at DESC 
    LIMIT 3
");
if ($relStmt) {
    $relStmt->bind_param('is', $article['id'], $article['category']);
    $relStmt->execute();
    $relRes = $relStmt->get_result();
    while ($rRow = $relRes->fetch_assoc()) {
        $relatedArticles[] = $rRow;
    }
    $relStmt->close();
}

if (!function_exists('art_e')) {
    function art_e(mixed $val): string {
        return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
    }
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = $_SERVER['REQUEST_URI'] ?? '/baca_artikel.php';
$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$host}{$uri}";
$articleImage = !empty($article['image']) ? $article['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1200&q=80';
$authorAvatar = !empty($article['author_avatar']) ? $article['author_avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= art_e($article['title']) ?> | Balla’ Fresh Edukasi</title>
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
    --radius-lg: 24px;
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
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

.container { width: min(1140px, 92%); margin: auto; }

/* HEADER & NAVBAR */
.site-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--line);
}
.navbar {
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
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
.menu-toggle { display: none; border: 0; background: none; font-size: 24px; color: var(--green); cursor: pointer; }

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

/* BREADCRUMB */
.breadcrumb-row {
    padding: 24px 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: #64748b;
    flex-wrap: wrap;
}
.breadcrumb-row a {
    color: #64748b;
    transition: var(--ease);
}
.breadcrumb-row a:hover {
    color: var(--green);
}
.breadcrumb-row .bc-sep {
    font-size: 10px;
    color: #cbd5e1;
}
.breadcrumb-row .bc-current {
    color: #0f172a;
    font-weight: 600;
}

/* ARTICLE HEADER (REFERENSI GAMBAR 2) */
.article-header {
    margin-bottom: 24px;
}
.article-headline {
    font-size: clamp(26px, 3.8vw, 38px);
    font-weight: 800;
    color: #0f172a;
    line-height: 1.28;
    margin-bottom: 18px;
    letter-spacing: -0.02em;
}

.article-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--line);
}
.meta-left-group {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.meta-author {
    display: flex;
    align-items: center;
    gap: 9px;
}
.meta-author-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--green-soft);
}
.meta-author-name {
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
}

.badge-category {
    display: inline-flex;
    align-items: center;
    background: var(--green-soft);
    color: var(--green);
    border: 1px solid rgba(91, 129, 33, 0.2);
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
}

.meta-info-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12.5px;
    color: #64748b;
}
.meta-info-item i {
    font-size: 12px;
    color: #94a3b8;
}

.meta-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.btn-meta-action {
    background: #ffffff;
    border: 1.5px solid var(--line);
    color: #64748b;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    font-size: 14px;
    cursor: pointer;
    transition: var(--ease);
}
.btn-meta-action:hover, .btn-meta-action.active {
    color: #e11d48;
    border-color: #fda4af;
    background: #fff1f2;
}

/* FULL-WIDTH HERO IMAGE */
.article-hero-wrap {
    margin-bottom: 40px;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08);
    background: #e2e8f0;
}
.article-hero-img {
    width: 100%;
    max-height: 480px;
    object-fit: cover;
    display: block;
}

/* 2-COLUMN LAYOUT (REFERENSI GAMBAR 2) */
.article-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 48px;
    align-items: start;
    margin-bottom: 70px;
}

/* MAIN CONTENT (LEFT COLUMN) */
.article-main-body {
    background: #ffffff;
    border-radius: var(--radius-lg);
    padding: 36px 40px;
    border: 1px solid var(--line);
    box-shadow: var(--shadow-sm);
}

.article-lead {
    font-size: 17px;
    line-height: 1.7;
    color: #334155;
    font-weight: 500;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid #f1f5f9;
}

.content-typography {
    font-size: 15.5px;
    line-height: 1.85;
    color: #334155;
}
.content-typography p {
    margin-bottom: 20px;
}
.content-typography h1 {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 36px;
    margin-bottom: 18px;
    line-height: 1.3;
}
.content-typography h2 {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 36px;
    margin-bottom: 16px;
    line-height: 1.35;
}
.content-typography h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 26px;
    margin-bottom: 12px;
}
.content-typography ul, .content-typography ol {
    margin-bottom: 22px;
    padding-left: 24px;
}
.content-typography ol {
    list-style-type: decimal;
}
.content-typography ul {
    list-style-type: disc;
}
.content-typography li {
    margin-bottom: 8px;
}
.content-typography strong, .content-typography b {
    color: #0f172a;
    font-weight: 700;
}
.content-typography em, .content-typography i {
    font-style: italic;
}
.content-typography u {
    text-decoration: underline;
}
.content-typography s, .content-typography strike, .content-typography del {
    text-decoration: line-through;
}
.content-typography a {
    color: var(--green);
    text-decoration: underline;
    font-weight: 600;
    transition: var(--ease);
}
.content-typography a:hover {
    color: var(--green-dark);
}
.content-typography blockquote {
    margin: 28px 0;
    padding: 18px 24px;
    background: var(--green-soft);
    border-left: 4px solid var(--green);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-style: italic;
    color: var(--green-dark);
    font-size: 16px;
    line-height: 1.7;
}

/* SHARE FOOTER BAR */
.share-bar-bottom {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.share-bar-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #475569;
}
.share-buttons-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-share-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 14px;
    color: #ffffff;
    transition: var(--ease);
}
.btn-share-icon:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
}
.share-wa { background: #25D366; }
.share-fb { background: #1877F2; }
.share-tw { background: #000000; }
.share-li { background: #0A66C2; }
.share-copy { background: #475569; cursor: pointer; border: 0; }

/* AUTHOR BIO CARD */
.author-bio-card {
    margin-top: 36px;
    padding: 24px;
    background: #f8fafc;
    border-radius: var(--radius);
    border: 1px solid var(--line);
    display: flex;
    gap: 20px;
    align-items: flex-start;
}
.author-bio-img {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
}
.author-bio-content h4 {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 2px;
}
.author-bio-content .role {
    font-size: 12px;
    font-weight: 600;
    color: var(--green);
    margin-bottom: 8px;
    display: inline-block;
}
.author-bio-content p {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}

/* SIDEBAR (RIGHT COLUMN - REFERENSI GAMBAR 2) */
.article-sidebar {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.sidebar-box {
    background: #ffffff;
    border-radius: var(--radius);
    padding: 24px;
    border: 1px solid var(--line);
    box-shadow: var(--shadow-sm);
}

.sidebar-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Share Icons Row */
.sidebar-share-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-share-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #475569;
    display: grid;
    place-items: center;
    font-size: 13px;
    transition: var(--ease);
    border: 1px solid #e2e8f0;
}
.sidebar-share-btn:hover {
    background: var(--green);
    color: #ffffff;
    border-color: var(--green);
}

/* All Tags Cloud (Gambar 2: All Tags) */
.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.tag-pill {
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 5px 12px;
    border-radius: 99px;
    transition: var(--ease);
}
.tag-pill:hover {
    background: var(--green-soft);
    border-color: rgba(91, 129, 33, 0.3);
    color: var(--green);
}

/* Related Articles List (Gambar 2: Related Blogs) */
.related-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.related-item {
    display: flex;
    align-items: center;
    gap: 14px;
    transition: var(--ease);
}
.related-thumb {
    width: 64px;
    height: 64px;
    border-radius: var(--radius-sm);
    object-fit: cover;
    flex-shrink: 0;
    background: #e2e8f0;
}
.related-meta {
    flex: 1;
}
.related-date {
    font-size: 11px;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 4px;
}
.related-link-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: var(--ease);
}
.related-item:hover .related-link-title {
    color: var(--green);
}

/* Callout Card (Gambar 2: Prefer to Talk to Us Directly?) */
.consult-card-sidebar {
    background: linear-gradient(145deg, #1e293b, #0f172a);
    color: #ffffff;
    border-radius: var(--radius);
    padding: 26px 22px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
}
.consult-card-sidebar:before {
    content: "";
    position: absolute;
    width: 140px;
    height: 140px;
    background: radial-gradient(circle, rgba(91, 129, 33, 0.4) 0%, transparent 70%);
    top: -30px;
    right: -30px;
    pointer-events: none;
}
.consult-eyebrow {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #a3e635;
    font-weight: 800;
    margin-bottom: 8px;
    display: block;
}
.consult-card-sidebar h4 {
    color: #ffffff;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.3;
    margin-bottom: 10px;
}
.consult-card-sidebar p {
    font-size: 12.5px;
    color: #94a3b8;
    line-height: 1.55;
    margin-bottom: 18px;
}
.btn-consult-sidebar {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--green);
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    padding: 10px 18px;
    border-radius: 99px;
    transition: var(--ease);
}
.btn-consult-sidebar:hover {
    background: #6f9d28;
    transform: translateX(2px);
}

/* Newsletter Box (Gambar 2: Join Our Newsletter) */
.newsletter-box {
    background: #f8fafc;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 24px;
}
.newsletter-box h4 {
    font-size: 15px;
    font-weight: 800;
    margin-bottom: 6px;
    color: #0f172a;
}
.newsletter-box p {
    font-size: 12.5px;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 14px;
}
.newsletter-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.newsletter-input {
    width: 100%;
    padding: 9px 12px;
    border-radius: 10px;
    border: 1px solid var(--line);
    font-size: 12.5px;
    outline: none;
    transition: var(--ease);
}
.newsletter-input:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(91,129,33,0.1);
}
.newsletter-submit {
    width: 100%;
    padding: 9px;
    border-radius: 10px;
    background: #0f172a;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    border: 0;
    cursor: pointer;
    transition: var(--ease);
}
.newsletter-submit:hover {
    background: var(--green);
}

/* TOAST ALERT */
.toast-msg {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #0f172a;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    display: none;
    align-items: center;
    gap: 10px;
    z-index: 9999;
    animation: slideUp 0.3s ease;
}
@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* FOOTER - mengikuti struktur footer index.php & artikel.php */
.site-footer {
    background: #0b140c;
    color: rgba(255, 255, 255, 0.7);
    padding: 64px 0 28px;
    font-size: 13px;
    border-top: 0;
    margin-top: 60px;
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

/* RESPONSIVE */
@media (max-width: 900px) {
    .article-layout {
        grid-template-columns: 1fr;
        gap: 36px;
    }
    .article-main-body {
        padding: 24px 20px;
    }
}
</style>
</head>
<body>

<!-- SITE NAVBAR - mengikuti struktur navbar index.php & artikel.php -->
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

<main class="container">

    <!-- BREADCRUMB (Gambar 2: Home • Our Blogs • Category) -->
    <nav class="breadcrumb-row" aria-label="Breadcrumb">
        <a href="index.php"><i class="fa-solid fa-house" style="font-size:11px"></i> Beranda</a>
        <span class="bc-sep">/</span>
        <a href="artikel.php">Artikel Edukasi</a>
        <span class="bc-sep">/</span>
        <a href="artikel.php?category=<?= urlencode($article['category']) ?>"><?= art_e($article['category']) ?></a>
        <span class="bc-sep">/</span>
        <span class="bc-current"><?= art_e(mb_strimwidth($article['title'], 0, 40, '...')) ?></span>
    </nav>

    <!-- ARTICLE HEADER -->
    <header class="article-header">
        <h1 class="article-headline"><?= art_e($article['title']) ?></h1>

        <div class="article-meta-row">
            <div class="meta-left-group">
                <div class="meta-author">
                    <img class="meta-author-avatar" src="<?= art_e($authorAvatar) ?>" alt="<?= art_e($article['author_name']) ?>">
                    <span class="meta-author-name"><?= art_e($article['author_name']) ?></span>
                </div>
                <span class="badge-category"><?= art_e($article['category']) ?></span>
                <span class="meta-info-item"><i class="fa-regular fa-clock"></i> <?= art_e($article['read_time']) ?></span>
                <span class="meta-info-item"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime((string)$article['created_at'])) ?></span>
                <span class="meta-info-item"><i class="fa-regular fa-eye"></i> <?= number_format((int)$article['views_count']) ?> pembaca</span>
            </div>

            <div class="meta-actions">
                <button class="btn-meta-action" id="likeBtn" title="Sukai artikel ini" aria-label="Sukai artikel">
                    <i class="fa-regular fa-heart"></i>
                </button>
                <button class="btn-meta-action" onclick="copyArticleLink()" title="Salin Tautan" aria-label="Salin Tautan">
                    <i class="fa-solid fa-share-nodes"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- FULL WIDTH HERO IMAGE (Gambar 2) -->
    <div class="article-hero-wrap">
        <img class="article-hero-img" src="<?= art_e($articleImage) ?>" alt="<?= art_e($article['title']) ?>">
    </div>

    <!-- 2-COLUMN LAYOUT -->
    <div class="article-layout">

        <!-- LEFT COLUMN: ARTICLE BODY -->
        <article class="article-main-body">

            <?php if (!empty($article['excerpt'])): ?>
                <div class="article-lead">
                    <?= art_e($article['excerpt']) ?>
                </div>
            <?php endif; ?>

            <div class="content-typography">
                <?= $article['content'] ?>
            </div>

            <!-- BOTTOM SOCIAL SHARE BAR -->
            <div class="share-bar-bottom">
                <div class="share-bar-title">Bagikan wawasan ini ke rekan petani &amp; tim usaha:</div>
                <div class="share-buttons-group">
                    <a class="btn-share-icon share-wa" target="_blank" rel="noopener noreferrer" 
                       href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $pageUrl) ?>" 
                       title="Bagikan ke WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                    <a class="btn-share-icon share-fb" target="_blank" rel="noopener noreferrer" 
                       href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>" 
                       title="Bagikan ke Facebook">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>
                    <a class="btn-share-icon share-tw" target="_blank" rel="noopener noreferrer" 
                       href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($pageUrl) ?>" 
                       title="Bagikan ke X">
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>
                    <a class="btn-share-icon share-li" target="_blank" rel="noopener noreferrer" 
                       href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($pageUrl) ?>" 
                       title="Bagikan ke LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>
                    <button class="btn-share-icon share-copy" onclick="copyArticleLink()" title="Salin Tautan">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>

            <!-- AUTHOR BIO CARD -->
            <div class="author-bio-card">
                <img class="author-bio-img" src="<?= art_e($authorAvatar) ?>" alt="<?= art_e($article['author_name']) ?>">
                <div class="author-bio-content">
                    <h4><?= art_e($article['author_name']) ?></h4>
                    <span class="role"><?= art_e($article['author_role']) ?></span>
                    <p>Praktisi dan konsultan di Balla’ Fresh yang berfokus mendampingi pelaku usaha hidroponik meningkatkan nilai jual produk, standardisasi SOP kebun, serta strategi penetrasi pasar ritel modern dan e-commerce.</p>
                </div>
            </div>

            <div style="margin-top: 30px; display:flex; justify-content: space-between; align-items:center;">
                <a href="artikel.php" class="tag-pill" style="padding: 8px 16px; font-weight:700;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Katalog Artikel
                </a>
                <a href="index.php#konsultasi" class="tag-pill" style="padding: 8px 16px; font-weight:700; background:var(--green-soft); color:var(--green); border-color:var(--green);">
                    Konsultasi Kebutuhan Kebun <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </article>

        <!-- RIGHT COLUMN: SIDEBAR (REFERENSI GAMBAR 2) -->
        <aside class="article-sidebar">

            <!-- 1. SHARE ON SOCIAL MEDIA (Gambar 2 Top Right) -->
            <div class="sidebar-box">
                <div class="sidebar-title">
                    <span>Share on Social Media</span>
                    <i class="fa-solid fa-share-nodes" style="color:#94a3b8;font-size:13px"></i>
                </div>
                <div class="sidebar-share-row">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $pageUrl) ?>" target="_blank" class="sidebar-share-btn" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    <a href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($pageUrl) ?>" target="_blank" class="sidebar-share-btn" title="X / Twitter"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>" target="_blank" class="sidebar-share-btn" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($pageUrl) ?>" target="_blank" class="sidebar-share-btn" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <button class="sidebar-share-btn" onclick="copyArticleLink()" title="Salin Tautan"><i class="fa-regular fa-copy"></i></button>
                </div>
            </div>

            <!-- 2. ALL TAGS (Gambar 2 Middle Right) -->
            <div class="sidebar-box">
                <div class="sidebar-title">
                    <span>All Tags / Topik Terkait</span>
                    <i class="fa-solid fa-tags" style="color:#94a3b8;font-size:13px"></i>
                </div>
                <div class="tag-cloud">
                    <a href="artikel.php?category=Branding+%26+Kemasan" class="tag-pill">Branding Kebun</a>
                    <a href="artikel.php?category=Branding+%26+Kemasan" class="tag-pill">Desain Kemasan</a>
                    <a href="artikel.php?category=Budidaya+%26+Teknis" class="tag-pill">Nutrisi AB Mix</a>
                    <a href="artikel.php?category=Budidaya+%26+Teknis" class="tag-pill">SOP Sayur Segar</a>
                    <a href="artikel.php?category=Pemasaran+%26+Penjualan" class="tag-pill">B2B Swalayan</a>
                    <a href="artikel.php?category=Pemasaran+%26+Penjualan" class="tag-pill">WhatsApp Marketing</a>
                    <a href="artikel.php?category=Manajemen+Bisnis" class="tag-pill">Analisis BEP</a>
                    <a href="artikel.php?category=Manajemen+Bisnis" class="tag-pill">Margin Sayur</a>
                </div>
            </div>

            <!-- 3. RELATED BLOGS (Gambar 2 Middle Right) -->
            <?php if (!empty($relatedArticles)): ?>
            <div class="sidebar-box">
                <div class="sidebar-title">
                    <span>Related Blogs / Artikel Terkait</span>
                    <i class="fa-solid fa-newspaper" style="color:#94a3b8;font-size:13px"></i>
                </div>
                <div class="related-list">
                    <?php foreach ($relatedArticles as $rel): 
                        $relImg = !empty($rel['image']) ? $rel['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=200&q=80';
                    ?>
                        <a href="baca_artikel.php?slug=<?= urlencode($rel['slug']) ?>" class="related-item">
                            <img class="related-thumb" src="<?= art_e($relImg) ?>" alt="<?= art_e($rel['title']) ?>">
                            <div class="related-meta">
                                <span class="related-date"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime((string)$rel['created_at'])) ?></span>
                                <div class="related-link-title"><?= art_e($rel['title']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 4. CALLOUT BOX (Gambar 2 Right: Prefer to Talk to Us Directly?) -->
            <div class="consult-card-sidebar">
                <span class="consult-eyebrow"><i class="fa-solid fa-comments"></i> Bimbingan Intensif</span>
                <h4>Prefer to Talk to Us Directly?</h4>
                <p>Punya tantangan khusus di kebun hidroponik atau ingin membedah strategi kemasan dan distribusi hasil panen Anda?</p>
                <a href="index.php#konsultasi" class="btn-consult-sidebar">
                    Konsultasi Gratis <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <!-- 5. JOIN OUR NEWSLETTER (Gambar 2 Bottom Right) -->
            <div class="newsletter-box">
                <h4>Join Our Newsletter</h4>
                <p>Dapatkan rangkuman artikel edukasi, kalkulator nutrisi, dan strategi pemasaran hidroponik langsung ke inbox Anda.</p>
                <form class="newsletter-form" onsubmit="event.preventDefault(); showToast('Terima kasih! Email Anda telah terdaftar dalam buletin mingguan Balla’ Fresh.', 'success'); this.reset();">
                    <input type="email" class="newsletter-input" placeholder="contoh@kebunanda.com" required>
                    <button type="submit" class="newsletter-submit">Langganan Sekarang</button>
                </form>
            </div>

        </aside>

    </div>

</main>

<div class="toast-msg" id="toastMsg">
    <i class="fa-solid fa-circle-check" style="color:#a3e635"></i>
    <span id="toastText">Tautan artikel berhasil disalin!</span>
</div>

<!-- FOOTER - mengikuti struktur footer index.php & artikel.php -->
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
// Navigasi mobile, mengikuti perilaku navbar halaman Beranda & Artikel.
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

function copyArticleLink() {
    const url = window.location.href;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Tautan artikel berhasil disalin ke clipboard!');
        });
    } else {
        const dummy = document.createElement('input');
        document.body.appendChild(dummy);
        dummy.value = url;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        showToast('Tautan artikel berhasil disalin!');
    }
}

function showToast(text) {
    const toast = document.getElementById('toastMsg');
    const toastText = document.getElementById('toastText');
    toastText.innerText = text;
    toast.style.display = 'flex';
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3200);
}

// Like button toggle
const likeBtn = document.getElementById('likeBtn');
if (likeBtn) {
    likeBtn.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon.classList.contains('fa-regular')) {
            icon.classList.remove('fa-regular');
            icon.classList.add('fa-solid');
            this.classList.add('active');
            showToast('Terima kasih! Anda menyukai artikel ini.');
        } else {
            icon.classList.remove('fa-solid');
            icon.classList.add('fa-regular');
            this.classList.remove('active');
        }
    });
}
</script>

</body>
</html>
