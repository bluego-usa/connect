-- Create the database
CREATE DATABASE bluego CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create a user for the database (replace 'password' with a secure password)
CREATE USER 'bluego_user'@'localhost' IDENTIFIED BY 'secure_password_123';

-- Grant privileges to the user
GRANT ALL PRIVILEGES ON bluego.* TO 'bluego_user'@'localhost';
ALTER TABLE users ADD COLUMN activation_paid TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN activated TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE payments ADD COLUMN payment_type ENUM('activation','task_payment','withdrawal') DEFAULT 'task_payment';
UPDATE users SET activated = 1, activation_paid = 1 WHERE role = 'admin';

-- Apply the changes
FLUSH PRIVILEGES;
