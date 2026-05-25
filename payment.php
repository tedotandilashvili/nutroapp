<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];

$slug    = trim(isset($_GET['plan']) ? $_GET['plan'] : '');
$stmt    = $db->prepare('SELECT * FROM subscription_plans WHERE slug=? AND is_active=1');
$stmt->execute(array($slug));
$plan    = $stmt->fetch();
if (!$plan) { setFlash('error','გეგმა ვერ მოიძებნა.'); redirect('/pricing.php'); }

$user     = getCurrentUser();
$user_sub = getUserSubscription($user_id);

// ── Handle test payment ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = isset($_POST['provider']) ? $_POST['provider'] : 'test';
    $months   = max(1, (int)(isset($_POST['months']) ? $_POST['months'] : 1));

    // Create payment record
    $order_id = 'NUTRO-' . $user_id . '-' . time();
    $db->prepare(
        'INSERT INTO payments (user_id,plan_id,amount_gel,status,provider,provider_order_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute(array($user_id,(int)$plan['id'],(float)$plan['price_gel']*$months,'pending',$provider,$order_id,time(),time()));
    $payment_id = $db->lastInsertId();

    if ($provider === 'test') {
        // Test mode — simulate successful payment
        $db->prepare('UPDATE payments SET status="completed", provider_txn_id=?, updated_at=? WHERE id=?')
           ->execute(array('TEST-TXN-'.rand(100000,999999), time(), $payment_id));

        // Activate subscription
        $db->prepare('UPDATE user_subscriptions SET status="cancelled" WHERE user_id=? AND status="active"')
           ->execute(array($user_id));
        $now = time();
        $db->prepare(
            'INSERT INTO user_subscriptions (user_id,plan_id,status,started_at,expires_at,created_at,notes)
             VALUES (?,?,"active",?,?,?,?)'
        )->execute(array($user_id,(int)$plan['id'],$now,$now+$months*30*86400,$now,'Test payment #'.$payment_id));

        // Add notification
        $db->prepare(
            'INSERT INTO notifications (user_id,type,title,message,created_at) VALUES (?,?,?,?,?)'
        )->execute(array($user_id,'subscription',
            '✅ გამოწერა გააქტიურდა!',
            $plan['name_ka'].' — '.$months.' თვე. Payment ID: '.$order_id,
            time()
        ));

        // Send confirmation email
        require_once __DIR__ . '/includes/mailer.php';
        $sub_user = array('name'=>$user['name'],'email'=>$user['email']);
        sendSubscriptionConfirmEmail($sub_user, $plan['name_ka'], $now+$months*30*86400, $plan['price_gel']*$months);
        setFlash('success','✅ გადახდა წარმატებულია! გამოწერა გააქტიურდა. შეამოწმეთ ელ.ფოსტა.');
        redirect('/dashboard.php');
    }

    if ($provider === 'bog') {
        redirect('/payment_bog.php?payment_id='.$payment_id);
    }
    if ($provider === 'tbc') {
        redirect('/payment_tbc.php?payment_id='.$payment_id);
    }
}

$months_options = array(1=>$plan['price_gel'], 3=>round($plan['price_gel']*3*0.95,2), 6=>round($plan['price_gel']*6*0.90,2));

