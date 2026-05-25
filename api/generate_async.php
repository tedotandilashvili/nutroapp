<?php
/**
 * NutroApp - Async Diet Plan Generator
 * Step 1: Client calls this → gets job_id immediately
 * Step 2: Client polls /api/job_status.php?id=JOB_ID every 3s
 * Step 3: When status=done → redirect to plan
 *
 * Uses output buffering + ignore_user_abort to keep PHP running
 * after response is sent — works on most shared hosting.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

// Catch any PHP errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode(array('error' => 'PHP Error: ' . $errstr . ' in ' . basename($errfile) . ':' . $errline));
    exit;
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        echo json_encode(array('error' => 'Fatal: ' . $err['message'] . ' line ' . $err['line']));
    }
});

if (!isLoggedIn()) {
    echo json_encode(array('error' => 'არ ხართ ავტორიზებული.')); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('error' => 'Invalid method.')); exit;
}

$user_id = $_SESSION['user_id'];
$check   = canGeneratePlan($user_id);
if (!$check['allowed']) {
    $msg = $check['reason'] === 'no_subscription'
        ? 'გამოწერა საჭიროა.'
        : 'თვის ლიმიტი ამოიწურა.';
    echo json_encode(array('error' => $msg, 'reason' => $check['reason'])); exit;
}

$sub     = $check['sub'];
$profile = getUserProfile($user_id);
if (!$profile) { echo json_encode(array('error' => 'პროფილი ვერ მოიძებნა.')); exit; }

$days = (int)(isset($_POST['days']) ? $_POST['days'] : 5);
$days = max(3, min($days, (int)$sub['max_days']));
if (!in_array($days, array(3,5,7))) $days = 3;
$profile['days'] = $days;

// Create job record in DB
$db  = getDB();
$job_id = md5($user_id . microtime(true) . rand(1000,9999));

$db->prepare(
    'INSERT INTO generate_jobs (job_id, user_id, status, created_at) VALUES (?, ?, "pending", ?)'
)->execute(array($job_id, $user_id, time()));

// Send job_id to client immediately
echo json_encode(array('job_id' => $job_id, 'status' => 'pending'));

// Flush ALL output buffer levels to client RIGHT NOW
while (ob_get_level() > 0) { ob_end_flush(); }
flush();

// Keep running after client response
ignore_user_abort(true);
set_time_limit(120);

// Now do the actual API call in background
try {
    $plan = callClaude(buildDietPrompt($profile));

    if (isset($plan['error'])) {
        $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
           ->execute(array($plan['error'], time(), $job_id));
    } elseif (!isset($plan['days']) || !is_array($plan['days'])) {
        $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
           ->execute(array('AI პასუხი არასწორია', time(), $job_id));
    } else {
        $plan_id = savePlanToDB($user_id, $profile, $plan);
        $db->prepare('UPDATE generate_jobs SET status="done", plan_id=?, updated_at=? WHERE job_id=?')
           ->execute(array($plan_id, time(), $job_id));
    }
} catch (Exception $e) {
    $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
       ->execute(array($e->getMessage(), time(), $job_id));
}
