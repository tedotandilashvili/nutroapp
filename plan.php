<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/claude.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$plan_id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$plan_id) redirect('/history.php');

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM diet_plans WHERE id = ? AND user_id = ?');
$stmt->execute(array($plan_id, $_SESSION['user_id']));
$plan = $stmt->fetch();
if (!$plan) { setFlash('error', 'გეგმა ვერ მოიძებნა.'); redirect('/history.php'); }

$stmt = $db->prepare('SELECT * FROM plan_days WHERE plan_id = ? ORDER BY day_number');
$stmt->execute(array($plan_id));
$days = $stmt->fetchAll();
foreach ($days as &$day) {
    $stmt2 = $db->prepare('SELECT * FROM plan_meals WHERE day_id = ?');
    $stmt2->execute(array($day['id']));
    $day['meals'] = $stmt2->fetchAll();
}
unset($day);

// Parse raw_json for price/store data (stored at generation time)
$raw = json_decode($plan['raw_json'], true);
$daily_cost     = isset($raw['estimated_daily_cost_gel']) ? number_format((float)$raw['estimated_daily_cost_gel'], 2) : null;
$cheapest_store = isset($raw['cheapest_store'])           ? $raw['cheapest_store']                                    : null;
// Build meal cost map: day_number => array of meal costs
$meal_costs = array();
if (isset($raw['days'])) {
    foreach ($raw['days'] as $rd) {
        $dn = $rd['day'];
        $meal_costs[$dn] = array();
        foreach ($rd['meals'] as $idx => $rm) {
            $meal_costs[$dn][$idx] = array(
                'cost_gel'   => isset($rm['cost_gel'])   ? $rm['cost_gel']   : null,
                'best_store' => isset($rm['best_store']) ? $rm['best_store'] : null,
            );
        }
    }
}

// Current price table for sidebar
$price_rows = getPriceTable();

$day_names_ka = array('','პირველი','მეორე','მესამე','მეოთხე','მეხუთე','მეექვსე','მეშვიდე');

renderHeader(sanitize($plan['title']), 'history');
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
  <div>
    <div class="page-title"><?php echo sanitize($plan['title']); ?></div>
    <div class="page-subtitle"><?php echo date('d/m/Y H:i', $plan['created_at']); ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="/shopping.php?plan_id=<?php echo $plan['id']; ?>" class="btn btn-primary">🛒 სასყიდლო სია</a>
    <a href="/plan_pdf.php?id=<?php echo $plan['id']; ?>" target="_blank" class="btn btn-outline">📄 PDF</a>
    <a href="/generate.php" class="btn btn-outline">ახალი გეგმა</a>
    <a href="/api/delete_plan.php?id=<?php echo $plan['id']; ?>"
       class="btn btn-danger"
       onclick="return confirm('გეგმა წაიშლება. დარწმუნებული ხართ?')">წაშლა</a>
  </div>
</div>

<!-- Macro + cost summary -->
<div class="card" style="margin-bottom:1rem;">
  <div class="card-title">დღიური სამიზნეები და ღირებულება</div>
  <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));">
    <div class="metric-card">
      <div class="metric-label">სამიზნე კალ.</div>
      <div class="metric-val"><?php echo $plan['target_calories']; ?></div>
      <div class="metric-sub">კკალ</div>
    </div>
    <div class="metric-card neutral">
      <div class="metric-label">TDEE</div>
      <div class="metric-val"><?php echo $plan['tdee']; ?></div>
      <div class="metric-sub">კკალ</div>
    </div>
    <?php if ($daily_cost): ?>
    <div class="metric-card" style="background:#E1F5EE;">
      <div class="metric-label" style="color:#0F6E56;">სავარ. ღირ./დღეში</div>
      <div class="metric-val"><?php echo $daily_cost; ?> ₾</div>
      <div class="metric-sub" style="color:#0F6E56;"><?php echo $plan['days']; ?> დღეში: ~<?php echo number_format($daily_cost * $plan['days'], 2); ?> ₾</div>
    </div>
    <?php endif; ?>
    <?php if ($cheapest_store): ?>
    <div class="metric-card" style="background:#FAEEDA;">
      <div class="metric-label" style="color:#854F0B;">იაფი მაღაზია</div>
      <div class="metric-val" style="color:#854F0B;font-size:16px;"><?php echo sanitize($cheapest_store); ?></div>
      <div class="metric-sub" style="color:#854F0B;">ამ გეგმისთვის</div>
    </div>
    <?php endif; ?>
  </div>
  <div class="macro-chips" style="margin-top:.75rem;">
    <span class="macro-chip chip-p">ცილა: <?php echo $plan['protein_g']; ?>გ</span>
    <span class="macro-chip chip-c">ნახშ.: <?php echo $plan['carbs_g']; ?>გ</span>
    <span class="macro-chip chip-f">ცხიმი: <?php echo $plan['fat_g']; ?>გ</span>
    <?php if ($raw['estimated_daily_cost_gel']): ?>
    <span class="macro-chip" style="background:#E1F5EE;color:#0F6E56;">~<?php echo $daily_cost; ?> ₾/დღე</span>
    <?php endif; ?>
  </div>
