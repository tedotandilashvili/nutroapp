<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$user    = getCurrentUser();
$profile = getUserProfile($user['id']);
$db = getDB();

$stmt = $db->prepare(
    'SELECT id, title, days, target_calories, created_at FROM diet_plans
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 3'
);
$stmt->execute(array($user['id']));
$recent_plans = $stmt->fetchAll();

$stmt2 = $db->prepare('SELECT COUNT(*) as total FROM diet_plans WHERE user_id = ?');
$stmt2->execute(array($user['id']));
$plan_count = $stmt2->fetch()['total'];

$goal_labels = array(
    'Weight Loss' => array('label'=>'წონის დაკლება',     'icon'=>'&#9660;', 'color'=>'#D85A30', 'bg'=>'#FAECE7'),
    'Muscle Gain' => array('label'=>'კუნთის მომატება',   'icon'=>'&#9650;', 'color'=>'#0F6E56', 'bg'=>'#E1F5EE'),
    'Maintenance' => array('label'=>'წონის შენარჩუნება', 'icon'=>'&#9670;', 'color'=>'#185FA5', 'bg'=>'#E6F1FB'),
);
$act_labels = array(
    'Sedentary'         => 'უმოქმედო',
    'Lightly Active'    => 'მცირე აქტიური',
    'Moderately Active' => 'ზომიერად აქტიური',
    'Very Active'       => 'ძალიან აქტიური',
);
$bud_labels = array('Low'=>'დაბალი','Medium'=>'საშუალო','High'=>'მაღალი');

$hour = (int)date('H');
if ($hour < 12)      $greeting = 'დილა მშვიდობისა';
elseif ($hour < 18)  $greeting = 'გამარჯობა';
else                 $greeting = 'საღამო მშვიდობისა';

renderHeader('მთავარი', 'dashboard');
?>

