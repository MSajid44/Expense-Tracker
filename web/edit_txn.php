<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

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

if ($type === 'income') {
    $table = 'incomes';
    $catTable = 'income_categories';
} else {
    $table = 'expenses';
    $catTable = 'expense_categories';
}

$sql = "
    SELECT t.id, t.amount, t.note, t.created_at, t.category_id, c.icon, c.name AS category
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

$cats = getCategories($pdo, $type);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_txn') {
    $amount = (float)($_POST['amount'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $created_at = $_POST['created_at'] ?? date('Y-m-d');

    $upd = $pdo->prepare("UPDATE $table
                          SET amount = ?, category_id = ?, note = ?, created_at = ?
                          WHERE id = ? AND user_id = ?");
    $upd->execute([$amount, $category_id, $note, $created_at, $id, $uid]);

    header("Location: dashboard.php");
    exit;
}

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Edit Transaction</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#0e1124; color:#fff; font-family:system-ui, sans-serif; }
  .card { background:#171d3e; border:1px solid #303671; border-radius:1rem; }

  /* Make all text white */
  *, .card, p, h4, strong, label, span, a, button, input, select, option, textarea {
    color:#fff !important;
  }

  /* Inputs & selects */
  .form-control, .form-select {
    background:#101534 !important;
    border:1px solid #303671;
    color:#fff !important;
  }
  .form-control:focus, .form-select:focus {
    background:#1b214d !important;
    border-color:#6aa5ff !important;
    box-shadow:none !important;
    color:#fff !important;
  }

  /* Dropdown options */
  option { background:#101534; color:#fff !important; }

  /* Buttons */
  .btn-accent { background:#6aa5ff; border:none; color:#fff !important; }
  .btn-accent:hover { background:#4a8be0 !important; }

  .btn-secondary {
    background:#6c757d;
    border:none;
    color:#fff !important;
  }
  .btn-secondary:hover {
    background:#5a6269 !important;
    color:#fff !important;
  }
</style>
</head>
<body>
<div class="container my-5">
  <div class="card p-4">
    <h4 class="mb-3">Edit <?= ucfirst($type) ?> Transaction</h4>

    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="update_txn">

      <div class="col-md-6">
        <label class="form-label">Amount</label>
        <input type="number" step="0.01" name="amount" class="form-control"
               value="<?= esc($txn['amount']) ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Category</label>
        <select name="category_id" class="form-select">
          <?php foreach ($cats as $c): ?>
          <option value="<?= $c['id'] ?>" 
            <?= ($c['id'] == $txn['category_id'] ? 'selected' : '') ?>>
            <?= esc(($c['icon'] ?? '') . ' ' . $c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-12">
        <label class="form-label">Note (optional)</label>
        <input type="text" name="note" class="form-control" value="<?= esc($txn['note']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Date</label>
        <input type="date" name="created_at" class="form-control"
               value="<?= esc($txn['created_at']) ?>">
      </div>

      <div class="col-md-12 d-flex gap-2 mt-4">
        <button class="btn btn-accent">Save Changes</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
