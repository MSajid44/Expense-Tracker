<?php
require 'db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="expense_export.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Amount', 'Category', 'Note', 'Date']);

$sql = "SELECT e.amount, c.name, e.note, e.created_at 
        FROM expenses e LEFT JOIN expense_categories c ON e.category_id = c.id ORDER BY e.created_at DESC";
foreach ($pdo->query($sql) as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
?>
