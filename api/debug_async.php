<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$result = array();

// 1. Check login
$result['logged_in'] = isLoggedIn();
if (!isLoggedIn()) { echo json_encode($result); exit; }

$db = getDB();

// 2. Check generate_jobs table exists
try {
    $cnt = $db->query("SELECT COUNT(*) FROM generate_jobs")->fetchColumn();
    $result['generate_jobs_table'] = 'OK, rows: ' . $cnt;
} catch (Exception $e) {
    $result['generate_jobs_table'] = 'ERROR: ' . $e->getMessage();
}

// 3. Try inserting a test job
try {
    $job_id = 'test_' . time();
    $db->prepare('INSERT INTO generate_jobs (job_id, user_id, status, created_at) VALUES (?, ?, "pending", ?)')
       ->execute(array($job_id, $_SESSION['user_id'], time()));
    $result['insert_job'] = 'OK, job_id: ' . $job_id;

    // Clean up
    $db->prepare('DELETE FROM generate_jobs WHERE job_id=?')->execute(array($job_id));
    $result['cleanup'] = 'OK';
} catch (Exception $e) {
    $result['insert_job'] = 'ERROR: ' . $e->getMessage();
}

// 4. Check ignore_user_abort support
$result['ignore_user_abort'] = function_exists('ignore_user_abort') ? 'supported' : 'not supported';

// 5. Check output buffering
$result['ob_level'] = ob_get_level();

// 6. Check curl timeout setting
$result['curl_available'] = function_exists('curl_init') ? 'yes' : 'no';

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
