
<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$config = require __DIR__ . '/smtp_config.php';

$error = '';
$success = '';
$stage = 'create';

if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sendOtpEmail($email, $firstName, $otp, $config) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$config['port'];
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 20;

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($email, $firstName);
    $mail->isHTML(true);
    $mail->Subject = 'Kode Verifikasi Balla Fresh';
    $mail->Body = '
        <div style="font-family:Arial,sans-serif;line-height:1.6">
            <h2>Verifikasi Email Balla’ Fresh</h2>
            <p>Halo ' . h($firstName) . ',</p>
            <p>Gunakan kode berikut untuk melanjutkan pendaftaran:</p>
            <h1 style="letter-spacing:8px">' . h($otp) . '</h1>
            <p>Kode berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun.</p>
        </div>';
    $mail->AltBody =
        "Halo $firstName. Kode verifikasi Balla Fresh: $otp. Berlaku 10 menit.";

    $mail->send();
}

function validCsrf() {
    return isset($_POST['csrf'])
        && isset($_SESSION['register_csrf'])
        && hash_equals($_SESSION['register_csrf'], $_POST['csrf']);
}

$pendingId = (int)($_SESSION['registration_pending_id'] ?? 0);
$pending = null;

if ($pendingId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, otp_expires_at,
                otp_attempts, verified_at
         FROM registration_pending WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $pendingId);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pending) {
        unset($_SESSION['registration_pending_id']);
        $pendingId = 0;
    }
}

