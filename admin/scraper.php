<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();
$db->exec("SET NAMES utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stores'])) {
    $ids = isset($_POST['store_id']) ? $_POST['store_id'] : array();
    foreach ($ids as $id) {
        $id  = (int)$id;
        $url = trim(isset($_POST['search_url'][$id])     ? $_POST['search_url'][$id]     : '');
        $sel = trim(isset($_POST['price_selector'][$id]) ? $_POST['price_selector'][$id] : '');
        $att = trim(isset($_POST['price_attr'][$id])     ? $_POST['price_attr'][$id]     : '');
        $db->prepare('UPDATE stores SET search_url=?, price_selector=?, price_attr=? WHERE id=?')
           ->execute(array($url ?: null, $sel ?: null, $att ?: null, $id));
    }
    setFlash('success', 'მაღაზიების პარამეტრები შენახულია.');
    header('Location: /admin/scraper.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_keywords'])) {
    $ids = isset($_POST['ing_id']) ? $_POST['ing_id'] : array();
    foreach ($ids as $id) {
        $id = (int)$id;
        $kw = trim(isset($_POST['keywords'][$id]) ? $_POST['keywords'][$id] : '');
        $db->prepare('UPDATE ingredient_prices SET search_keywords=? WHERE id=?')
           ->execute(array($kw ?: null, $id));
    }
    setFlash('success', 'Keywords შენახულია.');
    header('Location: /admin/scraper.php?tab=keywords'); exit;
}

function scrapePrice($url, $selectors = null, $attr = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ka,en;q=0.8',
            'Cache-Control: no-cache',
        ),
    ));
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || empty($html)) return null;

    // data-attribute
    if ($attr && preg_match('/' . preg_quote($attr, '/') . '="([\d.]+)"/', $html, $m)) {
        $p = (float)$m[1]; if ($p > 0.1 && $p < 300) return round($p,2);
    }
    // JSON-LD
    if (preg_match_all('/"price"\\s*:\\s*"?([\\d]+\\.?[\\d]*)"?/', $html, $ms)) {
        foreach ($ms[1] as $v) { $p=(float)$v; if($p>0.1&&$p<300) return round($p,2); }
    }
    // GEL patterns
    $pats = array(
        '/([0-9]+[.,][0-9]{1,2})\\s*₾/u',
        '/₾\\s*([0-9]+[.,][0-9]{1,2})/u',
        '/data-price="([\d.]+)"/',
        '/"sell_price":"?([\d.]+)"?/',
        '/"current_price":"?([\d.]+)"?/',
    );
    foreach ($pats as $pt) {
        if (preg_match($pt, $html, $m)) {
            $val = (float)str_replace(',','.',$m[1]);
            if ($val > 0.1 && $val < 300) return round($val,2);
        }
    }
    return null;
}

