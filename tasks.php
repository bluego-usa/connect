<?php
require 'header.php';
require 'config.php';
require 'payment_config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) redirect('login.php');

// Check if user is activated
$stmt = $pdo->prepare('SELECT activated FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user['activated']) {
    echo "<div class='card'><h2>Account Not Activated</h2><p>You need to activate your account to access tasks. <a href='dashboard.php'>Go to Dashboard</a></p></div>";
    require 'footer.php';
    exit;
}

// Handle video completion
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_video'])) {
    $task_id = (int)$_POST['task_id'];
    $watch_time = (int)$_POST['watch_time'];
    $completed = (int)$_POST['completed'];
    
    // Check if user already has this task
    $check_stmt = $pdo->prepare('SELECT id, status FROM user_tasks WHERE user_id = ? AND task_id = ?');
    $check_stmt->execute([$_SESSION['user_id'], $task_id]);
    $existing_task = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if($existing_task) {
        if($existing_task['status'] === 'approved') {
            $message = "You've already completed and been paid for this task.";
        } else {
            // Update watch time
            $update_stmt = $pdo->prepare('UPDATE user_tasks SET video_watch_time = ?, video_completed = ? WHERE id = ?');
            $update_stmt->execute([$watch_time, $completed, $existing_task['id']]);
            
            if($completed) {
                $message = "Video completed successfully! Your task is pending review.";
            } else {
                $message = "Watch progress saved.";
            }
        }
    } else {
        // Create new task entry
        $stmt = $pdo->prepare('INSERT INTO user_tasks (user_id, task_id, video_watch_time, video_completed, status) VALUES (?, ?, ?, ?, "completed")');
        $stmt->execute([$_SESSION['user_id'], $task_id, $watch_time, $completed]);
        $message = "Video task started. Please watch the entire video to earn your reward.";
    }
}

// Handle regular task submission with file upload
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_task'])) {
    $task_id = (int)$_POST['task_id'];
    $submitted_data = sanitizeInput($_POST['submitted_data']);
    $file_path = '';
    
    // Check if user already has this task in progress
    $check_stmt = $pdo->prepare('SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? AND status IN ("pending", "completed")');
    $check_stmt->execute([$_SESSION['user_id'], $task_id]);
    
    if($check_stmt->fetch()) {
        $message = "You've already submitted this task.";
    } else {
        // Handle file upload
        if(isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['task_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if($file['size'] > MAX_FILE_SIZE) {
                $message = 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
            } elseif(!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                $message = 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES);
            } else {
                $filename = 'task_' . time() . '_' . uniqid() . '.' . $file_ext;
                $destination = UPLOAD_PATH . $filename;
                
                if(move_uploaded_file($file['tmp_name'], $destination)) {
                    $file_path = $destination;
                } else {
                    $message = 'Error uploading file.';
                }
            }
        }
        
        if(!isset($message)) {
            // Create new task submission
            $stmt = $pdo->prepare('INSERT INTO user_tasks (user_id, task_id, submitted_data, file_path, status) VALUES (?, ?, ?, ?, "completed")');
            if($stmt->execute([$_SESSION['user_id'], $task_id, $submitted_data, $file_path])) {
                $message = "Task submitted successfully! It will be reviewed by our team.";
            } else {
                $message = "Error submitting task. Please try again.";
            }
        }
    }
}

