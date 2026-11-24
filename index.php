<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

$isUserLoggedIn = isset($_SESSION['user_id']);
$isAdminLoggedIn = isset($_SESSION['admin_id']);

// Admin diarahkan ke dashboard admin, bukan Home pelanggan.
if ($isAdminLoggedIn) {
    header('Location: admin_dashboard.php');
    exit;
}

// Default konten website (dapat diubah langsung oleh admin di dashboard)
$siteContent = [
    'hero_tag' => 'Digital Growth Partner',
    'hero_title' => 'Lebih banyak peluang, #DariRumahAja.',
    'hero_description' => 'Balla’ Fresh membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan melalui solusi digital yang sesuai dengan kebutuhan usahanya.',
    'hero_image' => '',
    'about_tag' => 'Tentang Balla’ Fresh',
    'about_title' => 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan',
    'about_story' => 'Balla’ Fresh berawal dari kepedulian terhadap para penggiat hidroponik rumahan di Makassar yang memiliki hasil panen berkualitas prima, namun seringkali kesulitan menjangkau pasar yang lebih luas. Berdiri sebagai agensi pertumbuhan digital, Balla’ Fresh mendedikasikan diri untuk mendampingi petani hidroponik lokal agar dapat bersaing di era digital dengan kemasan profesional, kanal pemasaran terpadu, dan identitas brand yang kuat.',
    'about_problem' => 'Banyak petani hidroponik rumahan memproduksi sayuran segar berkualitas tinggi seperti selada, pakcoy, dan kale, namun terbentur kendala: ketiadaan branding kemasan yang memikat konsumen modern, proses pemesanan manual yang tidak terstruktur, serta keterbatasan wawasan strategi promosi digital. Akibatnya, penjualan hanya berputar di lingkaran tetangga terdekat dengan margin keuntungan yang rendah.',
    'about_solution' => 'Balla’ Fresh hadir memberikan solusi terintegrasi dan modular: pembuatan identitas visual (logo & kemasan), setup katalog produk dan pemesanan digital berbasis web, hingga strategi promosi media sosial yang tepat sasaran untuk pasar rumah tangga dan pelaku kuliner sehat di perkotaan.',
    'about_image' => 'Edible1_dok.jpeg',
    'cta_badge' => 'Akses Eksklusif Mitra Balla’ Fresh',
    'cta_title' => 'Buka Seluruh Potensi Usaha Hidroponik Anda Sekarang',
    'cta_description' => 'Masuk atau daftarkan akun kebun Anda untuk membuka katalog harga resmi, melakukan konsultasi pemetaan kebutuhan, serta memesan layanan digitalisasi Balla’ Fresh secara langsung.',
    'contact_email' => 'ballafresh01@gmail.com',
    'contact_phone' => '081213725107',
    'contact_address' => 'Makassar, Sulawesi Selatan, Indonesia'
];

if (!empty($conn)) {
    $contentQuery = $conn->query("SELECT * FROM agency_site_content WHERE id = 1 LIMIT 1");
    if ($contentQuery && ($contentRow = $contentQuery->fetch_assoc())) {
        foreach ($contentRow as $k => $v) {
            if ($v !== null && trim((string)$v) !== '') {
                $siteContent[$k] = $v;
            }
        }
    }
}

