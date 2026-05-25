<?php
/**
 * Payment callback handler (webhook)
 * BOG and TBC call this URL after payment
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';
$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true);

$db = getDB();

if ($provider === 'bog') {
    $order_id = isset($data['order']['order_id'])    ? $data['order']['order_id']    : '';
    $status   = isset($data['order']['status'])       ? $data['order']['status']       : '';
    if ($order_id && $status === 'completed') {
        activatePayment($db, 'bog', $order_id, $data);
    }
}

if ($provider === 'tbc') {
    $pay_id = isset($data['payId']) ? $data['payId'] : '';
    $status = isset($data['status']) ? $data['status'] : '';
    if ($pay_id && $status === 'SUCCEEDED') {
        activatePayment($db, 'tbc', $pay_id, $data);
    }
}

echo json_encode(array('ok'=>true));

function activatePayment($db, $provider, $order_id, $data) {
    $stmt = $db->prepare('SELECT * FROM payments WHERE provider=? AND provider_order_id=? AND status="pending"');
    $stmt->execute(array($provider, $order_id));
    $payment = $stmt->fetch();
    if (!$payment) return;

    $txn_id = isset($data['order']['payment_detail']['trx_id']) ? $data['order']['payment_detail']['trx_id']
            : (isset($data['transactionId']) ? $data['transactionId'] : 'CB-'.time());

    $db->prepare('UPDATE payments SET status="completed",provider_txn_id=?,payload=?,updated_at=? WHERE id=?')
       ->execute(array($txn_id, json_encode($data), time(), $payment['id']));

    // Cancel existing subscription
    $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE user_id=? AND status="active"')
       ->execute(array($payment['user_id']));

    // Activate new subscription
    $months = 1;
    $now    = time();
    $db->prepare(
        'INSERT INTO user_subscriptions (user_id,plan_id,status,started_at,expires_at,created_at,notes)
         VALUES (?,?,"active",?,?,?,?)'
    )->execute(array($payment['user_id'],$payment['plan_id'],$now,$now+$months*30*86400,$now,$provider.' payment #'.$payment['id']));

    // Notification
    $db->prepare('INSERT INTO notifications (user_id,type,title,message,created_at) VALUES (?,?,?,?,?)')
       ->execute(array($payment['user_id'],'subscription','✅ გადახდა დადასტურდა!','Transaction: '.$txn_id,time()));
}
