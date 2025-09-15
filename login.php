<?php
require 'header.php';
require 'config.php';
require 'admin_config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    // Admin quick-check
    if(strtolower($email) === strtolower(ADMIN_EMAIL) && password_verify($password, ADMIN_PASSWORD_HASH)){
        $_SESSION['user_email'] = ADMIN_EMAIL; $_SESSION['role'] = 'admin';
        // ensure admin exists in DB
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1'); $stmt->execute([ADMIN_EMAIL]);
        if(!$stmt->fetch()){ $hash = ADMIN_PASSWORD_HASH; $pdo->prepare('INSERT INTO users (fullname,email,password,role) VALUES (?,?,?,"admin")')->execute(['Administrator', ADMIN_EMAIL, $hash]); }
        redirect('admin_dashboard.php');
    }
    $stmt = $pdo->prepare('SELECT id,fullname,email,password FROM users WHERE email = ? LIMIT 1'); $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if($user && password_verify($password, $user['password'])){
        $_SESSION['user_id'] = $user['id']; $_SESSION['user_email'] = $user['email']; $_SESSION['fullname'] = $user['fullname']; $_SESSION['role'] = 'user';
        redirect('dashboard.php');
    } else { $error = 'Invalid credentials.'; }
}
?>
<div class="card"><h2>Login</h2><?php if(!empty($error)): ?><div style="color:#b00020"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" action=""><div class="form-row"><label>Email</label><input type="email" name="email" required></div><div class="form-row"><label>Password</label><input type="password" name="password" required></div><div class="form-row"><button type="submit">Login</button></div><div class="small">Don't have an account? <a href="register.php">Register</a></div></form></div><?php require 'footer.php'; ?>