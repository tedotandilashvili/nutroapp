<?php
require_once __DIR__ . '/../admin/auth_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

$job_id = trim(isset($_POST['job_id']) ? $_POST['job_id'] : '');
if (!$job_id) { echo json_encode(array('error' => 'no job_id')); exit; }

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM generate_jobs WHERE job_id=? AND status="pending"');
$stmt->execute(array($job_id));
$job  = $stmt->fetch();
if (!$job) { echo json_encode(array('error' => 'job not found')); exit; }

$db->prepare('UPDATE generate_jobs SET status="running", updated_at=? WHERE job_id=?')
   ->execute(array(time(), $job_id));

// Return immediately
echo json_encode(array('status' => 'running', 'job_id' => $job_id));
while (ob_get_level() > 0) { ob_end_flush(); }
flush();

ignore_user_abort(true);
set_time_limit(180);

// Build ingredient list
$stmt = $db->query('SELECT ingredient_key, name_en, unit FROM ingredient_prices ORDER BY name_en');
$ingredients = $stmt->fetchAll();

// Split into batches of 8 to keep prompt short and reliable
$batches  = array_chunk($ingredients, 8);
$all_prices = array();
$had_error  = false;

foreach ($batches as $batch) {
    $list = array();
    foreach ($batch as $i) {
        $list[] = $i['name_en'] . ' (key:' . $i['ingredient_key'] . ')';;
    }
    $keys_str = implode(', ', $list);

    $prompt = 'Return ONLY valid JSON, no markdown, no explanation. Estimate realistic GEL prices for Tbilisi Georgia ' . date('Y') . ' at 5 stores (Agrohub=premium, 2Nabiji=midrange, Carrefour=cheap, Goodwill=cheap, Spar=midrange). Format: {"prices":[{"key":"item_key","agrohub":0.00,"nabiji":0.00,"carrefour":0.00,"goodwill":0.00,"spar":0.00}]}. Items: ' . $keys_str;

    $body = json_encode(array(
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 1000,
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

    if (!$response || $http_code !== 200) { $had_error = true; continue; }

    $data = json_decode($response, true);
    $text = '';
    if (isset($data['content'])) {
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text') $text .= $block['text'];
        }
    }

    $text  = preg_replace('/```json\s*/i', '', $text);
    $text  = preg_replace('/```\s*/', '', $text);
    $text  = trim($text);
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false) {
        $text = substr($text, $start, $end - $start + 1);
    }
    $parsed = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($parsed['prices'])) {
        $all_prices = array_merge($all_prices, $parsed['prices']);
    } else {
        $had_error = true;
    }
}

if (empty($all_prices)) {
    $db->prepare('UPDATE generate_jobs SET status="error", error_message=?, updated_at=? WHERE job_id=?')
       ->execute(array('ყველა batch ვერ დამუშავდა', time(), $job_id));
    exit;
}

// Save to DB
$updated = 0;
foreach ($all_prices as $p) {
    if (!isset($p['key'])) continue;
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
        time(), $_SESSION['admin_id'],
        $p['key']
    ));
    if ($stmt->rowCount() > 0) $updated++;
}

$msg = $updated . ' პროდუქტი განახლდა' . ($had_error ? ' (ზოგი batch-ი გამოტოვდა)' : '');
$db->prepare('UPDATE generate_jobs SET status="done", error_message=?, updated_at=? WHERE job_id=?')
   ->execute(array($msg, time(), $job_id));
