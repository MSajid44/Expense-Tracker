<?php

// Fetch categories (global, no user_id column required)
function getCategories($pdo, $type) {
    $table = $type === 'expense' ? 'expense_categories' : 'income_categories';
    $st = $pdo->prepare("SELECT id, name, icon FROM $table ORDER BY name ASC");
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Totals
function getTotals($pdo, $uid, $where, $params) {
    $sql = "SELECT 
              (SELECT COALESCE(SUM(amount),0) FROM incomes  WHERE user_id=? $where) AS income,
              (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? $where) AS expense";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([$uid], $params, [$uid], $params));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return [
        'income'  => (float)$row['income'],
        'expense' => (float)$row['expense'],
        'balance' => (float)$row['income'] - (float)$row['expense'],
    ];
}

// Monthly totals for bar charts
function monthlyTotals($pdo, $table, $uid, $where, $params) {
    $sql = "SELECT MONTH(created_at) AS m, COALESCE(SUM(amount),0) AS total
            FROM $table
            WHERE user_id=? $where
            GROUP BY m";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([$uid], $params));
    $out = array_fill(1,12,0);
    foreach ($st as $r) {
        $out[(int)$r['m']] = (float)$r['total'];
    }
    return $out;
}

// Date range helper (returns SQL filter and params)
function date_range() {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    $where = '';
    $params = [];

    if ($from) { $where .= " AND created_at >= ?"; $params[] = $from; }
    if ($to)   { $where .= " AND created_at <= ?"; $params[] = $to; }

    return [$where, $params, $from, $to];
}
