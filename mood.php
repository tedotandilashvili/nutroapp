<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/claude.php';
requireLogin();

$db      = getDB();
$db->exec("SET NAMES utf8mb4");
$user_id = (int)$_SESSION['user_id'];
$today   = mktime(0,0,0);

// Save mood
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_mood'])) {
    $mood      = max(1, min(5, (int)$_POST['mood']));
    $energy    = !empty($_POST['energy'])   ? max(1,min(5,(int)$_POST['energy']))   : null;
    $stress    = !empty($_POST['stress'])   ? max(1,min(5,(int)$_POST['stress']))   : null;
    $sleep_hrs = !empty($_POST['sleep_hrs']) ? (float)$_POST['sleep_hrs']            : null;
    $note      = trim($_POST['note'] ?? '');

    $existing = $db->prepare('SELECT id FROM mood_logs WHERE user_id=? AND logged_at>=? LIMIT 1');
    $existing->execute(array($user_id, $today));
    if ($existing->fetch()) {
        $db->prepare('UPDATE mood_logs SET mood=?,energy=?,stress=?,sleep_hrs=?,note=?,logged_at=? WHERE user_id=? AND logged_at>=?')
           ->execute(array($mood,$energy,$stress,$sleep_hrs,$note,time(),$user_id,$today));
    } else {
        $db->prepare('INSERT INTO mood_logs (user_id,mood,energy,stress,sleep_hrs,note,logged_at) VALUES (?,?,?,?,?,?,?)')
           ->execute(array($user_id,$mood,$energy,$stress,$sleep_hrs,$note,time()));
    }
    setFlash('success', 'განწყობა შენახულია!');
    header('Location: /mood.php'); exit;
}

// Today's mood
$today_mood = $db->prepare('SELECT * FROM mood_logs WHERE user_id=? AND logged_at>=? ORDER BY logged_at DESC LIMIT 1');
$today_mood->execute(array($user_id, $today));
$today_mood = $today_mood->fetch();

// Last 14 days
$two_weeks = $today - 13*86400;
$history = $db->prepare('SELECT * FROM mood_logs WHERE user_id=? AND logged_at>=? ORDER BY logged_at ASC');
$history->execute(array($user_id, $two_weeks));
$history = $history->fetchAll();

// Correlation with meals
$has_plans = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE user_id=?');
$has_plans->execute(array($user_id));
$plan_count = (int)$has_plans->fetchColumn();

// AI insight (if 7+ mood logs)
$mood_count = count($history);
$ai_insight = null;
if ($mood_count >= 7 && isset($_GET['insight'])) {
    $mood_summary = array();
    foreach ($history as $m) {
        $mood_summary[] = array(
            'date'  => date('d/m', $m['logged_at']),
            'mood'  => $m['mood'],
            'energy'=> $m['energy'],
            'stress'=> $m['stress'],
            'sleep' => $m['sleep_hrs'],
        );
    }
    $prompt = 'Analyze this mood data and give personalized insights in Georgian (2-3 sentences). Focus on patterns: ' . json_encode($mood_summary) . '. Response: plain text, Georgian only, practical advice about food and lifestyle.';
    $result = callClaudeRaw($prompt, 300);
    if (is_string($result)) $ai_insight = $result;
    elseif (isset($result['content'])) $ai_insight = $result['content'];
    elseif (isset($result['text']))    $ai_insight = $result['text'];
}

$moods = array(
    1 => array('emoji'=>'😔', 'label'=>'ძალიან ცუდად', 'color'=>'#E53935'),
    2 => array('emoji'=>'😐', 'label'=>'ცუდად',       'color'=>'#FF9500'),
    3 => array('emoji'=>'🙂', 'label'=>'ნორმალურად',  'color'=>'#F59E0B'),
    4 => array('emoji'=>'😊', 'label'=>'კარგად',      'color'=>'#16A370'),
    5 => array('emoji'=>'🤩', 'label'=>'შესანიშნავად','color'=>'#7C3AED'),
);

renderHeader('განწყობის ტრეკინგი', 'mood');
?>
<style>
.mood-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;border-radius:var(--r-lg);border:2px solid var(--border-s);background:var(--bg-card);cursor:pointer;transition:all .18s;flex:1;min-width:0;}
.mood-emoji{font-size:28px;line-height:1;}
.mood-label{font-size:9px;font-weight:600;color:var(--t3);text-align:center;white-space:nowrap;}
.mood-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-md);}
.mood-btn.selected{border-width:2px;}

