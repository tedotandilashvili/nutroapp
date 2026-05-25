<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error'=>'invalid method')); exit; }

$input    = json_decode(file_get_contents('php://input'), true);
$messages = isset($input['messages']) ? $input['messages'] : array();
if (empty($messages)) { echo json_encode(array('error'=>'no input')); exit; }

// Check if latest message has an image (large payload)
$has_image = false;
foreach ($messages as $msg) {
    if (is_array($msg['content'])) {
        foreach ($msg['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'image') {
                $has_image = true; break;
            }
        }
    }
}

$system = 'Act as a professional diet doctor and nutritionist with 20 years of clinical experience. You are currently consulting a patient in Georgia (the country). ' .
    ($has_image
        ? 'The user has sent a photo of food. Identify every food item visible in the image, estimate portion sizes based on visual cues, and calculate calories and macros.'
        : 'User tells you what they want to eat or have eaten.') .
    ' Give brief advice in Georgian and suggest improvements using local Georgian foods.

LANGUAGE RULES (critical):
- Always use correct, natural Georgian names for all foods.
- If user writes a foreign/transliterated word, convert it to proper Georgian: e.g. "ტუნა" → "კრაბის ხორცი/ტუნა", "სალათი" → "სალათი", "ბრინჯი" → "ბრინჯი".
- Common corrections: "ტუნა"="ტუნა თევზი", "კიტრი"="კიტრი", "ჩიქენი"="ქათამი", "ბიფი"="საქონლის ხორცი", "სელმონი"="ორაგული", "ეგი"="კვერცხი".
- Food names in JSON must be grammatically correct Georgian nouns (nominative case).
- Advice and suggestion must be fluent, natural Georgian sentences — not word-for-word translations.

CRITICAL: Respond ONLY with valid raw JSON starting with { and ending with }. No markdown. No backticks. No extra text.

JSON format:
{"foods":[{"name":"ტუნა თევზი","portion":"1 ქილა (150გ)","kcal":150},{"name":"კიტრი","portion":"1 საშუალო (100გ)","kcal":16}],"total_kcal":166,"protein_g":28,"carbs_g":4,"fat_g":2,"advice":"მსუბუქი და პროტეინით მდიდარი კომბინაციაა.","suggestion":"დაამატე ლიმონის წვენი და ზეითუნის ზეთი გემოსა და სასარგებლო ცხიმებისთვის.","warning":""}

Rules: warning only if truly unhealthy/excessive (else empty string). Realistic Georgian portion sizes.';

$body = json_encode(array(
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 800,
    'system'     => $system,
    'messages'   => $messages
));

// Increase timeout for image requests
$timeout = $has_image ? 40 : 25;

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'x-api-key: ' . ANTHROPIC_API_KEY,
    'anthropic-version: 2023-06-01'
));
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    $detail = $response ? json_decode($response,true) : array();
    $msg = isset($detail['error']['message']) ? $detail['error']['message'] : 'HTTP '.$http_code;
    echo json_encode(array('error' => 'API error: ' . $msg)); exit;
}

$data = json_decode($response, true);
$text = '';
if (isset($data['content'])) {
    foreach ($data['content'] as $block) {
        if (isset($block['type']) && $block['type'] === 'text') $text .= $block['text'];
    }
}

$clean = preg_replace('/```json\s*/i', '', $text);
$clean = preg_replace('/```\s*/', '', $clean);
$clean = trim($clean);
$start = strpos($clean, '{');
$end   = strrpos($clean, '}');
if ($start !== false && $end !== false) {
    $clean = substr($clean, $start, $end - $start + 1);
}

$parsed = json_decode($clean, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['total_kcal'])) {
    echo json_encode(array('error' => 'AI parse error — სცადეთ თავიდან')); exit;
}

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);