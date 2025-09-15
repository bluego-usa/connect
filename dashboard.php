<?php
require 'header.php'; 
require 'config.php'; 
require 'payment_config.php'; // Added currency constant
if(session_status()===PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') redirect('login.php');

// Get user data
$stmt = $pdo->prepare('SELECT balance, activated, activation_paid FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's total earnings
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = ? AND status = "COMPLETED" AND payment_type = "task_payment"');
$stmt->execute([$_SESSION['user_id']]);
$total_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get completed tasks count
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM user_tasks WHERE user_id = ? AND status = "approved"');
$stmt->execute([$_SESSION['user_id']]);
$completed_tasks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<div class="card">
    <h2>Welcome <?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?></h2>
    
    <?php if(!$user_data['activated']): ?>
        <div class="card" style="background-color: #fff3cd; border-color: #ffeaa7;">
            <h3>Account Activation Required</h3>
            <p>To access tasks and start earning, you need to activate your account by making a $3 payment.</p>
            
            <h4>Activation Instructions:</h4>
            <ol>
                <li>Send $3 to <strong>companybluego@gmail.com</strong> via PayPal, Venmo, or CashApp</li>
                <li>Include your email address (<strong><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></strong>) in the payment notes</li>
                <li>After sending payment, <a href="contact.php">contact us</a> with your transaction details</li>
                <li>We will activate your account within 24 hours</li>
            </ol>
            
            <p><strong>Alternatively:</strong> If you've already paid but your account isn't activated yet, 
            <a href="contact.php">contact us</a> with your payment details for manual activation.</p>
        </div>
    <?php else: ?>
        <div class="small">Quick Links: <a href="tasks.php">Tasks</a> | <a href="profile.php">Profile</a> | <a href="payments.php">Payments</a> | <a href="withdraw.php">Withdraw</a></div>
        
        <div style="margin-top:12px" class="top-grid">
            <div class="card">
                <h3>Your Profile</h3>
                <p class="small">
                    Name: <?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?><br>
                    Email: <?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?><br>
                    Status: <span style="color: green;">Activated</span>
                </p>
            </div>
            
            <div class="card">
                <h3>Account Balance</h3>
                <p class="small"><?php echo CURRENCY; ?> <?php echo number_format($user_data['balance'], 2); ?></p>
                <p><a href="withdraw.php">Request Withdrawal</a></p>
            </div>
            
            <div class="card">
                <h3>Total Earnings</h3>
                <p class="small"><?php echo CURRENCY; ?> <?php echo number_format($total_earnings, 2); ?></p>
            </div>
            
            <div class="card">
                <h3>Tasks Completed</h3>
                <p class="small"><?php echo $completed_tasks; ?> tasks</p>
            </div>
        </div>
        
        <div style="margin-top:20px">
            <h3>Available Tasks</h3>
            <?php
            $tasks = $pdo->query('SELECT * FROM tasks WHERE active = 1 ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
            if(empty($tasks)): ?>
                <p>No tasks available at the moment.</p>
            <?php else: 
                foreach($tasks as $task): ?>
                <div style="padding:10px; border-bottom:1px solid #eee;">
                    <strong><?php echo htmlspecialchars($task['title']); ?></strong> - 
                    <?php echo CURRENCY; ?> <?php echo number_format($task['price'], 2); ?>
                    <div class="small"><?php echo htmlspecialchars($task['description']); ?></div>
                    <a href="tasks.php">View Details</a>
                </div>
                <?php endforeach; 
            endif; ?>
            <div style="margin-top:10px; text-align:center">
                <a href="tasks.php"><button>View All Tasks</button></a>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require 'footer.php'; ?>