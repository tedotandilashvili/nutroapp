<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(array('error'=>'login required')); exit; }

$profile = getUserProfile($_SESSION['user_id']);
$profile['days'] = 3;

$prompt = buildDietPrompt($profile);
$prompt_len = strlen($prompt);
$prompt_tokens_est = round($prompt_len / 4);

// Send to Claude and capture raw response
$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 3500,
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
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$raw_text = '';
if (isset($data['content'])) {
    foreach ($data['content'] as $block) {
        if (isset($block['type']) && $block['type'] === 'text') $raw_text .= $block['text'];
    }
}

// Try parse
$clean = preg_replace('/```json\s*/i', '', $raw_text);
$clean = preg_replace('/```\s*/', '', $clean);
$clean = trim($clean);
$start = strpos($clean, '{');
$end   = strrpos($clean, '}');
if ($start !== false && $end !== false) {
    $clean = substr($clean, $start, $end - $start + 1);
}
$parsed   = json_decode($clean, true);
$json_err = json_last_error_msg();

echo json_encode(array(
    'prompt_length'    => $prompt_len,
    'prompt_tokens_est'=> $prompt_tokens_est,
    'http_code'        => $http_code,
    'raw_first_100'    => substr($raw_text, 0, 100),
    'raw_last_100'     => substr($raw_text, -100),
    'raw_length'       => strlen($raw_text),
    'first_char'       => mb_substr($raw_text, 0, 1),
    'json_error'       => $json_err,
    'parsed_ok'        => $parsed !== null,
    'stop_reason'      => isset($data['stop_reason']) ? $data['stop_reason'] : null,
    'output_tokens'    => isset($data['usage']['output_tokens']) ? $data['usage']['output_tokens'] : null,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);