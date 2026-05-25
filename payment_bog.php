<?php
/**
 * BOG (Bank of Georgia) eCommerce Payment
 * Docs: https://developer.bog.ge/docs/payments
 *
 * To activate:
 * 1. Register at https://developer.bog.ge
 * 2. Get CLIENT_ID and CLIENT_SECRET from BOG merchant portal
 * 3. Replace the constants below
 * 4. Change BOG_TEST_MODE to false in production
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

define('BOG_CLIENT_ID',     'YOUR_BOG_CLIENT_ID');
define('BOG_CLIENT_SECRET', 'YOUR_BOG_CLIENT_SECRET');
define('BOG_TEST_MODE',     true);
define('BOG_BASE_URL',      BOG_TEST_MODE ? 'https://ipay.ge/opay/api/v1' : 'https://ipay.ge/opay/api/v1');

$payment_id = (int)(isset($_GET['payment_id']) ? $_GET['payment_id'] : 0);
$db         = getDB();
$stmt       = $db->prepare('SELECT p.*,sp.name_ka FROM payments p JOIN subscription_plans sp ON sp.id=p.plan_id WHERE p.id=? AND p.user_id=?');
$stmt->execute(array($payment_id, $_SESSION['user_id']));
$payment    = $stmt->fetch();

if (!$payment) { header('Location: /pricing.php'); exit; }

// Step 1: Get BOG access token
function bogGetToken() {
    $ch = curl_init(BOG_BASE_URL . '/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . base64_encode(BOG_CLIENT_ID . ':' . BOG_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    return isset($data['access_token']) ? $data['access_token'] : null;
}

// Step 2: Create BOG order
function bogCreateOrder($token, $payment) {
    $body = json_encode(array(
        'callback_url'   => 'https://nutroapp.ge/payment_callback.php?provider=bog',
        'purchase_units' => array(array(
            'amount'      => array('currency_code'=>'GEL','value'=>(string)$payment['amount_gel']),
            'description' => 'NutroApp — ' . $payment['name_ka'],
        )),
        'redirect_urls'  => array(
            'success' => 'https://nutroapp.ge/payment_success.php?id='.$payment['id'],
            'fail'    => 'https://nutroapp.ge/payment_fail.php?id='.$payment['id'],
        ),
    ));
    $ch = curl_init(BOG_BASE_URL . '/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

if (BOG_CLIENT_ID === 'YOUR_BOG_CLIENT_ID') {
    // Not configured yet
    setFlash('info', 'BOG Pay ჯერ არ არის დაკავშირებული. სატესტო გადახდა გამოიყენე.');
    header('Location: /payment.php?plan=' . urlencode($payment['plan_id']));
    exit;
}

$token  = bogGetToken();
if (!$token) { setFlash('error','BOG კავშირი ვერ მოხდა.'); header('Location: /payment.php'); exit; }

$order  = bogCreateOrder($token, $payment);
if (!isset($order['_links']['redirect']['href'])) {
    setFlash('error','BOG შეკვეთა ვერ შეიქმნა.'); header('Location: /payment.php'); exit;
}

// Save BOG order ID
$db->prepare('UPDATE payments SET provider_order_id=?,updated_at=? WHERE id=?')
   ->execute(array($order['id'], time(), $payment_id));

// Redirect to BOG payment page
header('Location: ' . $order['_links']['redirect']['href']);
exit;
