<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];
$today   = mktime(0,0,0);
$profile = getUserProfile($user_id);


// ── Log steps ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['log_steps'])) {
    $steps = max(0, min(100000, (int)$_POST['steps']));
    $db->prepare(
        'INSERT INTO steps_logs (user_id,steps,logged_at) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE steps=VALUES(steps),logged_at=VALUES(logged_at)'
    )->execute(array($user_id, $steps, $today));
    setFlash('success', $steps . ' ნაბიჯი შენახულია!');
    header('Location: /tracker.php'); exit;
}

// ── Save weight log ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['log_weight'])) {
    $w = (float)$_POST['weight_kg'];
    $n = trim(isset($_POST['note']) ? $_POST['note'] : '');
    if ($w > 0) {
        $db->prepare('INSERT INTO weight_logs (user_id,weight_kg,note,logged_at) VALUES (?,?,?,?)')
           ->execute(array($user_id,$w,$n,time()));
        // Update profile weight too
        $db->prepare('UPDATE user_profiles SET weight_kg=? WHERE user_id=?')->execute(array($w,$user_id));
        setFlash('success','წონა შენახულია!');
    }
    header('Location: /tracker.php'); exit;
}

// ── Save water ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['log_water'])) {
    $g = max(1,min(20,(int)$_POST['glasses']));
    // Check if entry exists today
    $ex = $db->prepare('SELECT id,glasses FROM water_logs WHERE user_id=? AND logged_at>=?');
    $ex->execute(array($user_id,$today));
    $existing = $ex->fetch();
    if ($existing) {
        $db->prepare('UPDATE water_logs SET glasses=? WHERE id=?')
           ->execute(array($existing['glasses']+$g, $existing['id']));
    } else {
        $db->prepare('INSERT INTO water_logs (user_id,glasses,logged_at) VALUES (?,?,?)')
           ->execute(array($user_id,$g,time()));
    }
    header('Location: /tracker.php'); exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
// Weight history (last 30 entries)
$wlogs = $db->prepare('SELECT * FROM weight_logs WHERE user_id=? ORDER BY logged_at DESC LIMIT 30');
$wlogs->execute(array($user_id));
$wlogs = $wlogs->fetchAll();

// Today water
$water_today = $db->prepare('SELECT glasses FROM water_logs WHERE user_id=? AND logged_at>=?');
$water_today->execute(array($user_id,$today));
$water_today = $water_today->fetch();
$glasses_today = $water_today ? (int)$water_today['glasses'] : 0;

// BMI calculation
$bmi = null;
$bmi_label = '';
$bmi_color = '#888780';
if ($profile) {
    $bmi = round($profile['weight_kg'] / (($profile['height_cm']/100)**2), 1);
    if ($bmi < 18.5)      { $bmi_label = 'დაბალი';  $bmi_color = '#185FA5'; }
    elseif ($bmi < 25)    { $bmi_label = 'ნორმა';   $bmi_color = '#0F6E56'; }
    elseif ($bmi < 30)    { $bmi_label = 'ჭარბი';   $bmi_color = '#854F0B'; }
    else                  { $bmi_label = 'სიმსუქნე';$bmi_color = '#A32D2D'; }
}

// Progress toward target
$progress_pct = 0;
if ($profile && !empty($profile['target_weight_kg']) && !empty($wlogs)) {
    $start_w = $profile['weight_kg'];
    $target_w = (float)$profile['target_weight_kg'];
    $current_w = (float)$wlogs[0]['weight_kg'];
    $total_loss = $start_w - $target_w;
    if ($total_loss > 0) {
        $progress_pct = min(100, max(0, round(($start_w - $current_w) / $total_loss * 100)));
    }
}

// Chart data (last 14 entries reversed)
$chart_data = array_reverse(array_slice($wlogs, 0, 14));


// Steps data
$steps_today = 0;
try {
    $s = $db->prepare('SELECT steps FROM steps_logs WHERE user_id=? AND logged_at>=? LIMIT 1');
    $s->execute(array($user_id, $today));
    $row = $s->fetch();
    $steps_today = $row ? (int)$row['steps'] : 0;
} catch(Exception $e) { $steps_today = 0; }

