<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/claude.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$user    = getCurrentUser();
$profile = getUserProfile($user['id']);

if (!$profile) {
    setFlash('info', 'გთხოვთ ჯერ შეავსოთ თქვენი პროფილი.');
    redirect('/profile.php');
}

$check    = canGeneratePlan($user['id']);
$sub      = isset($check['sub']) ? $check['sub'] : null;
$max_days = $sub ? (int)$sub['max_days'] : 3;

$goal_labels = array('Weight Loss'=>'წონის დაკლება','Muscle Gain'=>'კუნთის მომატება','Maintenance'=>'წონის შენარჩუნება');
$act_labels  = array('Sedentary'=>'უმოქმედო','Lightly Active'=>'მცირე აქტიური','Moderately Active'=>'ზომიერად აქტიური','Very Active'=>'ძალიან აქტიური');
$bud_labels  = array('Low'=>'დაბალი','Medium'=>'საშუალო','High'=>'მაღალი');

renderHeader('ახალი გეგმა', 'generate');
?>

<?php if (!$check['allowed']): ?>
  <div class="page-header"><div class="page-title">კვების გეგმის გენერაცია</div></div>
  <?php if ($check['reason'] === 'no_subscription'): ?>
    <div style="text-align:center;background:#fff;border:1px solid #E8E6DF;border-radius:16px;padding:3rem 2rem;max-width:480px;margin:0 auto;">
      <div style="font-size:40px;margin-bottom:1rem;">🔒</div>
      <h2 style="font-size:20px;font-weight:500;margin:0 0 .5rem;">გამოწერა საჭიროა</h2>
      <p style="font-size:14px;color:#888780;margin:0 0 1.5rem;">კვების გეგმის გენერაციისთვის გთხოვთ აირჩიოთ გამოწერის გეგმა.</p>
      <a href="/pricing.php" class="btn btn-primary" style="padding:12px 32px;">გეგმების ნახვა</a>
    </div>
  <?php else: ?>
    <div style="text-align:center;background:#fff;border:1px solid #E8E6DF;border-radius:16px;padding:3rem 2rem;max-width:480px;margin:0 auto;">
      <div style="font-size:40px;margin-bottom:1rem;">📊</div>
      <h2 style="font-size:20px;font-weight:500;margin:0 0 .5rem;">თვის ლიმიტი ამოიწურა</h2>
      <p style="font-size:14px;color:#888780;margin:0 0 1.5rem;"><?php echo $check['count']; ?> / <?php echo $check['max']; ?> გეგმა ამ თვეში.</p>
      <a href="/pricing.php" class="btn btn-primary" style="padding:12px 32px;">გეგმის განახლება</a>
    </div>
  <?php endif; ?>

<?php else: ?>

<div class="page-header">
  <div class="page-title">კვების გეგმის გენერაცია</div>
  <div class="page-subtitle">AI შექმნის თქვენთვის პერსონალურ გეგმას</div>
</div>

<?php if ($sub): ?>
<div style="display:flex;align-items:center;justify-content:space-between;background:#E1F5EE;border-radius:10px;padding:10px 16px;margin-bottom:1rem;flex-wrap:wrap;gap:8px;">
  <div style="font-size:13px;color:#0F6E56;">
    <strong><?php echo sanitize($sub['name_ka']); ?></strong>
    <?php if ($sub['max_plans_month'] != -1): ?>
      &middot; <?php echo isset($check['count'])?$check['count']:0; ?> / <?php echo $sub['max_plans_month']; ?> გეგმა ამ თვეში
    <?php else: ?>
      &middot; შეუზღუდავი გეგმა
    <?php endif; ?>
  </div>
  <a href="/pricing.php" style="font-size:12px;color:#0F6E56;text-decoration:none;">გეგმის შეცვლა &#8594;</a>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">გეგმის ხანგრძლივობა</div>
  <div class="pill-group" id="days-group">
    <?php foreach (array(3,5,7) as $d):
      $disabled = $d > $max_days;
    ?>
    <button type="button"
            class="pill <?php echo (!$disabled && $d==min(5,$max_days))?'active':''; ?> <?php echo $disabled?'disabled-pill':''; ?>"
            data-val="<?php echo $d; ?>"
            <?php echo $disabled?'disabled title="'.$sub['name_ka'].'-ზე მაქს. '.$max_days.' დღე"':''; ?>
            onclick="selectDays(this)">
      <?php echo $d; ?> დღე<?php if($disabled): ?> 🔒<?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>
  <style>.disabled-pill{opacity:.45;cursor:not-allowed!important;border-style:dashed;}.disabled-pill:hover{background:transparent!important;}</style>
</div>

