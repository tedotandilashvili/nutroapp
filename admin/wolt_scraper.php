<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();
$db = getDB();
$db->exec("SET NAMES utf8mb4");

// Save price from browser scrape
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_price'])) {
    header('Content-Type: application/json');
    $ing_id   = (int)$_POST['ing_id'];
    $store_id = (int)$_POST['store_id'];
    $price    = (float)$_POST['price'];
    $name     = trim($_POST['product_name'] ?? '');

    if ($ing_id && $store_id && $price > 0) {
        $db->prepare(
            'INSERT INTO ingredient_store_prices (ingredient_id,store_id,price,ai_estimated,updated_at)
             VALUES (?,?,?,0,?) ON DUPLICATE KEY UPDATE price=VALUES(price),ai_estimated=0,updated_at=VALUES(updated_at)'
        )->execute(array($ing_id, $store_id, $price, time()));
        echo json_encode(array('ok'=>true));
    } else {
        echo json_encode(array('error'=>'invalid data'));
    }
    exit;
}

// Save batch results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_batch'])) {
    header('Content-Type: application/json');
    $items = json_decode($_POST['items'], true);
    $saved = 0;
    foreach ((array)$items as $item) {
        $ing_id   = (int)($item['ing_id']   ?? 0);
        $store_id = (int)($item['store_id']  ?? 0);
        $price    = (float)($item['price']   ?? 0);
        if ($ing_id && $store_id && $price > 0) {
            $db->prepare(
                'INSERT INTO ingredient_store_prices (ingredient_id,store_id,price,ai_estimated,updated_at)
                 VALUES (?,?,?,0,?) ON DUPLICATE KEY UPDATE price=VALUES(price),ai_estimated=0,updated_at=VALUES(updated_at)'
            )->execute(array($ing_id, $store_id, $price, time()));
            $saved++;
        }
    }
    echo json_encode(array('ok'=>true, 'saved'=>$saved));
    exit;
}

$ings   = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka')->fetchAll();
$stores = $db->query('SELECT * FROM stores WHERE is_active=1 ORDER BY sort_order')->fetchAll();

// Wolt venue IDs for Georgian stores - admin configures these
// We'll auto-detect from search results
// Wolt venues matched dynamically via JS

