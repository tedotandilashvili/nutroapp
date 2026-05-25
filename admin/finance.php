<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db  = getDB();
$now = time();

// ── Date ranges ──────────────────────────────────────────────────────────────
$this_month_start = mktime(0,0,0,date('n'),1);
$last_month_start = mktime(0,0,0,date('n')-1,1);
$last_month_end   = $this_month_start - 1;
$this_year_start  = mktime(0,0,0,1,1,date('Y'));
$range_months     = 12; // show last 12 months

// ── Revenue helpers ──────────────────────────────────────────────────────────
// Active subscriptions revenue
function getRevenue($db, $from, $to) {
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(sp.price_gel),0) as total, COUNT(us.id) as count
         FROM user_subscriptions us
         JOIN subscription_plans sp ON sp.id = us.plan_id
         WHERE us.created_at >= ? AND us.created_at <= ? AND us.status != "cancelled"'
    );
    $stmt->execute(array($from, $to));
    return $stmt->fetch();
}

// ── API cost estimation ───────────────────────────────────────────────────────
// Sonnet 4.6: $3/1M input, $15/1M output
// Diet plan: ~2000 input + ~1500 output tokens
// Analyze: ~600 input + ~300 output tokens
$cost_per_plan    = round((2000 * 3 + 1500 * 15) / 1000000, 4); // ~$0.0285
$cost_per_analyze = round((600  * 3 + 300  * 15) / 1000000, 4); // ~$0.0063
$gel_per_usd      = 2.72; // approx rate

$stmt = $db->query('SELECT COUNT(*) FROM diet_plans WHERE created_at >= ' . $this_month_start);
$plans_this_month = (int)$stmt->fetchColumn();

$stmt = $db->query('SELECT COUNT(*) FROM diet_plans WHERE created_at >= ' . $this_year_start);
$plans_this_year = (int)$stmt->fetchColumn();

$stmt = $db->query('SELECT COUNT(*) FROM diet_plans');
$plans_total = (int)$stmt->fetchColumn();

$api_cost_month_usd = round(($plans_this_month * $cost_per_plan) + ($plans_this_month * 2 * $cost_per_analyze), 2);
$api_cost_year_usd  = round(($plans_this_year  * $cost_per_plan) + ($plans_this_year  * 2 * $cost_per_analyze), 2);
$api_cost_total_usd = round(($plans_total      * $cost_per_plan) + ($plans_total      * 2 * $cost_per_analyze), 2);
$api_cost_month_gel = round($api_cost_month_usd * $gel_per_usd, 2);
$api_cost_year_gel  = round($api_cost_year_usd  * $gel_per_usd, 2);

// ── Revenue this/last month ────────────────────────────────────────────────────
$rev_this  = getRevenue($db, $this_month_start, $now);
$rev_last  = getRevenue($db, $last_month_start, $last_month_end);
$rev_year  = getRevenue($db, $this_year_start,  $now);

$rev_change = $rev_last['total'] > 0
    ? round(($rev_this['total'] - $rev_last['total']) / $rev_last['total'] * 100, 1)
    : 0;

// ── Profit estimates ──────────────────────────────────────────────────────────
$profit_month = round($rev_this['total'] - $api_cost_month_gel, 2);
$profit_year  = round($rev_year['total']  - $api_cost_year_gel,  2);
$margin_month = $rev_this['total'] > 0 ? round($profit_month / $rev_this['total'] * 100, 1) : 0;

// ── Subscriptions by plan ─────────────────────────────────────────────────────
$by_plan = $db->query(
    'SELECT sp.name, sp.name_ka, sp.price_gel, sp.slug,
            COUNT(us.id) as total_subs,
            SUM(CASE WHEN us.status="active" AND us.expires_at > ' . $now . ' THEN 1 ELSE 0 END) as active_subs,
            SUM(CASE WHEN us.status="active" AND us.expires_at > ' . $now . ' THEN sp.price_gel ELSE 0 END) as mrr
     FROM subscription_plans sp
     LEFT JOIN user_subscriptions us ON us.plan_id = sp.id
     GROUP BY sp.id ORDER BY sp.sort_order'
)->fetchAll();

$total_mrr = 0;
foreach ($by_plan as $p) { $total_mrr += $p['mrr']; }

