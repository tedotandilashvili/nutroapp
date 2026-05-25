<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Grant / change subscription ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_sub'])) {
    $uid     = (int)$_POST['user_id'];
    $plan_id = (int)$_POST['plan_id'];
    $months  = max(1, (int)$_POST['months']);

    $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE user_id=? AND status="active"')
       ->execute(array($uid));

    $now = time();
    $stmt = $db->prepare(
        'INSERT INTO user_subscriptions (user_id, plan_id, status, started_at, expires_at, created_at, notes)
         VALUES (?, ?, "active", ?, ?, ?, ?)'
    );
    $stmt->execute(array($uid, $plan_id, $now, $now + $months*30*86400, $now, 'Admin granted'));
    setFlash('success', 'გამოწერა გააქტიურდა.');
    header('Location: /admin/subscriptions.php'); exit;
}

// ── Cancel subscription ────────────────────────────────────────────────────────
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE id=?')
       ->execute(array((int)$_GET['cancel']));
    setFlash('success', 'გამოწერა გაუქმდა.');
    header('Location: /admin/subscriptions.php'); exit;
}

// ── Edit plan prices / features ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $pid      = (int)$_POST['plan_id'];
    $price    = (float)$_POST['price_gel'];
    $max_p    = $_POST['max_plans_month'] === '-1' ? -1 : (int)$_POST['max_plans_month'];
    $max_d    = (int)$_POST['max_days'];
    $ai_ref   = isset($_POST['ai_price_refresh']) ? 1 : 0;
    $features = trim($_POST['features']);
    // Validate JSON
    json_decode($features);
    if (json_last_error() !== JSON_ERROR_NONE) {
        setFlash('error', 'Features JSON-ი არასწორია.'); header('Location: /admin/subscriptions.php'); exit;
    }
    $db->prepare('UPDATE subscription_plans SET price_gel=?,max_plans_month=?,max_days=?,ai_price_refresh=?,features=? WHERE id=?')
       ->execute(array($price, $max_p, $max_d, $ai_ref, $features, $pid));
    setFlash('success', 'გეგმა განახლდა.');
    header('Location: /admin/subscriptions.php'); exit;
}

// ── Data ───────────────────────────────────────────────────────────────────────
$plans = $db->query('SELECT * FROM subscription_plans ORDER BY sort_order')->fetchAll();

$page   = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$limit  = 25;
$offset = ($page - 1) * $limit;
$total  = (int)$db->query('SELECT COUNT(*) FROM user_subscriptions')->fetchColumn();
$pages  = ceil($total / $limit);

$subs = $db->prepare(
    'SELECT us.*, u.name as user_name, u.email as user_email,
            sp.name_ka as plan_name_ka, sp.name as plan_name, sp.slug as plan_slug
     FROM user_subscriptions us
     JOIN users u ON u.id = us.user_id
     JOIN subscription_plans sp ON sp.id = us.plan_id
     ORDER BY us.created_at DESC LIMIT ? OFFSET ?'
);
$subs->execute(array($limit, $offset));
$subs = $subs->fetchAll();

// Stats per plan
$plan_stats = array();
foreach ($plans as $p) {
    $cnt = $db->prepare('SELECT COUNT(*) FROM user_subscriptions WHERE plan_id=? AND status="active" AND expires_at>?');
    $cnt->execute(array($p['id'], time())); $cnt = (int)$cnt->fetchColumn();
    $plan_stats[$p['id']] = $cnt;
}

$users_list = $db->query('SELECT id, name, email FROM users ORDER BY name')->fetchAll();

$status_badge = array('active'=>'badge-green','cancelled'=>'badge-red','expired'=>'badge-gray');
$status_ka    = array('active'=>'აქტიური','cancelled'=>'გაუქმებული','expired'=>'ვადაგასული');
$slug_color   = array('low_cost'=>'badge-blue','medium'=>'badge-amber','high_waltage'=>'badge-green');

renderAdminHeader('გამოწერები', 'subscriptions');
?>