// Get available tasks
$tasks = $pdo->query('SELECT * FROM tasks WHERE active = 1 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

// Get user's completed tasks
$user_tasks_stmt = $pdo->prepare('SELECT task_id, status, video_completed FROM user_tasks WHERE user_id = ?');
$user_tasks_stmt->execute([$_SESSION['user_id']]);
$user_tasks = $user_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
$user_task_map = [];
foreach($user_tasks as $ut) {
    $user_task_map[$ut['task_id']] = $ut;
}
?>
<div class="card">
    <h2>Available Tasks</h2>
    <p class="small">Complete tasks and earn money to your account balance.</p>
    
    <?php if(isset($message)): ?>
        <div class="card" style="background-color: #d4edda; border-color: #c3e6cb;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if(empty($tasks)): ?>
        <div class="small">No tasks available at the moment. Please check back later.</div>
    <?php else: 
        foreach($tasks as $task): 
            $user_task = $user_task_map[$task['id']] ?? null;
            $status = $user_task['status'] ?? null;
            $video_completed = $user_task['video_completed'] ?? 0;
        ?>
        <div class="card" style="margin-top:10px">
            <h3><?php echo htmlspecialchars($task['title']); ?></h3>
            <p><?php echo htmlspecialchars($task['description']); ?></p>
            
            <?php if($task['video_url']): ?>
                <div style="margin:10px 0;">
                    <strong>Video Task:</strong>
                    <div id="video-player-<?php echo $task['id']; ?>">
                        <video 
                            id="task-video-<?php echo $task['id']; ?>" 
                            controls 
                            width="100%" 
                            style="max-width: 600px;"
                            ontimeupdate="updateVideoProgress(<?php echo $task['id']; ?>)"
                            onended="videoCompleted(<?php echo $task['id']; ?>)">
                            <source src="<?php echo htmlspecialchars($task['video_url']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div id="video-progress-<?php echo $task['id']; ?>" style="margin-top: 5px;">
                            <small>Watch the entire video to earn <?php echo CURRENCY; ?> <?php echo number_format($task['price'], 2); ?></small>
                        </div>
                        <?php if($video_completed): ?>
                            <div style="color: green; margin-top: 5px;">
                                ✓ Video completed - pending approval
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if($task['external_link']): ?>
                <div style="margin:10px 0;">
                    <strong>External Resource:</strong>
                    <a href="<?php echo htmlspecialchars($task['external_link']); ?>" target="_blank">Click here to access</a>
                </div>
            <?php endif; ?>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px">
                <div><strong>Price: <?php echo CURRENCY; ?> <?php echo number_format($task['price'], 2); ?></strong></div>
                <div>
                    <?php if($status === 'approved'): ?>
                        <span style="color: green;">✓ Approved</span>
                    <?php elseif($status === 'completed' || $video_completed): ?>
                        <span style="color: blue;">⏳ Under Review</span>
                    <?php elseif($status === 'rejected'): ?>
                        <span style="color: red;">✗ Rejected</span>
                    <?php else: ?>
                        <?php if($task['video_url']): ?>
                            <button onclick="startVideoTask(<?php echo $task['id']; ?>)">Watch Video</button>
                        <?php else: ?>
                            <button onclick="openTaskModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')">Start Task</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; 
    endif; ?>
</div>

<!-- Task Submission Modal -->
<div id="taskModal" style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5)">
    <div style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:80%; max-width:600px; border-radius:8px; max-height:80vh; overflow-y:auto;">
        <span style="float:right; font-size:28px; font-weight:bold; cursor:pointer" onclick="closeModal()">&times;</span>
        <h2 id="modalTaskTitle">Complete Task</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="task_id" id="modalTaskId">
            <div class="form-row">
                <label>Your Submission</label>
                <textarea name="submitted_data" rows="6" required placeholder="Provide your task completion details here..."></textarea>
            </div>
            <div class="form-row">
                <label>Upload File (Optional, Max <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB)</label>
                <input type="file" name="task_file">
                <div class="small">Allowed file types: <?php echo implode(', ', ALLOWED_FILE_TYPES); ?></div>
            </div>
            <div class="form-row">
                <button type="submit" name="submit_task">Submit Task</button>
            </div>
        </form>
    </div>
</div>

<script>
// Video task functionality
let videoIntervals = {};

function startVideoTask(taskId) {
    const video = document.getElementById('task-video-' + taskId);
    if (video) {
        video.play();
        // Start tracking progress every 5 seconds
        videoIntervals[taskId] = setInterval(() => {
            updateVideoProgress(taskId);
        }, 5000);
    }
}

function updateVideoProgress(taskId) {
    const video = document.getElementById('task-video-' + taskId);
    if (video) {
        const watchTime = Math.floor(video.currentTime);
        const duration = Math.floor(video.duration);
        const progress = (watchTime / duration) * 100;
        
        // Update progress display
        const progressElement = document.getElementById('video-progress-' + taskId);
        if (progressElement) {
            progressElement.innerHTML = `<small>Watched: ${Math.round(progress)}% - Keep watching to earn reward</small>`;
        }
        
        // Send progress to server every 10 seconds or when significant progress is made
        if (watchTime % 10 === 0 || progress > 90) {
            saveVideoProgress(taskId, watchTime, false);
        }
    }
}

function videoCompleted(taskId) {
    const video = document.getElementById('task-video-' + taskId);
    if (video) {
        const watchTime = Math.floor(video.currentTime);
        saveVideoProgress(taskId, watchTime, true);
        
        // Clear interval
        if (videoIntervals[taskId]) {
            clearInterval(videoIntervals[taskId]);
        }
        
        // Update UI
        const progressElement = document.getElementById('video-progress-' + taskId);
        if (progressElement) {
            progressElement.innerHTML = '<small style="color: green;">✓ Video completed! Reward pending approval.</small>';
        }
    }
}

function saveVideoProgress(taskId, watchTime, completed) {
    // Send AJAX request to save progress
    const formData = new FormData();
    formData.append('complete_video', '1');
    formData.append('task_id', taskId);
    formData.append('watch_time', watchTime);
    formData.append('completed', completed ? '1' : '0');
    
    fetch('tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Progress saved:', data);
    })
    .catch(error => {
        console.error('Error saving progress:', error);
    });
}

function openTaskModal(taskId, taskTitle) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('modalTaskTitle').textContent = 'Complete: ' + taskTitle;
    document.getElementById('taskModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('taskModal').style.display = 'none';
}

// Close modal if clicked outside
window.onclick = function(event) {
    const modal = document.getElementById('taskModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php require 'footer.php'; ?>