// Last 7 days
$steps_week = array();
try {
    for ($i = 6; $i >= 0; $i--) {
        $day_start = mktime(0,0,0,date('n'),date('j')-$i);
        $s = $db->prepare('SELECT steps FROM steps_logs WHERE user_id=? AND logged_at>=? AND logged_at<? LIMIT 1');
        $s->execute(array($user_id, $day_start, $day_start+86400));
        $row = $s->fetch();
        $steps_week[] = array('day'=>$day_start, 'steps'=>$row ? (int)$row['steps'] : 0);
    }
} catch(Exception $e) { $steps_week = array(); }

renderHeader('ტრეკინგი', 'tracker');
?>
<style>
.water-glasses{display:flex;gap:8px;flex-wrap:wrap;margin-top:.75rem;}
.glass{width:36px;height:36px;border-radius:8px;border:2px solid var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer;transition:all .15s;background:#fff;}
.glass.full{background:#E6F1FB;border-color:#185FA5;}
.weight-chart{display:flex;align-items:flex-end;gap:5px;height:80px;margin-top:.75rem;}
.wc-bar{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;}
.wc-val{font-size:9px;color:var(--gray-400);}
.wc-fill{width:100%;border-radius:3px 3px 0 0;}
.wc-lbl{font-size:9px;color:var(--gray-400);}
.progress-bar-wrap{height:8px;background:var(--gray-200);border-radius:99px;overflow:hidden;margin-top:6px;}
.progress-bar-fill{height:100%;background:#1D9E75;border-radius:99px;transition:width .5s;}
</style>

<div class="page-header">
  <div class="page-title">📊 ჯანმრთელობის ტრეკინგი</div>
  <div class="page-subtitle">წონა, BMI, წყალი — ყოველდღიური მონიტორინგი</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

  <!-- BMI Card -->
  <div class="card">
    <div class="card-title">BMI ინდექსი</div>
    <?php if ($profile): ?>
    <div style="text-align:center;padding:.5rem 0;">
      <div style="font-size:48px;font-weight:500;color:<?php echo $bmi_color; ?>;line-height:1;"><?php echo $bmi; ?></div>
      <div style="font-size:14px;font-weight:500;color:<?php echo $bmi_color; ?>;margin-top:4px;"><?php echo $bmi_label; ?></div>
      <div style="font-size:12px;color:var(--gray-400);margin-top:4px;">
        <?php echo $profile['weight_kg']; ?>კგ / <?php echo $profile['height_cm']; ?>სმ
      </div>
    </div>
    <!-- BMI scale -->
    <div style="display:flex;gap:2px;margin-top:.75rem;">
      <?php
      $ranges = array(array('დაბ.','#E6F1FB',18.5),array('ნორმ.','#E1F5EE',25),array('ჭარ.','#FAEEDA',30),array('სიმ.','#FCEBEB',99));
      foreach ($ranges as $r):
        $active = ($bmi !== null && (
            ($r[0]==='დაბ.' && $bmi<18.5) || ($r[0]==='ნორმ.' && $bmi>=18.5&&$bmi<25) ||
            ($r[0]==='ჭარ.' && $bmi>=25&&$bmi<30) || ($r[0]==='სიმ.' && $bmi>=30)));
      ?>
      <div style="flex:1;background:<?php echo $r[1]; ?>;border-radius:4px;padding:4px 2px;text-align:center;font-size:10px;font-weight:<?php echo $active?'700':'400'; ?>;opacity:<?php echo $active?'1':'.6'; ?>;">
        <?php echo $r[0]; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div style="text-align:center;color:var(--gray-400);font-size:13px;padding:1rem;">
        <a href="/profile.php" style="color:#1D9E75;">პროფილი შეავსე</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Water Card -->
  <div class="card">
    <div class="card-title">წყლის ტრეკინგი — დღეს</div>
    <div style="text-align:center;">
      <div style="font-size:36px;font-weight:500;color:#185FA5;line-height:1;"><?php echo $glasses_today; ?></div>
      <div style="font-size:12px;color:var(--gray-400);">/ 8 ჭიქა (2ლ)</div>
      <div class="water-glasses" id="glasses-row">
        <?php for ($i=1; $i<=8; $i++): ?>
          <div class="glass <?php echo $i<=$glasses_today?'full':''; ?>"
               onclick="addWater(<?php echo $i; ?>)">💧</div>
        <?php endfor; ?>
      </div>
      <form method="POST" id="water-form" style="display:none;">
        <input type="hidden" name="log_water" value="1">
        <input type="hidden" name="glasses" id="water-count" value="1">
      </form>
    </div>
    <?php if ($glasses_today >= 8): ?>
      <div style="text-align:center;margin-top:.5rem;font-size:13px;color:#0F6E56;">🎉 მიზანი მიღწეულია!</div>
    <?php endif; ?>
  </div>

</div>

<!-- Weight log form + chart -->
<div class="card" style="margin-bottom:1rem;">
  <div class="card-title" style="display:flex;justify-content:space-between;">
    წონის ისტორია
    <?php if ($profile && !empty($profile['target_weight_kg'])): ?>
      <span style="font-size:12px;color:var(--gray-400);">სამიზნე: <?php echo $profile['target_weight_kg']; ?>კგ</span>
    <?php endif; ?>
  </div>

  <?php if ($profile && !empty($profile['target_weight_kg']) && $progress_pct > 0): ?>
  <div style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gray-400);margin-bottom:4px;">
      <span>პროგრესი სამიზნეამდე</span>
      <span style="color:#1D9E75;font-weight:500;"><?php echo $progress_pct; ?>%</span>
    </div>
    <div class="progress-bar-wrap">
      <div class="progress-bar-fill" style="width:<?php echo $progress_pct; ?>%;"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Mini chart -->
  <?php if (count($chart_data) > 1):
    $min_w = PHP_INT_MAX; $max_w = 0;
    foreach ($chart_data as $wl) {
        if ($wl['weight_kg'] < $min_w) $min_w = $wl['weight_kg'];
        if ($wl['weight_kg'] > $max_w) $max_w = $wl['weight_kg'];
    }
    $range = max(1, $max_w - $min_w);
  ?>
  <div class="weight-chart">
    <?php foreach ($chart_data as $wl):
      $h = max(10, round(($wl['weight_kg'] - $min_w) / $range * 65) + 10);
      $is_latest = ($wl['id'] === $chart_data[count($chart_data)-1]['id']);
    ?>
    <div class="wc-bar">
      <div class="wc-val"><?php echo $is_latest ? $wl['weight_kg'] : ''; ?></div>
      <div class="wc-fill" style="height:<?php echo $h; ?>px;background:<?php echo $is_latest?'#1D9E75':'#D3D1C7'; ?>;"></div>
      <div class="wc-lbl"><?php echo date('d/m',$wl['logged_at']); ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Log form -->
  <form method="POST" style="display:flex;gap:8px;align-items:flex-end;margin-top:1rem;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:120px;">
      <label>ახლანდელი წონა (კგ)</label>
      <input type="number" step="0.1" name="weight_kg" class="form-control"
             placeholder="<?php echo $profile ? $profile['weight_kg'] : '70.0'; ?>" required>
    </div>
    <div class="form-group" style="margin:0;flex:2;min-width:150px;">
      <label>შენიშვნა (სურ.)</label>
      <input type="text" name="note" class="form-control" placeholder="დილით, ჭამის წინ...">
    </div>
    <button type="submit" name="log_weight" class="btn btn-primary" style="flex-shrink:0;">შენახვა</button>
  </form>
</div>

<!-- Weight history table -->
<?php if (!empty($wlogs)): ?>
<div class="card">
  <div class="card-title">ბოლო ჩანაწერები</div>
  <?php
  $prev_w = null;
  foreach (array_slice($wlogs,0,10) as $wl):
    $diff = $prev_w !== null ? round($wl['weight_kg'] - $prev_w, 1) : null;
    $prev_w = $wl['weight_kg'];
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gray-100);">
    <div>
      <span style="font-weight:500;"><?php echo $wl['weight_kg']; ?> კგ</span>
      <?php if ($diff !== null): ?>
        <span style="font-size:12px;color:<?php echo $diff<0?'#0F6E56':($diff>0?'#A32D2D':'#888780'); ?>;margin-left:6px;">
          <?php echo $diff>0?'+':''; ?><?php echo $diff; ?>კგ
        </span>
      <?php endif; ?>
      <?php if ($wl['note']): ?>
        <span style="font-size:12px;color:var(--gray-400);margin-left:6px;"><?php echo sanitize($wl['note']); ?></span>
      <?php endif; ?>
    </div>
    <span style="font-size:12px;color:var(--gray-400);"><?php echo date('d/m/Y H:i',$wl['logged_at']); ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function addWater(n) {
  var current = <?php echo $glasses_today; ?>;
  var add = n > current ? n - current : 1;
  document.getElementById('water-count').value = add;
  document.getElementById('water-form').submit();
}
</script>


<!-- ══ STEPS CALCULATOR ══ -->
<div class="page-header" style="margin-top:1.5rem;">
  <div class="page-title" style="font-size:22px;">👟 ნაბიჯების ტრეკინგი</div>
</div>

<!-- Log steps form -->
<div class="card">
  <div class="card-title">დღის ნაბიჯები</div>
  <form method="POST" id="steps-form">
    <input type="hidden" name="log_steps" value="1">
    <div style="display:flex;gap:10px;align-items:flex-end;">
      <div class="form-group" style="margin:0;flex:1;">
        <label>ნაბიჯების რაოდენობა</label>
        <input type="number" name="steps" id="steps-input" class="form-control"
               min="0" max="100000" placeholder="10000"
               value="<?php echo $steps_today; ?>">
      </div>
      <div class="form-group" style="margin:0;width:140px;">
        <label>სიმაღლე (სმ)</label>
        <input type="number" name="height_cm" id="height-input" class="form-control"
               value="<?php echo $profile ? (int)$profile['height_cm'] : 170; ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="margin-bottom:0;">შენახვა</button>
    </div>
  </form>
</div>

<!-- Steps stats cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));" id="steps-stats">
  <div class="stat-card">
    <div class="stat-value" id="stat-steps" style="color:var(--green);"><?php echo number_format($steps_today); ?></div>
    <div class="stat-label">ნაბიჯი</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" id="stat-km">0</div>
    <div class="stat-label">კილომეტრი</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" id="stat-cal">0</div>
    <div class="stat-label">კალორია</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" id="stat-time">0</div>
    <div class="stat-label">წუთი</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" id="stat-goal">0%</div>
    <div class="stat-label">სამიზნე 10k</div>
  </div>
