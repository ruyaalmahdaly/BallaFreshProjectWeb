<?php
declare(strict_types=1);

// Konfigurasi session bootstrap (mendukung signed cookie multi-container di Vercel)
require_once __DIR__ . '/session_bootstrap.php';

// Koneksi database
require_once __DIR__ . '/db.php';

// Jika pengguna sudah login, langsung ke beranda
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Buat CSRF token (menggunakan generator HMAC stateless terpercaya)
if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = function_exists('generate_csrf_token') ? generate_csrf_token() : bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['register_csrf'];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validCsrf(): bool
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (function_exists('verify_csrf_token') && verify_csrf_token($token)) {
        return true;
    }
    return isset($_SESSION['register_csrf']) && hash_equals((string) $_SESSION['register_csrf'], $token);
}

// Reset data akun jika pengguna ingin mulai dari awal
if (isset($_GET['reset'])) {
    unset($_SESSION['temp_register']);
    sync_session_cookie();
    header('Location: register.php');
    exit;
}

$tempAccount = $_SESSION['temp_register'] ?? null;
$initialStep = (!empty($tempAccount) && is_array($tempAccount)) ? 2 : 1;
if (isset($_GET['stage']) && $_GET['stage'] === 'create') {
    $initialStep = 1;
}

// Proses Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'Sesi formulir berakhir. Silakan periksa kembali data Anda dan klik kirim.';
        // Perbarui CSRF token untuk percobaan selanjutnya
        $_SESSION['register_csrf'] = function_exists('generate_csrf_token') ? generate_csrf_token() : bin2hex(random_bytes(32));
        $csrfToken = $_SESSION['register_csrf'];
    } else {
        $action = $_POST['action'] ?? 'register_complete';

        // =========================================================================
        // ALUR TERPADU: PENDAFTARAN LENGKAP DALAM SATU ATOMIC REQUEST (STEP 1 + STEP 2)
        // =========================================================================
        if ($action === 'register_complete' || ($action === 'create' && isset($_POST['city']))) {
            $fullName = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
            $phoneInput = trim($_POST['phone'] ?? '');
            $phone = preg_replace('/[\s().-]+/', '', $phoneInput);
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            $city = trim($_POST['city'] ?? '');
            $details = trim($_POST['address_details'] ?? '');
            $subdistrict = trim($_POST['subdistrict'] ?? '');
            $rtRw = trim($_POST['rt_rw'] ?? '-');
            if ($rtRw === '') {
                $rtRw = '-';
            }
            $postal = trim($_POST['postal_code'] ?? '');

            // Validasi Data Akun
            if ($fullName === '' || strlen($fullName) > 200) {
                $error = 'Masukkan nama lengkap yang valid (maksimal 200 karakter).';
                $initialStep = 1;
            } elseif (!preg_match('/^\+?[0-9]{8,18}$/', $phone)) {
                $error = 'Masukkan nomor telepon yang valid (8-18 digit angka).';
                $initialStep = 1;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $error = 'Format alamat email tidak valid.';
                $initialStep = 1;
            } elseif (strlen($password) < 8) {
                $error = 'Password harus minimal 8 karakter.';
                $initialStep = 1;
            } elseif ($password !== $confirmPassword) {
                $error = 'Password dan konfirmasi password tidak cocok.';
                $initialStep = 1;
            }
            // Validasi Data Alamat
            elseif ($city === '' || strlen($city) > 100) {
                $error = 'Masukkan nama kota/kabupaten yang valid.';
                $initialStep = 2;
            } elseif ($details === '' || strlen($details) > 255) {
                $error = 'Masukkan detail alamat lengkap yang valid (maksimal 255 karakter).';
                $initialStep = 2;
            } elseif ($subdistrict === '' || strlen($subdistrict) > 100) {
                $error = 'Masukkan nama kecamatan yang valid.';
                $initialStep = 2;
            } elseif (strlen($rtRw) > 30) {
                $error = 'Kolom RT/RW maksimal 30 karakter.';
                $initialStep = 2;
            } elseif (!preg_match('/^\d{5,10}$/', $postal)) {
                $error = 'Kode pos harus berupa 5-10 digit angka.';
                $initialStep = 2;
            } else {
                // Periksa apakah email sudah terdaftar di tabel users
                $emailExists = false;
                try {
                    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('s', $email);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res && method_exists($res, 'fetch_assoc')) {
                            $row = $res->fetch_assoc();
                            if (!empty($row)) {
                                $emailExists = true;
                            }
                        }
                        $stmt->close();
                    }
                } catch (Throwable $chkErr) {
                    error_log('Gagal cek email: ' . $chkErr->getMessage());
                }

                if ($emailExists) {
                    $error = 'Email sudah terdaftar. Silakan gunakan menu Login untuk masuk ke akun Anda.';
                    $initialStep = 1;
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $fullAddress = trim($details . ', ' . $subdistrict . ', ' . $city . ' ' . $postal);

                    try {
                        if (method_exists($conn, 'begin_transaction')) {
                            $conn->begin_transaction();
                        }

                        // 1. Simpan Pengguna Baru ke Tabel `users`
                        $stmt = $conn->prepare(
                            'INSERT INTO users (name, phone, email, password, address) VALUES (?, ?, ?, ?, ?)'
                        );
                        $stmt->bind_param('sssss', $fullName, $phone, $email, $hashedPassword, $fullAddress);
                        $stmt->execute();
                        $userId = (int) $conn->insert_id;
                        $stmt->close();

                        // Fallback jika insert_id belum terbaca langsung dari sequence adapter
                        if ($userId <= 0) {
                            $getIdStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                            if ($getIdStmt) {
                                $getIdStmt->bind_param('s', $email);
                                $getIdStmt->execute();
                                $idRes = $getIdStmt->get_result();
                                if ($idRes && method_exists($idRes, 'fetch_assoc')) {
                                    $idRow = $idRes->fetch_assoc();
                                    $userId = (int) ($idRow['id'] ?? 0);
                                }
                                $getIdStmt->close();
                            }
                        }

                        // 2. Simpan Alamat ke Tabel `user_addresses`
                        if ($userId > 0) {
                            try {
                                $stmtAddr = $conn->prepare(
                                    'INSERT INTO user_addresses (user_id, city, address_details, subdistrict, rt_rw, postal_code)
                                     VALUES (?, ?, ?, ?, ?, ?)'
                                );
                                if ($stmtAddr) {
                                    $stmtAddr->bind_param('isssss', $userId, $city, $details, $subdistrict, $rtRw, $postal);
                                    $stmtAddr->execute();
                                    $stmtAddr->close();
                                }
                            } catch (Throwable $addrErr) {
                                error_log('Gagal simpan user_addresses: ' . $addrErr->getMessage());
                            }

                            // 3. Sinkronkan ke tabel `customers` agar checkout instan dapat mendeteksi kontak
                            try {
                                $cFind = $conn->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
                                if ($cFind) {
                                    $cFind->bind_param('s', $email);
                                    $cFind->execute();
                                    $cRow = $cFind->get_result()->fetch_assoc();
                                    $cFind->close();

                                    if ($cRow) {
                                        $cUp = $conn->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?');
                                        if ($cUp) {
                                            $cid = (int)$cRow['id'];
                                            $cUp->bind_param('sssi', $fullName, $phone, $fullAddress, $cid);
                                            $cUp->execute();
                                            $cUp->close();
                                        }
                                    } else {
                                        $cIn = $conn->prepare('INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)');
                                        if ($cIn) {
                                            $cIn->bind_param('ssss', $fullName, $email, $phone, $fullAddress);
                                            $cIn->execute();
                                            $cIn->close();
                                        }
                                    }
                                }
                            } catch (Throwable $custErr) {
                                error_log('Gagal sync customers: ' . $custErr->getMessage());
                            }
                        }

                        if (method_exists($conn, 'commit')) {
                            $conn->commit();
                        }

                        // Bersihkan data sementara pendaftaran
                        unset($_SESSION['temp_register']);

                        // Aktifkan sesi login pengguna
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $fullName;
                        $_SESSION['user_email'] = $email;

                        // Sinkronkan cookie session signed HMAC
                        sync_session_cookie();

                        // Arahkan ke checkout jika sudah ada keranjang belanja, atau ke beranda
                        $redirectTarget = (!empty($_SESSION['cart']) && count($_SESSION['cart']) > 0) ? 'checkout.php' : 'index.php';
                        header('Location: ' . $redirectTarget);
                        exit;

                    } catch (Throwable $e) {
                        if (method_exists($conn, 'rollback')) {
                            $conn->rollback();
                        }
                        error_log('Penyimpanan pendaftaran gagal: ' . $e->getMessage());
                        $error = 'Gagal menyimpan pendaftaran ke database: ' . $e->getMessage();
                    }
                }
            }
        }

        // =========================================================================
        // FALLBACK: DUKUNGAN FORM BERTAHAP LAMA (JIKA ADA POST STAGE LAMA)
        // =========================================================================
        elseif ($action === 'create') {
            $fullName = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
            $phoneInput = trim($_POST['phone'] ?? '');
            $phone = preg_replace('/[\s().-]+/', '', $phoneInput);
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($fullName === '' || strlen($fullName) > 200) {
                $error = 'Masukkan nama lengkap yang valid.';
                $initialStep = 1;
            } elseif (!preg_match('/^\+?[0-9]{8,18}$/', $phone)) {
                $error = 'Masukkan nomor telepon yang valid.';
                $initialStep = 1;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Format email tidak valid.';
                $initialStep = 1;
            } elseif (strlen($password) < 8 || $password !== $confirmPassword) {
                $error = 'Password minimal 8 karakter dan harus sama dengan konfirmasi.';
                $initialStep = 1;
            } else {
                $_SESSION['temp_register'] = [
                    'name'     => $fullName,
                    'phone'    => $phone,
                    'email'    => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ];
                sync_session_cookie();
                $tempAccount = $_SESSION['temp_register'];
                $initialStep = 2;
            }
        }
        elseif ($action === 'save_address') {
            $temp = $_SESSION['temp_register'] ?? null;
            if (empty($temp) || !is_array($temp)) {
                $error = 'Data pendaftaran belum lengkap. Silakan lengkapi formulir akun terlebih dahulu.';
                $initialStep = 1;
            } else {
                $city = trim($_POST['city'] ?? '');
                $details = trim($_POST['address_details'] ?? '');
                $subdistrict = trim($_POST['subdistrict'] ?? '');
                $rtRw = trim($_POST['rt_rw'] ?? '-');
                $postal = trim($_POST['postal_code'] ?? '');

                if ($city === '' || $details === '' || $subdistrict === '' || !preg_match('/^\d{5,10}$/', $postal)) {
                    $error = 'Mohon lengkapi semua kolom alamat dengan benar.';
                    $initialStep = 2;
                } else {
                    try {
                        $fullAddress = trim($details . ', ' . $subdistrict . ', ' . $city . ' ' . $postal);
                        $stmt = $conn->prepare('INSERT INTO users (name, phone, email, password, address) VALUES (?, ?, ?, ?, ?)');
                        $stmt->bind_param('sssss', $temp['name'], $temp['phone'], $temp['email'], $temp['password'], $fullAddress);
                        $stmt->execute();
                        $userId = (int) $conn->insert_id;
                        $stmt->close();

                        if ($userId > 0) {
                            $stmtAddr = $conn->prepare('INSERT INTO user_addresses (user_id, city, address_details, subdistrict, rt_rw, postal_code) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($stmtAddr) {
                                $stmtAddr->bind_param('isssss', $userId, $city, $details, $subdistrict, $rtRw, $postal);
                                $stmtAddr->execute();
                                $stmtAddr->close();
                            }
                        }

                        unset($_SESSION['temp_register']);
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $temp['name'];
                        $_SESSION['user_email'] = $temp['email'];
                        sync_session_cookie();

                        header('Location: index.php');
                        exit;
                    } catch (Throwable $e) {
                        $error = 'Gagal menyimpan pendaftaran: ' . $e->getMessage();
                        $initialStep = 2;
                    }
                }
            }
        }
    }
}

