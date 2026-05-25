<?php
require_once __DIR__ . '/auth_admin.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim($_POST['to']);
    $type    = $_POST['type'];
    $user    = array('name'=>'სატესტო მომხმარებელი','email'=>$to);

    if ($type === 'welcome') {
        $result = sendWelcomeEmail($user);
    } elseif ($type === 'expiry7') {
        $result = sendExpiryReminderEmail($user, 'სტანდარტი', time()+7*86400, 7);
    } elseif ($type === 'expiry3') {
        $result = sendExpiryReminderEmail($user, 'სტანდარტი', time()+3*86400, 3);
    } elseif ($type === 'confirm') {
        $result = sendSubscriptionConfirmEmail($user, 'სტანდარტი', time()+30*86400, 19.99);
    } elseif ($type === 'referral') {
        $result = sendReferralRewardEmail($user, 'გიორგი');
    }
}

renderAdminHeader('ელ.ფოსტის ტესტი', '');
?>
<h1 style="font-size:18px;font-weight:500;margin-bottom:1.5rem;">ელ.ფოსტის ტესტი</h1>

<?php if ($result !== null): ?>
<div class="alert alert-<?php echo $result?'success':'error'; ?>">
  <?php echo $result ? '✅ ელ.ფოსტა გაიგზავნა!' : '❌ გაგზავნა ვერ მოხდა. შეამოწმეთ SMTP პარამეტრები config/database.php-ში.'; ?>
</div>
<?php endif; ?>

<div class="adm-card" style="max-width:500px;">
  <div class="adm-card-head"><span class="adm-card-title">ტესტის გაგზავნა</span></div>
  <div style="padding:1.5rem;">
    <form method="POST">
      <div class="form-group">
        <label>ელ.ფოსტა</label>
        <input type="email" name="to" class="form-control" value="<?php echo sanitize(isset($_POST['to'])?$_POST['to']:''); ?>" required placeholder="test@example.com">
      </div>
      <div class="form-group">
        <label>ტიპი</label>
        <select name="type" class="form-control">
          <option value="welcome">👋 მოგესალმებთ (Welcome)</option>
          <option value="confirm">✅ გამოწერა გადადასტურდა</option>
          <option value="expiry7">⏰ 7 დღე დარჩა</option>
          <option value="expiry3">⏰ 3 დღე დარჩა</option>
          <option value="referral">🎁 რეფერალის ჯილდო</option>
        </select>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary">გაგზავნა</button>
    </form>
  </div>
</div>

<div class="adm-card" style="max-width:500px;margin-top:1rem;">
  <div class="adm-card-head"><span class="adm-card-title">SMTP კონფიგურაცია</span></div>
  <div style="padding:1.25rem;">
    <table style="width:100%;font-size:13px;border-collapse:collapse;">
      <tr style="border-bottom:1px solid #F1EFE8;"><td style="padding:6px 0;color:#888780;">Host</td><td style="font-weight:500;"><?php echo SMTP_HOST; ?></td></tr>
      <tr style="border-bottom:1px solid #F1EFE8;"><td style="padding:6px 0;color:#888780;">Port</td><td style="font-weight:500;"><?php echo SMTP_PORT; ?></td></tr>
      <tr style="border-bottom:1px solid #F1EFE8;"><td style="padding:6px 0;color:#888780;">User</td><td style="font-weight:500;"><?php echo SMTP_USER; ?></td></tr>
      <tr style="border-bottom:1px solid #F1EFE8;"><td style="padding:6px 0;color:#888780;">Secure</td><td style="font-weight:500;"><?php echo SMTP_SECURE; ?></td></tr>
      <tr><td style="padding:6px 0;color:#888780;">From</td><td style="font-weight:500;"><?php echo SMTP_FROM_NAME.' <'.SMTP_FROM.'>'; ?></td></tr>
    </table>
    <p style="font-size:12px;color:#888780;margin-top:.75rem;">პარამეტრების შეცვლა: <code>config/database.php</code></p>
  </div>
</div>
<?php renderAdminFooter(); ?>