if ($pending) {
    $stage = $pending['verified_at'] ? 'address' : 'verify';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        // TAHAP 1: CREATE ACCOUNT
        if ($action === 'create') {
            $first = trim($_POST['first_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($first === '' || strlen($first) > 100 ||
                $last === '' || strlen($last) > 100) {
                $error = 'Nama depan dan nama belakang wajib diisi.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 150) {
                $error = 'Format email tidak valid.';
            } elseif (strlen($password) < 8) {
                $error = 'Password minimal 8 karakter.';
            } else {
                $stmt = $conn->prepare(
                    'SELECT id FROM users WHERE email = ? LIMIT 1'
                );
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $error = 'Email sudah terdaftar. Silakan login.';
                } else {
                    // Hapus pendaftaran lama yang belum selesai
                    $stmt = $conn->prepare(
                        'DELETE FROM registration_pending WHERE email = ?'
                    );
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $stmt->close();

                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $otp = (string)random_int(100000, 999999);
                    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                    $expires = date('Y-m-d H:i:s', time() + 600);

                    $stmt = $conn->prepare(
                        'INSERT INTO registration_pending
                         (first_name, last_name, email, password,
                          otp_hash, otp_expires_at, otp_attempts)
                         VALUES (?, ?, ?, ?, ?, ?, 0)'
                    );
                    $stmt->bind_param(
                        'ssssss', $first, $last, $email,
                        $hash, $otpHash, $expires
                    );

                    if ($stmt->execute()) {
                        $newId = $stmt->insert_id;
                        $stmt->close();

                        try {
                            sendOtpEmail($email, $first, $otp, $config);
                            session_regenerate_id(true);
                            $_SESSION['registration_pending_id'] = $newId;
                            $_SESSION['register_csrf'] =
                                bin2hex(random_bytes(32));
                            header('Location: register.php');
                            exit;
                        } catch (\Throwable $e) {
                            error_log('Pengiriman OTP gagal: ' . $e->getMessage());
                            $stmt = $conn->prepare(
                                'DELETE FROM registration_pending WHERE id = ?'
                            );
                            $stmt->bind_param('i', $newId);
                            $stmt->execute();
                            $stmt->close();
                            $error = 'Email OTP gagal dikirim. Periksa konfigurasi email lalu coba lagi.';
                        }
                    } else {
                        $stmt->close();
                        $error = 'Pendaftaran gagal. Silakan coba lagi.';
                    }
                }
            }
        }

        // TAHAP 2: VERIFIKASI OTP
        elseif ($action === 'verify' || $action === 'resend') {
            $id = (int)($_SESSION['registration_pending_id'] ?? 0);

            if ($id < 1) {
                $error = 'Sesi pendaftaran berakhir. Silakan daftar kembali.';
            } else {
                $stmt = $conn->prepare(
                    'SELECT * FROM registration_pending WHERE id = ? LIMIT 1'
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $p = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$p) {
                    $error = 'Data pendaftaran tidak ditemukan.';
                } elseif ($p['verified_at']) {
                    header('Location: register.php');
                    exit;
                } elseif ((int)$p['otp_attempts'] >= 5) {
                    $error = 'Batas percobaan OTP tercapai. Silakan mulai pendaftaran kembali.';
                } elseif ($action === 'resend') {
                    $otp = (string)random_int(100000, 999999);
                    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                    $expires = date('Y-m-d H:i:s', time() + 600);

                    $stmt = $conn->prepare(
                        'UPDATE registration_pending
                         SET otp_hash = ?, otp_expires_at = ?, otp_attempts = 0
                         WHERE id = ? AND verified_at IS NULL'
                    );
                    $stmt->bind_param('ssi', $otpHash, $expires, $id);
                    $stmt->execute();
                    $stmt->close();

                    try {
                        sendOtpEmail($p['email'], $p['first_name'], $otp, $config);
                        $success = 'Kode OTP baru sudah dikirim.';
                    } catch (\Throwable $e) {
                        error_log('Kirim ulang OTP gagal: ' . $e->getMessage());
                        $error = 'OTP gagal dikirim. Coba lagi nanti.';
                    }
                } else {
                    $otp = trim($_POST['otp'] ?? '');

                    if (!preg_match('/^\d{6}$/', $otp)) {
                        $error = 'Masukkan OTP enam digit.';
                    } elseif (empty($p['otp_expires_at'])
                        || strtotime($p['otp_expires_at']) < time()) {
                        $error = 'OTP sudah kedaluwarsa. Minta kode baru.';
                    } elseif (!password_verify($otp, $p['otp_hash'])) {
                        $stmt = $conn->prepare(
                            'UPDATE registration_pending
                             SET otp_attempts = otp_attempts + 1 WHERE id = ?'
                        );
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $stmt->close();
                        $error = 'Kode OTP salah.';
                    } else {
                        $stmt = $conn->prepare(
                            'UPDATE registration_pending
                             SET verified_at = NOW() WHERE id = ?'
                        );
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $stmt->close();
                        header('Location: register.php');
                        exit;
                    }
                }
            }
        }

        // TAHAP 3: SIMPAN ALAMAT DAN AKTIFKAN AKUN
        elseif ($action === 'save_address') {
            $id = (int)($_SESSION['registration_pending_id'] ?? 0);
            $city = trim($_POST['city'] ?? '');
            $details = trim($_POST['address_details'] ?? '');
            $subdistrict = trim($_POST['subdistrict'] ?? '');
            $rtRw = trim($_POST['rt_rw'] ?? '');
            $postal = trim($_POST['postal_code'] ?? '');

            $stmt = $conn->prepare(
                'SELECT * FROM registration_pending
                 WHERE id = ? AND verified_at IS NOT NULL LIMIT 1'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$p) {
                $error = 'Verifikasi email diperlukan sebelum mengisi alamat.';
            } elseif ($city === '' || strlen($city) > 100 ||
                $details === '' || strlen($details) > 255 ||
                $subdistrict === '' || strlen($subdistrict) > 100 ||
                strlen($rtRw) > 30 ||
                !preg_match('/^\d{5,10}$/', $postal)) {
                $error = 'Periksa kembali semua kolom alamat dan kode pos.';
            } else {
                $name = trim($p['first_name'] . ' ' . $p['last_name']);

                try {
                    $conn->begin_transaction();

                    $stmt = $conn->prepare(
                        'INSERT INTO users (name, email, password)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'sss', $name, $p['email'], $p['password']
                    );
                    $stmt->execute();
                    $userId = $stmt->insert_id;
                    $stmt->close();

                    $stmt = $conn->prepare(
                        'INSERT INTO user_addresses
                         (user_id, city, address_details, subdistrict,
                          rt_rw, postal_code)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'isssss', $userId, $city, $details,
                        $subdistrict, $rtRw, $postal
                    );
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare(
                        'DELETE FROM registration_pending WHERE id = ?'
                    );
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    unset($_SESSION['registration_pending_id']);
                    header('Location: index.php');
                    exit;
                } catch (\Throwable $e) {
                    $conn->rollback();
                    error_log('Penyimpanan akun gagal: ' . $e->getMessage());
                    $error = 'Akun belum dapat disimpan. Periksa data dan coba lagi.';
                }
            }
        }
    }
}

if ($pending) {
    $stage = $pending['verified_at'] ? 'address' : 'verify';
}

