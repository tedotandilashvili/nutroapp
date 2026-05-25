<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db    = getDB();
$page  = max(1,(int)(isset($_GET['page'])?$_GET['page']:1));
$limit = 25; $offset = ($page-1)*$limit;
$total = (int)$db->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$pages = ceil($total/$limit);

$payments = $db->prepare(
    'SELECT p.*,u.name as uname,u.email,sp.name_ka
     FROM payments p
     JOIN users u ON u.id=p.user_id
     JOIN subscription_plans sp ON sp.id=p.plan_id
     ORDER BY p.created_at DESC LIMIT ? OFFSET ?'
);
$payments->execute(array($limit,$offset));
$payments = $payments->fetchAll();

// Stats
$total_rev = $db->query("SELECT COALESCE(SUM(amount_gel),0) FROM payments WHERE status='completed'")->fetchColumn();
$this_month = mktime(0,0,0,date('n'),1);
$month_rev  = $db->prepare("SELECT COALESCE(SUM(amount_gel),0) FROM payments WHERE status='completed' AND created_at>=?");
$month_rev->execute(array($this_month)); $month_rev = $month_rev->fetchColumn();

$status_badge = array('completed'=>'badge-green','pending'=>'badge-amber','failed'=>'badge-red','refunded'=>'badge-gray');
$status_ka    = array('completed'=>'დადასტურდა','pending'=>'მომლოდინე','failed'=>'ვერ მოხდა','refunded'=>'დაბრუნდა');
$prov_icons   = array('bog'=>'🏦','tbc'=>'💳','test'=>'🧪');

renderAdminHeader('გადახდები', 'payments');
?>
<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 style="font-size:18px;font-weight:500;margin:0 0 4px;">გადახდები</h1>
    <p style="font-size:13px;color:#888780;margin:0;">სულ <?php echo $total; ?> ტრანზაქცია</p>
  </div>
  <div style="display:flex;gap:1rem;">
    <div style="text-align:right;">
      <div style="font-size:11px;color:#888780;">ამ თვეს</div>
      <div style="font-size:18px;font-weight:500;color:#1D9E75;"><?php echo number_format($month_rev,2); ?> ₾</div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:11px;color:#888780;">სულ</div>
      <div style="font-size:18px;font-weight:500;"><?php echo number_format($total_rev,2); ?> ₾</div>
    </div>
  </div>
</div>

<div class="adm-card">
  <table class="adm-table">
    <thead>
      <tr><th>ID</th><th>მომხმარებელი</th><th>გეგმა</th><th>თანხა</th><th>სტ.</th><th>მეთოდი</th><th>TXN ID</th><th>თარიღი</th></tr>
    </thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td style="color:#888780;font-size:12px;">#<?php echo $p['id']; ?></td>
        <td>
          <div style="font-weight:500;font-size:13px;"><?php echo sanitize($p['uname']); ?></div>
          <div style="font-size:11px;color:#888780;"><?php echo sanitize($p['email']); ?></div>
        </td>
        <td style="font-size:13px;"><?php echo sanitize($p['name_ka']); ?></td>
        <td style="font-weight:500;color:#1D9E75;"><?php echo number_format($p['amount_gel'],2); ?> ₾</td>
        <td><span class="adm-badge <?php echo isset($status_badge[$p['status']])?$status_badge[$p['status']]:'badge-gray'; ?>">
          <?php echo isset($status_ka[$p['status']])?$status_ka[$p['status']]:$p['status']; ?>
        </span></td>
        <td style="font-size:13px;"><?php echo isset($prov_icons[$p['provider']])?$prov_icons[$p['provider']]:'?'; ?> <?php echo $p['provider']; ?></td>
        <td style="font-size:11px;color:#888780;font-family:monospace;"><?php echo $p['provider_txn_id'] ? substr($p['provider_txn_id'],0,20).'...' : '—'; ?></td>
        <td style="font-size:12px;color:#888780;"><?php echo date('d/m/Y H:i',$p['created_at']); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:#888780;">გადახდა ჯერ არ არის</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages>1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1rem;">
  <?php for($i=1;$i<=$pages;$i++): ?>
    <a href="?page=<?php echo $i; ?>" style="padding:6px 12px;border-radius:8px;font-size:13px;text-decoration:none;border:1px solid <?php echo $i==$page?'#1D9E75':'#E8E6DF'; ?>;background:<?php echo $i==$page?'#1D9E75':'#fff'; ?>;color:<?php echo $i==$page?'#fff':'#444441'; ?>;"><?php echo $i; ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php renderAdminFooter(); ?>
