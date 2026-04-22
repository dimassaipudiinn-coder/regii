<?php
// index.php — Homepage / Landing page
require_once 'config.php';

// Redirect logged-in users to their dashboard
if (isLoggedIn()) {
    $target = ($_SESSION['user_role'] === 'admin') ? 'admin.php' : 'dashboard.php';
    header("Location: $target");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — <?= SITE_TAGLINE ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Landing-page specific styles */
        .hero {
            min-height: calc(100vh - var(--navbar-h));
            display: flex; align-items: center;
            position: relative; overflow: hidden;
            padding: 4rem 0;
        }
        .hero-glow-1 {
            position: absolute; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(124,58,237,.18) 0%, transparent 65%);
            top: -100px; left: -100px; pointer-events: none;
            animation: float 8s ease-in-out infinite;
        }
        .hero-glow-2 {
            position: absolute; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(0,212,255,.12) 0%, transparent 65%);
            bottom: -50px; right: -50px; pointer-events: none;
            animation: float 10s ease-in-out infinite reverse;
        }
        @keyframes float {
            0%,100% { transform: translateY(0)   rotate(0deg); }
            50%      { transform: translateY(-30px) rotate(5deg); }
        }
        .hero-content { max-width: 600px; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(0,212,255,.1);
            border: 1px solid rgba(0,212,255,.25);
            border-radius: 20px;
            padding: .35rem 1rem;
            font-size: .8rem; font-weight: 700;
            color: var(--accent); letter-spacing: .06em;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
        }
        .hero-title {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            line-height: 1.1;
            color: #fff;
            margin-bottom: 1.25rem;
        }
        .hero-title .gradient-text {
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-desc { font-size: 1.1rem; color: var(--text-muted); max-width: 480px; line-height: 1.7; margin-bottom: 2rem; }
        .hero-cta   { display: flex; gap: 1rem; flex-wrap: wrap; }
        .hero-visual {
            position: absolute; right: -2rem; top: 50%; transform: translateY(-50%);
            font-size: 18rem; opacity: .04; pointer-events: none;
            animation: float 12s ease-in-out infinite;
            user-select: none;
        }
        .features-section { padding: 5rem 0; border-top: 1px solid var(--border); }
        .section-title { font-size: 2.2rem; text-align: center; color: #fff; margin-bottom: .5rem; }
        .section-sub   { text-align: center; color: var(--text-muted); margin-bottom: 3rem; }
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap: 1.25rem;
        }
        .feature-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.75rem;
            transition: var(--transition); position: relative; overflow: hidden;
        }
        .feature-card:hover { border-color: rgba(0,212,255,.3); transform: translateY(-4px); box-shadow: var(--glow-sm); }
        .feature-icon { font-size: 2.5rem; margin-bottom: 1rem; }
        .feature-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: .5rem; }
        .feature-desc  { font-size: .88rem; color: var(--text-muted); line-height: 1.6; }
        .games-section { padding: 4rem 0; }
        .games-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap: .875rem;
        }
        .game-chip {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 1rem .75rem;
            text-align: center; transition: var(--transition); cursor: default;
        }
        .game-chip:hover { border-color: var(--accent); box-shadow: var(--glow-sm); }
        .game-chip-icon  { font-size: 1.75rem; display: block; margin-bottom: .4rem; }
        .game-chip-name  { font-size: .75rem; font-weight: 700; color: var(--text-muted); }
        .stats-banner {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 2.5rem;
            display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr));
            gap: 2rem; text-align: center; margin: 3rem 0;
        }
        .stats-banner-item {}
        .stats-banner-num  { font-family: var(--font-display); font-size: 2.5rem; font-weight: 700; color: var(--accent); }
        .stats-banner-lbl  { font-size: .85rem; color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand">
        <a href="index.php" class="site-title">⚡<span><?= SITE_NAME ?></span></a>
    </div>
    <button class="navbar-toggle" onclick="toggleNav()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <ul class="navbar-nav" id="navMenu">
        <li><a href="#games">Games</a></li>
        <li><a href="#features">Fitur</a></li>
        <li><a href="login.php" class="btn btn-outline btn-sm">Masuk</a></li>
        <li><a href="register.php" class="btn btn-primary btn-sm">Daftar</a></li>
    </ul>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-glow-1"></div>
    <div class="hero-glow-2"></div>
    <div class="hero-visual">⚡</div>
    <div class="container">
        <div class="hero-content">
            <span class="hero-badge">✨ Platform Top-Up #1</span>
            <h1 class="hero-title">
                Top-Up Games<br>
                <span class="gradient-text">Lebih Cepat.</span>
            </h1>
            <p class="hero-desc">
                Isi ulang diamonds, UC, dan pulsa favorit kamu dengan mudah, aman, dan harga terbaik.
                Proses instan, 24/7 non-stop.
            </p>
            <div class="hero-cta">
                <a href="register.php" class="btn btn-accent btn-lg">Mulai Sekarang →</a>
                <a href="login.php" class="btn btn-outline btn-lg">Sudah Punya Akun</a>
            </div>
        </div>
    </div>
</section>

<!-- STATS BANNER -->
<div class="container">
    <div class="stats-banner">
        <div class="stats-banner-item">
            <div class="stats-banner-num">500K+</div>
            <div class="stats-banner-lbl">Pengguna Aktif</div>
        </div>
        <div class="stats-banner-item">
            <div class="stats-banner-num">2M+</div>
            <div class="stats-banner-lbl">Transaksi Sukses</div>
        </div>
        <div class="stats-banner-item">
            <div class="stats-banner-num">50+</div>
            <div class="stats-banner-lbl">Produk Tersedia</div>
        </div>
        <div class="stats-banner-item">
            <div class="stats-banner-num">99.9%</div>
            <div class="stats-banner-lbl">Uptime Server</div>
        </div>
    </div>
</div>

<!-- GAMES SECTION -->
<section class="games-section" id="games">
    <div class="container">
        <h2 class="section-title">Game Populer</h2>
        <p class="section-sub">Top-up berbagai game favoritmu di satu tempat</p>
        <div class="games-grid">
            <div class="game-chip"><span class="game-chip-icon">💎</span><span class="game-chip-name">Mobile Legends</span></div>
            <div class="game-chip"><span class="game-chip-icon">🔫</span><span class="game-chip-name">Free Fire</span></div>
            <div class="game-chip"><span class="game-chip-icon">🪖</span><span class="game-chip-name">PUBG Mobile</span></div>
            <div class="game-chip"><span class="game-chip-icon">📱</span><span class="game-chip-name">Pulsa</span></div>
            <div class="game-chip"><span class="game-chip-icon">💚</span><span class="game-chip-name">GoPay</span></div>
            <div class="game-chip"><span class="game-chip-icon">💜</span><span class="game-chip-name">OVO</span></div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
    <div class="container">
        <h2 class="section-title">Kenapa TopUpKu?</h2>
        <p class="section-sub">Terpercaya jutaan gamer Indonesia sejak 2024</p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <div class="feature-title">Proses Instan</div>
                <div class="feature-desc">Item langsung masuk ke akun game kamu dalam hitungan detik setelah pembayaran.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <div class="feature-title">100% Aman</div>
                <div class="feature-desc">Sistem keamanan berlapis dengan enkripsi data. Transaksimu selalu terlindungi.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <div class="feature-title">Harga Terbaik</div>
                <div class="feature-desc">Harga kompetitif tanpa biaya tersembunyi. Lebih hemat dibanding toko lainnya.</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎧</div>
                <div class="feature-title">Support 24/7</div>
                <div class="feature-desc">Tim support kami siap membantu kapan saja kamu mengalami masalah.</div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <p>© 2025 <?= SITE_NAME ?> · Dibuat dengan ❤️ untuk para gamer Indonesia</p>
    <p class="mt-1 text-sm">
        <a href="login.php">Login</a> ·
        <a href="register.php">Register</a>
    </p>
</footer>

<script>
function toggleNav() {
    document.getElementById('navMenu').classList.toggle('open');
}
</script>
</body>
</html>