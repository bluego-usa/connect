<?php
// config.php - MySQL Database Connection & auto-setup
$host = "localhost";
$dbname = "bluego";
$username = "bluego_user";
$password = "secure_password_123";

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'mp4', 'mov', 'avi']);
define('UPLOAD_PATH', 'uploads/');
define('MIN_WITHDRAWAL', 20.00); // Minimum withdrawal amount

try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists, create if not
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbname");
} catch (PDOException $e) {
    // If connection fails with dedicated user, try with root (for initial setup)
    try {
        $username = "root";
        $password = "";
        
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if database exists, create if not
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE $dbname");
        
        // Try to create the dedicated user (if it doesn't exist)
        try {
            $pdo->exec("CREATE USER 'bluego_user'@'localhost' IDENTIFIED BY 'secure_password_123'");
            $pdo->exec("GRANT ALL PRIVILEGES ON bluego.* TO 'bluego_user'@'localhost'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (Exception $e) {
            // User might already exist, which is fine
        }
    } catch (PDOException $e2) {
        die("Database connection failed: " . $e2->getMessage() . 
            "<br>Please check your database credentials in config.php");
    }
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Create tables if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150),
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255),
    balance DECIMAL(10,2) DEFAULT 0.00,
    activated TINYINT(1) DEFAULT 0,
    activation_paid TINYINT(1) DEFAULT 0,
    role ENUM('user','admin') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    description TEXT,
    video_url VARCHAR(255),
    external_link VARCHAR(255),
    video_duration INT DEFAULT 0, -- Video duration in seconds
    price DECIMAL(7,2),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS user_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    task_id INT,
    status ENUM('pending','completed','approved','rejected') DEFAULT 'pending',
    completed_at DATETIME NULL,
    submitted_data TEXT,
    file_path VARCHAR(255),
    video_watch_time INT DEFAULT 0, -- How many seconds user watched
    video_completed TINYINT(1) DEFAULT 0, -- Whether user completed watching
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    task_id INT NULL,
    amount DECIMAL(7,2),
    payment_type ENUM('activation','task_payment','withdrawal') DEFAULT 'task_payment',
    status VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150),
    subject VARCHAR(200),
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Check and add missing columns
$columns_to_check = [
    'users' => [
        'activation_paid' => "ALTER TABLE users ADD COLUMN activation_paid TINYINT(1) DEFAULT 0",
        'activated' => "ALTER TABLE users ADD COLUMN activated TINYINT(1) DEFAULT 0",
        'balance' => "ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00"
    ],
    'tasks' => [
        'video_url' => "ALTER TABLE tasks ADD COLUMN video_url VARCHAR(255)",
        'external_link' => "ALTER TABLE tasks ADD COLUMN external_link VARCHAR(255)",
        'video_duration' => "ALTER TABLE tasks ADD COLUMN video_duration INT DEFAULT 0"
    ],
    'user_tasks' => [
        'file_path' => "ALTER TABLE user_tasks ADD COLUMN file_path VARCHAR(255)",
        'video_watch_time' => "ALTER TABLE user_tasks ADD COLUMN video_watch_time INT DEFAULT 0",
        'video_completed' => "ALTER TABLE user_tasks ADD COLUMN video_completed TINYINT(1) DEFAULT 0"
    ],
    'payments' => [
        'payment_type' => "ALTER TABLE payments ADD COLUMN payment_type ENUM('activation','task_payment','withdrawal') DEFAULT 'task_payment'"
    ]
];

foreach ($columns_to_check as $table => $columns) {
    foreach ($columns as $column => $sql) {
        try {
            $pdo->query("SELECT $column FROM $table LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec($sql);
        }
    }
}

// Insert default admin if not exists
$admin_email = 'admin@bluego.com';
$admin_password = 'Admin123#';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$admin_email]);
if(!$stmt->fetch()){
    $hash = password_hash($admin_password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (fullname,email,password,activated,activation_paid,role) VALUES (?,?,?,1,1,"admin")');
    $ins->execute(['Administrator', $admin_email, $hash]);
}

// Seed demo tasks if none exist
$cnt = $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
if($cnt == 0){
    $ins = $pdo->prepare('INSERT INTO tasks (title,description,price,active,video_duration) VALUES (?,?,?,1,?)');
    $ins->execute(['Survey Participation','Answer a 2-minute survey',2.00, 120]);
    $ins->execute(['App Testing (15 mins)','Test the app and provide feedback',5.00, 900]);
    $ins->execute(['Watch Video Tutorial','Watch this tutorial video to earn money',3.00, 180]);
}

// Create indexes for better performance
try {
    $pdo->exec("CREATE INDEX idx_users_email ON users(email)");
    $pdo->exec("CREATE INDEX idx_users_activated ON users(activated)");
    $pdo->exec("CREATE INDEX idx_payments_user_id ON payments(user_id)");
    $pdo->exec("CREATE INDEX idx_payments_status ON payments(status)");
    $pdo->exec("CREATE INDEX idx_payments_type ON payments(payment_type)");
    $pdo->exec("CREATE INDEX idx_tasks_active ON tasks(active)");
    $pdo->exec("CREATE INDEX idx_user_tasks_status ON user_tasks(status)");
} catch (PDOException $e) {
    // Indexes might already exist, which is fine
}

// Create indexes for better performance
try {
    $pdo->exec("CREATE INDEX idx_users_email ON users(email)");
    $pdo->exec("CREATE INDEX idx_users_activated ON users(activated)");
    $pdo->exec("CREATE INDEX idx_payments_user_id ON payments(user_id)");
    $pdo->exec("CREATE INDEX idx_payments_status ON payments(status)");
    $pdo->exec("CREATE INDEX idx_payments_type ON payments(payment_type)");
    $pdo->exec("CREATE INDEX idx_tasks_active ON tasks(active)");
    $pdo->exec("CREATE INDEX idx_user_tasks_status ON user_tasks(status)");
} catch (PDOException $e) {
    // Indexes might already exist, which is fine
}
?>