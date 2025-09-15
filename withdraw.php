<?php
require 'header.php';
require 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') redirect('login.php');

// Get user balance
$stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user['balance'];

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $account_details = sanitizeInput($_POST['account_details']);
    
    if($amount < MIN_WITHDRAWAL) {
        $error = 'Minimum withdrawal amount is $' . number_format(MIN_WITHDRAWAL, 2) . '.';
    } elseif($amount > $balance) {
        $error = 'Insufficient balance. Your current balance is $' . number_format($balance, 2);
    } else {
        // Create withdrawal request
        $stmt = $pdo->prepare('INSERT INTO payments (user_id, amount, payment_type, status) VALUES (?, ?, "withdrawal", "pending")');
        if($stmt->execute([$_SESSION['user_id'], $amount])) {
            // Deduct the amount from user's balance
            $new_balance = $balance - $amount;
            $update_stmt = $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
            $update_stmt->execute([$new_balance, $_SESSION['user_id']]);
            
            $message = 'Withdrawal request submitted successfully! It will be processed within 24-48 hours.';
        } else {
            $error = 'Error processing withdrawal request. Please try again.';
        }
    }
}
?>

<div class="card">
    <h2>Request Withdrawal</h2>
    
    <?php if($message): ?>
        <div class="card" style="background-color: #d4edda; border-color: #c3e6cb;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="card" style="background-color: #f8d7da; border-color: #f5c6cb;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>Your Current Balance: $<?php echo number_format($balance, 2); ?></h3>
        <p class="small">Withdrawals are processed within 24-48 hours after approval.</p>
        <p class="small" style="color: #d63384; font-weight: bold;">Minimum withdrawal amount: $<?php echo number_format(MIN_WITHDRAWAL, 2); ?></p>
    </div>
    
    <?php if($balance < MIN_WITHDRAWAL): ?>
        <div class="card" style="background-color: #fff3cd; border-color: #ffeaa7;">
            <h3>Insufficient Balance for Withdrawal</h3>
            <p>You need at least $<?php echo number_format(MIN_WITHDRAWAL, 2); ?> to request a withdrawal.</p>
            <p>Your current balance is $<?php echo number_format($balance, 2); ?>.</p>
            <p>Complete more tasks to reach the minimum withdrawal amount.</p>
            <p><a href="tasks.php">View Available Tasks</a></p>
        </div>
    <?php else: ?>
        <form method="post" action="">
            <div class="form-row">
                <label>Withdrawal Amount ($)</label>
                <input type="number" name="amount" step="0.01" min="<?php echo MIN_WITHDRAWAL; ?>" max="<?php echo $balance; ?>" required>
                <div class="small">Minimum: $<?php echo number_format(MIN_WITHDRAWAL, 2); ?></div>
            </div>
            
            <div class="form-row">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="">Select Payment Method</option>
                    <option value="PayPal">PayPal</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Venmo">Venmo</option>
                    <option value="CashApp">CashApp</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-row">
                <label>Account Details</label>
                <textarea name="account_details" rows="3" required placeholder="Enter your account email, phone number, or other details for receiving payment"></textarea>
            </div>
            
            <div class="form-row">
                <button type="submit" name="request_withdrawal">Request Withdrawal</button>
            </div>
        </form>
    <?php endif; ?>
    
    <div class="card" style="margin-top: 20px;">
        <h3>Withdrawal History</h3>
        <?php
        // Check if payment_type column exists before querying
        try {
            $pdo->query("SELECT payment_type FROM payments LIMIT 1");
            $column_exists = true;
        } catch (PDOException $e) {
            $column_exists = false;
        }
        
        if ($column_exists) {
            $withdrawals = $pdo->prepare('
                SELECT amount, status, created_at 
                FROM payments 
                WHERE user_id = ? AND payment_type = "withdrawal" 
                ORDER BY created_at DESC
            ');
            $withdrawals->execute([$_SESSION['user_id']]);
            $withdrawal_history = $withdrawals->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback if payment_type column doesn't exist yet
            $withdrawals = $pdo->prepare('
                SELECT amount, status, created_at 
                FROM payments 
                WHERE user_id = ? 
                ORDER BY created_at DESC
            ');
            $withdrawals->execute([$_SESSION['user_id']]);
            $withdrawal_history = $withdrawals->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if(empty($withdrawal_history)): ?>
            <p>No withdrawal history found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($withdrawal_history as $withdrawal): ?>
                    <tr>
                        <td>$<?php echo number_format($withdrawal['amount'], 2); ?></td>
                        <td>
                            <?php if($withdrawal['status'] === 'COMPLETED'): ?>
                                <span style="color: green;">Completed</span>
                            <?php elseif($withdrawal['status'] === 'pending'): ?>
                                <span style="color: blue;">Pending</span>
                            <?php else: ?>
                                <span style="color: red;"><?php echo htmlspecialchars($withdrawal['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($withdrawal['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>