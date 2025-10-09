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

// Handle quick ranges
$range = $_GET['range'] ?? '';
if ($range === '1m') {
    $from = date('Y-m-d', strtotime('-1 month'));
    $to = date('Y-m-d');
} elseif ($range === '6m') {
    $from = date('Y-m-d', strtotime('-6 months'));
    $to = date('Y-m-d');
} elseif ($range === '1y') {
    $from = date('Y-m-d', strtotime('-1 year'));
    $to = date('Y-m-d');
} else {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
}

// SQL filters
$where = '';
$params = [];
if ($from) { $where .= " AND created_at >= ?"; $params[] = $from; }
if ($to)   { $where .= " AND created_at <= ?"; $params[] = $to; }

// Handle POST
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_txn') {
        $type   = $_POST['type'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $catId  = ($_POST['category_id'] !== '') ? (int)$_POST['category_id'] : null;
        $note   = trim($_POST['note'] ?? '');
        $date   = $_POST['created_at'] ?? date('Y-m-d');

        if ($amount > 0 && in_array($type, ['income','expense'])) {
            $tbl = $type === 'income' ? 'incomes' : 'expenses';
            $sql = "INSERT INTO $tbl (user_id, amount, category_id, note, created_at)
                    VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$uid, $amount, $catId, $note, $date]);
            $message = ucfirst($type) . " added.";
        }
    }

    if ($action === 'add_category') {
        $type = $_POST['category_type'] ?? 'income';
        $name = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['icon'] ?? '');

        if ($name !== '') {
            $tbl = ($type === 'expense') ? 'expense_categories' : 'income_categories';
            $pdo->prepare("INSERT INTO $tbl (name, icon) VALUES (?, ?)")
                ->execute([$name, $icon !== '' ? $icon : null]);
            $message = "Category added.";
        }
    }
}

// Totals
$totals = getTotals($pdo, $uid, $where, $params);

// Categories
$incomeCats  = getCategories($pdo, 'income');
$expenseCats = getCategories($pdo, 'expense');