// ── Monthly revenue chart (last 12 months) ─────────────────────────────────────
$monthly = array();
for ($i = 11; $i >= 0; $i--) {
    $m_start = mktime(0,0,0,date('n')-$i,1);
    $m_end   = mktime(23,59,59,date('n')-$i+1,0);
    $stmt    = $db->prepare(
        'SELECT COALESCE(SUM(sp.price_gel),0) as rev, COUNT(us.id) as subs
         FROM user_subscriptions us
         JOIN subscription_plans sp ON sp.id=us.plan_id
         WHERE us.created_at >= ? AND us.created_at <= ? AND us.status != "cancelled"'
    );
    $stmt->execute(array($m_start, $m_end));
    $row = $stmt->fetch();
    $monthly[] = array(
        'label' => date('M', $m_start),
        'rev'   => (float)$row['rev'],
        'subs'  => (int)$row['subs'],
    );
}

// ── Churn rate ─────────────────────────────────────────────────────────────────
$cancelled_month = $db->prepare(
    'SELECT COUNT(*) FROM user_subscriptions WHERE status="cancelled" AND created_at >= ?'
);
$cancelled_month->execute(array($this_month_start));
$cancelled_month = (int)$cancelled_month->fetchColumn();
$churn = ($rev_last['count'] > 0) ? round($cancelled_month / $rev_last['count'] * 100, 1) : 0;

// ── ARPU (Average Revenue Per User) ───────────────────────────────────────────
$total_users = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$arpu = $total_users > 0 ? round($total_mrr / $total_users, 2) : 0;

// Max for chart scaling
$max_rev = 1;
foreach ($monthly as $m) { if ($m['rev'] > $max_rev) $max_rev = $m['rev']; }

$slug_colors = array(
    'low_cost'     => array('#378ADD','#E6F1FB'),
    'medium'       => array('#EF9F27','#FAEEDA'),
    'high_waltage' => array('#1D9E75','#E1F5EE'),
);

renderAdminHeader('ფინანსური ანალიტიკა', 'finance');
?>

<div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:18px;font-weight:500;margin:0 0 4px;">ფინანსური ანალიტიკა</h1>
    <p style="font-size:13px;color:#888780;margin:0;"><?php echo date('F Y'); ?> &middot; სავარაუდო მონაცემები</p>
  </div>
  <div style="font-size:12px;color:#888780;background:#F8F7F2;padding:6px 12px;border-radius:8px;">
    USD/GEL: <?php echo $gel_per_usd; ?> ₾
  </div>
</div>

<!-- ── KPI Row ── -->
<div class="adm-stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:1.5rem;">

  <div class="adm-stat" style="border-top:3px solid #1D9E75;">
    <div class="adm-stat-lbl">MRR (მიმდ. თვე)</div>
    <div class="adm-stat-val" style="color:#1D9E75;"><?php echo number_format($total_mrr,2); ?> ₾</div>
    <div class="adm-stat-delta <?php echo $rev_change>=0?'up':'down'; ?>">
      <?php echo $rev_change>=0?'+':''; ?><?php echo $rev_change; ?>% გასულ თვეს
    </div>
  </div>

  <div class="adm-stat" style="border-top:3px solid #EF9F27;">
    <div class="adm-stat-lbl">ARR (სავარ.)</div>
    <div class="adm-stat-val" style="color:#EF9F27;"><?php echo number_format($total_mrr*12,2); ?> ₾</div>
    <div style="font-size:12px;color:#888780;">MRR × 12</div>
  </div>

  <div class="adm-stat" style="border-top:3px solid <?php echo $profit_month>=0?'#1D9E75':'#A32D2D'; ?>;">
    <div class="adm-stat-lbl">მოგება (თვე)</div>
    <div class="adm-stat-val" style="color:<?php echo $profit_month>=0?'#1D9E75':'#A32D2D'; ?>;">
      <?php echo number_format($profit_month,2); ?> ₾
    </div>
    <div style="font-size:12px;color:#888780;">მარჟი: <?php echo $margin_month; ?>%</div>
  </div>

  <div class="adm-stat" style="border-top:3px solid #3C3489;">
    <div class="adm-stat-lbl">API ხარჯი (თვე)</div>
    <div class="adm-stat-val" style="color:#3C3489;">$<?php echo $api_cost_month_usd; ?></div>
    <div style="font-size:12px;color:#888780;"><?php echo $api_cost_month_gel; ?> ₾ &middot; <?php echo $plans_this_month; ?> გეგმა</div>
  </div>

  <div class="adm-stat">
    <div class="adm-stat-lbl">ARPU</div>
    <div class="adm-stat-val"><?php echo $arpu; ?> ₾</div>
    <div style="font-size:12px;color:#888780;">შემ./მომხმ.</div>
  </div>

  <div class="adm-stat">
    <div class="adm-stat-lbl">Churn (თვე)</div>
    <div class="adm-stat-val" style="color:<?php echo $churn>5?'#A32D2D':'#444441'; ?>"><?php echo $churn; ?>%</div>
    <div style="font-size:12px;color:#888780;"><?php echo $cancelled_month; ?> გაუქმება</div>
  </div>

