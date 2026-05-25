<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Handle bulk save ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prices'])) {
    $ids = isset($_POST['ids']) ? $_POST['ids'] : array();
    // Get active stores from DB
    $active_stores = $db->query('SELECT id, slug FROM stores WHERE is_active=1')->fetchAll();
    foreach ($ids as $id) {
        $id      = (int)$id;
        $notes   = isset($_POST['notes'][$id])   ? trim($_POST['notes'][$id])   : '';
        $name_ka = isset($_POST['name_ka'][$id]) ? trim($_POST['name_ka'][$id]) : '';
        // Update name/notes
        $db->prepare('UPDATE ingredient_prices SET name_ka=?, notes=?, updated_at=?, updated_by=? WHERE id=?')
           ->execute(array($name_ka, $notes, time(), $_SESSION['admin_id'], $id));
        // Save prices per store
        foreach ($active_stores as $store) {
            $slug  = $store['slug'];
            $price = (isset($_POST[$slug][$id]) && $_POST[$slug][$id] !== '') ? (float)$_POST[$slug][$id] : null;
            if ($price !== null) {
                $db->prepare(
                    'INSERT INTO ingredient_store_prices (ingredient_id,store_id,price,ai_estimated,updated_at)
                     VALUES (?,?,?,0,?) ON DUPLICATE KEY UPDATE price=VALUES(price),ai_estimated=0,updated_at=VALUES(updated_at)'
                )->execute(array($id, (int)$store['id'], $price, time()));
            } else {
                $db->prepare('DELETE FROM ingredient_store_prices WHERE ingredient_id=? AND store_id=?')
                   ->execute(array($id, (int)$store['id']));
            }
        }
    }
    setFlash('success', 'ფასები წარმატებით განახლდა! (' . count($ids) . ' პროდუქტი)');
    header('Location: /admin/prices.php');
    exit;
}

// ── Handle add new ingredient ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ingredient'])) {
    $key     = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim(isset($_POST['ingredient_key']) ? $_POST['ingredient_key'] : '')));
    $name_ka = trim(isset($_POST['name_ka']) ? $_POST['name_ka'] : '');
    $name_en = trim(isset($_POST['name_en']) ? $_POST['name_en'] : '');
    $unit    = trim(isset($_POST['unit'])    ? $_POST['unit']    : 'კგ');
    if ($key && $name_ka && $name_en) {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO ingredient_prices (ingredient_key,name_ka,name_en,unit,ai_estimated,updated_at,updated_by)
             VALUES (?,?,?,?,1,?,?)'
        );
        $stmt->execute(array($key, $name_ka, $name_en, $unit, time(), $_SESSION['admin_id']));
        setFlash('success', 'პროდუქტი დაემატა: ' . htmlspecialchars($name_ka));
    } else {
        setFlash('error', 'გთხოვთ შეავსოთ ყველა სავალდებულო ველი.');
    }
    header('Location: /admin/prices.php');
    exit;
}

// ── Handle save keywords ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_keywords'])) {
    $ids = isset($_POST['ing_id']) ? $_POST['ing_id'] : array();
    foreach ($ids as $id) {
        $id = (int)$id;
        $kw = trim(isset($_POST['keywords'][$id]) ? $_POST['keywords'][$id] : '');
        $db->prepare('UPDATE ingredient_prices SET search_keywords=? WHERE id=?')
           ->execute(array($kw ?: null, $id));
    }
    setFlash('success', 'Keywords შენახულია!');
    header('Location: /admin/prices.php'); exit;
}

// ── Handle delete ingredient ──────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Delete store prices first (FK)
    $db->prepare('DELETE FROM ingredient_store_prices WHERE ingredient_id=?')->execute(array($del_id));
    // Delete ingredient
    $db->prepare('DELETE FROM ingredient_prices WHERE id=?')->execute(array($del_id));
    setFlash('success', 'პროდუქტი წაიშალა.');
    header('Location: /admin/prices.php');
    exit;
}

