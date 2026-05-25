<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

$result = array();

// 1. Check login
$result['logged_in'] = isLoggedIn();
$result['session_user_id'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!isLoggedIn()) {
    echo json_encode($result); exit;
}

$user_id = $_SESSION['user_id'];

// 2. Check profile
$profile = getUserProfile($user_id);
$result['has_profile'] = !empty($profile);

// 3. Check subscription tables exist
$db = getDB();
try {
    $cnt = $db->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    $result['subscription_plans_count'] = (int)$cnt;
} catch (Exception $e) {
    $result['subscription_plans_error'] = $e->getMessage();
}

try {
    $cnt = $db->query("SELECT COUNT(*) FROM user_subscriptions WHERE user_id = " . (int)$user_id)->fetchColumn();
    $result['user_subscriptions_count'] = (int)$cnt;
} catch (Exception $e) {
    $result['user_subscriptions_error'] = $e->getMessage();
}

// 4. Check canGeneratePlan
try {
    $check = canGeneratePlan($user_id);
    $result['can_generate'] = $check;
} catch (Exception $e) {
    $result['can_generate_error'] = $e->getMessage();
}

// 5. Check ingredient_prices table
try {
    $cnt = $db->query("SELECT COUNT(*) FROM ingredient_prices")->fetchColumn();
    $result['ingredient_prices_count'] = (int)$cnt;
} catch (Exception $e) {
    $result['ingredient_prices_error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
