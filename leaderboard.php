<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$db->exec("SET NAMES utf8mb4");
$user_id = (int)$_SESSION['user_id'];

// This week
$week_start = mktime(0,0,0,date('n'),date('j')-date('N')+1);

// Weight loss leaderboard — compare first vs latest weight log this week
$leaders = $db->prepare(
    'SELECT u.id, u.name,
            (SELECT weight_kg FROM weight_logs WHERE user_id=u.id ORDER BY logged_at ASC  LIMIT 1) as start_w,
            (SELECT weight_kg FROM weight_logs WHERE user_id=u.id ORDER BY logged_at DESC LIMIT 1) as latest_w,
            (SELECT COUNT(*) FROM diet_plans WHERE user_id=u.id AND created_at>=?) as plans_week,
            (SELECT COUNT(*) FROM diet_plans WHERE user_id=u.id) as plans_total
     FROM users u
     WHERE EXISTS (SELECT 1 FROM weight_logs wl WHERE wl.user_id=u.id)
     ORDER BY (
         (SELECT weight_kg FROM weight_logs WHERE user_id=u.id ORDER BY logged_at ASC LIMIT 1) -
         (SELECT weight_kg FROM weight_logs WHERE user_id=u.id ORDER BY logged_at DESC LIMIT 1)
     ) DESC
     LIMIT 20'
);
$leaders->execute(array($week_start));
$leaders = $leaders->fetchAll();

// Most active this week (plans generated)
$active = $db->prepare(
    'SELECT u.id, u.name, COUNT(d.id) as plan_count
     FROM diet_plans d JOIN users u ON u.id=d.user_id
     WHERE d.created_at>=?
     GROUP BY u.id ORDER BY plan_count DESC LIMIT 10'
);
$active->execute(array($week_start));
$active = $active->fetchAll();

// Latest progress photo per user
$photos_stmt = $db->query(
    'SELECT user_id, filename, weight_kg, created_at
     FROM progress_photos p1
     WHERE created_at = (SELECT MAX(created_at) FROM progress_photos p2 WHERE p2.user_id=p1.user_id)
     GROUP BY user_id'
);
$user_photos = array();
foreach ($photos_stmt->fetchAll() as $ph) {
    $user_photos[$ph['user_id']] = $ph;
}

// Current user rank
$my_rank = 0;
foreach ($leaders as $i => $l) {
    if ($l['id'] == $user_id) { $my_rank = $i+1; break; }
}