</div>

<!-- Days -->
<?php foreach ($days as $di => $day): ?>
  <?php
    $day_raw_cost = isset($raw['days'][$di]['estimated_cost_gel']) ? $raw['days'][$di]['estimated_cost_gel'] : null;
  ?>
  <div class="card day-block">
    <div class="day-label" style="display:flex;justify-content:space-between;align-items:center;">
      <span><?php echo isset($day_names_ka[$day['day_number']]) ? $day_names_ka[$day['day_number']] : ('დღე '.$day['day_number']); ?> დღე</span>
      <?php if ($day_raw_cost): ?>
        <span style="font-family:'DM Sans',sans-serif;font-size:14px;color:#1D9E75;font-weight:500;">~<?php echo number_format((float)$day_raw_cost,2); ?> ₾</span>
      <?php endif; ?>
    </div>

    <?php foreach ($day['meals'] as $mi => $meal): ?>
      <?php
        $mcost  = isset($meal_costs[$day['day_number']][$mi]['cost_gel'])   ? $meal_costs[$day['day_number']][$mi]['cost_gel']   : null;
        $mstore = isset($meal_costs[$day['day_number']][$mi]['best_store']) ? $meal_costs[$day['day_number']][$mi]['best_store'] : null;
      ?>
      <div class="meal-row" style="grid-template-columns:110px 1fr auto auto;">
        <?php
        $meal_type_display = array(
          'sauzme'   => 'საუზმე',
          'branchi'  => 'ბრანჩი',
          'sadili'   => 'სადილი',
          'vaxshami' => 'ვახშამი',
        );
        $mt = strtolower($meal['meal_type']);
        $mt_label = isset($meal_type_display[$mt]) ? $meal_type_display[$mt] : $meal['meal_type'];
      ?>
      <div class="meal-type">
          <?php echo sanitize($mt_label); ?>
          <?php if (!empty($meal['meal_time'])): ?>
            <div style="font-size:11px;color:var(--green);font-weight:600;margin-top:2px;">
              &#128336; <?php echo sanitize($meal['meal_time']); ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <div class="meal-name"><?php echo sanitize($meal['name']); ?></div>
        <?php if (isset($meal['protein_g']) || isset($meal['carbs_g']) || isset($meal['fat_g'])): ?>
        <div style="display:flex;gap:4px;margin-top:3px;">
          <span style="font-size:10px;background:#E1F5EE;color:#0F6E56;border-radius:4px;padding:1px 6px;font-weight:500;">ც <?php echo isset($meal['protein_g'])?(float)$meal['protein_g']:0; ?>გ</span>
          <span style="font-size:10px;background:#FAEEDA;color:#854F0B;border-radius:4px;padding:1px 6px;font-weight:500;">ნ <?php echo isset($meal['carbs_g'])?(float)$meal['carbs_g']:0; ?>გ</span>
          <span style="font-size:10px;background:#FAECE7;color:#993C1D;border-radius:4px;padding:1px 6px;font-weight:500;">ჩ <?php echo isset($meal['fat_g'])?(float)$meal['fat_g']:0; ?>გ</span>
        </div>
        <?php endif; ?>
          <div class="meal-detail"><?php echo sanitize($meal['ingredients']); ?> &middot; <?php echo sanitize($meal['portion']); ?></div>
          <?php if (!empty($meal['hack_ka'])): ?>
            <div style="margin-top:5px;font-size:12px;background:rgba(245,158,11,.08);color:#92400E;border-radius:6px;padding:4px 9px;display:inline-flex;align-items:center;gap:4px;">
              &#128161; <?php echo sanitize($meal['hack_ka']); ?>
            </div>
          <?php endif; ?>
          <?php
          // Per-ingredient prices from raw JSON
          $ing_prices = array();
          if (isset($raw['days']) && isset($raw['days'][$di]['meals'][$mi]['ingredient_list'])) {
              $ing_prices = $raw['days'][$di]['meals'][$mi]['ingredient_list'];
          }
          if (!empty($ing_prices)): ?>
          <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
            <?php foreach ($ing_prices as $ip): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;background:var(--bg);border-radius:8px;padding:5px 10px;">
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:500;color:var(--t1);"><?php echo sanitize($ip['name']); ?></span>
                <?php if (!empty($ip['amount'])): ?><span style="font-size:11px;color:var(--t3);"><?php echo sanitize($ip['amount']); ?></span><?php endif; ?>
                <?php if (!empty($ip['calories'])): ?><span style="font-size:11px;color:var(--t2);"><?php echo (int)$ip['calories']; ?> კკ</span><?php endif; ?>
                <?php if (!empty($ip['protein_g']) || !empty($ip['carbs_g']) || !empty($ip['fat_g'])): ?>
                <div style="display:flex;gap:3px;">
                  <span style="font-size:10px;background:#E1F5EE;color:#0F6E56;border-radius:4px;padding:1px 5px;">ც<?php echo (float)$ip['protein_g']; ?>გ</span>
                  <span style="font-size:10px;background:#FAEEDA;color:#854F0B;border-radius:4px;padding:1px 5px;">ნ<?php echo (float)$ip['carbs_g']; ?>გ</span>
                  <span style="font-size:10px;background:#FAECE7;color:#993C1D;border-radius:4px;padding:1px 5px;">ჩ<?php echo (float)$ip['fat_g']; ?>გ</span>
                </div>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:6px;">
                <span style="font-size:12px;color:#1D9E75;font-weight:500;"><?php echo number_format((float)$ip['price_gel'],2); ?>₾</span>
                <span style="font-size:10px;color:var(--t3);"><?php echo sanitize($ip['store']); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php if ($mstore): ?>
            <div style="font-size:11px;color:#1D9E75;margin-top:2px;">&#x2713; <?php echo sanitize($mstore); ?></div>
          <?php endif; ?>
        </div>
        <div style="text-align:right;">
          <div class="meal-cal"><?php echo $meal['calories']; ?> კკალ</div>
          <?php if ($mcost): ?>
            <div style="font-size:12px;color:#854F0B;font-weight:500;">~<?php echo number_format((float)$mcost,2); ?> ₾</div>
          <?php endif; ?>
          <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;justify-content:flex-end;">
            <button onclick="swapMeal('<?php echo addslashes($meal['name']); ?>','<?php echo addslashes($meal['meal_type']); ?>',<?php echo (int)$meal['calories']; ?>)"
                    style="background:none;border:0.5px solid var(--border-s);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;color:var(--t3);font-family:inherit;"
                    title="ალტერნატივა">🔄</button>
            <button onclick="showRecipe('<?php echo addslashes($meal['name']); ?>','<?php echo addslashes($meal['ingredients']); ?>')"
                    style="background:none;border:0.5px solid var(--border-s);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;color:var(--t3);font-family:inherit;"
                    title="რეცეპტი">👨‍🍳 რეცეპტი</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="total-row">
      <span class="total-label">დღის სულ</span>
      <span class="total-cal"><?php echo $day['total_calories']; ?> კკალ</span>
    </div>
  </div>
