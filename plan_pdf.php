<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/claude.php';
requireLogin();

$plan_id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$plan_id) die('No plan');

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM diet_plans WHERE id=? AND user_id=?');
$stmt->execute(array($plan_id, $_SESSION['user_id']));
$plan = $stmt->fetch();
if (!$plan) die('Not found');

$stmt = $db->prepare('SELECT * FROM plan_days WHERE plan_id=? ORDER BY day_number');
$stmt->execute(array($plan_id));
$days = $stmt->fetchAll();
foreach ($days as &$day) {
    $s2 = $db->prepare('SELECT * FROM plan_meals WHERE day_id=?');
    $s2->execute(array($day['id']));
    $day['meals'] = $s2->fetchAll();
}
unset($day);

$raw  = json_decode($plan['raw_json'], true);
$user = getCurrentUser();
$profile = getUserProfile($user['id']);
$day_names_ka = array('','პირველი','მეორე','მესამე','მეოთხე','მეხუთე','მეექვსე','მეშვიდე');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<title>კვების გეგმა — <?php echo htmlspecialchars($plan['title']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;font-size:12px;color:#1A1A18;background:#fff;padding:16mm;}
@media print{body{padding:0;} .no-print{display:none;} @page{margin:12mm;size:A4;}}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1D9E75;padding-bottom:12px;margin-bottom:16px;}
.logo{font-family:'DM Serif Display',serif;font-size:22px;color:#1A1A18;}
.logo span{color:#1D9E75;font-style:italic;}
.plan-title{font-size:14px;font-weight:500;margin-bottom:3px;}
.plan-meta{font-size:11px;color:#888780;}
.stats-row{display:flex;gap:12px;margin-bottom:16px;}
.stat{background:#F8F7F2;border-radius:8px;padding:8px 12px;flex:1;text-align:center;}
.stat-val{font-size:16px;font-weight:500;color:#1D9E75;}
.stat-lbl{font-size:10px;color:#888780;margin-top:2px;}
.macro-chips{display:flex;gap:6px;margin-bottom:16px;}
.chip{font-size:10px;padding:3px 8px;border-radius:99px;font-weight:500;}
.chip-p{background:#E1F5EE;color:#0F6E56;} .chip-c{background:#FAEEDA;color:#854F0B;} .chip-f{background:#FAECE7;color:#993C1D;}
.day-block{margin-bottom:14px;page-break-inside:avoid;}
.day-label{font-family:'DM Serif Display',serif;font-size:14px;border-bottom:1px solid #E8E6DF;padding-bottom:4px;margin-bottom:8px;}
table.meals{width:100%;border-collapse:collapse;}
table.meals th{text-align:left;font-size:10px;color:#888780;font-weight:500;text-transform:uppercase;letter-spacing:.05em;padding:0 6px 4px;}
table.meals td{padding:5px 6px;border-bottom:1px solid #F8F7F2;font-size:11px;vertical-align:top;}
table.meals tr:last-child td{border-bottom:none;}
.meal-name{font-weight:500;font-size:12px;}
.total-row{background:#E1F5EE;border-radius:6px;padding:6px 10px;display:flex;justify-content:space-between;margin-top:6px;}
.shopping-section{margin-top:18px;page-break-before:always;}
.shop-store{margin-bottom:12px;}
.shop-store-name{font-weight:500;font-size:12px;background:#F8F7F2;padding:5px 10px;border-radius:6px 6px 0 0;}
.shop-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3px;border:1px solid #E8E6DF;border-top:none;border-radius:0 0 6px 6px;padding:8px;}
.shop-item{display:flex;align-items:center;gap:6px;font-size:11px;}
.shop-box{width:12px;height:12px;border:1.5px solid #888780;border-radius:3px;flex-shrink:0;}
.print-btn{position:fixed;bottom:24px;right:24px;background:#1D9E75;color:#fff;border:none;border-radius:99px;padding:12px 24px;font-size:14px;font-family:'DM Sans',sans-serif;cursor:pointer;box-shadow:0 4px 16px rgba(29,158,117,.4);}
.watermark{font-size:10px;color:#B4B2A9;text-align:center;margin-top:16px;padding-top:10px;border-top:1px solid #F1EFE8;}
</style>
</head>
<body>

<div class="header">
  <div class="logo">Nutro<span>App</span></div>
  <div style="text-align:right;">
    <div class="plan-title"><?php echo htmlspecialchars($plan['title']); ?></div>
    <div class="plan-meta">
      <?php echo htmlspecialchars($user['name']); ?> &middot;
      <?php echo date('d/m/Y', $plan['created_at']); ?>
      <?php if ($profile): ?>
        &middot; <?php echo $profile['weight_kg']; ?>კგ &middot; <?php echo $profile['age']; ?>წ.
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="stats-row">
  <div class="stat"><div class="stat-val"><?php echo $plan['target_calories']; ?></div><div class="stat-lbl">კკალ/დღეში</div></div>
  <div class="stat"><div class="stat-val"><?php echo $plan['days']; ?></div><div class="stat-lbl">დღე</div></div>
  <div class="stat"><div class="stat-val"><?php echo $plan['tdee']; ?></div><div class="stat-lbl">TDEE</div></div>
  <?php if (isset($raw['estimated_daily_cost_gel'])): ?>
  <div class="stat"><div class="stat-val"><?php echo number_format($raw['estimated_daily_cost_gel'],2); ?>₾</div><div class="stat-lbl">სავ./დღეში</div></div>
  <?php endif; ?>
</div>

<div class="macro-chips">
  <span class="chip chip-p">ცილა: <?php echo $plan['protein_g']; ?>გ</span>
  <span class="chip chip-c">ნახ: <?php echo $plan['carbs_g']; ?>გ</span>
  <span class="chip chip-f">ცხიმი: <?php echo $plan['fat_g']; ?>გ</span>
  <?php if ($profile && $profile['goal']): ?>
    <span class="chip" style="background:#F1EFE8;color:#444441;"><?php
      $gl = array('Weight Loss'=>'წონის დაკლება','Muscle Gain'=>'კუნთის მომატება','Maintenance'=>'შენარჩუნება');
      echo isset($gl[$profile['goal']]) ? $gl[$profile['goal']] : $profile['goal'];
    ?></span>
  <?php endif; ?>
</div>

<?php foreach ($days as $di => $day): ?>
<div class="day-block">
  <div class="day-label">
    <?php echo isset($day_names_ka[$day['day_number']]) ? $day_names_ka[$day['day_number']] : ('დღე '.$day['day_number']); ?> დღე
  </div>
  <table class="meals">
    <thead><tr><th style="width:80px;">კვება</th><th>სახელი</th><th>ინგრ.</th><th style="width:55px;">პორცია</th><th style="width:50px;text-align:right;">კკალ</th></tr></thead>
    <tbody>
    <?php foreach ($day['meals'] as $meal): ?>
    <tr>
      <td style="color:#888780;"><?php echo sanitize($meal['meal_type']); ?></td>
      <td><div class="meal-name"><?php echo sanitize($meal['name']); ?></div></td>
      <td style="color:#888780;"><?php echo sanitize($meal['ingredients']); ?></td>
      <td><?php echo sanitize($meal['portion']); ?></td>
      <td style="text-align:right;font-weight:500;color:#1D9E75;"><?php echo $meal['calories']; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total-row">
    <span style="font-size:11px;color:#0F6E56;">სულ <?php echo isset($day_names_ka[$day['day_number']]) ? $day_names_ka[$day['day_number']] : '' ; ?> დღე</span>
    <span style="font-weight:500;color:#0F6E56;"><?php echo $day['total_calories']; ?> კკალ</span>
  </div>
</div>
<?php endforeach; ?>

<!-- Shopping list -->
<?php
$shopping = array();
if ($raw && isset($raw['days'])) {
    foreach ($raw['days'] as $rday) {
        foreach ($rday['meals'] as $rmeal) {
            $store = !empty($rmeal['best_store']) ? $rmeal['best_store'] : 'სხვა';
            $ingrs = explode(',', $rmeal['ingredients']);
            foreach ($ingrs as $ingr) {
                $ingr = trim($ingr);
                if ($ingr) $shopping[$store][$ingr] = true;
            }
        }
    }
}
?>
<?php if (!empty($shopping)): ?>
<div class="shopping-section">
  <div style="font-family:'DM Serif Display',serif;font-size:16px;margin-bottom:12px;border-bottom:2px solid #1D9E75;padding-bottom:6px;">
    სასყიდლო სია
  </div>
  <?php foreach ($shopping as $store => $items): ?>
  <div class="shop-store">
    <div class="shop-store-name">🏪 <?php echo sanitize($store); ?></div>
    <div class="shop-grid">
      <?php foreach (array_keys($items) as $item): ?>
        <div class="shop-item"><div class="shop-box"></div><?php echo sanitize($item); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="watermark">nutroapp.ge &middot; პერსონალური კვების გეგმა &middot; <?php echo date('d/m/Y'); ?></div>

<button class="print-btn no-print" onclick="window.print()">🖨️ დაბეჭდე / PDF</button>

</body></html>
