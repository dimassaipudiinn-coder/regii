<?php
// admin.php — Admin panel: users, transactions, products management
require_once 'config.php';
requireAdmin();

$pdo    = getDB();
$tab    = clean($_GET['tab'] ?? 'dashboard');
$msg    = '';
$error  = '';

// ══════════════════════════════════════════════════════════════
// HANDLE ACTIONS (POST requests)
// ══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');

    // ── Add product ───────────────────────────────────────────
    if ($action === 'add_product') {
        $gameName = clean($_POST['game_name'] ?? '');
        $name     = clean($_POST['name']      ?? '');
        $price    = (float)($_POST['price']   ?? 0);
        $desc     = clean($_POST['description'] ?? '');
        $icon     = clean($_POST['icon']      ?? '💎');
        $category = clean($_POST['category']  ?? 'Game');

        if (!$gameName || !$name || $price <= 0) {
            $error = 'Semua field wajib diisi dan harga harus > 0.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO products (game_name, name, price, description, icon, category) VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$gameName, $name, $price, $desc, $icon, $category]);
            setFlash('success', '✅ Produk berhasil ditambahkan.');
        }
        $tab = 'products';
    }

    // ── Edit product ──────────────────────────────────────────
    elseif ($action === 'edit_product') {
        $id       = (int)$_POST['product_id'];
        $gameName = clean($_POST['game_name'] ?? '');
        $name     = clean($_POST['name']      ?? '');
        $price    = (float)($_POST['price']   ?? 0);
        $desc     = clean($_POST['description'] ?? '');
        $icon     = clean($_POST['icon']      ?? '💎');
        $category = clean($_POST['category']  ?? 'Game');
        $active   = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "UPDATE products SET game_name=?, name=?, price=?, description=?, icon=?, category=?, is_active=? WHERE id=?"
        );
        $stmt->execute([$gameName, $name, $price, $desc, $icon, $category, $active, $id]);
        setFlash('success', '✅ Produk berhasil diperbarui.');
        $tab = 'products';
    }

    // ── Delete product ────────────────────────────────────────
    elseif ($action === 'delete_product') {
        $id   = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', '🗑️ Produk berhasil dihapus.');
        $tab = 'products';
    }

    // ── Adjust user balance ───────────────────────────────────
    elseif ($action === 'adjust_balance') {
        $userId    = (int)$_POST['user_id'];
        $newBalance= (float)$_POST['new_balance'];
        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);
        setFlash('success', '💰 Saldo user berhasil diperbarui.');
        $tab = 'users';
    }

    // ── Delete user ───────────────────────────────────────────
    elseif ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid === $_SESSION['user_id']) {
            setFlash('error', 'Tidak bisa menghapus akun sendiri!');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$uid]);
            setFlash('success', '🗑️ User berhasil dihapus.');
        }
        $tab = 'users';
    }

    // Redirect to avoid form resubmission
    header("Location: admin.php?tab=$tab");
    exit;
}

// ══════════════════════════════════════════════════════════════
// FETCH DATA FOR CURRENT TAB
// ══════════════════════════════════════════════════════════════

// Dashboard stats
$totalUsers  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalTrx    = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$totalRevenue= $pdo->query("SELECT SUM(amount) FROM transactions WHERE status='success'")->fetchColumn() ?? 0;
$totalProducts=$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();

// Users tab
$usersSearch = clean($_GET['uq'] ?? '');
$usersPage   = max(1,(int)($_GET['up'] ?? 1));
$usersPerPage= 15;
$usersOffset = ($usersPage - 1) * $usersPerPage;
$usersWhere  = $usersSearch ? "WHERE username LIKE ? OR email LIKE ?" : "";
$usersParams = $usersSearch ? ["%$usersSearch%", "%$usersSearch%"] : [];
$usersCount  = $pdo->prepare("SELECT COUNT(*) FROM users $usersWhere");
$usersCount->execute($usersParams);
$usersTotalCount = $usersCount->fetchColumn();
$usersParams[] = $usersPerPage;
$usersParams[] = $usersOffset;
$usersStmt   = $pdo->prepare("SELECT * FROM users $usersWhere ORDER BY created_at DESC LIMIT ? OFFSET ?");
$usersStmt->execute($usersParams);
$users       = $usersStmt->fetchAll();

