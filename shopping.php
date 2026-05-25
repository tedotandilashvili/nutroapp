<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$plan_id = (int)(isset($_GET['plan_id']) ? $_GET['plan_id'] : 0);
$db      = getDB();

// Get user's recent plans for selector
$plans_stmt = $db->prepare('SELECT id, title, created_at FROM diet_plans WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
$plans_stmt->execute(array($_SESSION['user_id']));
$user_plans = $plans_stmt->fetchAll();

$plan = null;
$raw  = null;
if ($plan_id) {
    $stmt = $db->prepare('SELECT * FROM diet_plans WHERE id=? AND user_id=?');
    $stmt->execute(array($plan_id, $_SESSION['user_id']));
    $plan = $stmt->fetch();
    if ($plan) $raw = json_decode($plan['raw_json'], true);
}

// Build shopping list from raw JSON
$shopping = array(); // store => array(ingredient => array(qty, unit, price))
if ($raw && isset($raw['days'])) {
    $ingredient_map = array(); // name => total portions count
    foreach ($raw['days'] as $day) {
        foreach ($day['meals'] as $meal) {
            $best_store = isset($meal['best_store']) ? $meal['best_store'] : '';

            // New format: ingredient_list array
            if (!empty($meal['ingredient_list']) && is_array($meal['ingredient_list'])) {
                foreach ($meal['ingredient_list'] as $ing) {
                    $name  = isset($ing['name'])  ? trim($ing['name'])  : '';
                    $store = isset($ing['store'])  ? $ing['store']       : $best_store;
                    $price = isset($ing['price_gel']) ? (float)$ing['price_gel'] : 0;
                    $amount= isset($ing['amount']) ? $ing['amount']      : '';
                    if (!$name) continue;
                    if (!isset($ingredient_map[$name])) {
                        $ingredient_map[$name] = array('count'=>0,'store'=>$store,'price'=>$price,'amount'=>$amount);
                    }
                    $ingredient_map[$name]['count']++;
                    if ($store) $ingredient_map[$name]['store'] = $store;
                }
            // Old format: comma-separated string
            } elseif (!empty($meal['ingredients'])) {
                $ingrs = explode(',', $meal['ingredients']);
                foreach ($ingrs as $ingr) {
                    $ingr = trim($ingr);
                    if (!$ingr) continue;
                    if (!isset($ingredient_map[$ingr])) {
                        $ingredient_map[$ingr] = array('count'=>0,'store'=>$best_store,'price'=>0,'amount'=>'');
                    }
                    $ingredient_map[$ingr]['count']++;
                    if ($best_store) $ingredient_map[$ingr]['store'] = $best_store;
                }
            }
        }
    }
    // Group by store
    foreach ($ingredient_map as $name => $data) {
        $store = $data['store'] ?: 'სხვა';
        if (!isset($shopping[$store])) $shopping[$store] = array();
        $shopping[$store][] = array('name'=>$name,'count'=>$data['count'],'price'=>$data['price'],'amount'=>$data['amount']);
    }
}

$store_colors = array(
    'Carrefour'=>array('#E6F1FB','#185FA5'),
    'Goodwill' =>array('var(--green-soft)','var(--green-2)'),
    '2Nabiji'  =>array('#FAEEDA','#854F0B'),
    'Agrohub'  =>array('#F3E8FF','#6B21A8'),
    'Spar'     =>array('#FEF3C7','#92400E'),
    'სხვა'     =>array('#F1EFE8','#444441'),
);

renderHeader('საყიდლების სია', 'shopping');
?>
<style>
.shop-plan-sel{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1rem;}
.shop-plan-btn{padding:7px 14px;border-radius:99px;border:1px solid var(--border-s);font-size:13px;text-decoration:none;color:var(--t2);background:#fff;transition:all .15s;}
.shop-plan-btn:hover,.shop-plan-btn.active{border-color:var(--green);color:var(--green);background:var(--green-soft);}
.store-section{margin-bottom:1.25rem;}
.store-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px 10px 0 0;font-size:13px;font-weight:500;}
.store-items{background:#fff;border:1px solid var(--border-s);border-top:none;border-radius:0 0 10px 10px;}
.shop-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border);font-size:14px;}
.shop-item:last-child{border-bottom:none;}
.shop-check{width:18px;height:18px;border:2px solid var(--border-s);border-radius:4px;flex-shrink:0;cursor:pointer;transition:all .15s;}
.shop-item.checked .shop-check{background:var(--green);border-color:var(--green);}
.shop-item.checked span{text-decoration:line-through;color:var(--t3);}
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;">
  <div>
    <div class="page-title">🛒 საყიდლების სია</div>
    <div class="page-subtitle">გეგმის პროდუქტები მაღაზიების მიხედვით</div>
  </div>
  <?php if ($plan): ?>
    <a href="/plan.php?id=<?php echo $plan_id; ?>" class="btn btn-outline">&#8592; გეგმა</a>
  <?php endif; ?>
