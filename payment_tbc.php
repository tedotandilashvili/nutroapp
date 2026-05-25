<?php
/**
 * TBC Pay eCommerce Payment
 * Docs: https://developers.tbcbank.ge/docs/tbc-pay
 *
 * To activate:
 * 1. Register at https://developers.tbcbank.ge
 * 2. Get API_KEY and MERCHANT_ID from TBC merchant portal
 * 3. Replace constants below
 * 4. Change TBC_TEST_MODE to false in production
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

define('TBC_API_KEY',    'YOUR_TBC_API_KEY');
define('TBC_MERCHANT_ID','YOUR_TBC_MERCHANT_ID');
define('TBC_TEST_MODE',  true);
define('TBC_BASE_URL',   TBC_TEST_MODE
    ? 'https://api.tbcbank.ge/v1'
    : 'https://api.tbcbank.ge/v1');

$payment_id = (int)(isset($_GET['payment_id']) ? $_GET['payment_id'] : 0);
$db         = getDB();
$stmt       = $db->prepare('SELECT p.*,sp.name_ka FROM payments p JOIN subscription_plans sp ON sp.id=p.plan_id WHERE p.id=? AND p.user_id=?');
$stmt->execute(array($payment_id, $_SESSION['user_id']));
$payment    = $stmt->fetch();
if (!$payment) { header('Location: /pricing.php'); exit; }

function tbcCreateOrder($payment) {
    $body = json_encode(array(
        'amount'        => array('currency'=>'GEL','total'=>(float)$payment['amount_gel'],'subTotal'=>(float)$payment['amount_gel']),
        'returnurl'     => 'https://nutroapp.ge/payment_success.php?id='.$payment['id'],
        'extra'         => 'NutroApp-'.$payment['id'],
        'merchantPaymentId' => $payment['provider_order_id'],
        'installmentProducts' => array(),
        'saveCard'      => false,
    ));
    $ch = curl_init(TBC_BASE_URL . '/tpay/payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apikey: ' . TBC_API_KEY,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

if (TBC_API_KEY === 'YOUR_TBC_API_KEY') {
    setFlash('info', 'TBC Pay ჯერ არ არის დაკავშირებული. სატესტო გადახდა გამოიყენე.');
    header('Location: /payment.php?plan=' . urlencode($payment['plan_id']));
    exit;
}

$order = tbcCreateOrder($payment);
if (!isset($order['links'][0]['uri'])) {
    setFlash('error','TBC შეკვეთა ვერ შეიქმნა.'); header('Location: /payment.php'); exit;
}

$db->prepare('UPDATE payments SET provider_order_id=?,updated_at=? WHERE id=?')
   ->execute(array($order['payId'], time(), $payment_id));

header('Location: ' . $order['links'][0]['uri']);
exit;