$stmt  = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka');
$items = $stmt->fetchAll();

// Load active stores dynamically
$active_stores = $db->query('SELECT * FROM stores WHERE is_active=1 ORDER BY sort_order')->fetchAll();

// Load all prices from ingredient_store_prices
$all_prices = array();
$isp = $db->query('SELECT ingredient_id, store_id, price, ai_estimated FROM ingredient_store_prices')->fetchAll();
foreach ($isp as $r) {
    $all_prices[$r['ingredient_id']][$r['store_id']] = array('price'=>$r['price'],'ai'=>$r['ai_estimated']);
}

$total        = count($items);
$ai_count     = 0;
$last_updated = 0;
foreach ($items as $i) {
    if ($i['ai_estimated']) $ai_count++;
    if ($i['updated_at'] > $last_updated) $last_updated = $i['updated_at'];
}
$manual_count = $total - $ai_count;

renderAdminHeader('ფასების მართვა', 'prices');
?>

<div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:22px;font-weight:500;margin-bottom:4px;">ფასების მართვა</h1>
    <p style="font-size:13px;color:var(--gray-400);">
      სულ <?php echo $total; ?> პროდუქტი &middot;
      <span style="color:#0F6E56;"><?php echo $manual_count; ?> ხელით შეყვანილი</span> &middot;
      <span style="color:#854F0B;"><?php echo $ai_count; ?> AI შეფასება</span>
      <?php if ($last_updated > 0): ?>
        &middot; ბოლო განახლება: <?php echo date('d/m/Y H:i', $last_updated); ?>
      <?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button id="ai-refresh-btn" onclick="aiRefreshPrices()"
            class="btn btn-outline"
            style="font-size:13px;border-color:#1D9E75;color:#1D9E75;">
      AI-ით განახლება &#x27F3;
    </button>
    <button onclick="document.getElementById('add-form').style.display='block';this.style.display='none';"
            class="btn btn-outline" style="font-size:13px;">+ ახალი პროდუქტი</button>
  </div>
</div>

<!-- AI refresh status bar -->
<div id="ai-status" style="display:none;margin-bottom:1rem;">
  <div class="card" style="padding:1rem;border-color:#1D9E75;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div id="ai-spinner" style="width:18px;height:18px;border:2px solid #E1F5EE;border-top-color:#1D9E75;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0;"></div>
      <div>
        <div style="font-weight:500;font-size:14px;color:#0F6E56;" id="ai-status-text">Claude AI ამოწმებს ბაზრის ფასებს...</div>
        <div style="font-size:12px;color:var(--gray-400);margin-top:2px;">ეს შეიძლება 15-30 წამი გაგრძელდეს</div>
      </div>
    </div>
    <div style="margin-top:10px;height:3px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
      <div id="ai-progress" style="height:100%;background:#1D9E75;width:0;border-radius:99px;transition:width .3s;"></div>
    </div>
  </div>
</div>

<!-- Add ingredient form -->
<div id="add-form" class="card" style="display:none;margin-bottom:1.5rem;">
  <div class="card-title">ახალი პროდუქტის დამატება</div>
  <form method="POST">
    <div class="grid-3" style="gap:12px;margin-bottom:12px;">
      <div class="form-group" style="margin:0;">
        <label>გასაღები (slug)</label>
        <input type="text" name="ingredient_key" class="form-control" placeholder="chicken_breast" required>
      </div>
      <div class="form-group" style="margin:0;">
        <label>სახელი (ქართ.)</label>
        <input type="text" name="name_ka" class="form-control" placeholder="ქათმის მკერდი" required>
      </div>
      <div class="form-group" style="margin:0;">
        <label>სახელი (EN)</label>
        <input type="text" name="name_en" class="form-control" placeholder="Chicken breast" required>
      </div>
    </div>
    <div style="display:flex;gap:12px;align-items:flex-end;">
      <div class="form-group" style="margin:0;">
        <label>ერთეული</label>
        <input type="text" name="unit" class="form-control" placeholder="კგ" style="width:80px;" value="კგ">
      </div>
      <button type="submit" name="add_ingredient" class="btn btn-primary">დამატება</button>
      <button type="button" onclick="document.getElementById('add-form').style.display='none';" class="btn btn-outline">გაუქმება</button>
    </div>
  </form>
