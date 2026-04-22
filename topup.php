<?php
// topup.php — Top-Up product page & purchase handler
require_once 'config.php';
requireLogin();

$pdo    = getDB();
$userId = $_SESSION['user_id'];

// ── Handle POST (purchase) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $target    = clean($_POST['target'] ?? '');

    $errorMsg = null;

    // 1. Validate inputs
    if (!$productId || empty($target)) {
        $errorMsg = 'Semua field harus diisi.';
    } elseif (strlen($target) < 3) {
        $errorMsg = 'ID / nomor terlalu pendek.';
    } else {
        // 2. Fetch product
        $pStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();

        if (!$product) {
            $errorMsg = 'Produk tidak ditemukan atau tidak tersedia.';
        } else {
            // 3. Fetch current user balance (fresh from DB — not from session)
            $uStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $uStmt->execute([$userId]);
            $currentBalance = (float)$uStmt->fetchColumn();

            if ($currentBalance < $product['price']) {
                $errorMsg = 'Saldo tidak mencukupi. Saldo kamu: ' . rupiah($currentBalance) .
                            ', dibutuhkan: ' . rupiah($product['price']) . '.';
            } else {
                // 4. Perform transaction atomically
                try {
                    $pdo->beginTransaction();

                    // Deduct balance
                    $deductStmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
                    $deductStmt->execute([$product['price'], $userId, $product['price']]);

                    if ($deductStmt->rowCount() === 0) {
                        // Race condition guard — someone else changed balance
                        throw new Exception('Saldo tidak mencukupi.');
                    }

                    // Record transaction
                    $txStmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, product_id, target, amount, status)
                        VALUES (?, ?, ?, ?, 'success')
                    ");
                    $txStmt->execute([$userId, $productId, $target, $product['price']]);

                    $pdo->commit();

                    // Update session balance
                    $_SESSION['user_balance'] = $currentBalance - $product['price'];

                    setFlash('success',
                        '✅ Top-up berhasil! ' . $product['icon'] . ' ' .
                        $product['name'] . ' untuk ' . htmlspecialchars($target) .
                        ' sudah dikirim.'
                    );
                    header('Location: dashboard.php');
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();

                    // Record failed transaction
                    try {
                        $failStmt = $pdo->prepare("
                            INSERT INTO transactions (user_id, product_id, target, amount, status, notes)
                            VALUES (?, ?, ?, ?, 'failed', ?)
                        ");
                        $failStmt->execute([$userId, $productId, $target, $product['price'], $e->getMessage()]);
                    } catch (Exception) { /* ignore logging error */ }

                    $errorMsg = 'Transaksi gagal: ' . $e->getMessage();
                }
            }
        }
    }

    if ($errorMsg) {
        setFlash('error', $errorMsg);
        header('Location: topup.php');
        exit;
    }
}

// ── Fetch products grouped by category ────────────────────────
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY category, game_name, price")->fetchAll();

// Group products
$grouped    = [];
$categories = [];
foreach ($products as $p) {
    $cat = $p['category'];
    $grouped[$cat][] = $p;
    $categories[$cat] = true;
}
$categories = array_keys($categories);

