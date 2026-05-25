<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$payment_id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$db = getDB();
$stmt = $db->prepare('SELECT p.*,sp.name_ka,sp.price_gel FROM payments p JOIN subscription_plans sp ON sp.id=p.plan_id WHERE p.id=? AND p.user_id=?');
$stmt->execute(array($payment_id,(int)$_SESSION['user_id']));
$payment = $stmt->fetch();

renderHeader('გადახდა წარმატებული', '');
?>
<div style="max-width:480px;margin:4rem auto;text-align:center;">
  <div style="font-size:64px;margin-bottom:1rem;">✅</div>
  <h1 style="font-size:24px;font-weight:500;margin-bottom:.5rem;">გადახდა წარმატებული!</h1>
  <?php if ($payment): ?>
    <p style="color:var(--gray-400);margin-bottom:2rem;">
      <?php echo sanitize($payment['name_ka']); ?> — <?php echo number_format($payment['price_gel'],2); ?> ₾
    </p>
  <?php endif; ?>
  <a href="/dashboard.php" class="btn btn-primary" style="padding:12px 32px;">მთავარ გვერდზე</a>
</div>
<?php renderFooter(); ?>
