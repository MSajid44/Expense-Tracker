<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['uid'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#0b0e24;color:#fff;display:grid;place-items:center;height:100vh;margin:0;}
form{background:#141838;padding:30px;border-radius:14px;box-shadow:0 0 25px rgba(0,0,0,0.3);width:300px;}
input{width:100%;padding:10px;margin:10px 0;border:none;border-radius:8px;}
button{width:100%;padding:10px;background:#3f6aff;border:none;color:#fff;border-radius:8px;cursor:pointer;}
a{color:#9fb4ff;text-decoration:none;font-size:14px;}
</style>
</head>
<body>
<form method="POST">
<h2>Login</h2>
<?php if(!empty($error)) echo "<p style='color:red'>$error</p>"; ?>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
<p><a href="register.php">Register</a></p>
</form>
</body>
</html>