if (isset($_GET['ajax_scrape'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ing_id = (int)$_GET['ing_id'];
    $stmt   = $db->prepare('SELECT * FROM ingredient_prices WHERE id=?');
    $stmt->execute(array($ing_id));
    $ing = $stmt->fetch();
    if (!$ing) { echo json_encode(array('error'=>'not found')); exit; }

    $stores  = $db->query('SELECT * FROM stores WHERE is_active=1 AND search_url IS NOT NULL ORDER BY sort_order')->fetchAll();
    $results = array();

    foreach ($stores as $store) {
        $keywords = $ing['search_keywords'] ?: ($ing['name_en'] ?: $ing['name_ka']);
        $kw_list  = array_filter(array_map('trim', explode(',', $keywords)));
        $price    = null;
        $used_url = '';

        foreach ($kw_list as $kw) {
            $url      = str_replace('{query}', urlencode($kw), $store['search_url']);
            $used_url = $url;
            $price    = scrapePrice($url, $store['price_selector'], $store['price_attr']);
            if ($price) break;
            sleep(1);
        }

        if ($price) {
            $db->prepare(
                'INSERT INTO ingredient_store_prices (ingredient_id,store_id,price,ai_estimated,updated_at)
                 VALUES (?,?,?,0,?) ON DUPLICATE KEY UPDATE price=VALUES(price),ai_estimated=0,updated_at=VALUES(updated_at)'
            )->execute(array($ing_id, $store['id'], $price, time()));
        }
        $results[] = array('store'=>$store['name'],'price'=>$price,'url'=>$used_url);
    }
    echo json_encode(array('ok'=>true,'product'=>$ing['name_ka'],'results'=>$results), JSON_UNESCAPED_UNICODE);
    exit;
}

$tab         = isset($_GET['tab']) ? $_GET['tab'] : 'stores';
$stores      = $db->query('SELECT * FROM stores ORDER BY sort_order')->fetchAll();
$ings        = $db->query('SELECT * FROM ingredient_prices ORDER BY name_ka')->fetchAll();
$last_scrape = $db->query('SELECT MAX(updated_at) FROM ingredient_store_prices WHERE ai_estimated=0')->fetchColumn();

renderAdminHeader('Price Scraper', 'scraper');
?>
<style>
.tab-bar{display:flex;gap:2px;margin-bottom:1.5rem;background:rgba(0,0,0,.04);border-radius:10px;padding:3px;}
.tab-btn{flex:1;padding:8px;border:none;background:transparent;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;color:#888;transition:all .15s;}
.tab-btn.active{background:#fff;color:#111;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.kw-input{width:100%;border:0.5px solid rgba(0,0,0,.12);border-radius:8px;padding:6px 10px;font-size:12px;font-family:monospace;background:#fff;outline:none;}
.kw-input:focus{border-color:#16A370;box-shadow:0 0 0 3px rgba(22,163,112,.12);}
.scrape-status{font-size:11px;color:#888;margin-top:3px;min-height:14px;}
.scrape-status.ok{color:#16A370;font-weight:500;}
.scrape-status.err{color:#E53935;}
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 style="font-size:18px;font-weight:600;margin:0 0 3px;">&#128375; Price Scraper</h1>
    <p style="font-size:13px;color:#888;margin:0;">ბოლო სქრეიფი: <?php echo $last_scrape ? date('d/m/Y H:i',$last_scrape) : 'არასდროს'; ?></p>
  </div>
  <button onclick="runAll()" class="adm-btn adm-btn-primary" id="run-all-btn">&#9654; ყველის სქრეიფი</button>
</div>

<div id="progress-wrap" style="display:none;margin-bottom:1rem;">
  <div class="adm-card" style="padding:1rem;">
    <div style="font-size:13px;font-weight:500;margin-bottom:.5rem;" id="progress-text">მიმდინარეობს...</div>
    <div style="height:6px;background:#F1EFE8;border-radius:99px;overflow:hidden;">
      <div id="progress-bar" style="height:100%;background:#16A370;width:0%;border-radius:99px;transition:width .3s;"></div>
    </div>
    <div style="font-size:11px;color:#888;margin-top:.5rem;" id="progress-log"></div>
  </div>
</div>

<div class="tab-bar">
  <button class="tab-btn <?php echo $tab==='stores'?'active':''; ?>" onclick="showTab('stores',this)">&#127978; მაღაზიები</button>
  <button class="tab-btn <?php echo $tab==='keywords'?'active':''; ?>" onclick="showTab('keywords',this)">&#128269; Keywords</button>
  <button class="tab-btn <?php echo $tab==='results'?'active':''; ?>" onclick="showTab('results',this)">&#128202; შედეგები</button>
</div>

<!-- STORES -->
<div id="tab-stores" style="display:<?php echo $tab==='stores'?'block':'none'; ?>;">
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">მაღაზიების სქრეიფ პარამეტრები</span></div>
    <form method="POST">
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="border-bottom:1px solid rgba(0,0,0,.08);background:rgba(0,0,0,.02);">
              <th style="text-align:left;padding:10px 16px;min-width:100px;">მაღაზია</th>
              <th style="text-align:left;padding:10px 16px;min-width:300px;">Search URL ({query})</th>
              <th style="text-align:left;padding:10px 16px;min-width:160px;">CSS Selector</th>
              <th style="text-align:left;padding:10px 16px;min-width:120px;">Data Attr</th>
              <th style="text-align:center;padding:10px 16px;">სტატ.</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($stores as $s): ?>
            <tr style="border-bottom:0.5px solid rgba(0,0,0,.06);">
              <td style="padding:10px 16px;font-weight:600;">
                <input type="hidden" name="store_id[]" value="<?php echo $s['id']; ?>">
                <?php echo sanitize($s['name']); ?>
              </td>
              <td style="padding:7px 16px;">
                <input type="text" name="search_url[<?php echo $s['id']; ?>]"
                  value="<?php echo sanitize($s['search_url'] ?: ''); ?>"
                  class="kw-input" placeholder="https://example.ge/search?q={query}">
              </td>
              <td style="padding:7px 16px;">
                <input type="text" name="price_selector[<?php echo $s['id']; ?>]"
                  value="<?php echo sanitize($s['price_selector'] ?: ''); ?>"
                  class="kw-input" placeholder=".price,.product-price">
              </td>
              <td style="padding:7px 16px;">
                <input type="text" name="price_attr[<?php echo $s['id']; ?>]"
                  value="<?php echo sanitize($s['price_attr'] ?: ''); ?>"
                  class="kw-input" placeholder="data-price">
              </td>
              <td style="padding:7px 16px;text-align:center;">
                <?php echo $s['search_url'] ? '<span class="adm-badge badge-green">&#10003;</span>' : '<span class="adm-badge badge-gray">—</span>'; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:12px 16px;border-top:0.5px solid rgba(0,0,0,.06);">
        <button type="submit" name="save_stores" class="adm-btn adm-btn-primary">შენახვა</button>
      </div>
    </form>
  </div>
  <div class="adm-card" style="padding:1rem 1.25rem;">
    <div style="font-size:13px;font-weight:600;margin-bottom:.5rem;">&#128161; მითითებები</div>
    <div style="font-size:12px;color:#888;line-height:1.9;">
      <b>{query}</b> — ჩანაცვლდება keywords-ით &nbsp;|&nbsp;
      <b>CSS Selector</b> — .price,.product-price (მძიმით) &nbsp;|&nbsp;
      <b>Data Attr</b> — data-price, data-value &nbsp;|&nbsp;
      სქრეიფი ასევე ეძებს JSON-LD-სა და ₾ სიმბოლოს ავტომატურად
    </div>
  </div>
</div>

<!-- KEYWORDS -->
<div id="tab-keywords" style="display:<?php echo $tab==='keywords'?'block':'none'; ?>;">
  <div class="adm-card">
    <div class="adm-card-head">
      <span class="adm-card-title">Keywords</span>
      <span style="font-size:12px;color:#888;"><?php echo count($ings); ?> პროდუქტი — &#9654; ღილაკი სათითაო სქრეიფია</span>
    </div>
    <form method="POST">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="border-bottom:0.5px solid rgba(0,0,0,.1);background:rgba(0,0,0,.02);">
            <th style="text-align:left;padding:8px 16px;">პროდუქტი</th>
            <th style="text-align:left;padding:8px 16px;">EN</th>
            <th style="text-align:left;padding:8px 16px;min-width:250px;">Keywords (მძიმით გამოყოფილი)</th>
            <th style="text-align:center;padding:8px 16px;min-width:80px;">სქრეიფი</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ings as $ing): ?>
          <tr style="border-bottom:0.5px solid rgba(0,0,0,.05);">
            <td style="padding:8px 16px;font-weight:500;">
              <input type="hidden" name="ing_id[]" value="<?php echo $ing['id']; ?>">
              <?php echo sanitize($ing['name_ka']); ?>
            </td>
            <td style="padding:8px 16px;color:#888;font-size:12px;"><?php echo sanitize($ing['name_en']); ?></td>
            <td style="padding:6px 16px;">
              <input type="text" name="keywords[<?php echo $ing['id']; ?>]"
                value="<?php echo sanitize($ing['search_keywords'] ?: ''); ?>"
                class="kw-input"
                placeholder="<?php echo sanitize(($ing['name_en']?$ing['name_en'].', ':''). $ing['name_ka']); ?>">
            </td>
            <td style="padding:8px 16px;text-align:center;">
              <button type="button" onclick="scrapeOne(<?php echo $ing['id']; ?>,this)"
                      class="adm-btn adm-btn-sm adm-btn-outline">&#9654;</button>
              <div class="scrape-status" id="status-<?php echo $ing['id']; ?>"></div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 16px;border-top:0.5px solid rgba(0,0,0,.06);">
        <button type="submit" name="save_keywords" class="adm-btn adm-btn-primary">შენახვა</button>
      </div>
    </form>
  </div>
</div>

<!-- RESULTS -->
<div id="tab-results" style="display:<?php echo $tab==='results'?'block':'none'; ?>;">
  <?php
  $results = $db->query(
    'SELECT ip.name_ka, s.name as sn, isp.price, isp.ai_estimated,
             isp.updated_at as upd
     FROM ingredient_store_prices isp
     JOIN ingredient_prices ip ON ip.id=isp.ingredient_id
     JOIN stores s ON s.id=isp.store_id
     ORDER BY ip.name_ka, s.sort_order'
  )->fetchAll();
  $scraped = array_filter($results, function($r){ return !$r['ai_estimated']; });
  ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
    <div class="adm-stat"><div class="adm-stat-lbl">სქრეიფილი</div><div class="adm-stat-val" style="color:#16A370;"><?php echo count($scraped); ?></div></div>
    <div class="adm-stat"><div class="adm-stat-lbl">AI შეფასება</div><div class="adm-stat-val"><?php echo count($results)-count($scraped); ?></div></div>
  </div>
  <div class="adm-card">
    <table class="adm-table">
      <thead><tr><th>პროდუქტი</th><th>მაღაზია</th><th>ფასი</th><th>წყარო</th><th>განახლ.</th></tr></thead>
      <tbody>
      <?php foreach ($results as $r): ?>
        <tr>
          <td><?php echo sanitize($r['name_ka']); ?></td>
          <td><?php echo sanitize($r['sn']); ?></td>
          <td style="font-weight:600;color:#16A370;"><?php echo number_format((float)$r['price'],2); ?> ₾</td>
          <td><?php echo !$r['ai_estimated'] ? '<span class="adm-badge badge-green">სქრეიფი</span>' : '<span class="adm-badge badge-gray">AI</span>'; ?></td>
          <td style="font-size:11px;color:#888;"><?php echo $r['upd'] ? date('d/m H:i',$r['upd']) : '—'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function showTab(n,b){
  ['stores','keywords','results'].forEach(function(t){document.getElementById('tab-'+t).style.display='none';});
  document.querySelectorAll('.tab-btn').forEach(function(x){x.classList.remove('active');});
  document.getElementById('tab-'+n).style.display='block'; b.classList.add('active');
}
function scrapeOne(id,btn){
  btn.disabled=true; btn.textContent='⟳';
  var st=document.getElementById('status-'+id);
  st.textContent='მიმდ...'; st.className='scrape-status';
  fetch('/admin/scraper.php?ajax_scrape=1&ing_id='+id)
    .then(function(r){return r.json();})
    .then(function(d){
      btn.disabled=false; btn.textContent='▶';
      if(d.error){st.textContent='შეცდომა';st.className='scrape-status err';return;}
      var f=(d.results||[]).filter(function(r){return r.price;});
      if(f.length){
        st.textContent='✓ '+f.map(function(r){return r.store+': '+r.price+'₾';}).join(' | ');
        st.className='scrape-status ok';
      } else {st.textContent='— ვერ მოიძ.';st.className='scrape-status err';}
    })
    .catch(function(){btn.disabled=false;btn.textContent='▶';st.textContent='შეცდ.';st.className='scrape-status err';});
}
var _ids=<?php echo json_encode(array_column($ings,'id')); ?>,_idx=0;
function runAll(){
  var b=document.getElementById('run-all-btn');
  b.disabled=true; _idx=0;
  document.getElementById('progress-wrap').style.display='block';
  nextScrape();
}
function nextScrape(){
  if(_idx>=_ids.length){
    document.getElementById('progress-text').textContent='✅ დასრულდა!';
    document.getElementById('progress-bar').style.width='100%';
    document.getElementById('run-all-btn').disabled=false;
    return;
  }
  var id=_ids[_idx];
  document.getElementById('progress-bar').style.width=Math.round(_idx/_ids.length*100)+'%';
  document.getElementById('progress-text').textContent=(_idx+1)+'/'+_ids.length+' — მუშავდება...';
  fetch('/admin/scraper.php?ajax_scrape=1&ing_id='+id)
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.product){
        var f=(d.results||[]).filter(function(r){return r.price;});
        document.getElementById('progress-log').textContent=d.product+': '+(f.length?f.map(function(r){return r.store+' '+r.price+'₾';}).join(', '):'ვერ მოიძ.');
      }
      _idx++; setTimeout(nextScrape, 800);
    })
    .catch(function(){_idx++;setTimeout(nextScrape,1000);});
}
</script>
<?php renderAdminFooter(); ?>
