<?php
// admin_config.php - admin credentials (change immediately in production)
define('ADMIN_EMAIL','admin@bluego.com');
define('ADMIN_PASSWORD_HASH', password_hash('Admin123#', PASSWORD_DEFAULT));
?>