</div>

<!-- Goal progress bar -->
<div class="card" style="padding:.875rem 1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
    <span style="font-size:13px;font-weight:500;">10,000 ნაბიჯის სამიზნე</span>
    <span style="font-size:12px;color:var(--t3);" id="goal-label"><?php echo $steps_today; ?> / 10,000</span>
  </div>
  <div style="height:8px;background:var(--green-soft);border-radius:99px;overflow:hidden;">
    <div id="goal-bar" style="height:100%;background:linear-gradient(90deg,var(--green),var(--green-2));border-radius:99px;transition:width .4s;width:0%;"></div>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:var(--t3);">
    <span>0</span><span>2,500</span><span>5,000</span><span>7,500</span><span>10,000+</span>
  </div>
</div>

<!-- Quick add buttons -->
<div class="card">
  <div class="card-title">სწრაფი დამატება</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px;">
    <?php
    $activities = array(
      array('🚶','სიარული',5,30),
      array('🏃','სირბილი',10,20),
      array('🏔️','ლაშქრობა',6,60),
      array('🛒','შოპინგი',3,45),
      array('🏢','სამსახური',4,60),
    );
    foreach ($activities as $a): ?>
    <button onclick="addActivitySteps(<?php echo $a[2]; ?>,<?php echo $a[3]; ?>)"
            class="btn btn-outline btn-sm" style="flex-direction:column;height:auto;padding:10px 6px;gap:3px;">
      <span style="font-size:18px;"><?php echo $a[0]; ?></span>
      <span style="font-size:11px;font-weight:500;"><?php echo $a[1]; ?></span>
      <span style="font-size:10px;color:var(--t3);"><?php echo $a[2]; ?>კ/სთ · <?php echo $a[3]; ?>წთ</span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Weekly steps chart -->
