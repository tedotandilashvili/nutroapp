<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db   = getDB();
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === 'users') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nutroapp_users_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, array('ID','სახელი','ელ.ფოსტა','მიზანი','წონა','სიმაღლე','ასაკი','გეგმები','რეგ.თარიღი'));
    $rows = $db->query(
        'SELECT u.id,u.name,u.email,up.goal,up.weight_kg,up.height_cm,up.age,
                (SELECT COUNT(*) FROM diet_plans d WHERE d.user_id=u.id) as plans,
                u.created_at
         FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id
         ORDER BY u.created_at DESC'
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, array(
            $r['id'],$r['name'],$r['email'],
            $r['goal'],$r['weight_kg'],$r['height_cm'],$r['age'],
            $r['plans'], date('d/m/Y',$r['created_at'])
        ));
    }
    fclose($out); exit;
}

if ($type === 'subscriptions') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nutroapp_subs_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, array('ID','მომხმარებელი','ელ.ფოსტა','გეგმა','ფასი','სტატუსი','დაიწყო','სრულდება'));
    $rows = $db->query(
        'SELECT us.*,u.name,u.email,sp.name_ka,sp.price_gel
         FROM user_subscriptions us
         JOIN users u ON u.id=us.user_id
         JOIN subscription_plans sp ON sp.id=us.plan_id
         ORDER BY us.created_at DESC'
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, array(
            $r['id'],$r['name'],$r['email'],
            $r['name_ka'],$r['price_gel'],$r['status'],
            date('d/m/Y',$r['started_at']),date('d/m/Y',$r['expires_at'])
        ));
    }
    fclose($out); exit;
}

if ($type === 'revenue') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nutroapp_revenue_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, array('თვე','გამოწერები','შემოსავ.₾'));
    for ($i=11;$i>=0;$i--) {
        $ms = mktime(0,0,0,date('n')-$i,1);
        $me = mktime(23,59,59,date('n')-$i+1,0);
        $stmt = $db->prepare('SELECT COUNT(*) as c, COALESCE(SUM(sp.price_gel),0) as r FROM user_subscriptions us JOIN subscription_plans sp ON sp.id=us.plan_id WHERE us.created_at>=? AND us.created_at<=? AND us.status!="cancelled"');
        $stmt->execute(array($ms,$me));
        $row = $stmt->fetch();
        fputcsv($out, array(date('M Y',$ms),$row['c'],number_format($row['r'],2)));
    }
    fclose($out); exit;
}

// Export page
renderAdminHeader('CSV ექსპორტი', 'export');
?>
<h1 style="font-size:18px;font-weight:500;margin-bottom:1.5rem;">CSV ექსპორტი</h1>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;">
  <div class="adm-card" style="padding:1.5rem;text-align:center;">
    <div style="font-size:32px;margin-bottom:.75rem;">👥</div>
    <div style="font-weight:500;margin-bottom:.5rem;">მომხმარებლები</div>
    <div style="font-size:12px;color:#888780;margin-bottom:1rem;">ყველა მომხმარებელი და პროფილი</div>
    <a href="/admin/export.php?type=users" class="adm-btn adm-btn-primary" style="width:100%;display:block;text-align:center;">ჩამოტვირთვა</a>
  </div>
  <div class="adm-card" style="padding:1.5rem;text-align:center;">
    <div style="font-size:32px;margin-bottom:.75rem;">💳</div>
    <div style="font-weight:500;margin-bottom:.5rem;">გამოწერები</div>
    <div style="font-size:12px;color:#888780;margin-bottom:1rem;">ყველა სუბსკრიფცია</div>
    <a href="/admin/export.php?type=subscriptions" class="adm-btn adm-btn-primary" style="width:100%;display:block;text-align:center;">ჩამოტვირთვა</a>
  </div>
  <div class="adm-card" style="padding:1.5rem;text-align:center;">
    <div style="font-size:32px;margin-bottom:.75rem;">📈</div>
    <div style="font-weight:500;margin-bottom:.5rem;">შემოსავლები</div>
    <div style="font-size:12px;color:#888780;margin-bottom:1rem;">თვიური შემოსავლის ისტორია</div>
    <a href="/admin/export.php?type=revenue" class="adm-btn adm-btn-primary" style="width:100%;display:block;text-align:center;">ჩამოტვირთვა</a>
  </div>
</div>
<?php renderAdminFooter(); ?>