<div class="card">
  <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;">
    <span>მიმდინარე პროფილი</span>
    <a href="/profile.php" style="font-size:12px;color:var(--green);text-decoration:none;font-weight:400;text-transform:none;letter-spacing:0;">რედაქტირება &#8594;</a>
  </div>
  <?php
  $target_wt      = !empty($profile['target_weight_kg']) ? (float)$profile['target_weight_kg'] : null;
  $current_wt     = (float)$profile['weight_kg'];
  $loss_kg        = $target_wt ? round($current_wt - $target_wt, 1) : round($current_wt * 0.08, 1);
  $timeline_weeks = max(1, round($loss_kg / 0.5));
  $bmi            = round($current_wt / (($profile['height_cm']/100) * ($profile['height_cm']/100)), 1);
  $bmi_label      = $bmi < 18.5 ? 'დაბალი' : ($bmi < 25 ? 'ნორმა' : ($bmi < 30 ? 'ჭარბი' : 'სიმსუქნე'));
  $bmi_color      = $bmi < 18.5 ? '#185FA5' : ($bmi < 25 ? '#0F6E56' : ($bmi < 30 ? '#854F0B' : '#A32D2D'));
  ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px;margin-bottom:1rem;">
    <div style="background:#F8F7F2;border-radius:10px;padding:10px;text-align:center;">
      <div style="font-size:11px;color:#888780;margin-bottom:2px;">წონა</div>
      <div style="font-size:18px;font-weight:500;"><?php echo $profile['weight_kg']; ?><span style="font-size:12px;color:#888780;">კგ</span></div>
    </div>
    <div style="background:#F8F7F2;border-radius:10px;padding:10px;text-align:center;">
      <div style="font-size:11px;color:#888780;margin-bottom:2px;">სიმაღლე</div>
      <div style="font-size:18px;font-weight:500;"><?php echo $profile['height_cm']; ?><span style="font-size:12px;color:#888780;">სმ</span></div>
    </div>
    <div style="background:#F8F7F2;border-radius:10px;padding:10px;text-align:center;">
      <div style="font-size:11px;color:#888780;margin-bottom:2px;">BMI</div>
      <div style="font-size:18px;font-weight:500;color:<?php echo $bmi_color; ?>"><?php echo $bmi; ?></div>
      <div style="font-size:10px;color:<?php echo $bmi_color; ?>"><?php echo $bmi_label; ?></div>
    </div>
    <div style="background:#E1F5EE;border-radius:10px;padding:10px;text-align:center;">
      <div style="font-size:11px;color:#0F6E56;margin-bottom:2px;">სამიზნე</div>
      <div style="font-size:18px;font-weight:500;color:#0F6E56;"><?php echo $target_wt ?: ($current_wt - $loss_kg); ?><span style="font-size:12px;">კგ</span></div>
    </div>
  </div>
  <div style="background:#FAEEDA;border-radius:10px;padding:10px 14px;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div>
      <div style="font-size:11px;color:#854F0B;margin-bottom:2px;">AI-ის სამიზნე</div>
      <div style="font-size:14px;font-weight:500;color:#633806;"><?php echo $loss_kg; ?>კგ-ის დაკლება <?php echo $timeline_weeks; ?> კვირაში</div>
      <div style="font-size:11px;color:#854F0B;">~0.5კგ/კვირაში — რეალისტური ტემპი</div>
    </div>
    <div style="font-size:24px;">🎯</div>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:<?php echo (!empty($profile['allergies']) || !empty($profile['health_notes'])) ? '10px' : '0'; ?>;">
    <span style="background:#F1EFE8;border-radius:99px;padding:4px 12px;font-size:12px;color:#444441;"><?php echo isset($goal_labels[$profile['goal']]) ? $goal_labels[$profile['goal']] : $profile['goal']; ?></span>
    <span style="background:#F1EFE8;border-radius:99px;padding:4px 12px;font-size:12px;color:#444441;"><?php echo isset($act_labels[$profile['activity_level']]) ? $act_labels[$profile['activity_level']] : $profile['activity_level']; ?></span>
    <span style="background:#F1EFE8;border-radius:99px;padding:4px 12px;font-size:12px;color:#444441;">ბიუჯეტი: <?php echo isset($bud_labels[$profile['budget']]) ? $bud_labels[$profile['budget']] : $profile['budget']; ?></span>
    <span style="background:#F1EFE8;border-radius:99px;padding:4px 12px;font-size:12px;color:#444441;"><?php echo $profile['age']; ?> წ. · <?php echo $profile['gender'] === 'male' ? '♂' : '♀'; ?></span>
  </div>
  <?php if (!empty($profile['allergies'])): ?>
  <div style="background:#FCEBEB;border-radius:8px;padding:8px 12px;font-size:12px;color:#A32D2D;margin-bottom:6px;">⚠️ ალერგია: <?php echo sanitize($profile['allergies']); ?></div>
  <?php endif; ?>
  <?php if (!empty($profile['health_notes'])): ?>
  <div style="background:#E6F1FB;border-radius:8px;padding:8px 12px;font-size:12px;color:#185FA5;">🏥 სამედიცინო: <?php echo sanitize($profile['health_notes']); ?></div>
  <?php endif; ?>
