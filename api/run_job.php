<?php
/**
 * Step 2: Client calls this to run the actual AI generation.
 * Returns immediately with status — client should call job_status.php to poll.
 * Uses self-calling curl trick to run in background on shared hosting.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }

$job_id  = trim(isset($_POST['job_id']) ? $_POST['job_id'] : '');
if (!$job_id) { echo json_encode(array('error'=>'no job_id')); exit; }

$user_id = (int)$_SESSION['user_id'];
$db      = getDB();

$stmt = $db->prepare('SELECT * FROM generate_jobs WHERE job_id=? AND user_id=? AND status="pending"');
$stmt->execute(array($job_id, $user_id));
$job = $stmt->fetch();
if (!$job) { echo json_encode(array('error'=>'job not found or already running')); exit; }

// Mark as running
$db->prepare('UPDATE generate_jobs SET status="running", updated_at=? WHERE job_id=?')
   ->execute(array(time(), $job_id));

// Mark running BEFORE flush — so polling finds correct status
$db->prepare('UPDATE generate_jobs SET status="running", updated_at=? WHERE job_id=?')
   ->execute(array(time(), $job_id));

// Return to client immediately
echo json_encode(array('status'=>'running', 'job_id'=>$job_id));

// Flush to client
while (ob_get_level() > 0) { ob_end_flush(); }
flush();

// Now keep running in background
ignore_user_abort(true);
set_time_limit(300);

// Get profile and days
$profile = getUserProfile($user_id);
$days    = isset($job['days']) ? (int)$job['days'] : 5;
if (!in_array($days, array(3,5,7))) $days = 5;
$profile['days'] = $days;

try {
    $plan = generateFullPlan($profile);

    if (isset($plan['error'])) {
        $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
           ->execute(array($plan['error'], time(), $job_id));
    } elseif (!isset($plan['days']) || !is_array($plan['days'])) {
        $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
           ->execute(array('AI პასუხი არასწორია — სცადეთ თავიდან.', time(), $job_id));
    } else {
        $plan_id = savePlanToDB($user_id, $profile, $plan);
        $db->prepare('UPDATE generate_jobs SET status="done", plan_id=?, updated_at=? WHERE job_id=?')
           ->execute(array($plan_id, time(), $job_id));
    }
} catch (Exception $e) {
    $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
       ->execute(array($e->getMessage(), time(), $job_id));
}