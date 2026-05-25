<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];
$user    = getCurrentUser();

// Generate referral code if not exists
$ref_check = $db->prepare('SELECT referral_code FROM users WHERE id=?');
$ref_check->execute(array($user_id));
$ref_row = $ref_check->fetch();
$ref_code = $ref_row['referral_code'];

if (!$ref_code) {
    $ref_code = strtoupper(substr(md5($user_id . $user['email'] . 'nutro'), 0, 8));
    $db->prepare('UPDATE users SET referral_code=? WHERE id=?')->execute(array($ref_code, $user_id));
    $db->prepare('INSERT IGNORE INTO referrals (referrer_id,code,used,reward_given,created_at) VALUES (?,?,0,0,?)')
       ->execute(array($user_id, $ref_code, time()));
}

$ref_url = 'https://nutroapp.ge/register.php?ref=' . $ref_code;

// Stats
$total_refs = $db->prepare('SELECT COUNT(*) FROM users WHERE referred_by=?');
$total_refs->execute(array($user_id));
$total_refs = (int)$total_refs->fetchColumn();

$converted = $db->prepare(
    'SELECT COUNT(*) FROM users u
     JOIN user_subscriptions us ON us.user_id=u.id
     WHERE u.referred_by=? AND us.status="active"'
);
$converted->execute(array($user_id));
$converted = (int)$converted->fetchColumn();

// Reward: 1 free month per converted referral
$rewards_given = $db->prepare('SELECT COUNT(*) FROM referrals WHERE referrer_id=? AND reward_given=1');
$rewards_given->execute(array($user_id));
$rewards_given = (int)$rewards_given->fetchColumn();
$pending_rewards = $converted - $rewards_given;

renderHeader('რეფერალი', 'referral');
?>
<div class="page-header">
  <div class="page-title">👥 მოიყვანე მეგობარი</div>
  <div class="page-subtitle">ყოველი გამოწერილი მეგობრისთვის — 1 თვე უფასო!</div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;">
  <div class="card" style="text-align:center;margin-bottom:0;">
    <div style="font-size:32px;font-weight:500;color:#1D9E75;"><?php echo $total_refs; ?></div>
    <div style="font-size:12px;color:var(--gray-400);">სულ მოყვანილი</div>
  </div>
  <div class="card" style="text-align:center;margin-bottom:0;">
    <div style="font-size:32px;font-weight:500;color:#854F0B;"><?php echo $converted; ?></div>
    <div style="font-size:12px;color:var(--gray-400);">გამოწერილი</div>
  </div>
  <div class="card" style="text-align:center;margin-bottom:0;background:<?php echo $pending_rewards>0?'#E1F5EE':'#fff'; ?>;">
    <div style="font-size:32px;font-weight:500;color:<?php echo $pending_rewards>0?'#0F6E56':'#888780'; ?>;"><?php echo $pending_rewards; ?></div>
    <div style="font-size:12px;color:var(--gray-400);">მომლოდინე ჯილდო</div>
  </div>
</div>

<?php if ($pending_rewards > 0): ?>
<div class="alert alert-success">
  🎉 <?php echo $pending_rewards; ?> უფასო თვე მოლოდინშია! ადმინი მალე გააქტიურებს.
</div>
<?php endif; ?>

<!-- Referral link -->
<div class="card">
  <div class="card-title">შენი რეფერალური ლინკი</div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="text" id="ref-url" value="<?php echo $ref_url; ?>"
           class="form-control" style="flex:1;font-size:13px;" readonly
           onclick="this.select();">
    <button onclick="copyRef()" class="btn btn-primary" style="flex-shrink:0;">კოპირება</button>
  </div>
  <div id="copy-msg" style="font-size:12px;color:#1D9E75;margin-top:6px;display:none;">✓ კოპირებულია!</div>

  <div style="margin-top:1.25rem;background:var(--gray-50);border-radius:10px;padding:1rem;">
    <div style="font-size:13px;font-weight:500;margin-bottom:.5rem;">როგორ მუშაობს:</div>
    <div style="font-size:13px;color:var(--gray-400);display:flex;flex-direction:column;gap:6px;">
      <div>1️⃣ გაუგზავნე ლინკი მეგობარს</div>
      <div>2️⃣ მეგობარი დარეგისტრირდება შენი ლინკით</div>
      <div>3️⃣ მეგობარი ყიდულობს გამოწერას</div>
      <div>4️⃣ შენ იღებ 1 თვე უფასო გამოწერას 🎁</div>
    </div>
  </div>

  <div style="margin-top:1rem;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="https://wa.me/?text=<?php echo urlencode('NutroApp-ზე ვარ — კვების გეგმების AI-სერვისი! სცადე: '.$ref_url); ?>"
       target="_blank" class="btn btn-outline" style="font-size:13px;">📱 WhatsApp</a>
    <a href="https://t.me/share/url?url=<?php echo urlencode($ref_url); ?>&text=<?php echo urlencode('NutroApp — AI კვების გეგმა'); ?>"
       target="_blank" class="btn btn-outline" style="font-size:13px;">✈️ Telegram</a>
  </div>
</div>

<script>
function copyRef() {
  var el = document.getElementById('ref-url');
  el.select();
  try {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(el.value);
    } else {
      document.execCommand('copy');
    }
    document.getElementById('copy-msg').style.display='block';
    setTimeout(function(){ document.getElementById('copy-msg').style.display='none'; },2000);
  } catch(e) {}
}
</script>
<?php renderFooter(); ?>
