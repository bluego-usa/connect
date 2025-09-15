<?php
if(session_status() === PHP_SESSION_NONE) session_start();
function sanitizeInput($data){ return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8'); }
function redirect($url){ header('Location: ' . $url); exit; }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BlueGo</title>
<style>
:root{--bg:#f5f7fb;--primary:#0b74ff;--white:#fff;--muted:#6b7280;--card-shadow:0 8px 30px rgba(2,6,23,0.08)}
*{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:linear-gradient(180deg,var(--bg),#eef2ff);margin:0;padding:20px;color:#0f172a}
.container{width:100%;max-width:1100px;margin:0 auto} .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
.logo{font-weight:700;color:var(--primary);font-size:20px} .nav a{margin-left:12px;color:var(--primary);text-decoration:none;font-weight:600}
.card{background:var(--white);border-radius:12px;padding:20px;box-shadow:var(--card-shadow);margin-bottom:16px}
.form-row{margin-bottom:12px} label{display:block;margin-bottom:6px;color:var(--muted);font-size:14px}
input[type=text],input[type=password],input[type=email],input[type=tel],select,textarea{width:100%;padding:10px;border:1px solid #e6e9ef;border-radius:8px;font-size:15px;background:#fff}
textarea{min-height:100px} button{background:var(--primary);color:#fff;border:none;padding:10px 16px;border-radius:8px;font-size:15px;cursor:pointer}
.small{font-size:13px;color:var(--muted)} .top-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.table{width:100%;border-collapse:collapse} .table th,.table td{padding:8px;border-bottom:1px solid #eef2ff;text-align:left} .actions a{margin-right:8px;color:var(--primary)}
.faq-item {margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;}
.faq-item h4 {margin-bottom: 8px;}
@media(max-width:600px){.topbar{flex-direction:column;align-items:flex-start}.nav a{margin:8px 8px 0 0;display:inline-block}}
</style>
</head>
<body>
<div class="container">
  <div class="topbar"><div class="logo">BlueGo</div><div class="nav">
<?php
// ... existing header code ...
if(isset($_SESSION['role']) && $_SESSION['role']==='admin'){
    echo '<a href="admin_dashboard.php">Dashboard</a><a href="admin_users.php">Users</a><a href="admin_tasks.php">Tasks</a><a href="admin_tasks_review.php">Review Tasks</a><a href="payments.php">Payments</a><a href="logout.php">Logout</a>';
} elseif(isset($_SESSION['role']) && $_SESSION['role']==='user'){
    echo '<a href="dashboard.php">Dashboard</a><a href="tasks.php">Tasks</a><a href="profile.php">Profile</a><a href="payments.php">Payments</a><a href="withdraw.php">Withdraw</a><a href="logout.php">Logout</a>';
} else {
    echo '<a href="index.php">Home</a><a href="about.php">About</a><a href="services.php">Services</a><a href="contact.php">Contact</a><a href="login.php">Login</a><a href="register.php">Register</a>';
}
// ... rest of header code ...
?>
  </div></div>