renderAdminHeader('Wolt Scraper', 'wolt_scraper');
?>
<style>
.w-card{background:#fff;border-radius:12px;border:0.5px solid var(--border-s);padding:1rem 1.25rem;margin-bottom:1rem;}
.w-status{font-size:12px;padding:3px 8px;border-radius:6px;font-weight:500;}
.w-ok{background:rgba(22,163,112,.1);color:#0D8059;}
.w-err{background:rgba(229,57,53,.08);color:#C53030;}
.w-pending{background:rgba(0,0,0,.05);color:#888;}
.result-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:0.5px solid rgba(0,0,0,.06);}
.result-row:last-child{border:none;}
#log{max-height:200px;overflow-y:auto;font-size:11px;font-family:monospace;background:#F5F5F0;border-radius:8px;padding:8px;color:#333;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 style="font-size:18px;font-weight:600;margin:0 0 3px;">🛵 Wolt Price Scraper</h1>
    <p style="font-size:13px;color:#888;margin:0;">Browser-side — Wolt API პირდაპირ შენი browser-იდან</p>
  </div>
  <div style="display:flex;gap:8px;">
    <button onclick="runAll()" class="adm-btn adm-btn-primary" id="run-btn">▶ ყველის სქრეიფი</button>
    <button onclick="clearLog()" class="adm-btn adm-btn-outline">ლოგის გასუფთავება</button>
  </div>
</div>

<!-- Config -->
<div class="w-card">
  <div style="font-size:13px;font-weight:600;margin-bottom:.75rem;">⚙️ Wolt კონფიგურაცია</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div>
      <label style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;">Tbilisi კოორდინატები</label>
      <div style="display:flex;gap:6px;margin-top:4px;">
        <input id="lat" class="adm-search" value="41.6938" style="width:100px;" placeholder="lat">
        <input id="lon" class="adm-search" value="44.8015" style="width:100px;" placeholder="lon">
      </div>
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;">მაღაზიების ფილტრი (სახელის ნაწილი)</label>
      <input id="venue-filter" class="adm-search" style="width:100%;margin-top:4px;"
             value="Goodwill,Smart,Carrefour,Agrohub,Europroduct,Magniti"
             placeholder="Goodwill,Smart,Carrefour">
    </div>
  </div>
</div>

<!-- Progress -->
<div class="w-card" id="progress-card" style="display:none;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
    <span style="font-size:13px;font-weight:500;" id="progress-text">მიმდინარეობს...</span>
    <span style="font-size:12px;color:#888;" id="progress-count">0/0</span>
  </div>
  <div style="height:6px;background:#F1EFE8;border-radius:99px;overflow:hidden;margin-bottom:.75rem;">
    <div id="progress-bar" style="height:100%;background:#16A370;width:0%;border-radius:99px;transition:width .3s;"></div>
  </div>
  <div id="log"></div>
</div>

<!-- Results table -->
<div class="w-card" id="results-card" style="display:none;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
    <span style="font-size:13px;font-weight:600;" id="results-title">შედეგები</span>
    <button onclick="saveAll()" class="adm-btn adm-btn-primary adm-btn-sm" id="save-btn" style="display:none;">
      &#128190; ყველის შენახვა
    </button>
  </div>
  <div id="results-list"></div>
</div>

<!-- Ingredient list with status -->
<div class="w-card">
  <div style="font-size:13px;font-weight:600;margin-bottom:.75rem;">📦 პროდუქტები</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px;" id="ing-grid">
    <?php foreach ($ings as $ing): ?>
    <div style="display:flex;align-items:center;gap:6px;padding:6px 8px;background:#F8F7F2;border-radius:8px;font-size:12px;">
      <span id="dot-<?php echo $ing['id']; ?>" style="width:8px;height:8px;border-radius:50%;background:#DDD;flex-shrink:0;"></span>
      <span><?php echo htmlspecialchars($ing['name_ka']); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
var INGS = <?php echo json_encode(array_values(array_map(function($i){ return array('id'=>(int)$i['id'],'name_ka'=>$i['name_ka'],'name_en'=>$i['name_en'],'keywords'=>$i['search_keywords'] ?: ''); }, $ings)), JSON_UNESCAPED_UNICODE); ?>;
var STORES = <?php echo json_encode(array_values(array_map(function($s){ return array('id'=>(int)$s['id'],'name'=>$s['name'],'slug'=>$s['slug']); }, $stores)), JSON_UNESCAPED_UNICODE); ?>;

var allResults = []; // {ing_id, ing_name, store_id, store_name, price, wolt_name, wolt_venue}

function log(msg) {
  var el = document.getElementById('log');
  el.innerHTML += '<div>' + new Date().toLocaleTimeString() + ' ' + msg + '</div>';
  el.scrollTop = el.scrollHeight;
}

function clearLog() {
  document.getElementById('log').innerHTML = '';
}

function setDot(ingId, color) {
  var d = document.getElementById('dot-' + ingId);
  if (d) d.style.background = color;
}

// Search Wolt for one keyword
function woltSearch(query, lat, lon) {
  var url = '/api/wolt_proxy.php?q=' + encodeURIComponent(query)
          + '&lat=' + encodeURIComponent(lat)
          + '&lon=' + encodeURIComponent(lon);
  return fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.error) throw new Error(data.error);
      return (data.items || []);
    });
}

// Match wolt venue name to our stores
function matchStore(venueName) {
  var filters = document.getElementById('venue-filter').value.split(',').map(function(s){ return s.trim().toLowerCase(); });
  var vl = venueName.toLowerCase();
  for (var i = 0; i < STORES.length; i++) {
    var s = STORES[i];
    if (vl.indexOf(s.name.toLowerCase()) >= 0 || s.name.toLowerCase().indexOf(vl.split(' ')[0]) >= 0) {
      return s;
    }
  }
  // Check filter keywords
  for (var j = 0; j < filters.length; j++) {
    if (filters[j] && vl.indexOf(filters[j]) >= 0) {
      // find matching store
      for (var k = 0; k < STORES.length; k++) {
        if (STORES[k].name.toLowerCase().indexOf(filters[j]) >= 0) return STORES[k];
        if (filters[j].indexOf(STORES[k].slug) >= 0) return STORES[k];
      }
    }
  }
  return null;
}

// Filter venues by our store filter
function isWantedVenue(venueName) {
  var filters = document.getElementById('venue-filter').value.split(',').map(function(s){ return s.trim().toLowerCase(); });
  var vl = venueName.toLowerCase();
  for (var i = 0; i < filters.length; i++) {
    if (filters[i] && vl.indexOf(filters[i]) >= 0) return true;
  }
  return false;
}

var running = false;
var idx = 0;

