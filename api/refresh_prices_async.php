<?php
/**
 * Async price refresh — same job pattern as generate
 * Step 1 (this file): create job, return job_id immediately
 * Step 2: client calls run_price_job.php
 * Step 3: client polls price_job_status.php
 */
require_once __DIR__ . '/../admin/auth_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$job_id = 'price_' . md5(time() . mt_rand());

// Reuse generate_jobs table
$db->prepare(
    'INSERT INTO generate_jobs (job_id, user_id, status, created_at, updated_at) VALUES (?,?,?,?,?)'
)->execute(array($job_id, $_SESSION['admin_id'], 'pending', time(), time()));

echo json_encode(array('job_id' => $job_id, 'status' => 'pending'));
