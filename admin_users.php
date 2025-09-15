<?php 
require 'header.php'; 
require 'config.php'; 
require 'payment_config.php'; // Added currency constant
if(session_status()===PHP_SESSION_NONE) session_start(); 
if(!isset($_SESSION['role']) || $_SESSION['role']!=='admin') redirect('login.php');

// Handle user actions
if(isset($_GET['delete'])){ 
    $id = (int)$_GET['delete']; 
    $pdo->prepare('DELETE FROM users WHERE id = ? AND role != "admin"')->execute([$id]); 
    redirect('admin_users.php'); 
}

if(isset($_GET['activate'])){ 
    $id = (int)$_GET['activate']; 
    $pdo->prepare('UPDATE users SET activated = 1 WHERE id = ?')->execute([$id]); 
    redirect('admin_users.php'); 
}

if(isset($_GET['deactivate'])){ 
    $id = (int)$_GET['deactivate']; 
    $pdo->prepare('UPDATE users SET activated = 0 WHERE id = ?')->execute([$id]); 
    redirect('admin_users.php'); 
}

if(isset($_GET['mark_paid'])){ 
    $id = (int)$_GET['mark_paid']; 
    $pdo->prepare('UPDATE users SET activation_paid = 1 WHERE id = ?')->execute([$id]); 
    
    // Also activate the user if marked as paid
    $pdo->prepare('UPDATE users SET activated = 1 WHERE id = ?')->execute([$id]);
    
    redirect('admin_users.php'); 
}

if(isset($_GET['add_balance'])){ 
    $id = (int)$_GET['add_balance']; 
    $amount = (float)$_GET['amount']; 
    
    if($amount > 0) {
        // Get current balance
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update balance
        $new_balance = $user['balance'] + $amount;
        $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$new_balance, $id]);
        
        // Record the transaction
        $pdo->prepare('INSERT INTO payments (user_id, amount, payment_type, status) VALUES (?, ?, "task_payment", "admin_added")')->execute([$id, $amount]);
        
        $message = 'Balance added successfully.';
    }
}

// Get users with error handling for missing columns
try {
    $users = $pdo->query('SELECT id,fullname,email,phone,balance,activated,activation_paid,role,created_at FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If columns don't exist yet, use a simpler query
    $users = $pdo->query('SELECT id,fullname,email,phone,role,created_at FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    
    // Add default values for missing columns
    foreach ($users as &$user) {
        $user['balance'] = 0.00;
        $user['activated'] = 0;
        $user['activation_paid'] = 0;
    }
}
?>
<div class="card">
    <h2>Manage Users</h2>
    
    <?php if(isset($message)): ?>
        <div style="color:green"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if(empty($users)): ?>
        <div class="small">No users found.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Activation Paid</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['fullname']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                    <?php echo CURRENCY; ?> <?php echo number_format($u['balance'], 2); ?>
                    <?php if($u['role'] !== 'admin'): ?>
                        <br>
                        <form method="GET" action="" style="display:inline;">
                            <input type="hidden" name="add_balance" value="<?php echo $u['id']; ?>">
                            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" style="width: 80px; padding: 2px;">
                            <button type="submit" style="padding: 2px 5px;">Add</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($u['activated']): ?>
                        <span style="color: green;">Activated</span>
                    <?php else: ?>
                        <span style="color: red;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($u['activation_paid']): ?>
                        <span style="color: green;">Yes</span>
                    <?php else: ?>
                        <span style="color: red;">No</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($u['role']); ?></td>
                <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                <td class="actions">
                    <?php if($u['role'] !== 'admin'): ?>
                        <?php if($u['activated']): ?>
                            <a href="?deactivate=<?php echo (int)$u['id']; ?>">Deactivate</a>
                        <?php else: ?>
                            <a href="?activate=<?php echo (int)$u['id']; ?>">Activate</a>
                        <?php endif; ?>
                        
                        <?php if(!$u['activation_paid']): ?>
                            <a href="?mark_paid=<?php echo (int)$u['id']; ?>">Mark Paid & Activate</a>
                        <?php endif; ?>
                        
                        <a href="?delete=<?php echo (int)$u['id']; ?>" onclick="return confirm('Delete user?')">Delete</a>
                    <?php else: ?>
                        <span class="small">Protected</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Quick User Activation</h3>
    <p>Use this form to manually activate a user without requiring payment:</p>
    
    <form method="GET" action="">
        <div class="form-row">
            <label>Select User</label>
            <select name="mark_paid" required>
                <option value="">Select User</option>
                <?php foreach($users as $u): ?>
                    <?php if($u['role'] !== 'admin' && !$u['activation_paid']): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['fullname']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <button type="submit">Activate User Account</button>
        </div>
    </form>
</div>

<?php require 'footer.php'; ?>