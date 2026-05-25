<?php
require_once __DIR__ . '/../admin/auth_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$job_id = trim(isset($_GET['id']) ? $_GET['id'] : '');
if (!$job_id) { echo json_encode(array('error' => 'no job id')); exit; }

$db   = getDB();
$stmt = $db->prepare('SELECT status, error_message FROM generate_jobs WHERE job_id=?');
$stmt->execute(array($job_id));
$job  = $stmt->fetch();

if (!$job) { echo json_encode(array('error' => 'not found')); exit; }

$resp = array('status' => $job['status']);
if ($job['status'] === 'done') {
    $resp['message'] = $job['error_message']; // reused field for success msg
}
if ($job['status'] === 'error') {
    $resp['error'] = $job['error_message'];
}
echo json_encode($resp);