<!-- Plan stats cards -->
<div style="display:grid;grid-template-columns:repeat(<?php echo count($plans); ?>,1fr);gap:1rem;margin-bottom:1.5rem;">
  <?php foreach ($plans as $p): ?>
  <div class="adm-stat" style="border-top:3px solid <?php echo $p['slug']==='high_waltage'?'#1D9E75':($p['slug']==='medium'?'#EF9F27':'#378ADD'); ?>;">
    <div class="adm-stat-lbl"><?php echo sanitize($p['name_ka']); ?></div>
    <div class="adm-stat-val"><?php echo $plan_stats[$p['id']]; ?></div>
    <div style="font-size:12px;color:#888780;"><?php echo number_format($p['price_gel'],2); ?> ₾/თვეში &middot; აქტიური</div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">

  <!-- Grant subscription form -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">გამოწერის მინიჭება</span></div>
    <div style="padding:1.25rem;">
      <form method="POST">
        <div class="form-group">
          <label style="font-size:12px;color:#888780;">მომხმარებელი</label>
          <select name="user_id" class="form-control" required>
            <option value="">აირჩიეთ...</option>
            <?php foreach ($users_list as $u): ?>
              <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['name']); ?> — <?php echo sanitize($u['email']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size:12px;color:#888780;">გეგმა</label>
          <select name="plan_id" class="form-control" required>
            <?php foreach ($plans as $p): ?>
              <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['name_ka']); ?> — <?php echo number_format($p['price_gel'],2); ?> ₾</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size:12px;color:#888780;">ვადა (თვეები)</label>
          <input type="number" name="months" class="form-control" value="1" min="1" max="24">
        </div>
        <button type="submit" name="grant_sub" class="adm-btn adm-btn-primary" style="width:100%;padding:9px;">გააქტიურება</button>
      </form>
    </div>
  </div>

  <!-- Edit plan settings -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">გეგმის პარამეტრები</span></div>
    <div style="padding:1.25rem;">
      <div style="margin-bottom:12px;">
        <label style="font-size:12px;color:#888780;">გეგმის არჩევა</label>
        <select id="edit-plan-select" class="form-control" onchange="loadPlan(this.value)">
          <?php foreach ($plans as $p): ?>
            <option value="<?php echo $p['id']; ?>"
                    data-price="<?php echo $p['price_gel']; ?>"
                    data-max-plans="<?php echo $p['max_plans_month']; ?>"
                    data-max-days="<?php echo $p['max_days']; ?>"
                    data-ai="<?php echo $p['ai_price_refresh']; ?>"
                    data-features='<?php echo htmlspecialchars($p['features'],ENT_QUOTES); ?>'>
              <?php echo sanitize($p['name_ka']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <form method="POST" id="edit-plan-form">
        <input type="hidden" name="plan_id" id="ep-id" value="<?php echo $plans[0]['id']; ?>">
        <div class="grid-2" style="gap:8px;margin-bottom:8px;">
          <div>
            <label style="font-size:11px;color:#888780;">ფასი ₾</label>
            <input type="number" step="0.01" name="price_gel" id="ep-price" class="form-control" value="<?php echo $plans[0]['price_gel']; ?>">
          </div>
          <div>
            <label style="font-size:11px;color:#888780;">მაქს. გეგმა/თვეში (-1=∞)</label>
            <input type="number" name="max_plans_month" id="ep-maxp" class="form-control" value="<?php echo $plans[0]['max_plans_month']; ?>">
          </div>
        </div>
        <div class="grid-2" style="gap:8px;margin-bottom:8px;">
          <div>
            <label style="font-size:11px;color:#888780;">მაქს. დღეები</label>
            <select name="max_days" id="ep-maxd" class="form-control">
              <option value="3">3 დღე</option>
              <option value="5">5 დღე</option>
              <option value="7">7 დღე</option>
            </select>
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
            <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="checkbox" name="ai_price_refresh" id="ep-ai" <?php echo $plans[0]['ai_price_refresh']?'checked':''; ?>>
              AI ფასები
            </label>
          </div>
        </div>
        <div style="margin-bottom:8px;">
          <label style="font-size:11px;color:#888780;">Features JSON</label>
          <textarea name="features" id="ep-features" class="form-control"
                    style="height:80px;font-size:12px;font-family:monospace;"><?php echo htmlspecialchars($plans[0]['features'],ENT_QUOTES); ?></textarea>
        </div>
        <button type="submit" name="save_plan" class="adm-btn adm-btn-primary" style="width:100%;padding:8px;">შენახვა</button>
      </form>
    </div>
  </div>

</div>

<!-- Subscriptions table -->
<div class="adm-card">
  <div class="adm-card-head">
    <span class="adm-card-title">ყველა გამოწერა</span>
    <span style="font-size:12px;color:#888780;">სულ <?php echo $total; ?></span>
  </div>
  <table class="adm-table">
    <thead>
      <tr><th>მომხმარებელი</th><th>გეგმა</th><th>სტატუსი</th><th>დაიწყო</th><th>სრულდება</th><th>მოქმ.</th></tr>
    </thead>
    <tbody>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td>
          <div style="font-weight:500;font-size:13px;"><?php echo sanitize($s['user_name']); ?></div>
          <div style="font-size:11px;color:#888780;"><?php echo sanitize($s['user_email']); ?></div>
        </td>
        <td>
          <span class="adm-badge <?php echo isset($slug_color[$s['plan_slug']])?$slug_color[$s['plan_slug']]:'badge-gray'; ?>">
            <?php echo sanitize($s['plan_name_ka']); ?>
          </span>
        </td>
        <td>
          <span class="adm-badge <?php echo isset($status_badge[$s['status']])?$status_badge[$s['status']]:'badge-gray'; ?>">
            <?php echo isset($status_ka[$s['status']])?$status_ka[$s['status']]:$s['status']; ?>
          </span>
        </td>
        <td style="font-size:12px;color:#888780;"><?php echo date('d/m/Y', $s['started_at']); ?></td>
        <td style="font-size:12px;<?php echo $s['expires_at']<time()?'color:#A32D2D;':'color:#888780;'; ?>">
          <?php echo date('d/m/Y', $s['expires_at']); ?>
        </td>
        <td>
          <?php if ($s['status']==='active'): ?>
            <a href="/admin/subscriptions.php?cancel=<?php echo $s['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-danger"
               onclick="return confirm('გამოწერა გაუქმდება.')">გაუქმება</a>
          <?php else: ?>
            <span style="font-size:12px;color:#B4B2A9;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($subs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:2rem;color:#888780;">გამოწერა ჯერ არ არის</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1rem;flex-wrap:wrap;">
  <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?php echo $i; ?>"
       style="padding:6px 12px;border-radius:8px;font-size:13px;text-decoration:none;border:1px solid <?php echo $i==$page?'#1D9E75':'#E8E6DF'; ?>;background:<?php echo $i==$page?'#1D9E75':'#fff'; ?>;color:<?php echo $i==$page?'#fff':'#444441'; ?>;">
      <?php echo $i; ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
var plans = <?php echo json_encode(array_column($plans, null, 'id'), JSON_UNESCAPED_UNICODE); ?>;

function loadPlan(id) {
    var p = plans[id];
    if (!p) return;
    document.getElementById('ep-id').value      = id;
    document.getElementById('ep-price').value   = p.price_gel;
    document.getElementById('ep-maxp').value    = p.max_plans_month;
    document.getElementById('ep-ai').checked    = p.ai_price_refresh == 1;
    document.getElementById('ep-features').value= p.features;
    var sel = document.getElementById('ep-maxd');
    for (var i=0;i<sel.options.length;i++) {
        if (parseInt(sel.options[i].value) === parseInt(p.max_days)) { sel.selectedIndex=i; break; }
    }
}
</script>

<?php renderAdminFooter(); ?>