<?php endforeach; ?>

<!-- Live price table -->
<div class="card" style="margin-top:1.5rem;">
  <div class="card-title" style="display:flex;justify-content:space-between;">
    <span>ფასების ცხრილი — მიმდინარე</span>
    <a href="/admin/prices.php" style="font-size:11px;color:#1D9E75;text-decoration:none;">რედაქტირება &#8594;</a>
  </div>

  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="border-bottom:1px solid var(--gray-200);">
          <th style="text-align:left;padding:6px 8px;color:var(--gray-400);font-weight:500;">პროდუქტი</th>
          <th style="text-align:center;padding:6px 4px;color:#1D9E75;font-weight:500;">Agrohub</th>
          <th style="text-align:center;padding:6px 4px;color:#1D9E75;font-weight:500;">2Nabiji</th>
          <th style="text-align:center;padding:6px 4px;color:#1D9E75;font-weight:500;">Carrefour</th>
          <th style="text-align:center;padding:6px 4px;color:#1D9E75;font-weight:500;">Goodwill</th>
          <th style="text-align:center;padding:6px 4px;color:#1D9E75;font-weight:500;">Spar</th>
          <th style="text-align:center;padding:6px 4px;color:#854F0B;font-weight:500;">იაფი</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($price_rows as $pr):
          $cheapest = getMinPrice($pr);
        ?>
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:6px 8px;">
            <?php echo sanitize($pr['name_ka']); ?>
            <span style="color:var(--gray-400);font-size:11px;"> / <?php echo sanitize($pr['unit']); ?></span>
            <?php if ($pr['ai_estimated']): ?>
              <span style="font-size:10px;color:#854F0B;background:#FAEEDA;padding:1px 5px;border-radius:4px;margin-left:4px;">AI</span>
            <?php endif; ?>
          </td>
          <?php
          $store_cols = array('agrohub_price','nabiji_price','carrefour_price','goodwill_price','spar_price');
          foreach ($store_cols as $col):
            $val = $pr[$col];
            $is_cheapest = ($val !== null && (float)$val === (float)$cheapest['price']);
          ?>
          <td style="text-align:center;padding:6px 4px;<?php echo $is_cheapest ? 'background:#E1F5EE;font-weight:500;color:#0F6E56;border-radius:4px;' : ''; ?>">
            <?php echo $val !== null ? number_format((float)$val, 2).' ₾' : '—'; ?>
          </td>
          <?php endforeach; ?>
          <td style="text-align:center;padding:6px 4px;font-weight:500;color:#854F0B;">
            <?php echo $cheapest['store']; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
    $oldest = 0;
    foreach ($price_rows as $pr) { if ($pr['updated_at'] > $oldest) $oldest = $pr['updated_at']; }
    $ai_count = 0;
    foreach ($price_rows as $pr) { if ($pr['ai_estimated']) $ai_count++; }
  ?>
  <div style="font-size:11px;color:var(--gray-400);margin-top:.75rem;display:flex;justify-content:space-between;flex-wrap:wrap;gap:4px;">
    <span><?php echo $ai_count > 0 ? $ai_count . ' ფასი AI-ის შეფასებაა' : 'ყველა ფასი ხელით შეყვანილია'; ?></span>
    <?php if ($oldest > 0): ?>
      <span>ბოლო განახლება: <?php echo date('d/m/Y H:i', $oldest); ?></span>
    <?php endif; ?>
  </div>