</div>

<!-- Price table form -->
<form method="POST" id="prices-form">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;" id="price-table">
      <thead>
        <tr style="border-bottom:2px solid var(--gray-200);">
          <th style="text-align:left;padding:8px;min-width:160px;">პროდუქტი</th>
          <th style="text-align:left;padding:8px;min-width:180px;">Keywords</th>
          <?php foreach ($active_stores as $s): ?>
          <th style="text-align:center;padding:8px;min-width:90px;color:#1D9E75;">
            <?php echo sanitize($s['name']); ?> ₾
          </th>
          <?php endforeach; ?>
          <th style="text-align:center;padding:8px;min-width:60px;color:#854F0B;">იაფი</th>
          <th style="text-align:left;padding:8px;min-width:110px;">შენიშვნა</th>
          <th style="text-align:center;padding:8px;">განახლ.</th>
          <th style="text-align:center;padding:8px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item):
          $cheapest  = null;
          $min_store = '';
          $store_prices = array(
            'Agrohub'   => $item['agrohub_price'],
            '2Nabiji'   => $item['nabiji_price'],
            'Carrefour' => $item['carrefour_price'],
            'Goodwill'  => $item['goodwill_price'],
            'Spar'      => $item['spar_price'],
          );
          foreach ($store_prices as $s => $p) {
              if ($p !== null && ($cheapest === null || (float)$p < (float)$cheapest)) {
                  $cheapest  = (float)$p;
                  $min_store = $s;
              }
          }
        ?>
        <tr style="border-bottom:1px solid var(--gray-100);" class="price-row"
            data-key="<?php echo sanitize($item['ingredient_key']); ?>"
            data-id="<?php echo $item['id']; ?>">
          <td style="padding:6px 8px;">
            <input type="hidden" name="ids[]" value="<?php echo $item['id']; ?>">
            <input type="text" name="name_ka[<?php echo $item['id']; ?>]"
                   value="<?php echo sanitize($item['name_ka']); ?>"
                   style="width:100%;border:1px solid var(--gray-200);border-radius:6px;padding:4px 7px;font-size:13px;font-weight:500;margin-bottom:3px;">
            <div style="font-size:11px;color:var(--gray-400);"><?php echo sanitize($item['name_en']); ?> / <?php echo sanitize($item['unit']); ?></div>
            <span class="price-badge <?php echo $item['ai_estimated'] ? 'badge-ai' : 'badge-manual'; ?>"
                  id="badge-<?php echo $item['id']; ?>">
              <?php echo $item['ai_estimated'] ? 'AI' : 'ხელი'; ?>
            </span>
          </td>
          <td style="padding:4px 8px;">
            <input type="text"
                   name="keywords[<?php echo $item['id']; ?>]"
                   value="<?php echo htmlspecialchars($item['search_keywords'] ?: ''); ?>"
                   style="width:100%;border:0.5px solid var(--border-s);border-radius:6px;padding:5px 8px;font-size:12px;font-family:monospace;background:var(--bg);color:var(--t1);outline:none;"
                   placeholder="egg, kvercxi, ...">
            <input type="hidden" name="ing_id[]" value="<?php echo $item['id']; ?>">
          </td>
          <?php
          // Build store prices from new table
          $store_prices = array();
          foreach ($active_stores as $s) {
              $store_prices[$s['id']] = isset($all_prices[$item['id']][$s['id']]) ? (float)$all_prices[$item['id']][$s['id']]['price'] : null;
          }
          $min_val = null;
          foreach ($store_prices as $sp) { if ($sp !== null && ($min_val === null || $sp < $min_val)) $min_val = $sp; }
          foreach ($active_stores as $s):
            $fval = $store_prices[$s['id']];
            $is_cheapest = ($fval !== null && $fval === $min_val);
          ?>
          <td style="text-align:center;padding:4px;">
            <input type="number" step="0.01" min="0"
                   name="<?php echo sanitize($s['slug']); ?>[<?php echo $item['id']; ?>]"
                   value="<?php echo $fval !== null ? number_format($fval, 2, '.', '') : ''; ?>"
                   placeholder="—"
                   style="width:80px;text-align:center;border:1px solid <?php echo $is_cheapest ? '#1D9E75' : 'var(--gray-200)'; ?>;border-radius:6px;padding:5px 4px;font-size:13px;background:<?php echo $is_cheapest ? '#E1F5EE' : 'transparent'; ?>;">
          </td>
          <?php endforeach; ?>
          <?php
          $cheapest_store_name = '—';
          foreach ($active_stores as $s) {
              if ($store_prices[$s['id']] !== null && $store_prices[$s['id']] === $min_val) {
                  $cheapest_store_name = $s['name']; break;
              }
          }
          ?>
          <td style="text-align:center;padding:4px;font-size:12px;font-weight:500;color:#854F0B;"
              id="cheap-<?php echo $item['id']; ?>">
            <?php echo sanitize($cheapest_store_name); ?>
          </td>
          <td style="padding:4px;">
            <input type="text" name="notes[<?php echo $item['id']; ?>]"
                   value="<?php echo sanitize(isset($item['notes']) ? $item['notes'] : ''); ?>"
                   placeholder="შენიშვნა..."
                   style="width:100%;border:1px solid var(--gray-200);border-radius:6px;padding:5px 6px;font-size:12px;">
          </td>
          <td style="text-align:center;padding:4px;font-size:11px;color:var(--gray-400);"
              id="date-<?php echo $item['id']; ?>">
            <?php echo $item['updated_at'] > 0 ? date('d/m/y', $item['updated_at']) : 'AI'; ?>
          </td>
          <td style="text-align:center;padding:4px;">
            <a href="/admin/prices.php?delete=<?php echo $item['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-danger"
               onclick="return confirm('&#39;<?php echo addslashes(htmlspecialchars($item['name_ka'])); ?>&#39; წაიშალოს? შეუქცევადია.')">
              წაშლა
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
      <div style="padding:10px 16px;border-top:0.5px solid var(--border-s);display:flex;justify-content:flex-end;">
        <button type="submit" name="save_keywords" class="adm-btn adm-btn-primary">Keywords შენახვა</button>
      </div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div style="font-size:12px;color:var(--gray-400);">
      ყოლა ფასი ₾-ში, ერთეულზე. ცარიელი = უცნობი.
    </div>
    <button type="submit" name="save_prices" class="btn btn-primary" style="padding:12px 28px;font-size:15px;">
      ყველა ფასის შენახვა
    </button>
  </div>
