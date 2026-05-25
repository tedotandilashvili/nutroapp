<?php
require_once __DIR__ . '/../admin/auth_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$db   = getDB();
$stmt = $db->query('SELECT ingredient_key, name_ka, name_en, unit FROM ingredient_prices ORDER BY name_en');
$ingredients = $stmt->fetchAll();

$list = array();
foreach ($ingredients as $i) {
    $list[] = "- {$i['name_en']} ({$i['name_ka']}), unit: {$i['unit']}, key: {$i['ingredient_key']}";
}
$ingredient_list = implode("\n", $list);

$prompt = "You are a Georgian market price expert. It is " . date('F Y') . ".
Estimate realistic retail prices in GEL for these grocery items at 5 Tbilisi stores: Agrohub, 2Nabiji, Carrefour, Goodwill, Spar.
Ingredients:\n" . $ingredient_list . "\n
CRITICAL: Return ONLY a raw JSON object. Start with { end with }. No markdown, no backticks, no explanation.
Structure: {\"prices\":[{\"key\":\"kvercxi\",\"agrohub\":4.90,\"nabiji\":4.50,\"carrefour\":3.90,\"goodwill\":4.00,\"spar\":4.30}]}";

$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 3000,
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
$curl_err  = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);
$text = '';
if (isset($data['content'])) {
    foreach ($data['content'] as $block) {
        if ($block['type'] === 'text') $text .= $block['text'];
    }
}

// Try parse
$clean = preg_replace('/```json\s*/i', '', $text);
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
    'http_code'    => $http_code,
    'curl_error'   => $curl_err,
    'raw_text_100' => substr($text, 0, 500),
    'json_error'   => $json_err,
    'parsed_ok'    => $parsed !== null,
    'prices_count' => ($parsed && isset($parsed['prices'])) ? count($parsed['prices']) : 0,
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
