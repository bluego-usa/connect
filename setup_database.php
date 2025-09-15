<?php
// setup_database.php - Run this once to set up the database
echo "<h2>BlueGo Database Setup</h2>";

// Database configuration
$host = "localhost";
$root_user = "root";
$root_pass = ""; // Default XAMPP/WAMP password
$dbname = "bluego";
$app_user = "bluego_user";
$app_pass = "secure_password_123";

try {
    // Connect to MySQL server as root
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $root_user, $root_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>Database '$dbname' created or already exists.</p>";
    
    // Create application user
    try {
        $pdo->exec("CREATE USER '$app_user'@'localhost' IDENTIFIED BY '$app_pass'");
        echo "<p>User '$app_user' created.</p>";
    } catch (PDOException $e) {
        echo "<p>User '$app_user' already exists or couldn't be created: " . $e->getMessage() . "</p>";
    }
    
    // Grant privileges
    $pdo->exec("GRANT ALL PRIVILEGES ON $dbname.* TO '$app_user'@'localhost'");
    $pdo->exec("FLUSH PRIVILEGES");
    echo "<p>Privileges granted to '$app_user'.</p>";
    
    // Switch to the database
    $pdo->exec("USE $dbname");
    
    // Create tables
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(150),
            email VARCHAR(150) UNIQUE,
            phone VARCHAR(50),
            password VARCHAR(255),
            role ENUM('user','admin') DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "tasks" => "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200),
            description TEXT,
            price DECIMAL(7,2),
            active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "payments" => "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            task_id INT NULL,
            amount DECIMAL(7,2),
            paypal_txn VARCHAR(100),
            status VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "contacts" => "CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(150),
            subject VARCHAR(200),
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "<p>Table '$name' created or already exists.</p>";
    }
    
    // Insert default admin
    $admin_email = 'admin@bluego.com';
    $admin_password = 'Admin123#';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$admin_email]);
    
    if(!$stmt->fetch()){
        $hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (fullname,email,password,role) VALUES (?,?,?,"admin")');
        $ins->execute(['Administrator', $admin_email, $hash]);
        echo "<p>Default admin user created (email: $admin_email, password: $admin_password).</p>";
    } else {
        echo "<p>Admin user already exists.</p>";
    }
    
    // Seed demo tasks
    $cnt = $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    if($cnt == 0){
        $tasks = [
            ['Survey Participation', 'Answer a 2-minute survey', 2.00],
            ['App Testing (15 mins)', 'Test the app and provide feedback', 5.00],
            ['Article Writing (short post)', 'Write a short article (~150 words)', 7.50]
        ];
        
        $ins = $pdo->prepare('INSERT INTO tasks (title,description,price,active) VALUES (?,?,?,1)');
        foreach ($tasks as $task) {
            $ins->execute($task);
        }
        echo "<p>Demo tasks added.</p>";
    } else {
        echo "<p>Tasks already exist in database.</p>";
    }
    
    // Create indexes
    $indexes = [
        "idx_users_email" => "CREATE INDEX idx_users_email ON users(email)",
        "idx_payments_user_id" => "CREATE INDEX idx_payments_user_id ON payments(user_id)",
        "idx_payments_status" => "CREATE INDEX idx_payments_status ON payments(status)",
        "idx_tasks_active" => "CREATE INDEX idx_tasks_active ON tasks(active)"
    ];
    
    foreach ($indexes as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p>Index '$name' created.</p>";
        } catch (PDOException $e) {
            echo "<p>Index '$name' already exists or couldn't be created.</p>";
        }
    }
    
    echo "<h3 style='color:green;'>Database setup completed successfully!</h3>";
    echo "<p>You can now <a href='index.php'>access the BlueGo website</a>.</p>";
    echo "<p>Admin login: $admin_email / $admin_password</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your MySQL server is running and credentials are correct.</p>";
}
echo "<h2>BlueGo Database Update</h2>";

require 'config.php';

try {
    // Check if activation_paid column exists, add if not
    try {
        $pdo->query("SELECT activation_paid FROM users LIMIT 1");
        echo "<p>activation_paid column already exists in users table.</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN activation_paid TINYINT(1) DEFAULT 0");
        echo "<p>Added activation_paid column to users table.</p>";
    }

    // Check if activated column exists, add if not
    try {
        $pdo->query("SELECT activated FROM users LIMIT 1");
        echo "<p>activated column already exists in users table.</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN activated TINYINT(1) DEFAULT 0");
        echo "<p>Added activated column to users table.</p>";
    }

    // Check if balance column exists, add if not
    try {
        $pdo->query("SELECT balance FROM users LIMIT 1");
        echo "<p>balance column already exists in users table.</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00");
        echo "<p>Added balance column to users table.</p>";
    }

    // Check if payment_type column exists, add if not
    try {
        $pdo->query("SELECT payment_type FROM payments LIMIT 1");
        echo "<p>payment_type column already exists in payments table.</p>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_type ENUM('activation','task_payment','withdrawal') DEFAULT 'task_payment'");
        echo "<p>Added payment_type column to payments table.</p>";
    }

    // Update admin user to be activated
    $pdo->exec("UPDATE users SET activated = 1, activation_paid = 1 WHERE role = 'admin'");
    echo "<p>Updated admin accounts to be activated.</p>";
    
    echo "<h3 style='color:green;'>Database update completed successfully!</h3>";
    echo "<p>You can now <a href='index.php'>access the BlueGo website</a>.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your MySQL server is running and credentials are correct.</p>";
}
?>