// Fetch fresh balance
$balanceStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$balanceStmt->execute([$userId]);
$balance = (float)$balanceStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top-Up — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .topup-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 1.5rem;
            align-items: start;
        }
        .sticky-panel {
            position: sticky;
            top: calc(var(--navbar-h) + 1.25rem);
        }
        .order-summary {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
        }
        .order-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .6rem 0;
            border-bottom: 1px solid rgba(30,45,69,.5);
            font-size: .9rem;
        }
        .order-row:last-child { border-bottom: none; }
        .order-label { color: var(--text-muted); }
        .order-value { font-weight: 700; color: #fff; }
        .order-total .order-value { color: var(--accent3); font-size: 1.1rem; }

        @media (max-width: 900px) {
            .topup-layout { grid-template-columns: 1fr; }
            .sticky-panel { position: static; }
        }
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
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="topup.php" class="active">Top-Up</a></li>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <li><a href="admin.php">Admin Panel</a></li>
        <?php endif; ?>
        <li>
            <div class="navbar-balance">💰 <?= rupiah($balance) ?></div>
        </li>
        <li><a href="logout.php" class="btn btn-danger btn-sm">Logout</a></li>
    </ul>
</nav>

<div class="main-content">
<div class="container">

    <?php renderFlash(); ?>

    <div class="page-hero">
        <h1 class="page-hero-title">⚡ Top-Up</h1>
        <p class="page-hero-sub">Pilih produk, masukkan ID, dan selesaikan pembayaran dalam detik.</p>
    </div>

    <div class="topup-layout">
        <!-- LEFT: Product selection + ID input -->
        <div>
            <!-- Category tabs -->
            <div class="category-tabs" id="categoryTabs">
                <button class="category-tab active" onclick="filterCategory('all', this)">🌐 Semua</button>
                <?php foreach ($categories as $cat): ?>
                <button class="category-tab" onclick="filterCategory('<?= htmlspecialchars($cat) ?>', this)">
                    <?= $cat === 'Game' ? '🎮' : ($cat === 'Pulsa' ? '📱' : '💳') ?>
                    <?= htmlspecialchars($cat) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Products grid -->
            <form method="POST" action="topup.php" id="topupForm">
                <input type="hidden" name="product_id" id="selectedProductId" value="">

                <?php foreach ($grouped as $category => $catProducts): ?>
                <div class="product-section mb-2" data-category="<?= htmlspecialchars($category) ?>">
                    <h3 style="font-family:var(--font-display);font-size:.85rem;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;
                        margin-bottom:.875rem;">
                        <?php
                        $catIcons = ['Game'=>'🎮','Pulsa'=>'📱','E-Wallet'=>'💳'];
                        echo ($catIcons[$category] ?? '📦') . ' ' . htmlspecialchars($category);
                        ?>
                    </h3>
                    <div class="products-grid" style="margin-bottom:1.5rem;">
                        <?php foreach ($catProducts as $prod): ?>
                        <div
                            class="product-card"
                            data-id="<?= $prod['id'] ?>"
                            data-price="<?= $prod['price'] ?>"
                            data-name="<?= htmlspecialchars($prod['name']) ?>"
                            data-game="<?= htmlspecialchars($prod['game_name']) ?>"
                            data-icon="<?= $prod['icon'] ?>"
                            onclick="selectProduct(this)"
                        >
                            <div class="product-icon"><?= $prod['icon'] ?></div>
                            <div class="product-name"><?= clean($prod['name']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;"><?= clean($prod['game_name']) ?></div>
                            <div class="product-price"><?= rupiah($prod['price']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Target ID input (shown after product selected) -->
                <div id="targetSection" class="card" style="display:none;margin-top:.5rem;">
                    <div class="card-header">
                        <div class="card-title">🎯 Masukkan Target</div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" id="targetLabel">ID / Nomor</label>
                        <div class="input-group">
                            <span class="input-icon" id="targetIcon">🎮</span>
                            <input
                                type="text"
                                name="target"
                                id="targetInput"
                                class="form-control"
                                placeholder="Masukkan ID akun atau nomor..."
                                maxlength="100"
                                required
                            >
                        </div>
                        <div class="form-hint" id="targetHint">Pastikan ID yang kamu masukkan sudah benar.</div>
                    </div>
                </div>
            </form>
        </div>

        <!-- RIGHT: Order summary panel -->
        <div class="sticky-panel">
            <div class="order-summary">
                <div class="card-title" style="margin-bottom:1rem;">🛒 Ringkasan Pesanan</div>

                <div id="emptyState" style="text-align:center;padding:2rem 0;color:var(--text-muted);">
                    <div style="font-size:2.5rem;margin-bottom:.5rem;">🎮</div>
                    <p style="font-size:.88rem;">Pilih produk terlebih dahulu</p>
                </div>

                <div id="orderDetails" style="display:none;">
                    <div class="order-row">
                        <span class="order-label">Produk</span>
                        <span class="order-value" id="summaryName">—</span>
                    </div>
                    <div class="order-row">
                        <span class="order-label">Game</span>
                        <span class="order-value text-muted" id="summaryGame">—</span>
                    </div>
                    <div class="order-row">
                        <span class="order-label">Target</span>
                        <span class="order-value" id="summaryTarget">—</span>
                    </div>
                    <div class="order-row order-total">
                        <span class="order-label">Total Bayar</span>
                        <span class="order-value" id="summaryPrice">—</span>
                    </div>
                </div>

                <!-- Balance check -->
                <div id="balanceInfo" style="margin:1rem 0;padding:.875rem;background:var(--bg-card2);border-radius:10px;font-size:.85rem;">
                    <div style="display:flex;justify-content:space-between;">
                        <span class="text-muted">Saldo kamu</span>
                        <span style="font-weight:700;color:var(--accent3);"><?= rupiah($balance) ?></span>
                    </div>
                    <div id="balanceAfter" style="display:flex;justify-content:space-between;margin-top:.35rem;display:none;">
                        <span class="text-muted">Saldo setelah</span>
                        <span id="balanceAfterVal" style="font-weight:700;color:var(--success);">—</span>
                    </div>
                </div>

                <div id="insufficientWarning" class="flash flash-error" style="display:none;margin-bottom:.875rem;">
                    ⚠️ Saldo tidak mencukupi!
                    <a href="#" onclick="document.getElementById('addBalanceModal').style.display='flex'" style="color:inherit;text-decoration:underline;">Tambah saldo</a>
                </div>

                <button
                    type="button"
                    id="confirmBtn"
                    class="btn btn-accent btn-block btn-lg"
                    disabled
                    onclick="confirmPurchase()"
                >
                    Beli Sekarang
                </button>
                <p style="font-size:.75rem;color:var(--text-muted);text-align:center;margin-top:.75rem;">
                    Dengan membeli kamu menyetujui syarat &amp; ketentuan kami
                </p>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Confirm Purchase Modal -->
<div id="confirmModal" class="modal-overlay" style="display:none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">⚡ Konfirmasi Pembelian</h3>
            <button class="modal-close" onclick="document.getElementById('confirmModal').style.display='none'">✕</button>
        </div>
        <div style="text-align:center;padding:1.25rem 0;">
            <div id="modalIcon" style="font-size:3.5rem;margin-bottom:1rem;filter:drop-shadow(0 0 12px rgba(0,212,255,.4));"></div>
            <div id="modalProductName" style="font-family:var(--font-display);font-size:1.3rem;font-weight:700;color:#fff;margin-bottom:.25rem;"></div>
            <div id="modalGameName" style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;"></div>
            <div style="background:var(--bg-card2);border-radius:10px;padding:1rem;text-align:left;margin-bottom:1.25rem;">
                <div style="display:flex;justify-content:space-between;padding:.3rem 0;">
                    <span class="text-muted text-sm">Target</span>
                    <span id="modalTarget" style="font-weight:700;"></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:.3rem 0;border-top:1px solid var(--border);">
                    <span class="text-muted text-sm">Total Bayar</span>
                    <span id="modalPrice" style="font-weight:700;color:var(--accent3);"></span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button class="btn btn-outline btn-block" onclick="document.getElementById('confirmModal').style.display='none'">Batal</button>
            <button class="btn btn-accent btn-block" onclick="submitForm()" id="finalBuyBtn">
                ✅ Konfirmasi Beli
            </button>
        </div>
    </div>
</div>

<!-- Add Balance Modal -->
<div id="addBalanceModal" class="modal-overlay" style="display:none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">💳 Tambah Saldo</h3>
            <button class="modal-close" onclick="document.getElementById('addBalanceModal').style.display='none'">✕</button>
        </div>
        <form method="POST" action="add_balance.php">
            <div class="form-group">
                <label class="form-label">Pilih Nominal</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                    <?php foreach ([10000,25000,50000,100000,200000,500000] as $amt): ?>
                    <label style="cursor:pointer;">
                        <div class="product-card" style="padding:.75rem;font-size:.9rem;font-weight:700;text-align:center;"
                             onclick="this.classList.toggle('selected')">
                            <?= rupiah($amt) ?>
                            <input type="radio" name="amount" value="<?= $amt ?>" style="display:none;">
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                <button type="button" class="btn btn-outline btn-block" onclick="document.getElementById('addBalanceModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-accent btn-block">Bayar</button>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>© 2025 <?= SITE_NAME ?> · Semua transaksi dilindungi enkripsi</p>
</footer>

<script>
const userBalance = <?= $balance ?>;
let selectedProduct = null;

// ── Product selection ────────────────────────────────────────
function selectProduct(el) {
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');

    selectedProduct = {
        id:    el.dataset.id,
        price: parseFloat(el.dataset.price),
        name:  el.dataset.name,
        game:  el.dataset.game,
        icon:  el.dataset.icon,
    };

    document.getElementById('selectedProductId').value = selectedProduct.id;

    // Show target input
    const targetSection = document.getElementById('targetSection');
    targetSection.style.display = 'block';
    targetSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Adjust label based on category
    const isPulsa = selectedProduct.game === 'Pulsa' || selectedProduct.game === 'GoPay' || selectedProduct.game === 'OVO';
    document.getElementById('targetLabel').textContent = isPulsa ? 'Nomor HP' : 'Game ID / User ID';
    document.getElementById('targetIcon').textContent  = isPulsa ? '📱' : '🎮';
    document.getElementById('targetHint').textContent  = isPulsa
        ? 'Masukkan nomor HP yang valid (contoh: 0812xxxxxxxx)'
        : 'Masukkan ID akun game kamu (bisa dilihat di profil game)';

    updateSummary();
}

// ── Update summary panel ──────────────────────────────────────
function updateSummary() {
    if (!selectedProduct) return;

    const target = document.getElementById('targetInput').value.trim();

    document.getElementById('emptyState').style.display   = 'none';
    document.getElementById('orderDetails').style.display = 'block';

    document.getElementById('summaryName').textContent   = selectedProduct.icon + ' ' + selectedProduct.name;
    document.getElementById('summaryGame').textContent   = selectedProduct.game;
    document.getElementById('summaryTarget').textContent = target || '—';
    document.getElementById('summaryPrice').textContent  = formatRupiah(selectedProduct.price);

    const afterBalance = userBalance - selectedProduct.price;
    const balanceAfterEl = document.getElementById('balanceAfter');
    balanceAfterEl.style.display = 'flex';
    document.getElementById('balanceAfterVal').textContent = formatRupiah(afterBalance);
    document.getElementById('balanceAfterVal').style.color = afterBalance < 0 ? 'var(--danger)' : 'var(--success)';

    const insufficient = selectedProduct.price > userBalance;
    document.getElementById('insufficientWarning').style.display = insufficient ? 'flex' : 'none';

    const btn = document.getElementById('confirmBtn');
    btn.disabled = insufficient || !target;
}

// ── Target input change ───────────────────────────────────────
document.getElementById('targetInput').addEventListener('input', updateSummary);

// ── Category filter ───────────────────────────────────────────
function filterCategory(cat, btn) {
    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.product-section').forEach(section => {
        if (cat === 'all' || section.dataset.category === cat) {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    });
}

// ── Confirm purchase modal ────────────────────────────────────
function confirmPurchase() {
    if (!selectedProduct) return;
    const target = document.getElementById('targetInput').value.trim();
    if (!target) { alert('Masukkan ID / nomor target terlebih dahulu!'); return; }

    document.getElementById('modalIcon').textContent        = selectedProduct.icon;
    document.getElementById('modalProductName').textContent = selectedProduct.name;
    document.getElementById('modalGameName').textContent    = selectedProduct.game;
    document.getElementById('modalTarget').textContent      = target;
    document.getElementById('modalPrice').textContent       = formatRupiah(selectedProduct.price);

    document.getElementById('confirmModal').style.display = 'flex';
}

function submitForm() {
    document.getElementById('finalBuyBtn').textContent = '⏳ Memproses...';
    document.getElementById('finalBuyBtn').disabled = true;
    document.getElementById('topupForm').submit();
}

// ── Helpers ───────────────────────────────────────────────────
function formatRupiah(num) {
    return 'Rp ' + num.toLocaleString('id-ID');
}

function closeModalOnBackdrop(e) {
    if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
}

function toggleNav() { document.getElementById('navMenu').classList.toggle('open'); }
</script>
</body>
</html>