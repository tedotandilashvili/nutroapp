<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 100,
    'messages'   => array(array('role' => 'user', 'content' => 'Say hello in Georgian'))
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
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo json_encode(array(
    'http_code'   => $http_code,
    'curl_error'  => $curl_error,
    'api_key_set' => !empty(ANTHROPIC_API_KEY),
    'api_key_len' => strlen(ANTHROPIC_API_KEY),
    'raw_response'=> $response,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
