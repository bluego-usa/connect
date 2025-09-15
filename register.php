<?php
require 'header.php';
require 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if(empty($fullname) || empty($email) || empty($password)){
        $error = 'Please fill all required fields.';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = 'Please enter a valid email address.';
    } elseif($password !== $confirm){
        $error = 'Passwords do not match.';
    } elseif(strlen($password) < 6){
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if($stmt->fetch()){
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (fullname,email,phone,password) VALUES (?,?,?,?)');
            if($stmt->execute([$fullname, $email, $phone, $hash])){
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_email'] = $email;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = 'user';
                redirect('dashboard.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<div class="card">
    <h2>Register</h2>
    <?php if(!empty($error)): ?><div style="color:#b00020"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="">
        <div class="form-row">
            <label>Full Name *</label>
            <input type="text" name="fullname" required>
        </div>
        <div class="form-row">
            <label>Email *</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-row">
            <label>Phone</label>
            <input type="tel" name="phone">
        </div>
        <div class="form-row">
            <label>Password *</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-row">
            <label>Confirm Password *</label>
            <input type="password" name="confirm_password" required>
        </div>
        <div class="form-row">
            <button type="submit">Register</button>
        </div>
        <div class="small">Already have an account? <a href="login.php">Login</a></div>
    </form>
</div>
<?php require 'footer.php'; ?>