</div>

<button class="btn-generate" id="gen-btn" onclick="startGeneration()">
  გეგმის გენერაცია ↗
</button>

<div id="progress-wrap" style="display:none;margin-top:1.5rem;">
  <div class="card" style="text-align:center;padding:2rem;">
    <div style="width:40px;height:40px;border:3px solid #E1F5EE;border-top-color:#1D9E75;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 1rem;"></div>
    <div id="progress-text" style="font-size:15px;font-weight:500;color:#1A1A18;margin-bottom:.5rem;">AI მუშაობს...</div>
    <div id="progress-sub" style="font-size:13px;color:#888780;">გეგმა გენერირდება, 30-60 წამი სჭირდება</div>
    <div style="margin-top:1.25rem;height:4px;background:#F1EFE8;border-radius:99px;overflow:hidden;">
      <div id="progress-bar" style="height:100%;background:#1D9E75;width:5%;border-radius:99px;transition:width .5s;"></div>
    </div>
  </div>
</div>

<div id="error-wrap" style="display:none;margin-top:1rem;">
  <div class="alert alert-error" id="error-msg"></div>
  <button class="btn btn-outline" onclick="resetForm()" style="margin-top:.5rem;">თავიდან სცადე</button>
</div>

<style>@keyframes spin{to{transform:rotate(360deg);}}</style>

<script>
var selectedDays = <?php echo min(5, $max_days); ?>;
var pollTimer    = null;
var progressPct  = 5;
var progressTimer= null;
var msgIdx       = 0;
var messages = ['AI მუშაობს...','ქართული ბაზრის ფასები...','კალორიების გამოთვლა...','მენიუს შედგენა...','გეგმა თითქმის მზადაა...'];

function selectDays(btn) {
  if (btn.disabled) return;
  document.querySelectorAll('#days-group .pill').forEach(function(p){ p.classList.remove('active'); });
  btn.classList.add('active');
  selectedDays = parseInt(btn.getAttribute('data-val'));
}

function startGeneration() {
  document.getElementById('gen-btn').style.display = 'none';
  document.getElementById('progress-wrap').style.display = 'block';
  document.getElementById('error-wrap').style.display = 'none';

  progressPct = 5;
  progressTimer = setInterval(function() {
    progressPct = Math.min(85, progressPct + (progressPct < 40 ? 2 : 0.5));
    document.getElementById('progress-bar').style.width = progressPct + '%';
  }, 800);

  var msgTimer = setInterval(function() {
    msgIdx = (msgIdx + 1) % messages.length;
    document.getElementById('progress-text').textContent = messages[msgIdx];
  }, 6000);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '/api/create_job.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.timeout = 15000;
  xhr.onload = function() {
    var data;
    try { data = JSON.parse(xhr.responseText); } catch(e) {
      clearInterval(msgTimer);
      showError('შეცდომა: ' + xhr.responseText.substring(0, 150));
      return;
    }
    if (data.error) { clearInterval(msgTimer); showError(data.error); return; }
    if (data.job_id) { pollStatus(data.job_id); }
  };
  xhr.onerror = xhr.ontimeout = function() {
    clearInterval(msgTimer);
    showError('კავშირის შეცდომა. სცადეთ თავიდან.');
  };
  xhr.send('days=' + selectedDays);
}

function pollStatus(jobId) {
  pollTimer = setInterval(function() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/api/job_status.php?id=' + jobId, true);
    xhr.timeout = 120000; // first poll triggers generation, needs time
    xhr.onload = function() {
      var data;
      try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
      if (data.status === 'done') {
        clearInterval(pollTimer);
        clearInterval(progressTimer);
        document.getElementById('progress-bar').style.width = '100%';
        document.getElementById('progress-text').textContent = 'გეგმა მზადაა!';
        document.getElementById('progress-sub').textContent = 'გადამისამართება...';
        setTimeout(function() { window.location.href = data.redirect; }, 800);
      } else if (data.status === 'error') {
        clearInterval(pollTimer);
        clearInterval(progressTimer);
        showError(data.error || 'AI-ის შეცდომა. სცადეთ თავიდან.');
      }
    };
    xhr.send();
  }, 3000);
}

function showError(msg) {
  clearInterval(pollTimer);
  clearInterval(progressTimer);
  document.getElementById('progress-wrap').style.display = 'none';
  document.getElementById('error-wrap').style.display = 'block';
  document.getElementById('error-msg').textContent = msg;
}

function resetForm() {
  document.getElementById('gen-btn').style.display = 'block';
  document.getElementById('error-wrap').style.display = 'none';
  progressPct = 5;
  document.getElementById('progress-bar').style.width = '5%';
}
</script>

<?php endif; ?>
<?php renderFooter(); ?>