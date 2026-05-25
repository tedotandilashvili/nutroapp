<?php
/**
 * Widget API — returns today's meals for Home Screen Widget
 * GET /api/widget.php?token=USER_TOKEN
 * Returns minimal JSON for widget display
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: max-age=3600');

$token = trim(isset($_GET['token']) ? $_GET['token'] : '');
if (!$token) { echo json_encode(array('error'=>'no token')); exit; }

$db = getDB();
$db->exec("SET NAMES utf8mb4");

// Verify token (use push_tokens table as auth)
$stmt = $db->prepare('SELECT user_id FROM push_tokens WHERE token=? LIMIT 1');
$stmt->execute(array($token));
$row = $stmt->fetch();
if (!$row) { echo json_encode(array('error'=>'invalid token')); exit; }

$user_id = (int)$row['user_id'];

// Get latest plan's today meals
$plan = $db->prepare(
    'SELECT id FROM diet_plans WHERE user_id=? ORDER BY created_at DESC LIMIT 1'
);
$plan->execute(array($user_id));
$plan = $plan->fetch();

if (!$plan) {
    echo json_encode(array('meals'=>array(), 'message'=>'გეგმა არ არის'));
    exit;
}

// Day number based on plan creation
$plan_info = $db->prepare('SELECT created_at, days FROM diet_plans WHERE id=?');
$plan_info->execute(array($plan['id']));
$plan_info = $plan_info->fetch();

$days_elapsed = floor((time() - $plan_info['created_at']) / 86400) + 1;
$day_num      = min($days_elapsed, $plan_info['days'] ?: 5);

$meals_stmt = $db->prepare(
    'SELECT pm.meal_type, pm.name, pm.calories, pm.meal_time
     FROM plan_meals pm
     JOIN plan_days pd ON pd.id = pm.day_id
     WHERE pd.plan_id=? AND pd.day_number=?
     ORDER BY pm.meal_time ASC, pm.id ASC'
);
$meals_stmt->execute(array($plan['id'], $day_num));
$meals = $meals_stmt->fetchAll();

$meal_labels = array(
    'sauzme'   => 'საუზმე',
    'branchi'  => 'ბრანჩი',
    'sadili'   => 'სადილი',
    'vaxshami' => 'ვახშამი',
);

$result = array();
foreach ($meals as $m) {
    $type = strtolower($m['meal_type']);
    $result[] = array(
        'type'     => isset($meal_labels[$type]) ? $meal_labels[$type] : $m['meal_type'],
        'name'     => $m['name'],
        'calories' => (int)$m['calories'],
        'time'     => $m['meal_time'] ?: '',
    );
}

// Today's mood
$today_mood = $db->prepare(
    'SELECT mood FROM mood_logs WHERE user_id=? AND logged_at>=? LIMIT 1'
);
$today_mood->execute(array($user_id, mktime(0,0,0)));
$mood_row = $today_mood->fetch();
$mood_emojis = array(1=>'😔',2=>'😐',3=>'🙂',4=>'😊',5=>'🤩');

echo json_encode(array(
    'meals'      => $result,
    'day'        => $day_num,
    'mood'       => $mood_row ? ($mood_emojis[$mood_row['mood']] ?? '') : null,
    'updated_at' => time(),
), JSON_UNESCAPED_UNICODE);