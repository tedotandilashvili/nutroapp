<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];

if (isset($_GET['mark_all'])) {
    $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute(array($user_id));
    header('Location: /notifications.php'); exit;
}
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
       ->execute(array((int)$_GET['read'],$user_id));
    header('Location: /notifications.php'); exit;
}

$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$stmt->execute(array($user_id));
$notifs = $stmt->fetchAll();
$unread = 0;
foreach ($notifs as $n) { if (!$n['is_read']) $unread++; }

$icons = array(
    'plan'         => '📋',
    'subscription' => '💳',
    'referral'     => '🎁',
    'water'        => '💧',
    'weight'       => '⚖️',
    'system'       => '🔔',
);

renderHeader('შეტყობინებები', 'notifications');
?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;">
  <div>
    <div class="page-title">🔔 შეტყობინებები</div>
    <?php if ($unread > 0): ?>
      <div class="page-subtitle"><?php echo $unread; ?> წაუკითხავი</div>
    <?php endif; ?>
  </div>
  <?php if ($unread > 0): ?>
    <a href="/notifications.php?mark_all=1" class="btn btn-outline" style="font-size:13px;">ყველას წაკითხულად მონიშვნა</a>
  <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
  <div class="empty-state"><p>შეტყობინებები ცარიელია</p></div>
<?php else: ?>
  <div class="card" style="padding:0;">
    <?php foreach ($notifs as $n):
      $icon = isset($icons[$n['type']]) ? $icons[$n['type']] : '🔔';
    ?>
    <div style="display:flex;gap:12px;padding:14px 1.25rem;border-bottom:1px solid var(--gray-100);background:<?php echo $n['is_read']?'transparent':'#F8FFF9'; ?>;<?php echo $n['is_read']?'':'border-left:3px solid #1D9E75;'; ?>">
      <div style="font-size:20px;flex-shrink:0;margin-top:1px;"><?php echo $icon; ?></div>
      <div style="flex:1;">
        <div style="font-weight:<?php echo $n['is_read']?'400':'500'; ?>;font-size:14px;margin-bottom:2px;">
          <?php echo sanitize($n['title']); ?>
        </div>
        <?php if ($n['message']): ?>
          <div style="font-size:12px;color:var(--gray-400);"><?php echo sanitize($n['message']); ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--gray-400);margin-top:4px;"><?php echo date('d/m/Y H:i',$n['created_at']); ?></div>
      </div>
      <?php if (!$n['is_read']): ?>
        <a href="/notifications.php?read=<?php echo $n['id']; ?>" style="font-size:11px;color:#1D9E75;text-decoration:none;flex-shrink:0;padding-top:2px;">✓</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php renderFooter(); ?>