renderHeader('გადახდა — '.$plan['name_ka'], '');
?>
<style>
.pay-wrap{max-width:480px;margin:0 auto;}
.pay-summary{background:#fff;border:1px solid var(--gray-200);border-radius:16px;padding:1.5rem;margin-bottom:1rem;}
.pay-method{border:2px solid var(--gray-200);border-radius:12px;padding:1rem;cursor:pointer;transition:all .15s;margin-bottom:.75rem;display:flex;align-items:center;gap:12px;}
.pay-method:hover{border-color:#1D9E75;}
.pay-method.selected{border-color:#1D9E75;background:#E1F5EE;}
.pay-method input[type=radio]{flex-shrink:0;}
.pay-logo{font-size:24px;flex-shrink:0;}
.pay-info{flex:1;}
.pay-name{font-weight:500;font-size:14px;}
.pay-desc{font-size:12px;color:var(--gray-400);}
.pay-badge{font-size:10px;padding:2px 8px;border-radius:99px;background:#FAEEDA;color:#854F0B;font-weight:500;}
.month-sel{display:flex;gap:8px;margin-bottom:1rem;}
.month-btn{flex:1;padding:10px;border-radius:10px;border:1.5px solid var(--gray-200);background:#fff;cursor:pointer;text-align:center;font-family:'DM Sans',sans-serif;transition:all .15s;}
.month-btn:hover,.month-btn.active{border-color:#1D9E75;background:#E1F5EE;}
.month-btn .price{font-size:16px;font-weight:500;color:#1D9E75;}
.month-btn .label{font-size:12px;color:var(--gray-400);}
.month-btn .save{font-size:10px;color:#0F6E56;font-weight:500;}
</style>

<div class="pay-wrap">
  <div style="margin-bottom:1.5rem;">
    <a href="/pricing.php" style="font-size:13px;color:var(--gray-400);text-decoration:none;">&#8592; გეგმები</a>
  </div>

  <h1 style="font-size:22px;font-weight:500;margin:0 0 1.5rem;">გადახდა</h1>

  <!-- Plan summary -->
  <div class="pay-summary">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;">
      <div>
        <div style="font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:var(--gray-400);"><?php echo sanitize($plan['name']); ?></div>
        <div style="font-size:18px;font-weight:500;"><?php echo sanitize($plan['name_ka']); ?></div>
      </div>
      <div style="font-size:24px;font-weight:500;color:#1D9E75;"><?php echo number_format($plan['price_gel'],2); ?> ₾</div>
    </div>
    <?php if ($user_sub): ?>
    <div style="font-size:12px;color:#854F0B;background:#FAEEDA;padding:6px 10px;border-radius:8px;">
      მიმდინარე გეგმა (<?php echo sanitize($user_sub['name_ka']); ?>) შეიცვლება
    </div>
    <?php endif; ?>
  </div>

  <form method="POST" id="pay-form">
    <!-- Duration -->
    <div style="margin-bottom:1.25rem;">
      <div style="font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.06em;">ვადა</div>
      <div class="month-sel">
        <?php foreach ($months_options as $m => $price):
          $save = $m > 1 ? round(100 - ($price / ($plan['price_gel']*$m) * 100)) : 0;
        ?>
        <label class="month-btn <?php echo $m===1?'active':''; ?>" onclick="selectMonth(this,<?php echo $m; ?>,<?php echo $price; ?>)">
          <input type="radio" name="months" value="<?php echo $m; ?>" style="display:none;" <?php echo $m===1?'checked':''; ?>>
          <div class="price"><?php echo number_format($price,2); ?>₾</div>
          <div class="label"><?php echo $m; ?> თვე</div>
          <?php if ($save > 0): ?><div class="save">-<?php echo $save; ?>%</div><?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Payment method -->
    <div style="margin-bottom:1.25rem;">
      <div style="font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:.75rem;text-transform:uppercase;letter-spacing:.06em;">გადახდის მეთოდი</div>

      <label class="pay-method" onclick="selectMethod(this,'test')">
        <input type="radio" name="provider" value="test" checked>
        <div class="pay-logo">🧪</div>
        <div class="pay-info">
          <div class="pay-name">სატესტო გადახდა</div>
          <div class="pay-desc">დეველოპმენტისთვის — ფული არ ჩამოიჭრება</div>
        </div>
        <span class="pay-badge">TEST</span>
      </label>

      <label class="pay-method" onclick="selectMethod(this,'bog')">
        <input type="radio" name="provider" value="bog">
        <div class="pay-logo">💳</div>
        <div class="pay-info">
          <div class="pay-name">Bank of Georgia</div>
          <div class="pay-desc">BOG ecommerce — ბარათით გადახდა</div>
        </div>
        <span class="pay-badge">მალე</span>
      </label>

      <label class="pay-method" onclick="selectMethod(this,'tbc')">
        <input type="radio" name="provider" value="tbc">
        <div class="pay-logo">💳</div>
        <div class="pay-info">
          <div class="pay-name">TBC Pay</div>
          <div class="pay-desc">TBC ecommerce — ბარათით გადახდა</div>
        </div>
        <span class="pay-badge">მალე</span>
      </label>
    </div>

    <div style="background:var(--gray-50);border-radius:10px;padding:12px 16px;margin-bottom:1rem;">
      <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:4px;">
        <span><?php echo sanitize($plan['name_ka']); ?></span>
        <span id="display-price"><?php echo number_format($plan['price_gel'],2); ?> ₾</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-weight:500;font-size:16px;border-top:1px solid var(--gray-200);padding-top:8px;margin-top:4px;">
        <span>სულ</span>
        <span style="color:#1D9E75;" id="display-total"><?php echo number_format($plan['price_gel'],2); ?> ₾</span>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-full" style="padding:14px;font-size:16px;" id="pay-btn">
      გადახდა ↗
    </button>
    <a href="/pricing.php" style="display:block;text-align:center;margin-top:1rem;font-size:13px;color:var(--gray-400);text-decoration:none;">გაუქმება</a>
  </form>
</div>

<script>
var prices = <?php echo json_encode($months_options); ?>;
var selectedMonths = 1;

function selectMonth(el, m, price) {
  document.querySelectorAll('.month-btn').forEach(function(b){ b.classList.remove('active'); });
  el.classList.add('active');
  el.querySelector('input').checked = true;
  selectedMonths = m;
  document.getElementById('display-price').textContent = price.toFixed(2) + ' ₾';
  document.getElementById('display-total').textContent = price.toFixed(2) + ' ₾';
}

function selectMethod(el, provider) {
  document.querySelectorAll('.pay-method').forEach(function(b){ b.classList.remove('selected'); });
  el.classList.add('selected');
  var btn = document.getElementById('pay-btn');
  if (provider === 'bog' || provider === 'tbc') {
    btn.textContent = 'გადახდა ↗';
    btn.disabled = true;
    btn.style.opacity = '.5';
    btn.title = 'მალე ხელმისაწვდომი';
  } else {
    btn.textContent = 'გადახდა ↗';
    btn.disabled = false;
    btn.style.opacity = '1';
    btn.title = '';
  }
}

document.getElementById('pay-form').addEventListener('submit', function(e) {
  var provider = document.querySelector('input[name=provider]:checked').value;
  if (provider !== 'test') { e.preventDefault(); alert('BOG/TBC Pay მალე დაემატება!'); }
});
</script>

<?php renderFooter(); ?>
