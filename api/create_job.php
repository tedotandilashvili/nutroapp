<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error'=>'invalid method')); exit; }

$user_id = (int)$_SESSION['user_id'];
$check   = canGeneratePlan($user_id);
if (!$check['allowed']) {
    $msg = $check['reason'] === 'no_subscription' ? 'გამოწერა საჭიროა.' : 'თვის ლიმიტი ამოიწურა.';
    echo json_encode(array('error'=>$msg, 'reason'=>$check['reason'])); exit;
}

$sub     = $check['sub'];
$profile = getUserProfile($user_id);
if (!$profile) { echo json_encode(array('error'=>'პროფილი ვერ მოიძებნა.')); exit; }

$days = (int)(isset($_POST['days']) ? $_POST['days'] : 5);
$days = max(3, min($days, (int)$sub['max_days']));
if (!in_array($days, array(3,5,7))) $days = 3;

$db     = getDB();
$job_id = md5($user_id . microtime(true) . mt_rand());

try { $db->prepare('ALTER TABLE generate_jobs ADD COLUMN days TINYINT DEFAULT 5')->execute(); } catch(Exception $e) {}

$db->prepare(
    'INSERT INTO generate_jobs (job_id, user_id, days, status, created_at, updated_at) VALUES (?,?,?,?,?,?)'
)->execute(array($job_id, $user_id, $days, 'pending', time(), time()));

echo json_encode(array('job_id' => $job_id, 'status' => 'pending'));