<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }

$input    = json_decode(file_get_contents('php://input'), true);
$token    = isset($input['token'])    ? trim($input['token'])    : '';
$platform = isset($input['platform']) ? trim($input['platform']) : 'android';

if (!$token) { echo json_encode(array('error'=>'no token')); exit; }

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];

$db->prepare(
    'INSERT INTO push_tokens (user_id, token, platform, created_at, updated_at)
     VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE user_id=?, updated_at=?'
)->execute(array($user_id, $token, $platform, time(), time(), $user_id, time()));

echo json_encode(array('ok' => true));