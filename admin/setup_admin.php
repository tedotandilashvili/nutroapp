<?php
/**
 * NutroApp - Admin Password Setup
 * Run this ONCE via browser: http://yoursite.com/nutroapp/setup_admin.php
 * DELETE this file immediately after use.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$new_password = 'admin123'; // Change this before running if you want a different password
$hash         = hashPassword($new_password);

$db   = getDB();

// Upsert admin account
$stmt = $db->prepare('SELECT id FROM admins WHERE username = ?');
$stmt->execute(array('admin'));
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare('UPDATE admins SET password_hash = ? WHERE username = ?');
    $stmt->execute(array($hash, 'admin'));
    $action = 'განახლდა';
} else {
    $stmt = $db->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)');
    $stmt->execute(array('admin', $hash, time()));
    $action = 'შეიქმნა';
}

echo '<pre style="font-family:monospace;padding:2rem;">';
echo "Admin account {$action} successfully.\n\n";
echo "Username: admin\n";
echo "Password: {$new_password}\n\n";
echo "Hash stored: {$hash}\n\n";
echo "NOW DELETE THIS FILE from your server!\n";
echo "Path: " . __FILE__ . "\n";
echo '</pre>';
