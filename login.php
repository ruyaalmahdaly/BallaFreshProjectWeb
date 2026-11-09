<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

// Jika pengguna sudah login, langsung ke beranda.
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

// Membuat token CSRF.
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

// Proses login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $csrfInput = $_POST['csrf'] ?? '';

    $email = is_string($emailInput)
        ? strtolower(trim($emailInput))
        : '';

    $password = is_string($passwordInput)
        ? $passwordInput
        : '';

    $csrf = is_string($csrfInput)
        ? $csrfInput
        : '';

    if (
        !isset($_SESSION['login_csrf']) ||
        !hash_equals($_SESSION['login_csrf'], $csrf)
    ) {
        $error = 'Sesi formulir berakhir. Muat ulang halaman dan coba lagi.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $password === ''
    ) {
        $error = 'Masukkan email dan password yang valid.';

    } else {

        $stmt = $conn->prepare(
            'SELECT id, name, email, password
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $stmt->bind_param('s', $email);
        $stmt->execute();

        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $account &&
            password_verify($password, $account['password'])
        ) {

            // Regenerasi session setelah login.
            session_regenerate_id(true);

            // Simpan identitas pengguna.
            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['user_name'] = $account['name'];

            // Perbarui token CSRF.
            $_SESSION['login_csrf'] = bin2hex(random_bytes(32));

            // Masuk ke halaman utama.
            header('Location: index.php');
            exit;

        } else {
            $error = 'Email atau password tidak sesuai.';
        }
    }
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | Balla’ Fresh</title>

    <link rel="stylesheet" href="auth.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .login-submit {
            width: 100%;
            cursor: pointer;
        }

        /* Pembungkus input password dan tombol mata */
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

        .login-back-home {
            display: inline-block;
            margin-top: 20px;
            color: #587d23;
            font-size: 13px;
            text-decoration: none;
        }

        .login-back-home:hover {
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

        <span class="auth-eyebrow">
            SELAMAT DATANG KEMBALI
        </span>

        <h1>Login Akunmu</h1>

        <p class="auth-description">
            Masuk untuk melanjutkan pengalamanmu bersama Balla’ Fresh.
        </p>

        <?php if ($error !== ''): ?>
            <div class="auth-notice" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login.php">

            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="auth-field">
                <label for="email">Email</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    required
                    maxlength="150"
                    autocomplete="username"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="nama@email.com"
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
                        placeholder="Masukkan password"
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
                Login
            </button>

        </form>

        <p class="auth-switch">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
        </p>

        <a href="index.php" class="login-back-home">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Kembali ke Beranda
        </a>

    </section>

    <aside class="auth-visual">

        <div class="auth-visual-content">

            <div class="leaf-icon">🌿</div>

            <span class="welcome-label">
                HI, WELCOME BACK!
            </span>

            <h2>Senang melihatmu lagi.</h2>

            <p>
                Masuk ke akunmu dan lanjutkan langkah bersama Balla’ Fresh
                untuk kebutuhan bisnis yang lebih fresh.
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

// Kondisi awal: password tersembunyi, ikon mata tertutup.
passwordInput.type = 'password';
passwordIcon.classList.remove('fa-eye');
passwordIcon.classList.add('fa-eye-slash');

togglePassword.addEventListener('click', function () {

    if (passwordInput.type === 'password') {

        // Tampilkan password dan buka ikon mata.
        passwordInput.type = 'text';

        passwordIcon.classList.remove('fa-eye-slash');
        passwordIcon.classList.add('fa-eye');

        togglePassword.setAttribute('aria-label', 'Sembunyikan password');
        togglePassword.setAttribute('aria-pressed', 'true');

    } else {

        // Sembunyikan password dan tutup ikon mata.
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
