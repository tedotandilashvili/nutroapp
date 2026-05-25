<?php
/**
 * NutroApp Push Notifications via Expo Push API
 * Sends evening reminder (previous night) about tomorrow's breakfast
 *
 * cPanel Cron — run at 21:00 every day:
 *   0 21 * * * /usr/local/bin/php /home/mwgbsngr/public_html/cron/push_reminders.php
 */

if (PHP_SAPI !== 'cli') {
    $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
    if ($secret !== 'NUTROAPP_CRON_SECRET_CHANGE_ME') {
        http_response_code(403); die('Forbidden');
    }
}

define('NUTROAPP_ROOT', dirname(__DIR__));
require_once NUTROAPP_ROOT . '/config/database.php';

$db  = getDB();
$db->exec("SET NAMES utf8mb4");
$now = time();

function logMsg($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function sendExpoPush($tokens, $title, $body, $data = array()) {
    if (empty($tokens)) return array();

    // Expo batch limit = 100
    $chunks   = array_chunk($tokens, 100);
    $results  = array();

    foreach ($chunks as $chunk) {
        $messages = array();
        foreach ($chunk as $token) {
            $messages[] = array(
                'to'    => $token,
                'sound' => 'default',
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
                'priority' => 'high',
            );
        }

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data_resp = json_decode($resp, true);
        if (isset($data_resp['data'])) {
            $results = array_merge($results, $data_resp['data']);
        }
    }
    return $results;
}

logMsg('=== Push Reminders START ===');

// ── 1. Evening reminder (21:00) — tomorrow's breakfast ──────────────────────
// Find users who have an active diet plan
$users_with_plans = $db->query(
    'SELECT DISTINCT dp.user_id, u.name,
            (SELECT pm.name FROM plan_meals pm
             JOIN plan_days pd ON pd.id = pm.day_id
             WHERE pd.plan_id = dp.id AND pm.meal_type LIKE "%საუზმე%"
             ORDER BY pd.day_number ASC LIMIT 1) as breakfast_name,
            (SELECT pm.calories FROM plan_meals pm
             JOIN plan_days pd ON pd.id = pm.day_id
             WHERE pd.plan_id = dp.id AND pm.meal_type LIKE "%საუზმე%"
             ORDER BY pd.day_number ASC LIMIT 1) as breakfast_cal
     FROM diet_plans dp
     JOIN users u ON u.id = dp.user_id
     WHERE dp.created_at > ' . ($now - 7 * 86400) . '
     ORDER BY dp.created_at DESC'
)->fetchAll();

// Group by user_id (latest plan only)
$user_map = array();
foreach ($users_with_plans as $u) {
    if (!isset($user_map[$u['user_id']])) {
        $user_map[$u['user_id']] = $u;
    }
}

foreach ($user_map as $user_id => $u) {
    // Get push tokens for this user
    $stmt = $db->prepare('SELECT token FROM push_tokens WHERE user_id=?');
    $stmt->execute(array($user_id));
    $tokens = array_column($stmt->fetchAll(), 'token');

    if (empty($tokens)) continue;

    $breakfast = $u['breakfast_name'] ?: 'საუზმე';
    $cal       = $u['breakfast_cal']  ? ' (' . $u['breakfast_cal'] . 'კკ)' : '';

    $results = sendExpoPush(
        $tokens,
        '🌙 ხვალ დილის საუზმე',
        $breakfast . $cal . ' — მზად გქონდეს!',
        array('screen' => 'plan')
    );

    foreach ($results as $r) {
        if (isset($r['status']) && $r['status'] === 'error') {
            // Token invalid — remove it
            if (isset($r['details']['error']) && $r['details']['error'] === 'DeviceNotRegistered') {
                $db->prepare('DELETE FROM push_tokens WHERE token=?')
                   ->execute(array($r['id']));
                logMsg("Removed invalid token for user $user_id");
            }
        }
    }

    logMsg("✓ Sent breakfast reminder to user {$user_id}: {$breakfast}");
}

// ── 2. Morning reminder (08:00) — good morning + today's plan ───────────────
// Check if it's morning (run separate cron at 08:00 or check hour)
$hour = (int)date('G');
if ($hour >= 7 && $hour <= 9) {
    $stmt = $db->query('SELECT DISTINCT user_id FROM push_tokens');
    $all_users = array_column($stmt->fetchAll(), 'user_id');

    foreach ($all_users as $user_id) {
        $stmt = $db->prepare('SELECT token FROM push_tokens WHERE user_id=?');
        $stmt->execute(array($user_id));
        $tokens = array_column($stmt->fetchAll(), 'token');
        if (empty($tokens)) continue;

        // Get today's meals from latest plan
        $plan = $db->prepare(
            'SELECT dp.id FROM diet_plans dp
             WHERE dp.user_id=? ORDER BY dp.created_at DESC LIMIT 1'
        );
        $plan->execute(array($user_id));
        $plan = $plan->fetch();
        if (!$plan) continue;

        $day_num = max(1, (int)round(($now - (time() - 86400)) / 86400));

        $meals = $db->prepare(
            'SELECT pm.name, pm.calories, pm.meal_type
             FROM plan_meals pm
             JOIN plan_days pd ON pd.id = pm.day_id
             WHERE pd.plan_id=? AND pd.day_number=?
             ORDER BY pm.id ASC'
        );
        $meals->execute(array($plan['id'], $day_num));
        $meals = $meals->fetchAll();

        if (empty($meals)) continue;

        $summary = array();
        foreach ($meals as $m) { $summary[] = $m['name']; }
        $body = implode(' · ', array_slice($summary, 0, 3));

        sendExpoPush($tokens, '☀️ დღის კვების გეგმა', $body, array('screen'=>'plan'));
        logMsg("✓ Morning reminder sent to user $user_id");
    }
}

// ── 3. Water reminder (14:00) ────────────────────────────────────────────────
if ($hour >= 13 && $hour <= 15) {
    $stmt = $db->query(
        'SELECT DISTINCT pt.user_id, pt.token
         FROM push_tokens pt
         LEFT JOIN water_logs wl ON wl.user_id = pt.user_id AND wl.logged_at >= ' . mktime(0,0,0) . '
         WHERE wl.id IS NULL OR wl.glasses < 4'
    );
    $water_users = $stmt->fetchAll();

    $tokens = array_column($water_users, 'token');
    if (!empty($tokens)) {
        sendExpoPush($tokens, '💧 წყლის შეხსენება', 'დღეს 8 ჭიქა წყალი დალიე! სხეული გმადლობს 💪', array('screen'=>'tracker'));
        logMsg("✓ Water reminder sent to " . count($tokens) . " users");
    }
}

logMsg('=== Push Reminders END ===');