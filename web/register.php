<?php
require 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password === $confirm && !empty($username)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?,?)");
        try {
            $stmt->execute([$username, $hash]);
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = "Username already exists.";
        }
    } else {
        $error = "Passwords do not match.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>Register</title>
<style>
body{font-family:Arial;background:#0b0e24;color:#fff;display:grid;place-items:center;height:100vh;margin:0;}
form{background:#141838;padding:30px;border-radius:14px;box-shadow:0 0 25px rgba(0,0,0,0.3);width:300px;}
input,button{width:100%;padding:10px;margin:10px 0;border:none;border-radius:8px;}
button{background:#3f6aff;color:#fff;cursor:pointer;}
a{color:#9fb4ff;text-decoration:none;font-size:14px;}
</style>
</head>
<body>
<form method="POST">
<h2>Register</h2>
<?php if(!empty($error)) echo "<p style='color:red'>$error</p>"; ?>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<input type="password" name="confirm" placeholder="Confirm Password" required>
<button type="submit">Register</button>
<p><a href="index.php">Back to Login</a></p>
</form>
</body>
</html>
