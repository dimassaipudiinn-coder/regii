<?php
// login.php — User login page
require_once 'config.php';
redirectIfLoggedIn();

$error = '';

// Show logout success message
if (isset($_GET['logged_out'])) {
    $error = ''; // will display via info flash below
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── 1. Sanitize inputs ────────────────────────────────────
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';   // Don't htmlspecialchars the password before verifying

    // ── 2. Basic validation ───────────────────────────────────
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } else {
        // ── 3. Look up user with prepared statement ───────────
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, username, password, balance, role, avatar FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            /*
             * PASSWORD VERIFICATION:
             * We use password_verify() which works with bcrypt hashes.
             * This function is timing-safe (constant-time comparison)
             * so it prevents timing attacks.
             *
             * For educational purposes, the comment below shows what
             * MD5 would look like — NEVER use this in production:
             *   if (md5($password) === $user['password']) { ... }
             */
            if (password_verify($password, $user['password'])) {
                // ── 4. Regenerate session ID (prevents session fixation) ──
                session_regenerate_id(true);

                // ── 5. Store minimal info in session ──────────
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_balance']  = $user['balance'];
                $_SESSION['user_role']     = $user['role'];
                $_SESSION['user_avatar']   = $user['avatar'];

                setFlash('success', 'Selamat datang kembali, ' . $user['username'] . '! 👋');

                $redirect = ($user['role'] === 'admin') ? 'admin.php' : 'dashboard.php';
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Username atau password salah.';
            }
        } else {
            // Same message as wrong password — don't reveal whether username exists
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-bg"></div>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Logo -->
        <div class="auth-logo">
            <span class="logo-icon">⚡</span>
            <div class="site-title">Top<span>UpKu</span></div>
        </div>

        <h1 class="auth-title">Masuk ke Akun</h1>
        <p class="auth-sub">Selamat datang! Silakan login untuk melanjutkan.</p>

        <!-- Flash message -->
        <?php if (isset($_GET['logged_out'])): ?>
        <div class="flash flash-info">👋 Kamu berhasil logout. Sampai jumpa!</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="login.php" novalidate>
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Masukkan username"
                        value="<?= clean($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    Password
                    <span style="float:right">
                        <a href="#" onclick="togglePassword()" style="font-size:.78rem;text-transform:none;">Tampilkan</a>
                    </span>
                </label>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-accent btn-block btn-lg" style="margin-top:.5rem;">
                Masuk
            </button>
        </form>

        <!-- Demo credentials note -->
        <div class="flash flash-info" style="margin-top:1.25rem;font-size:.82rem;">
            <div>
                <strong>Demo:</strong> username <code>budi</code> atau <code>admin</code>, password <code>password123</code>
            </div>
        </div>

        <div class="auth-divider">atau</div>

        <p style="text-align:center;font-size:.9rem;color:var(--text-muted);">
            Belum punya akun?
            <a href="register.php" style="font-weight:700;">Daftar sekarang</a>
        </p>
    </div>
</div>

<script>
function togglePassword() {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Client-side validation
document.querySelector('form').addEventListener('submit', function(e) {
    const u = document.getElementById('username').value.trim();
    const p = document.getElementById('password').value;
    if (!u || !p) {
        e.preventDefault();
        alert('Semua field harus diisi!');
    }
});
</script>
</body>
</html>