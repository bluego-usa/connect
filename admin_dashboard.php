<?php 
require 'header.php'; 
require 'config.php'; 
if(session_status()===PHP_SESSION_NONE) session_start(); 
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get stats for admin dashboard
$users_count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$tasks_count = $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$payments_count = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$total_earnings = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "COMPLETED"')->fetchColumn();
?>
<div class="card">
    <h2>Admin Dashboard</h2>
    <div class="small">
        Admin Links: <a href="admin_users.php">Users</a> | 
        <a href="admin_tasks.php">Tasks</a> | 
        <a href="payments.php">Payments</a>
    </div>
    
    <div style="margin-top:12px" class="top-grid">
        <div class="card">
            <h3>Users</h3>
            <p class="small">Total: <?php echo $users_count; ?></p>
            <p><a href="admin_users.php">Manage users</a></p>
        </div>
        
        <div class="card">
            <h3>Tasks</h3>
            <p class="small">Total: <?php echo $tasks_count; ?></p>
            <p><a href="admin_tasks.php">Manage tasks</a></p>
        </div>
        
        <div class="card">
            <h3>Payments</h3>
            <p class="small">Total: <?php echo $payments_count; ?></p>
            <p><a href="payments.php">View payments</a></p>
        </div>
        
        <div class="card">
            <h3>Earnings</h3>
            <p class="small">Total: $<?php echo number_format($total_earnings, 2); ?></p>
        </div>
    </div>
    
    <div style="margin-top:20px" class="top-grid">
        <div class="card">
            <h3>Recent Users</h3>
            <?php
            $users = $pdo->query('SELECT fullname, email, created_at FROM users ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
            if(empty($users)): ?>
                <p>No users found.</p>
            <?php else: 
                foreach($users as $user): ?>
                <div style="padding:5px 0; border-bottom:1px solid #eee;">
                    <strong><?php echo htmlspecialchars($user['fullname']); ?></strong><br>
                    <span class="small"><?php echo htmlspecialchars($user['email']); ?></span><br>
                    <span class="small">Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <?php endforeach; 
            endif; ?>
        </div>
        
        <div class="card">
            <h3>Recent Payments</h3>
            <?php
            $payments = $pdo->query('
                SELECT p.amount, p.status, p.created_at, u.email 
                FROM payments p 
                LEFT JOIN users u ON p.user_id = u.id 
                ORDER BY p.id DESC LIMIT 5
            ')->fetchAll(PDO::FETCH_ASSOC);
            if(empty($payments)): ?>
                <p>No payments found.</p>
            <?php else: 
                foreach($payments as $payment): ?>
                <div style="padding:5px 0; border-bottom:1px solid #eee;">
                    <strong>$<?php echo number_format($payment['amount'], 2); ?></strong><br>
                    <span class="small">User: <?php echo htmlspecialchars($payment['email']); ?></span><br>
                    <span class="small">Status: <?php echo htmlspecialchars($payment['status']); ?></span><br>
                    <span class="small">Date: <?php echo date('M j, Y', strtotime($payment['created_at'])); ?></span>
                </div>
                <?php endforeach; 
            endif; ?>
        </div>
    </div>
</div>
<?php require 'footer.php'; ?>