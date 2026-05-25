<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
header('Location: /nutroapp/admin/login.php');
exit;
