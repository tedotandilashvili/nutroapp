<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$payment_id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$db = getDB();
$db->prepare('UPDATE payments SET status="failed",updated_at=? WHERE id=? AND user_id=?')
   ->execute(array(time(),$payment_id,(int)$_SESSION['user_id']));
renderHeader('გადახდა ვერ მოხდა','');
?>
<div style="max-width:480px;margin:4rem auto;text-align:center;">
  <div style="font-size:64px;margin-bottom:1rem;">❌</div>
  <h1 style="font-size:24px;font-weight:500;margin-bottom:.5rem;">გადახდა ვერ მოხდა</h1>
  <p style="color:var(--gray-400);margin-bottom:2rem;">სცადეთ თავიდან ან სხვა მეთოდი გამოიყენეთ.</p>
  <a href="/pricing.php" class="btn btn-primary" style="padding:12px 32px;">სცადე თავიდან</a>
</div>
<?php renderFooter(); ?>