// Transactions tab
$trxSearch   = clean($_GET['tq'] ?? '');
$trxStatus   = clean($_GET['ts'] ?? '');
$trxPage     = max(1,(int)($_GET['tp'] ?? 1));
$trxPerPage  = 15;
$trxOffset   = ($trxPage - 1) * $trxPerPage;
$trxWhere    = [];
$trxParams   = [];
if ($trxSearch) {
    $trxWhere[]  = "(u.username LIKE ? OR p.name LIKE ? OR t.target LIKE ?)";
    $trxParams[] = "%$trxSearch%";
    $trxParams[] = "%$trxSearch%";
    $trxParams[] = "%$trxSearch%";
}
if (in_array($trxStatus, ['success','failed','pending'])) {
    $trxWhere[]  = "t.status = ?";
    $trxParams[] = $trxStatus;
}
$trxWhereSQL  = $trxWhere ? "WHERE " . implode(' AND ', $trxWhere) : "";
$trxCountStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id=u.id JOIN products p ON t.product_id=p.id $trxWhereSQL");
$trxCountStmt->execute($trxParams);
$trxTotalCount= $trxCountStmt->fetchColumn();
$trxParamsFull= [...$trxParams, $trxPerPage, $trxOffset];
$trxStmt      = $pdo->prepare("
    SELECT t.*, u.username, u.avatar, p.name AS product_name, p.game_name, p.icon
    FROM transactions t
    JOIN users u    ON t.user_id    = u.id
    JOIN products p ON t.product_id = p.id
    $trxWhereSQL
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
");
$trxStmt->execute($trxParamsFull);
$allTransactions = $trxStmt->fetchAll();

// Products tab
$products = $pdo->query("SELECT * FROM products ORDER BY category, game_name, price")->fetchAll();

// Recent activity for dashboard tab
$recentTrx = $pdo->query("
    SELECT t.*, u.username, p.name AS product_name, p.icon
    FROM transactions t JOIN users u ON t.user_id=u.id JOIN products p ON t.product_id=p.id
    ORDER BY t.created_at DESC LIMIT 5
")->fetchAll();

// Edit product data
$editProduct = null;
if (isset($_GET['edit_product'])) {
    $ep = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $ep->execute([(int)$_GET['edit_product']]);
    $editProduct = $ep->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-stat-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand">
        <a href="index.php" class="site-title">⚡<span><?= SITE_NAME ?></span></a>
    </div>
    <button class="navbar-toggle" onclick="toggleNav()"><span></span><span></span><span></span></button>
    <ul class="navbar-nav" id="navMenu">
        <li><a href="dashboard.php">Dashboard User</a></li>
        <li><a href="admin.php" class="active">Admin Panel</a></li>
        <li>
            <div class="navbar-user">
                <div class="navbar-avatar"><?= $_SESSION['user_avatar'] ?></div>
                <span style="font-weight:700;font-size:.9rem;"><?= clean($_SESSION['user_username']) ?></span>
                <span class="badge badge-admin" style="margin-left:.25rem;">Admin</span>
            </div>
        </li>
        <li><a href="logout.php" class="btn btn-danger btn-sm">Logout</a></li>
    </ul>
</nav>

<div class="main-content">
<div class="container">

<?php renderFlash(); ?>

<div class="admin-grid">
<!-- SIDEBAR -->
<aside class="admin-sidebar">
    <div style="font-family:var(--font-display);font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;padding:.25rem .875rem;margin-bottom:.5rem;">
        Admin Menu
    </div>
    <ul class="admin-nav">
        <li><a href="admin.php?tab=dashboard"  class="<?= $tab==='dashboard' ?'active':'' ?>">📊 Overview</a></li>
        <li><a href="admin.php?tab=users"      class="<?= $tab==='users'     ?'active':'' ?>">👥 Users</a></li>
        <li><a href="admin.php?tab=transactions" class="<?= $tab==='transactions'?'active':'' ?>">📋 Transaksi</a></li>
        <li><a href="admin.php?tab=products"   class="<?= $tab==='products'  ?'active':'' ?>">🛒 Produk</a></li>
    </ul>
    <div style="border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem;">
        <a href="dashboard.php" style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--text-muted);padding:.4rem .875rem;">
            ← Kembali ke User Area
        </a>
    </div>
</aside>

<!-- MAIN PANEL -->
<div>

<?php if ($tab === 'dashboard'): ?>
<!-- ════════ OVERVIEW TAB ════════ -->
<div class="page-hero">
    <h1 class="page-hero-title">📊 Admin Overview</h1>
    <p class="page-hero-sub">Pantau aktivitas platform secara real-time</p>
</div>

<div class="stats-grid" style="margin-bottom:2rem;">
    <div class="stat-card accent">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Total Transaksi</div>
        <div class="stat-value"><?= number_format($totalTrx) ?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Revenue</div>
        <div class="stat-value" style="font-size:1.1rem;"><?= rupiah($totalRevenue) ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">🛒</div>
        <div class="stat-label">Produk Aktif</div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">🕐 Transaksi Terbaru</div></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Produk</th><th>Target</th><th>Jumlah</th><th>Status</th><th>Waktu</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentTrx as $r): ?>
                <tr>
                    <td style="display:flex;align-items:center;gap:.5rem;">
                        <span><?= $r['username'] ?></span>
                    </td>
                    <td><?= $r['icon'] ?> <?= clean($r['product_name']) ?></td>
                    <td class="trx-target"><?= clean($r['target']) ?></td>
                    <td class="trx-amount">-<?= rupiah($r['amount']) ?></td>
                    <td>
                        <?php $bm=['success'=>'badge-success','failed'=>'badge-danger','pending'=>'badge-warning'];
                              $lm=['success'=>'✅ Sukses','failed'=>'❌ Gagal','pending'=>'⏳ Pending']; ?>
                        <span class="badge <?= $bm[$r['status']] ?>"><?= $lm[$r['status']] ?></span>
                    </td>
                    <td class="text-sm text-muted"><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php elseif ($tab === 'users'): ?>
<!-- ════════ USERS TAB ════════ -->
<div class="page-hero">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="page-hero-title">👥 Manajemen Users</h1>
            <p class="page-hero-sub"><?= number_format($usersTotalCount) ?> user terdaftar</p>
        </div>
    </div>
</div>

<form method="GET" action="admin.php" class="search-bar">
    <input type="hidden" name="tab" value="users">
    <div class="input-group" style="flex:1;">
        <span class="input-icon">🔍</span>
        <input type="text" name="uq" class="form-control" placeholder="Cari username atau email..." value="<?= htmlspecialchars($usersSearch) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Cari</button>
    <?php if ($usersSearch): ?><a href="admin.php?tab=users" class="btn btn-outline">Reset</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>User</th><th>Email</th><th>Saldo</th><th>Role</th><th>Bergabung</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $usersOffset + $i + 1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),var(--accent));display:flex;align-items:center;justify-content:center;font-size:.9rem;">
                                <?= $u['avatar'] ?>
                            </div>
                            <span style="font-weight:700;"><?= clean($u['username']) ?></span>
                        </div>
                    </td>
                    <td class="text-sm text-muted"><?= clean($u['email']) ?></td>
                    <td style="color:var(--accent3);font-weight:700;"><?= rupiah($u['balance']) ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin' ? 'badge-admin' : 'badge-info' ?>">
                            <?= $u['role'] === 'admin' ? '👑 Admin' : '👤 User' ?>
                        </span>
                    </td>
                    <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:.35rem;">
                            <button class="btn btn-outline btn-sm"
                                onclick="openEditBalance(<?= $u['id'] ?>, '<?= clean($u['username']) ?>', <?= $u['balance'] ?>)">
                                💰
                            </button>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <form method="POST" action="admin.php?tab=users" onsubmit="return confirm('Hapus user <?= clean($u['username']) ?>?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <?php $usersTotalPages = (int)ceil($usersTotalCount / $usersPerPage); if ($usersTotalPages > 1): ?>
    <div class="pagination" style="margin-top:1rem;">
        <?php for ($p=1; $p<=$usersTotalPages; $p++): ?>
        <a href="admin.php?tab=users&up=<?= $p ?>&uq=<?= urlencode($usersSearch) ?>"
           class="page-link <?= $p===$usersPage?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>


