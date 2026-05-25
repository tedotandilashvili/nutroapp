<?php
require_once __DIR__ . '/../admin/auth_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

// Use only 3 ingredients to keep it short and fast
$ingredients = array(
    array('ingredient_key'=>'kvercxi', 'name_ka'=>'კვერცხი', 'name_en'=>'Eggs (10 pcs)', 'unit'=>'10 ც'),
    array('ingredient_key'=>'qatami',  'name_ka'=>'ქათმის მკერდი', 'name_en'=>'Chicken breast', 'unit'=>'კგ'),
    array('ingredient_key'=>'lobio',   'name_ka'=>'ლობიო',   'name_en'=>'Dried beans',   'unit'=>'კგ'),
);

$list = array();
foreach ($ingredients as $i) {
    $list[] = "- {$i['name_en']} (key: {$i['ingredient_key']})";
}

$prompt = 'Return ONLY this JSON, no other text, no markdown:
{"prices":[{"key":"kvercxi","agrohub":4.90,"nabiji":4.50,"carrefour":3.90,"goodwill":4.00,"spar":4.30},{"key":"qatami","agrohub":13.00,"nabiji":12.00,"carrefour":10.90,"goodwill":11.00,"spar":12.50},{"key":"lobio","agrohub":5.00,"nabiji":4.50,"carrefour":3.80,"goodwill":4.20,"spar":4.50}]}

But replace the numbers with your own realistic GEL price estimates for Tbilisi Georgia ' . date('Y') . '.';

$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 500,
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
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$text = '';
if (isset($data['content'])) {
    foreach ($data['content'] as $block) {
        if (isset($block['type']) && $block['type'] === 'text') $text .= $block['text'];
    }
}

echo json_encode(array(
    'http_code'  => $http_code,
    'raw_text'   => $text,
    'first_char' => substr($text, 0, 1),
    'last_char'  => substr($text, -1),
    'length'     => strlen($text),
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
