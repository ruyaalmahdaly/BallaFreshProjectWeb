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

// Buat CSRF token jika belum ada
if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validCsrf(): bool
{
    return isset($_POST['csrf'], $_SESSION['register_csrf'])
        && hash_equals($_SESSION['register_csrf'], (string) $_POST['csrf']);
}

// Reset data akun jika pengguna ingin mulai dari awal
if (isset($_GET['stage']) && $_GET['stage'] === 'create' && isset($_GET['reset'])) {
    unset($_SESSION['temp_register']);
    sync_session_cookie();
    header('Location: register.php');
    exit;
}

$stage = 'create';
$tempAccount = $_SESSION['temp_register'] ?? null;

if (!empty($tempAccount) && is_array($tempAccount)) {
    $stage = 'address';
}

if (isset($_GET['stage']) && $_GET['stage'] === 'create') {
    $stage = 'create';
}

// Proses Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        // ==========================================
        // TAHAP 1: DATA AKUN (TANPA OTP EMAIL)
        // ==========================================
        if ($action === 'create') {
            $fullName = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
            $phoneInput = trim($_POST['phone'] ?? '');
            $phone = preg_replace('/[\s().-]+/', '', $phoneInput);
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($fullName === '' || strlen($fullName) > 200) {
                $error = 'Masukkan nama lengkap yang valid (maksimal 200 karakter).';
            } elseif (!preg_match('/^\+?[0-9]{8,18}$/', $phone)) {
                $error = 'Masukkan nomor telepon yang valid (8-18 digit).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $error = 'Format email tidak valid.';
            } elseif (strlen($password) < 8) {
                $error = 'Password minimal 8 karakter.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Password dan konfirmasi password tidak sama.';
            } else {
                // Periksa apakah email sudah terdaftar di users
                $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $error = 'Email sudah terdaftar. Silakan gunakan menu Login.';
                } else {
                    // Simpan data akun sementara ke session (langsung ke tahap alamat tanpa OTP)
                    $_SESSION['temp_register'] = [
                        'name'     => $fullName,
                        'phone'    => $phone,
                        'email'    => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                    ];
                    $_SESSION['register_csrf'] = bin2hex(random_bytes(32));

                    sync_session_cookie();

                    header('Location: register.php?stage=address');
                    exit;
                }
            }
        }

        // ==========================================
        // TAHAP 2: SIMPAN ALAMAT & SELESAIKAN PENDAFTARAN
        // ==========================================
        elseif ($action === 'save_address') {
            $temp = $_SESSION['temp_register'] ?? null;

            if (empty($temp) || !is_array($temp)) {
                $error = 'Data pendaftaran tidak ditemukan. Silakan lengkapi formulir akun terlebih dahulu.';
                $stage = 'create';
            } else {
                $city = trim($_POST['city'] ?? '');
                $details = trim($_POST['address_details'] ?? '');
                $subdistrict = trim($_POST['subdistrict'] ?? '');
                $rtRw = trim($_POST['rt_rw'] ?? '');
                $postal = trim($_POST['postal_code'] ?? '');

                if ($city === '' || strlen($city) > 100) {
                    $error = 'Masukkan nama kota/kabupaten yang valid.';
                } elseif ($details === '' || strlen($details) > 255) {
                    $error = 'Masukkan detail alamat yang valid (maksimal 255 karakter).';
                } elseif ($subdistrict === '' || strlen($subdistrict) > 100) {
                    $error = 'Masukkan nama kecamatan yang valid.';
                } elseif (strlen($rtRw) > 30) {
                    $error = 'Kolom RT/RW maksimal 30 karakter.';
                } elseif (!preg_match('/^\d{5,10}$/', $postal)) {
                    $error = 'Kode pos harus berupa 5-10 digit angka.';
                } else {
                    try {
                        if (method_exists($conn, 'begin_transaction')) {
                            $conn->begin_transaction();
                        }

                        // 1. Simpan Pengguna Baru
                        $stmt = $conn->prepare(
                            'INSERT INTO users (name, phone, email, password) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->bind_param('ssss', $temp['name'], $temp['phone'], $temp['email'], $temp['password']);
                        $stmt->execute();
                        $userId = (int) $conn->insert_id;
                        $stmt->close();

                        // 2. Simpan Alamat Pengguna
                        $stmt = $conn->prepare(
                            'INSERT INTO user_addresses (user_id, city, address_details, subdistrict, rt_rw, postal_code)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->bind_param('isssss', $userId, $city, $details, $subdistrict, $rtRw, $postal);
                        $stmt->execute();
                        $stmt->close();

                        if (method_exists($conn, 'commit')) {
                            $conn->commit();
                        }

                        // 3. Bersihkan data sementara & langsung aktifkan status login
                        unset($_SESSION['temp_register']);
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $temp['name'];
                        $_SESSION['user_email'] = $temp['email'];

                        // Sinkronkan cookie session sebelum redirect
                        sync_session_cookie();

                        // Arahkan langsung ke halaman beranda
                        header('Location: index.php');
                        exit;

                    } catch (Throwable $e) {
                        if (method_exists($conn, 'rollback')) {
                            $conn->rollback();
                        }
                        error_log('Penyimpanan pendaftaran gagal: ' . $e->getMessage());
                        $error = 'Gagal menyimpan pendaftaran: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Persiapkan nilai default untuk form
$fullNameValue = $_POST['full_name'] ?? ($tempAccount['name'] ?? '');
$emailValue = $_POST['email'] ?? ($tempAccount['email'] ?? '');
$phoneValue = $_POST['phone'] ?? ($tempAccount['phone'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun | Balla’ Fresh</title>
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="auth-page">
    <section class="auth-form-side">
        <a class="auth-brand" href="index.php">Balla’<span>Fresh</span></a>

        <?php if ($stage === 'create'): ?>
            <span class="auth-eyebrow">TAHAP 1 DARI 2: DATA AKUN</span>
            <h1>Create Account</h1>
            <p class="auth-description">
                Lengkapi profil usaha hidroponik Anda untuk mendaftar akun.
            </p>

            <?php if ($error): ?>
                <div class="auth-notice"><?= h($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" id="registerForm">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="create">

                <div class="auth-field">
                    <label for="full_name">Nama Lengkap</label>
                    <input id="full_name" type="text" name="full_name" required
                           maxlength="200" autocomplete="name"
                           value="<?= h($fullNameValue) ?>"
                           placeholder="Masukkan nama lengkap">
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

                <button class="auth-button" type="submit">Lanjut ke Alamat →</button>
            </form>

            <p class="auth-switch">
                Sudah punya akun? <a href="login.php">Login</a>
            </p>

        <?php else: ?>
            <span class="auth-eyebrow">TAHAP 2 DARI 2: LENGKAPI ALAMAT</span>
            <h1>Set Your Address</h1>
            <p class="auth-description">
                Halo <strong><?= h($tempAccount['name'] ?? 'Mitra') ?></strong>, lengkapi alamat kebun atau usaha Anda untuk menyelesaikan pendaftaran.
            </p>

            <?php if ($error): ?>
                <div class="auth-notice"><?= h($error) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="save_address">

                <div class="auth-field">
                    <label for="city">Kota / Kabupaten</label>
                    <input id="city" name="city" required maxlength="100"
                           autocomplete="address-level2" placeholder="Contoh: Makassar">
                </div>

                <div class="auth-field">
                    <label for="address_details">Detail Alamat Lengkap</label>
                    <textarea id="address_details" name="address_details"
                              required maxlength="255" autocomplete="street-address"
                              placeholder="Nama jalan, nomor rumah/kebun, dan kelurahan" rows="3"></textarea>
                </div>

                <div class="auth-field">
                    <label for="subdistrict">Kecamatan</label>
                    <input id="subdistrict" name="subdistrict" required maxlength="100"
                           placeholder="Contoh: Panakkukang">
                </div>

                <div class="auth-field">
                    <label for="rt_rw">RT / RW (Opsional)</label>
                    <input id="rt_rw" name="rt_rw" maxlength="30" placeholder="Contoh: 001/002">
                </div>

                <div class="auth-field">
                    <label for="postal_code">Kode Pos</label>
                    <input id="postal_code" name="postal_code" required
                           inputmode="numeric" pattern="[0-9]{5,10}" maxlength="10"
                           placeholder="Contoh: 90231">
                </div>

                <button class="auth-button" type="submit">Selesaikan Pendaftaran & Masuk →</button>
            </form>

            <p class="auth-switch">
                <a href="register.php?stage=create&reset=1">← Ubah data akun</a>
            </p>
        <?php endif; ?>
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
document.querySelectorAll('.toggle-password').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = document.getElementById(this.dataset.target);
        const icon = this.querySelector('i');
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', show);
        icon.classList.toggle('fa-eye-slash', !show);
        this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
    });
});

const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const message = document.getElementById('passwordMessage');

        if (password !== confirmPassword) {
            event.preventDefault();
            message.textContent = 'Password dan konfirmasi password tidak sama.';
            message.style.color = '#c0392b';
            document.getElementById('confirm_password').focus();
        } else {
            message.textContent = '';
        }
    });
}
</script>
</body>
</html>
