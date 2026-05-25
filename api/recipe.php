<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error'=>'method')); exit; }

$input      = json_decode(file_get_contents('php://input'), true);
$meal_name  = isset($input['meal_name'])   ? trim($input['meal_name'])   : '';
$ingredients= isset($input['ingredients']) ? trim($input['ingredients']) : '';

if (!$meal_name) { echo json_encode(array('error'=>'meal name required')); exit; }

$prompt = 'Write a simple Georgian recipe for: "' . $meal_name . '".'
        . ($ingredients ? ' Main ingredients: ' . $ingredients . '.' : '')
        . ' Respond ONLY with JSON, no markdown, start { end }.'
        . ' Format: {"prep_time":"10 წთ","cook_time":"15 წთ","difficulty":"მარტივი",'
        . '"ingredients":[{"name":"კვერცხი","amount":"2 ცალი"}],'
        . '"steps":["ნაბიჯი 1...","ნაბიჯი 2..."],'
        . '"tip":"მოკლე პრაქტიკული რჩევა"}.'
        . ' Steps in Georgian. Max 6 steps. Keep it simple and practical.';

$result = callClaudeRaw($prompt, 800);

if (isset($result['error'])) {
    echo json_encode(array('error' => $result['error'])); exit;
}

if (!isset($result['steps'])) {
    echo json_encode(array('error' => 'AI-მ სწორი ფორმატი ვერ დააბრუნა')); exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);