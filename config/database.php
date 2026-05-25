<?php
// NutroApp - Database Configuration
// PHP 5.6 compatible

date_default_timezone_set('Asia/Tbilisi');

define('DB_HOST', '188.169.47.66');
define('DB_NAME', 'nutroapp');
define('DB_USER', 'root');       // Change to your MySQL user
define('DB_PASS', 'a213546b');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');
define('ANTHROPIC_API_KEY', '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');


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
define('SMTP_HOST',     'mail.nutroapp.ge');   // Your hosting mail server
define('SMTP_PORT',     587);                   // 587 (TLS) or 465 (SSL) or 25
define('SMTP_USER',     'no-reply@nutroapp.ge');
define('SMTP_PASS',     'Tedotedo2022!');
define('SMTP_FROM',     'no-reply@nutroapp.ge');
define('SMTP_FROM_NAME','NutroApp');
define('SMTP_SECURE',   'tls');                 // 'tls', 'ssl', or '' for plain