.scale-btn{width:34px;height:34px;border-radius:50%;border:0.5px solid var(--border-s);background:var(--bg-card);cursor:pointer;font-size:13px;font-weight:600;color:var(--t2);transition:all .15s;font-family:inherit;flex-shrink:0;}
.scale-btn.active{background:var(--green);color:#fff;border-color:var(--green);}
.bar-wrap{display:flex;align-items:flex-end;gap:5px;height:80px;}
.bar{flex:1;border-radius:4px 4px 0 0;min-height:4px;transition:height .3s;}
.insight-card{background:linear-gradient(135deg,rgba(124,58,237,.08),rgba(22,163,112,.08));border:0.5px solid rgba(124,58,237,.2);border-radius:var(--r-lg);padding:1rem;}
@media(max-width:400px){
  .mood-label{display:none;}
  .mood-btn{padding:10px 4px;}
  .mood-emoji{font-size:26px;}
  .mood-scales-grid{grid-template-columns:1fr!important;}
  .scale-btn{width:32px;height:32px;font-size:12px;}
}
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;">
  <div>
    <div class="page-title">🧠 განწყობის ტრეკინგი</div>
    <div class="page-subtitle">კვება + განწყობის კორელაცია</div>
  </div>
  <?php if ($mood_count >= 7): ?>
  <a href="/mood.php?insight=1" class="btn btn-outline btn-sm">✨ AI ანალიზი</a>
  <?php endif; ?>
</div>

<?php if ($ai_insight): ?>
<div class="insight-card" style="margin-bottom:1rem;">
  <div style="font-size:12px;font-weight:600;color:#7C3AED;margin-bottom:6px;">✨ AI შენი პატერნი</div>
  <div style="font-size:14px;color:var(--t1);line-height:1.6;"><?php echo htmlspecialchars($ai_insight); ?></div>
</div>
<?php endif; ?>

<!-- Log today's mood -->
<div class="card">
  <div class="card-title">
    <?php echo $today_mood ? 'დღეს უკვე შეიყვანე — განახლება' : 'როგორ გრძნობ თავს დღეს?'; ?>
  </div>
  <form method="POST" id="mood-form">
    <input type="hidden" name="log_mood" value="1">
    <input type="hidden" name="mood" id="mood-val" value="<?php echo $today_mood ? $today_mood['mood'] : ''; ?>">

    <!-- Mood emojis -->
    <div style="display:flex;gap:6px;margin-bottom:1.25rem;overflow:visible;">
      <?php foreach ($moods as $val => $m): ?>
      <button type="button" class="mood-btn <?php echo ($today_mood && $today_mood['mood']==$val)?'selected':''; ?>"
              id="mood-<?php echo $val; ?>"
              style="<?php echo ($today_mood && $today_mood['mood']==$val)?'border-color:'.$m['color'].';background:'.$m['color'].'18;':''?>"
              onclick="selectMood(<?php echo $val; ?>, '<?php echo $m['color']; ?>')">
        <span class="mood-emoji"><?php echo $m['emoji']; ?></span>
        <span class="mood-label"><?php echo $m['label']; ?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Energy, Stress, Sleep -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:1rem;" class="mood-scales-grid">
      <div>
        <label style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">⚡ ენერგია</label>
        <div style="display:flex;gap:4px;" id="energy-btns">
          <?php for($i=1;$i<=5;$i++): ?>
          <button type="button" class="scale-btn <?php echo ($today_mood && $today_mood['energy']==$i)?'active':''; ?>"
                  onclick="selectScale('energy', <?php echo $i; ?>)"><?php echo $i; ?></button>
          <?php endfor; ?>
          <input type="hidden" name="energy" id="energy-val" value="<?php echo $today_mood ? $today_mood['energy'] : ''; ?>">
        </div>
      </div>
      <div>
        <label style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">😤 სტრესი</label>
        <div style="display:flex;gap:4px;" id="stress-btns">
          <?php for($i=1;$i<=5;$i++): ?>
          <button type="button" class="scale-btn <?php echo ($today_mood && $today_mood['stress']==$i)?'active':''; ?>"
                  onclick="selectScale('stress', <?php echo $i; ?>)"><?php echo $i; ?></button>
          <?php endfor; ?>
          <input type="hidden" name="stress" id="stress-val" value="<?php echo $today_mood ? $today_mood['stress'] : ''; ?>">
        </div>
      </div>
      <div>
        <label style="font-size:11px;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">😴 ძილი (სთ)</label>
        <input type="number" name="sleep_hrs" step="0.5" min="0" max="12"
               value="<?php echo $today_mood ? $today_mood['sleep_hrs'] : ''; ?>"
               class="form-control" style="padding:8px 10px;font-size:14px;"
               placeholder="7.5">
      </div>
    </div>

    <div class="form-group" style="margin-bottom:1rem;">
      <label>შენიშვნა (სურვ.)</label>
      <input type="text" name="note" class="form-control"
             value="<?php echo $today_mood ? htmlspecialchars($today_mood['note']) : ''; ?>"
             placeholder="მაგ: დაღლილი ვარ, კარგი დღე იყო...">
    </div>

    <button type="submit" class="btn btn-primary btn-full" id="save-btn" disabled>შენახვა</button>
  </form>
</div>

<!-- 14-day chart -->
<?php if (!empty($history)): ?>
<div class="card">
  <div class="card-title">ბოლო 14 დღე</div>
  <div class="bar-wrap" style="padding:0 4px;">
    <?php
    $days = array();
    for ($i=13; $i>=0; $i--) {
        $day_ts = $today - $i*86400;
        $found  = null;
        foreach ($history as $h) {
            if ($h['logged_at'] >= $day_ts && $h['logged_at'] < $day_ts+86400) {
                $found = $h; break;
            }
        }
        $days[] = array('ts'=>$day_ts, 'log'=>$found);
    }
    foreach ($days as $d):
        $log    = $d['log'];
        $height = $log ? round(($log['mood']/5)*72) : 4;
        $color  = $log ? $moods[$log['mood']]['color'] : 'var(--border-s)';
        $is_today = $d['ts'] == $today;
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;" title="<?php echo date('d/m',$d['ts']); echo $log?': '.$moods[$log['mood']]['label']:''; ?>">
      <?php if ($log): ?><div style="font-size:11px;"><?php echo $moods[$log['mood']]['emoji']; ?></div><?php else: ?><div style="font-size:11px;opacity:0;">·</div><?php endif; ?>
      <div style="width:100%;height:<?php echo $height; ?>px;background:<?php echo $color; ?>;border-radius:4px 4px 0 0;opacity:<?php echo $is_today?1:.7; ?>;"></div>
      <div style="font-size:8px;color:<?php echo $is_today?'var(--green)'  :'var(--t4)'; ?>;font-weight:<?php echo $is_today?'700':'400'; ?>;"><?php echo date('d',$d['ts']); ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Averages -->
  <?php if ($mood_count >= 3):
    $avg_mood   = round(array_sum(array_column($history,'mood')) / $mood_count, 1);
    $sleep_data = array_filter(array_column($history,'sleep_hrs'));
    $avg_sleep  = $sleep_data ? round(array_sum($sleep_data)/count($sleep_data),1) : null;
    $stress_data= array_filter(array_column($history,'stress'));
    $avg_stress = $stress_data ? round(array_sum($stress_data)/count($stress_data),1) : null;
  ?>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:1rem;">
    <div class="stat-card" style="text-align:center;padding:10px;">
      <div class="stat-value" style="font-size:18px;"><?php echo $avg_mood; ?>/5</div>
      <div class="stat-label">საშ. განწყობა</div>
    </div>
    <?php if ($avg_sleep): ?>
    <div class="stat-card" style="text-align:center;padding:10px;">
      <div class="stat-value" style="font-size:18px;"><?php echo $avg_sleep; ?>სთ</div>
      <div class="stat-label">საშ. ძილი</div>
    </div>
    <?php endif; ?>
    <?php if ($avg_stress): ?>
    <div class="stat-card" style="text-align:center;padding:10px;">
      <div class="stat-value" style="font-size:18px;"><?php echo $avg_stress; ?>/5</div>
      <div class="stat-label">საშ. სტრესი</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Mindful eating tips -->
<div class="card" style="background:linear-gradient(135deg,rgba(124,58,237,.06),rgba(22,163,112,.06));border-color:rgba(124,58,237,.15);">
  <div style="font-size:13px;font-weight:600;color:#7C3AED;margin-bottom:.75rem;">🌱 კვების რჩევები</div>
  <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--t2);">
    <div>🍽️ <strong>ნელა ჭამე</strong> — 20 წუთი სჭირდება მუცლის სიგნალს ტვინამდე მისაღწევად</div>
    <div>📵 <strong>ტელეფონი გადადე</strong> — ყურადღებით კვება 30%-ით ამცირებს ზედმეტ ჭამას</div>
    <div>💧 <strong>ჯერ წყალი</strong> — შიმშილი ხშირად წყურვილია</div>
    <div>😤 <strong>სტრეს-ჭამა?</strong> — 5 ღრმა ამოსუნთქვა ჭამამდე ეხმარება ორგანიზმს საჭმლის მიღებაში</div>
    <div>🌙 <strong>ვახშამი 19:00-მდე</strong> — ძილი და განწყობა გაუმჯობესდება</div>
  </div>
