
<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
$loginType = $_POST['login_type'] ?? $_GET['type'] ?? 'user';

if (!in_array($loginType, ['user', 'admin'], true)) {
    $loginType = 'user';
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (
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
        if ($loginType === 'admin') {
            $stmt = $conn->prepare(
                'SELECT id, name, email, password, is_approved, is_owner
                FROM admins WHERE email = ? LIMIT 1'
            );
        } else {
            $stmt = $conn->prepare(
                'SELECT id, name, email, password
                 FROM users WHERE email = ? LIMIT 1'
            );
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $account &&
            password_verify($password, $account['password']) &&
            (
                $loginType !== 'admin' ||
                (int) $account['is_approved'] === 1
            )
        ) {
            session_regenerate_id(true);

            // Hapus identitas login lama sebelum membuat sesi baru.
            unset(
                $_SESSION['user_id'],
                $_SESSION['user_name'],
                $_SESSION['admin_id'],
                $_SESSION['admin_name']
            );

            if ($loginType === 'admin') {
                $_SESSION['admin_id'] = (int) $account['id'];
                $_SESSION['admin_name'] = $account['name'];
                $_SESSION['admin_is_owner'] = (int) $account['is_owner'];

                header('Location: admin_dashboard.php');
                exit;
            }

            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['user_name'] = $account['name'];

            header('Location: index.php');
            exit;
        }

        $error = 'Email atau password tidak sesuai untuk jenis akun yang dipilih.';
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
        .login-role-label {
            display: block;
            margin: 22px 0 9px;
            color: #344329;
            font-size: 13px;
            font-weight: 700;
        }

        .login-role-switch {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            padding: 5px;
            margin-bottom: 22px;
            border: 1px solid #e2e9d8;
            border-radius: 14px;
            background: #f1f5e9;
        }

        .login-role-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 48px;
            padding: 10px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #526148;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, color .2s, box-shadow .2s;
        }

        .login-role-option.active {
            background: #5b8121;
            color: #fff;
            box-shadow: 0 4px 12px rgba(57, 83, 23, .16);
        }

        .login-role-option:focus-visible {
            outline: 3px solid #b5d28b;
            outline-offset: 2px;
        }

        .login-role-help {
            margin: -12px 0 20px;
            color: #6c7863;
            font-size: 12px;
            line-height: 1.6;
        }

        .login-submit {
            width: 100%;
            cursor: pointer;
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

        @media (max-width: 420px) {
            .login-role-option {
                gap: 6px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
<div class="auth-page">
    <section class="auth-form-side">
        <a class="auth-brand" href="index.php">
            Balla’<span>Fresh</span>
        </a>

        <span class="auth-eyebrow" id="loginEyebrow">
            SELAMAT DATANG KEMBALI
        </span>

        <h1 id="loginTitle">Login Akunmu</h1>

        <p class="auth-description" id="loginDescription">
            Masuk untuk melanjutkan pengalamanmu bersama Balla’ Fresh.
        </p>

        <span class="login-role-label">Masuk sebagai</span>

        <div class="login-role-switch" role="group"
             aria-label="Pilih jenis akun">
            <button type="button"
                    class="login-role-option <?= $loginType === 'user' ? 'active' : '' ?>"
                    data-role="user"
                    aria-pressed="<?= $loginType === 'user' ? 'true' : 'false' ?>">
                <i class="fa-regular fa-user" aria-hidden="true"></i>
                USER
            </button>

            <button type="button"
                    class="login-role-option <?= $loginType === 'admin' ? 'active' : '' ?>"
                    data-role="admin"
                    aria-pressed="<?= $loginType === 'admin' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                ADMIN
            </button>
        </div>

        <p class="login-role-help" id="loginRoleHelp">
            Login pelanggan untuk mengakses layanan Balla’ Fresh.
        </p>

        <?php if ($error !== ''): ?>
            <div class="auth-notice" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login.php">
            <input type="hidden" name="csrf"
                   value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <input type="hidden" name="login_type" id="loginType"
                   value="<?= htmlspecialchars($loginType, ENT_QUOTES, 'UTF-8') ?>">

            <div class="auth-field">
                <label for="email">Email</label>
                <input id="email"
                       type="email"
                       name="email"
                       required
                       maxlength="150"
                       autocomplete="username"
                       value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="nama@email.com">
            </div>

            <div class="auth-field">
                <label for="password">Password</label>
                <input id="password"
                       type="password"
                       name="password"
                       required
                       autocomplete="current-password"
                       placeholder="Masukkan password">
            </div>

            <button class="auth-button login-submit" type="submit"
                    id="loginSubmit">
                Login sebagai User →
            </button>
        </form>

        <p class="auth-switch" id="registerSwitch">
            Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </p>

        <a href="index.php" class="login-back-home">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Kembali ke Beranda
        </a>
    </section>

    <aside class="auth-visual">
        <div class="auth-visual-content">
            <div class="leaf-icon">🌿</div>
            <span class="welcome-label" id="visualLabel">
                HI, WELCOME BACK!
            </span>

            <h2 id="visualTitle">Senang melihatmu lagi.</h2>

            <p id="visualDescription">
                Masuk ke akunmu dan lanjutkan langkah bersama Balla’ Fresh
                untuk kebutuhan bisnis yang lebih fresh.
            </p>

            <div class="visual-note">Fresh solutions, better business.</div>
        </div>
    </aside>
</div>

<script>
const roleButtons = document.querySelectorAll('.login-role-option');
const loginTypeInput = document.getElementById('loginType');

const loginTitle = document.getElementById('loginTitle');
const loginDescription = document.getElementById('loginDescription');
const loginEyebrow = document.getElementById('loginEyebrow');
const loginRoleHelp = document.getElementById('loginRoleHelp');
const loginSubmit = document.getElementById('loginSubmit');
const registerSwitch = document.getElementById('registerSwitch');

const visualLabel = document.getElementById('visualLabel');
const visualTitle = document.getElementById('visualTitle');
const visualDescription = document.getElementById('visualDescription');

function selectLoginRole(role) {
    loginTypeInput.value = role;

    roleButtons.forEach(button => {
        const active = button.dataset.role === role;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', String(active));
    });

    if (role === 'admin') {
        loginEyebrow.textContent = 'AREA ADMINISTRATOR';
        loginTitle.textContent = 'Login Admin';
        loginDescription.textContent =
            'Masuk ke akun pengelola Balla’ Fresh.';
        loginRoleHelp.textContent =
            'Khusus admin yang terdaftar pada sistem Balla’ Fresh.';
        loginSubmit.textContent = 'Login sebagai Admin →';
        registerSwitch.hidden = true;

        visualLabel.textContent = 'BALLA’ FRESH ADMIN';
        visualTitle.textContent = 'Kelola Balla’ Fresh dengan mudah.';
        visualDescription.textContent =
            'Masuk ke area pengelolaan untuk menjalankan tugas administrator.';
    } else {
        loginEyebrow.textContent = 'SELAMAT DATANG KEMBALI';
        loginTitle.textContent = 'Login Akunmu';
        loginDescription.textContent =
            'Masuk untuk melanjutkan pengalamanmu bersama Balla’ Fresh.';
        loginRoleHelp.textContent =
            'Login pelanggan untuk mengakses layanan Balla’ Fresh.';
        loginSubmit.textContent = 'Login sebagai User →';
        registerSwitch.hidden = false;

        visualLabel.textContent = 'HI, WELCOME BACK!';
        visualTitle.textContent = 'Senang melihatmu lagi.';
        visualDescription.textContent =
            'Masuk ke akunmu dan lanjutkan langkah bersama Balla’ Fresh untuk kebutuhan bisnis yang lebih fresh.';
    }
}

roleButtons.forEach(button => {
    button.addEventListener('click', () => {
        selectLoginRole(button.dataset.role);
    });
});

selectLoginRole(loginTypeInput.value);
</script>
</body>
</html>
