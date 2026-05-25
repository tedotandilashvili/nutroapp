<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(array('error' => 'არ ხართ ავტორიზებული.')); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('error' => 'Invalid method.')); exit;
}

$user_id = $_SESSION['user_id'];

// Check subscription
$check = canGeneratePlan($user_id);
if (!$check['allowed']) {
    $msg = $check['reason'] === 'no_subscription'
        ? 'გამოწერა საჭიროა. იხილეთ /nutroapp/pricing.php'
        : 'თვის ლიმიტი ამოიწურა ('.$check['count'].'/'.$check['max'].'). გადადით უფრო მაღალ გეგმაზე.';
    echo json_encode(array('error' => $msg, 'reason' => $check['reason'])); exit;
}

$sub     = $check['sub'];
$profile = getUserProfile($user_id);
if (!$profile) { echo json_encode(array('error' => 'პროფილი ვერ მოიძებნა.')); exit; }

$days = (int)(isset($_POST['days']) ? $_POST['days'] : 5);
$days = max(3, min($days, (int)$sub['max_days']));
if (!in_array($days, array(3, 5, 7))) $days = 3;

$profile['days'] = $days;

$plan = callClaude(buildDietPrompt($profile));
if (isset($plan['error'])) { echo json_encode(array('error' => 'AI-ის შეცდომა: '.$plan['error'])); exit; }
if (!isset($plan['days']) || !is_array($plan['days'])) {
    echo json_encode(array('error' => 'AI-ის პასუხი არასწორია.')); exit;
}

$plan_id = savePlanToDB($user_id, $profile, $plan);
echo json_encode(array('success'=>true, 'plan_id'=>$plan_id, 'redirect'=>'/nutroapp/plan.php?id='.$plan_id));