<?php elseif ($tab === 'transactions'): ?>
<!-- ════════ TRANSACTIONS TAB ════════ -->
<div class="page-hero">
    <h1 class="page-hero-title">📋 Semua Transaksi</h1>
    <p class="page-hero-sub"><?= number_format($trxTotalCount) ?> transaksi total</p>
</div>

<form method="GET" action="admin.php" class="search-bar">
    <input type="hidden" name="tab" value="transactions">
    <div class="input-group" style="flex:1;">
        <span class="input-icon">🔍</span>
        <input type="text" name="tq" class="form-control" placeholder="Cari user, produk, atau target..." value="<?= htmlspecialchars($trxSearch) ?>">
    </div>
    <select name="ts" class="form-control" style="width:auto;">
        <option value="">Semua Status</option>
        <option value="success" <?= $trxStatus==='success'?'selected':'' ?>>✅ Sukses</option>
        <option value="failed"  <?= $trxStatus==='failed' ?'selected':'' ?>>❌ Gagal</option>
        <option value="pending" <?= $trxStatus==='pending'?'selected':'' ?>>⏳ Pending</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($trxSearch || $trxStatus): ?><a href="admin.php?tab=transactions" class="btn btn-outline">Reset</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>User</th><th>Produk</th><th>Target</th><th>Jumlah</th><th>Status</th><th>Waktu</th></tr>
            </thead>
            <tbody>
                <?php foreach ($allTransactions as $i => $t):
                    $bm=['success'=>'badge-success','failed'=>'badge-danger','pending'=>'badge-warning'];
                    $lm=['success'=>'✅ Sukses','failed'=>'❌ Gagal','pending'=>'⏳ Pending'];
                ?>
                <tr>
                    <td class="text-muted text-sm"><?= $trxOffset + $i + 1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <span style="font-size:.9rem;"><?= $t['avatar'] ?></span>
                            <span style="font-weight:600;font-size:.88rem;"><?= clean($t['username']) ?></span>
                        </div>
                    </td>
                    <td>
                        <span style="font-size:1rem;"><?= $t['icon'] ?></span>
                        <span style="font-size:.85rem;"><?= clean($t['product_name']) ?></span>
                    </td>
                    <td class="trx-target"><?= clean($t['target']) ?></td>
                    <td class="trx-amount">-<?= rupiah($t['amount']) ?></td>
                    <td><span class="badge <?= $bm[$t['status']] ?>"><?= $lm[$t['status']] ?></span></td>
                    <td class="text-sm text-muted"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $trxTotalPages=(int)ceil($trxTotalCount/$trxPerPage); if ($trxTotalPages > 1): ?>
    <div class="pagination" style="margin-top:1rem;">
        <?php for ($p=1; $p<=$trxTotalPages; $p++): ?>
        <a href="admin.php?tab=transactions&tp=<?= $p ?>&tq=<?= urlencode($trxSearch) ?>&ts=<?= $trxStatus ?>"
           class="page-link <?= $p===$trxPage?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>


