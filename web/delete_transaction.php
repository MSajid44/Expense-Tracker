<?php
require 'db.php';
$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($id > 0 && in_array($type, ['income','expense'])) {
    $table = $type === 'income' ? 'incomes' : 'expenses';
    $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
}
header("Location: /dashboard.php");
exit;
?>
