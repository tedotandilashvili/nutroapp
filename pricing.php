<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$plans    = getAllPlans();
$user_sub = isLoggedIn() ? getUserSubscription($_SESSION['user_id']) : null;

renderHeader('გამოწერის გეგმები', 'pricing');
?>
<style>
.pricing-wrap{padding:2rem 0 1rem;}
.pricing-header{text-align:center;margin-bottom:2.5rem;}
.pricing-eyebrow{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.12em;color:#1D9E75;margin-bottom:.75rem;}
.pricing-title{font-size:28px;font-weight:500;color:var(--gray-900);margin:0 0 .5rem;line-height:1.2;}
.pricing-sub{font-size:14px;color:var(--gray-400);margin:0;}
.pricing-current-banner{max-width:500px;margin:0 auto 2rem;background:#E6F1FB;border-radius:10px;padding:10px 16px;font-size:13px;color:#185FA5;text-align:center;}
.pricing-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;}
.p-card{background:#fff;border:1px solid var(--gray-200);border-radius:18px;padding:1.75rem 1.5rem;display:flex;flex-direction:column;position:relative;transition:transform .18s,box-shadow .18s;}
.p-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.06);}
.p-card.featured{border:2px solid #1D9E75;}
.p-badge{position:absolute;top:-12px;left:50%;transform:translateX(-50%);font-size:11px;font-weight:500;padding:4px 14px;border-radius:99px;white-space:nowrap;}
.p-badge.popular{background:#1D9E75;color:#E1F5EE;}
.p-badge.current{background:#185FA5;color:#E6F1FB;}
.p-tier{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:var(--gray-400);margin-bottom:.3rem;}
.p-name{font-size:18px;font-weight:500;color:var(--gray-900);margin-bottom:1.1rem;}
.p-price-row{display:flex;align-items:flex-end;gap:4px;margin-bottom:4px;}
.p-price{font-size:38px;font-weight:500;color:var(--gray-900);line-height:1;}
.p-currency{font-size:18px;color:var(--gray-400);padding-bottom:5px;}
.p-period{font-size:13px;color:var(--gray-400);padding-bottom:6px;}
.p-tag{font-size:12px;padding:3px 10px;border-radius:99px;display:inline-block;margin-bottom:1.1rem;}
.p-tag.green{background:#E1F5EE;color:#0F6E56;}
.p-tag.amber{background:#FAEEDA;color:#854F0B;}
.p-divider{height:1px;background:var(--gray-100);margin:1.1rem 0;}
.p-features{list-style:none;padding:0;margin:0 0 1.4rem;flex:1;}
.p-features li{display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--gray-700);padding:5px 0;}
.p-features li.off{color:var(--gray-400);}
.check-circle{width:16px;height:16px;border-radius:50%;background:#E1F5EE;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
.check-circle.off{background:var(--gray-100);}
.p-btn{display:block;width:100%;padding:11px;border-radius:10px;font-size:14px;font-weight:500;text-align:center;text-decoration:none;cursor:pointer;border:1px solid var(--gray-200);background:transparent;color:var(--gray-700);font-family:'DM Sans',sans-serif;transition:all .15s;box-sizing:border-box;}
.p-btn:hover{border-color:#1D9E75;color:#1D9E75;}
.p-btn.primary{background:#1D9E75;border-color:#1D9E75;color:#fff;}
.p-btn.primary:hover{background:#0F6E56;border-color:#0F6E56;}
.p-btn.current-btn{background:var(--gray-100);color:var(--gray-400);cursor:default;border-color:var(--gray-200);}
.p-limit{font-size:11px;color:var(--gray-400);text-align:center;margin-top:8px;}
.pricing-footer{margin-top:2rem;background:var(--gray-50);border-radius:14px;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
.footer-items{display:flex;gap:1.5rem;flex-wrap:wrap;}
.footer-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--gray-400);}
.footer-dot{width:6px;height:6px;border-radius:50%;background:#1D9E75;flex-shrink:0;}
@media(max-width:640px){.pricing-grid{grid-template-columns:1fr;}}
</style>

<div class="pricing-wrap">
  <div class="pricing-header">
    <div class="pricing-eyebrow">გამოწერა</div>
    <h1 class="pricing-title">აირჩიე შენი გეგმა</h1>
    <p class="pricing-sub">პერსონალური კვების გეგმა შენი ბიუჯეტის მიხედვით</p>
  </div>

  <?php if ($user_sub): ?>
  <div class="pricing-current-banner">
    მიმდინარე გეგმა: <strong><?php echo sanitize($user_sub['name_ka']); ?></strong>
    &middot; მოქმედია <?php echo date('d/m/Y', $user_sub['expires_at']); ?>-მდე
  </div>
  <?php endif; ?>

  <div class="pricing-grid">
    <?php
    $highlights = array('low_cost'=>false,'medium'=>true,'high_waltage'=>false);
    foreach ($plans as $plan):
      $features   = json_decode($plan['features'], true);
      if (!is_array($features)) $features = array();
      $is_current = $user_sub && $user_sub['plan_id'] == $plan['id'];
      $is_featured= isset($highlights[$plan['slug']]) && $highlights[$plan['slug']];
      $is_unlimited = $plan['max_plans_month'] == -1;
      $limit_txt  = $is_unlimited ? 'შეუზღუდავი' : $plan['max_plans_month'].' გეგმა/თვეში';
      $tag_class  = $is_unlimited ? 'amber' : 'green';
    ?>
    <div class="p-card <?php echo $is_featured?'featured':''; ?>">

      <?php if ($is_current): ?>
        <div class="p-badge current">&#10003; მიმდინარე გეგმა</div>
      <?php elseif ($is_featured): ?>
        <div class="p-badge popular">ყველაზე პოპულარული</div>
      <?php endif; ?>

      <div class="p-tier"><?php echo sanitize($plan['name']); ?></div>
      <div class="p-name"><?php echo sanitize($plan['name_ka']); ?></div>

      <div class="p-price-row">
        <span class="p-currency">&#8382;</span>
        <span class="p-price"><?php echo number_format($plan['price_gel'],2); ?></span>
        <span class="p-period">/ თვეში</span>
      </div>
      <div class="p-tag <?php echo $tag_class; ?>"><?php echo $limit_txt; ?></div>

      <div class="p-divider"></div>

      <ul class="p-features">
        <?php foreach ($features as $fi => $f):
          $is_off = (strpos($f,'🔒') !== false);
          $f_clean = str_replace('🔒','', $f);
        ?>
        <li class="<?php echo $is_off?'off':''; ?>">
          <span class="check-circle <?php echo $is_off?'off':''; ?>">
            <?php if ($is_off): ?>
              <svg width="9" height="9" viewBox="0 0 9 9" fill="none"><path d="M2 2L7 7M7 2L2 7" stroke="#B4B2A9" stroke-width="1.5" stroke-linecap="round"/></svg>
            <?php else: ?>
              <svg width="9" height="9" viewBox="0 0 9 9" fill="none"><path d="M1.5 4.5L3.5 6.5L7.5 2.5" stroke="#0F6E56" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php endif; ?>
          </span>
          <?php echo sanitize($f_clean); ?>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($is_current): ?>
        <div class="p-btn current-btn">მიმდინარე გეგმა</div>
        <div class="p-limit">მოქმ. <?php echo date('d/m/Y', $user_sub['expires_at']); ?>-მდე</div>
      <?php elseif (!isLoggedIn()): ?>
        <a href="/register.php" class="p-btn <?php echo $is_featured?'primary':''; ?>">
          დაიწყე
        </a>
      <?php else: ?>
        <a href="/subscribe.php?plan=<?php echo sanitize($plan['slug']); ?>"
           class="p-btn <?php echo $is_featured?'primary':''; ?>">
          <?php echo $user_sub ? 'გადართვა' : 'გამოწერა'; ?>
        </a>
      <?php endif; ?>

      <div class="p-limit"><?php echo sanitize($plan['max_days']); ?> დღე მაქს. &middot; <?php echo $limit_txt; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="pricing-footer">
    <div class="footer-items">
      <div class="footer-item"><div class="footer-dot"></div>ქართული ბაზრის ფასები</div>
      <div class="footer-item"><div class="footer-dot"></div>AI-ით გენერირებული მენიუ</div>
      <div class="footer-item"><div class="footer-dot"></div>გაუქმება ნებისმიერ დროს</div>
    </div>
    <div style="font-size:12px;color:var(--gray-400);">კითხვები? <a href="mailto:info@nutroapp.ge" style="color:#1D9E75;text-decoration:none;">დაგვიკავშირდი</a></div>
  </div>
</div>

<?php renderFooter(); ?>
