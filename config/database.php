<?php
// NutroApp - Database Configuration
// PHP 5.6 compatible

date_default_timezone_set('Asia/Tbilisi');

define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');       // Change to your MySQL user
define('DB_PASS', '');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');
define('ANTHROPIC_API_KEY', 'AAAA');


function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET . ';connect_timeout=5';
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(array('error' => 'Database connection failed: ' . $e->getMessage())));
        }
    }
    return $pdo;
}

// ── Email (SMTP) Configuration ─────────────────────────────────────────────
// Change these to your hosting SMTP settings
define('SMTP_HOST',     '');   // Your hosting mail server
define('SMTP_PORT',     587);                   // 587 (TLS) or 465 (SSL) or 25
define('SMTP_USER',     '');
define('SMTP_PASS',     '');
define('SMTP_FROM',     '');
define('SMTP_FROM_NAME','');
define('SMTP_SECURE',   'tls');                 // 'tls', 'ssl', or '' for plain
