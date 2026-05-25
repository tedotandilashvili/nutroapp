<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$slug    = trim(isset($_GET['plan']) ? $_GET['plan'] : '');
$stmt    = $db->prepare('SELECT * FROM subscription_plans WHERE slug=? AND is_active=1');
$stmt->execute(array($slug));
$plan    = $stmt->fetch();

if (!$plan) { setFlash('error', 'გეგმა ვერ მოიძებნა.'); redirect('/pricing.php'); }

// Redirect to new payment page
redirect('/payment.php?plan=' . urlencode($slug));

$user     = getCurrentUser();
$user_sub = getUserSubscription($user['id']);

// ── Handle confirmation ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {

    // Cancel existing active subscription
    $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE user_id=? AND status="active"')
       ->execute(array($user['id']));

    // Create new subscription — 30 days from now
    // In production: integrate with payment gateway (Stripe, BOG, TBC Pay, etc.)
    $stmt = $db->prepare(
        'INSERT INTO user_subscriptions (user_id, plan_id, status, started_at, expires_at, created_at, notes)
         VALUES (?, ?, "active", ?, ?, ?, ?)'
    );
    $now = time();
    $stmt->execute(array(
        $user['id'], $plan['id'],
        $now, $now + 30*86400, $now,
        'Manual activation — payment pending integration'
    ));

    setFlash('success', '🎉 გამოწერა წარმატებით გააქტიურდა! გეგმა: ' . $plan['name_ka']);
    redirect('/dashboard.php');
}

$features = json_decode($plan['features'], true);

renderHeader('გამოწერა — '.$plan['name_ka'], '');
?>

<style>
.sub-wrap{max-width:480px;margin:0 auto;}
.sub-summary{background:#fff;border:1px solid #E8E6DF;border-radius:16px;padding:1.75rem;margin-bottom:1rem;}
.sub-plan-name{font-size:13px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:#888780;margin-bottom:.25rem;}
.sub-price{font-size:36px;font-weight:500;color:#1A1A18;margin-bottom:.25rem;}
.sub-price span{font-size:15px;font-weight:400;color:#888780;}
.feature-list{list-style:none;padding:0;margin:1rem 0 0;}
.feature-list li{font-size:13px;color:#444441;padding:6px 0;display:flex;gap:8px;border-bottom:1px solid #F8F7F2;}
.feature-list li:last-child{border-bottom:none;}
.feature-list li::before{content:"✓";color:#1D9E75;font-weight:500;flex-shrink:0;}
.notice{background:#FAEEDA;border-radius:10px;padding:12px 16px;font-size:13px;color:#854F0B;margin-bottom:1rem;}
</style>

<div class="sub-wrap">
  <div style="margin-bottom:1.5rem;">
    <a href="/pricing.php" style="font-size:13px;color:#888780;text-decoration:none;">&#8592; გეგმები</a>
  </div>

  <h1 style="font-size:22px;font-weight:500;margin:0 0 1.5rem;">გამოწერის დადასტურება</h1>

  <div class="sub-summary">
    <div class="sub-plan-name"><?php echo sanitize($plan['name']); ?></div>
    <div style="font-size:18px;font-weight:500;color:#1A1A18;margin-bottom:.25rem;"><?php echo sanitize($plan['name_ka']); ?></div>
    <div class="sub-price"><?php echo number_format($plan['price_gel'],2); ?><span> ₾ / თვეში</span></div>

    <?php if ($user_sub): ?>
      <div style="font-size:13px;color:#854F0B;background:#FAEEDA;border-radius:8px;padding:8px 12px;margin-top:.75rem;">
        მიმდინარე გეგმა (<?php echo sanitize($user_sub['name_ka']); ?>) გაუქმდება
      </div>
    <?php endif; ?>

    <ul class="feature-list">
      <?php if (is_array($features)): foreach ($features as $f): ?>
        <li><?php echo sanitize($f); ?></li>
      <?php endforeach; endif; ?>
    </ul>
  </div>

  <div class="notice">
    <strong>შენიშვნა:</strong> ეს არის სატესტო რეჟიმი. გადახდის სისტემა (BOG / TBC Pay) მალე დაემატება.
    გააქტიურება ამჯერად უფასოა.
  </div>

  <form method="POST">
    <button type="submit" name="confirm" class="btn btn-primary btn-full" style="padding:13px;font-size:16px;">
      გამოწერის გააქტიურება — <?php echo number_format($plan['price_gel'],2); ?> ₾
    </button>
  </form>

  <a href="/pricing.php" style="display:block;text-align:center;margin-top:1rem;font-size:13px;color:#888780;text-decoration:none;">
    გაუქმება
  </a>
</div>

<?php renderFooter(); ?>
