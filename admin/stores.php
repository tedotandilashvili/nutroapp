<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Add store ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_store'])) {
    $name  = trim(isset($_POST['name'])  ? $_POST['name']  : '');
    $slug  = trim(isset($_POST['slug'])  ? $_POST['slug']  : '');
    $url   = trim(isset($_POST['url'])   ? $_POST['url']   : '');
    $slug  = preg_replace('/[^a-z0-9_]/', '_', strtolower($slug));
    if ($name && $slug) {
        $cnt = (int)$db->query('SELECT COUNT(*) FROM stores')->fetchColumn();
        $db->prepare('INSERT IGNORE INTO stores (slug,name,url,is_active,sort_order,created_at) VALUES (?,?,?,1,?,?)')
           ->execute(array($slug, $name, $url, $cnt+1, time()));
        setFlash('success', 'მაღაზია დაემატა: ' . htmlspecialchars($name));
    } else {
        setFlash('error', 'სახელი და slug სავალდებულოა.');
    }
    header('Location: /admin/stores.php'); exit;
}

// ── Toggle active ──────────────────────────────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $db->prepare('UPDATE stores SET is_active = 1 - is_active WHERE id=?')
       ->execute(array((int)$_GET['toggle']));
    header('Location: /admin/stores.php'); exit;
}

// ── Delete store ───────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare('DELETE FROM stores WHERE id=?')->execute(array((int)$_GET['delete']));
    setFlash('success', 'მაღაზია წაიშალა.');
    header('Location: /admin/stores.php'); exit;
}

// ── Save prices for a store ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prices'])) {
    $store_id = (int)$_POST['store_id'];
    $prices   = isset($_POST['prices']) ? $_POST['prices'] : array();
    foreach ($prices as $ing_id => $price) {
        $ing_id = (int)$ing_id;
        $price  = trim($price);
        if ($price === '' || $price === null) {
            $db->prepare('DELETE FROM ingredient_store_prices WHERE ingredient_id=? AND store_id=?')
               ->execute(array($ing_id, $store_id));
        } else {
            $db->prepare(
                'INSERT INTO ingredient_store_prices (ingredient_id,store_id,price,ai_estimated,updated_at)
                 VALUES (?,?,?,0,?) ON DUPLICATE KEY UPDATE price=VALUES(price),ai_estimated=0,updated_at=VALUES(updated_at)'
            )->execute(array($ing_id, $store_id, (float)$price, time()));
        }
    }
    setFlash('success', 'ფასები შენახულია.');
    header('Location: /admin/stores.php?edit=' . $store_id); exit;
}

$stores      = $db->query('SELECT * FROM stores ORDER BY sort_order')->fetchAll();
$ingredients = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka')->fetchAll();

// Load prices for editing
$edit_store_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_prices   = array();
if ($edit_store_id) {
    $stmt = $db->prepare('SELECT ingredient_id, price FROM ingredient_store_prices WHERE store_id=?');
    $stmt->execute(array($edit_store_id));
    foreach ($stmt->fetchAll() as $r) {
        $edit_prices[$r['ingredient_id']] = $r['price'];
    }
}

renderAdminHeader('მაღაზიები', 'stores');
?>

<div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:18px;font-weight:500;margin:0 0 4px;">მაღაზიების მართვა</h1>
    <p style="font-size:13px;color:#888780;margin:0;">სულ <?php echo count($stores); ?> მაღაზია</p>
  </div>
  <button onclick="document.getElementById('add-form').style.display='block';this.style.display='none';"
          class="adm-btn adm-btn-primary">+ ახალი მაღაზია</button>
</div>

<!-- Add store form -->
<div id="add-form" class="adm-card" style="display:none;margin-bottom:1.5rem;">
  <div class="adm-card-head"><span class="adm-card-title">ახალი მაღაზიის დამატება</span></div>
  <div style="padding:1.25rem;">
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label style="font-size:12px;color:#888780;display:block;margin-bottom:4px;">სახელი *</label>
          <input type="text" name="name" class="adm-search" style="width:100%;" placeholder="მაგ. Nikora" required>
        </div>
        <div>
          <label style="font-size:12px;color:#888780;display:block;margin-bottom:4px;">Slug * (EN, lowercase)</label>
          <input type="text" name="slug" class="adm-search" style="width:100%;" placeholder="მაგ. nikora" required>
        </div>
        <div>
          <label style="font-size:12px;color:#888780;display:block;margin-bottom:4px;">URL</label>
          <input type="text" name="url" class="adm-search" style="width:100%;" placeholder="https://nikora.ge">
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" name="add_store" class="adm-btn adm-btn-primary">დამატება</button>
        <button type="button" onclick="document.getElementById('add-form').style.display='none';" class="adm-btn adm-btn-outline">გაუქმება</button>
      </div>
    </form>
  </div>
