<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(array('error' => 'not logged in')); exit;
}

$profile = getUserProfile($_SESSION['user_id']);
$profile['days'] = 3;

$prompt = buildDietPrompt($profile);

$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 5000,
    'messages'   => array(array('role' => 'user', 'content' => $prompt))
));

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'x-api-key: ' . ANTHROPIC_API_KEY,
    'anthropic-version: 2023-06-01'
));
curl_setopt($ch, CURLOPT_TIMEOUT, 90);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$text = '';
foreach ($data['content'] as $block) {
    if ($block['type'] === 'text') $text .= $block['text'];
}

$clean   = preg_replace('/```json|```/', '', $text);
$clean   = trim($clean);
$parsed  = json_decode($clean, true);
$json_err = json_last_error_msg();

echo json_encode(array(
    'http_code'  => $http_code,
    'raw_text'   => substr($text, 0, 2000),
    'json_error' => $json_err,
    'parsed_ok'  => $parsed !== null,
    'has_days'   => isset($parsed['days']),
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
