<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

/*
 * Balla' Fresh Admin Dashboard
 * Requires: admins, users, user_addresses (address table is optional).
 * Protect every admin page with the admin session.
 */
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php?type=admin');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminName = (string) ($_SESSION['admin_name'] ?? 'Administrator');
$adminEmail = '';

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
        header('Location: login.php?type=admin');
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

if ($addressTableExists) {
    $sql = 'SELECT u.id, u.name, u.email, a.city, a.subdistrict, a.address_details, a.postal_code
            FROM users u
            LEFT JOIN user_addresses a ON a.user_id = u.id';
} else {
    $sql = 'SELECT u.id, u.name, u.email, NULL AS city, NULL AS subdistrict,
                   NULL AS address_details, NULL AS postal_code
            FROM users u';
}

if ($search !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?';
}
$sql .= ' ORDER BY u.id DESC LIMIT 12';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('ss', $like, $like);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();
} else {
    $searchError = 'Data pelanggan belum dapat dimuat. Periksa struktur tabel users dan user_addresses.';
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
    <title>Dashboard Admin | Balla’ Fresh</title>
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
    </style>
</head>
<body>
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
            <a href="#overview" class="side-link active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="#customers" class="side-link"><i class="fa-solid fa-users"></i> Data Pelanggan</a>
            <a href="#activity" class="side-link"><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas Terbaru</a>
            <a href="#agency" class="side-link"><i class="fa-solid fa-seedling"></i> Operasional Agency</a>
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
            <a class="side-logout" href="logout.php" title="Logout" aria-label="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        </div>
    </aside>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <main class="main">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
            <div class="crumb">Workspace <span aria-hidden="true">/</span> <strong>Dashboard</strong></div>
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
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · ADMIN PANEL</span>
                    <h1>Dashboard Overview</h1>
                    <p>Selamat datang, <?= e($adminName) ?>. Berikut ringkasan data yang tersedia di sistem saat ini.</p>
                </div>
                <span class="welcome-tag"><span></span> Sistem terhubung ke database</span>
            </section>

            <section class="stats-grid" aria-label="Ringkasan data">
                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-kicker">Total Pelanggan</span>
                        <span class="stat-icon"><i class="fa-solid fa-users"></i></span>
                    </div>
                    <div class="stat-value"><?= number_format($customerCount, 0, ',', '.') ?></div>
                    <div class="stat-foot"><i class="fa-solid fa-database"></i> Akun tersimpan pada tabel users</div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-kicker">Alamat Tersimpan</span>
                        <span class="stat-icon sage"><i class="fa-solid fa-location-dot"></i></span>
                    </div>
                    <div class="stat-value"><?= number_format($addressCount, 0, ',', '.') ?></div>
                    <div class="stat-foot"><?= $addressTableExists ? 'Pelanggan dengan data alamat' : 'Tabel user_addresses belum tersedia' ?></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-kicker">Administrator</span>
                        <span class="stat-icon lilac"><i class="fa-solid fa-user-shield"></i></span>
                    </div>
                    <div class="stat-value"><?= number_format($adminCount, 0, ',', '.') ?></div>
                    <div class="stat-foot"><i class="fa-solid fa-lock"></i> Akun admin terpisah dari pelanggan</div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-kicker">Pendaftaran Tertunda</span>
                        <span class="stat-icon amber"><i class="fa-regular fa-envelope"></i></span>
                    </div>
                    <div class="stat-value"><?= $pendingCount === null ? '—' : number_format($pendingCount, 0, ',', '.') ?></div>
                    <div class="stat-foot"><?= $pendingCount === null ? 'Tabel registration_pending tidak ditemukan' : 'Data yang belum menyelesaikan registrasi' ?></div>
                </article>
            </section>

            <section class="panel-grid">
                <article class="panel" id="customers">
                    <div class="panel-head">
                        <div>
                            <h2>Data Pelanggan</h2>
                            <p>Daftar akun terbaru yang tersimpan di database.</p>
                        </div>
                        <form class="search-form" method="get" action="admin_dashboard.php">
                            <label class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau email..." aria-label="Cari pelanggan">
                            </label>
                            <button class="btn" type="submit">Cari</button>
                            <?php if ($search !== ''): ?>
                                <a class="btn btn-light" href="admin_dashboard.php" aria-label="Hapus pencarian"><i class="fa-solid fa-xmark"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($searchError !== ''): ?>
                        <div class="notice"><?= e($searchError) ?></div>
                    <?php endif; ?>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Alamat</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$customers): ?>
                                <tr><td colspan="3">
                                    <div class="empty">
                                        <i class="fa-solid fa-user-group"></i>
                                        <?= $search !== '' ? 'Tidak ada pelanggan yang cocok dengan pencarian.' : 'Belum ada data pelanggan untuk ditampilkan.' ?>
                                    </div>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <?php
                                    $customerName = (string) $customer['name'];
                                    $parts = preg_split('/\s+/', trim($customerName)) ?: [];
                                    $customerInitials = count($parts) > 1
                                        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
                                        : strtoupper(substr($customerName, 0, 2));
                                    $addressText = trim(implode(', ', array_filter([
                                        $customer['address_details'] ?? '',
                                        $customer['subdistrict'] ?? '',
                                        $customer['city'] ?? '',
                                        $customer['postal_code'] ?? ''
                                    ])));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="customer-cell">
                                                <span class="customer-avatar"><?= e($customerInitials) ?></span>
                                                <span>
                                                    <strong><?= e($customerName) ?></strong>
                                                    <small><?= e($customer['email']) ?> · ID #<?= (int) $customer['id'] ?></small>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="muted-cell"><?= $addressText !== '' ? e($addressText) : 'Alamat belum tersedia' ?></td>
                                        <td><span class="status-pill"><i class="fa-solid fa-circle"></i> Terdaftar</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <div style="display:grid;gap:20px;min-width:0">
                    <article class="panel" id="activity">
                        <div class="panel-head">
                            <div>
                                <h2>Aktivitas Terbaru</h2>
                                <p>Aktivitas yang dapat disimpulkan dari data saat ini.</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="activity-list">
                                <?php if (!$latestUsers): ?>
                                    <div class="empty"><i class="fa-regular fa-bell"></i>Belum ada aktivitas pelanggan.</div>
                                <?php else: ?>
                                    <?php foreach ($latestUsers as $latest): ?>
                                        <div class="activity-item">
                                            <span class="activity-icon"><i class="fa-solid fa-user-plus"></i></span>
                                            <div>
                                                <strong>Akun pelanggan tersimpan</strong>
                                                <p><?= e($latest['name']) ?> · <?= e($latest['email']) ?></p>
                                            </div>
                                            <span class="activity-id">#<?= (int) $latest['id'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                    <article class="panel" id="agency">
                        <div class="panel-head">
                            <div>
                                <h2>Operasional Agency</h2>
                                <p>Modul pemantauan bisnis yang perlu dihubungkan.</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="ops-grid">
                                <div class="ops-card">
                                    <i class="fa-solid fa-layer-group"></i>
                                    <strong>Layanan Agency</strong>
                                    <p>Paket layanan, harga, dan status ketersediaan.</p>
                                    <span class="coming">Perlu tabel layanan</span>
                                </div>
                                <div class="ops-card">
                                    <i class="fa-solid fa-comments"></i>
                                    <strong>Konsultasi</strong>
                                    <p>Permintaan masuk, jadwal, dan tindak lanjut.</p>
                                    <span class="coming">Perlu tabel konsultasi</span>
                                </div>
                                <div class="ops-card">
                                    <i class="fa-solid fa-cart-shopping"></i>
                                    <strong>Pesanan</strong>
                                    <p>Pesanan layanan, pembayaran, dan progres.</p>
                                    <span class="coming">Perlu tabel pesanan</span>
                                </div>
                                <div class="ops-card">
                                    <i class="fa-solid fa-chart-line"></i>
                                    <strong>Kinerja Agency</strong>
                                    <p>Ringkasan pendapatan dan performa layanan.</p>
                                    <span class="coming">Perlu data transaksi</span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <div class="footnote">
                <i class="fa-solid fa-circle-info"></i>
                <div><strong>Catatan data:</strong> angka pelanggan, alamat, admin, dan pendaftaran tertunda dibaca dari database. Modul layanan, konsultasi, pesanan, pembayaran, dan pendapatan belum menampilkan angka karena struktur tabel transaksinya belum dikonfirmasi. Setelah tabel tersebut tersedia, dashboard dapat menampilkan status dan pembaruan operasional secara nyata.</div>
            </div>
        </div>
    </main>
</div>

<script>
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
</script>
</body>
</html>