// Ambil struktur tim (4 orang: 1 Owner + 3 Member)
$teamMembers = [];
if (!empty($conn)) {
    $teamQuery = $conn->query("SELECT * FROM agency_team ORDER BY display_order ASC, id ASC");
    if ($teamQuery) {
        while ($tRow = $teamQuery->fetch_assoc()) {
            $teamMembers[] = $tRow;
        }
    }
}
if (empty($teamMembers)) {
    $teamMembers = [
        ['id' => 1, 'role_type' => 'owner', 'name' => "Ru'ya Shadiqa A", 'position' => 'Owner & Agency Lead', 'photo' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80', 'display_order' => 1],
        ['id' => 2, 'role_type' => 'member', 'name' => 'Andi Fadhlur Rahman', 'position' => 'Lead Brand & Visual Designer', 'photo' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80', 'display_order' => 2],
        ['id' => 3, 'role_type' => 'member', 'name' => 'Nur Sernovita Bunga', 'position' => 'Lead Marketing & Social Growth', 'photo' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80', 'display_order' => 3],
        ['id' => 4, 'role_type' => 'member', 'name' => 'Muh. Ilham Pratama', 'position' => 'Lead Web & Operations Specialist', 'photo' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80', 'display_order' => 4],
    ];
}

$ownerMember = null;
$teamOtherMembers = [];
foreach ($teamMembers as $tm) {
    if ($tm['role_type'] === 'owner' && $ownerMember === null) {
        $ownerMember = $tm;
    } else {
        $teamOtherMembers[] = $tm;
    }
}
if (!$ownerMember && !empty($teamOtherMembers)) {
    $ownerMember = array_shift($teamOtherMembers);
}

// Artikel edukasi terbaru untuk pengguna yang sudah login
$homeArticles = [];
if ($isUserLoggedIn && !empty($conn)) {
    $artQuery = $conn->query("SELECT id, title, slug, category, excerpt, image, author_name, author_avatar, read_time, created_at FROM articles WHERE is_published = 1 ORDER BY is_featured DESC, created_at DESC LIMIT 3");
    if ($artQuery) {
        while ($aRow = $artQuery->fetch_assoc()) {
            $homeArticles[] = $aRow;
        }
        $artQuery->free();
    }
}

// Data pengguna yang sedang login & status siklus konsultasi
$currentUser = null;
$userLatestConsultation = null;
$consultNotice = '';
$consultNoticeType = '';

if ($isUserLoggedIn && !empty($conn)) {
    $uId = (int)$_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT id, name, email, phone, business_name FROM users WHERE id = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param('i', $uId);
        $uStmt->execute();
        $currentUser = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
    }

    // Handle pengiriman permintaan konsultasi baru dari beranda
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_home_consultation') {
        // Validasi apakah sedang ada tiket yang pending atau approved
        $checkActive = $conn->prepare("SELECT id, status FROM consultations WHERE user_id = ? AND status IN ('pending', 'approved') ORDER BY id DESC LIMIT 1");
        $activeRow = null;
        if ($checkActive) {
            $checkActive->bind_param('i', $uId);
            $checkActive->execute();
            $activeRow = $checkActive->get_result()->fetch_assoc();
            $checkActive->close();
        }

        if ($activeRow) {
            if ($activeRow['status'] === 'pending') {
                $consultNotice = 'Permohonan konsultasi Anda sebelumnya masih menunggu persetujuan (ACC) dari admin.';
            } else {
                $consultNotice = 'Anda saat ini memiliki sesi konsultasi yang sedang aktif. Silakan buka room chat di halaman profil Anda.';
            }
            $consultNoticeType = 'error';
        } else {
            $cClientName = trim((string)($_POST['client_name'] ?? ($currentUser['name'] ?? '')));
            $cBusinessName = trim((string)($_POST['business_name'] ?? ($currentUser['business_name'] ?? '')));
            $cPhone = trim((string)($_POST['phone'] ?? ($currentUser['phone'] ?? '')));
            $cCategory = trim((string)($_POST['category'] ?? 'Starter Branding'));
            $cNotes = trim((string)($_POST['notes'] ?? ''));
            $cEmail = (string)($currentUser['email'] ?? '');

            if ($cClientName === '' || $cPhone === '' || $cNotes === '') {
                $consultNotice = 'Mohon lengkapi Nama, Nomor Kontak, dan Kebutuhan Usaha Anda.';
                $consultNoticeType = 'error';
            } else {
                $insC = $conn->prepare("INSERT INTO consultations (user_id, client_name, business_name, phone, email, category, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($insC) {
                    $insC->bind_param('issssss', $uId, $cClientName, $cBusinessName, $cPhone, $cEmail, $cCategory, $cNotes);
                    if ($insC->execute()) {
                        $newCid = (int)$conn->insert_id;
                        $initMsg = "Permintaan konsultasi diajukan dari halaman beranda:\nTopik: {$cCategory}\nKebutuhan: {$cNotes}";
                        $mStmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, 'user', ?, ?, ?)");
                        if ($mStmt) {
                            $mStmt->bind_param('iiss', $newCid, $uId, $cClientName, $initMsg);
                            $mStmt->execute();
                            $mStmt->close();
                        }
                        $consultNotice = 'Permintaan konsultasi berhasil dikirim! Silakan menunggu persetujuan (ACC) admin untuk memulai obrolan.';
                        $consultNoticeType = 'success';
                    } else {
                        $consultNotice = 'Gagal mengirim permintaan: ' . $insC->error;
                        $consultNoticeType = 'error';
                    }
                    $insC->close();
                }
            }
        }
    }

    // Ambil konsultasi paling akhir untuk menentukan siklus form
    $cLatestStmt = $conn->prepare("SELECT id, category, notes, status, created_at, approved_at FROM consultations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    if ($cLatestStmt) {
        $cLatestStmt->bind_param('i', $uId);
        $cLatestStmt->execute();
        $userLatestConsultation = $cLatestStmt->get_result()->fetch_assoc();
        $cLatestStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Balla’ Fresh — Digital Growth Partner untuk UMKM Hidroponik</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --green: #5b8121;
    --green-dark: #405b18;
    --green-light: #789f31;
    --green-soft: #f0f5e7;
    --lime: #dce9bd;
    --cream: #ffffff;
    --white: #ffffff;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --orange: #e48e1e;
    --orange-soft: #fff6e8;
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -2px rgba(0, 0, 0, 0.03);
    --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    --radius-sm: 12px;
    --radius: 20px;
    --radius-lg: 28px;
    --ease: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family:"DM Sans",sans-serif;
    background:var(--cream);
    color:var(--ink);
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
}
body.no-scroll{overflow:hidden}
h1,h2,h3,h4{font-family:"Manrope",sans-serif;line-height:1.2;letter-spacing:-0.03em}
a{text-decoration:none;color:inherit}
button,input,textarea,select{font:inherit}
button{cursor:pointer}
img{max-width:100%;display:block}

.container{width:min(1180px,90%);margin:auto}
.section{padding:100px 0}
.section.alt{background:#f5f7ef}
.section-head{max-width:680px;margin:0 auto 52px;text-align:center}
.eyebrow{
    display:inline-flex;align-items:center;gap:8px;
    color:var(--green);font-weight:700;font-size:12px;
    letter-spacing:.12em;text-transform:uppercase;
    background:var(--green-soft);padding:8px 16px;border-radius:999px;
    margin-bottom:18px;border:1px solid rgba(46,107,52,0.12);
}
.section-head h2{font-size:40px;margin-bottom:16px;color:#1d2a12}
.section-head p{color:var(--muted);font-size:16px;line-height:1.7}

.btn{
    border:0;border-radius:14px;padding:14px 24px;
    display:inline-flex;align-items:center;justify-content:center;gap:10px;
    font-weight:700;font-size:14px;transition:var(--ease);
}
.btn-primary{background:var(--green);color:#fff;box-shadow:0 4px 14px rgba(46,107,52,0.25)}
.btn-primary:hover{background:var(--green-dark);transform:translateY(-2px);box-shadow:0 6px 20px rgba(46,107,52,0.35)}
.btn-outline{background:#fff;color:var(--green);border:1.5px solid var(--green)}
.btn-outline:hover{background:var(--green-soft);transform:translateY(-2px)}
.btn-light{background:#fff;color:var(--green)}
.btn-light:hover{transform:translateY(-2px)}

header{
    position:sticky;top:0;z-index:1000;
    background:rgba(248,250,246,0.85);backdrop-filter:blur(16px);
    border-bottom:1px solid rgba(0,0,0,0.05);
}
.navbar{height:80px;display:flex;align-items:center;justify-content:space-between;gap:28px}
.logo{
    width:145px;height:45px;display:flex;align-items:center;
}
.logo img{max-height:42px;width:auto}
.logo-fallback{
    display:none;font-family:"Manrope";font-weight:800;font-size:22px;color:var(--green);letter-spacing:-0.04em;
}
.nav-menu{display:flex;align-items:center;gap:32px}
.nav-link{font-size:14px;font-weight:600;color:#475569;position:relative;transition:var(--ease)}
.nav-link:after{
    content:"";position:absolute;left:0;bottom:-6px;width:0;height:2.5px;
    background:var(--green);transition:var(--ease);border-radius:2px;
}
.nav-link:hover,.nav-link.active{color:var(--green)}
.nav-link:hover:after,.nav-link.active:after{width:100%}
.nav-actions{display:flex;align-items:center;gap:12px}
.cart-btn{
    position:relative;width:44px;height:44px;border-radius:12px;
    border:1px solid var(--line);background:#fff;color:var(--green);
    display:grid;place-items:center;transition:var(--ease);
    box-shadow:var(--shadow-sm);
}

/* CSS LOGIN & REGISTER */
.auth-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.auth-nav-link {
    color: #405b18;
    text-decoration: none;
    font-weight: 700;
}

.auth-nav-button {
    background: #5b8121;
    color: #fff;
    padding: 11px 17px;
    border-radius: 15px;
    text-decoration: none;
    font-weight: 700;
}

.auth-nav-button:hover {
    background: #405b18;
}


/* Navbar pengguna setelah login */
.auth-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
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

@media (max-width: 720px) {
    .auth-nav {
        gap: 8px;
    }

    .auth-nav-profile,
    .auth-nav-logout {
        width: 38px;
        height: 38px;
        font-size: 14px;
    }
}



@media (max-width: 720px) {
    .auth-nav { gap: 10px; }
    .auth-nav-button { padding: 9px 12px; }
}

.cart-btn:hover{background:var(--green-soft);border-color:var(--green)}
.cart-count{
    position:absolute;right:-6px;top:-6px;min-width:22px;height:22px;
    border-radius:999px;background:var(--orange);color:#fff;
    display:grid;place-items:center;font-size:11px;font-weight:800;
    padding:0 6px;border:2px solid #fff
}
.menu-toggle{display:none;border:0;background:none;font-size:24px;color:var(--green)}

.hero{
    padding:96px 0 84px;
    background:
      radial-gradient(circle at 85% 20%, rgba(217,249,157,0.4), transparent 35%),
      linear-gradient(180deg,#f8faf6 0%,#f5f7ef 100%);
}
.hero-grid{display:grid;grid-template-columns:1.05fr .95fr;align-items:center;gap:64px}
.hero h1{font-size:56px;max-width:650px;margin-bottom:24px;color:#18220f;letter-spacing:-0.035em}
.hero h1 span{color:var(--green);background:linear-gradient(135deg, var(--green), var(--green-light));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.hero-copy{font-size:18px;color:var(--muted);max-width:580px;margin-bottom:34px;line-height:1.7}
.hero-actions{display:flex;gap:14px;flex-wrap:wrap}
.hero-note{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;margin-top:20px;font-weight:500}
.hero-note i{color:var(--green);font-size:15px}
.hero-visual{
    position:relative;background:linear-gradient(145deg, var(--green-dark), var(--green));
    border-radius:36px;min-height:440px;padding:36px;overflow:hidden;
    box-shadow:0 25px 50px -12px rgba(30,71,34,0.3);
}
.hero-visual:before{
    content:"";position:absolute;width:300px;height:300px;border-radius:50%;
    background:rgba(217,249,157,0.15);right:-80px;top:-80px;filter:blur(40px)
}
.hero-visual:after{
    content:"";position:absolute;width:220px;height:220px;border-radius:50%;
    background:rgba(255,255,255,0.08);left:-80px;bottom:-80px;filter:blur(30px)
}
.hero-panel{
    position:relative;z-index:2;background:#fff;border-radius:24px;
    padding:28px;box-shadow:var(--shadow-lg);
}
.panel-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.panel-label{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:var(--green);font-weight:800}
.panel-dot{width:10px;height:10px;border-radius:50%;background:#22c55e;box-shadow:0 0 10px #22c55e}
.hero-services{display:grid;gap:12px}
.hero-service{
    display:flex;align-items:center;gap:16px;padding:14px 16px;
    background:#f8faf6;border:1px solid #eef2eb;border-radius:16px;
    transition:var(--ease)
}
.hero-service:hover{transform:translateX(4px);background:#fff;border-color:var(--line)}
.hero-service-icon{
    width:42px;height:42px;border-radius:12px;background:var(--green-soft);
    color:var(--green);display:grid;place-items:center;flex:none;font-size:16px
}
.hero-service strong{display:block;font-size:14px;color:var(--ink)}
.hero-service small{color:var(--muted);font-size:12px}
.hero-chip{
    position:absolute;z-index:3;right:24px;bottom:24px;
    background:var(--lime);color:var(--green-dark);
    padding:12px 18px;border-radius:16px;font-size:13px;font-weight:800;
    box-shadow:var(--shadow);border:1px solid rgba(255,255,255,0.6)
}

/* problem accordion */
.problem-wrap{max-width:880px;margin:auto}
.problem-item{
    border:1px solid var(--line);background:#fff;margin-bottom:12px;
    border-radius:18px;overflow:hidden;transition:var(--ease);box-shadow:var(--shadow-sm)
}
.problem-item:hover{border-color:#cbd5e1}
.problem-item.open{border-color:rgba(46,107,52,0.3);box-shadow:var(--shadow)}
.problem-trigger{
    width:100%;background:none;border:0;padding:24px;
    display:flex;align-items:center;gap:20px;text-align:left;cursor:pointer
}
.problem-icon{
    width:50px;height:50px;border-radius:16px;background:var(--green-soft);
    color:var(--green);display:grid;place-items:center;flex:none;font-size:18px
}
.problem-title{flex:1}
.problem-title strong{display:block;font-size:16px;color:var(--ink)}
.problem-title span{display:block;color:var(--muted);font-size:13px;margin-top:3px}
.problem-chevron{color:var(--green);transition:var(--ease);font-size:14px}
.problem-item.open .problem-chevron{transform:rotate(180deg)}
.problem-answer{
    max-height:0;overflow:hidden;transition:max-height .35s ease;
}
.problem-inner{padding:0 24px 24px 94px;display:grid;grid-template-columns:1fr 1fr;gap:18px}
.answer-box{padding:20px;border-radius:16px}
.answer-box.problem{background:#fff7ed;border:1px solid #ffedd5}
.answer-box.solution{background:var(--green-soft);border:1px solid #dcfce7}
.answer-box small{font-weight:800;text-transform:uppercase;letter-spacing:.08em;font-size:11px;display:block;margin-bottom:8px}
.answer-box.problem small{color:#c2410c}
.answer-box.solution small{color:var(--green)}
.answer-box p{font-size:13.5px;color:#334155;line-height:1.6}

/* services */
.services-top{display:flex;justify-content:space-between;align-items:flex-end;gap:25px;margin-bottom:36px}
.services-top .section-head{margin:0;text-align:left}
.services-top .section-head h2{font-size:36px}
.service-controls{display:flex;flex-direction:column;align-items:flex-end;gap:14px;min-width:min(100%,430px)}
.service-search{position:relative;width:min(100%,430px)}
.service-search i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#84917c}
.service-search input{width:100%;height:46px;padding:0 16px 0 44px;border:1px solid var(--line);border-radius:13px;background:#fff;color:var(--ink);outline:none;box-shadow:var(--shadow-sm);transition:var(--ease)}
.service-search input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(91,129,33,.10)}
.category-tabs{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.category-tab{
    border:1px solid var(--line);background:#fff;color:var(--muted);
    padding:10px 18px;border-radius:999px;font-size:13px;font-weight:700;
    transition:var(--ease);box-shadow:var(--shadow-sm)
}
.category-tab:hover{border-color:var(--green);color:var(--green)}
.category-tab.active{background:var(--green);border-color:var(--green);color:#fff;box-shadow:0 4px 12px rgba(46,107,52,0.2)}
.service-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.service-card{
    background:#fff;border:1px solid var(--line);border-radius:22px;
    padding:24px;display:flex;flex-direction:column;min-height:390px;
    transition:var(--ease);position:relative;box-shadow:var(--shadow-sm);overflow:hidden
}
.service-image{height:150px;margin:-24px -24px 22px;overflow:hidden;background:linear-gradient(135deg,#edf3df,#fafcf6)}
.service-image img{width:100%;height:100%;object-fit:cover;transition:transform .45s ease}
.service-card:hover .service-image img{transform:scale(1.045)}
.service-empty{grid-column:1/-1;padding:35px;text-align:center;color:var(--muted);background:#fff;border:1px dashed var(--line);border-radius:18px}
.service-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg);border-color:rgba(46,107,52,0.2)}
.service-tag{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--green);margin-bottom:14px}
.service-card h3{font-size:20px;margin-bottom:10px;color:var(--ink)}
.service-card p{font-size:13.5px;color:var(--muted);margin-bottom:20px;line-height:1.6}
.service-duration{display:inline-flex;align-items:center;gap:7px;width:max-content;margin-top:-4px;margin-bottom:18px;padding:7px 10px;border-radius:9px;background:#f7f9f2;border:1px solid #e7edd8;color:var(--green);font-size:11.5px;font-weight:700}
.service-duration i{font-size:11px}
.service-list{list-style:none;display:grid;gap:10px;margin-bottom:24px}
.service-list li{font-size:13px;color:#475569;display:flex;gap:10px;align-items:center}
.service-list i{color:var(--green);font-size:12px}
.service-bottom{margin-top:auto;border-top:1px solid var(--line);padding-top:20px;display:flex;align-items:flex-end;justify-content:space-between;gap:10px}
.price small{display:block;color:var(--muted);font-size:11px;font-weight:500}
.price strong{font-family:"Manrope";font-size:20px;color:#e48e1e;font-weight:800}
.service-card {
    cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}
.service-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(58, 105, 50, 0.35);
}
.service-card h3 a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}
.service-card h3 a:hover {
    color: var(--green);
}
.service-card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-detail-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--green);
    background: #fff;
    border: 1px solid var(--line);
    padding: 10px 14px;
    border-radius: 12px;
    text-decoration: none;
    transition: var(--ease);
}
.btn-detail-link:hover {
    background: var(--green-soft);
    border-color: var(--green);
    color: var(--green);
    transform: translateY(-1px);
}
.add-btn{
    width:42px;height:42px;border-radius:12px;border:1px solid var(--line);
    color:var(--green);background:var(--green-soft);display:grid;place-items:center;transition:var(--ease)
}
.add-btn:hover{background:var(--green);color:#fff;border-color:var(--green);transform:scale(1.05)}
.service-card.hidden{display:none}

/* portfolio */
.portfolio-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.portfolio-card{
    background:#fff;border:1px solid var(--line);border-radius:24px;overflow:hidden;
    box-shadow:var(--shadow-sm);transition:var(--ease)
}
.portfolio-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.partner-cover{
    height:190px;background:linear-gradient(135deg,#edf3df,#fafcf6);
    position:relative;display:grid;place-items:center;overflow:hidden
}
.partner-cover img{width:100%;height:100%;object-fit:cover}
.image-placeholder{
    width:80px;height:80px;border-radius:28px;background:rgba(255,255,255,0.85);
    display:grid;place-items:center;color:var(--green);font-size:28px;
    backdrop-filter:blur(8px);box-shadow:var(--shadow-sm)
}
.partner-info{padding:22px 22px 0}
.partner-meta{font-size:11px;color:var(--green);font-weight:800;text-transform:uppercase;letter-spacing:.08em}
.partner-info h3{font-size:20px;margin:6px 0 8px;color:var(--ink)}
.partner-info p{font-size:13px;color:var(--muted)}
.work-strip{display:flex;gap:10px;overflow:hidden;padding:20px 22px 18px}
.work-thumb{
    flex:0 0 90px;height:68px;border-radius:12px;background:#f5f7ef;
    display:grid;place-items:center;color:#94a3b8;font-size:12px;overflow:hidden;
    border:1px solid var(--line)
}
.work-thumb img{width:100%;height:100%;object-fit:cover}
.partner-socials{
    display:flex;gap:10px;padding:0 22px 22px
}
.social{
    width:36px;height:36px;border-radius:10px;background:#f5f7ef;color:var(--green);
    display:grid;place-items:center;font-size:15px;transition:var(--ease)
}
.social:hover{background:var(--green);color:#fff;transform:translateY(-2px)}

/* workflow */
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.step{background:#fff;border:1px solid var(--line);border-radius:20px;padding:26px;box-shadow:var(--shadow-sm);transition:var(--ease)}
.step:hover{transform:translateY(-4px);box-shadow:var(--shadow)}
.step-no{
    width:38px;height:38px;border-radius:50%;background:#e48e1e;
    color:var(--white);display:grid;place-items:center;font-weight:800;font-size:13px;margin-bottom:20px;
    border:1px solid rgba(46,107,52,0.15)
}
.step h3{font-size:17px;margin-bottom:8px;color:var(--ink)}
.step p{font-size:13px;color:var(--muted);line-height:1.6}

/* consultation */
.consult{
    background:linear-gradient(135deg, var(--green-dark), var(--green));
    color:#fff;border-radius:36px;padding:60px;
    display:grid;grid-template-columns:.9fr 1.1fr;gap:56px;align-items:start;
    box-shadow:0 25px 50px -12px rgba(30,71,34,0.3);position:relative;overflow:hidden
}
.consult h2{font-size:38px;margin:12px 0 16px;color:#fff}
.consult-copy{color:rgba(255,255,255,0.85);font-size:15px;line-height:1.7}
.consult-points{list-style:none;display:grid;gap:12px;margin-top:28px}
.consult-points li{font-size:14px;display:flex;gap:12px;align-items:flex-start}
.consult-points i{color:var(--lime);margin-top:4px;font-size:15px}
.form-card{background:#fff;color:var(--ink);padding:32px;border-radius:24px;box-shadow:var(--shadow-lg)}
.form-card h3{font-size:22px;margin-bottom:20px;color:var(--ink)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:12px;font-weight:700;display:block;margin-bottom:8px;color:#334155}
.form-control{
    width:100%;border:1px solid var(--line);border-radius:12px;
    background:#f8faf6;padding:12px 14px;outline:0;font-size:13.5px;
    transition:var(--ease);color:var(--ink)
}
.form-control:focus{border-color:var(--green);background:#fff;box-shadow:0 0 0 4px rgba(46,107,52,0.12)}
textarea.form-control{resize:vertical;min-height:100px}
.form-submit{width:100%;margin-top:16px;padding:16px}

/* cart drawer */
.overlay{
    position:fixed;inset:0;background:rgba(15,23,42,0.4);
    backdrop-filter:blur(4px);
    opacity:0;pointer-events:none;transition:var(--ease);z-index:1100
}
.overlay.show{opacity:1;pointer-events:auto}
.cart-drawer{
    position:fixed;right:0;top:0;height:100vh;width:min(440px,92vw);
    background:#fff;z-index:1200;transform:translateX(100%);transition:transform .35s cubic-bezier(0.4, 0, 0.2, 1);
    display:flex;flex-direction:column;box-shadow:-20px 0 50px rgba(0,0,0,0.15)
}
.cart-drawer.open{transform:translateX(0)}
.cart-head{padding:24px 26px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between}
.cart-head h3{font-size:22px;color:var(--ink)}
.close-cart{border:0;background:#f5f7ef;color:var(--green);width:38px;height:38px;border-radius:10px;transition:var(--ease)}
.close-cart:hover{background:var(--green-soft);transform:scale(1.05)}
.cart-body{flex:1;overflow:auto;padding:24px}
.cart-category{margin-bottom:24px}
.cart-category h4{
    font-size:11.5px;color:var(--green);text-transform:uppercase;letter-spacing:.12em;
    padding-bottom:10px;border-bottom:1px solid var(--line);margin-bottom:12px;font-weight:800
}
.cart-item{display:grid;grid-template-columns:1fr auto;gap:14px;padding:14px 0;border-bottom:1px solid #f5f7ef}
.cart-item strong{font-size:14px;display:block;color:var(--ink)}
.cart-item small{font-size:12px;color:var(--muted)}
.qty{display:flex;align-items:center;gap:8px}
.qty button{
    border:1px solid var(--line);background:#fff;width:28px;height:28px;border-radius:8px;color:var(--green);
    font-weight:700;transition:var(--ease)
}
.qty button:hover{background:var(--green-soft);border-color:var(--green)}
.qty span{font-size:13px;font-weight:800;min-width:18px;text-align:center}
.cart-empty{text-align:center;color:var(--muted);padding:60px 20px}
.cart-empty i{font-size:40px;color:#cbd5e1;margin-bottom:16px}
.cart-foot{padding:22px 24px;border-top:1px solid var(--line);background:#fff}
.cart-total{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.cart-total span{font-size:13px;color:var(--muted)}
.cart-total strong{font-family:"Manrope";color:var(--green-dark);font-size:22px;font-weight:800}

/* toast */
.toast{
    position:fixed;left:50%;bottom:28px;transform:translate(-50%,20px);
    background:#0f172a;color:#fff;padding:14px 22px;border-radius:14px;font-size:13px;
    opacity:0;pointer-events:none;transition:var(--ease);z-index:1500;box-shadow:var(--shadow-lg);
    border:1px solid rgba(255,255,255,0.1)
}
.toast.show{opacity:1;transform:translate(-50%,0)}

/* Popup konfirmasi logout */
.logout-modal {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 12, 0.48);
    backdrop-filter: blur(5px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}

.logout-modal.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.logout-dialog {
    width: min(100%, 420px);
    padding: 32px;
    border: 1px solid #e8eddf;
    border-radius: 24px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.18);
    text-align: center;
    transform: translateY(12px) scale(0.98);
    transition: transform 0.22s ease;
}

.logout-modal.show .logout-dialog {
    transform: translateY(0) scale(1);
}

.logout-icon {
    display: grid;
    place-items: center;
    width: 58px;
    height: 58px;
    margin: 0 auto 20px;
    border-radius: 18px;
    background: #f0f5e7;
    color: #5b8121;
    font-size: 23px;
}

.logout-dialog h2 {
    margin-bottom: 12px;
    color: #1d2a12;
    font-size: 24px;
}

.logout-dialog p {
    margin-bottom: 26px;
    color: #64705b;
    font-size: 14px;
    line-height: 1.7;
}

.logout-actions {
    display: flex;
    gap: 12px;
}

.logout-actions button {
    flex: 1;
    min-height: 46px;
    padding: 12px 15px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.2s ease;
}

.logout-cancel {
    border: 1px solid #dce4d1;
    background: #fff;
    color: #405b18;
}

.logout-cancel:hover {
    background: #f5f7ef;
}

.logout-confirm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    border: 1px solid #5b8121;
    background: #5b8121;
    color: #fff;
}

.logout-confirm:hover {
    border-color: #405b18;
    background: #405b18;
}

@media (max-width: 420px) {
    .logout-dialog {
        padding: 26px 20px;
    }

    .logout-actions {
        flex-direction: column-reverse;
    }
}

/* footer */
footer{background:#0b140c;color:rgba(255,255,255,0.7);padding:64px 0 28px}
.footer-grid{display:grid;grid-template-columns:1.3fr .7fr .8fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,0.08)}
.footer-brand img{max-height:40px;max-width:180px;margin-bottom:18px;background:#fff;padding:6px 10px;border-radius:10px}
.footer-brand p{font-size:13px;max-width:400px;line-height:1.7}
footer h4{color:#fff;font-size:14px;margin-bottom:16px;letter-spacing:-0.01em}
.footer-links{display:grid;gap:10px;font-size:13px}
.footer-links a{transition:var(--ease)}
.footer-links a:hover{color:#fff}
.footer-bottom{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;padding-top:24px;font-size:12px;color:rgba(255,255,255,0.5)}

/* responsive */
@media(max-width:980px){
    .nav-menu{gap:20px}
    .nav-actions .btn{display:none}
    .hero-grid{grid-template-columns:1fr;gap:40px}
    .hero h1{font-size:46px}
    .hero-visual{min-height:360px}
    .service-grid,.portfolio-grid{grid-template-columns:1fr 1fr}
    .steps{grid-template-columns:1fr 1fr}
    .consult{grid-template-columns:1fr;padding:40px}
}
@media(max-width:720px){
    .section{padding:72px 0}
    .navbar{height:70px}
    .nav-menu{display:none;position:absolute;left:4%;right:4%;top:75px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:var(--shadow-lg)}
    .nav-menu.open{display:grid;gap:8px}
    .nav-link{display:block;padding:10px 8px}
    .nav-link:after{display:none}
    .menu-toggle{display:block}
    .logo{width:125px}
    .hero{padding:64px 0}
    .hero h1{font-size:36px}
    .hero-copy{font-size:15px}
    .hero-visual{min-height:330px;padding:24px}
    .section-head h2,.services-top .section-head h2{font-size:30px}
    .services-top{display:block}
    .service-controls{align-items:stretch;min-width:0;margin-top:22px}
    .category-tabs{justify-content:flex-start}
    .service-grid,.portfolio-grid,.problem-inner{grid-template-columns:1fr}
    .problem-inner{padding:0 20px 20px 20px}
    .steps{grid-template-columns:1fr}
    .consult{padding:32px 20px;border-radius:24px}
    .consult h2{font-size:30px}
    .form-grid{grid-template-columns:1fr}
    .form-group.full{grid-column:auto}
    .footer-grid{grid-template-columns:1fr}
}


/* Fitur yang hanya tersedia setelah login */
.auth-only {
    display: none !important;
}

/* Tombol keranjang */
body.logged-in .cart-btn.auth-only {
    display: grid !important;
}


/* Tombol konsultasi disembunyikan secara default */
.btn-consult-auth {
    display: none !important;
}

/* Tampilkan hanya jika pengguna sudah login */
body.logged-in .btn-consult-auth {
    display: inline-flex !important;
}


/* Form konsultasi */
.auth-only-section {
    display: none;
}

body.logged-in .auth-only-section {
    display: block;
}

/* ========================================================
   SECTION TENTANG KAMI (MODERN & REFINED)
   ======================================================== */
.about-section {
    background: #ffffff;
    position: relative;
    overflow: hidden;
}
.about-hero-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 36px;
    align-items: stretch;
    margin-bottom: 36px;
}
.about-story-card {
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
    padding: 36px;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: var(--ease);
}
.about-story-card:hover {
    box-shadow: var(--shadow);
    border-color: rgba(91, 129, 33, 0.25);
}
.story-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--green);
    background: var(--green-soft);
    padding: 6px 14px;
    border-radius: 999px;
    margin-bottom: 16px;
    align-self: flex-start;
}
.about-story-card h3 {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
    margin-bottom: 14px;
    letter-spacing: -0.02em;
}
.story-text {
    font-size: 14.5px;
    color: #475569;
    line-height: 1.8;
    margin-bottom: 20px;
}
.about-quote-box {
    background: #f8fafc;
    border-left: 3.5px solid var(--green);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    padding: 14px 18px;
    margin-bottom: 22px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.about-quote-box .quote-icon {
    font-size: 18px;
    color: var(--green);
    opacity: 0.7;
    margin-top: 2px;
    flex-shrink: 0;
}
.about-quote-box p {
    font-size: 13px;
    font-style: italic;
    color: #334155;
    line-height: 1.6;
    margin: 0;
}
.about-values-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 18px;
    border-top: 1px solid #f1f5f9;
}
.about-value-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--green-soft);
    color: var(--green-dark);
    font-size: 12px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 99px;
    border: 1px solid rgba(91, 129, 33, 0.15);
}
.about-value-pill i {
    font-size: 11px;
    color: var(--green);
}

/* Visual Column */
.about-visual-card {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.about-img-frame {
    position: relative;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
    background: #e2e8f0;
    flex: 1;
    min-height: 290px;
}
.about-img {
    width: 100%;
    height: 100%;
    min-height: 290px;
    max-height: 380px;
    object-fit: cover;
    display: block;
    transition: transform 0.5s ease;
}
.about-img-frame:hover .about-img {
    transform: scale(1.03);
}
.about-img-tag-top {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.about-img-overlay-card {
    position: absolute;
    bottom: 16px;
    left: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 16px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}
.about-overlay-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--green-soft);
    color: var(--green);
    display: grid;
    place-items: center;
    font-size: 18px;
    flex-shrink: 0;
}
.about-img-overlay-card strong {
    display: block;
    font-size: 13px;
    color: #0f172a;
    font-weight: 700;
}
.about-img-overlay-card small {
    display: block;
    font-size: 11px;
    color: #64748b;
    margin-top: 1px;
}
.about-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.about-stat-item {
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 14px 10px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    transition: var(--ease);
}
.about-stat-item:hover {
    border-color: rgba(91, 129, 33, 0.3);
    transform: translateY(-2px);
}
.stat-number {
    display: block;
    font-family: "Manrope", sans-serif;
    font-size: 18px;
    font-weight: 800;
    color: var(--green);
    margin-bottom: 2px;
}
.stat-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    line-height: 1.35;
    display: block;
}

/* Problem vs Solution Bento Grid */
.about-problem-solution-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
    margin-bottom: 30px;
}
.problem-solution-card {
    border-radius: var(--radius-lg);
    padding: 32px;
    border: 1.5px solid var(--line);
    background: #ffffff;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    transition: var(--ease);
}
.problem-solution-card:hover {
    box-shadow: var(--shadow);
}
.problem-card-theme {
    background: linear-gradient(180deg, #ffffff 0%, #fffcf8 100%);
    border-color: #fed7aa;
}
.problem-card-theme:hover {
    border-color: #f97316;
}
.solution-card-theme {
    background: linear-gradient(180deg, #ffffff 0%, #f9fbf6 100%);
    border-color: rgba(91, 129, 33, 0.25);
}
.solution-card-theme:hover {
    border-color: var(--green);
}
.ps-card-header {
    margin-bottom: 20px;
}
.ps-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    padding: 5px 12px;
    border-radius: 99px;
    margin-bottom: 12px;
}
.warning-badge {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.success-badge {
    background: var(--green-soft);
    color: var(--green);
    border: 1px solid rgba(91, 129, 33, 0.25);
}
.problem-solution-card h4 {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 8px;
}
.ps-desc {
    font-size: 13.5px;
    color: #64748b;
    line-height: 1.65;
    margin-bottom: 0;
}
.ps-items-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
    padding-top: 18px;
}
.ps-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 14px;
    transition: var(--ease);
}
.problem-item-card {
    background: #ffffff;
    border: 1px solid #ffedd5;
}
.solution-item-card {
    background: #ffffff;
    border: 1px solid rgba(91, 129, 33, 0.15);
}
.ps-item:hover {
    transform: translateX(3px);
}
.ps-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 14px;
    flex-shrink: 0;
}
.warning-icon {
    background: #fff7ed;
    color: #ea580c;
}
.success-icon {
    background: var(--green-soft);
    color: var(--green);
}
.ps-text strong {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
}
.ps-text p {
    font-size: 12px;
    color: #64748b;
    line-height: 1.5;
    margin: 0;
}

/* Bottom CTA Bar */
.about-cta-bar {
    background: #f8fafc;
    border: 1.5px solid var(--line);
    border-radius: var(--radius);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.about-cta-info {
    display: flex;
    align-items: center;
    gap: 14px;
}
.about-cta-info i {
    font-size: 24px;
    color: var(--green);
}
.about-cta-info strong {
    display: block;
    font-size: 13.5px;
    color: #0f172a;
    font-weight: 700;
}
.about-cta-info span {
    font-size: 12px;
    color: #64748b;
}
.about-cta-btns {
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 900px) {
    .about-hero-grid { grid-template-columns: 1fr; gap: 24px; }
    .about-problem-solution-grid { grid-template-columns: 1fr; gap: 20px; }
    .about-cta-bar { flex-direction: column; align-items: flex-start; }
    .about-cta-btns { width: 100%; justify-content: flex-start; }
}

/* ========================================================
   HARGA TERKUNCI & LOGIN MITRA DI KATALOG
   ======================================================== */
.price-locked {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.price-locked .lock-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px dashed #cbd5e1;
    width: fit-content;
}
.price-locked small {
    font-size: 11px;
    color: var(--muted);
}
.btn-lock-login {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--green-soft);
    color: var(--green);
    border: 1.5px solid var(--green);
    border-radius: 12px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: var(--ease);
}
.btn-lock-login:hover {
    background: var(--green);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(91, 129, 33, 0.25);
}

/* ========================================================
   SECTION AJAKAN LOGIN / BANNER CTA
   ======================================================== */
.cta-section {
    padding: 40px 0 90px;
}
.cta-banner-wrapper {
    position: relative;
    background: linear-gradient(135deg, #1d2a12 0%, #2f4514 45%, #5b8121 100%);
    border-radius: 32px;
    padding: 68px 48px;
    color: #ffffff;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(46, 107, 52, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.cta-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    pointer-events: none;
    opacity: 0.45;
}
.cta-orb-1 {
    top: -60px;
    right: -60px;
    width: 260px;
    height: 260px;
    background: #a3e635;
    animation: ctaPulse 6s ease-in-out infinite alternate;
}
.cta-orb-2 {
    bottom: -60px;
    left: -40px;
    width: 220px;
    height: 220px;
    background: #e48e1e;
    animation: ctaPulse 8s ease-in-out infinite alternate-reverse;
}
@keyframes ctaPulse {
    0% { transform: scale(0.9) translate(0, 0); opacity: 0.3; }
    100% { transform: scale(1.15) translate(20px, -20px); opacity: 0.6; }
}
.cta-float-badge {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 9px 18px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #ffffff;
    box-shadow: 0 8px 22px rgba(0,0,0,0.18);
    z-index: 2;
}
.cta-float-badge.float-top-left {
    top: 32px;
    left: 40px;
    animation: ctaFloat1 5s ease-in-out infinite;
}
.cta-float-badge.float-bottom-right {
    bottom: 32px;
    right: 40px;
    animation: ctaFloat2 6s ease-in-out infinite;
}
@keyframes ctaFloat1 {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(-1.5deg); }
}
@keyframes ctaFloat2 {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(8px) rotate(2deg); }
}
.cta-banner-content {
    position: relative;
    z-index: 3;
    max-width: 680px;
    margin: 0 auto;
    text-align: center;
}
.cta-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #dce9bd;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 7px 18px;
    border-radius: 999px;
    margin-bottom: 22px;
}
.cta-banner-content h2 {
    font-size: clamp(26px, 4vw, 38px);
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 16px;
    color: #ffffff;
}
.cta-banner-content p {
    font-size: 16px;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 32px;
}
.cta-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}
.btn-cta-main {
    background: #ffffff;
    color: var(--green-dark);
    font-weight: 800;
    font-size: 15px;
    padding: 14px 28px;
    border-radius: 14px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    transition: var(--ease);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.btn-cta-main:hover {
    background: #f0fdf4;
    transform: translateY(-3px);
    box-shadow: 0 14px 30px rgba(0,0,0,0.22);
}
.btn-cta-ghost {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    border: 1.5px solid rgba(255, 255, 255, 0.4);
    font-weight: 700;
    font-size: 15px;
    padding: 14px 28px;
    border-radius: 14px;
    transition: var(--ease);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    backdrop-filter: blur(8px);
    text-decoration: none;
}
.btn-cta-ghost:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: #ffffff;
    transform: translateY(-3px);
}
.cta-perks {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 600;
}
.cta-perks i {
    color: #a3e635;
    margin-right: 6px;
}
@media (max-width: 640px) {
    .cta-banner-wrapper { padding: 42px 24px; border-radius: 24px; }
    .cta-float-badge { display: none; }
    .btn-cta-main, .btn-cta-ghost { width: 100%; justify-content: center; }
}

/* ARTIKEL HOME SECTION */
.home-articles-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-top: 32px;
}
.home-art-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid var(--line);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: var(--ease);
}
.home-art-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    border-color: rgba(91, 129, 33, 0.3);
}
.home-art-thumb-wrap {
    position: relative;
    height: 190px;
    overflow: hidden;
    background: #e2e8f0;
    display: block;
}
.home-art-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.home-art-card:hover .home-art-thumb {
    transform: scale(1.05);
}
.home-art-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    color: var(--green);
    border: 1px solid rgba(91, 129, 33, 0.15);
}
.home-art-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.home-art-title {
    font-size: 15px;
    font-weight: 800;
    line-height: 1.35;
    margin-bottom: 8px;
    color: #0f172a;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.home-art-title a {
    color: inherit;
    text-decoration: none;
    transition: var(--ease);
}
.home-art-title a:hover {
    color: var(--green);
}
.home-art-excerpt {
    font-size: 13px;
    color: #64748b;
    line-height: 1.55;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}
.home-art-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
    font-size: 11.5px;
    color: #94a3b8;
}
.home-art-author {
    display: flex;
    align-items: center;
    gap: 7px;
}
.home-art-author-img {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
}
@media (max-width: 900px) {
    .home-articles-grid {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body class="<?= $isUserLoggedIn ? 'logged-in' : 'logged-out' ?>">

<header>
    <div class="container navbar">
        <a class="logo" href="#beranda" aria-label="Balla' Fresh">
            <!-- GANTI src DENGAN FILE LOGO PNG BALLA' FRESH -->
            <img src="logo-balla-fresh.png"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
            <span class="logo-fallback">Balla’ Fresh</span>
        </a>

        <nav class="nav-menu" id="navMenu">
            <a href="#beranda" class="nav-link active">Beranda</a>
            <a href="#tentang-kami" class="nav-link">Tentang Kami</a>
            <a href="#layanan" class="nav-link">Layanan</a>
            <?php if ($isUserLoggedIn): ?>
                <a href="artikel.php" class="nav-link">Artikel</a>
            <?php endif; ?>
            <a href="#portfolio" class="nav-link">Portofolio</a>
            <a href="#konsultasi" class="nav-link">Konsultasi</a>
        </nav>

        <div class="nav-actions">
            <button class="cart-btn" id="cartOpen" aria-label="Buka keranjang">
                <i class="fa-solid fa-bag-shopping"></i>
                <span class="cart-count" id="cartCount">0</span>
            </button>
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
            <button class="menu-toggle" id="menuToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</header>

<main>

<!-- HERO -->
<section class="hero" id="beranda">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-leaf"></i> <?= htmlspecialchars($siteContent['hero_tag'] ?? 'Digital Growth Partner') ?></span>
            <h1><?= htmlspecialchars($siteContent['hero_title'] ?? 'Lebih banyak peluang, #DariRumahAja.') ?></h1>
            <p class="hero-copy">
                <?= nl2br(htmlspecialchars($siteContent['hero_description'] ?? 'Balla’ Fresh membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan melalui solusi digital yang sesuai dengan kebutuhan usahanya.')) ?>
            </p>
            <div class="hero-actions">
                <a href="#layanan" class="btn btn-primary">Jelajahi Layanan <i class="fa-solid fa-arrow-right"></i></a>
                <a href="#tentang-kami" class="btn btn-outline">Tentang Kami</a>
            </div>
            <div class="hero-note">
                <i class="fa-solid fa-circle-check"></i>
                <span>Layanan modular yang dapat dipilih sesuai kebutuhan kebun Anda.</span>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-panel">
                <div class="panel-top">
                    <div>
                        <div class="panel-label">Balla’ Fresh Solution</div>
                        <h3 style="font-size:21px;margin-top:4px">Bangun yang memang dibutuhkan.</h3>
                    </div>
                    <span class="panel-dot"></span>
                </div>
                <?php if (!empty($siteContent['hero_image'])): ?>
                    <div style="margin-bottom:16px;border-radius:14px;overflow:hidden;max-height:160px">
                        <img src="<?= htmlspecialchars($siteContent['hero_image']) ?>" alt="Visual Balla' Fresh" style="width:100%;height:100%;object-fit:cover">
                    </div>
                <?php endif; ?>
                <div class="hero-services">
                    <div class="hero-service">
                        <div class="hero-service-icon"><i class="fa-solid fa-pen-ruler"></i></div>
                        <div><strong>Starter Branding</strong><small>Bangun identitas dasar yang siap digunakan.</small></div>
                    </div>
                    <div class="hero-service">
                        <div class="hero-service-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                        <div><strong>E-Commerce Grow</strong><small>Bangun kanal digital untuk informasi dan transaksi.</small></div>
                    </div>
                    <div class="hero-service">
                        <div class="hero-service-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <div><strong>Full Marketing Scale</strong><small>Kembangkan pemasaran digital secara berkelanjutan.</small></div>
                    </div>
                </div>
            </div>
            <div class="hero-chip"><i class="fa-solid fa-sliders"></i> Solusi Terarah & Fleksibel</div>
        </div>
    </div>
</section>

<!-- TENTANG KAMI -->
<section class="section about-section" id="tentang-kami">
    <div class="container">
        <div class="section-head text-center">
            <span class="eyebrow"><i class="fa-solid fa-leaf"></i> <?= htmlspecialchars($siteContent['about_tag'] ?? 'Tentang Balla’ Fresh') ?></span>
            <h2><?= htmlspecialchars($siteContent['about_title'] ?? 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan') ?></h2>
            <p>Membawa hasil panen terbaik hidroponik lokal Makassar melangkah lebih jauh ke pasar digital yang berdaya saing tinggi.</p>
        </div>

        <!-- 1. Story & Visual Showcase (2-Column Hero Row) -->
        <div class="about-hero-grid">
            <!-- Left: Cerita Berkembangnya Agensi -->
            <div class="about-story-card">
                <div>
                    <span class="story-pill"><i class="fa-solid fa-seedling"></i> Cerita Berkembangnya Agensi</span>
                    <h3>Berawal dari Kepedulian Nyata Petani Lokal</h3>
                    <p class="story-text"><?= nl2br(htmlspecialchars($siteContent['about_story'] ?? '')) ?></p>
                    
                    <div class="about-quote-box">
                        <i class="fa-solid fa-quote-left quote-icon"></i>
                        <p>“Kami percaya sayur hidroponik lokal bukan sekadar komoditas dapur, melainkan produk premium bernilai tinggi yang layak tampil di etalase swalayan dan pasar modern.”</p>
                    </div>
                </div>

                <div class="about-values-row">
                    <div class="about-value-pill">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>100% Fokus Hidroponik</span>
                    </div>
                    <div class="about-value-pill">
                        <i class="fa-solid fa-cubes"></i>
                        <span>Layanan Modular</span>
                    </div>
                    <div class="about-value-pill">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Basis di Makassar</span>
                    </div>
                </div>
            </div>

            <!-- Right: Dokumentasi & Key Metrics -->
            <div class="about-visual-card">
                <div class="about-img-frame">
                    <?php $aboutImg = !empty($siteContent['about_image']) ? $siteContent['about_image'] : 'Edible1_dok.jpeg'; ?>
                    <img src="<?= htmlspecialchars($aboutImg) ?>" alt="Dokumentasi Balla’ Fresh Hidroponik" class="about-img" onerror="this.src='Edible1_dok.jpeg'">
                    
                    <div class="about-img-tag-top">
                        <i class="fa-solid fa-camera"></i> Dokumentasi Nyata
                    </div>

                    <div class="about-img-overlay-card">
                        <div class="about-overlay-icon">
                            <i class="fa-solid fa-leaf"></i>
                        </div>
                        <div>
                            <strong>Pendampingan Langsung Kebun</strong>
                            <small>Membina kemasan &amp; pemasaran petani hidroponik lokal di Makassar</small>
                        </div>
                    </div>
                </div>

                <div class="about-stats-grid">
                    <div class="about-stat-item">
                        <span class="stat-number">100%</span>
                        <span class="stat-label">Spesialisasi Hidroponik</span>
                    </div>
                    <div class="about-stat-item">
                        <span class="stat-number">B2B &amp; B2C</span>
                        <span class="stat-label">Pasar Ritel &amp; Rumah Tangga</span>
                    </div>
                    <div class="about-stat-item">
                        <span class="stat-number">Modular</span>
                        <span class="stat-label">Pilih Sesuai Kebutuhan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Problem & Solution Bento Grid (Side-by-Side Comparison) -->
        <div class="about-problem-solution-grid">
            
            <!-- Left: Permasalahan Terdekat UMKM Hidroponik -->
            <div class="problem-solution-card problem-card-theme">
                <div class="ps-card-header">
                    <span class="ps-badge warning-badge"><i class="fa-solid fa-triangle-exclamation"></i> Permasalahan Terdekat UMKM Hidroponik</span>
                    <h4>Kendala Nyata yang Kerap Menghambat Usaha:</h4>
                    <p class="ps-desc"><?= nl2br(htmlspecialchars($siteContent['about_problem'] ?? '')) ?></p>
                </div>

                <div class="ps-items-list">
                    <div class="ps-item problem-item-card">
                        <div class="ps-icon-wrap warning-icon">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Ketiadaan Branding &amp; Kemasan Standar</strong>
                            <p>Sayuran segar bermutu dipasarkan polos tanpa label dan izin resmi, sehingga sulit masuk ritel modern dan nilai jualnya rendah.</p>
                        </div>
                    </div>

                    <div class="ps-item problem-item-card">
                        <div class="ps-icon-wrap warning-icon">
                            <i class="fa-solid fa-comments-dollar"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Pemesanan Manual Tidak Terstruktur</strong>
                            <p>Pencatatan order via chat WhatsApp personal sering terselip, lambat direspon, dan rawan salah kirim varian sayur.</p>
                        </div>
                    </div>

                    <div class="ps-item problem-item-card">
                        <div class="ps-icon-wrap warning-icon">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Jangkauan Pasar Hanya Sebatas Tetangga</strong>
                            <p>Keterbatasan saluran promosi membuat penjualan berputar di lingkaran terdekat saja dengan margin keuntungan yang sempit.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Solusi Terarah Balla' Fresh -->
            <div class="problem-solution-card solution-card-theme">
                <div class="ps-card-header">
                    <span class="ps-badge success-badge"><i class="fa-solid fa-shield-halved"></i> Solusi Terarah Balla’ Fresh</span>
                    <h4>Pendekatan Terpadu &amp; Teruji Siap Pakai:</h4>
                    <p class="ps-desc"><?= nl2br(htmlspecialchars($siteContent['about_solution'] ?? '')) ?></p>
                </div>

                <div class="ps-items-list">
                    <div class="ps-item solution-item-card">
                        <div class="ps-icon-wrap success-icon">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Desain Identitas &amp; Kemasan Siap Ritel</strong>
                            <p>Logo profesional, stiker kemasan kedap udara, dan identitas visual yang memikat minat beli konsumen swalayan dan kafe sehat.</p>
                        </div>
                    </div>

                    <div class="ps-item solution-item-card">
                        <div class="ps-icon-wrap success-icon">
                            <i class="fa-solid fa-laptop-code"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Katalog Digital &amp; Format Pemesanan Cepat</strong>
                            <p>Katalog produk terstruktur yang memudahkan mitra memilih layanan dan mendigitalisasi rekap order harian tanpa repot.</p>
                        </div>
                    </div>

                    <div class="ps-item solution-item-card">
                        <div class="ps-icon-wrap success-icon">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div class="ps-text">
                            <strong>Pemasaran Digital &amp; Penetrasi Pasar B2B</strong>
                            <p>Strategi konten media sosial serta pendampingan untuk menghubungkan kebun langsung dengan restoran dan pelanggan langganan.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- 3. Bottom Action Banner -->
        <div class="about-cta-bar">
            <div class="about-cta-info">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Punya kendala serupa pada kebun hidroponik Anda?</strong>
                    <span>Konsultasikan langsung bersama tim kami untuk menemukan solusi digital dan kemasan yang tepat sasaran.</span>
                </div>
            </div>
            <div class="about-cta-btns">
                <a href="#solusi" class="btn btn-outline" style="border-radius:99px;font-size:13px;padding:9px 18px">Eksplor Solusi</a>
                <a href="#layanan" class="btn btn-outline" style="border-radius:99px;font-size:13px;padding:9px 18px">Katalog Layanan</a>
                <a href="#konsultasi" class="btn btn-primary" style="border-radius:99px;font-size:13px;padding:9px 20px">Mulai Konsultasi <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>

    </div>
</section>

<!-- SOLUTION -->
<section class="section" id="solusi">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Hydroponic Business Solution</span>
            <h2>Mulai dari kendalanya, bukan dari paketnya.</h2>
            <p>Setiap usaha punya kebutuhan yang berbeda. Pilih kendala yang paling dekat dengan kondisi usaha Anda untuk melihat solusi Balla’ Fresh.</p>
        </div>

        <div class="problem-wrap">
            <div class="problem-item open">
                <button class="problem-trigger">
                    <div class="problem-icon"><i class="fa-solid fa-bullseye"></i></div>
                    <div class="problem-title">
                        <strong>Jangkauan pemasaran masih terbatas</strong>
                        <span>Masih bergantung pada pelanggan sekitar, media sosial, atau jaringan pribadi.</span>
                    </div>
                    <i class="fa-solid fa-chevron-down problem-chevron"></i>
                </button>
                <div class="problem-answer">
                    <div class="problem-inner">
                        <div class="answer-box problem"><small>Kendala</small><p>Produk sudah tersedia, tetapi jangkauan pemasaran belum maksimal dan kehadiran digital belum dimanfaatkan secara optimal.</p></div>
                        <div class="answer-box solution"><small>Solusi Balla’ Fresh</small><p>Strategi digital marketing, content marketing, dan optimasi kanal digital untuk membantu produk lebih mudah ditemukan oleh pasar yang dituju.</p></div>
                    </div>
                </div>
            </div>

            <div class="problem-item">
                <button class="problem-trigger">
                    <div class="problem-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                    <div class="problem-title">
                        <strong>Belum memiliki kanal penjualan digital yang terstruktur</strong>
                        <span>Pemesanan dan informasi produk masih tersebar di beberapa kanal.</span>
                    </div>
                    <i class="fa-solid fa-chevron-down problem-chevron"></i>
                </button>
                <div class="problem-answer">
                    <div class="problem-inner">
                        <div class="answer-box problem"><small>Kendala</small><p>Informasi produk dan proses pemesanan masih dilakukan secara manual melalui media sosial atau WhatsApp sehingga belum terpusat.</p></div>
                        <div class="answer-box solution"><small>Solusi Balla’ Fresh</small><p>Pengembangan website e-commerce sebagai kanal digital yang menghubungkan informasi produk, edukasi, katalog, dan transaksi.</p></div>
                    </div>
                </div>
            </div>

            <div class="problem-item">
                <button class="problem-trigger">
                    <div class="problem-icon"><i class="fa-solid fa-message"></i></div>
                    <div class="problem-title">
                        <strong>Nilai dan keunikan produk belum tersampaikan dengan baik</strong>
                        <span>Produk ada, tetapi calon pembeli belum banyak memahami nilai di baliknya.</span>
                    </div>
                    <i class="fa-solid fa-chevron-down problem-chevron"></i>
                </button>
                <div class="problem-answer">
                    <div class="problem-inner">
                        <div class="answer-box problem"><small>Kendala</small><p>Informasi mengenai karakteristik produk, proses budidaya, dan cerita usaha belum dikomunikasikan secara konsisten.</p></div>
                        <div class="answer-box solution"><small>Solusi Balla’ Fresh</small><p>Product storytelling dan konten edukasi yang disesuaikan dengan karakter setiap mitra agar produk lebih mudah dipahami.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SERVICES / ECOMMERCE -->
<section class="section alt" id="layanan">
    <div class="container">
        <div class="services-top">
            <div class="section-head">
                <span class="eyebrow">Service E-Commerce</span>
                <h2>Pilih layanan sesuai kebutuhan.</h2>
                <p>Tidak semua usaha membutuhkan layanan yang sama. Pilih yang benar-benar Anda butuhkan dan susun sesuai kebutuhan bisnis Anda.</p>
            </div>
            <div class="service-controls">
                <label class="service-search" for="serviceSearch">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="serviceSearch" placeholder="Cari layanan..." autocomplete="off">
                </label>
                <div class="category-tabs" role="group" aria-label="Filter kategori layanan">
                    <button class="category-tab active" type="button" data-filter="all">Semua</button>
                    <button class="category-tab" type="button" data-filter="branding">Starter Branding</button>
                    <button class="category-tab" type="button" data-filter="ecommerce">E-Commerce Grow</button>
                    <button class="category-tab" type="button" data-filter="marketing">Full Marketing Scale</button>
                </div>
            </div>
        </div>

        <div class="service-grid" id="services-container">
            <!-- STARTER BRANDING -->
            <article class="service-card" data-category="branding" data-id="logo" data-name="Desain Logo" data-price="750000" data-duration="3–5 hari">
                <div class="service-tag">Starter Branding</div>
                <h3>Desain Logo</h3>
                <p>Logo dasar yang menyesuaikan karakter usaha hidroponik dan siap digunakan pada kanal digital.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Konsep visual</li>
                    <li><i class="fa-solid fa-check"></i> Logo utama & variasi</li>
                    <li><i class="fa-solid fa-check"></i> File siap pakai</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 3–5 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp750.000</strong></div>
                        <div class="service-card-actions">
                            <a href="detail_layanan.php?id=1" class="btn-detail-link" onclick="event.stopPropagation()">Detail <i class="fa-solid fa-arrow-right"></i></a>
                            <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Desain Logo"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <div class="service-card-actions">
                            <a href="detail_layanan.php?id=1" class="btn-detail-link" onclick="event.stopPropagation()">Detail <i class="fa-solid fa-arrow-right"></i></a>
                            <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                                <i class="fa-solid fa-lock"></i> Pesan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="branding" data-id="packaging" data-name="Stiker Kemasan" data-price="350000" data-duration="2–4 hari">
                <div class="service-tag">Starter Branding</div>
                <h3>Stiker Kemasan</h3>
                <p>Desain stiker kemasan untuk membantu produk terlihat lebih rapi dan memiliki identitas.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Desain stiker</li>
                    <li><i class="fa-solid fa-check"></i> Penyesuaian ukuran</li>
                    <li><i class="fa-solid fa-check"></i> File siap cetak</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 2–4 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp350.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Stiker Kemasan"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="branding" data-id="social-profile" data-name="Setup Profil Sosial Media" data-price="400000" data-duration="2–3 hari">
                <div class="service-tag">Starter Branding</div>
                <h3>Setup Profil Sosial Media</h3>
                <p>Penataan profil Instagram/TikTok agar informasi usaha lebih jelas dan konsisten.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Bio & informasi usaha</li>
                    <li><i class="fa-solid fa-check"></i> Struktur profil</li>
                    <li><i class="fa-solid fa-check"></i> Basic visual direction</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 2–3 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp400.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Setup Profil"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <!-- E-COMMERCE GROW -->
            <article class="service-card" data-category="ecommerce" data-id="web" data-name="Website E-Commerce" data-price="3000000" data-duration="7–14 hari">
                <div class="service-tag">E-Commerce Grow</div>
                <h3>Website E-Commerce</h3>
                <p>Website sebagai kanal informasi dan penjualan digital yang disesuaikan dengan kebutuhan usaha.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Katalog produk</li>
                    <li><i class="fa-solid fa-check"></i> Checkout & transaksi</li>
                    <li><i class="fa-solid fa-check"></i> Struktur halaman responsif</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 7–14 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp3.000.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Website E-Commerce"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="ecommerce" data-id="catalog" data-name="Katalog Produk Digital" data-price="600000" data-duration="3–5 hari">
                <div class="service-tag">E-Commerce Grow</div>
                <h3>Katalog Produk Digital</h3>
                <p>Struktur katalog yang membantu calon pembeli memahami produk, harga, dan informasi penting lainnya.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Struktur kategori</li>
                    <li><i class="fa-solid fa-check"></i> Detail produk</li>
                    <li><i class="fa-solid fa-check"></i> Product information</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 3–5 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp600.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Katalog Produk"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="ecommerce" data-id="maintenance" data-name="Website Maintenance" data-price="500000" data-duration="1–3 hari / request">
                <div class="service-tag">E-Commerce Grow</div>
                <h3>Website Maintenance</h3>
                <p>Pemeliharaan dan pembaruan website setelah platform digunakan oleh mitra.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Update konten</li>
                    <li><i class="fa-solid fa-check"></i> Pemeriksaan dasar</li>
                    <li><i class="fa-solid fa-check"></i> Perbaikan minor</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 1–3 hari / request</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp500.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Maintenance"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <!-- FULL MARKETING SCALE -->
            <article class="service-card" data-category="marketing" data-id="content" data-name="Produksi Konten" data-price="1000000" data-duration="5–7 hari">
                <div class="service-tag">Full Marketing Scale</div>
                <h3>Produksi Konten</h3>
                <p>Konten visual, edukasi, dan storytelling yang disesuaikan dengan produk serta karakter usaha.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Content planning</li>
                    <li><i class="fa-solid fa-check"></i> Desain / video sesuai brief</li>
                    <li><i class="fa-solid fa-check"></i> Product storytelling</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 5–7 hari</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari</small><strong>Rp1.000.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Produksi Konten"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="marketing" data-id="social-management" data-name="Social Media Management" data-price="1500000" data-duration="7 hari setup + berjalan bulanan">
                <div class="service-tag">Full Marketing Scale</div>
                <h3>Social Media Management</h3>
                <p>Pengelolaan media sosial secara berkala untuk menjaga kehadiran digital dan komunikasi dengan audiens.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Content planning</li>
                    <li><i class="fa-solid fa-check"></i> Scheduling & publishing</li>
                    <li><i class="fa-solid fa-check"></i> Evaluasi performa</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 7 hari setup + berjalan bulanan</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari / bulan</small><strong>Rp1.500.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Social Media Management"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <article class="service-card" data-category="marketing" data-id="ads" data-name="Ads Management" data-price="1000000" data-duration="3–5 hari setup + monitoring">
                <div class="service-tag">Full Marketing Scale</div>
                <h3>Ads Management</h3>
                <p>Pengelolaan kampanye iklan digital untuk membantu menjangkau audiens yang dituju.</p>
                <ul class="service-list">
                    <li><i class="fa-solid fa-check"></i> Campaign setup</li>
                    <li><i class="fa-solid fa-check"></i> Monitoring</li>
                    <li><i class="fa-solid fa-check"></i> Performance report</li>
                </ul>
                <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: 3–5 hari setup + monitoring</div>
                <div class="service-bottom">
                    <?php if ($isUserLoggedIn): ?>
                        <div class="price"><small>Mulai dari / bulan</small><strong>Rp1.000.000</strong></div>
                        <button class="add-btn" onclick="addToCart(this, event)" aria-label="Tambah Ads Management"><i class="fa-solid fa-plus"></i></button>
                    <?php else: ?>
                        <div class="price price-locked">
                            <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                            <small>Login untuk melihat penawaran</small>
                        </div>
                        <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                            <i class="fa-solid fa-lock"></i> Pesan
                        </a>
                    <?php endif; ?>
                </div>
            </article>

        </div>
    </div>
</section>

<!-- PORTFOLIO -->
<section class="section" id="portfolio">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Experience</span>
            <h2>Jejak Pengembangan Digital UMKM</h2>
            <p>Sebelum berfokus pada UMKM hidroponik, Balla’ Fresh telah terlibat dalam pengembangan dan penguatan berbagai bisnis UMKM melalui pendekatan digital.</p>
        </div>

        <div class="portfolio-grid">

            <!-- PARTNER 1 -->
            <article class="portfolio-card">
                <div class="partner-cover">
                    <!-- GANTI dengan foto profil mitra -->
                    <img src="EdibleStraw_Logo.jpeg" alt="Profil Mitra 1"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                    <div class="image-placeholder" style="display:none"><i class="fa-solid fa-seedling"></i></div>
                </div>
                <div class="partner-info">
                    <span class="partner-meta">Mitra 01 · Makassar</span>
                    <h3>Edible.Straw</h3>
                    <p>Inovasi Sedotan yang Dapat Dimakan sebagai Alternatif Ramah Lingkungan bagi Industri F&B di Kota Makassar</p>
                </div>
                <div class="work-strip">
                    <div class="work-thumb"><img src="Edible1_dok.jpeg" alt="Hasil kerja 1" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Edible2_dok.jpeg" alt="Hasil kerja 2" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Edible3_dok.jpeg" alt="Hasil kerja 3" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                </div>
                <div class="partner-socials">
                    <!-- GANTI href dengan akun mitra -->
                    <a class="social" href="https://www.instagram.com/edible.straww?stkn=MTNzYXZ1YXlmYmt3bg==" target="_blank" aria-label="Instagram mitra"><i class="fa-brands fa-instagram"></i></a>
                    <a class="social" href="https://www.tiktok.com/@edible.straw?_r=1&_t=ZS-99kzE8x0xhE" target="_blank" aria-label="TikTok mitra"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </article>

            <!-- PARTNER 2 -->
            <article class="portfolio-card">
                <div class="partner-cover">
                    <img src="Pouch_Logo.jpeg" alt="Profil Mitra 2"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                    <div class="image-placeholder" style="display:none"><i class="fa-solid fa-leaf"></i></div>
                </div>
                <div class="partner-info">
                    <span class="partner-meta">Mitra 02 · Makassar</span>
                    <h3>PouchClix</h3>
                    <p>Pouch serbaguna dengan menggunakan penutup magnet, jadi tidak ada lagi drama resleting macet</p>
                </div>
                <div class="work-strip">
                    <div class="work-thumb"><img src="Pouch1_dok.jpeg" alt="Hasil kerja 1" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Pouch2_dok.jpeg" alt="Hasil kerja 2" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Pouch3_dok.jpeg" alt="Hasil kerja 3" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                </div>
                <div class="partner-socials">
                    <a class="social" href="https://www.instagram.com/pouchclix.id?stkn=aDVtMGQ3eTRtZTh6" target="_blank" aria-label="Instagram mitra"><i class="fa-brands fa-instagram"></i></a>
                    <a class="social" href="https://www.tiktok.com/@pouchclix.id?_r=1&_t=ZS-99kzLNZAHbQ" target="_blank" aria-label="TikTok mitra"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </article>

            <!-- PARTNER 3 -->
            <article class="portfolio-card">
                <div class="partner-cover">
                    <img src="TUMBWARP_Logo.jpeg" alt="Profil Mitra 3"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                    <div class="image-placeholder" style="display:none"><i class="fa-solid fa-spa"></i></div>
                </div>
                <div class="partner-info">
                    <span class="partner-meta">Mitra 03 · Makassar</span>
                    <h3>Tumbwarp.id</h3>
                    <p>Sarung pelindung praktis yang dirancang untuk menjaga tumbler tetap aman dan mudah dibawa.
</p>
                </div>
                <div class="work-strip">
                    <div class="work-thumb"><img src="Tumb1_dok.jpeg" alt="Hasil kerja 1" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Tumb2_dok.jpeg" alt="Hasil kerja 2" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                    <div class="work-thumb"><img src="Tumb3_dok.jpeg" alt="Hasil kerja 3" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa-regular fa-image&quot;></i>'"></div>
                </div>
                <div class="partner-socials">
                    <a class="social" href="https://www.instagram.com/tumbwarp.id?stkn=ZnBvcnNkd3BxcHox" target="_blank" aria-label="Instagram mitra"><i class="fa-brands fa-instagram"></i></a>
                    <a class="social" href="https://www.tiktok.com/@tumbwarp.id?_r=1&_t=ZS-99kz8kVK1On" target="_blank" aria-label="TikTok mitra"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </article>

        </div>
    </div>
</section>

<!-- WORKFLOW -->
<section class="section alt" id="alur">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Workflow</span>
            <h2>Dari kebutuhan sampai solusi.</h2>
            <p>Alur website mengikuti proses B2B Balla’ Fresh: calon mitra mengenal, memahami, memilih, lalu memesan layanan.</p>
        </div>

        <div class="steps">
            <div class="step"><div class="step-no">01</div><h3>Kenali Kebutuhan</h3><p>Calon mitra melihat kendala usaha dan solusi yang relevan.</p></div>
            <div class="step"><div class="step-no">02</div><h3>Pilih Layanan</h3><p>Mitra memilih layanan satu per satu sesuai kebutuhan, tanpa harus mengambil seluruh paket.</p></div>
            <div class="step"><div class="step-no">03</div><h3>Masukkan Keranjang</h3><p>Layanan dari kategori berbeda dapat digabungkan dalam satu pesanan.</p></div>
            <div class="step"><div class="step-no">04</div><h3>Konsultasi & Konfirmasi</h3><p>Tim Balla’ Fresh meninjau kebutuhan dan mengonfirmasi detail pekerjaan sebelum layanan dijalankan.</p></div>
        </div>
    </div>
</section>

<?php if ($isUserLoggedIn && !empty($homeArticles)): ?>
<!-- ARTIKEL & EDUKASI SECTION (Khusus Pengguna Login/Terdaftar) -->
<section class="section" id="artikel-preview">
    <div class="container">
        <div class="section-head" style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:16px;">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-leaf"></i> Edukasi &amp; Wawasan Agensi</span>
                <h2>Artikel &amp; Panduan Terkini</h2>
                <p>Strategi branding, riset pasar, dan teknik budidaya dari tim Balla’ Fresh untuk mempercepat kemajuan usaha hidroponik Anda.</p>
            </div>
            <div>
                <a href="artikel.php" class="btn btn-outline" style="border-radius:99px; font-weight:700; padding:10px 22px;">
                    Jelajahi Semua Artikel <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="home-articles-grid">
            <?php foreach ($homeArticles as $hArt): 
                $hImg = !empty($hArt['image']) ? $hArt['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80';
                $hAvatar = !empty($hArt['author_avatar']) ? $hArt['author_avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80';
            ?>
                <article class="home-art-card">
                    <a href="baca_artikel.php?slug=<?= urlencode($hArt['slug']) ?>" class="home-art-thumb-wrap">
                        <img class="home-art-thumb" src="<?= htmlspecialchars($hImg) ?>" alt="<?= htmlspecialchars($hArt['title']) ?>" loading="lazy">
                        <span class="home-art-badge"><?= htmlspecialchars($hArt['category']) ?></span>
                    </a>
                    <div class="home-art-body">
                        <h3 class="home-art-title">
                            <a href="baca_artikel.php?slug=<?= urlencode($hArt['slug']) ?>">
                                <?= htmlspecialchars($hArt['title']) ?>
                            </a>
                        </h3>
                        <p class="home-art-excerpt"><?= htmlspecialchars($hArt['excerpt']) ?></p>
                        <div class="home-art-foot">
                            <div class="home-art-author">
                                <img class="home-art-author-img" src="<?= htmlspecialchars($hAvatar) ?>" alt="<?= htmlspecialchars($hArt['author_name']) ?>">
                                <span><?= htmlspecialchars($hArt['author_name']) ?></span>
                            </div>
                            <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($hArt['read_time']) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CONSULTATION -->
<section class="section auth-only-section" id="konsultasi">
    <div class="container">
        <div class="consult">
            <div>
                <span class="eyebrow" style="background:rgba(217,249,157,0.15);color:var(--lime);border-color:rgba(217,249,157,0.3)">Consultation</span>
                <h2>Punya kebutuhan yang belum tahu harus mulai dari mana?</h2>
                <p class="consult-copy">Ceritakan kondisi usaha Anda. Tim Balla’ Fresh akan membantu memetakan kebutuhan sebelum layanan dipilih.</p>
                <ul class="consult-points">
                    <li><i class="fa-solid fa-check"></i><span>Analisis kebutuhan pemasaran dan kanal digital.</span></li>
                    <li><i class="fa-solid fa-check"></i><span>Rekomendasi layanan yang sesuai kondisi usaha.</span></li>
                    <li><i class="fa-solid fa-check"></i><span>Tidak harus mengambil seluruh kategori layanan.</span></li>
                </ul>
            </div>

            <div class="form-card">
                <?php if (!empty($consultNotice)): ?>
                    <div style="padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;<?= $consultNoticeType === 'success' ? 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0' : 'background:#fee2e2;color:#991b1b;border:1px solid #fecaca' ?>">
                        <i class="fa-solid <?= $consultNoticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-right:6px"></i>
                        <?= htmlspecialchars($consultNotice) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$isUserLoggedIn): ?>
                    <!-- TAMPILAN BELUM LOGIN -->
                    <div style="text-align:center;padding:24px 10px">
                        <div style="width:56px;height:56px;border-radius:50%;background:#f0fdf4;color:var(--green);display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:14px">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <h3 style="margin-bottom:8px">Masuk untuk Memulai Konsultasi</h3>
                        <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:20px">
                            Layanan konsultasi Balla’ Fresh terhubung langsung dengan sistem tiket dan ruang konsultasi privat di profil akun Anda.
                        </p>
                        <div style="display:flex;gap:10px;justify-content:center">
                            <a href="login.php" class="btn btn-primary" style="padding:10px 22px">Masuk Akun</a>
                            <a href="register.php" class="btn btn-outline" style="padding:10px 22px">Daftar Mitra</a>
                        </div>
                    </div>

                <?php elseif ($userLatestConsultation && $userLatestConsultation['status'] === 'pending'): ?>
                    <!-- STATUS: MENUNGGU ACC ADMIN -->
                    <div style="border:1.5px dashed #f59e0b;background:#fffdf7;padding:22px;border-radius:14px">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>
                            <div>
                                <span style="font-size:10.5px;font-weight:800;color:#d97706;text-transform:uppercase;letter-spacing:0.5px">Tiket Konsultasi #<?= (int)$userLatestConsultation['id'] ?></span>
                                <h4 style="margin:0;font-size:15px;color:#92400e">Menunggu Persetujuan (ACC) Admin</h4>
                            </div>
                        </div>
                        <p style="font-size:12.5px;color:#78350f;line-height:1.5;margin-bottom:14px">
                            Permintaan konsultasi Anda untuk topik <strong><?= htmlspecialchars($userLatestConsultation['category']) ?></strong> telah terkirim dan sedang ditinjau oleh admin Balla’ Fresh. Ruang obrolan dapat digunakan segera setelah disetujui.
                        </p>
                        <a href="profil.php?view=consultation#chatRoomSection" class="btn btn-primary" style="width:100%;justify-content:center;display:inline-flex;align-items:center;gap:8px">
                            <i class="fa-solid fa-comments"></i> Pantau Status di Profil <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                <?php elseif ($userLatestConsultation && $userLatestConsultation['status'] === 'approved'): ?>
                    <!-- STATUS: SEDANG BERLANGSUNG -->
                    <div style="border:1.5px solid #86efac;background:#f0fdf4;padding:22px;border-radius:14px">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
                                <i class="fa-solid fa-comment-dots"></i>
                            </div>
                            <div>
                                <span style="font-size:10.5px;font-weight:800;color:#16a34a;text-transform:uppercase;letter-spacing:0.5px">Sesi Konsultasi Aktif #<?= (int)$userLatestConsultation['id'] ?></span>
                                <h4 style="margin:0;font-size:15px;color:#14532d">Room Chat Terhubung ke Konsultan</h4>
                            </div>
                        </div>
                        <p style="font-size:12.5px;color:#166534;line-height:1.5;margin-bottom:14px">
                            Sesi konsultasi Anda untuk topik <strong><?= htmlspecialchars($userLatestConsultation['category']) ?></strong> sedang aktif. Anda dapat langsung berkirim pesan dan dokumen dengan konsultan kami.
                        </p>
                        <a href="profil.php?view=consultation&amp;chat_id=<?= (int)$userLatestConsultation['id'] ?>#chatRoomSection" class="btn btn-primary" style="width:100%;justify-content:center;display:inline-flex;align-items:center;gap:8px">
                            <i class="fa-solid fa-comments"></i> Masuk ke Room Chat Sekarang <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- STATUS: BELUM ADA / SELESAI (BISA AJUKAN BARU) -->
                    <h3>Formulir Konsultasi Mitra</h3>
                    <form method="post" action="#konsultasi">
                        <input type="hidden" name="action" value="submit_home_consultation">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nama Pemilik Usaha</label>
                                <input class="form-control" type="text" name="client_name" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" placeholder="Nama Anda" required>
                            </div>
                            <div class="form-group">
                                <label>Nama Usaha / Brand</label>
                                <input class="form-control" type="text" name="business_name" value="<?= htmlspecialchars($currentUser['business_name'] ?? '') ?>" placeholder="Nama usaha">
                            </div>
                            <div class="form-group">
                                <label>Nomor Kontak</label>
                                <input class="form-control" type="tel" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                            </div>
                            <div class="form-group">
                                <label>Layanan yang Dipertimbangkan</label>
                                <select class="form-control" name="category" required>
                                    <option value="Starter Branding">Starter Branding</option>
                                    <option value="E-Commerce Grow">E-Commerce Grow</option>
                                    <option value="Full Marketing Scale">Full Marketing Scale</option>
                                    <option value="Belum tahu / ingin direkomendasikan">Belum tahu / ingin direkomendasikan</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Kendala / Kebutuhan Usaha</label>
                                <textarea class="form-control" name="notes" placeholder="Contoh: sudah punya Instagram tetapi belum memiliki website dan ingin memperluas pasar..." required></textarea>
                            </div>
                        </div>
                        <button class="btn btn-primary form-submit" type="submit">Kirim Permintaan Konsultasi <i class="fa-solid fa-arrow-right"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

</main>

<!-- CART -->
<div class="overlay" id="overlay"></div>
<aside class="cart-drawer" id="cartDrawer" aria-label="Keranjang layanan">
    <div class="cart-head">
        <div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:var(--green);font-weight:800">Service Cart</div>
            <h3>Keranjang Layanan</h3>
        </div>
        <button class="close-cart" id="cartClose"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cart-body" id="cartBody">
        <div class="cart-empty">
            <i class="fa-solid fa-bag-shopping"></i>
            <p>Belum ada layanan di keranjang.</p>
            <small>Pilih layanan yang benar-benar dibutuhkan usaha Anda.</small>
        </div>
    </div>
    <div class="cart-foot">
        <div class="cart-total"><span>Estimasi total</span><strong id="cartTotal">Rp0</strong></div>
        <button class="btn btn-primary" style="width:100%" id="cartCheckout">Lanjutkan Pemesanan <i class="fa-solid fa-arrow-right"></i></button>
        <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:10px">Harga dapat disesuaikan setelah kebutuhan mitra dikonfirmasi.</div>
    </div>
</aside>

<div class="toast" id="toast"></div>

<!-- Popup konfirmasi logout -->
<div class="logout-modal" id="logoutModal"
     aria-hidden="true">

    <div class="logout-dialog"
         role="dialog"
         aria-modal="true"
         aria-labelledby="logoutTitle"
         aria-describedby="logoutDescription">

        <div class="logout-icon">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </div>

        <h2 id="logoutTitle">Yakin ingin keluar?</h2>

        <p id="logoutDescription">
            Kamu akan keluar dari akun Balla’ Fresh.
            Kamu bisa login kembali kapan saja.
        </p>

        <div class="logout-actions">
            <button type="button"
                    class="logout-cancel"
                    id="cancelLogout">
                Kembali
            </button>

            <button type="button"
                    class="logout-confirm"
                    id="confirmLogout">
                Ya, Keluar
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <!-- GANTI dengan file logo PNG -->
                <img src="logo-balla-fresh.png" alt="Balla’ Fresh" onerror="this.style.display='none'">
                <p>Digital marketing agency yang berfokus membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan secara digital.</p>
            </div>
            <div>
                <h4>Jelajahi</h4>
                <div class="footer-links">
                    <a href="#tentang-kami">Tentang Kami</a>
                    <a href="#solusi">Solusi</a>
                    <a href="#layanan">Layanan</a>
                    <a href="#portfolio">Portofolio</a>
                    <a href="#alur">Alur Kerja</a>
                </div>
            </div>
            <div>
                <h4>Hubungi</h4>
                <div class="footer-links">
                    <a href="#konsultasi">Konsultasi Mitra</a>
                    <a href="mailto:<?= htmlspecialchars($siteContent['contact_email'] ?? 'ballafresh01@gmail.com') ?>"><?= htmlspecialchars($siteContent['contact_email'] ?? 'ballafresh01@gmail.com') ?></a>
                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $siteContent['contact_phone'] ?? '081213725107') ?>" target="_blank">WhatsApp: <?= htmlspecialchars($siteContent['contact_phone'] ?? '081213725107') ?></a>
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
let cart = [];
try {
    const savedCart = JSON.parse(localStorage.getItem("ballaFreshCart") || "[]");
    cart = Array.isArray(savedCart)
        ? savedCart.filter(item => item && /^\d+$/.test(String(item.id)) && Number(item.qty) > 0)
            .map(item => ({ ...item, id: String(item.id), qty: Math.min(20, Math.max(1, Number(item.qty) || 1)) }))
        : [];
} catch (error) {
    console.warn("Keranjang lama tidak dapat dibaca; keranjang dimulai ulang.", error);
}

const money = n => new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0
}).format(n);

function saveCart() {
    localStorage.setItem("ballaFreshCart", JSON.stringify(cart));
    renderCart();
}

/* Memuat layanan dari database */

const isUserLoggedIn = <?= json_encode($isUserLoggedIn) ?>;

async function loadServices() {
    const container = document.getElementById("services-container");
    if (!container) return;

    const escapeHTML = value => String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[char]);

    const formatMoney = value => new Intl.NumberFormat("id-ID", {
        style: "currency", currency: "IDR", maximumFractionDigits: 0
    }).format(Number(value) || 0);

    const categoryMap = {
        "Starter Branding": "branding",
        "E-Commerce Grow": "ecommerce",
        "Full Marketing Scale": "marketing"
    };
    const sampleImages = {
        branding: [
            "https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=900&q=80",
            "https://images.unsplash.com/photo-1600508774634-4e11d34730e2?auto=format&fit=crop&w=900&q=80"
        ],
        ecommerce: [
            "https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=900&q=80",
            "https://images.unsplash.com/photo-1559028012-481c04fa702d?auto=format&fit=crop&w=900&q=80"
        ],
        marketing: [
            "https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=900&q=80",
            "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=900&q=80"
        ]
    };

    try {
        const response = await fetch("api_layanan.php", { cache: "no-store" });
        if (!response.ok) throw new Error("Gagal mengambil data layanan.");

        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) {
            throw new Error("Format data layanan tidak sesuai.");
        }

        // Maksimal dua layanan per kategori: total enam layanan.
        const categoryCounts = { branding: 0, ecommerce: 0, marketing: 0 };
        const selectedServices = result.data.filter(service => {
            const category = categoryMap[service.category_name];
            if (!category || categoryCounts[category] >= 2) return false;
            categoryCounts[category]++;
            return true;
        });

        const imageIndexes = { branding: 0, ecommerce: 0, marketing: 0 };
        if (!selectedServices.length) {
            container.innerHTML = '<div class="service-empty">Belum ada layanan yang tersedia saat ini.</div>';
            return;
        }

        container.innerHTML = selectedServices.map(service => {
            const price = Number(service.price) || 0;
            const duration = service.estimated_days ? `${service.estimated_days} hari` : "Menyesuaikan kebutuhan";
            const category = categoryMap[service.category_name] || "branding";
            const imageIndex = imageIndexes[category]++;
            const image = service.image || sampleImages[category]?.[imageIndex] || sampleImages[category]?.[0] || sampleImages.branding[0];
            const features = String(service.features || "").split("|").map(x => x.trim()).filter(Boolean);

            const actionBottomHtml = isUserLoggedIn ? `
                <div class="price"><small>Mulai dari</small><strong>${formatMoney(price)}</strong></div>
                <div class="service-card-actions">
                    <a href="detail_layanan.php?id=${encodeURIComponent(service.id)}" class="btn-detail-link" onclick="event.stopPropagation()">
                        Detail <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <button class="add-btn" type="button" onclick="addToCart(this, event)"
                            aria-label="Tambah ${escapeHTML(service.name)} ke keranjang">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>` : `
                <div class="price price-locked">
                    <span class="lock-badge"><i class="fa-solid fa-lock"></i> Harga Khusus Mitra</span>
                    <small>Login untuk melihat penawaran</small>
                </div>
                <div class="service-card-actions">
                    <a href="detail_layanan.php?id=${encodeURIComponent(service.id)}" class="btn-detail-link" onclick="event.stopPropagation()">
                        Detail <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="login.php" class="btn-lock-login" onclick="event.stopPropagation()" title="Login untuk memesan">
                        <i class="fa-solid fa-lock"></i> Pesan
                    </a>
                </div>`;

            return `
                <article class="service-card"
                    data-id="${escapeHTML(service.id)}"
                    data-name="${escapeHTML(service.name)}"
                    data-category="${category}"
                    data-price="${price}"
                    data-duration="${escapeHTML(duration)}"
                    onclick="handleCardClick(event, '${escapeHTML(service.id)}')">
                    <div class="service-image">
                        <img src="${image}" alt="Contoh visual ${escapeHTML(service.name)}"
                             loading="lazy" onerror="this.style.display='none'">
                    </div>
                    <span class="service-tag">${escapeHTML(service.category_name)}</span>
                    <h3><a href="detail_layanan.php?id=${encodeURIComponent(service.id)}" onclick="event.stopPropagation()">${escapeHTML(service.name)}</a></h3>
                    <p>${escapeHTML(service.description)}</p>
                    ${features.length ? `<ul class="service-list">
                        ${features.map(feature => `<li><i class="fa-solid fa-check"></i>${escapeHTML(feature)}</li>`).join("")}
                    </ul>` : ""}
                    <div class="service-duration"><i class="fa-regular fa-clock"></i> Estimasi: ${escapeHTML(duration)}</div>
                    <div class="service-bottom">
                        ${actionBottomHtml}
                    </div>
                </article>`;
        }).join("");

        applyServiceFilters();
    } catch (error) {
        console.error("Gagal memuat layanan:", error);
        container.innerHTML = '<div class="service-empty">Layanan belum dapat dimuat. Periksa koneksi atau endpoint api_layanan.php, lalu muat ulang halaman.</div>';
    }
}
function handleCardClick(event, id) {
    if (event && (event.target.closest('.add-btn') || event.target.closest('.btn-detail-link') || event.target.closest('.btn-lock-login') || event.target.closest('a'))) {
        return;
    }
    window.location.href = `detail_layanan.php?id=${encodeURIComponent(id)}`;
}

/* Keranjang */
function addToCart(button, event) {
    if (event) {
        event.stopPropagation();
    }
    if (!isUserLoggedIn) {
        showToast("Silakan login terlebih dahulu untuk memesan layanan.", "info");
        setTimeout(() => { window.location.href = "login.php"; }, 1000);
        return;
    }

    const card = button ? button.closest(".service-card") : null;
    if (!card) return;

    const item = {
        id: card.dataset.id,
        name: card.dataset.name,
        category: card.dataset.category,
        price: Number(card.dataset.price),
        duration: card.dataset.duration || "",
        qty: 1
    };

    const existing = cart.find(x => x.id === item.id);

    if (existing) {
        if (existing.qty >= 20) {
            showToast("Maksimal 20 untuk setiap layanan.");
            return;
        }
        existing.qty++;
    } else {
        cart.push(item);
    }

    saveCart();
    openCart();
    showToast(item.name + " ditambahkan ke keranjang");
}

function changeQty(id, amount) {
    const item = cart.find(x => x.id === id);
    if (!item) return;

    item.qty += amount;
    if (item.qty > 20) {
        item.qty = 20;
        showToast("Maksimal 20 untuk setiap layanan.");
    }

    if (item.qty <= 0) {
        const index = cart.findIndex(x => x.id === id);
        cart.splice(index, 1);
    }

    saveCart();
}

function categoryName(cat) {
    return {
        branding: "Starter Branding",
        ecommerce: "E-Commerce Grow",
        marketing: "Full Marketing Scale"
    }[cat] || cat;
}

function renderCart() {
    const body = document.getElementById("cartBody");
    const countElement = document.getElementById("cartCount");
    const totalElement = document.getElementById("cartTotal");

    if (!body || !countElement || !totalElement) return;

    const count = cart.reduce((sum, x) => sum + x.qty, 0);
    const total = cart.reduce((sum, x) => sum + x.price * x.qty, 0);

    countElement.textContent = count;
    totalElement.textContent = money(total);

    if (!cart.length) {
        body.innerHTML = `
            <div class="cart-empty">
                <i class="fa-solid fa-bag-shopping"></i>
                <p>Belum ada layanan di keranjang.</p>
                <small>Pilih layanan yang benar-benar dibutuhkan usaha Anda.</small>
            </div>`;
        return;
    }

    const order = ["branding", "ecommerce", "marketing"];

    body.innerHTML = order.map(cat => {
        const items = cart.filter(x => x.category === cat);
        if (!items.length) return "";

        return `
            <div class="cart-category">
                <h4>${categoryName(cat)}</h4>

                ${items.map(item => `
                    <div class="cart-item">
                        <div>
                            <strong>${item.name}</strong>
                            <small>${money(item.price)} / layanan · ${item.duration || "Estimasi menyesuaikan kebutuhan"}</small>
                        </div>

                        <div class="qty">
                            <button onclick="changeQty('${item.id}', -1)">−</button>
                            <span>${item.qty}</span>
                            <button onclick="changeQty('${item.id}', 1)">+</button>
                        </div>
                    </div>
                `).join("")}
            </div>`;
    }).join("");
}

/* Buka dan tutup keranjang */
const drawer = document.getElementById("cartDrawer");
const overlay = document.getElementById("overlay");

function openCart() {
    if (!drawer || !overlay) return;

    drawer.classList.add("open");
    overlay.classList.add("show");
    document.body.classList.add("no-scroll");
}

function closeCart() {
    if (!drawer || !overlay) return;

    drawer.classList.remove("open");
    overlay.classList.remove("show");
    document.body.classList.remove("no-scroll");
}

document.getElementById("cartOpen")?.addEventListener("click", openCart);
document.getElementById("cartClose")?.addEventListener("click", closeCart);
overlay?.addEventListener("click", closeCart);

function showToast(text) {
    const toast = document.getElementById("toast");
    if (!toast) return;

    toast.textContent = text;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 2200);
}

/* Filter kategori dan pencarian layanan */
function applyServiceFilters() {
    const activeTab = document.querySelector(".category-tab.active");
    const category = activeTab?.dataset.filter || "all";
    const query = (document.getElementById("serviceSearch")?.value || "").trim().toLocaleLowerCase("id-ID");

    let visibleCount = 0;
    document.querySelectorAll("#services-container .service-card").forEach(card => {
        const matchesCategory = category === "all" || card.dataset.category === category;
        const searchable = `${card.dataset.name || ""} ${card.textContent || ""}`.toLocaleLowerCase("id-ID");
        const visible = matchesCategory && (!query || searchable.includes(query));
        card.classList.toggle("hidden", !visible);
        if (visible) visibleCount++;
    });

    let emptyState = document.getElementById("serviceNoResults");
    if (!emptyState) {
        emptyState = document.createElement("div");
        emptyState.id = "serviceNoResults";
        emptyState.className = "service-empty";
        emptyState.textContent = "Layanan tidak ditemukan. Coba kata kunci atau kategori lain.";
        document.getElementById("services-container")?.appendChild(emptyState);
    }
    emptyState.style.display = visibleCount ? "none" : "block";
}

document.querySelectorAll(".category-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        document.querySelectorAll(".category-tab").forEach(item => item.classList.remove("active"));
        tab.classList.add("active");
        applyServiceFilters();
    });
});
document.getElementById("serviceSearch")?.addEventListener("input", applyServiceFilters);

/* Dropdown masalah */
document.querySelectorAll(".problem-trigger").forEach(trigger => {
    trigger.addEventListener("click", () => {
        const item = trigger.closest(".problem-item");
        const answer = item.querySelector(".problem-answer");
        const isOpen = item.classList.contains("open");

        document.querySelectorAll(".problem-item").forEach(other => {
            other.classList.remove("open");
            const otherAnswer = other.querySelector(".problem-answer");
            if (otherAnswer) otherAnswer.style.maxHeight = null;
        });

        if (!isOpen) {
            item.classList.add("open");
            answer.style.maxHeight = answer.scrollHeight + "px";
        }
    });
});

document.querySelectorAll(".problem-item.open .problem-answer").forEach(answer => {
    answer.style.maxHeight = answer.scrollHeight + "px";
});

/* Navigasi mobile */
document.getElementById("menuToggle")?.addEventListener("click", () => {
    document.getElementById("navMenu")?.classList.toggle("open");
});

document.querySelectorAll(".nav-link").forEach(link => {
    link.addEventListener("click", () => {
        document.getElementById("navMenu")?.classList.remove("open");
    });
});



/* Checkout */
document.getElementById("cartCheckout")?.addEventListener("click", () => {
    if (!cart.length) {
        showToast("Keranjang masih kosong.");
        return;
    }

    saveCart();
    window.location.href = "checkout.php";
});

/* Navigasi aktif saat scroll */
const sections = [...document.querySelectorAll("main section[id]")];
const links = [...document.querySelectorAll(".nav-link")];

window.addEventListener("scroll", () => {
    const y = window.scrollY + 110;
    let current = "beranda";

    sections.forEach(sec => {
        if (y >= sec.offsetTop) current = sec.id;
    });

    const navMap = {
        'solusi': 'tentang-kami',
        'alur': 'layanan',
        'artikel-preview': 'layanan'
    };
    const targetId = navMap[current] || current;

    links.forEach(link => {
        link.classList.toggle(
            "active",
            link.getAttribute("href") === "#" + targetId
        );
    });
});

/* Inisialisasi halaman */
renderCart();
loadServices();


/* Status login dan navigasi pengguna */

/* Status login dan navigasi pengguna */
async function checkAuthStatus() {
    const authNav = document.getElementById("authNav");

    if (!authNav) return;

    try {
        const response = await fetch("auth_status.php", {
            cache: "no-store",
            credentials: "same-origin"
        });

        if (!response.ok) {
            throw new Error("Status login tidak dapat diperiksa.");
        }

        const user = await response.json();

        if (user.loggedIn === true) {
            document.body.classList.add("logged-in");

            authNav.innerHTML = `
                <a href="profil.php" class="auth-nav-profile" title="Profil Saya" aria-label="Profil Saya">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                </a>

                <a href="logout.php"
                   class="auth-nav-logout"
                   id="logoutLink"
                   title="Keluar / Logout"
                   aria-label="Keluar / Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </a>
            `;
        } else {
            if (typeof isUserLoggedIn !== "undefined" && isUserLoggedIn) {
                return;
            }
            document.body.classList.remove("logged-in");

            authNav.innerHTML = `
                <a href="login.php" class="auth-nav-link">
                    Login
                </a>

                <a href="register.php" class="auth-nav-button">
                    Daftar
                </a>
            `;
        }
    } catch (error) {
        console.error("Pemeriksaan autentikasi gagal:", error);
    }
}

/* Popup konfirmasi logout */
const logoutModal = document.getElementById("logoutModal");
const cancelLogout = document.getElementById("cancelLogout");
const confirmLogout = document.getElementById("confirmLogout");

let logoutUrl = "logout.php";

function openLogoutModal(url) {
    if (!logoutModal) {
        // Jika popup tidak tersedia, jangan langsung logout.
        return;
    }

    logoutUrl = url || "logout.php";
    logoutModal.classList.add("show");
    logoutModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("no-scroll");
    cancelLogout?.focus();
}

function closeLogoutModal() {
    if (!logoutModal) return;

    logoutModal.classList.remove("show");
    logoutModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("no-scroll");
}

document.getElementById("authNav")?.addEventListener("click", event => {
    const link = event.target.closest(".auth-nav-logout");

    if (!link) return;

    event.preventDefault();
    openLogoutModal(link.href);
});

cancelLogout?.addEventListener("click", closeLogoutModal);

confirmLogout?.addEventListener("click", () => {
    window.location.href = logoutUrl;
});

logoutModal?.addEventListener("click", event => {
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});

document.addEventListener("keydown", event => {
    if (event.key === "Escape" &&
        logoutModal?.classList.contains("show")) {
        closeLogoutModal();
    }
});

checkAuthStatus();
</script>
</body>
</html>