renderHeader('Leaderboard', 'leaderboard');
?>
<style>
.lb-row{display:flex;align-items:center;gap:12px;padding:12px 1rem;border-bottom:1px solid var(--gray-100);}
.lb-row:last-child{border-bottom:none;}
.lb-rank{font-size:18px;font-weight:500;min-width:32px;text-align:center;}
.lb-rank.gold{color:#F59E0B;}
.lb-rank.silver{color:#9CA3AF;}
.lb-rank.bronze{color:#B45309;}
.lb-avatar{width:36px;height:36px;border-radius:50%;background:#1D9E75;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:500;font-size:15px;flex-shrink:0;}
.lb-me .lb-avatar{background:#185FA5;}
.lb-name{flex:1;font-size:14px;font-weight:500;}
.lb-val{font-size:14px;font-weight:500;color:#1D9E75;text-align:right;}
.lb-sub{font-size:11px;color:var(--gray-400);}
.tab-btn{padding:8px 16px;border-radius:99px;border:1px solid var(--gray-200);background:#fff;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;}
.tab-btn.active{background:#1D9E75;color:#fff;border-color:#1D9E75;}
</style>

<div class="page-header">
  <div class="page-title">🏆 Leaderboard</div>
  <div class="page-subtitle">ამ კვირის საუკეთესო შედეგები</div>
</div>

<?php if ($my_rank > 0): ?>
<div style="background:#E1F5EE;border-radius:10px;padding:10px 16px;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
  <span style="font-size:13px;color:#0F6E56;">შენი პოზიცია</span>
  <span style="font-size:18px;font-weight:500;color:#0F6E56;">#<?php echo $my_rank; ?></span>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:1rem;">
  <button class="tab-btn active" onclick="showTab('weight',this)">⚖️ წონის დაკლება</button>
  <button class="tab-btn" onclick="showTab('active',this)">📋 აქტიური</button>
  <button class="tab-btn" onclick="showTab('photos',this)">📸 ფოტოები</button>
</div>

<!-- Weight loss tab -->
<div id="tab-weight" class="card" style="padding:0;">
  <?php if (empty($leaders)): ?>
    <div style="text-align:center;padding:2rem;color:var(--gray-400);font-size:14px;">
      ჯერ არავის დაუფიქსირებია წონა. <a href="/tracker.php" style="color:#1D9E75;">ჩაწერე შენი წონა!</a>
    </div>
  <?php else: ?>
    <?php foreach ($leaders as $i => $l):
      $rank = $i + 1;
      $loss = $l['start_w'] && $l['latest_w'] ? round($l['start_w'] - $l['latest_w'], 1) : 0;
      $is_me = $l['id'] == $user_id;
      $rank_class = $rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':''));
    ?>
    <div class="lb-row <?php echo $is_me?'lb-me':''; ?>" style="<?php echo $is_me?'background:#F0FFF8;':''; ?>">
      <div class="lb-rank <?php echo $rank_class; ?>">
        <?php echo $rank<=3 ? array(1=>'🥇',2=>'🥈',3=>'🥉')[$rank] : '#'.$rank; ?>
      </div>
      <div class="lb-avatar"><?php echo mb_strtoupper(mb_substr($l['name'],0,1,'UTF-8'),'UTF-8'); ?></div>
      <div style="flex:1;">
        <div class="lb-name"><?php echo $is_me ? sanitize($l['name']).' (შენ)' : sanitize($l['name']); ?></div>
        <div class="lb-sub"><?php echo $l['plans_total']; ?> გეგმა სულ</div>
      </div>
      <div style="text-align:right;">
        <?php if ($loss > 0): ?>
          <div class="lb-val">-<?php echo $loss; ?> კგ</div>
        <?php elseif ($loss < 0): ?>
          <div class="lb-val" style="color:#854F0B;">+<?php echo abs($loss); ?> კგ</div>
        <?php else: ?>
          <div class="lb-val" style="color:var(--gray-400);">—</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Photos tab -->
<div id="tab-photos" style="display:none;">
  <?php
  $has_photos = false;
  foreach ($leaders as $l) {
      if (isset($user_photos[$l['id']])) { $has_photos = true; break; }
  }
  ?>
  <?php if (!$has_photos): ?>
    <div class="card" style="text-align:center;padding:2rem;color:var(--gray-400);">
      ჯერ არავის ატვირთული progress ფოტო. <a href="/progress.php" style="color:#1D9E75;">ატვირთე შენი!</a>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
    <?php foreach ($leaders as $l):
      if (!isset($user_photos[$l['id']])) continue;
      $ph    = $user_photos[$l['id']];
      $is_me = $l['id'] == $user_id;
      $loss  = $l['start_w'] && $l['latest_w'] ? round($l['start_w'] - $l['latest_w'], 1) : null;
    ?>
    <div style="background:#fff;border:<?php echo $is_me?'2px solid #1D9E75':'1px solid var(--gray-200)'; ?>;border-radius:12px;overflow:hidden;">
      <div style="position:relative;">
        <img src="/uploads/progress/<?php echo sanitize($ph['filename']); ?>"
             style="width:100%;aspect-ratio:3/4;object-fit:cover;display:block;" loading="lazy">
        <?php if ($is_me): ?>
          <div style="position:absolute;top:6px;right:6px;background:#1D9E75;color:#fff;font-size:10px;font-weight:500;padding:2px 6px;border-radius:99px;">შენ</div>
        <?php endif; ?>
      </div>
      <div style="padding:8px 10px;">
        <div style="font-size:13px;font-weight:500;margin-bottom:2px;"><?php echo sanitize($l['name']); ?></div>
        <?php if ($ph['weight_kg']): ?>
          <div style="font-size:12px;color:#1D9E75;font-weight:500;"><?php echo $ph['weight_kg']; ?> კგ</div>
        <?php endif; ?>
        <?php if ($loss !== null && $loss > 0): ?>
          <div style="font-size:11px;color:#0F6E56;">-<?php echo $loss; ?> კგ</div>
        <?php endif; ?>
        <div style="font-size:10px;color:var(--gray-400);margin-top:2px;"><?php echo date('d/m/Y', $ph['created_at']); ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div style="text-align:center;margin-top:1rem;">
    <a href="/progress.php" class="btn btn-outline" style="font-size:13px;padding:8px 20px;">+ ჩემი ფოტოს დამატება</a>
  </div>
</div>

<!-- Active tab -->
<div id="tab-active" class="card" style="padding:0;display:none;">
  <?php if (empty($active)): ?>
    <div style="text-align:center;padding:2rem;color:var(--gray-400);">ამ კვირაში ჯერ გეგმა არ შექმნილა.</div>
  <?php else: ?>
    <?php foreach ($active as $i => $a):
      $rank = $i+1;
      $is_me = $a['id'] == $user_id;
      $rank_class = $rank===1?'gold':($rank===2?'silver':($rank===3?'bronze':''));
    ?>
    <div class="lb-row <?php echo $is_me?'lb-me':''; ?>" style="<?php echo $is_me?'background:#F0FFF8;':''; ?>">
      <div class="lb-rank <?php echo $rank_class; ?>"><?php echo $rank<=3 ? array(1=>'🥇',2=>'🥈',3=>'🥉')[$rank] : '#'.$rank; ?></div>
      <div class="lb-avatar"><?php echo mb_strtoupper(mb_substr($a['name'],0,1,'UTF-8'),'UTF-8'); ?></div>
      <div style="flex:1;">
        <div class="lb-name"><?php echo $is_me ? sanitize($a['name']).' (შენ)' : sanitize($a['name']); ?></div>
      </div>
      <div class="lb-val"><?php echo $a['plan_count']; ?> გეგმა</div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div style="background:#F8F7F2;border-radius:10px;padding:12px 16px;margin-top:1rem;font-size:12px;color:var(--gray-400);text-align:center;">
  განახლდება ყოველ კვირაში. წონის ჩასაწერად გამოიყენე <a href="/tracker.php" style="color:#1D9E75;">ტრეკინგი</a>.
</div>

<script>
function showTab(name, btn) {
  document.getElementById('tab-weight').style.display = name==='weight' ? 'block' : 'none';
  document.getElementById('tab-active').style.display = name==='active' ? 'block' : 'none';
  document.getElementById('tab-photos').style.display = name==='photos' ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
}
</script>
<?php renderFooter(); ?>