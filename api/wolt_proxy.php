<?php
// Buffer all output to prevent any accidental HTML leaking
ob_start();

// Load config only — no auth redirect
define('NUTROAPP_ROOT', dirname(dirname(__FILE__)));
require_once NUTROAPP_ROOT . '/config/database.php';

// Check admin session manually
if (session_status() === PHP_SESSION_NONE) session_start();

ob_end_clean(); // discard any output from includes

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    echo json_encode(array('error' => 'unauthorized')); exit;
}

$q   = trim(isset($_GET['q'])   ? $_GET['q']   : '');
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 41.6938;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : 44.8015;

if (!$q) { echo json_encode(array('error' => 'no query')); exit; }

$payload = json_encode(array(
    'q'      => $q,
    'target' => 'items',
    'lat'    => (string)$lat,
    'lon'    => (string)$lon,
));

$ch = curl_init('https://restaurant-api.wolt.com/v1/pages/search');
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => array(
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
        'Origin: https://wolt.com',
        'Referer: https://wolt.com/en/geo/tbilisi',
        'W-Token: ',
    ),
));

$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(array('error' => 'curl: ' . $err)); exit;
}
if ($code !== 200) {
    echo json_encode(array('error' => 'HTTP ' . $code, 'raw' => substr($body, 0, 500))); exit;
}

$data = json_decode($body, true);
if (!$data) {
    echo json_encode(array('error' => 'invalid JSON from Wolt', 'raw' => substr($body, 0, 300))); exit;
}

$items = array();
foreach ((array)($data['sections'] ?? array()) as $sec) {
    foreach ((array)($sec['items'] ?? array()) as $it) {
        $mi = $it['menu_item'] ?? $it['item'] ?? $it;

        // name: string or [{lang,value}]
        $name = $mi['name'] ?? '';
        if (is_array($name)) {
            $picked = '';
            foreach ($name as $n) {
                if (($n['lang'] ?? '') === 'ka') { $picked = $n['value'] ?? ''; break; }
            }
            if (!$picked) foreach ($name as $n) {
                if (($n['lang'] ?? '') === 'en') { $picked = $n['value'] ?? ''; break; }
            }
            if (!$picked) $picked = $name[0]['value'] ?? '';
            $name = $picked;
        }

        $price = round(($mi['baseprice'] ?? $mi['price'] ?? 0) / 100, 2);

        $venue = $mi['venue_name'] ?? '';
        if (!$venue && isset($mi['venue']['name'])) {
            $v = $mi['venue']['name'];
            $venue = is_array($v) ? ($v[0]['value'] ?? '') : $v;
        }

        if ($name && $price > 0) {
            $items[] = array('name' => $name, 'price' => $price, 'venue' => $venue);
        }
    }
}

echo json_encode(array(
    'ok'    => true,
    'count' => count($items),
    'items' => $items,
    'http'  => $code,
), JSON_UNESCAPED_UNICODE);