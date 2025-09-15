<?php 
require 'header.php'; 
require 'config.php'; 
if(session_status()===PHP_SESSION_NONE) session_start(); 
if(!isset($_SESSION['role']) || $_SESSION['role']!=='admin') redirect('login.php');

// Handle task approval/rejection
if(isset($_GET['approve'])){ 
    $task_id = (int)$_GET['approve']; 
    
    // Get task details
    $stmt = $pdo->prepare('SELECT ut.*, t.price, u.balance FROM user_tasks ut 
                          JOIN tasks t ON ut.task_id = t.id 
                          JOIN users u ON ut.user_id = u.id 
                          WHERE ut.id = ?');
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($task) {
        // Update task status
        $pdo->prepare('UPDATE user_tasks SET status = "approved", completed_at = NOW() WHERE id = ?')->execute([$task_id]);
        
        // Update user balance
        $new_balance = $task['balance'] + $task['price'];
        $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$new_balance, $task['user_id']]);
        
        // Record payment
        $pdo->prepare('INSERT INTO payments (user_id, task_id, amount, payment_type, status) VALUES (?, ?, ?, "task_payment", "COMPLETED")')
            ->execute([$task['user_id'], $task['task_id'], $task['price']]);
    }
    
    redirect('admin_tasks_review.php'); 
}

if(isset($_GET['reject'])){ 
    $task_id = (int)$_GET['reject']; 
    $pdo->prepare('UPDATE user_tasks SET status = "rejected" WHERE id = ?')->execute([$task_id]); 
    redirect('admin_tasks_review.php'); 
}

if(isset($_GET['auto_approve_videos'])){ 
    // Auto-approve completed video tasks
    $video_tasks = $pdo->query('
        SELECT ut.id, ut.user_id, ut.task_id, t.price, u.balance 
        FROM user_tasks ut 
        JOIN tasks t ON ut.task_id = t.id 
        JOIN users u ON ut.user_id = u.id 
        WHERE ut.status = "completed" 
        AND ut.video_completed = 1
        AND t.video_url IS NOT NULL
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    $approved_count = 0;
    foreach($video_tasks as $task) {
        // Update task status
        $pdo->prepare('UPDATE user_tasks SET status = "approved", completed_at = NOW() WHERE id = ?')->execute([$task['id']]);
        
        // Update user balance
        $new_balance = $task['balance'] + $task['price'];
        $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$new_balance, $task['user_id']]);
        
        // Record payment
        $pdo->prepare('INSERT INTO payments (user_id, task_id, amount, payment_type, status) VALUES (?, ?, ?, "task_payment", "COMPLETED")')
            ->execute([$task['user_id'], $task['task_id'], $task['price']]);
        
        $approved_count++;
    }
    
    $message = "Auto-approved $approved_count video tasks.";
}

// Get tasks for review
$tasks = $pdo->query('
    SELECT ut.*, u.email, u.fullname, t.title, t.price, t.video_url
    FROM user_tasks ut 
    JOIN users u ON ut.user_id = u.id 
    JOIN tasks t ON ut.task_id = t.id 
    WHERE ut.status = "completed" 
    ORDER BY ut.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
    <h2>Review Completed Tasks</h2>
    
    <?php if(isset($message)): ?>
        <div style="color:green"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if(!empty($tasks) && count(array_filter($tasks, fn($t) => $t['video_url'])) > 0): ?>
        <div style="margin-bottom: 15px;">
            <a href="?auto_approve_videos=1" class="button">Auto-Approve Completed Videos</a>
            <small>This will automatically approve all completed video tasks and pay users.</small>
        </div>
    <?php endif; ?>
    
    <?php if(empty($tasks)): ?>
        <div class="small">No tasks pending review.</div>
    <?php else: 
        foreach($tasks as $task): ?>
        <div class="card" style="margin-top:10px">
            <h3><?php echo htmlspecialchars($task['title']); ?></h3>
            <p><strong>User:</strong> <?php echo htmlspecialchars($task['fullname']); ?> (<?php echo htmlspecialchars($task['email']); ?>)</p>
            <p><strong>Price:</strong> $<?php echo number_format($task['price'], 2); ?></p>
            <p><strong>Submitted:</strong> <?php echo htmlspecialchars($task['created_at']); ?></p>
            
            <?php if($task['video_url']): ?>
                <p><strong>Video Task:</strong> 
                    <?php if($task['video_completed']): ?>
                        <span style="color: green;">✓ Completed</span> 
                        (Watched: <?php echo gmdate("H:i:s", $task['video_watch_time']); ?>)
                    <?php else: ?>
                        <span style="color: orange;">Incomplete</span>
                        (Watched: <?php echo gmdate("H:i:s", $task['video_watch_time']); ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <div class="card" style="background-color: #f8f9fa;">
                <h4>Submission Details:</h4>
                <p><?php echo nl2br(htmlspecialchars($task['submitted_data'])); ?></p>
            </div>
            
            <?php if($task['file_path']): ?>
                <div class="card" style="background-color: #e9ecef; margin-top: 10px;">
                    <h4>Uploaded File:</h4>
                    <p>
                        <a href="<?php echo htmlspecialchars($task['file_path']); ?>" target="_blank" download>
                            Download Submitted File
                        </a>
                    </p>
                    <?php 
                    $file_ext = pathinfo($task['file_path'], PATHINFO_EXTENSION);
                    if(in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?php echo htmlspecialchars($task['file_path']); ?>" style="max-width: 100%; max-height: 300px; margin-top: 10px;">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top:10px">
                <a href="?approve=<?php echo (int)$task['id']; ?>" style="color: green; margin-right: 15px;">✓ Approve ($<?php echo number_format($task['price'], 2); ?>)</a>
                <a href="?reject=<?php echo (int)$task['id']; ?>" style="color: red;">✗ Reject</a>
            </div>
        </div>
        <?php endforeach; 
    endif; ?>
</div>
<?php require 'footer.php'; ?>