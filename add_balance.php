<?php
// add_balance.php — Simulated balance top-up (demo)
// In production, integrate a real payment gateway (Midtrans, Xendit, etc.)
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$userId       = $_SESSION['user_id'];
$radioAmount  = (int)($_POST['amount'] ?? 0);
$customAmount = (int)($_POST['custom_amount'] ?? 0);

// Use custom if provided, otherwise radio selection
$amount = $customAmount > 0 ? $customAmount : $radioAmount;

// Validate
if ($amount < 10000 || $amount > 10000000) {
    setFlash('error', 'Nominal tidak valid. Minimal Rp 10.000.');
    header('Location: dashboard.php');
    exit;
}

// Add balance (in real app this happens AFTER payment gateway callback)
$pdo  = getDB();
$stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
$stmt->execute([$amount, $userId]);

// Update session
$balStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$balStmt->execute([$userId]);
$_SESSION['user_balance'] = $balStmt->fetchColumn();

setFlash('success', '💰 Saldo berhasil ditambahkan sebesar ' . rupiah($amount) . '! (Demo mode)');
header('Location: dashboard.php');
exit;