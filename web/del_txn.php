<?php
session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['uid'])) {
    header("Location: index.php");
    exit;
}

$uid  = (int)$_SESSION['uid'];
$type = $_GET['type'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['income', 'expense'], true) || $id < 1) {
    die("Invalid request.");
}

// Determine table and category join
if ($type === 'income') {
    $table = 'incomes';
    $catTable = 'income_categories';
} else {
    $table = 'expenses';
    $catTable = 'expense_categories';
}

// Fetch transaction details
$sql = "
    SELECT t.id, t.amount, t.note, t.created_at, c.name AS category, c.icon
    FROM $table t
    LEFT JOIN $catTable c ON c.id = t.category_id
    WHERE t.id = ? AND t.user_id = ?
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$id, $uid]);
$txn = $st->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    die("Transaction not found or not yours.");
}

// If user confirmed deletion
if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    $del = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
    $del->execute([$id, $uid]);
    header("Location: dashboard.php");
    exit;
}

// If user canceled
if (isset($_POST['confirm']) && $_POST['confirm'] === 'no') {
    header("Location: dashboard.php");
    exit;
}

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Confirm Delete</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0e1124; color:#fff; font-family:system-ui, sans-serif; }
  .card { background:#171d3e; border:1px solid #303671; border-radius:1rem; }
  .btn-accent { background:#6aa5ff; color:#fff !important; border:none; }
  .btn-accent:hover { background:#4a8be0 !important; color:#fff !important; }
</style>
</head>
<body>
<div class="container my-5">
  <div class="card p-4">
    <h4 class="mb-3">Confirm Deletion</h4>
    <p>Are you sure you want to delete this transaction?</p>

    <ul class="list-group mb-4">
      <li class="list-group-item bg-dark text-white">
        <strong>Amount: </strong> £<?= esc(number_format($txn['amount'], 2)) ?>
      </li>
      <li class="list-group-item bg-dark text-white">
        <strong>Category: </strong> <?= esc(($txn['icon'] ?? '') . ' ' . ($txn['category'] ?? '')) ?>
      </li>
      <li class="list-group-item bg-dark text-white">
        <strong>Date: </strong> <?= esc($txn['created_at']) ?>
      </li>
      <li class="list-group-item bg-dark text-white">
        <strong>Note: </strong> <?= esc($txn['note']) ?>
      </li>
    </ul>

    <form method="post" class="d-flex gap-2">
      <button type="submit" name="confirm" value="yes" class="btn btn-danger">Yes, Delete</button>
      <button type="submit" name="confirm" value="no" class="btn btn-secondary">Cancel</button>
    </form>
  </div>
</div>
</body>
</html>
