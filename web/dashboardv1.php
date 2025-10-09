<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

if (!isset($_SESSION['uid'])) { header('Location: /index.php'); exit; }
$uid = (int)$_SESSION['uid'];
$username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES);

list($filterWhere, $filterParams, $from, $to) = date_range();
$message = '';

$fallbackIcons = [
  'Salary'=>'💼','Additional Work / Freelance'=>'🧰','Business Income'=>'🧾','Bonus / Commission'=>'🎁',
  'Rental Income'=>'🏠','Investment Returns'=>'📈','Other'=>'💸',
  'Rent / Mortgage'=>'🏠','Utilities'=>'💡','Groceries'=>'🛒','Fuel / Transport'=>'⛽',
  'Education'=>'🎓','Subscriptions'=>'📺','Entertainment'=>'🎬','Insurance'=>'📄','Medical'=>'🏥'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_txn') {
    $type   = $_POST['type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $catId  = ($_POST['category_id'] !== '') ? (int)$_POST['category_id'] : null;
    $note   = trim($_POST['note'] ?? '');
    $date   = $_POST['created_at'] ?? '';
    if ($amount > 0 && ($type === 'income' || $type === 'expense')) {
      $tbl = $type === 'income' ? 'incomes' : 'expenses';
      $sql = "INSERT INTO $tbl (user_id, amount, category_id, note, created_at)
              VALUES (?, ?, ?, ?, COALESCE(NULLIF(?, ''), CURRENT_DATE))";
      $pdo->prepare($sql)->execute([$uid, $amount, $catId, $note, $date]);
      $message = ucfirst($type) . " added.";
    }
  }

  if ($action === 'add_category') {
    $type = $_POST['category_type'] ?? 'income';
    $name = trim($_POST['category_name'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    if ($name !== '') {
      $tbl = $type === 'expense' ? 'expense_categories' : 'income_categories';
      $pdo->prepare("INSERT INTO $tbl (user_id, name, icon) VALUES (?, ?, ?)")
          ->execute([$uid, $name, $icon !== '' ? $icon : null]);
      $message = "Category added.";
    }
  }

  if ($action === 'edit_txn') {
    $type   = $_POST['type'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $catId  = ($_POST['category_id'] !== '') ? (int)$_POST['category_id'] : null;
    $note   = trim($_POST['note'] ?? '');
    $date   = $_POST['created_at'] ?? '';
    if ($id > 0 && $amount > 0 && ($type === 'income' || $type === 'expense')) {
      $tbl = $type === 'income' ? 'incomes' : 'expenses';
      $sql = "UPDATE $tbl
              SET amount = ?, category_id = ?, note = ?, created_at = COALESCE(NULLIF(?, ''), created_at)
              WHERE id = ? AND user_id = ?";
      $pdo->prepare($sql)->execute([$amount, $catId, $note, $date, $id, $uid]);
      header("Location: dashboard.php?from=".urlencode($from)."&to=".urlencode($to)."&msg=updated");
      exit;
    }
  }
}

$incomeCats  = getCategories($pdo, 'income',  $uid);
$expenseCats = getCategories($pdo, 'expense', $uid);
$totals = getTotals($pdo, $uid, $filterWhere, $filterParams);

$st = $pdo->prepare("
  SELECT i.id, i.amount, i.note, i.created_at,
         COALESCE(ic.name,'Uncategorized') AS category,
         COALESCE(ic.icon,'') AS icon,
         COALESCE(i.category_id,0) AS category_id
  FROM incomes i
  LEFT JOIN income_categories ic ON ic.id = i.category_id
  WHERE i.user_id = ? $filterWhere
  ORDER BY i.created_at DESC, i.id DESC
  LIMIT 10");
$st->execute(array_merge([$uid], $filterParams));
$incomes = $st->fetchAll();

$st = $pdo->prepare("
  SELECT e.id, e.amount, e.note, e.created_at,
         COALESCE(ec.name,'Uncategorized') AS category,
         COALESCE(ec.icon,'') AS icon,
         COALESCE(e.category_id,0) AS category_id
  FROM expenses e
  LEFT JOIN expense_categories ec ON ec.id = e.category_id
  WHERE e.user_id = ? $filterWhere
  ORDER BY e.created_at DESC, e.id DESC
  LIMIT 10");
$st->execute(array_merge([$uid], $filterParams));
$expenses = $st->fetchAll();

$mi = monthlyTotals($pdo, 'incomes',  $uid, $filterWhere, $filterParams);
$me = monthlyTotals($pdo, 'expenses', $uid, $filterWhere, $filterParams);

$st = $pdo->prepare("
  SELECT COALESCE(ec.name,'Uncategorized') AS name,
         SUM(e.amount) AS total
  FROM expenses e
  LEFT JOIN expense_categories ec ON ec.id = e.category_id
  WHERE e.user_id = ? $filterWhere
  GROUP BY ec.id, name
  HAVING total > 0
  ORDER BY total DESC");
$st->execute(array_merge([$uid], $filterParams));
$pieRows = $st->fetchAll();
$pieLabels = array_column($pieRows, 'name');
$pieData   = array_map('floatval', array_column($pieRows, 'total'));

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
function optionsFromCats($cats, $selected = null){
  $out = '<option value="">Select</option>';
  foreach ($cats as $c) {
    $sel = ($selected !== null && (int)$selected === (int)$c['id']) ? ' selected' : '';
    $label = trim(($c['icon'] ? $c['icon'].' ' : '').$c['name']);
    $out .= '<option value="'.$c['id'].'"'.$sel.'>'.esc($label).'</option>';
  }
  return $out;
}
$incomeOptions  = optionsFromCats($incomeCats);
$expenseOptions = optionsFromCats($expenseCats);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<style>
  :root{
    --bg:#101329; --panel:#161b3b; --line:#2a3272; --text:#f1f3ff;
    --muted:#aeb6e9; --accent:#6aa5ff; --success:#20c997; --danger:#ff6b6b;
    --input:#121740; --input-border:#35408f; --placeholder:#c7ccff;
  }
  body{background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Roboto,Arial}
  .navbar{background:linear-gradient(90deg,#0f1438,#0f173f);}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:16px}
  .btn-accent{background:var(--accent);border:none;color:#fff}
  .btn-accent:hover{filter:brightness(1.06)}
  .form-control,.form-select{background:var(--input);border:1px solid var(--input-border);color:var(--text)}
  .form-control::placeholder{color:var(--placeholder)}
  .form-control:focus,.form-select:focus{box-shadow:0 0 0 .2rem rgba(106,165,255,.25);border-color:#7fb3ff}
  .table{color:var(--text)}
  .table thead th{color:#dfe4ff;border-bottom-color:var(--line)}
  .table td{border-color:var(--line)}
  #pieWrap canvas{max-height:340px}
</style>
</head>
<body>
<nav class="navbar navbar-dark px-3">
  <a class="navbar-brand fw-semibold" href="#">💰 Expense Tracker</a>
  <div class="d-flex align-items-center gap-2">
    <form class="d-none d-md-inline" action="export_all.php" method="get">
      <input type="hidden" name="from" value="<?php echo esc($from); ?>">
      <input type="hidden" name="to"   value="<?php echo esc($to); ?>">
      <button class="btn btn-sm btn-outline-light">⬇️ Export CSV</button>
    </form>
    <span class="badge bg-secondary px-3 py-2">👤 <?php echo esc($username); ?></span>
    <a class="btn btn-sm btn-outline-info" href="logout.php">Logout</a>
  </div>
</nav>
<div class="container my-4">
<?php if(isset($_GET['msg']) && $_GET['msg']==='updated'): ?>
  <div class="alert alert-success py-2">Entry updated.</div>
<?php endif; ?>
<?php if($message): ?><div class="alert alert-info py-2"><?php echo esc($message); ?></div><?php endif; ?>

<!-- Totals -->
<div class="row g-3">
  <div class="col-md-4"><div class="card p-3 text-center"><div class="small text-uppercase text-light">Income</div><div class="fs-3 fw-bold" style="color:var(--success)">£<?php echo number_format($totals['income'],2);?></div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div class="small text-uppercase text-light">Expense</div><div class="fs-3 fw-bold" style="color:var(--danger)">£<?php echo number_format($totals['expense'],2);?></div></div></div>
  <div class="col-md-4"><div class="card p-3 text-center"><div class="small text-uppercase text-light">Balance</div><div class="fs-3 fw-bold" style="color:#FFD54F">£<?php echo number_format($totals['balance'],2);?></div></div></div>
</div>

<!-- Filters -->
<form class="row g-2 align-items-end mt-3" method="get">
  <div class="col-6 col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?php echo esc($from);?>"></div>
  <div class="col-6 col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?php echo esc($to);?>"></div>
  <div class="col-md-3 col-12"><button class="btn btn-accent w-100">Apply</button></div>
</form>

<!-- Add Income/Expense -->
<div class="row g-3 mt-3">
  <div class="col-lg-6"><div class="card p-3"><h5>Add Income</h5>
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="add_txn"><input type="hidden" name="type" value="income">
      <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
      <div class="col-6"><select name="category_id" class="form-select"><?php echo $incomeOptions;?></select></div>
      <div class="col-6"><input type="date" name="created_at" class="form-control" value="<?php echo esc(date('Y-m-d'));?>"></div>
      <div class="col-6"><input type="text" name="note" class="form-control" placeholder="Note (optional)"></div>
      <div class="col-12"><button class="btn btn-accent w-100">Add Income</button></div>
    </form></div></div>

  <div class="col-lg-6"><div class="card p-3"><h5>Add Expense</h5>
    <form method="post" class="row g-2">
      <input type="hidden" name="action" value="add_txn"><input type="hidden" name="type" value="expense">
      <div class="col-6"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
      <div class="col-6"><select name="category_id" class="form-select"><?php echo $expenseOptions;?></select></div>
      <div class="col-6"><input type="date" name="created_at" class="form-control" value="<?php echo esc(date('Y-m-d'));?>"></div>
      <div class="col-6"><input type="text" name="note" class="form-control" placeholder="Note (optional)"></div>
      <div class="col-12"><button class="btn btn-accent w-100">Add Expense</button></div>
    </form></div></div>
</div>

<!-- Add Category (moved up) -->
<div class="row g-3 mt-3">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5>Add Category</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_category">
        <div class="col-6"><input type="text" name="category_name" class="form-control" placeholder="Category Name" required></div>
        <div class="col-6">
          <select name="category_type" class="form-select">
            <option value="income">Income</option>
            <option value="expense">Expense</option>
          </select>
        </div>
        <div class="col-12"><input type="text" name="icon" class="form-control" placeholder="Emoji Icon (e.g. 💼)"></div>
        <div class="col-12"><button class="btn btn-accent w-100">Add Category</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Tables start -->
<!-- Tables -->
<div class="row g-3 mt-3">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5>Latest Incomes</h5>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Note</th></tr></thead>
          <tbody>
          <?php foreach($incomes as $r):
            $icon = $r['icon'] ?: ($fallbackIcons[$r['category']] ?? '❓'); ?>
            <tr>
              <td><?php echo esc($r['created_at']); ?></td>
              <td>£<?php echo number_format($r['amount'],2); ?></td>
              <td><?php echo esc($icon.' '.$r['category']); ?></td>
              <td><?php echo esc($r['note']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <h5>Latest Expenses</h5>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Note</th></tr></thead>
          <tbody>
          <?php foreach($expenses as $r):
            $icon = $r['icon'] ?: ($fallbackIcons[$r['category']] ?? '❓'); ?>
            <tr>
              <td><?php echo esc($r['created_at']); ?></td>
              <td>£<?php echo number_format($r['amount'],2); ?></td>
              <td><?php echo esc($icon.' '.$r['category']); ?></td>
              <td><?php echo esc($r['note']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 3D PIE + Bars -->
<div id="pieWrap" class="row g-3 mt-3">
  <div class="col-12">
    <div class="card p-3">
      <h5 class="mb-3">Expense Breakdown (3D)</h5>
      <canvas id="pie3d"></canvas>
      <div id="pieLegend" class="mt-2"></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-3">
  <div class="col-lg-6"><div class="card p-3"><canvas id="barIncome"></canvas></div></div>
  <div class="col-lg-6"><div class="card p-3"><canvas id="barExpense"></canvas></div></div>
</div>
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const pieLabels = <?php echo json_encode($pieLabels,JSON_UNESCAPED_UNICODE);?>;
const pieData   = <?php echo json_encode($pieData);?>;
const months=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
const incomeData=<?php echo json_encode(array_values($mi));?>;
const expenseData=<?php echo json_encode(array_values($me));?>;

// Helper to build bright gradients for pseudo-3D
function brightGrad(ctx, area, color){
  const grad=ctx.createLinearGradient(0,area.top,0,area.bottom);
  grad.addColorStop(0,Chart.helpers.color(color).lighten(0.25));
  grad.addColorStop(1,Chart.helpers.color(color).darken(0.25));
  return grad;
}
function buildColors(ctx, area, baseColors){
  return baseColors.map(c=>brightGrad(ctx,area,c));
}
function palette(n){
  const colors=["#ff6b6b","#4ecdc4","#45b7d1","#feca57","#5f27cd","#10ac84","#ee5253","#54a0ff","#01a3a4","#ff9f43","#48dbfb","#c8d6e5"];
  return colors.slice(0,n);
}

// --- PIE 3D style ---
new Chart(document.getElementById('pie3d'),{
  type:'pie',
  data:{labels:pieLabels,datasets:[{data:pieData,backgroundColor:palette(pieLabels.length),borderWidth:0}]},
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{
      legend:{position:'bottom',labels:{color:'#f1f3ff'}},
      tooltip:{callbacks:{label:(ctx)=>ctx.label+': £'+ctx.parsed.toLocaleString()}}
    },
    animation:{animateRotate:true,duration:1200,easing:'easeOutCubic'}
  },
  plugins:[{
    // shadow for depth illusion
    id:'shadow',
    beforeDraw(chart,args,opts){
      const {ctx,chartArea:{top,left,right,bottom}}=chart;
      ctx.save();
      ctx.shadowColor='rgba(0,0,0,0.4)';
      ctx.shadowBlur=15;
      ctx.shadowOffsetY=8;
      ctx.beginPath();ctx.rect(left,top,right-left,bottom-top);ctx.clip();
    },
    afterDraw(chart){chart.ctx.restore();}
  }]
});

// --- BAR CHARTS with gradients ---
function makeBarChart(id,label,data,color){
  new Chart(document.getElementById(id),{
    type:'bar',
    data:{labels:months,datasets:[{label:label,data:data,backgroundColor:(ctx)=>brightGrad(ctx.chart.ctx,ctx.chart.chartArea,color)}]},
    options:{
      plugins:{legend:{labels:{color:'#f1f3ff'}}},
      scales:{
        x:{ticks:{color:'#f1f3ff'},grid:{color:'#2a3272'}},
        y:{ticks:{color:'#f1f3ff'},grid:{color:'#2a3272'}}
      },
      animation:{duration:1000}
    }
  });
}
makeBarChart('barIncome','Monthly Income',incomeData,'#20c997');
makeBarChart('barExpense','Monthly Expense',expenseData,'#ff6b6b');
</script>
</body>
</html>