</div>

<div style="text-align:center;margin-top:1.5rem;">
  <a href="/history.php" class="btn btn-outline">&#8592; ისტორიაში დაბრუნება</a>
</div>

<!-- Recipe modal -->
<div id="recipe-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--bg-card-s);border-radius:var(--r-xl);padding:1.5rem;max-width:520px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div>
        <h3 style="font-size:17px;font-weight:700;color:var(--t1);margin:0;" id="recipe-title">რეცეპტი</h3>
        <div style="font-size:12px;color:var(--t3);margin-top:2px;" id="recipe-subtitle"></div>
      </div>
      <button onclick="closeRecipe()" style="width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.07);border:none;cursor:pointer;font-size:14px;color:var(--t3);display:flex;align-items:center;justify-content:center;">✕</button>
    </div>
    <div id="recipe-loading" style="text-align:center;padding:2rem;">
      <div style="width:32px;height:32px;border:3px solid var(--green-soft);border-top-color:var(--green);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 1rem;"></div>
      <div style="font-size:14px;color:var(--t3);">AI ამზადებს რეცეპტს...</div>
    </div>
    <div id="recipe-content" style="display:none;"></div>
    <div id="recipe-error" style="display:none;" class="alert alert-error"></div>
  </div>
</div>

<!-- Swap meal modal -->
<div id="swap-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--bg-card-s);border-radius:16px;padding:1.5rem;max-width:480px;width:100%;max-height:85vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="font-size:16px;font-weight:500;margin:0;">ალტერნატიული კვება</h3>
      <button onclick="closeSwap()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--gray-400);">✕</button>
    </div>
    <div id="swap-loading" style="text-align:center;padding:2rem;">
      <div style="width:32px;height:32px;border:3px solid #E1F5EE;border-top-color:#1D9E75;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 1rem;"></div>
      <div style="font-size:14px;color:var(--gray-400);">AI ეძებს ალტერნატივებს...</div>
    </div>
    <div id="swap-results" style="display:none;"></div>
    <div id="swap-error" style="display:none;" class="alert alert-error"></div>
  </div>