// Transactions
$inSt = $pdo->prepare("SELECT i.*, c.name AS category, c.icon
                       FROM incomes i
                       LEFT JOIN income_categories c ON c.id = i.category_id
                       WHERE i.user_id = ? $where
                       ORDER BY i.created_at DESC, i.id DESC
                       LIMIT 5");
$inSt->execute(array_merge([$uid], $params));
$incomes = $inSt->fetchAll();

$exSt = $pdo->prepare("SELECT e.*, c.name AS category, c.icon
                       FROM expenses e
                       LEFT JOIN expense_categories c ON c.id = e.category_id
                       WHERE e.user_id = ? $where
                       ORDER BY e.created_at DESC, e.id DESC
                       LIMIT 5");
$exSt->execute(array_merge([$uid], $params));
$expenses = $exSt->fetchAll();

// Bar charts
$mi = monthlyTotals($pdo, 'incomes',  $uid, $where, $params);
$me = monthlyTotals($pdo, 'expenses', $uid, $where, $params);

// Pie chart
$pieQ = $pdo->prepare("
    SELECT COALESCE(ec.name, 'Uncategorized') AS name,
           SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id
    WHERE e.user_id = ? $where
    GROUP BY ec.id, name
    HAVING total > 0
    ORDER BY total DESC
");
$pieQ->execute(array_merge([$uid], $params));
$pieRows   = $pieQ->fetchAll();
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
  .form-label, .card h5, .card label { color:#fff !important; }
  .form-control, .form-select {
    background:#101534;
    border:1px solid #303671;
    color:#fff !important;
  }
  .form-control::placeholder {
    color:#d8dcff !important;
  }
  .form-control:focus {
    background:#1b214d !important;
    color:#fff !important;
    border-color:#6aa5ff !important;
    box-shadow:0 0 0 .2rem rgba(106,165,255,.2) !important;
  }
  .table { color:#fff; }
  .table thead th { color:#fff; border-bottom-color:#303671; }
  .table td { border-color:#303671; }
  #pie3d { max-height: 380px; }
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3">
  <span class="navbar-brand fw-semibold">💷 Expense Tracker</span>
  <div class="ms-auto d-flex gap-2">
    <form action="export_all.php" method="get" class="d-none d-md-inline">
      <input type="hidden" name="from" value="<?= esc($from) ?>">
      <input type="hidden" name="to"   value="<?= esc($to) ?>">
      <button class="btn btn-sm btn-outline-light">⬇️ Export CSV</button>
    </form>
    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙️ Settings</button>
    <span class="badge bg-secondary px-3 py-2">👤 <?= $username ?></span>
    <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
  </div>
</nav>
<div class="container my-4">

  <!-- Date filter -->
  <div class="card p-3 mb-4">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label">Quick Range</label><br>
        <a href="?range=1m" class="btn btn-sm btn-outline-light">1M</a>
        <a href="?range=6m" class="btn btn-sm btn-outline-light">6M</a>
        <a href="?range=1y" class="btn btn-sm btn-outline-light">1Y</a>
      </div>
      <div class="col-auto">
        <label class="form-label">From</label>
        <input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-auto">
        <label class="form-label">To</label>
        <input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-auto">
        <button class="btn btn-accent btn-sm">Apply</button>
      </div>
    </form>
  </div>

  <!-- Totals -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card p-3 text-center">
        <div class="small text-uppercase text-white">Income</div>
        <div class="fs-3 fw-bold" style="color:#20c997">
          £<?= number_format($totals['income'] ?? 0,2) ?>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 text-center">
        <div class="small text-uppercase text-white">Expense</div>
        <div class="fs-3 fw-bold" style="color:#ff6b6b">
          £<?= number_format($totals['expense'] ?? 0,2) ?>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 text-center">
        <div class="small text-uppercase text-white">Balance</div>
        <div class="fs-3 fw-bold" style="color:#FFD54F">
          £<?= number_format($totals['balance'] ?? 0,2) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Income / Add Expense side by side -->
  <div class="row g-3 mb-4">
    <!-- Add Income -->
    <div class="col-md-6">
      <div class="card p-3">
        <h5>Add Income</h5>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_txn">
          <input type="hidden" name="type" value="income">
          <div class="col-6">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
              <option value="">Select</option>
              <?php foreach($incomeCats as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= esc(($c['icon']?:'💼').' '.$c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Note</label>
            <input type="text" name="note" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Date</label>
            <input type="date" name="created_at" class="form-control" value="<?= esc(date('Y-m-d')) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-accent w-100">Add Income</button>
          </div>
        </form>
      </div>

      <!-- Recent Incomes -->
      <div class="card p-3 mt-3">
        <h5>Recent Incomes</h5>
        <table class="table table-dark table-hover align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Category</th>
              <th>Note</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($incomes as $r): ?>
            <tr>
              <td><?= esc($r['created_at']) ?></td>
              <td>£<?= number_format($r['amount'],2) ?></td>
              <td><?= esc(($r['icon']?:'💼').' '.($r['category']??'Uncategorized')) ?></td>
              <td><?= esc($r['note']) ?></td>
              <td>
                <a href="edit_txn.php?id=<?= $r['id'] ?>&type=income" class="text-info">✏️</a>
                <a href="del_txn.php?id=<?= $r['id'] ?>&type=income" class="text-danger">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Expense -->
    <div class="col-md-6">
      <div class="card p-3">
        <h5>Add Expense</h5>
        <form method="post" class="row g-2">
          <input type="hidden" name="action" value="add_txn">
          <input type="hidden" name="type" value="expense">
          <div class="col-6">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
              <option value="">Select</option>
              <?php foreach($expenseCats as $c): ?>
                <option value="<?= $c['id'] ?>">
                  <?= esc(($c['icon']?:'💸').' '.$c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Note</label>
            <input type="text" name="note" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Date</label>
            <input type="date" name="created_at" class="form-control" value="<?= esc(date('Y-m-d')) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-accent w-100">Add Expense</button>
          </div>
        </form>
      </div>

      <!-- Recent Expenses -->
      <div class="card p-3 mt-3">
        <h5>Recent Expenses</h5>
        <table class="table table-dark table-hover align-middle">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Category</th>
              <th>Note</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($expenses as $r): ?>
            <tr>
              <td><?= esc($r['created_at']) ?></td>
              <td>£<?= number_format($r['amount'],2) ?></td>
              <td><?= esc(($r['icon']?:'💸').' '.($r['category']??'Uncategorized')) ?></td>
              <td><?= esc($r['note']) ?></td>
              <td>
                <a href="edit_txn.php?id=<?= $r['id'] ?>&type=expense" class="text-info">✏️</a>
                <a href="del_txn.php?id=<?= $r['id'] ?>&type=expense" class="text-danger">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="card p-3 mb-4">
    <h5>Expense Breakdown</h5>
    <canvas id="pie3d"></canvas>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card p-3">
        <h5>Monthly Income</h5>
        <canvas id="barIncome"></canvas>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3">
        <h5>Monthly Expense</h5>
        <canvas id="barExpense"></canvas>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
Chart.register(ChartDataLabels);

const pieLabels = <?= json_encode($pieLabels, JSON_UNESCAPED_UNICODE) ?>;
const pieData = <?= json_encode($pieData) ?>;
const incomeData = <?= json_encode(array_values($mi)) ?>;
const expenseData = <?= json_encode(array_values($me)) ?>;
const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

// Color palette
function palette(n){
  const c=[
    "#00e6e6","#ff4081","#7c4dff","#ffb300","#00c853",
    "#e53935","#1e88e5","#ff6f00","#8e24aa","#26c6da",
    "#ffca28","#43a047"
  ];
  return c.slice(0,n);
}

// PIE CHART
(function buildPie(){
  const el=document.getElementById("pie3d");
  if(!el)return;

  const total=(pieData||[]).reduce((a,b)=>a+Number(b||0),0);

  new Chart(el,{
    type:"pie",
    data:{
      labels:pieLabels,
      datasets:[{
        data:pieData,
        backgroundColor:palette(pieLabels.length),
        borderColor:"#0e1124",
        borderWidth:1
      }]
    },
    options:{
      responsive:true,
      plugins:{
        legend:{
          position:"bottom",
          labels:{ color:"#fff" }
        },
        datalabels:{
          color:"#fff",
          align:"end",
          anchor:"end",
          formatter:(val,ctx)=>{
            if(!total)return"";
            const pct=(val/total*100).toFixed(1)+"%";
            return ctx.chart.data.labels[ctx.dataIndex]+" ("+pct+")";
          },
          font:{ weight:"bold" }
        }
      },
      animation:{ animateRotate:true,duration:900 }
    },
    plugins:[{
      id:"shadow3d",
      beforeDatasetDraw(chart){
        const{ctx,chartArea}=chart;
        if(!chartArea)return;
        ctx.save();
        ctx.shadowColor="rgba(0,0,0,0.45)";
        ctx.shadowBlur=15;
        ctx.shadowOffsetY=6;
      },
      afterDatasetDraw(chart){
        chart.ctx.restore();
      }
    }]
  });
})();

// BAR CHART HELPERS
function makeVerticalGradient(chart,topColor,bottomColor){
  const{ctx,chartArea}=chart;
  if(!chartArea)return topColor;
  const grad=ctx.createLinearGradient(0,chartArea.top,0,chartArea.bottom);
  grad.addColorStop(0,topColor);
  grad.addColorStop(1,bottomColor);
  return grad;
}

function makeBar(id,label,data,c1,c2){
  const el=document.getElementById(id);
  if(!el)return;
  new Chart(el,{
    type:"bar",
    data:{
      labels:months,
      datasets:[{
        label,
        data,
        backgroundColor:(ctx)=>makeVerticalGradient(ctx.chart,c1,c2),
        borderWidth:0
      }]
    },
    options:{
      plugins:{ legend:{ labels:{color:"#fff"} }},
      scales:{
        x:{ ticks:{color:"#fff"}, grid:{color:"#2a3272"} },
        y:{ ticks:{color:"#fff"}, grid:{color:"#2a3272"} }
      },
      animation:{ duration:800 }
    }
  });
}

// Init bar charts
makeBar("barIncome","Monthly Income",incomeData,"#38e6b8","#0f9d58");
makeBar("barExpense","Monthly Expense",expenseData,"#ff8a80","#e53935");
</script>
</body>
</html>
