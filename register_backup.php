
<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: profil.php');
    exit;
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || strlen($name) > 100) {
        $error = 'Nama wajib diisi dan maksimal 100 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $check->bind_param('s', $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
            );
            $stmt->bind_param('sss', $name, $email, $hash);

            if ($stmt->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $name;

                $stmt->close();
                header('Location: profil.php');
                exit;
            }

            $error = 'Registrasi gagal. Silakan coba lagi.';
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun | Balla’Fresh</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-page">
    <section class="auth-form-side">
        <a class="auth-brand" href="index.html">Balla’<span>Fresh</span></a>
        <span class="auth-eyebrow">GABUNG BERSAMA KAMI</span>
        <h1>Buat akun baru</h1>
        <p class="auth-description">
            Daftar untuk menikmati layanan Balla’Fresh dengan lebih mudah.
        </p>

        <?php if ($error): ?>
            <div class="auth-notice"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="register.php">
            <div class="auth-field">
                <label for="name">Nama lengkap</label>
                <input id="name" name="name" required maxlength="100"
                       autocomplete="name"
                       value="<?= htmlspecialchars($name) ?>"
                       placeholder="Masukkan nama lengkap">
            </div>
            <div class="auth-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" required maxlength="150"
                       autocomplete="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="nama@email.com">
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required
                       minlength="8" autocomplete="new-password"
                       placeholder="Minimal 8 karakter">
            </div>
            <div class="auth-field">
                <label for="confirm_password">Konfirmasi password</label>
                <input id="confirm_password" type="password"
                       name="confirm_password" required minlength="8"
                       autocomplete="new-password"
                       placeholder="Ulangi password">
            </div>
            <button class="auth-button" type="submit">Daftar sekarang →</button>
        </form>

        <p class="auth-switch">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </p>
    </section>

    <aside class="auth-visual">
        <div class="auth-visual-content">
            <div class="leaf-icon">🌿</div>
            <span class="welcome-label">WELCOME TO BALLA’FRESH</span>
            <h2>Mulai perjalanan yang lebih fresh.</h2>
            <p>
                Satu akun untuk terhubung dengan layanan Balla’Fresh.
                Praktis, nyaman, dan siap membantu kebutuhanmu.
            </p>
            <div class="visual-note">Fresh solutions, better business.</div>
        </div>
    </aside>
</div>
</body>
</html>