</div>

<!-- Plan selector -->
<?php if (!empty($user_plans)): ?>
<div class="card">
  <div class="card-title">გეგმის არჩევა</div>
  <div class="shop-plan-sel">
    <?php foreach ($user_plans as $p): ?>
      <a href="/shopping.php?plan_id=<?php echo $p['id']; ?>"
         class="shop-plan-btn <?php echo $p['id']==$plan_id?'active':''; ?>">
        <?php echo sanitize($p['title']); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$plan): ?>
  <div class="empty-state"><p> აირჩიე გეგმა ზემოთ</p></div>
<?php elseif (empty($shopping)): ?>
  <div class="empty-state"><p>საყიდლების სია ვერ შეიქმნა</p></div>
<?php else: ?>

<div style="display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap;">
  <button onclick="checkAll()" class="btn btn-outline" style="font-size:12px;padding:6px 14px;">ყველა ✓</button>
  <button onclick="uncheckAll()" class="btn btn-outline" style="font-size:12px;padding:6px 14px;">გაუქმება</button>
  <a href="/plan_pdf.php?id=<?php echo $plan_id; ?>" target="_blank" class="btn btn-outline" style="font-size:12px;padding:6px 14px;">📄 PDF</a>
</div>

<?php foreach ($shopping as $store => $items):
  $cols = isset($store_colors[$store]) ? $store_colors[$store] : $store_colors['სხვა'];
?>
<div class="store-section">
  <div class="store-head" style="background:<?php echo $cols[0]; ?>;color:<?php echo $cols[1]; ?>;">
    <span>🏪 <?php echo sanitize($store); ?></span>
    <span style="font-size:12px;opacity:.7;"><?php echo count($items); ?> პროდუქტი</span>
  </div>
  <div class="store-items">
    <?php foreach ($items as $item): ?>
    <div class="shop-item" onclick="toggleItem(this)">
      <div class="shop-check"></div>
      <div style="flex:1;">
        <span><?php echo sanitize($item['name']); ?></span>
        <?php if (!empty($item['amount'])): ?>
          <span style="font-size:12px;color:var(--t3);margin-left:6px;"><?php echo sanitize($item['amount']); ?></span>
        <?php endif; ?>
      </div>
      <div style="text-align:right;">
        <?php if (!empty($item['price']) && $item['price'] > 0): ?>
          <div style="font-size:12px;font-weight:600;color:var(--green);"><?php echo number_format((float)$item['price'],2); ?> ₾</div>
        <?php endif; ?>
        <?php if ($item['count'] > 1): ?>
          <div style="font-size:11px;color:var(--t3);"><?php echo $item['count']; ?>x</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<script>
function toggleItem(el) {
  el.classList.toggle('checked');
  saveState();
}
function checkAll()   { document.querySelectorAll('.shop-item').forEach(function(e){e.classList.add('checked');}); saveState(); }
function uncheckAll() { document.querySelectorAll('.shop-item').forEach(function(e){e.classList.remove('checked');}); saveState(); }
function saveState() {
  var checked = [];
  document.querySelectorAll('.shop-item.checked span').forEach(function(e){ checked.push(e.textContent); });
  try { localStorage.setItem('shop_<?php echo $plan_id; ?>', JSON.stringify(checked)); } catch(ex){}
}
// Restore
try {
  var saved = JSON.parse(localStorage.getItem('shop_<?php echo $plan_id; ?>') || '[]');
  document.querySelectorAll('.shop-item').forEach(function(el) {
    var name = el.querySelector('span').textContent;
    if (saved.indexOf(name) !== -1) el.classList.add('checked');
  });
} catch(ex){}
</script>

<?php endif; ?>
<?php renderFooter(); ?>