</div>

<?php if (!$today_mood): ?>
<div style="text-align:center;margin-top:1rem;font-size:13px;color:var(--t3);">
  ყოველდღიური ჩაწერა გეხმარება პატერნების პოვნაში 🔍<br>
  <strong style="color:var(--green);"><?php echo $mood_count; ?> / 7</strong> — AI ანალიზისთვის საჭირო ჩანაწერი
</div>
<?php endif; ?>

<script>
var selectedMood = <?php echo $today_mood ? $today_mood['mood'] : '0'; ?>;

function selectMood(val, color) {
  selectedMood = val;
  document.getElementById('mood-val').value = val;
  document.querySelectorAll('.mood-btn').forEach(function(b) {
    b.classList.remove('selected');
    b.style.borderColor = '';
    b.style.background  = '';
  });
  var btn = document.getElementById('mood-' + val);
  btn.classList.add('selected');
  btn.style.borderColor = color;
  btn.style.background  = color + '18';
  document.getElementById('save-btn').disabled = false;
}

function selectScale(field, val) {
  document.getElementById(field + '-val').value = val;
  document.querySelectorAll('#' + field + '-btns .scale-btn').forEach(function(b, i) {
    b.classList.toggle('active', i+1 === val);
  });
}

<?php if ($today_mood): ?>
// Already logged today — enable save
document.getElementById('save-btn').disabled = false;
<?php endif; ?>
</script>

<?php renderFooter(); ?>