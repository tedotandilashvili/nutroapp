<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo json_encode(array('error'=>'unauthorized')); exit; }

$db      = getDB();
$db->exec("SET NAMES utf8mb4");
$user_id = (int)$_SESSION['user_id'];

// Check premium subscription
$sub = getUserSubscription($user_id);
$is_premium = $sub && $sub['slug'] === 'high_waltage' && $sub['expires_at'] > time();
if (!$is_premium) {
    echo json_encode(array('error'=>'premium_required')); exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'messages';

// ── Send message ──────────────────────────────────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim(file_get_contents('php://input'));
    $msg = json_decode($msg, true);
    $text = isset($msg['message']) ? trim($msg['message']) : '';

    if (!$text && empty($msg['image'])) {
        echo json_encode(array('error'=>'empty message')); exit;
    }
    if (mb_strlen($text) > 500) {
        echo json_encode(array('error'=>'too long')); exit;
    }

    $text = strip_tags($text);
    $image_url = null;

    // Handle image upload
    if (!empty($msg['image'])) {
        $img_data = base64_decode($msg['image']);
        $img_type = isset($msg['img_type']) ? $msg['img_type'] : 'image/jpeg';
        $ext_map  = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/heic'=>'heic');
        $ext      = isset($ext_map[$img_type]) ? $ext_map[$img_type] : 'jpg';

        // Max 3MB
        if (strlen($img_data) > 3 * 1024 * 1024) {
            echo json_encode(array('error'=>'image too large')); exit;
        }

        $dir   = dirname(__DIR__) . '/uploads/chat/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'c_' . $user_id . '_' . time() . '_' . mt_rand(100,999) . '.' . $ext;
        file_put_contents($dir . $fname, $img_data);
        $image_url = '/uploads/chat/' . $fname;
    }

    // Save to DB
    try { $db->prepare('ALTER TABLE chat_messages ADD COLUMN image_url VARCHAR(255) DEFAULT NULL')->execute(); } catch(Exception $e) {}

    $db->prepare('INSERT INTO chat_messages (user_id, message, image_url, created_at) VALUES (?,?,?,?)')
       ->execute(array($user_id, $text, $image_url, time()));

    // Auto-cleanup: delete messages older than 2 days
    $db->prepare('DELETE FROM chat_messages WHERE created_at < ?')
       ->execute(array(time() - 2 * 86400));

    // Also cleanup old chat images
    $old_imgs = $db->query('SELECT image_url FROM chat_messages WHERE image_url IS NOT NULL')->fetchAll();
    $valid = array_column($old_imgs, 'image_url');
    // (orphan cleanup skipped for simplicity)

    $id   = $db->lastInsertId();
    $user = getCurrentUser();
    echo json_encode(array(
        'ok'      => true,
        'id'      => $id,
        'user'    => $user['name'],
        'message' => $text,
        'image'   => $image_url,
        'time'    => date('H:i'),
        'mine'    => true,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Get messages ──────────────────────────────────────────
if ($action === 'messages') {
    $since  = isset($_GET['since']) ? (int)$_GET['since'] : 0;
    $cutoff = time() - 2 * 86400;

    // since = last message ID (not timestamp)
    $stmt = $db->prepare(
        'SELECT cm.id, cm.user_id, cm.message, cm.image_url, cm.created_at, u.name
         FROM chat_messages cm
         JOIN users u ON u.id = cm.user_id
         WHERE cm.id > ? AND cm.created_at > ?
         ORDER BY cm.id ASC
         LIMIT 100'
    );
    $stmt->execute(array($since, $cutoff));
    $rows = $stmt->fetchAll();

    $messages = array();
    foreach ($rows as $r) {
        $messages[] = array(
            'id'      => (int)$r['id'],
            'user'    => $r['name'],
            'user_id' => (int)$r['user_id'],
            'message' => $r['message'],
            'image'   => $r['image_url'],
            'time'    => date('H:i', $r['created_at']),
            'date'    => date('d/m', $r['created_at']),
            'mine'    => ($r['user_id'] == $user_id),
        );
    }

    // Online users count (active in last 5 min — crude estimate)
    $online = $db->query(
        'SELECT COUNT(DISTINCT user_id) FROM chat_messages WHERE created_at > ' . (time()-300)
    )->fetchColumn();

    echo json_encode(array(
        'messages' => $messages,
        'online'   => (int)$online,
        'server_time' => time(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(array('error'=>'unknown action'));