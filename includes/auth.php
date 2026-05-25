<?php
// NutroApp - Auth & Session Helpers
// PHP 5.6 compatible

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch();
}

function getUserProfile($user_id) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
    $stmt->execute(array($user_id));
    return $stmt->fetch();
}

function hashPassword($password) {
    // Cost 8 instead of default 10 — safe but faster on shared hosting
    return password_hash($password, PASSWORD_BCRYPT, array('cost' => 8));
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Subscription helpers ───────────────────────────────────────────────────────

function getUserSubscription($user_id) {
    $db   = getDB();
    $now  = time();
    $stmt = $db->prepare(
        'SELECT us.*, sp.slug, sp.name, sp.name_ka, sp.price_gel,
                sp.max_plans_month, sp.max_days, sp.ai_price_refresh, sp.features
         FROM user_subscriptions us
         JOIN subscription_plans sp ON sp.id = us.plan_id
         WHERE us.user_id = ? AND us.status = "active" AND us.expires_at > ?
         ORDER BY us.created_at DESC LIMIT 1'
    );
    $stmt->execute(array($user_id, $now));
    return $stmt->fetch();
}

function getUserPlanCount($user_id) {
    $db        = getDB();
    $month_start = mktime(0, 0, 0, date('n'), 1);
    $stmt      = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE user_id=? AND created_at>=?');
    $stmt->execute(array($user_id, $month_start));
    return (int)$stmt->fetchColumn();
}

function canGeneratePlan($user_id) {
    $sub = getUserSubscription($user_id);
    if (!$sub) return array('allowed'=>false, 'reason'=>'no_subscription');
    if ($sub['max_plans_month'] === -1) return array('allowed'=>true, 'sub'=>$sub);
    $count = getUserPlanCount($user_id);
    if ($count >= $sub['max_plans_month']) {
        return array('allowed'=>false, 'reason'=>'limit_reached', 'count'=>$count, 'max'=>$sub['max_plans_month']);
    }
    return array('allowed'=>true, 'sub'=>$sub, 'count'=>$count);
}

function getAllPlans() {
    $db = getDB();
    return $db->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY sort_order')->fetchAll();
}
