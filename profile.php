<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$user    = getCurrentUser();
$profile = getUserProfile($user['id']);
$errors  = array();

// ── Delete account ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_email = trim(isset($_POST['confirm_email']) ? $_POST['confirm_email'] : '');
    if (strtolower($confirm_email) !== strtolower($user['email'])) {
        $errors[] = 'ელ.ფოსტა არასწორია. ანგარიში ვერ წაიშალა.';
    } else {
        $db = getDB();
        // Cancel active subscriptions first
        $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE user_id=?')
           ->execute(array($user['id']));
        // Delete user (cascades to profile, plans, logs etc.)
        $db->prepare('DELETE FROM users WHERE id=?')->execute(array($user['id']));
        // Destroy session
        session_destroy();
        header('Location: /index.php?deleted=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $age      = (int)((isset($_POST['age']) ? $_POST['age'] : 0));
    $gender   = (isset($_POST['gender']) ? $_POST['gender'] : '');
    $weight   = (float)((isset($_POST['weight_kg']) ? $_POST['weight_kg'] : 0));
    $height   = (float)((isset($_POST['height_cm']) ? $_POST['height_cm'] : 0));
    $goal     = (isset($_POST['goal']) ? $_POST['goal'] : '');
    $activity = (isset($_POST['activity_level']) ? $_POST['activity_level'] : '');
    $budget   = (isset($_POST['budget']) ? $_POST['budget'] : '');
    $allergies    = trim(isset($_POST['allergies'])        ? $_POST['allergies']        : '');
    $health_notes = trim(isset($_POST['health_notes'])     ? $_POST['health_notes']     : '');
    $target_wt    = (isset($_POST['target_weight_kg']) && $_POST['target_weight_kg'] !== '')
                    ? (float)$_POST['target_weight_kg'] : null;

    $valid_goals     = array('Weight Loss','Muscle Gain','Maintenance');
    $valid_activity  = array('Sedentary','Lightly Active','Moderately Active','Very Active');
    $valid_budget    = array('Low','Medium','High');

    if ($age < 15 || $age > 80)          $errors[] = 'ასაკი 15-დან 80-მდე უნდა იყოს.';
    if (!in_array($gender, array('male','female'))) $errors[] = 'სქესი სავალდებულოა.';
    if ($weight < 30 || $weight > 300)   $errors[] = 'წონა 30-300 კგ-ს შორის უნდა იყოს.';
    if ($height < 100 || $height > 250)  $errors[] = 'სიმაღლე 100-250 სმ-ს შორის უნდა იყოს.';
    if (!in_array($goal, $valid_goals))  $errors[] = 'მიზანი სავალდებულოა.';
    if (!in_array($activity, $valid_activity)) $errors[] = 'აქტიურობის დონე სავალდებულოა.';
    if (!in_array($budget, $valid_budget))     $errors[] = 'ბიუჯეტი სავალდებულოა.';

    if (empty($errors)) {
        $db = getDB();
        if ($profile) {
            $stmt = $db->prepare(
                'UPDATE user_profiles SET age=?, gender=?, weight_kg=?, height_cm=?, goal=?,
                 activity_level=?, budget=?, allergies=?, health_notes=?, target_weight_kg=?, updated_at=? WHERE user_id=?'
            );
            $stmt->execute(array($age, $gender, $weight, $height, $goal, $activity, $budget, $allergies, $health_notes, $target_wt, time(), $user['id']));
        } else {
            $stmt = $db->prepare(
                'INSERT INTO user_profiles (user_id, age, gender, weight_kg, height_cm, goal, activity_level, budget, allergies, health_notes, target_weight_kg, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array($user['id'], $age, $gender, $weight, $height, $goal, $activity, $budget, $allergies, $health_notes, $target_wt, time()));
        }
        setFlash('success', 'პროფილი წარმატებით განახლდა!');
        redirect('/profile.php');
    }
}

// ── Save food preferences ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $db   = getDB();
    $uid  = (int)$user['id'];
    $db->exec("SET NAMES utf8mb4");
    // Save weekly budget
    $weekly_budget = !empty($_POST['weekly_budget']) ? (float)$_POST['weekly_budget'] : null;
    try {
        $db->prepare('UPDATE user_profiles SET weekly_budget=? WHERE user_id=?')
           ->execute(array($weekly_budget, $uid));
    } catch(Exception $e) {}

    // Save avoid/prefer lists
    $db->prepare('DELETE FROM user_food_prefs WHERE user_id=?')->execute(array($uid));
    foreach (array('avoid','prefer') as $type) {
        $items = explode(',', $_POST[$type] ?? '');
        foreach ($items as $item) {
            $item = trim($item);
            if (!$item) continue;
            try {
                $db->prepare('INSERT IGNORE INTO user_food_prefs (user_id,preference_type,item,created_at) VALUES (?,?,?,?)')
                   ->execute(array($uid, $type, $item, time()));
            } catch(Exception $e) {}
        }
    }
    setFlash('success', 'პრეფერენციები შენახულია!');
    header('Location: /profile.php'); exit;
}

// Load food prefs
$food_prefs = array('avoid'=>array(), 'prefer'=>array());
$user_id    = (int)$user['id'];
try {
    $db2 = getDB();
    $db2->exec("SET NAMES utf8mb4");
    $fp  = $db2->prepare('SELECT * FROM user_food_prefs WHERE user_id=?');
    $fp->execute(array($user_id));
    foreach ($fp->fetchAll() as $row) {
        $food_prefs[$row['preference_type']][] = $row['item'];
    }
    // Also load weekly_budget into profile
    if ($profile && !isset($profile['weekly_budget'])) {
        $wb = $db2->prepare('SELECT weekly_budget FROM user_profiles WHERE user_id=? LIMIT 1');
        $wb->execute(array($user_id));
        $wb_row = $wb->fetch();
        if ($wb_row) $profile['weekly_budget'] = $wb_row['weekly_budget'];
    }
} catch(Exception $e) {}

// Prefill from existing profile
$p = $profile ?: array();
renderHeader('პროფილი', 'profile');
?>

<div class="page-header">
  <div class="page-title">ჩემი პროფილი</div>
  <div class="page-subtitle">შეავსეთ თქვენი მონაცემები პერსონალური გეგმისთვის</div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
  </div>
<?php endif; ?>

<form method="POST" action="">

  <div class="card">
    <div class="card-title">პირადი მონაცემები</div>
    <div class="grid-2" style="margin-bottom:12px;">
      <div class="form-group">
        <label>ასაკი</label>
        <input type="number" name="age" class="form-control" min="15" max="80"
               value="<?php echo (int)(isset($p['age']) ? $p['age'] : (isset($_POST['age']) ? $_POST['age'] : '')); ?>" required>
      </div>
      <div class="form-group">
        <label>სქესი</label>
        <select name="gender" class="form-control" required>
          <option value="">აირჩიეთ...</option>
          <option value="male"   <?php echo ((isset($p['gender']) ? $p['gender'] : ''))=='male'?'selected':''; ?>>მამრობითი</option>
          <option value="female" <?php echo ((isset($p['gender']) ? $p['gender'] : ''))=='female'?'selected':''; ?>>მდედრობითი</option>
        </select>
      </div>
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label>წონა (კგ)</label>
        <input type="number" name="weight_kg" step="0.1" class="form-control" min="30" max="300"
               value="<?php echo (isset($p['weight_kg']) ? $p['weight_kg'] : (isset($_POST['weight_kg']) ? $_POST['weight_kg'] : '')); ?>" required>
      </div>
      <div class="form-group">
        <label>სიმაღლე (სმ)</label>
        <input type="number" name="height_cm" step="0.1" class="form-control" min="100" max="250"
               value="<?php echo (isset($p['height_cm']) ? $p['height_cm'] : (isset($_POST['height_cm']) ? $_POST['height_cm'] : '')); ?>" required>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">მიზანი</div>
    <div class="pill-group" id="goal-group">
      <?php
      $goals = array('Weight Loss'=>'წონის დაკლება','Muscle Gain'=>'კუნთის მომატება','Maintenance'=>'წონის შენარჩუნება');
      $sel_goal = isset($p['goal']) ? $p['goal'] : (isset($_POST['goal']) ? $_POST['goal'] : '');
      foreach ($goals as $val => $label): ?>
        <button type="button" class="pill <?php echo $sel_goal===$val?'active':''; ?>"
                data-group="goal" data-val="<?php echo $val; ?>" onclick="selectPill(this,'goal')">
          <?php echo $label; ?>
        </button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="goal" id="input-goal" value="<?php echo sanitize($sel_goal); ?>">
  </div>

  <div class="card">
    <div class="card-title">აქტიურობის დონე</div>
    <div class="pill-group" id="activity-group">
      <?php
      $activities = array('Sedentary'=>'უმოქმედო','Lightly Active'=>'მცირე აქტიური','Moderately Active'=>'ზომიერად აქტიური','Very Active'=>'ძალიან აქტიური');
      $sel_act = isset($p['activity_level']) ? $p['activity_level'] : (isset($_POST['activity_level']) ? $_POST['activity_level'] : '');
      foreach ($activities as $val => $label): ?>
        <button type="button" class="pill <?php echo $sel_act===$val?'active':''; ?>"
                data-val="<?php echo $val; ?>" onclick="selectPill(this,'activity')">
          <?php echo $label; ?>
        </button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="activity_level" id="input-activity" value="<?php echo sanitize($sel_act); ?>">
  </div>

  <div class="card">
    <div class="card-title">ბიუჯეტი</div>
    <div class="pill-group" id="budget-group">
      <?php
      $budgets = array('Low'=>'დაბალი (კვერცხი, მარცვლეული)','Medium'=>'საშუალო (დაბალანსებული)','High'=>'მაღალი (ორაგული, ავოკადო)');
      $sel_bud = isset($p['budget']) ? $p['budget'] : (isset($_POST['budget']) ? $_POST['budget'] : '');
      foreach ($budgets as $val => $label): ?>
        <button type="button" class="pill budget-<?php echo strtolower($val); ?> <?php echo $sel_bud===$val?'active':''; ?>"
                data-val="<?php echo $val; ?>" onclick="selectPill(this,'budget')">
          <?php echo $label; ?>
        </button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="budget" id="input-budget" value="<?php echo sanitize($sel_bud); ?>">
  </div>

  <div class="card">
    <div class="card-title">ალერგია / არ მოსწონს (სურვილისამებრ)</div>
    <div class="form-group" style="margin-bottom:0;">
      <input type="text" name="allergies" class="form-control"
             placeholder="მაგ. ღორის ხორცი, რძე..."
             value="<?php echo sanitize(isset($p['allergies']) ? $p['allergies'] : (isset($_POST['allergies']) ? $_POST['allergies'] : '')); ?>">
    </div>
  </div>

  <div class="card">
    <div class="card-title">სამედიცინო ინფორმაცია (სურვილისამებრ)</div>
    <div class="form-group">
      <label>სამიზნე წონა (კგ)</label>
      <input type="number" step="0.1" name="target_weight_kg" class="form-control"
             placeholder="მაგ. 70"
             value="<?php echo sanitize(isset($p['target_weight_kg']) ? $p['target_weight_kg'] : (isset($_POST['target_weight_kg']) ? $_POST['target_weight_kg'] : '')); ?>">
      <small style="font-size:12px;color:var(--gray-400);margin-top:4px;display:block;">AI გამოიყენებს წონის დაკლების გეგმის შედგენისთვის</small>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label>ჯანმრთელობის შენიშვნები</label>
      <textarea name="health_notes" class="form-control" rows="3"
                placeholder="მაგ. დიაბეტი ტიპი 2, მაღალი წნევა, ფარისებრი ჯირკვლის პრობლემა, ქოლესტეროლი..."
                style="resize:vertical;"><?php echo sanitize(isset($p['health_notes']) ? $p['health_notes'] : (isset($_POST['health_notes']) ? $_POST['health_notes'] : '')); ?></textarea>
      <small style="font-size:12px;color:var(--gray-400);margin-top:4px;display:block;">AI ექიმი გაითვალისწინებს შენი ჯანმრთელობის მდგომარეობას</small>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-full" style="padding:13px;">
    პროფილის შენახვა
  </button>

</form>

<!-- Smart Plan — separate form -->
<form method="POST" action="">
<input type="hidden" name="save_prefs" value="1">
<div class="card" style="margin-top:.75rem;">
  <div class="card-title">&#129504; Smart Plan პარამეტრები</div>
  <div class="form-group">
    <label>&#128230; კვირის ბიუჯეტი (&#8382;)</label>
    <input type="number" name="weekly_budget" class="form-control" step="1" min="0" max="500"
           value="<?php echo isset($p['weekly_budget']) ? $p['weekly_budget'] : ''; ?>" placeholder="&#4351;&#4304;&#4306;: 60">
    <div style="font-size:11px;color:var(--t3);margin-top:4px;">AI ოპტიმიზებს გეგმას ამ ბიუჯეტის მიხედვით</div>
  </div>
  <div class="form-group">
    <label>&#128683; რას არ ვჭამ</label>
    <input type="text" name="avoid" class="form-control"
           value="<?php echo htmlspecialchars(implode(', ', $food_prefs['avoid'])); ?>"
           placeholder="&#4315;&#4304;&#4306;: &#4334;&#4304;&#4334;&#4309;&#4312;, &#4316;&#4312;&#4317;&#4308;&#4312;">
    <div style="font-size:11px;color:var(--t3);margin-top:4px;">AI ამ პროდუქტებს არ გამოიყენებს</div>
  </div>
  <div class="form-group">
    <label>&#10084;&#65039; რისი ჭამა მიყვარს</label>
    <input type="text" name="prefer" class="form-control"
           value="<?php echo htmlspecialchars(implode(', ', $food_prefs['prefer'])); ?>"
           placeholder="&#4315;&#4304;&#4306;: &#4325;&#4304;&#4311;&#4304;&#4315;&#4312;, &#4305;&#4320;&#4312;&#4316;&#4326;&#4312;">
    <div style="font-size:11px;color:var(--t3);margin-top:4px;">AI ამ პროდუქტებს უფრო ხშირად გამოიყენებს</div>
  </div>
  <button type="submit" class="btn btn-primary">შენახვა</button>
</div>
</form>

<script>
function selectPill(btn, group) {
  var pills = btn.parentNode.querySelectorAll('.pill');
  for (var i = 0; i < pills.length; i++) pills[i].classList.remove('active');
  btn.classList.add('active');
  document.getElementById('input-' + group).value = btn.getAttribute('data-val');
}
</script>

<!-- Delete account section -->
<div class="card" style="border-color:#F7C1C1;margin-top:2rem;">
  <div class="card-title" style="color:#A32D2D;">⚠️ საფრთხის ზონა</div>
  <p style="font-size:13px;color:var(--gray-400);margin-bottom:1rem;line-height:1.6;">
    ანგარიშის წაშლა <strong>შეუქცევადია</strong> — ყველა გეგმა, ისტორია და მონაცემი სამუდამოდ წაიშლება.
  </p>
  <button type="button" class="btn btn-danger"
          onclick="document.getElementById('delete-modal').style.display='flex'">
    ანგარიშის წაშლა
  </button>
</div>

<!-- Delete modal -->
<div id="delete-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#fff;border-radius:16px;padding:2rem;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <div style="font-size:32px;text-align:center;margin-bottom:.75rem;">⚠️</div>
    <h2 style="font-size:18px;font-weight:500;text-align:center;margin-bottom:.5rem;">ანგარიშის წაშლა</h2>
    <p style="font-size:13px;color:var(--gray-400);text-align:center;margin-bottom:1.5rem;line-height:1.6;">
      ეს მოქმედება შეუქცევადია. ყველა მონაცემი წაიშლება.<br>
      დასადასტურებლად შეიყვანეთ თქვენი ელ.ფოსტა:
    </p>
    <form method="POST">
      <div class="form-group">
        <input type="email" name="confirm_email" class="form-control"
               placeholder="<?php echo sanitize($user['email']); ?>"
               required autocomplete="off">
      </div>
      <div style="display:flex;gap:8px;margin-top:.5rem;">
        <button type="button" class="btn btn-outline btn-full"
                onclick="document.getElementById('delete-modal').style.display='none'">
          გაუქმება
        </button>
        <button type="submit" name="delete_account" class="btn btn-full"
                style="background:#A32D2D;color:#fff;border-color:#A32D2D;">
          წაშლა
        </button>
      </div>
    </form>
  </div>
</div>



<?php renderFooter(); ?>