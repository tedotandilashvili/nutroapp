<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Stats ──────────────────────────────────────────────────────────────────────
$total_users  = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_plans  = $db->query('SELECT COUNT(*) FROM diet_plans')->fetchColumn();
$today_start  = mktime(0,0,0);
$week_start   = mktime(0,0,0) - 6*86400;
$users_today  = $db->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ?');
$users_today->execute(array($today_start)); $users_today = $users_today->fetchColumn();
$plans_today  = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE created_at >= ?');
$plans_today->execute(array($today_start)); $plans_today = $plans_today->fetchColumn();
$plans_week   = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE created_at >= ?');
$plans_week->execute(array($week_start)); $plans_week = $plans_week->fetchColumn();
$users_week   = $db->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ?');
$users_week->execute(array($week_start)); $users_week = $users_week->fetchColumn();

// Goal distribution
$goals = $db->query(
    'SELECT up.goal, COUNT(*) as cnt FROM user_profiles up GROUP BY up.goal ORDER BY cnt DESC'
)->fetchAll();

// Most active users
$top_users = $db->query(
    'SELECT u.name, u.email, COUNT(d.id) as plan_count, MAX(d.created_at) as last_plan
     FROM users u LEFT JOIN diet_plans d ON d.user_id = u.id
     GROUP BY u.id ORDER BY plan_count DESC LIMIT 5'
)->fetchAll();

// Recent plans
$recent_plans = $db->query(
    'SELECT d.id, d.title, d.target_calories, d.created_at, u.name as user_name
     FROM diet_plans d JOIN users u ON u.id = d.user_id
     ORDER BY d.created_at DESC LIMIT 6'
)->fetchAll();

// Plans per day last 7 days (for mini chart)
$chart_data = array();
for ($i = 6; $i >= 0; $i--) {
    $day_start = mktime(0,0,0) - $i*86400;
    $day_end   = $day_start + 86400;
    $cnt = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE created_at >= ? AND created_at < ?');
    $cnt->execute(array($day_start, $day_end));
    $chart_data[] = array('label'=> date('d/m', $day_start), 'count'=> (int)$cnt->fetchColumn());
}

$goal_labels = array('Weight Loss'=>'წონის დაკლება','Muscle Gain'=>'კუნთის მომატება','Maintenance'=>'შენარჩუნება');

renderAdminHeader('Dashboard', 'dashboard');
?>