</form>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes flash-green { 0%,100%{background:transparent;} 50%{background:#E1F5EE;} }
.row-updated { animation: flash-green 1s ease; }
</style>

<script>
// Highlight cheapest on input
document.querySelectorAll('.price-row').forEach(function(row) {
    var inputs = row.querySelectorAll('input[type=number]');
    var cheapCell = row.querySelector('[id^=cheap-]');
    var storeNames = ['Agrohub','2Nabiji','Carrefour','Goodwill','Spar'];
    function highlight() {
        var min = null;
        inputs.forEach(function(inp) {
            var v = parseFloat(inp.value);
            if (!isNaN(v) && (min === null || v < min)) min = v;
        });
        var cheapStore = '—';
        inputs.forEach(function(inp, i) {
            var v = parseFloat(inp.value);
            if (!isNaN(v) && v === min) {
                inp.style.background = '#E1F5EE';
                inp.style.borderColor = '#1D9E75';
                cheapStore = storeNames[i];
            } else {
                inp.style.background = '';
                inp.style.borderColor = 'var(--gray-200)';
            }
        });
        if (cheapCell) cheapCell.textContent = cheapStore;
    }
    inputs.forEach(function(inp) { inp.addEventListener('input', highlight); });
});

// AI Refresh
function aiRefreshPrices() {
    var btn      = document.getElementById('ai-refresh-btn');
    var status   = document.getElementById('ai-status');
    var statusTxt= document.getElementById('ai-status-text');
    var progress = document.getElementById('ai-progress');

    btn.disabled = true;
    btn.textContent = 'AI მუშაობს...';
    status.style.display = 'block';
    status.scrollIntoView({behavior:'smooth', block:'nearest'});

    var pct = 5;
    var timer = setInterval(function() {
        pct = Math.min(85, pct + (pct < 40 ? 2 : 0.4));
        progress.style.width = pct + '%';
    }, 800);

    var messages = [
        'Claude AI ამოწმებს ბაზრის ფასებს...',
        'Agrohub, 2Nabiji, Carrefour-ის ფასები...',
        'Goodwill და Spar-ის ფასები...',
        'ფასების შედარება და შეფასება...',
        'DB-ში შენახვა...'
    ];
    var mi = 0;
    var msgTimer = setInterval(function() {
        mi = (mi + 1) % messages.length;
        statusTxt.textContent = messages[mi];
    }, 5000);

    // Step 1: Create job
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/refresh_prices_async.php', true);
    xhr.timeout = 10000;
    xhr.onload = function() {
        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) {
            clearInterval(msgTimer); clearInterval(timer);
            showAiError('Job error: ' + xhr.responseText.substring(0,100)); return;
        }
        if (data.error) { clearInterval(msgTimer); clearInterval(timer); showAiError(data.error); return; }

        // Step 2: Run job
        var xhr2 = new XMLHttpRequest();
        xhr2.open('POST', '/api/run_price_job.php', true);
        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr2.timeout = 10000;
        xhr2.onload = xhr2.onerror = xhr2.ontimeout = function() {
            // Regardless — start polling
            pollPriceJob(data.job_id, timer, msgTimer);
        };
        xhr2.send('job_id=' + data.job_id);
    };
    xhr.onerror = xhr.ontimeout = function() {
        clearInterval(msgTimer); clearInterval(timer);
        showAiError('კავშირის შეცდომა.');
    };
    xhr.send();
}

function pollPriceJob(jobId, timer, msgTimer) {
    var pollTimer = setInterval(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/api/price_job_status.php?id=' + jobId, true);
        xhr.timeout = 8000;
        xhr.onload = function() {
            var data;
            try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (data.status === 'done') {
                clearInterval(pollTimer); clearInterval(timer); clearInterval(msgTimer);
                document.getElementById('ai-progress').style.width = '100%';
                document.getElementById('ai-status-text').textContent = data.message || 'განახლდა!';
                document.getElementById('ai-spinner').style.animation = 'none';
                document.getElementById('ai-spinner').style.borderColor = '#1D9E75';
                setTimeout(function() { window.location.reload(); }, 1500);
            } else if (data.status === 'error') {
                clearInterval(pollTimer); clearInterval(timer); clearInterval(msgTimer);
                showAiError(data.error || 'შეცდომა.');
            }
        };
        xhr.send();
    }, 3000);
}

function showAiError(msg) {
    var btn    = document.getElementById('ai-refresh-btn');
    var status = document.getElementById('ai-status');
    document.getElementById('ai-status-text').textContent = 'შეცდომა: ' + msg;
    document.getElementById('ai-spinner').style.borderTopColor = '#E24B4A';
    document.getElementById('ai-progress').style.background = '#E24B4A';
    document.getElementById('ai-progress').style.width = '100%';
    btn.disabled = false;
    btn.textContent = 'AI-ით განახლება ↻';
    setTimeout(function() { status.style.display = 'none'; }, 4000);
}
</script>

<?php renderAdminFooter(); ?>