// Persiapkan nilai form agar tidak hilang saat validasi gagal
$fullNameValue = $_POST['full_name'] ?? ($tempAccount['name'] ?? '');
$emailValue = $_POST['email'] ?? ($tempAccount['email'] ?? '');
$phoneValue = $_POST['phone'] ?? ($tempAccount['phone'] ?? '');
$cityValue = $_POST['city'] ?? '';
$detailsValue = $_POST['address_details'] ?? '';
$subdistrictValue = $_POST['subdistrict'] ?? '';
$rtRwValue = $_POST['rt_rw'] ?? '';
$postalValue = $_POST['postal_code'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun | Balla’ Fresh</title>
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .stepper-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .step-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.25s ease;
            user-select: none;
        }
        .step-pill.active {
            background: #eff5e5;
            color: var(--green);
            border: 1px solid rgba(91, 129, 33, 0.25);
            box-shadow: 0 2px 8px rgba(91, 129, 33, 0.1);
        }
        .step-pill.inactive {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid transparent;
        }
        .step-pill:hover:not(.active) {
            background: #e2e8f0;
            color: #334155;
        }
        .stepper-arrow {
            color: #94a3b8;
            font-size: 13px;
        }
        .step-panel {
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        .step-panel.hidden {
            display: none;
            opacity: 0;
            transform: translateY(8px);
        }
        .step-panel.visible {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .auth-field textarea {
            width: 100%;
            border: 1px solid #d8ded0;
            background: #fff;
            border-radius: 11px;
            padding: 14px 15px;
            font: inherit;
            outline: none;
            resize: vertical;
            transition: .2s;
        }
        .auth-field textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 4px #5b812118;
        }
        .btn-secondary-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            border: none;
            color: var(--green);
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            margin-top: 18px;
            transition: color 0.2s ease;
        }
        .btn-secondary-step:hover {
            color: var(--dark-green);
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="auth-page">
    <section class="auth-form-side">
        <a class="auth-brand" href="index.php">Balla’<span>Fresh</span></a>

        <!-- STEPPER PROGRESS INDICATOR -->
        <div class="stepper-nav" aria-label="Tahap Pendaftaran">
            <div id="pillStep1" class="step-pill <?= $initialStep === 1 ? 'active' : 'inactive' ?>" onclick="goToStep(1)">
                <i class="fa-solid fa-user-check"></i> 1. Data Akun
            </div>
            <span class="stepper-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            <div id="pillStep2" class="step-pill <?= $initialStep === 2 ? 'active' : 'inactive' ?>" onclick="goToStep(2)">
                <i class="fa-solid fa-location-dot"></i> 2. Alamat
            </div>
        </div>

        <?php if ($error): ?>
            <div class="auth-notice" id="serverNotice"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="auth-notice" id="clientNotice" style="display:none;"></div>

        <!-- FORMULIR PENDAFTARAN TERPADU -->
        <form class="auth-form" method="post" id="registerForm" novalidate>
            <input type="hidden" name="csrf" id="formCsrf" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" id="formAction" value="register_complete">

            <!-- ========================================== -->
            <!-- TAHAP 1: DATA AKUN (CLIENT-PROGRESSIVE)   -->
            <!-- ========================================== -->
            <div id="stepPanel1" class="step-panel <?= $initialStep === 1 ? 'visible' : 'hidden' ?>">
                <span class="auth-eyebrow">TAHAP 1 DARI 2: DATA AKUN</span>
                <h1>Create Account</h1>
                <p class="auth-description">
                    Lengkapi profil usaha hidroponik Anda untuk mendaftar akun.
                </p>

                <div class="auth-field">
                    <label for="full_name">Nama Lengkap</label>
                    <input id="full_name" type="text" name="full_name" required
                           maxlength="200" autocomplete="name"
                           value="<?= h($fullNameValue) ?>"
                           placeholder="Contoh: Anilva Fitriani">
                </div>

                <div class="auth-field">
                    <label for="phone">Nomor Telepon / WhatsApp</label>
                    <input id="phone" type="tel" name="phone" required
                           maxlength="24" autocomplete="tel"
                           value="<?= h($phoneValue) ?>"
                           placeholder="Contoh: 081234567890">
                </div>

                <div class="auth-field">
                    <label for="email">Alamat Email</label>
                    <input id="email" type="email" name="email" required
                           autocomplete="email" maxlength="150"
                           value="<?= h($emailValue) ?>"
                           placeholder="nama@email.com">
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input id="password" type="password" name="password" required
                               minlength="8" autocomplete="new-password"
                               placeholder="Minimal 8 karakter">
                        <button type="button" class="toggle-password"
                                data-target="password" aria-label="Lihat password">
                            <i class="fa-regular fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="password-wrapper">
                        <input id="confirm_password" type="password"
                               name="confirm_password" required minlength="8"
                               autocomplete="new-password"
                               placeholder="Masukkan ulang password">
                        <button type="button" class="toggle-password"
                                data-target="confirm_password"
                                aria-label="Lihat konfirmasi password">
                            <i class="fa-regular fa-eye-slash"></i>
                        </button>
                    </div>
                    <small id="passwordMessage" aria-live="polite"></small>
                </div>

                <button class="auth-button" type="button" id="btnNextStep">Lanjut ke Alamat →</button>

                <p class="auth-switch">
                    Sudah punya akun? <a href="login.php">Login</a>
                </p>
            </div>

            <!-- ========================================== -->
            <!-- TAHAP 2: DATA ALAMAT (CLIENT-PROGRESSIVE) -->
            <!-- ========================================== -->
            <div id="stepPanel2" class="step-panel <?= $initialStep === 2 ? 'visible' : 'hidden' ?>">
                <span class="auth-eyebrow">TAHAP 2 DARI 2: LENGKAPI ALAMAT</span>
                <h1>Set Your Address</h1>
                <p class="auth-description" id="addressGreeting">
                    Halo <strong id="greetingName"><?= h($fullNameValue !== '' ? $fullNameValue : 'Mitra') ?></strong>, lengkapi alamat kebun atau usaha Anda untuk menyelesaikan pendaftaran.
                </p>

                <div class="auth-field">
                    <label for="city">Kota / Kabupaten</label>
                    <input id="city" name="city" required maxlength="100"
                           autocomplete="address-level2" value="<?= h($cityValue) ?>"
                           placeholder="Contoh: Makassar">
                </div>

                <div class="auth-field">
                    <label for="address_details">Detail Alamat Lengkap</label>
                    <textarea id="address_details" name="address_details"
                              required maxlength="255" autocomplete="street-address"
                              placeholder="Nama jalan, nomor rumah/kebun, dan kelurahan" rows="3"><?= h($detailsValue) ?></textarea>
                </div>

                <div class="auth-field">
                    <label for="subdistrict">Kecamatan</label>
                    <input id="subdistrict" name="subdistrict" required maxlength="100"
                           value="<?= h($subdistrictValue) ?>" placeholder="Contoh: Panakkukang">
                </div>

                <div class="auth-field">
                    <label for="rt_rw">RT / RW (Opsional)</label>
                    <input id="rt_rw" name="rt_rw" maxlength="30"
                           value="<?= h($rtRwValue) ?>" placeholder="Contoh: 001/002 atau -">
                </div>

                <div class="auth-field">
                    <label for="postal_code">Kode Pos</label>
                    <input id="postal_code" name="postal_code" required
                           inputmode="numeric" pattern="[0-9]{5,10}" maxlength="10"
                           value="<?= h($postalValue) ?>" placeholder="Contoh: 90231">
                </div>

                <button class="auth-button" type="submit" id="btnSubmitForm">Selesaikan Pendaftaran & Masuk →</button>

                <div style="margin-top:14px;">
                    <button type="button" class="btn-secondary-step" id="btnBackStep">
                        <i class="fa-solid fa-arrow-left"></i> Ubah data akun
                    </button>
                </div>
            </div>
        </form>
    </section>

    <aside class="auth-visual">
        <div class="auth-visual-content">
            <div class="leaf-icon">🌿</div>
            <span class="welcome-label">WELCOME TO BALLA’ FRESH</span>
            <h2>Mulai perjalanan yang lebih fresh.</h2>
            <p>Satu akun untuk terhubung dengan layanan digitalisasi Balla’ Fresh.</p>
            <div class="visual-note">Fresh solutions, better business.</div>
        </div>
    </aside>
</div>

<script>
// Toggle Password Visibility
document.querySelectorAll('.toggle-password').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = document.getElementById(this.dataset.target);
        if (!input) return;
        const icon = this.querySelector('i');
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        if (icon) {
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
        }
        this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
    });
});

