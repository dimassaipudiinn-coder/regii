<?php
// ============================================================
// config.php — Database connection & global helpers
// ============================================================

// ── Database credentials ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'topup_db');
define('DB_USER', 'root');
define('DB_PASS', '');           // Change this in production!
define('DB_CHARSET', 'utf8mb4');

// ── Site settings ───────────────────────────────────────────
define('SITE_NAME', 'TopUpKu');
define('SITE_TAGLINE', 'Top-Up Cepat, Aman & Terpercaya');
define('CURRENCY', 'Rp');

// ── Session start ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

// ── Auth helpers ─────────────────────────────────────────────

/** Check if user is logged in */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/** Redirect if NOT logged in */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

/** Redirect if NOT admin */
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        setFlash('error', 'Akses ditolak. Halaman khusus admin.');
        header('Location: dashboard.php');
        exit;
    }
}

/** Redirect if already logged in */
function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        $target = ($_SESSION['user_role'] === 'admin') ? 'admin.php' : 'dashboard.php';
        header("Location: $target");
        exit;
    }
}

// ── Flash message helpers ────────────────────────────────────

/** Set a one-time flash message */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Retrieve and clear flash message */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Render flash HTML */
function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $type = htmlspecialchars($flash['type']);
    $msg  = htmlspecialchars($flash['message']);
    echo "<div class=\"flash flash-{$type}\" role=\"alert\">{$msg}</div>";
}

// ── Formatting helpers ───────────────────────────────────────

/** Format number as Indonesian Rupiah */
function rupiah(float $amount): string {
    return CURRENCY . ' ' . number_format($amount, 0, ',', '.');
}

/** Sanitize string input */
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