<?php if (!empty($steps_week)): ?>
<div class="card">
  <div class="card-title">კვირის ნაბიჯები</div>
  <div style="display:flex;align-items:flex-end;gap:6px;height:80px;">
    <?php
    $max_steps = max(array_column($steps_week, 'steps') + array(1));
    $days_ka   = array('ორშ','სამ','ოთხ','ხუთ','პარ','შაბ','კვი');
    foreach ($steps_week as $i => $sw):
      $h = max(4, round(($sw['steps'] / $max_steps) * 72));
      $is_today = date('Y-m-d', $sw['day']) === date('Y-m-d');
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
      <div style="font-size:9px;color:var(--t3);"><?php echo $sw['steps'] > 0 ? number_format($sw['steps']) : ''; ?></div>
      <div style="width:100%;height:<?php echo $h; ?>px;background:<?php echo $is_today ? 'var(--green)' : 'var(--green-soft)'; ?>;border-radius:4px 4px 0 0;"></div>
      <div style="font-size:9px;color:<?php echo $is_today ? 'var(--green)' : 'var(--t3)'; ?>;font-weight:<?php echo $is_today ? '600' : '400'; ?>;">
        <?php echo $days_ka[$i] ?? ''; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
var _steps  = <?php echo (int)$steps_today; ?>;
var _height = <?php echo $profile ? (int)$profile['height_cm'] : 170; ?>;
var _weight = <?php echo $profile ? (float)$profile['weight_kg'] : 70; ?>;

