<?php
require 'header.php'; 
require 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Get user data
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitizeInput($_POST['fullname']);
    $phone = sanitizeInput($_POST['phone']);
    
    // Update profile
    $stmt = $pdo->prepare('UPDATE users SET fullname = ?, phone = ? WHERE id = ?');
    if($stmt->execute([$fullname, $phone, $_SESSION['user_id']])) {
        $message = 'Profile updated successfully!';
        $_SESSION['fullname'] = $fullname;
        // Refresh user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = 'Error updating profile.';
    }
}
?>

<div class="card">
    <h2>User Profile</h2>
    
    <?php if($message): ?>
        <div style="color: green; margin-bottom: 15px;"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-row">
            <label>Full Name</label>
            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
        </div>
        
        <div class="form-row">
            <label>Email</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
            <div class="small">Email cannot be changed</div>
        </div>
        
        <div class="form-row">
            <label>Phone</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
        </div>
        
        <button type="submit">Update Profile</button>
    </form>
</div>

<div class="card">
    <h3>Payment History</h3>
    <?php
    $payments = $pdo->prepare('
        SELECT p.*, t.title 
        FROM payments p 
        LEFT JOIN tasks t ON p.task_id = t.id 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC
    ');
    $payments->execute([$_SESSION['user_id']]);
    $user_payments = $payments->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($user_payments)): ?>
        <p>No payment history found.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Task</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($user_payments as $payment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($payment['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($payment['title'] ?? 'N/A'); ?></td>
                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($payment['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require 'footer.php'; ?>