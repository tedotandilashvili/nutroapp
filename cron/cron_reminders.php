<?php
/**
 * NutroApp - Cron Job: Email Reminders
 *
 * cPanel Cron Setup:
 *   Minute: 0  Hour: 10  Day: *  Month: *  Weekday: *
 *   Command: /usr/local/bin/php /home/mwgbsngr/public_html/cron/reminders.php >> /home/mwgbsngr/public_html/cron/reminders.log 2>&1
 *
 * Or via URL (less secure):
 *   https://nutroapp.ge/cron/reminders.php?secret=NUTROAPP_CRON_SECRET_CHANGE_ME
 */

if (PHP_SAPI !== 'cli') {
    $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
    if ($secret !== 'NUTROAPP_CRON_SECRET_CHANGE_ME') {
        http_response_code(403); die('Forbidden');
    }
}

define('NUTROAPP_ROOT', dirname(__DIR__));
require_once NUTROAPP_ROOT . '/config/database.php';
require_once NUTROAPP_ROOT . '/includes/auth.php';
require_once NUTROAPP_ROOT . '/includes/mailer.php';

$db  = getDB();
$now = time();

function logMsg($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

logMsg('=== NutroApp Cron START ===');

// ── 1. Expiry reminders (7, 3, 1 days before) ─────────────────────────────────
foreach (array(7, 3, 1) as $days) {
    $ws = $now + $days * 86400 - 3600;
    $we = $now + $days * 86400 + 3600;
    $stmt = $db->prepare(
        'SELECT us.*, u.name, u.email, sp.name_ka
         FROM user_subscriptions us
         JOIN users u ON u.id=us.user_id
         JOIN subscription_plans sp ON sp.id=us.plan_id
         WHERE us.status="active" AND us.expires_at>=? AND us.expires_at<=?'
    );
    $stmt->execute(array($ws, $we));
    foreach ($stmt->fetchAll() as $sub) {
        $already = $db->prepare('SELECT id FROM notifications WHERE user_id=? AND type="expiry_reminder" AND message LIKE ? AND created_at>=?');
        $already->execute(array($sub['user_id'], '%'.$days.' დღე%', mktime(0,0,0)));
        if ($already->fetch()) { logMsg("SKIP {$days}d reminder for {$sub['email']}"); continue; }

        $user = array('name'=>$sub['name'],'email'=>$sub['email']);
        $sent = sendExpiryReminderEmail($user, $sub['name_ka'], $sub['expires_at'], $days);
        $db->prepare('INSERT INTO notifications (user_id,type,title,message,created_at) VALUES (?,?,?,?,?)')->execute(array(
            $sub['user_id'],'expiry_reminder',
            'გამოწერა სრულდება '.$days.' დღეში ⏰',
            $sub['name_ka'].' — '.$days.' დღე დარჩა.',
            $now
        ));
        logMsg(($sent?'✓':'✗')." expiry {$days}d → {$sub['email']}");
    }
}

// ── 2. Mark expired ───────────────────────────────────────────────────────────
$stmt = $db->prepare('UPDATE user_subscriptions SET status="expired" WHERE status="active" AND expires_at<?');
$stmt->execute(array($now));
logMsg("Expired: ".$stmt->rowCount()." subscriptions");

// ── 3. Referral rewards ───────────────────────────────────────────────────────
$refs = $db->query(
    'SELECT r.*, u.name, u.email
     FROM referrals r JOIN users u ON u.id=r.referrer_id
     WHERE r.used=1 AND r.reward_given=0'
)->fetchAll();

foreach ($refs as $ref) {
    $has_sub = $db->prepare('SELECT COUNT(*) FROM user_subscriptions us JOIN users u ON u.id=us.user_id WHERE u.referred_by=? AND us.status="active" AND us.expires_at>?');
    $has_sub->execute(array($ref['referrer_id'],$now));
    if (!(int)$has_sub->fetchColumn()) continue;

    $db->prepare('UPDATE referrals SET reward_given=1 WHERE id=?')->execute(array($ref['id']));
    $db->prepare('UPDATE user_subscriptions SET expires_at=expires_at+? WHERE user_id=? AND status="active"')->execute(array(30*86400,$ref['referrer_id']));
    $db->prepare('INSERT INTO notifications (user_id,type,title,message,created_at) VALUES (?,?,?,?,?)')->execute(array($ref['referrer_id'],'referral','🎁 რეფერალის ჯილდო!','1 თვე დაემატა თქვენს გამოწერას!',$now));
    sendReferralRewardEmail(array('name'=>$ref['name'],'email'=>$ref['email']), 'მეგობარი');
    logMsg("✓ referral reward → {$ref['email']}");
}

// ── 4. Weekly summary (Mondays) ───────────────────────────────────────────────
if (date('N') === '1') {
    $week_ago = $now - 7*86400;
    $stmt = $db->prepare('SELECT d.user_id,u.name,u.email,COUNT(d.id) as cnt FROM diet_plans d JOIN users u ON u.id=d.user_id WHERE d.created_at>=? GROUP BY d.user_id HAVING cnt>=1');
    $stmt->execute(array($week_ago));
    foreach ($stmt->fetchAll() as $u) {
        $db->prepare('INSERT INTO notifications (user_id,type,title,message,created_at) VALUES (?,?,?,?,?)')->execute(array(
            $u['user_id'],'weekly','კვირის შეჯამება 📊',
            'ამ კვირაში '.$u['cnt'].' კვების გეგმა — კარგი სამუშაო! 💪', $now
        ));
        logMsg("✓ weekly summary → {$u['email']} ({$u['cnt']} plans)");
    }
}

logMsg('=== NutroApp Cron END ===');
