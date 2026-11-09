<?php
session_start();
require_once __DIR__ . '/db.php';

// Jika admin sudah login, langsung ke dashboard.
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
$email = '';

// Token keamanan CSRF.
if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

// Proses login admin.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (
        !hash_equals($_SESSION['admin_login_csrf'], $csrf)
    ) {
        $error = 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $password === ''
    ) {
        $error = 'Masukkan email dan password yang valid.';
    } else {
        // Cari akun hanya dari tabel admins.
        $stmt = $conn->prepare(
            'SELECT id, name, email, password, is_approved, is_owner
             FROM admins
             WHERE email = ?
             LIMIT 1'
        );

        $stmt->bind_param('s', $email);
        $stmt->execute();

        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $admin &&
            password_verify($password, $admin['password']) &&
            (int) $admin['is_approved'] === 1
        ) {
            session_regenerate_id(true);

            // Hapus identitas sesi sebelumnya.
            unset(
                $_SESSION['user_id'],
                $_SESSION['user_name'],
                $_SESSION['admin_id'],
                $_SESSION['admin_name'],
                $_SESSION['admin_is_owner']
            );

            // Buat sesi khusus admin.
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_is_owner'] = (int) $admin['is_owner'];

            // Perbarui token keamanan.
            $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));

            header('Location: admin_dashboard.php');
            exit;
        }

        $error = 'Email atau password tidak sesuai, atau akun admin belum disetujui.';
    }
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login Admin | Balla’ Fresh</title>

    <link rel="stylesheet" href="auth.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .admin-eyebrow {
            display: block;
            color: #5b8121;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .login-submit {
            width: 100%;
            cursor: pointer;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper input {
            width: 100%;
            box-sizing: border-box;
            padding-right: 48px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #718064;
            cursor: pointer;
            font-size: 17px;
            padding: 5px;
        }

        .toggle-password:hover {
            color: #5b8121;
        }

        .toggle-password:focus-visible {
            outline: 2px solid #5b8121;
            border-radius: 5px;
        }

        .admin-back {
            display: inline-block;
            margin-top: 20px;
            color: #587d23;
            font-size: 13px;
            text-decoration: none;
        }

        .admin-back:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="auth-page">

    <section class="auth-form-side">

        <a class="auth-brand" href="index.php">
            Balla’<span>Fresh</span>
        </a>

        <span class="admin-eyebrow">
            ADMINISTRATOR AREA
        </span>

        <h1>Login Admin</h1>

        <p class="auth-description">
            Silakan masuk menggunakan akun administrator
            untuk mengelola website Balla’ Fresh.
        </p>

        <?php if ($error !== ''): ?>
            <div class="auth-notice" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="admin_login.php">

            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="auth-field">
                <label for="email">Email Admin</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    required
                    maxlength="150"
                    autocomplete="username"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Email administrator"
                >
            </div>

            <div class="auth-field">
                <label for="password">Password</label>

                <div class="password-wrapper">

                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan password admin"
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Tampilkan password"
                        aria-pressed="false"
                    >
                        <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                    </button>

                </div>
            </div>

            <button
                class="auth-button login-submit"
                type="submit"
            >
                Login Admin
            </button>

        </form>

        <a href="index.php" class="admin-back">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Kembali ke Website
        </a>

    </section>

    <aside class="auth-visual">

        <div class="auth-visual-content">

            <div class="leaf-icon">🌿</div>

            <span class="welcome-label">
                BALLA’ FRESH ADMIN
            </span>

            <h2>Kelola Balla’ Fresh dengan mudah.</h2>

            <p>
                Selamat datang di area administrator.
                Gunakan akses ini untuk mengelola layanan
                dan aktivitas website Balla’ Fresh.
            </p>

            <div class="visual-note">
                Fresh solutions, better business.
            </div>

        </div>

    </aside>

</div>

<script>
// Fitur tampilkan dan sembunyikan password.
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const passwordIcon = togglePassword.querySelector('i');

// Kondisi awal: password tersembunyi.
togglePassword.addEventListener('click', function () {

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';

        passwordIcon.classList.remove('fa-eye-slash');
        passwordIcon.classList.add('fa-eye');

        togglePassword.setAttribute('aria-label', 'Sembunyikan password');
        togglePassword.setAttribute('aria-pressed', 'true');

    } else {
        passwordInput.type = 'password';

        passwordIcon.classList.remove('fa-eye');
        passwordIcon.classList.add('fa-eye-slash');

        togglePassword.setAttribute('aria-label', 'Tampilkan password');
        togglePassword.setAttribute('aria-pressed', 'false');
    }

});
</script>

</body>
</html>