</div>

<!-- ── Revenue Chart + Plan breakdown ── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">

  <!-- Bar chart -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">შემოსავალი — ბოლო 12 თვე (₾)</span></div>
    <div style="padding:1.5rem 1.5rem 1rem;">
      <div style="display:flex;align-items:flex-end;gap:6px;height:120px;">
        <?php foreach ($monthly as $m):
          $h = $max_rev > 0 ? max(3, round(($m['rev']/$max_rev)*112)) : 3;
          $is_current = ($m['label'] === date('M'));
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
          <?php if ($m['rev'] > 0): ?>
            <div style="font-size:10px;color:#888780;"><?php echo number_format($m['rev'],0); ?></div>
          <?php else: ?>
            <div style="font-size:10px;color:transparent;">0</div>
          <?php endif; ?>
          <div style="width:100%;height:<?php echo $h; ?>px;background:<?php echo $is_current?'#1D9E75':'#D3D1C7'; ?>;border-radius:4px 4px 0 0;transition:height .3s;"></div>
          <div style="font-size:9px;color:<?php echo $is_current?'#1D9E75':'#888780'; ?>;font-weight:<?php echo $is_current?'600':'400'; ?>;"><?php echo $m['label']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Plan breakdown -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">გეგმები</span></div>
    <div style="padding:1rem 1.5rem;">
      <?php foreach ($by_plan as $p):
        $cols = isset($slug_colors[$p['slug']]) ? $slug_colors[$p['slug']] : array('#888780','#F1EFE8');
        $pct  = $total_mrr > 0 ? round($p['mrr']/$total_mrr*100) : 0;
      ?>
      <div style="margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span style="font-weight:500;"><?php echo sanitize($p['name_ka']); ?></span>
          <span style="color:#888780;"><?php echo $p['active_subs']; ?> სუბ &middot; <?php echo number_format($p['mrr'],2); ?>₾</span>
        </div>
        <div style="height:6px;background:#F1EFE8;border-radius:99px;overflow:hidden;">
          <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $cols[0]; ?>;border-radius:99px;transition:width .4s;"></div>
        </div>
        <div style="font-size:11px;color:#888780;margin-top:2px;"><?php echo $pct; ?>% შემოსავლიდან &middot; <?php echo number_format($p['price_gel'],2); ?>₾/თვე</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- ── Cost breakdown + Projections ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

  <!-- API Cost breakdown -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">API ხარჯის დეტალი</span></div>
    <div style="padding:1.25rem 1.5rem;">

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:1.25rem;">
        <div style="background:#F8F7F2;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#888780;margin-bottom:2px;">ამ თვეს</div>
          <div style="font-size:16px;font-weight:500;color:#3C3489;">$<?php echo $api_cost_month_usd; ?></div>
          <div style="font-size:10px;color:#888780;"><?php echo $api_cost_month_gel; ?>₾</div>
        </div>
        <div style="background:#F8F7F2;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#888780;margin-bottom:2px;">ამ წელს</div>
          <div style="font-size:16px;font-weight:500;color:#3C3489;">$<?php echo $api_cost_year_usd; ?></div>
          <div style="font-size:10px;color:#888780;"><?php echo $api_cost_year_gel; ?>₾</div>
        </div>
        <div style="background:#F8F7F2;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#888780;margin-bottom:2px;">სულ</div>
          <div style="font-size:16px;font-weight:500;color:#3C3489;">$<?php echo $api_cost_total_usd; ?></div>
          <div style="font-size:10px;color:#888780;"><?php echo round($api_cost_total_usd*$gel_per_usd,2); ?>₾</div>
        </div>
      </div>

      <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">კვების გეგმა (1x)</td>
          <td style="text-align:right;font-weight:500;">$<?php echo $cost_per_plan; ?></td>
          <td style="text-align:right;color:#888780;"><?php echo round($cost_per_plan*$gel_per_usd,3); ?>₾</td>
        </tr>
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">კალ. ანალიზი (1x)</td>
          <td style="text-align:right;font-weight:500;">$<?php echo $cost_per_analyze; ?></td>
          <td style="text-align:right;color:#888780;"><?php echo round($cost_per_analyze*$gel_per_usd,3); ?>₾</td>
        </tr>
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">გეგმები ამ თვეს</td>
          <td style="text-align:right;font-weight:500;" colspan="2"><?php echo $plans_this_month; ?></td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#888780;">მოდელი</td>
          <td style="text-align:right;font-size:11px;color:#3C3489;" colspan="2">claude-sonnet-4-6</td>
        </tr>
      </table>

      <div style="margin-top:1rem;font-size:11px;color:#888780;background:#F8F7F2;padding:8px 10px;border-radius:6px;line-height:1.5;">
        * სავარაუდო. რეალური ხარჯი Anthropic Console-ში: <strong>console.anthropic.com/billing</strong>
      </div>
    </div>
  </div>

  <!-- Financial projections -->
  <div class="adm-card">
    <div class="adm-card-head"><span class="adm-card-title">პროგნოზი</span></div>
    <div style="padding:1.25rem 1.5rem;">
      <?php
      $active_count = 0;
      foreach ($by_plan as $p) $active_count += $p['active_subs'];
      $proj_3m  = round($total_mrr * 3, 2);
      $proj_6m  = round($total_mrr * 6, 2);
      $proj_12m = round($total_mrr * 12, 2);
      $api_3m   = round($api_cost_month_gel * 3, 2);
      $api_12m  = round($api_cost_month_gel * 12, 2);
      $net_12m  = round($proj_12m - $api_12m, 2);
      ?>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:1.25rem;">
        <div style="background:#E1F5EE;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#0F6E56;margin-bottom:2px;">3 თვე</div>
          <div style="font-size:16px;font-weight:500;color:#0F6E56;"><?php echo number_format($proj_3m,0); ?>₾</div>
        </div>
        <div style="background:#E1F5EE;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#0F6E56;margin-bottom:2px;">6 თვე</div>
          <div style="font-size:16px;font-weight:500;color:#0F6E56;"><?php echo number_format($proj_6m,0); ?>₾</div>
        </div>
        <div style="background:#E1F5EE;border-radius:8px;padding:10px;text-align:center;">
          <div style="font-size:10px;color:#0F6E56;margin-bottom:2px;">12 თვე</div>
          <div style="font-size:16px;font-weight:500;color:#0F6E56;"><?php echo number_format($proj_12m,0); ?>₾</div>
        </div>
      </div>

      <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">აქტიური გამოწერა</td>
          <td style="text-align:right;font-weight:500;"><?php echo $active_count; ?></td>
        </tr>
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">MRR</td>
          <td style="text-align:right;font-weight:500;color:#1D9E75;"><?php echo number_format($total_mrr,2); ?>₾</td>
        </tr>
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;color:#888780;">API ხარჯი/წელი (სავ.)</td>
          <td style="text-align:right;color:#3C3489;">-<?php echo number_format($api_12m,2); ?>₾</td>
        </tr>
        <tr style="border-bottom:1px solid #F1EFE8;">
          <td style="padding:6px 0;font-weight:500;">სუფთა მოგება/წელი</td>
          <td style="text-align:right;font-weight:500;color:<?php echo $net_12m>=0?'#1D9E75':'#A32D2D'; ?>;">
            <?php echo number_format($net_12m,2); ?>₾
          </td>
        </tr>
        <tr>
          <td style="padding:6px 0;color:#888780;">Break-even</td>
          <td style="text-align:right;color:#888780;font-size:11px;">
            <?php
            // Break-even = minimum subs needed so that revenue covers API costs
            // Formula: api_cost_month / avg_revenue_per_sub >= N subs
            // We use cheapest plan price as minimum revenue per sub
            $min_plan_price = PHP_INT_MAX;
            foreach ($by_plan as $bp) {
                if ((float)$bp['price_gel'] > 0 && (float)$bp['price_gel'] < $min_plan_price) {
                    $min_plan_price = (float)$bp['price_gel'];
                }
            }
            if ($min_plan_price === PHP_INT_MAX) $min_plan_price = 9.99;
            // plans_per_sub_per_month estimate: active_subs>0 ? plans_this_month/active_count : 5
            $plans_per_sub = $active_count > 0 ? max(1, round($plans_this_month / $active_count)) : 5;
            // cost per sub per month
            $api_cost_per_sub = round(($plans_per_sub * $cost_per_plan + $plans_per_sub * 2 * $cost_per_analyze) * $gel_per_usd, 2);
            // net revenue per sub = plan price - api cost per sub
            $net_per_sub = $min_plan_price - $api_cost_per_sub;
            if ($net_per_sub > 0) {
                // not really "break-even subs" in traditional sense since each sub is profitable
                // Show: currently profitable at 1 sub, and show cost per sub
                echo 'პროფ. 1 სუბ-დან (ხარჯი/სუბ: ' . number_format($api_cost_per_sub, 2) . '₾)';
            } elseif ($api_cost_per_sub > 0) {
                // Sub is unprofitable — need higher plan or fewer plans per sub
                $needed = ceil($api_cost_per_sub / $min_plan_price);
                echo $needed . ' სუბ. (ზარალი/სუბ: ' . number_format(abs($net_per_sub), 2) . '₾)';
            } else {
                echo 'N/A';
            }
            ?>
          </td>
        </tr>
      </table>

      <?php if ($total_mrr == 0): ?>
      <div style="margin-top:1rem;font-size:12px;color:#854F0B;background:#FAEEDA;padding:8px 10px;border-radius:6px;">
        გამოწერები ჯერ არ არის. პროგნოზი გაჩნდება პირველი გამოწერის შემდეგ.
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── Year summary table ── -->
<div class="adm-card">
  <div class="adm-card-head">
    <span class="adm-card-title"><?php echo date('Y'); ?> — თვიური შეჯამება</span>
  </div>
  <table class="adm-table">
    <thead>
      <tr>
        <th>თვე</th>
        <th>გამოწერები</th>
        <th>შემოსავ. ₾</th>
        <th>API ხარჯი ₾</th>
        <th>სუფთა მოგ. ₾</th>
        <th>მარჟი</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $year_total_rev  = 0;
      $year_total_api  = 0;
      foreach ($monthly as $idx => $m):
        // plans for this month from chart data
        $m_start_ts = mktime(0,0,0,date('n')-11+$idx,1);
        $m_end_ts   = mktime(23,59,59,date('n')-11+$idx+1,0);
        $plans_stmt = $db->prepare('SELECT COUNT(*) FROM diet_plans WHERE created_at>=? AND created_at<=?');
        $plans_stmt->execute(array($m_start_ts,$m_end_ts));
        $m_plans   = (int)$plans_stmt->fetchColumn();
        $m_api_gel = round(($m_plans * $cost_per_plan + $m_plans * 2 * $cost_per_analyze) * $gel_per_usd, 2);
        $m_net     = round($m['rev'] - $m_api_gel, 2);
        $m_margin  = $m['rev'] > 0 ? round($m_net/$m['rev']*100,1) : 0;
        $year_total_rev += $m['rev'];
        $year_total_api += $m_api_gel;
        $is_current = ($m['label'] === date('M'));
      ?>
      <tr style="<?php echo $is_current?'background:#F8FFF9;':''; ?>">
        <td style="font-weight:<?php echo $is_current?'500':'400'; ?>;color:<?php echo $is_current?'#1D9E75':'inherit'; ?>;">
          <?php echo $m['label']; ?><?php if($is_current) echo ' ◀'; ?>
        </td>
        <td><span class="adm-badge <?php echo $m['subs']>0?'badge-green':'badge-gray'; ?>"><?php echo $m['subs']; ?></span></td>
        <td style="font-weight:500;color:<?php echo $m['rev']>0?'#1D9E75':'#888780'; ?>;"><?php echo number_format($m['rev'],2); ?></td>
        <td style="color:#3C3489;">-<?php echo $m_api_gel; ?></td>
        <td style="font-weight:500;color:<?php echo $m_net>=0?'#1D9E75':'#A32D2D'; ?>"><?php echo number_format($m_net,2); ?></td>
        <td>
          <?php if ($m['rev'] > 0): ?>
            <span style="font-size:12px;color:<?php echo $m_margin>=50?'#0F6E56':($m_margin>=0?'#854F0B':'#A32D2D'); ?>;">
              <?php echo $m_margin; ?>%
            </span>
          <?php else: ?>
            <span style="color:#B4B2A9;font-size:12px;">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#F8F7F2;font-weight:500;">
        <td style="padding:10px 1.5rem;">სულ <?php echo date('Y'); ?></td>
        <td></td>
        <td style="color:#1D9E75;padding:10px 1.5rem;"><?php echo number_format($year_total_rev,2); ?> ₾</td>
        <td style="color:#3C3489;padding:10px 1.5rem;">-<?php echo number_format($year_total_api,2); ?> ₾</td>
        <td style="padding:10px 1.5rem;color:<?php echo ($year_total_rev-$year_total_api)>=0?'#1D9E75':'#A32D2D'; ?>;">
          <?php echo number_format($year_total_rev-$year_total_api,2); ?> ₾
        </td>
        <td style="padding:10px 1.5rem;">
          <?php echo $year_total_rev>0 ? round(($year_total_rev-$year_total_api)/$year_total_rev*100,1).'%' : '—'; ?>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<?php renderAdminFooter(); ?>