const pill1 = document.getElementById('pillStep1');
const pill2 = document.getElementById('pillStep2');
const panel1 = document.getElementById('stepPanel1');
const panel2 = document.getElementById('stepPanel2');
const clientNotice = document.getElementById('clientNotice');
const greetingName = document.getElementById('greetingName');

function showNotice(msg) {
    if (!clientNotice) return;
    clientNotice.textContent = msg;
    clientNotice.style.display = 'block';
    clientNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideNotice() {
    if (!clientNotice) return;
    clientNotice.textContent = '';
    clientNotice.style.display = 'none';
}

function validateStep1() {
    hideNotice();
    const fullName = document.getElementById('full_name').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (!fullName || fullName.length < 2) {
        showNotice('Mohon isi nama lengkap Anda (minimal 2 karakter).');
        document.getElementById('full_name').focus();
        return false;
    }

    const cleanPhone = phone.replace(/[\s().-]+/g, '');
    if (!/^\+?[0-9]{8,18}$/.test(cleanPhone)) {
        showNotice('Masukkan nomor telepon/WhatsApp yang valid (8-18 digit angka).');
        document.getElementById('phone').focus();
        return false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showNotice('Masukkan alamat email yang valid.');
        document.getElementById('email').focus();
        return false;
    }

    if (!password || password.length < 8) {
        showNotice('Password harus minimal 8 karakter.');
        document.getElementById('password').focus();
        return false;
    }

    if (password !== confirmPassword) {
        showNotice('Password dan konfirmasi password tidak cocok.');
        document.getElementById('confirm_password').focus();
        return false;
    }

    return true;
}

function goToStep(targetStep) {
    if (targetStep === 2) {
        if (!validateStep1()) {
            return;
        }

        const fullName = document.getElementById('full_name').value.trim();
        if (greetingName && fullName) {
            greetingName.textContent = fullName;
        }

        panel1.classList.remove('visible');
        panel1.classList.add('hidden');

        panel2.classList.remove('hidden');
        panel2.classList.add('visible');

        pill1.classList.remove('active');
        pill1.classList.add('inactive');

        pill2.classList.remove('inactive');
        pill2.classList.add('active');

        hideNotice();
        const cityInput = document.getElementById('city');
        if (cityInput && !cityInput.value) {
            setTimeout(() => cityInput.focus(), 50);
        }
    } else {
        panel2.classList.remove('visible');
        panel2.classList.add('hidden');

        panel1.classList.remove('hidden');
        panel1.classList.add('visible');

        pill2.classList.remove('active');
        pill2.classList.add('inactive');

        pill1.classList.remove('inactive');
        pill1.classList.add('active');

        hideNotice();
    }
}

document.getElementById('btnNextStep')?.addEventListener('click', function() {
    goToStep(2);
});

document.getElementById('btnBackStep')?.addEventListener('click', function() {
    goToStep(1);
});

// Form Submission Validator
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(event) {
        // Jika masih di panel 1, arahkan ke panel 2 dulu
        if (panel1.classList.contains('visible')) {
            event.preventDefault();
            goToStep(2);
            return;
        }

        // Validasi Step 1 sekali lagi untuk memastikan tidak ada field kosong
        if (!validateStep1()) {
            event.preventDefault();
            goToStep(1);
            return;
        }

        // Validasi Step 2 (Alamat)
        const city = document.getElementById('city').value.trim();
        const details = document.getElementById('address_details').value.trim();
        const subdistrict = document.getElementById('subdistrict').value.trim();
        const postal = document.getElementById('postal_code').value.trim();

        if (!city) {
            event.preventDefault();
            showNotice('Mohon isi nama kota atau kabupaten tempat usaha Anda.');
            document.getElementById('city').focus();
            return;
        }

        if (!details) {
            event.preventDefault();
            showNotice('Mohon isi detail alamat lengkap (nama jalan, nomor, kelurahan).');
            document.getElementById('address_details').focus();
            return;
        }

        if (!subdistrict) {
            event.preventDefault();
            showNotice('Mohon isi nama kecamatan.');
            document.getElementById('subdistrict').focus();
            return;
        }

        if (!/^\d{5,10}$/.test(postal)) {
            event.preventDefault();
            showNotice('Kode pos harus berupa 5-10 digit angka.');
            document.getElementById('postal_code').focus();
            return;
        }

        const submitBtn = document.getElementById('btnSubmitForm');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan Pendaftaran...';
        }
    });
}
</script>
</body>
</html>
