<?php
session_start();
if (!isset($_SESSION['uid'])) { http_response_code(403); exit('Forbidden'); }
$uid = (int)$_SESSION['uid'];

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
list($where, $params) = date_range();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=all_transactions.csv');
$fp = fopen('php://output', 'w');
fputcsv($fp, ['Type','Date','Amount','Category','Note']);

$sqlI = "SELECT 'Income' AS t, i.created_at d, i.amount a, COALESCE(c.name,'Uncategorized') cat, i.note n
         FROM incomes i
         LEFT JOIN income_categories c ON c.id = i.category_id
         WHERE i.user_id = ? $where";

$sqlE = "SELECT 'Expense' AS t, e.created_at d, e.amount a, COALESCE(c.name,'Uncategorized') cat, e.note n
         FROM expenses e
         LEFT JOIN expense_categories c ON c.id = e.category_id
         WHERE e.user_id = ? $where";

$st = $pdo->prepare("$sqlI UNION ALL $sqlE ORDER BY d");
$st->execute(array_merge([$uid], $params, [$uid], $params));

while ($r = $st->fetch()) {
  fputcsv($fp, [$r['t'],$r['d'],$r['a'],$r['cat'],$r['n']]);
}
fclose($fp);
