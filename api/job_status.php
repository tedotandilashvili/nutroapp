<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }

$job_id = trim(isset($_GET['id']) ? $_GET['id'] : '');
if (!$job_id) { echo json_encode(array('error'=>'no job id')); exit; }

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM generate_jobs WHERE job_id=? AND user_id=?');
$stmt->execute(array($job_id, (int)$_SESSION['user_id']));
$job  = $stmt->fetch();
if (!$job) { echo json_encode(array('error'=>'job not found')); exit; }

// If pending — start processing now (this poll triggers the work)
if ($job['status'] === 'pending') {
    // Mark as running
    $db->prepare('UPDATE generate_jobs SET status="running", updated_at=? WHERE job_id=?')
       ->execute(array(time(), $job_id));

    // Get profile and run generation
    $user_id = (int)$_SESSION['user_id'];
    $profile = getUserProfile($user_id);
    $days    = isset($job['days']) ? (int)$job['days'] : 5;
    if (!in_array($days, array(3,5,7))) $days = 5;
    $profile['days'] = $days;

    try {
        $plan = generateFullPlan($profile);

        if (isset($plan['error'])) {
            $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
               ->execute(array($plan['error'], time(), $job_id));
            echo json_encode(array('status'=>'error', 'error'=>$plan['error'])); exit;
        } elseif (!isset($plan['days']) || !is_array($plan['days'])) {
            $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
               ->execute(array('AI პასუხი არასწორია.', time(), $job_id));
            echo json_encode(array('status'=>'error', 'error'=>'AI პასუხი არასწორია.')); exit;
        } else {
            $plan_id = savePlanToDB($user_id, $profile, $plan);
            $db->prepare('UPDATE generate_jobs SET status="done", plan_id=?, updated_at=? WHERE job_id=?')
               ->execute(array($plan_id, time(), $job_id));
            echo json_encode(array('status'=>'done', 'redirect'=>'/plan.php?id='.$plan_id)); exit;
        }
    } catch (Exception $e) {
        $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
           ->execute(array($e->getMessage(), time(), $job_id));
        echo json_encode(array('status'=>'error', 'error'=>$e->getMessage())); exit;
    }
}

// Already running or done — return current status
$resp = array('status' => $job['status']);
if ($job['status'] === 'done') {
    $resp['redirect'] = '/plan.php?id=' . $job['plan_id'];
}
if ($job['status'] === 'error') {
    $resp['error'] = $job['error_message'];
}
echo json_encode($resp);