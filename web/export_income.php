<?php
require 'db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="income_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Amount', 'Category', 'Note', 'Date']);

$sql = "SELECT i.amount, c.name, i.note, i.created_at 
        FROM incomes i LEFT JOIN income_categories c ON i.category_id = c.id ORDER BY i.created_at DESC";
foreach ($pdo->query($sql) as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
?>