function calcSteps(steps, height_cm, weight_kg) {
  var stride = height_cm * 0.415 / 100; // stride length in meters
  var km     = (steps * stride) / 1000;
  var met    = 3.5; // walking MET
  var hours  = (km / 5); // avg 5km/h
  var cal    = met * weight_kg * hours;
  var mins   = Math.round(hours * 60);
  var pct    = Math.min(100, Math.round(steps / 100));
  return {
    km:   km.toFixed(2),
    cal:  Math.round(cal),
    mins: mins,
    pct:  pct
  };
}

function updateStats(steps) {
  var h  = parseInt(document.getElementById('height-input').value) || _height;
  var r  = calcSteps(steps, h, _weight);
  document.getElementById('stat-steps').textContent = steps.toLocaleString();
  document.getElementById('stat-km').textContent    = r.km;
  document.getElementById('stat-cal').textContent   = r.cal;
  document.getElementById('stat-time').textContent  = r.mins;
  document.getElementById('stat-goal').textContent  = r.pct + '%';
  document.getElementById('goal-bar').style.width   = r.pct + '%';
  document.getElementById('goal-label').textContent = steps.toLocaleString() + ' / 10,000';
  // bar color
  var bar = document.getElementById('goal-bar');
  if (steps >= 10000) bar.style.background = 'linear-gradient(90deg,#16A370,#0D8059)';
  else if (steps >= 7500) bar.style.background = 'linear-gradient(90deg,#F59E0B,#D97706)';
  else bar.style.background = 'linear-gradient(90deg,var(--green),var(--green-2))';
}

// Live update as user types
document.getElementById('steps-input').addEventListener('input', function() {
  updateStats(parseInt(this.value) || 0);
});
document.getElementById('height-input').addEventListener('input', function() {
  updateStats(parseInt(document.getElementById('steps-input').value) || 0);
});

// Add activity steps
function addActivitySteps(kph, minutes) {
  var stride    = _height * 0.415 / 100;
  var km        = kph * (minutes / 60);
  var newSteps  = Math.round(km * 1000 / stride);
  var inp       = document.getElementById('steps-input');
  var current   = parseInt(inp.value) || 0;
  inp.value     = current + newSteps;
  updateStats(current + newSteps);
}

// Init
updateStats(_steps);
</script>

<?php renderFooter(); ?>