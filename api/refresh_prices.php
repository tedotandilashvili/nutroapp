<?php
// NutroApp - Admin API: Refresh prices via Claude AI
// Called via AJAX from prices.php
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

Estimate realistic retail prices in GEL (Georgian Lari) for the following grocery ingredients at these 5 stores in Tbilisi, Georgia:
- Agrohub (organic/farm market)
- 2Nabiji (online grocery)
- Carrefour (hypermarket)
- Goodwill (hypermarket)
- Spar (supermarket)

Ingredients:
$ingredient_list

Rules:
- Prices must be realistic for Georgia in " . date('Y') . "
- Agrohub is typically 10-20% more expensive (organic/premium)
- Carrefour and Goodwill are typically cheapest for staples
- 2Nabiji is mid-range
- Spar is slightly more expensive than Carrefour
- Return ONLY valid raw JSON, no markdown, no backticks, no explanation

JSON structure:
{
  \"prices\": [
    {
      \"key\": \"kvercxi\",
      \"agrohub\": 4.90,
      \"nabiji\": 4.50,
      \"carrefour\": 3.90,
      \"goodwill\": 4.00,
      \"spar\": 4.30
    }
  ]
}";

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
curl_close($ch);

if ($response === false || $http_code !== 200) {
    echo json_encode(array('error' => 'API error: ' . $http_code));
    exit;
}

$data = json_decode($response, true);
$text = '';
foreach ($data['content'] as $block) {
    if ($block['type'] === 'text') $text .= $block['text'];
}

$text   = preg_replace('/```json|```/', '', $text);
$text   = trim($text);
$parsed = json_decode($text, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['prices'])) {
    echo json_encode(array('error' => 'Invalid JSON from AI'));
    exit;
}

// Save to DB
$updated = 0;
foreach ($parsed['prices'] as $p) {
    $key = $p['key'];
    $stmt = $db->prepare(
        'UPDATE ingredient_prices
         SET agrohub_price=?, nabiji_price=?, carrefour_price=?, goodwill_price=?, spar_price=?,
             ai_estimated=1, updated_at=?, updated_by=?
         WHERE ingredient_key=?'
    );
    $stmt->execute(array(
        isset($p['agrohub'])   ? (float)$p['agrohub']   : null,
        isset($p['nabiji'])    ? (float)$p['nabiji']     : null,
        isset($p['carrefour']) ? (float)$p['carrefour']  : null,
        isset($p['goodwill'])  ? (float)$p['goodwill']   : null,
        isset($p['spar'])      ? (float)$p['spar']       : null,
        time(),
        $_SESSION['admin_id'],
        $key
    ));
    if ($stmt->rowCount() > 0) $updated++;
}

echo json_encode(array(
    'success' => true,
    'updated' => $updated,
    'message' => $updated . ' პროდუქტის ფასი განახლდა AI-ის მიერ'
));