<!-- Stats -->
<div class="adm-stat-grid">
  <div class="adm-stat">
    <div class="adm-stat-lbl">სულ მომხმარებელი</div>
    <div class="adm-stat-val"><?php echo $total_users; ?></div>
    <div class="adm-stat-delta up">+<?php echo $users_week; ?> კვირაში</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-lbl">სულ გეგმა</div>
    <div class="adm-stat-val"><?php echo $total_plans; ?></div>
    <div class="adm-stat-delta up">+<?php echo $plans_week; ?> კვირაში</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-lbl">დღეს რეგ.</div>
    <div class="adm-stat-val"><?php echo $users_today; ?></div>
    <div class="adm-stat-delta" style="color:#888780;">ახალი მომხმარებელი</div>
  </div>
  <div class="adm-stat">
    <div class="adm-stat-lbl">დღეს გეგმა</div>
    <div class="adm-stat-val"><?php echo $plans_today; ?></div>
    <div class="adm-stat-delta" style="color:#888780;">გენერირებული</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

  <!-- Mini bar chart -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">გეგმები — ბოლო 7 დღე</span></div>
    <div style="padding:1.25rem 1.5rem;">
      <?php
      $max = 1;
      foreach ($chart_data as $d) { if ($d['count'] > $max) $max = $d['count']; }
      ?>
      <div style="display:flex;align-items:flex-end;gap:6px;height:80px;">
        <?php foreach ($chart_data as $d):
          $h = $max > 0 ? max(4, round(($d['count']/$max)*76)) : 4;
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div style="font-size:11px;color:#888780;font-weight:500;"><?php echo $d['count'] ?: ''; ?></div>
          <div style="width:100%;height:<?php echo $h; ?>px;background:<?php echo $d['count']>0?'#1D9E75':'#E8E6DF'; ?>;border-radius:4px 4px 0 0;"></div>
          <div style="font-size:10px;color:#888780;"><?php echo $d['label']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Goal breakdown -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">მიზნების განაწილება</span></div>
    <div style="padding:1rem 1.5rem;">
      <?php
      $goal_colors = array('Weight Loss'=>array('#D85A30','#FAECE7'),'Muscle Gain'=>array('#0F6E56','#E1F5EE'),'Maintenance'=>array('#185FA5','#E6F1FB'));
      $total_profiles = array_sum(array_column($goals, 'cnt'));
      foreach ($goals as $g):
        $pct  = $total_profiles > 0 ? round($g['cnt']/$total_profiles*100) : 0;
        $lbl  = isset($goal_labels[$g['goal']]) ? $goal_labels[$g['goal']] : $g['goal'];
        $cols = isset($goal_colors[$g['goal']]) ? $goal_colors[$g['goal']] : array('#888780','#F1EFE8');
      ?>
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span style="color:#444441;"><?php echo $lbl; ?></span>
          <span style="color:#888780;"><?php echo $g['cnt']; ?> (<?php echo $pct; ?>%)</span>
        </div>
        <div style="height:6px;background:#F1EFE8;border-radius:99px;overflow:hidden;">
          <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $cols[0]; ?>;border-radius:99px;"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($goals)): ?>
        <div style="font-size:13px;color:#888780;text-align:center;padding:1rem 0;">პროფილი ჯერ არ არის</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

  <!-- Top users -->
  <div class="adm-card">
    <div class="adm-card-head">
      <span class="adm-card-title">აქტიური მომხმარებლები</span>
      <a href="/admin/users.php" class="adm-btn adm-btn-sm adm-btn-outline">ყველა</a>
    </div>
    <table class="adm-table">
      <thead><tr><th>მომხმარებელი</th><th>გეგმები</th><th>ბოლო</th></tr></thead>
      <tbody>
        <?php foreach ($top_users as $u): ?>
        <tr>
          <td>
            <div style="font-weight:500;"><?php echo sanitize($u['name']); ?></div>
            <div style="font-size:11px;color:#888780;"><?php echo sanitize($u['email']); ?></div>
          </td>
          <td><span class="adm-badge badge-green"><?php echo $u['plan_count']; ?></span></td>
          <td style="font-size:12px;color:#888780;">
            <?php echo $u['last_plan'] ? date('d/m/y', $u['last_plan']) : '—'; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_users)): ?>
          <tr><td colspan="3" style="text-align:center;color:#888780;padding:1.5rem;">მომხმარებელი არ არის</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent plans -->
  <div class="adm-card">
    <div class="adm-card-head">
      <span class="adm-card-title">ბოლო გეგმები</span>
      <a href="/admin/plans.php" class="adm-btn adm-btn-sm adm-btn-outline">ყველა</a>
    </div>
    <table class="adm-table">
      <thead><tr><th>გეგმა</th><th>კკალ</th><th>თარიღი</th></tr></thead>
      <tbody>
        <?php foreach ($recent_plans as $p): ?>
        <tr>
          <td>
            <div style="font-size:12px;font-weight:500;"><?php echo sanitize($p['title']); ?></div>
            <div style="font-size:11px;color:#888780;"><?php echo sanitize($p['user_name']); ?></div>
          </td>
          <td style="font-weight:500;color:#1D9E75;"><?php echo $p['target_calories']; ?></td>
          <td style="font-size:12px;color:#888780;"><?php echo date('d/m/y', $p['created_at']); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_plans)): ?>
          <tr><td colspan="3" style="text-align:center;color:#888780;padding:1.5rem;">გეგმა არ არის</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php renderAdminFooter(); ?>