<style>
.db-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
.db-stat { background:#fff; border:1px solid #E8E6DF; border-radius:14px; padding:1.25rem 1.5rem; }
.db-stat-val { font-size:32px; font-weight:500; line-height:1; margin-bottom:4px; }
.db-stat-lbl { font-size:12px; color:#888780; letter-spacing:.05em; }
.goal-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:99px; font-size:13px; font-weight:500; }
.plan-card { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; background:#fff; border:1px solid #E8E6DF; border-radius:12px; text-decoration:none; color:inherit; margin-bottom:.5rem; transition:border-color .15s, transform .1s; }
.plan-card:hover { border-color:#1D9E75; transform:translateX(3px); }
.plan-num { font-size:22px; font-weight:500; color:#1D9E75; line-height:1; }
.plan-unit { font-size:11px; color:#888780; }
.hero-card { background:linear-gradient(135deg,#0F6E56 0%,#1D9E75 60%,#5DCAA5 100%); border-radius:16px; padding:1.75rem; color:#fff; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1rem; }
.hero-name { font-size:22px; font-weight:500; margin-bottom:4px; }
.hero-sub { font-size:13px; opacity:.8; }
.hero-btn { background:rgba(255,255,255,.2); border:1.5px solid rgba(255,255,255,.5); color:#fff; padding:9px 20px; border-radius:10px; font-size:14px; font-weight:500; text-decoration:none; transition:background .15s; white-space:nowrap; }
.hero-btn:hover { background:rgba(255,255,255,.35); }
.section-label { font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.1em; color:#888780; margin-bottom:.75rem; display:flex; justify-content:space-between; align-items:center; }
.section-label a { text-transform:none; letter-spacing:0; font-weight:400; color:#1D9E75; text-decoration:none; font-size:13px; }
.profile-strip { background:#F8F7F2; border-radius:12px; padding:1rem 1.25rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.profile-item { font-size:13px; color:#444441; }
.profile-item span { color:#888780; font-size:12px; display:block; margin-bottom:1px; }
.empty-hero { text-align:center; padding:3rem 1rem; background:#F8F7F2; border-radius:16px; margin-bottom:1rem; }
.empty-hero p { font-size:15px; color:#888780; margin-bottom:1.25rem; }
@media(max-width:540px){ .db-grid{grid-template-columns:1fr;} .hero-card{flex-direction:column;align-items:flex-start;} }
</style>

<!-- Hero -->
<div class="hero-card">
  <div>
    <div class="hero-name"><?php echo $greeting ?>, <?php echo sanitize($user['name']); ?></div>
    <div class="hero-sub"><?php echo date('d/m/Y'); ?> &middot; <?php echo $plan_count; ?> კვების გეგმა სულ</div>
  </div>
  <?php if ($profile): ?>
    <a href="/generate.php" class="hero-btn">+ ახალი გეგმა</a>
  <?php else: ?>
    <a href="/profile.php" class="hero-btn">პროფილის შევსება</a>
  <?php endif; ?>
</div>

<?php if (!$profile): ?>
  <div class="empty-hero">
    <div style="font-size:36px;margin-bottom:.75rem;">&#x1F957;</div>
    <p>პროფილი არ არის შევსებული.<br>შეავსე და მიიღე პერსონალური კვების გეგმა.</p>
    <a href="/profile.php" class="btn btn-primary" style="padding:11px 28px;">პროფილის შევსება</a>
  </div>

<?php else:
  $goal = isset($goal_labels[$profile['goal']]) ? $goal_labels[$profile['goal']] : array('label'=>$profile['goal'],'icon'=>'','color'=>'#888780','bg'=>'#F1EFE8');
?>

  <!-- Stats row -->
  <div class="db-grid">
    <div class="db-stat">
      <div class="db-stat-lbl">წონა / სიმაღლე</div>
      <div class="db-stat-val" style="color:#1A1A18;"><?php echo $profile['weight_kg']; ?><span style="font-size:16px;color:#888780;"> კგ</span></div>
      <div style="font-size:13px;color:#888780;margin-top:2px;"><?php echo $profile['height_cm']; ?> სმ &middot; <?php echo $profile['age']; ?> წ.</div>
    </div>
    <div class="db-stat">
      <div class="db-stat-lbl">მიზანი</div>
      <div style="margin-top:6px;">
        <span class="goal-pill" style="background:<?php echo $goal['bg']; ?>;color:<?php echo $goal['color']; ?>;">
          <?php echo $goal['icon']; ?> <?php echo $goal['label']; ?>
        </span>
      </div>
      <div style="font-size:12px;color:#888780;margin-top:8px;">
        <?php echo isset($act_labels[$profile['activity_level']]) ? $act_labels[$profile['activity_level']] : $profile['activity_level']; ?>
        &middot; ბიუჯეტი: <?php echo isset($bud_labels[$profile['budget']]) ? $bud_labels[$profile['budget']] : $profile['budget']; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($recent_plans)): ?>
  <!-- Recent plans -->
  <div style="margin-top:1.5rem;">
    <div class="section-label">
      ბოლო გეგმები
      <a href="/history.php">ყველა &#8594;</a>
    </div>
    <?php foreach ($recent_plans as $plan): ?>
      <a href="/plan.php?id=<?php echo $plan['id']; ?>" class="plan-card">
        <div>
          <div style="font-weight:500;font-size:14px;margin-bottom:3px;"><?php echo sanitize($plan['title']); ?></div>
          <div style="font-size:12px;color:#888780;"><?php echo date('d/m/Y', $plan['created_at']); ?> &middot; <?php echo $plan['days']; ?> დღე</div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="plan-num"><?php echo $plan['target_calories']; ?></div>
          <div class="plan-unit">კკალ / დღე</div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="empty-hero" style="margin-top:1rem;">
    <div style="font-size:36px;margin-bottom:.75rem;">&#x1F4C5;</div>
    <p>ჯერ გეგმა არ გაქვს.</p>
    <a href="/generate.php" class="btn btn-primary" style="padding:11px 28px;">პირველი გეგმის შექმნა</a>
  </div>
  <?php endif; ?>

<?php endif; ?>

<?php renderFooter(); ?>
