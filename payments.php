<?php 
require 'header.php'; 
require 'config.php'; 
if(session_status()===PHP_SESSION_NONE) session_start(); 
if(!isset($_SESSION['role'])) redirect('login.php');

if($_SESSION['role'] === 'user'){
    $stmt = $pdo->prepare('SELECT p.*, t.title FROM payments p LEFT JOIN tasks t ON p.task_id = t.id WHERE p.user_id = ? ORDER BY p.id DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $payments = $pdo->query('SELECT p.*, t.title, u.email as user_email FROM payments p LEFT JOIN tasks t ON p.task_id = t.id LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="card">
    <h2>Payments</h2>
    <p class="small">Activation payments should be sent to companybluego@gmail.com via PayPal.</p>
    
    <?php if(empty($payments)): ?>
        <div class="small">No payments recorded.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Order/Txn</th>
                <th>Task</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <?php if($_SESSION['role']!=='user') echo '<th>User</th>'; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($payments as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['paypal_txn']); ?></td>
                <td><?php echo htmlspecialchars($p['title'] ?? ''); ?></td>
                <td><?php echo '$' . number_format($p['amount'],2); ?></td>
                <td><?php echo htmlspecialchars($p['status']); ?></td>
                <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                <?php if($_SESSION['role']!=='user') echo '<td>'.htmlspecialchars($p['user_email'] ?? '').'</td>'; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php require 'footer.php'; ?>