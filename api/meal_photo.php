<?php
/**
 * Meal Photo API — fetches food photo from Pexels with DB cache
 * GET /api/meal_photo.php?meal=ქათმის სალათი&en=chicken salad
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }

// Pexels API key — add to config/database.php:
// define('PEXELS_API_KEY', 'your_key_here');
$api_key = defined('PEXELS_API_KEY') ? PEXELS_API_KEY : '';
if (!$api_key) {
    echo json_encode(array('error'=>'no_api_key','msg'=>'Add PEXELS_API_KEY to config')); exit;
}

$meal_ka = trim(isset($_GET['meal']) ? $_GET['meal'] : '');
$meal_en = trim(isset($_GET['en'])   ? $_GET['en']   : '');

// Build search query — English works better on Pexels
$query = $meal_en ?: transliterateKa($meal_ka);
$query = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $query));
$query = trim($query) ?: 'healthy food';

// Check cache
$db = getDB();
$db->exec("SET NAMES utf8mb4");
$cache = $db->prepare('SELECT * FROM meal_photos WHERE query=? LIMIT 1');
$cache->execute(array($query));
$cached = $cache->fetch();

if ($cached) {
    echo json_encode(array(
        'ok'    => true,
        'url'   => $cached['photo_url'],
        'thumb' => $cached['photo_thumb'],
        'query' => $query,
        'cache' => true,
    ));
    exit;
}

// Fetch from Pexels
$search_url = 'https://api.pexels.com/v1/search?query=' . urlencode($query . ' food dish') . '&per_page=3&orientation=landscape';
$ch = curl_init($search_url);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => array('Authorization: ' . $api_key),
    CURLOPT_SSL_VERIFYPEER => false,
));
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || !$body) {
    echo json_encode(array('error'=>'pexels_error','code'=>$code)); exit;
}

$data = json_decode($body, true);
if (empty($data['photos'])) {
    // Try simpler query
    $simple = explode(' ', $query);
    $query2 = $simple[0] . ' food';
    $search_url2 = 'https://api.pexels.com/v1/search?query=' . urlencode($query2) . '&per_page=1';
    $ch2 = curl_init($search_url2);
    curl_setopt_array($ch2, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => array('Authorization: ' . $api_key),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    $body2 = curl_exec($ch2);
    curl_close($ch2);
    $data2 = json_decode($body2, true);
    if (!empty($data2['photos'])) $data = $data2;
}

if (empty($data['photos'])) {
    echo json_encode(array('error'=>'no_photos','query'=>$query)); exit;
}

// Pick best photo — prefer landscape food photos
$photo  = $data['photos'][0];
$url    = $photo['src']['medium']  ?? $photo['src']['original'];
$thumb  = $photo['src']['small']   ?? $url;

// Save to cache
$db->prepare(
    'INSERT INTO meal_photos (query,photo_url,photo_thumb,source,created_at) VALUES (?,?,?,?,?)
     ON DUPLICATE KEY UPDATE photo_url=VALUES(photo_url),photo_thumb=VALUES(photo_thumb)'
)->execute(array($query, $url, $thumb, 'pexels', time()));

echo json_encode(array(
    'ok'    => true,
    'url'   => $url,
    'thumb' => $thumb,
    'query' => $query,
    'cache' => false,
), JSON_UNESCAPED_UNICODE);

// ── Transliteration helper ────────────────────────────────────────────────────
function transliterateKa($text) {
    $map = array(
        'ა'=>'a','ბ'=>'b','გ'=>'g','დ'=>'d','ე'=>'e','ვ'=>'v','ზ'=>'z',
        'თ'=>'t','ი'=>'i','კ'=>'k','ლ'=>'l','მ'=>'m','ნ'=>'n','ო'=>'o',
        'პ'=>'p','ჟ'=>'zh','რ'=>'r','ს'=>'s','ტ'=>'t','უ'=>'u','ფ'=>'f',
        'ქ'=>'k','ღ'=>'gh','ყ'=>'q','შ'=>'sh','ჩ'=>'ch','ც'=>'ts','ძ'=>'dz',
        'წ'=>'ts','ჭ'=>'ch','ხ'=>'kh','ჯ'=>'j','ჰ'=>'h',
    );
    return strtr($text, $map);
}