</div>

<!-- Stores list -->
<div class="adm-card" style="margin-bottom:1.5rem;">
  <table class="adm-table">
    <thead>
      <tr><th>#</th><th>მაღაზია</th><th>Slug</th><th>URL</th><th>სტატუსი</th><th>მოქმ.</th></tr>
    </thead>
    <tbody>
      <?php foreach ($stores as $s): ?>
      <tr>
        <td style="color:#888780;font-size:12px;"><?php echo $s['sort_order']; ?></td>
        <td style="font-weight:500;"><?php echo sanitize($s['name']); ?></td>
        <td style="font-size:12px;color:#888780;font-family:monospace;"><?php echo sanitize($s['slug']); ?></td>
        <td style="font-size:12px;">
          <?php if ($s['url']): ?>
            <a href="<?php echo sanitize($s['url']); ?>" target="_blank" style="color:#1D9E75;text-decoration:none;">
              <?php echo sanitize($s['url']); ?>
            </a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <span class="adm-badge <?php echo $s['is_active'] ? 'badge-green' : 'badge-gray'; ?>">
            <?php echo $s['is_active'] ? 'აქტიური' : 'გამორთული'; ?>
          </span>
        </td>
        <td>
          <div style="display:flex;gap:6px;">
            <a href="/admin/stores.php?edit=<?php echo $s['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-outline">ფასები</a>
            <a href="/admin/stores.php?toggle=<?php echo $s['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-outline">
              <?php echo $s['is_active'] ? 'გამორთვა' : 'ჩართვა'; ?>
            </a>
            <a href="/admin/stores.php?delete=<?php echo $s['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-danger"
               onclick="return confirm('მაღაზია და მისი ყველა ფასი წაიშლება. დარწმუნებული ხართ?')">წაშლა</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Edit prices for selected store -->
<?php if ($edit_store_id):
  $edit_store = null;
  foreach ($stores as $s) { if ($s['id'] == $edit_store_id) { $edit_store = $s; break; } }
  if ($edit_store):
?>
<div class="adm-card">
  <div class="adm-card-head">
    <span class="adm-card-title">
      <?php echo sanitize($edit_store['name']); ?> — ფასების რედაქტირება
    </span>
    <span style="font-size:12px;color:#888780;"><?php echo count($ingredients); ?> პროდუქტი</span>
  </div>
  <div style="padding:1.25rem;">
    <form method="POST">
      <input type="hidden" name="store_id" value="<?php echo $edit_store_id; ?>">
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="border-bottom:2px solid #E8E6DF;">
              <th style="text-align:left;padding:8px;min-width:180px;">პროდუქტი</th>
              <th style="text-align:left;padding:8px;min-width:80px;">ერთეული</th>
              <th style="text-align:left;padding:8px;min-width:120px;">ფასი ₾</th>
              <th style="text-align:left;padding:8px;">სტატუსი</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingredients as $ing):
              $price    = isset($edit_prices[$ing['id']]) ? $edit_prices[$ing['id']] : '';
              $has_price = $price !== '';
            ?>
            <tr style="border-bottom:1px solid #F1EFE8;">
              <td style="padding:6px 8px;">
                <div style="font-weight:500;"><?php echo sanitize($ing['name_ka']); ?></div>
                <div style="font-size:11px;color:#888780;"><?php echo sanitize($ing['name_en']); ?></div>
              </td>
              <td style="padding:6px 8px;color:#888780;font-size:12px;"><?php echo sanitize($ing['unit']); ?></td>
              <td style="padding:6px 8px;">
                <input type="number" step="0.01" min="0"
                       name="prices[<?php echo $ing['id']; ?>]"
                       value="<?php echo $has_price ? number_format((float)$price, 2, '.', '') : ''; ?>"
                       placeholder="—"
                       style="width:90px;border:1px solid <?php echo $has_price ? '#1D9E75' : '#E8E6DF'; ?>;border-radius:6px;padding:5px 8px;font-size:13px;text-align:center;background:<?php echo $has_price ? '#E1F5EE' : 'transparent'; ?>;"
                       oninput="this.style.borderColor=this.value?'#1D9E75':'#E8E6DF';this.style.background=this.value?'#E1F5EE':'transparent';">
              </td>
              <td style="padding:6px 8px;">
                <?php if ($has_price): ?>
                  <span class="adm-badge badge-green">შეყვანილია</span>
                <?php else: ?>
                  <span class="adm-badge badge-gray">ცარიელი</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;flex-wrap:wrap;gap:1rem;">
        <a href="/admin/stores.php" class="adm-btn adm-btn-outline">&#8592; უკან</a>
        <button type="submit" name="save_prices" class="adm-btn adm-btn-primary" style="padding:10px 28px;">
          ფასების შენახვა
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; endif; ?>

<?php renderAdminFooter(); ?>