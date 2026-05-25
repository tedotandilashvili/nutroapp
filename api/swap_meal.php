<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error'=>'invalid method')); exit; }

$input    = json_decode(file_get_contents('php://input'), true);
$meal_name= isset($input['meal_name'])  ? $input['meal_name']  : '';
$meal_type= isset($input['meal_type'])  ? $input['meal_type']  : '';
$calories = isset($input['calories'])   ? (int)$input['calories'] : 400;
$goal     = isset($input['goal'])       ? $input['goal']       : 'Weight Loss';
$budget   = isset($input['budget'])     ? $input['budget']     : 'Medium';
$allergies= isset($input['allergies'])  ? $input['allergies']  : 'none';

if (!$meal_name) { echo json_encode(array('error'=>'meal_name required')); exit; }

$db = getDB();
$db->exec("SET NAMES utf8mb4");

// Get approved ingredients
$price_rows = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka')->fetchAll();
$ing_prices = array();
foreach ($price_rows as $r) {
    $cheapest = getMinPrice($r);
    if ($cheapest['price']) {
        $ing_prices[] = $r['name_ka'] . '=' . $cheapest['price'] . '(' . $cheapest['store'] . ')';
    }
}
$ing_str = implode(',', $ing_prices);

$goal_note = '';
if ($goal === 'Weight Loss') {
    $goal_note = 'WL: boiled/baked/grilled only. No fried. FORBIDDEN:ჩახოხბილი,ლობიანი,მწვადი,ხინკალი,საცივი.';
} elseif ($goal === 'Muscle Gain') {
    $goal_note = 'MG: high protein. Include chicken/eggs/beans.';
}

$prompt = "Meal: \"{$meal_name}\" ({$meal_type}, ~{$calories}kcal). User dislikes it.
Suggest 3 alternative meals with similar calories (±100kcal).
Goal:{$goal_note} Budget:{$budget}. Allergies:{$allergies}.
Use ONLY these ingredients:{$ing_str}
Georgian meal names only. Return ONLY JSON, start { end }:
{\"alternatives\":[{\"name\":\"KA_NAME\",\"type\":\"TYPE\",\"calories\":N,\"protein_g\":N,\"carbs_g\":N,\"fat_g\":N,\"ingredients\":\"i1,i2\",\"portion\":\"Xg\",\"cost_gel\":N,\"best_store\":\"S\",\"reason\":\"KA_REASON\"}]}";

$result = callClaudeRaw($prompt, 1200);

if (isset($result['error'])) {
    echo json_encode(array('error' => $result['error'])); exit;
}
if (!isset($result['alternatives'])) {
    echo json_encode(array('error' => 'Invalid AI response')); exit;
}

echo json_encode(array('alternatives' => $result['alternatives']), JSON_UNESCAPED_UNICODE);
