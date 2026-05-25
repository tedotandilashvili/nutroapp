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
        <div class="meal-type"><?php echo sanitize($meal['meal_type']); ?></div>
        <div>
          <div class="meal-name"><?php echo sanitize($meal['name']); ?></div>
          <div class="meal-detail"><?php echo sanitize($meal['ingredients']); ?> &middot; <?php echo sanitize($meal['portion']); ?></div>
          <?php
          // Per-ingredient prices from raw JSON
          $ing_prices = array();
          if (isset($raw['days']) && isset($raw['days'][$di]['meals'][$mi]['ingredient_list'])) {
              $ing_prices = $raw['days'][$di]['meals'][$mi]['ingredient_list'];
          }
          if (!empty($ing_prices)): ?>
          <div style="margin-top:5px;display:flex;flex-wrap:wrap;gap:4px;">
            <?php foreach ($ing_prices as $ip): ?>
            <span style="font-size:11px;background:#F8F7F2;border-radius:6px;padding:2px 7px;">
              <?php echo sanitize($ip['name']); ?>
              <?php if (!empty($ip['amount'])): ?>
                <span style="color:#888780;"><?php echo sanitize($ip['amount']); ?></span>
              <?php endif; ?>
              — <span style="color:#1D9E75;font-weight:500;"><?php echo number_format((float)$ip['price_gel'],2); ?>₾</span>
              <span style="color:#888780;font-size:10px;"><?php echo sanitize($ip['store']); ?></span>
            </span>
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

<?php renderFooter(); ?>