</div>

<script>
var swapProfile = {
  goal:      '<?php echo addslashes($profile ? $profile['goal'] : 'Weight Loss'); ?>',
  budget:    '<?php echo addslashes($profile ? $profile['budget'] : 'Medium'); ?>',
  allergies: '<?php echo addslashes($profile ? $profile['allergies'] : 'none'); ?>'
};

function swapMeal(name, type, calories) {
  document.getElementById('swap-modal').style.display = 'flex';
  document.getElementById('swap-loading').style.display = 'block';
  document.getElementById('swap-results').style.display = 'none';
  document.getElementById('swap-error').style.display = 'none';

  fetch('/api/swap_meal.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      meal_name: name,
      meal_type: type,
      calories:  calories,
      goal:      swapProfile.goal,
      budget:    swapProfile.budget,
      allergies: swapProfile.allergies
    })
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    document.getElementById('swap-loading').style.display = 'none';
    if (data.error) {
      document.getElementById('swap-error').textContent = data.error;
      document.getElementById('swap-error').style.display = 'block';
      return;
    }
    var html = '<div style="font-size:12px;color:var(--gray-400);margin-bottom:1rem;">3 ალტერნატივა "'+esc(name)+'" კვებისთვის:</div>';
    data.alternatives.forEach(function(a, i) {
      html += '<div style="background:var(--bg);border-radius:10px;padding:12px;margin-bottom:8px;">';
      html += '<div style="font-weight:500;font-size:14px;margin-bottom:4px;">'+esc(a.name)+'</div>';
      html += '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">';
      html += '<span style="font-size:11px;background:#F1EFE8;border-radius:4px;padding:2px 6px;">'+a.calories+' კკ</span>';
      if (a.protein_g) html += '<span style="font-size:11px;background:#E1F5EE;color:#0F6E56;border-radius:4px;padding:2px 6px;">ც '+a.protein_g+'გ</span>';
      if (a.carbs_g)   html += '<span style="font-size:11px;background:#FAEEDA;color:#854F0B;border-radius:4px;padding:2px 6px;">ნ '+a.carbs_g+'გ</span>';
      if (a.fat_g)     html += '<span style="font-size:11px;background:#FAECE7;color:#993C1D;border-radius:4px;padding:2px 6px;">ჩ '+a.fat_g+'გ</span>';
      if (a.cost_gel)  html += '<span style="font-size:11px;color:#1D9E75;font-weight:500;">~'+parseFloat(a.cost_gel).toFixed(2)+'₾ '+esc(a.best_store||'')+'</span>';
      html += '</div>';
      if (a.ingredients) html += '<div style="font-size:12px;color:var(--gray-400);">'+esc(a.ingredients)+'</div>';
      if (a.reason) html += '<div style="font-size:11px;color:#0F6E56;margin-top:4px;">💡 '+esc(a.reason)+'</div>';
      html += '</div>';
    });
    document.getElementById('swap-results').innerHTML = html;
    document.getElementById('swap-results').style.display = 'block';
  })
  .catch(function(e) {
    document.getElementById('swap-loading').style.display = 'none';
    document.getElementById('swap-error').textContent = 'შეცდომა: ' + e.message;
    document.getElementById('swap-error').style.display = 'block';
  });
}

