<?php
// register.php — New user registration
require_once 'config.php';
redirectIfLoggedIn();

$errors = [];
$values = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── 1. Sanitize inputs ────────────────────────────────────
    $username  = clean($_POST['username']  ?? '');
    $email     = clean($_POST['email']     ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    $values = compact('username', 'email');

    // ── 2. Validation rules ───────────────────────────────────
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = 'Username harus 3–50 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username hanya boleh huruf, angka, dan underscore.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password harus mengandung minimal 1 huruf besar.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password harus mengandung minimal 1 angka.';
    }

    if ($password !== $password2) {
        $errors['password2'] = 'Konfirmasi password tidak cocok.';
    }

    // ── 3. Check duplicates ───────────────────────────────────
    if (empty($errors)) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $errors['global'] = 'Username atau email sudah digunakan.';
        }
    }

    // ── 4. Create user if no errors ───────────────────────────
    if (empty($errors)) {
        /*
         * HASHING COMPARISON (Educational):
         *
         * MD5 (INSECURE — DO NOT USE IN PRODUCTION):
         *   $hashed = md5($password);
         *   → Output: 32-char hex string (always same for same input)
         *   → No salt, attackable via rainbow tables
         *
         * BCRYPT (SECURE — RECOMMENDED):
         *   $hashed = password_hash($password, PASSWORD_DEFAULT);
         *   → Output: 60-char string with embedded salt + cost factor
         *   → Different hash every time, even for same password
         *   → Slow by design (default cost 10 = ~100ms per hash)
         */
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $avatars = ['🎮','⚡','🔥','💫','🌟','🎯','🚀','💎'];
        $avatar  = $avatars[array_rand($avatars)];

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password, balance, role, avatar) VALUES (?, ?, ?, 0.00, 'user', ?)"
        );
        $stmt->execute([$username, $email, $hashed, $avatar]);

        setFlash('success', 'Akun berhasil dibuat! Silakan login sekarang. 🎉');
        header('Location: login.php');
        exit;
    }
}

// Helper: render error for a field
function fieldError(array $errors, string $field): string {
    return isset($errors[$field])
        ? '<div class="form-error">' . htmlspecialchars($errors[$field]) . '</div>'
        : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — <?= SITE_NAME ?></title>
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

        <h1 class="auth-title">Buat Akun Baru</h1>
        <p class="auth-sub">Bergabung dengan jutaan gamer Indonesia.</p>

        <?php if (!empty($errors['global'])): ?>
        <div class="flash flash-error"><?= htmlspecialchars($errors['global']) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate id="regForm">
            <!-- Username -->
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control <?= isset($errors['username']) ? 'error' : '' ?>"
                        placeholder="Minimal 3 karakter"
                        value="<?= htmlspecialchars($values['username']) ?>"
                        maxlength="50"
                        autocomplete="username"
                        required
                    >
                </div>
                <?= fieldError($errors, 'username') ?>
                <div class="form-hint">Huruf, angka, dan underscore saja.</div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <div class="input-group">
                    <span class="input-icon">📧</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                        placeholder="contoh@email.com"
                        value="<?= htmlspecialchars($values['email']) ?>"
                        autocomplete="email"
                        required
                    >
                </div>
                <?= fieldError($errors, 'email') ?>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
                        placeholder="Min. 8 karakter"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <?= fieldError($errors, 'password') ?>
                <!-- Password strength bar -->
                <div id="strengthBar" style="height:3px;border-radius:3px;background:var(--border);margin-top:.5rem;overflow:hidden;">
                    <div id="strengthFill" style="height:100%;width:0;transition:all .3s;border-radius:3px;"></div>
                </div>
                <div id="strengthText" class="form-hint"></div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label class="form-label" for="password2">Konfirmasi Password</label>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password2"
                        name="password2"
                        class="form-control <?= isset($errors['password2']) ? 'error' : '' ?>"
                        placeholder="Ulangi password"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <?= fieldError($errors, 'password2') ?>
            </div>

            <button type="submit" class="btn btn-accent btn-block btn-lg" style="margin-top:.25rem;">
                Buat Akun →
            </button>
        </form>

        <div class="auth-divider">atau</div>

        <p style="text-align:center;font-size:.9rem;color:var(--text-muted);">
            Sudah punya akun?
            <a href="login.php" style="font-weight:700;">Masuk di sini</a>
        </p>
    </div>
</div>

<script>
// ── Password strength indicator ────────────────────────────
const pwdInput    = document.getElementById('password');
const strengthFill= document.getElementById('strengthFill');
const strengthText= document.getElementById('strengthText');

pwdInput.addEventListener('input', () => {
    const val = pwdInput.value;
    let score = 0;
    if (val.length >= 8)               score++;
    if (/[A-Z]/.test(val))             score++;
    if (/[0-9]/.test(val))             score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    const colors = ['','#ef4444','#f59e0b','#10b981','#00d4ff'];
    const labels = ['','Lemah','Cukup','Kuat','Sangat Kuat'];

    strengthFill.style.width  = (score * 25) + '%';
    strengthFill.style.background = colors[score] || 'transparent';
    strengthText.textContent  = val.length ? ('Kekuatan: ' + labels[score]) : '';
    strengthText.style.color  = colors[score] || 'inherit';
});

// ── Real-time password match ───────────────────────────────
const pwd2Input = document.getElementById('password2');
pwd2Input.addEventListener('input', () => {
    if (pwd2Input.value && pwd2Input.value !== pwdInput.value) {
        pwd2Input.style.borderColor = 'var(--danger)';
    } else {
        pwd2Input.style.borderColor = '';
    }
});
</script>
</body>
</html>