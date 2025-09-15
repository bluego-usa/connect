<?php 
require 'header.php'; 
require 'config.php'; 
require 'payment_config.php'; // Added this line to include currency constant
if(session_status()===PHP_SESSION_NONE) session_start(); 
if(!isset($_SESSION['role']) || $_SESSION['role']!=='admin') redirect('login.php');

$message = ''; 
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_task'])){ 
    $title = sanitizeInput($_POST['title'] ?? ''); 
    $desc = sanitizeInput($_POST['description'] ?? ''); 
    $video_url = sanitizeInput($_POST['video_url'] ?? '');
    $external_link = sanitizeInput($_POST['external_link'] ?? '');
    $price = (float)$_POST['price']; 
    
    if($price <= 0 || $price > MAX_TASK_PRICE){ 
        $message = 'Price must be > 0 and not exceed ' . CURRENCY . ' ' . number_format(MAX_TASK_PRICE,2); 
    } else { 
        $stmt = $pdo->prepare('INSERT INTO tasks (title,description,video_url,external_link,price,active) VALUES (?,?,?,?,?,1)'); 
        $stmt->execute([$title,$desc,$video_url,$external_link,$price]); 
        $message = 'Task created.'; 
    } 
}

// Handle video upload
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['video_upload']) && $_FILES['video_upload']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['video_upload'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if($file['size'] > MAX_FILE_SIZE) {
        $message = 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
    } elseif(!in_array($file_ext, ALLOWED_FILE_TYPES)) {
        $message = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES);
    } else {
        $filename = 'video_' . time() . '_' . uniqid() . '.' . $file_ext;
        $destination = UPLOAD_PATH . $filename;
        
        if(move_uploaded_file($file['tmp_name'], $destination)) {
            $message = 'Video uploaded successfully. URL: ' . $destination;
        } else {
            $message = 'Error uploading file.';
        }
    }
}

// Handle adding balance to user
if(isset($_GET['add_balance'])){ 
    $user_id = (int)$_GET['add_balance']; 
    $amount = (float)$_GET['amount']; 
    
    if($amount > 0) {
        // Get current balance
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update balance
        $new_balance = $user['balance'] + $amount;
        $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$new_balance, $user_id]);
        
        // Record the transaction
        $pdo->prepare('INSERT INTO payments (user_id, amount, payment_type, status) VALUES (?, ?, "task_payment", "admin_added")')->execute([$user_id, $amount]);
        
        $message = 'Balance added successfully to user.';
    }
}

if(isset($_GET['deactivate'])){ 
    $pdo->prepare('UPDATE tasks SET active = 0 WHERE id = ?')->execute([(int)$_GET['deactivate']]); 
    redirect('admin_tasks.php'); 
}

if(isset($_GET['activate'])){ 
    $pdo->prepare('UPDATE tasks SET active = 1 WHERE id = ?')->execute([(int)$_GET['activate']]); 
    redirect('admin_tasks.php'); 
}

$tasks = $pdo->query('SELECT * FROM tasks ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query('SELECT id, fullname, email FROM users WHERE role = "user" ORDER BY fullname')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
    <h2>Manage Tasks</h2>
    
    <?php if($message): ?>
        <div style="color:green"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="form-row">
            <label>Title</label>
            <input name="title" required>
        </div>
        <div class="form-row">
            <label>Description</label>
            <textarea name="description" required></textarea>
        </div>
        <div class="form-row">
            <label>Video URL (optional)</label>
            <input name="video_url" type="url" placeholder="https://example.com/video.mp4">
        </div>
        <div class="form-row">
            <label>External Link (optional)</label>
            <input name="external_link" type="url" placeholder="https://example.com">
        </div>
        <div class="form-row">
            <label>Price (<?php echo CURRENCY; ?>)</label>
            <input name="price" type="number" step="0.01" required>
        </div>
        <div class="form-row">
            <button name="create_task" type="submit">Create Task</button>
        </div>
    </form>
    
    <h3 style="margin-top:20px">Upload Video Content</h3>
    <form method="post" action="" enctype="multipart/form-data">
        <div class="form-row">
            <label>Upload Video File (Max <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB)</label>
            <input type="file" name="video_upload" accept="video/*">
        </div>
        <div class="form-row">
            <button type="submit">Upload Video</button>
        </div>
    </form>
    
    <h3 style="margin-top:20px">Add Balance to User</h3>
    <form method="GET" action="">
        <div class="form-row">
            <label>Select User</label>
            <select name="add_balance" required>
                <option value="">Select User</option>
                <?php foreach($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Amount (<?php echo CURRENCY; ?>)</label>
            <input type="number" name="amount" step="0.01" min="0.01" required>
        </div>
        <div class="form-row">
            <button type="submit">Add Balance</button>
        </div>
    </form>
    
    <h3 style="margin-top:16px">Existing Tasks</h3>
    <?php if(empty($tasks)): ?>
        <div class="small">No tasks yet.</div>
    <?php else: 
        foreach($tasks as $t): ?>
        <div class="card" style="margin-top:10px">
            <div>
                <strong><?php echo htmlspecialchars($t['title']); ?></strong> — 
                <?php echo CURRENCY . ' ' . number_format($t['price'],2); ?>
                <?php if(!$t['active']): ?>
                    <span style="color:red">(Inactive)</span>
                <?php endif; ?>
            </div>
            <div class="small"><?php echo htmlspecialchars($t['description']); ?></div>
            <?php if($t['video_url']): ?>
                <div class="small">
                    <strong>Video:</strong> 
                    <a href="<?php echo htmlspecialchars($t['video_url']); ?>" target="_blank">View Video</a>
                </div>
            <?php endif; ?>
            <?php if($t['external_link']): ?>
                <div class="small">
                    <strong>External Link:</strong> 
                    <a href="<?php echo htmlspecialchars($t['external_link']); ?>" target="_blank">Visit Link</a>
                </div>
            <?php endif; ?>
            <div style="margin-top:8px">
                <?php if($t['active']): ?>
                    <a href="?deactivate=<?php echo (int)$t['id']; ?>">Deactivate</a>
                <?php else: ?>
                    <a href="?activate=<?php echo (int)$t['id']; ?>">Activate</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; 
    endif; ?>
</div>
<?php require 'footer.php'; ?>