$firstValue = $_POST['first_name'] ?? '';
$lastValue = $_POST['last_name'] ?? '';
$emailValue = $_POST['email'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun | Balla’ Fresh</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-page">
    <section class="auth-form-side">
        <a class="auth-brand" href="index.php">Balla’<span>Fresh</span></a>

        <?php if ($stage === 'create'): ?>
            <span class="auth-eyebrow">TAHAP 1 DARI 3</span>
            <h1>Create Account</h1>
            <p class="auth-description">Buat akun Balla’ Fresh untuk melanjutkan.</p>

            <?php if ($error): ?><div class="auth-notice"><?= h($error) ?></div><?php endif; ?>

            <form class="auth-form" method="post">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="create">

                <div class="auth-field">
                    <label for="first_name">First Name</label>
                    <input id="first_name" name="first_name" required maxlength="100"
                           autocomplete="given-name" value="<?= h($firstValue) ?>">
                </div>
                <div class="auth-field">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" required maxlength="100"
                           autocomplete="family-name" value="<?= h($lastValue) ?>">
                </div>
                <div class="auth-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required maxlength="150"
                           autocomplete="email" value="<?= h($emailValue) ?>">
                </div>
                <div class="auth-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required
                           minlength="8" autocomplete="new-password">
                </div>
                <button class="auth-button" type="submit">Next →</button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="/balla-fresh/login.php">Login</a></p>

        <?php elseif ($stage === 'verify'): ?>
            <span class="auth-eyebrow">TAHAP 2 DARI 3</span>
            <h1>Account Verification</h1>
            <p class="auth-description">
                Kode OTP enam digit telah dikirim ke
                <strong><?= h($pending['email']) ?></strong>.
                Periksa juga folder Spam.
            </p>

            <?php if ($error): ?><div class="auth-notice"><?= h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="auth-notice"><?= h($success) ?></div><?php endif; ?>

            <form class="auth-form" method="post">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="verify">
                <div class="auth-field">
                    <label for="otp">Kode OTP</label>
                    <input id="otp" name="otp" inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" required
                           autocomplete="one-time-code" placeholder="Masukkan 6 digit">
                </div>
                <button class="auth-button" type="submit">Verifikasi →</button>
            </form>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="resend">
                <button class="auth-button" type="submit">Kirim ulang OTP</button>
            </form>

        <?php else: ?>
            <span class="auth-eyebrow">TAHAP 3 DARI 3</span>
            <h1>Set Your Address</h1>
            <p class="auth-description">
                Email terverifikasi. Lengkapi alamat untuk menyelesaikan pendaftaran.
            </p>

            <?php if ($error): ?><div class="auth-notice"><?= h($error) ?></div><?php endif; ?>

            <form class="auth-form" method="post">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['register_csrf']) ?>">
                <input type="hidden" name="action" value="save_address">

                <div class="auth-field">
                    <label for="city">Kota/Kabupaten</label>
                    <input id="city" name="city" required maxlength="100"
                           autocomplete="address-level2" placeholder="Contoh: Makassar">
                </div>
                <div class="auth-field">
                    <label for="address_details">Detail alamat</label>
                    <textarea id="address_details" name="address_details"
                              required maxlength="255" autocomplete="street-address"
                              placeholder="Nama jalan, nomor rumah, dan kelurahan"></textarea>
                </div>
                <div class="auth-field">
                    <label for="subdistrict">Kecamatan</label>
                    <input id="subdistrict" name="subdistrict" required maxlength="100">
                </div>
                <div class="auth-field">
                    <label for="rt_rw">RT/RW (opsional)</label>
                    <input id="rt_rw" name="rt_rw" maxlength="30" placeholder="Contoh: 001/002">
                </div>
                <div class="auth-field">
                    <label for="postal_code">Kode pos</label>
                    <input id="postal_code" name="postal_code" required
                           inputmode="numeric" pattern="[0-9]{5,10}" maxlength="10">
                </div>
                <button class="auth-button" type="submit">Selesaikan Pendaftaran →</button>
            </form>
        <?php endif; ?>
    </section>

    <aside class="auth-visual">
        <div class="auth-visual-content">
            <div class="leaf-icon">🌿</div>
            <span class="welcome-label">WELCOME TO BALLA’ FRESH</span>
            <h2>Mulai perjalanan yang lebih fresh.</h2>
            <p>Satu akun untuk terhubung dengan layanan Balla’ Fresh.</p>
            <div class="visual-note">Fresh solutions, better business.</div>
        </div>
    </aside>
</div>
</body>
</html>