async function runAll() {
  if (running) return;
  running = true;
  allResults = [];
  idx = 0;

  var lat = document.getElementById('lat').value;
  var lon = document.getElementById('lon').value;

  document.getElementById('progress-card').style.display = 'block';
  document.getElementById('results-card').style.display = 'none';
  document.getElementById('results-list').innerHTML = '';
  document.getElementById('run-btn').disabled = true;
  document.getElementById('run-btn').textContent = '⟳ მიმდინარეობს...';

  for (var i = 0; i < INGS.length; i++) {
    var ing = INGS[i];
    var pct = Math.round((i / INGS.length) * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-count').textContent = (i+1) + '/' + INGS.length;
    document.getElementById('progress-text').textContent = ing.name_ka + ' — ეძებს...';

    // Build search queries
    var queries = [];
    if (ing.keywords) {
      ing.keywords.split(',').forEach(function(k){ var t=k.trim(); if(t) queries.push(t); });
    }
    if (ing.name_en) queries.push(ing.name_en);
    queries.push(ing.name_ka);
    // dedupe
    queries = queries.filter(function(v,i,a){ return a.indexOf(v)===i; }).slice(0,3);

    var found = false;
    var bestByVenue = {};

    for (var q = 0; q < queries.length; q++) {
      try {
        var items = await woltSearch(queries[q], lat, lon);
        items.forEach(function(item) {
          if (!isWantedVenue(item.venue)) return;
          var store = matchStore(item.venue);
          if (!store) return;
          // Keep cheapest per venue
          if (!bestByVenue[item.venue_slug] || item.price < bestByVenue[item.venue_slug].price) {
            bestByVenue[item.venue_slug] = {
              ing_id:     ing.id,
              ing_name:   ing.name_ka,
              store_id:   store.id,
              store_name: store.name,
              price:      item.price,
              wolt_name:  item.name,
              wolt_venue: item.venue
            };
          }
          found = true;
        });
        if (found) break; // found with first working query
      } catch(e) {
        log('⚠️ ' + ing.name_ka + ' (' + queries[q] + '): ' + e.message);
      }
      await sleep(300);
    }

    Object.values(bestByVenue).forEach(function(r) {
      allResults.push(r);
      log('✓ ' + r.ing_name + ' → ' + r.store_name + ': ' + r.price.toFixed(2) + '₾ (' + r.wolt_name + ')');
    });

    if (Object.keys(bestByVenue).length > 0) {
      setDot(ing.id, '#16A370');
    } else {
      setDot(ing.id, '#E53935');
      log('— ' + ing.name_ka + ': ვერ მოიძებნა');
    }

    await sleep(400);
  }

  // Show results
  document.getElementById('progress-bar').style.width = '100%';
  document.getElementById('progress-text').textContent = '✅ დასრულდა! ' + allResults.length + ' ფასი მოიძებნა';
  document.getElementById('run-btn').disabled = false;
  document.getElementById('run-btn').textContent = '▶ ყველის სქრეიფი';
  running = false;

  showResults();
}

function showResults() {
  if (!allResults.length) return;
  document.getElementById('results-card').style.display = 'block';
  document.getElementById('results-title').textContent = 'შედეგები — ' + allResults.length + ' ფასი';
  document.getElementById('save-btn').style.display = 'inline-flex';

  var html = '';
  var current_ing = '';
  allResults.forEach(function(r) {
    if (r.ing_name !== current_ing) {
      if (current_ing) html += '</div>';
      html += '<div style="margin-bottom:.75rem;">';
      html += '<div style="font-size:13px;font-weight:600;color:#111;margin-bottom:4px;">'  + esc(r.ing_name) + '</div>';
      current_ing = r.ing_name;
    }
    html += '<div class="result-row">';
    html += '<span style="font-size:11px;background:rgba(22,163,112,.1);color:#0D8059;padding:2px 7px;border-radius:6px;font-weight:500;">' + esc(r.store_name) + '</span>';
    html += '<span style="font-size:13px;font-weight:600;color:#16A370;">' + r.price.toFixed(2) + ' ₾</span>';
    html += '<span style="font-size:11px;color:#888;">' + esc(r.wolt_name) + ' @ ' + esc(r.wolt_venue) + '</span>';
    html += '</div>';
  });
  if (current_ing) html += '</div>';
  document.getElementById('results-list').innerHTML = html;
}

async function saveAll() {
  var btn = document.getElementById('save-btn');
  btn.disabled = true;
  btn.textContent = 'შენახვა...';

  var resp = await fetch('wolt_scraper.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'save_batch=1&items=' + encodeURIComponent(JSON.stringify(allResults))
  });
  var data = await resp.json();
  btn.textContent = '✅ ' + data.saved + ' ფასი შენახულია!';
  setTimeout(function(){ btn.textContent = '💾 ყველის შენახვა'; btn.disabled=false; }, 3000);
}

function sleep(ms) { return new Promise(function(r){ setTimeout(r,ms); }); }
function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
</script>

<?php renderAdminFooter(); ?>