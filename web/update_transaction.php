<?php
require 'db.php';
$type = $_POST['type'];
$id = (int)$_POST['id'];
$amount = $_POST['amount'];
$category_id = $_POST['category_id'];
$note = $_POST['note'];

$table = $type === 'income' ? 'incomes' : 'expenses';
$stmt = $pdo->prepare("UPDATE $table SET amount=?, category_id=?, note=? WHERE id=?");
$stmt->execute([$amount, $category_id, $note, $id]);
header("Location: /dashboard.php");
exit;
?>
