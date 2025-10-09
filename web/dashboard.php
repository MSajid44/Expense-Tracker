<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

if (!isset($_SESSION['uid'])) {
    header("Location: index.php");
    exit;
}

$uid = (int)$_SESSION['uid'];
$username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES);

list($filterWhere, $filterParams, $from, $to) = date_range();

$totals = getTotals($pdo, $uid, $filterWhere, $filterParams);
$incomeCats  = getCategories($pdo, 'income');
$expenseCats = getCategories($pdo, 'expense');

// Fetch transactions
$incomes = $pdo->prepare("SELECT i.*, c.name as category, c.icon 
                          FROM incomes i 
                          LEFT JOIN income_categories c ON c.id=i.category_id
                          WHERE i.user_id=? ORDER BY i.created_at DESC LIMIT 5");
$incomes->execute([$uid]);
$incomes = $incomes->fetchAll();

$expenses = $pdo->prepare("SELECT e.*, c.name as category, c.icon 
                           FROM expenses e 
                           LEFT JOIN expense_categories c ON c.id=e.category_id
                           WHERE e.user_id=? ORDER BY e.created_at DESC LIMIT 5");
$expenses->execute([$uid]);
$expenses = $expenses->fetchAll();

// Pie Chart Data
$st = $pdo->prepare("
    SELECT COALESCE(ec.name, 'Uncategorized') AS name,
           SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    WHERE e.user_id = ? $filterWhere
    GROUP BY ec.id, name
    HAVING total > 0
    ORDER BY total DESC
");
$st->execute(array_merge([$uid], $filterParams));
$pieRows = $st->fetchAll();
$pieLabels = array_column($pieRows, 'name');
$pieData   = array_map('floatval', array_column($pieRows, 'total'));

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Expense Tracker</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<style>
  body { background:#0e1124; color:#fff; font-family:system-ui, sans-serif; }
  .navbar { background:linear-gradient(90deg,#14193c,#0e132f); }
  .card { background:#171d3e; border:1px solid #303671; border-radius:1rem; }
  .btn-accent { background:#6aa5ff; color:#fff !important; border:none; }
  .btn-accent:hover { background:#4a8be0 !important; color:#fff !important; }
  .form-control, .form-select {
    background:#101534; border:1px solid #303671; color:#fff !important;
  }
  .form-control:focus, .form-select:focus {
    background:#1b214d !important;
    color:#fff !important;
    border-color:#6aa5ff !important;
    box-shadow:none !important;
  }
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3">
  <span class="navbar-brand fw-semibold">💰 Expense Tracker</span>
  <div class="ms-auto d-flex gap-2">
    <form action="export_all.php" method="get" class="d-none d-md-inline">
      <button class="btn btn-sm btn-outline-light">⬇️ Export CSV</button>
    </form>
    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙️ Settings</button>
    <span class="badge bg-secondary px-3 py-2">👤 <?= $username ?></span>
    <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
  </div>
</nav>

<div class="container my-4">

<!-- Totals -->
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card p-3 text-center"><div>Income</div><div class="fs-3 fw-bold text-white">£<?= number_format($totals['income'],2) ?></div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div>Expense</div><div class="fs-3 fw-bold text-white">£<?= number_format($totals['expense'],2) ?></div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div>Balance</div><div class="fs-3 fw-bold" style="color:#FFD54F">£<?= number_format($totals['balance'],2) ?></div></div></div>
</div>

<!-- Add Income / Expense -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Add Income</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_txn">
        <input type="hidden" name="type" value="income">
        <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
        <div class="col-6"><select name="category_id" class="form-select"><?php foreach($incomeCats as $c): ?><option value="<?= $c['id'] ?>"><?= esc(($c['icon']?:'💼').' '.$c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><input type="text" name="note" class="form-control" placeholder="Note (optional)"></div>
        <div class="col-12"><button class="btn btn-accent w-100">Add Income</button></div>
      </form>
    </div>
    <!-- Recent Incomes -->
    <div class="card p-3 mt-3">
      <h5>Recent Incomes</h5>
      <table class="table table-dark table-hover align-middle">
        <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Note</th><th></th></tr></thead>
        <tbody>
          <?php foreach($incomes as $r): ?>
            <tr>
              <td><?= esc($r['created_at']) ?></td>
              <td>£<?= number_format($r['amount'],2) ?></td>
              <td><?= esc(($r['icon']?:'💼').' '.$r['category']) ?></td>
              <td><?= esc($r['note']) ?></td>
              <td>
                <a href="edit_txn.php?id=<?= $r['id'] ?>&type=income" class="text-info me-2">✏️</a>
                <a href="del_txn.php?id=<?= $r['id'] ?>&type=income" onclick="return confirm('Delete this entry?')" class="text-danger">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card p-3">
      <h5>Add Expense</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_txn">
        <input type="hidden" name="type" value="expense">
        <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
        <div class="col-6"><select name="category_id" class="form-select"><?php foreach($expenseCats as $c): ?><option value="<?= $c['id'] ?>"><?= esc(($c['icon']?:'💸').' '.$c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><input type="text" name="note" class="form-control" placeholder="Note (optional)"></div>
        <div class="col-12"><button class="btn btn-accent w-100">Add Expense</button></div>
      </form>
    </div>
    <!-- Recent Expenses -->
    <div class="card p-3 mt-3">
      <h5>Recent Expenses</h5>
      <table class="table table-dark table-hover align-middle">
        <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Note</th><th></th></tr></thead>
        <tbody>
          <?php foreach($expenses as $r): ?>
            <tr>
              <td><?= esc($r['created_at']) ?></td>
              <td>£<?= number_format($r['amount'],2) ?></td>
              <td><?= esc(($r['icon']?:'💸').' '.$r['category']) ?></td>
              <td><?= esc($r['note']) ?></td>
              <td>
                <a href="edit_txn.php?id=<?= $r['id'] ?>&type=expense" class="text-info me-2">✏️</a>
                <a href="del_txn.php?id=<?= $r['id'] ?>&type=expense" onclick="return confirm('Delete this entry?')" class="text-danger">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pie + Bars -->
<div class="card p-3 mb-4"><h5>Expense Breakdown</h5><canvas id="pie3d" style="max-height:380px;"></canvas></div>
<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="card p-3"><h5>Monthly Income</h5><canvas id="barIncome"></canvas></div></div>
  <div class="col-md-6"><div class="card p-3"><h5>Monthly Expense</h5><canvas id="barExpense"></canvas></div></div>
</div>

</div><!-- /container -->

<!-- Settings Modal (same as before) -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">⚙️ Settings</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#incTab">Income Categories</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#expTab">Expense Categories</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="incTab">
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="add_category">
              <input type="hidden" name="category_type" value="income">
              <div class="col-6"><input type="text" name="category_name" class="form-control" placeholder="Name" required></div>
              <div class="col-4"><input type="text" name="icon" class="form-control" placeholder="Emoji (💼)"></div>
              <div class="col-2"><button class="btn btn-accent w-100">Add</button></div>
            </form>
          </div>
          <div class="tab-pane fade" id="expTab">
            <form method="post" class="row g-2">
              <input type="hidden" name="action" value="add_category">
              <input type="hidden" name="category_type" value="expense">
              <div class="col-6"><input type="text" name="category_name" class="form-control" placeholder="Name" required></div>
              <div class="col-4"><input type="text" name="icon" class="form-control" placeholder="Emoji (🛒)"></div>
              <div class="col-2"><button class="btn btn-accent w-100">Add</button></div>
            </form>
          </div>
        </div>
      </div>
      <div class="modal-footer border-secondary">
        <button class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
Chart.register(ChartDataLabels);

new Chart(document.getElementById("pie3d"), {
  type: "pie",
  data: {
    labels: <?= json_encode($pieLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      data: <?= json_encode($pieData) ?>,
      backgroundColor: ["#00e6e6","#ff4081","#7c4dff","#ffb300","#00c853","#e53935","#1e88e5","#ff6f00","#8e24aa","#26c6da","#ffca28","#43a047"],
      borderColor: "#0e1124", borderWidth: 1
    }]
  },
  options: {
    plugins: {
      legend: { position: "bottom", labels: { color: "#fff" } },
      datalabels: {
        color: "#fff", align: "end", anchor: "end",
        formatter: (val, ctx) => {
          const total = ctx.chart._metasets[0].total;
          const pct = (val/total*100).toFixed(1)+"%";
          return ctx.chart.data.labels[ctx.dataIndex]+" ("+pct+")";
        },
        font: { weight:"bold" }
      }
    }
  }
});

function makeBar(id,label,data,color){
  new Chart(document.getElementById(id), {
    type:"bar",
    data:{labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],datasets:[{label,data,backgroundColor:color}]},
    options:{
      plugins:{legend:{labels:{color:"#fff"}}},
      scales:{x:{ticks:{color:"#fff"},grid:{color:"#2a3272"}},y:{ticks:{color:"#fff"},grid:{color:"#2a3272"}}}
    }
  });
}
makeBar("barIncome","Monthly Income",<?= json_encode(array_values($mi)) ?>,"#20c997");
makeBar("barExpense","Monthly Expense",<?= json_encode(array_values($me)) ?>,"#ff6b6b");
</script>
</body>
</html>