function closeSwap() {
  document.getElementById('swap-modal').style.display = 'none';
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = s || '';
  return d.innerHTML;
}

// ── Recipe modal ──────────────────────────────────────────────────────────────
function showRecipe(mealName, ingredients) {
  document.getElementById('recipe-modal').style.display = 'flex';
  document.getElementById('recipe-loading').style.display = 'block';
  document.getElementById('recipe-content').style.display = 'none';
  document.getElementById('recipe-error').style.display = 'none';
  document.getElementById('recipe-title').textContent = mealName;
  document.getElementById('recipe-subtitle').textContent = ingredients ? ingredients.slice(0, 60) + '...' : '';

  fetch('/api/recipe.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({meal_name: mealName, ingredients: ingredients})
  })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    document.getElementById('recipe-loading').style.display = 'none';
    if (data.error) {
      document.getElementById('recipe-error').textContent = data.error;
      document.getElementById('recipe-error').style.display = 'block';
      return;
    }
    renderRecipe(data);
  })
  .catch(function(e) {
    document.getElementById('recipe-loading').style.display = 'none';
    document.getElementById('recipe-error').textContent = 'შეცდომა: ' + e.message;
    document.getElementById('recipe-error').style.display = 'block';
  });
}

function renderRecipe(d) {
  var html = '';

  // Time and difficulty
  if (d.prep_time || d.cook_time) {
    html += '<div style="display:flex;gap:12px;margin-bottom:1rem;flex-wrap:wrap;">';
    if (d.prep_time) html += '<div style="background:var(--green-soft);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:500;color:var(--green-2);">⏱️ მომზ. ' + esc(d.prep_time) + '</div>';
    if (d.cook_time) html += '<div style="background:rgba(245,158,11,.1);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:500;color:#92400E;">🔥 მოხ. ' + esc(d.cook_time) + '</div>';
    if (d.difficulty) html += '<div style="background:rgba(0,0,0,.05);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:500;color:var(--t2);">📊 ' + esc(d.difficulty) + '</div>';
    html += '</div>';
  }

  // Ingredients
  if (d.ingredients && d.ingredients.length) {
    html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--t3);margin-bottom:8px;">ინგრედიენტები</div>';
    html += '<div style="background:var(--bg);border-radius:var(--r-md);padding:10px 14px;margin-bottom:1rem;">';
    d.ingredients.forEach(function(ing) {
      html += '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid var(--border);font-size:13px;color:var(--t1);">'
            + '<span>' + esc(ing.name) + '</span>'
            + '<span style="color:var(--t3);font-weight:500;">' + esc(ing.amount) + '</span>'
            + '</div>';
    });
    html += '</div>';
  }

  // Steps
  if (d.steps && d.steps.length) {
    html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--t3);margin-bottom:8px;">მომზადება</div>';
    d.steps.forEach(function(step, i) {
      html += '<div style="display:flex;gap:12px;margin-bottom:10px;align-items:flex-start;">'
            + '<div style="width:26px;height:26px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">' + (i+1) + '</div>'
            + '<div style="font-size:14px;color:var(--t1);line-height:1.6;padding-top:3px;">' + esc(step) + '</div>'
            + '</div>';
    });
  }

  // Tip
  if (d.tip) {
    html += '<div style="margin-top:1rem;background:rgba(245,158,11,.08);border-radius:var(--r-md);padding:10px 14px;font-size:13px;color:#92400E;">'
          + '💡 <strong>ჰაკი:</strong> ' + esc(d.tip) + '</div>';
  }

  document.getElementById('recipe-content').innerHTML = html;
  document.getElementById('recipe-content').style.display = 'block';
}

function closeRecipe() {
  document.getElementById('recipe-modal').style.display = 'none';
}
</script>


<?php renderFooter(); ?>