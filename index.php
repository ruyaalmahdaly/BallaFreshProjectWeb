<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isUserLoggedIn = isset($_SESSION['user_id']);
$isAdminLoggedIn = isset($_SESSION['admin_id']);

// Admin diarahkan ke dashboard admin, bukan Home pelanggan.
if ($isAdminLoggedIn) {
    header('Location: admin_dashboard.php');
    exit;
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
    justify-content: flex-end;
    gap: 22px;
    flex-shrink: 0;
}

.auth-nav-profile {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: var(--green-dark);
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    transition: color 0.2s ease;
}

.auth-nav-profile i {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--green-soft);
    color: var(--green);
    font-size: 15px;
}

.auth-nav-profile:hover {
    color: var(--green-light);
}

.auth-nav-logout {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 0;
    border: none;
    background: transparent;
    color: #64705b;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s ease;
}

.auth-nav-logout:hover {
    color: #b45309;
}

.auth-nav-logout i {
    font-size: 13px;
}

@media (max-width: 720px) {
    .auth-nav {
        gap: 14px;
    }

    .auth-nav-profile,
    .auth-nav-logout {
        font-size: 12px;
    }

    .auth-nav-profile i {
        width: 30px;
        height: 30px;
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
.category-tabs{display:flex;gap:10px;flex-wrap:wrap}
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
    padding:28px;display:flex;flex-direction:column;min-height:330px;
    transition:var(--ease);position:relative;box-shadow:var(--shadow-sm)
}
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
    .category-tabs{margin-top:20px}
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
            <a href="#solusi" class="nav-link">Solusi</a>
            <a href="#layanan" class="nav-link">Layanan</a>
            <a href="#portfolio" class="nav-link">Portofolio</a>
            <a href="#alur" class="nav-link">Alur Kerja</a>
        </nav>

        <div class="nav-actions">
            <button class="cart-btn auth-only" id="cartOpen"
            aria-label="Buka keranjang">
                <i class="fa-solid fa-bag-shopping"></i>
                <span class="cart-count" id="cartCount">0</span>
            </button>
            <a href="#konsultasi"
            class="btn btn-primary btn-consult-auth">
            Mulai Konsultasi
        </a>
            <button class="menu-toggle" id="menuToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        </div>
        <div id="authNav" class="auth-nav">
            <?php if ($isUserLoggedIn): ?>
                <a href="profil.php" class="auth-nav-profile">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>Profil</span>
                </a>
                <a href="logout.php"
                class="auth-nav-logout"
                id="logoutLink">
                Logout\
            </a>
        <?php else: ?>
            <a href="login.php" class="auth-nav-link">Login</a>
            <a href="register.php" class="auth-nav-button">Daftar</a>
        <?php endif; ?>
    </div>
</header>

<main>

<!-- HERO -->
<section class="hero" id="beranda">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-leaf"></i> Digital Growth Partner</span>
            <h1>Lebih banyak peluang, <span>#DariRumahAja.</span></h1>
            <p class="hero-copy">
                Balla’ Fresh membantu petani dan UMKM hidroponik rumahan di Makassar
                mengembangkan pemasaran dan penjualan melalui solusi digital yang sesuai dengan kebutuhan usahanya.
            </p>
            <div class="hero-actions">
                <a href="#layanan" class="btn btn-primary">Jelajahi Layanan <i class="fa-solid fa-arrow-right"></i></a>
                <a href="#portfolio" class="btn btn-outline">Lihat Portofolio</a>
            </div>
            <div class="hero-note">
                <i class="fa-solid fa-circle-check"></i>
                <span>Layanan dapat dipilih satu per satu sesuai kebutuhan mitra.</span>
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
            <div class="hero-chip"><i class="fa-solid fa-sliders"></i> Pilih sesuai kebutuhan</div>
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
            <div class="category-tabs">
                <button class="category-tab active" data-filter="all">Semua</button>
                <button class="category-tab" data-filter="branding">Starter Branding</button>
                <button class="category-tab" data-filter="ecommerce">E-Commerce Grow</button>
                <button class="category-tab" data-filter="marketing">Full Marketing Scale</button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp750.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Desain Logo"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp350.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Stiker Kemasan"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp400.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Setup Profil"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp3.000.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Website E-Commerce"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp600.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Katalog Produk"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp500.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Maintenance"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari</small><strong>Rp1.000.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Produksi Konten"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari / bulan</small><strong>Rp1.500.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Social Media Management"><i class="fa-solid fa-plus"></i></button>
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
                    <div class="price"><small>Mulai dari / bulan</small><strong>Rp1.000.000</strong></div>
                    <button class="add-btn" onclick="addToCart(this)" aria-label="Tambah Ads Management"><i class="fa-solid fa-plus"></i></button>
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
                <h3>Formulir Konsultasi Mitra</h3>
                <form id="consultForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Pemilik Usaha</label>
                            <input class="form-control" type="text" placeholder="Nama Anda" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Usaha / Brand</label>
                            <input class="form-control" type="text" placeholder="Nama usaha" required>
                        </div>
                        <div class="form-group">
                            <label>Nomor Kontak</label>
                            <input class="form-control" type="tel" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div class="form-group">
                            <label>Layanan yang Dipertimbangkan</label>
                            <select class="form-control">
                                <option value="">Pilih layanan</option>
                                <option>Starter Branding</option>
                                <option>E-Commerce Grow</option>
                                <option>Full Marketing Scale</option>
                                <option>Belum tahu / ingin direkomendasikan</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Kendala / Kebutuhan Usaha</label>
                            <textarea class="form-control" placeholder="Contoh: sudah punya Instagram tetapi belum memiliki website dan ingin memperluas pasar..."></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary form-submit" type="submit">Kirim Permintaan Konsultasi <i class="fa-solid fa-arrow-right"></i></button>
                </form>
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
                    <a href="mailto:info@ballafresh.com">Email</a>
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
const cart = JSON.parse(localStorage.getItem("ballaFreshCart") || "[]");

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

async function loadServices() {
    const container = document.getElementById("services-container");

    if (!container) {
        console.error("Elemen services-container tidak ditemukan.");
        return;
    }

    const escapeHTML = (value) =>
        String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[char]);

    const money = (value) =>
        new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0
        }).format(Number(value) || 0);

    try {
        const response = await fetch("api_layanan.php", {
            cache: "no-store"
        });

        if (!response.ok) {
            throw new Error("Gagal mengambil data layanan.");
        }

        const result = await response.json();

        if (!result.success || !Array.isArray(result.data)) {
            throw new Error("Format data layanan tidak sesuai.");
        }

        container.innerHTML = result.data.map((service) => {
            const price = Number(service.price) || 0;
            const duration = service.estimated_days
                ? `${service.estimated_days} hari`
                : "Menyesuaikan kebutuhan";

            const category = ({
                "Starter Branding": "branding",
                "E-Commerce Grow": "ecommerce",
                "Full Marketing Scale": "marketing"
            })[service.category_name] || "all";

            return `
                <article class="service-card"
                    data-id="${escapeHTML(service.id)}"
                    data-name="${escapeHTML(service.name)}"
                    data-category="${category}"
                    data-price="${price}"
                    data-duration="${escapeHTML(duration)}">

                    <span class="service-tag">
                        ${escapeHTML(service.category_name)}
                    </span>

                    <h3>${escapeHTML(service.name)}</h3>

<p>${escapeHTML(service.description)}</p>

<ul class="service-list">
    ${(service.features || "")
        .split("|")
        .filter(feature => feature.trim() !== "")
        .map(feature => `
            <li>
                <i class="fa-solid fa-check"></i>
                ${escapeHTML(feature.trim())}
            </li>
        `)
        .join("")}
</ul>

<div class="service-duration">
    <i class="fa-regular fa-clock"></i>
    Estimasi: ${escapeHTML(duration)}
</div>

                    <div class="service-bottom">
                        <div class="price">
                            <small>Mulai dari</small>
                            <strong>${money(price)}</strong>
                        </div>

                        <button
                            class="add-btn"
                            onclick="addToCart(this)"
                            aria-label="Tambah ${escapeHTML(service.name)} ke keranjang">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </article>
            `;
        }).join("");

        // Terapkan kembali filter kategori jika fungsi tersedia.
        const activeFilter = document.querySelector(".filter-btn.active");

        if (activeFilter) {
            activeFilter.click();
        }

    } catch (error) {
        console.error("Gagal memuat layanan:", error);
        container.innerHTML = `
            <p>Layanan belum dapat dimuat. Silakan muat ulang halaman.</p>
        `;
    }
}

/* Keranjang */
function addToCart(button) {
    const card = button.closest(".service-card");

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

/* Filter kategori */
document.querySelectorAll(".category-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        document.querySelectorAll(".category-tab").forEach(x =>
            x.classList.remove("active")
        );

        tab.classList.add("active");

        const filter = tab.dataset.filter;

        document.querySelectorAll(".service-card").forEach(card => {
            card.classList.toggle(
                "hidden",
                filter !== "all" && card.dataset.category !== filter
            );
        });
    });
});

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

/* Form konsultasi */
document.getElementById("consultForm")?.addEventListener("submit", e => {
    e.preventDefault();

    showToast(
        "Permintaan konsultasi berhasil dikirim. Tim Balla’ Fresh akan meninjau kebutuhan Anda."
    );

    e.target.reset();
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

    links.forEach(link => {
        link.classList.toggle(
            "active",
            link.getAttribute("href") === "#" + current
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
                <a href="profil.php" class="auth-nav-profile">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>Profil</span>
                </a>

                <a href="logout.php"
                   class="auth-nav-logout"
                   id="logoutLink">
                    Logout
                </a>
            `;
        } else {
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