<?php
require 'header.php';
require 'config.php';

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message_content = sanitizeInput($_POST['message'] ?? '');
    
    if(empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $error = 'All fields are required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // In a real application, you would send an email here
        // For now, we'll just store it in the database
        try {
            $stmt = $pdo->prepare('INSERT INTO contacts (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $email, $subject, $message_content]);
            $message = 'Your message has been sent. We will get back to you soon!';
        } catch (PDOException $e) {
            $error = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}
?>
<div class="card">
    <h2>Contact Us</h2>
    
    <?php if(!empty($message)): ?>
        <div style="color: green; margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if(!empty($error)): ?>
        <div style="color: #b00020; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="form-row">
            <label>Your Name</label>
            <input type="text" name="name" required>
        </div>
        
        <div class="form-row">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>
        
        <div class="form-row">
            <label>Subject</label>
            <input type="text" name="subject" required>
        </div>
        
        <div class="form-row">
            <label>Message</label>
            <textarea name="message" rows="5" required></textarea>
        </div>
        
        <div class="form-row">
            <button type="submit">Send Message</button>
        </div>
    </form>
    
    <div style="margin-top: 30px;">
        <h3>Other Ways to Reach Us</h3>
        <p>Email: <a href="mailto:companybluego@gmail.com">companybluego@gmail.com</a></p>
        <p>For payment-related issues, please include your transaction ID in your message.</p>
    </div>
</div>
<?php require 'footer.php'; ?>