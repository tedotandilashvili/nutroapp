<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, title, days, target_calories, protein_g, carbs_g, fat_g, created_at
     FROM diet_plans WHERE user_id = ? ORDER BY created_at DESC'
);
$stmt->execute(array($_SESSION['user_id']));
$plans = $stmt->fetchAll();

$goal_labels = array('Weight Loss'=>'წ. დაკლება','Muscle Gain'=>'კუნთი','Maintenance'=>'შენ.');
$goal_colors = array('Weight Loss'=>'#1D9E75','Muscle Gain'=>'#007AFF','Maintenance'=>'#FF9500');

renderHeader('ისტორია', 'history');
?>
<style>
.plan-card {
  display: flex;
  align-items: center;
  gap: 14px;
  background: var(--bg-card);
  border: 0.5px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  margin-bottom: 10px;
  text-decoration: none;
  color: var(--t1);
  transition: opacity .15s, transform .1s;
}
.plan-card:active { opacity: .8; transform: scale(.99); }
.plan-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: var(--green-soft);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; flex-shrink: 0;
}
.plan-name { font-size: 15px; font-weight: 600; color: var(--t1); margin-bottom: 3px; }
.plan-meta { font-size: 12px; color: var(--t3); display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.plan-tag  { background: rgba(0,0,0,.05); border-radius: 6px; padding: 2px 7px; font-size: 11px; font-weight: 500; }
.plan-cal  { font-size: 20px; font-weight: 700; letter-spacing: -.5px; color: var(--t1); text-align: right; }
.plan-days { font-size: 11px; color: var(--t3); text-align: right; margin-top: 1px; }
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
  <div>
    <div class="page-title">ისტორია</div>
    <div class="page-subtitle"><?php echo count($plans); ?> გეგმა</div>
  </div>
  <a href="/generate.php" class="btn btn-primary btn-sm">+ ახალი</a>
</div>

<?php if (empty($plans)): ?>
<div class="card" style="text-align:center;padding:3rem 1rem;">
  <div style="font-size:48px;margin-bottom:1rem;">📋</div>
  <div style="font-size:17px;font-weight:600;margin-bottom:.5rem;">გეგმა ჯერ არ არის</div>
  <div style="font-size:14px;color:var(--t3);margin-bottom:1.5rem;">შექმენი პირველი AI კვების გეგმა</div>
  <a href="/generate.php" class="btn btn-primary">✨ გეგმის შექმნა</a>
</div>
<?php else: ?>
  <?php foreach ($plans as $plan):
    // Guess goal from title
    $title_lower = mb_strtolower($plan['title']);
    if (mb_strpos($title_lower, 'weight loss') !== false || mb_strpos($title_lower, 'წ. დ') !== false) {
        $goal_key = 'Weight Loss';
    } elseif (mb_strpos($title_lower, 'muscle') !== false || mb_strpos($title_lower, 'კუნთ') !== false) {
        $goal_key = 'Muscle Gain';
    } else {
        $goal_key = 'Maintenance';
    }
    $goal_label = isset($goal_labels[$goal_key]) ? $goal_labels[$goal_key] : '';
    $goal_color = isset($goal_colors[$goal_key]) ? $goal_colors[$goal_key] : '#1D9E75';
    $emoji = $goal_key === 'Weight Loss' ? '🥗' : ($goal_key === 'Muscle Gain' ? '💪' : '⚖️');
  ?>
  <a href="/plan.php?id=<?php echo $plan['id']; ?>" class="plan-card">
    <div class="plan-icon" style="background:<?php echo $goal_color; ?>18;"><?php echo $emoji; ?></div>
    <div style="flex:1;min-width:0;">
      <div class="plan-name"><?php echo sanitize($plan['title']); ?></div>
      <div class="plan-meta">
        <span><?php echo date('d/m/Y', $plan['created_at']); ?></span>
        <span style="color:var(--border-mid);">·</span>
        <span><?php echo $plan['days']; ?> დღე</span>
        <?php if ($goal_label): ?>
        <span class="plan-tag" style="color:<?php echo $goal_color; ?>;background:<?php echo $goal_color; ?>15;"><?php echo $goal_label; ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="flex-shrink:0;text-align:right;">
      <div class="plan-cal" style="color:<?php echo $goal_color; ?>"><?php echo number_format($plan['target_calories']); ?></div>
      <div class="plan-days">კკ/დღე</div>
    </div>
  </a>
  <?php endforeach; ?>
<?php endif; ?>

<?php renderFooter(); ?>