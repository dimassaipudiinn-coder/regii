<?php
// dashboard.php — User dashboard
require_once 'config.php';
requireLogin();

$pdo    = getDB();
$userId = $_SESSION['user_id'];

// ── Fetch fresh user data ─────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Update session balance (might have changed)
$_SESSION['user_balance'] = $user['balance'];

// ── Fetch transaction history ─────────────────────────────────
$search  = clean($_GET['search']  ?? '');
$filter  = clean($_GET['status']  ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Build query with optional filters
$where  = ["t.user_id = ?"];
$params = [$userId];

if ($search) {
    $where[]  = "(p.game_name LIKE ? OR p.name LIKE ? OR t.target LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($filter, ['success','failed','pending'])) {
    $where[]  = "t.status = ?";
    $params[] = $filter;
}

$whereSQL = implode(' AND ', $where);

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t JOIN products p ON t.product_id = p.id WHERE $whereSQL");
$countStmt->execute($params);
$totalTrx  = $countStmt->fetchColumn();
$totalPages= (int)ceil($totalTrx / $perPage);

// Paginated results
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare("
    SELECT t.*, p.game_name, p.name AS product_name, p.icon
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    WHERE $whereSQL
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// ── Transaction summary stats ─────────────────────────────────
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS success,
        SUM(CASE WHEN status='success' THEN amount ELSE 0 END) AS total_spent
    FROM transactions WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Helper: build URL preserving query params
function pageUrl(int $p, string $search, string $filter): string {
    $q = http_build_query(['page' => $p, 'search' => $search, 'status' => $filter]);
    return 'dashboard.php?' . $q;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
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
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="topup.php">Top-Up</a></li>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <li><a href="admin.php">Admin Panel</a></li>
        <?php endif; ?>
        <li>
            <div class="navbar-balance">
                💰 <?= rupiah($user['balance']) ?>
            </div>
        </li>
        <li>
            <div class="navbar-user">
                <div class="navbar-avatar"><?= $user['avatar'] ?></div>
                <span style="font-weight:700;font-size:.9rem;"><?= clean($user['username']) ?></span>
            </div>
        </li>
        <li><a href="logout.php" class="btn btn-danger btn-sm">Logout</a></li>
    </ul>
</nav>

<div class="main-content">
<div class="container">

    <!-- Flash messages -->
    <?php renderFlash(); ?>

    <!-- Page hero / profile banner -->
    <div class="page-hero" style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
            <div style="font-size:3.5rem;line-height:1;filter:drop-shadow(0 0 12px rgba(0,212,255,.4));">
                <?= $user['avatar'] ?>
            </div>
            <div>
                <h1 class="page-hero-title">Halo, <?= clean($user['username']) ?>! 👋</h1>
                <p class="page-hero-sub">📧 <?= clean($user['email']) ?> &nbsp;·&nbsp;
                    Bergabung <?= date('d M Y', strtotime($user['created_at'])) ?>
                </p>
            </div>
            <div style="margin-left:auto;">
                <a href="topup.php" class="btn btn-accent">⚡ Top-Up Sekarang</a>
            </div>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="stats-grid">
        <div class="stat-card accent">
            <div class="stat-icon">💰</div>
            <div class="stat-label">Saldo</div>
            <div class="stat-value"><?= rupiah($user['balance']) ?></div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">📋</div>
            <div class="stat-label">Total Transaksi</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Sukses</div>
            <div class="stat-value"><?= number_format($stats['success']) ?></div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon">💸</div>
            <div class="stat-label">Total Pengeluaran</div>
            <div class="stat-value"><?= rupiah($stats['total_spent'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Quick actions -->
    <div style="display:flex;gap:.75rem;margin-bottom:2rem;flex-wrap:wrap;">
        <a href="topup.php" class="btn btn-primary">
            ⚡ Top-Up Baru
        </a>
        <button class="btn btn-outline" onclick="document.getElementById('addBalanceModal').style.display='flex'">
            💳 Tambah Saldo
        </button>
    </div>

    <!-- Transaction history card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Riwayat Transaksi</div>
        </div>

        <!-- Search & filter -->
        <form method="GET" action="dashboard.php" class="search-bar">
            <div class="input-group" style="flex:1;min-width:200px;">
                <span class="input-icon">🔍</span>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Cari game, produk, atau ID..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>
            <select name="status" class="form-control" style="width:auto;">
                <option value="">Semua Status</option>
                <option value="success" <?= $filter==='success' ? 'selected' : '' ?>>✅ Sukses</option>
                <option value="failed"  <?= $filter==='failed'  ? 'selected' : '' ?>>❌ Gagal</option>
                <option value="pending" <?= $filter==='pending' ? 'selected' : '' ?>>⏳ Pending</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search || $filter): ?>
            <a href="dashboard.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <?php if (empty($transactions)): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-muted);">
            <div style="font-size:3rem;margin-bottom:.75rem;">📭</div>
            <p>Belum ada transaksi<?= ($search || $filter) ? ' yang cocok' : '' ?>.</p>
            <?php if (!$search && !$filter): ?>
            <a href="topup.php" class="btn btn-accent" style="margin-top:1rem;">Top-Up Pertamamu →</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Game / Produk</th>
                        <th>Target ID</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $i => $trx): ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <span style="font-size:1.2rem;"><?= $trx['icon'] ?></span>
                                <div>
                                    <div style="font-weight:700;font-size:.88rem;color:#fff;"><?= clean($trx['product_name']) ?></div>
                                    <div style="font-size:.75rem;color:var(--text-muted);"><?= clean($trx['game_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="trx-target"><?= clean($trx['target']) ?></td>
                        <td class="trx-amount">-<?= rupiah($trx['amount']) ?></td>
                        <td>
                            <?php
                            $badgeMap = ['success'=>'badge-success','failed'=>'badge-danger','pending'=>'badge-warning'];
                            $iconMap  = ['success'=>'✅','failed'=>'❌','pending'=>'⏳'];
                            $labelMap = ['success'=>'Sukses','failed'=>'Gagal','pending'=>'Pending'];
                            $s = $trx['status'];
                            ?>
                            <span class="badge <?= $badgeMap[$s] ?>">
                                <?= $iconMap[$s] ?> <?= $labelMap[$s] ?>
                            </span>
                        </td>
                        <td class="text-sm text-muted">
                            <?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;flex-wrap:wrap;gap:.5rem;">
            <div class="text-sm text-muted">
                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalTrx) ?> dari <?= $totalTrx ?> transaksi
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="<?= pageUrl($page-1, $search, $filter) ?>" class="page-link">‹</a>
                <?php endif; ?>
                <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                <a href="<?= pageUrl($p, $search, $filter) ?>" class="page-link <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="<?= pageUrl($page+1, $search, $filter) ?>" class="page-link">›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div><!-- /container -->
</div><!-- /main-content -->

<!-- ADD BALANCE MODAL (Demo — in real app this links to payment gateway) -->
<div id="addBalanceModal" class="modal-overlay" style="display:none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">💳 Tambah Saldo</h3>
            <button class="modal-close" onclick="document.getElementById('addBalanceModal').style.display='none'">✕</button>
        </div>
        <p class="text-muted text-sm" style="margin-bottom:1.25rem;">
            Pilih nominal yang ingin ditambahkan ke saldo akun kamu.
        </p>
        <form method="POST" action="add_balance.php">
            <div class="form-group">
                <label class="form-label">Pilih Nominal</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1rem;">
                    <?php foreach ([10000,25000,50000,100000,200000,500000] as $amt): ?>
                    <label style="cursor:pointer;">
                        <input type="radio" name="amount" value="<?= $amt ?>" style="display:none;" class="balance-radio">
                        <div class="product-card" style="padding:.75rem;font-size:.9rem;font-weight:700;" onclick="selectBalance(this, <?= $amt ?>)">
                            <?= rupiah($amt) ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <input type="number" name="custom_amount" class="form-control" placeholder="Atau ketik nominal lain..." min="10000" step="1000">
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="button" class="btn btn-outline btn-block" onclick="document.getElementById('addBalanceModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-accent btn-block">Bayar Sekarang</button>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>© 2025 <?= SITE_NAME ?> · Semua transaksi dilindungi enkripsi</p>
</footer>

<script>
function toggleNav() { document.getElementById('navMenu').classList.toggle('open'); }

function closeModalOnBackdrop(e) {
    if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
}

function selectBalance(el, amount) {
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
}
</script>
</body>
</html>