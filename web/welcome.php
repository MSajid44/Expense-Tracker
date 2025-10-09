<?php
session_start();
if (!isset($_SESSION['uid'])) {
    header('Location: index.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Welcome</title>
<style>
body{font-family:system-ui;background:#0b0e24;color:#fff;display:grid;place-items:center;height:100vh;margin:0;}
.card{background:#141838;padding:30px;border-radius:18px;box-shadow:0 0 25px rgba(0,0,0,0.3);text-align:center;}
a{color:#9fb4ff;text-decoration:none;margin:0 10px;}
</style>
</head>
<body>
<div class="card">
<h2>Welcome, <?php echo $username; ?>!</h2>
<p><a href="dashboard.php">Go to Dashboard</a> | <a href="logout.php">Logout</a></p>
</div>
</body>
</html>