<?php elseif ($tab === 'products'): ?>
<!-- ════════ PRODUCTS TAB ════════ -->
<div class="page-hero">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="page-hero-title">🛒 Manajemen Produk</h1>
            <p class="page-hero-sub"><?= count($products) ?> produk terdaftar</p>
        </div>
        <button class="btn btn-accent" onclick="document.getElementById('addProductModal').style.display='flex'">
            + Tambah Produk
        </button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Icon</th><th>Game / Produk</th><th>Nominal</th><th>Harga</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $i => $p): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $i+1 ?></td>
                    <td style="font-size:1.4rem;"><?= $p['icon'] ?></td>
                    <td>
                        <div style="font-weight:700;font-size:.88rem;color:#fff;"><?= clean($p['name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);"><?= clean($p['game_name']) ?></div>
                    </td>
                    <td style="font-size:.82rem;color:var(--text-muted);"><?= clean($p['description']) ?></td>
                    <td style="font-weight:700;color:var(--accent3);"><?= rupiah($p['price']) ?></td>
                    <td><span class="badge badge-info"><?= clean($p['category']) ?></span></td>
                    <td>
                        <span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $p['is_active'] ? '✅ Aktif' : '❌ Nonaktif' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem;">
                            <a href="admin.php?tab=products&edit_product=<?= $p['id'] ?>" class="btn btn-outline btn-sm">✏️</a>
                            <form method="POST" onsubmit="return confirm('Hapus produk ini?')">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
</div><!-- /main panel -->
</div><!-- /admin-grid -->
</div>
</div>

<!-- ════════ MODALS ════════ -->

<!-- Edit Balance Modal -->
<div id="editBalanceModal" class="modal-overlay" style="display:none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">💰 Edit Saldo User</h3>
            <button class="modal-close" onclick="document.getElementById('editBalanceModal').style.display='none'">✕</button>
        </div>
        <form method="POST" action="admin.php?tab=users">
            <input type="hidden" name="action" value="adjust_balance">
            <input type="hidden" name="user_id" id="balanceUserId">
            <div class="form-group">
                <label class="form-label">User: <span id="balanceUsername" style="color:var(--accent);font-style:normal;text-transform:none;"></span></label>
                <input type="number" name="new_balance" id="newBalanceInput" class="form-control" min="0" step="1000" required>
            </div>
            <div style="display:flex;gap:.75rem;">
                <button type="button" class="btn btn-outline btn-block" onclick="document.getElementById('editBalanceModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-accent btn-block">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal-overlay" style="display:none;" onclick="closeModalOnBackdrop(event)">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">➕ Tambah Produk</h3>
            <button class="modal-close" onclick="document.getElementById('addProductModal').style.display='none'">✕</button>
        </div>
        <form method="POST" action="admin.php?tab=products">
            <input type="hidden" name="action" value="add_product">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Game</label>
                    <input type="text" name="game_name" class="form-control" placeholder="Mobile Legends" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="name" class="form-control" placeholder="100 Diamonds" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" class="form-control" placeholder="15000" min="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-control">
                        <option>Game</option>
                        <option>Pulsa</option>
                        <option>E-Wallet</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon (Emoji)</label>
                    <input type="text" name="icon" class="form-control" placeholder="💎" maxlength="5" value="💎">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="description" class="form-control" placeholder="Deskripsi singkat">
                </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                <button type="button" class="btn btn-outline btn-block" onclick="document.getElementById('addProductModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-accent btn-block">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<?php if ($editProduct): ?>
<div id="editProductModal" class="modal-overlay" style="display:flex;" onclick="closeModalOnBackdrop(event)">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Produk</h3>
            <a href="admin.php?tab=products" class="modal-close">✕</a>
        </div>
        <form method="POST" action="admin.php?tab=products">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Game</label>
                    <input type="text" name="game_name" class="form-control" value="<?= clean($editProduct['game_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="name" class="form-control" value="<?= clean($editProduct['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" class="form-control" value="<?= $editProduct['price'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-control">
                        <?php foreach (['Game','Pulsa','E-Wallet'] as $c): ?>
                        <option <?= $editProduct['category']===$c?'selected':'' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <input type="text" name="icon" class="form-control" value="<?= $editProduct['icon'] ?>" maxlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="description" class="form-control" value="<?= clean($editProduct['description']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                    <input type="checkbox" name="is_active" <?= $editProduct['is_active'] ? 'checked' : '' ?>>
                    <span class="form-label" style="margin:0;">Produk Aktif</span>
                </label>
            </div>
            <div style="display:flex;gap:.75rem;">
                <a href="admin.php?tab=products" class="btn btn-outline btn-block">Batal</a>
                <button type="submit" class="btn btn-accent btn-block">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<footer>
    <p>© 2025 <?= SITE_NAME ?> · Admin Panel</p>
</footer>

<script>
function toggleNav() { document.getElementById('navMenu').classList.toggle('open'); }

function closeModalOnBackdrop(e) {
    if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
}

function openEditBalance(userId, username, balance) {
    document.getElementById('balanceUserId').value   = userId;
    document.getElementById('balanceUsername').textContent = username;
    document.getElementById('newBalanceInput').value = balance;
    document.getElementById('editBalanceModal').style.display = 'flex';
}
</script>
</body>
</html>