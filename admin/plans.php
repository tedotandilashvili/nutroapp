<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Delete plan ────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare('DELETE FROM diet_plans WHERE id=?')->execute(array((int)$_GET['delete']));
    setFlash('success', 'გეგმა წაიშალა.');
    $back = isset($_GET['user_id']) ? '/admin/plans.php?user_id='.(int)$_GET['user_id'] : '/admin/plans.php';
    header('Location: ' . $back); exit;
}

// ── Filters ────────────────────────────────────────────────────────────────────
$search  = trim(isset($_GET['q'])       ? $_GET['q']       : '');
$user_id = (int)(isset($_GET['user_id'])? $_GET['user_id'] : 0);
$page    = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$limit   = 20;
$offset  = ($page - 1) * $limit;

$where  = array('1=1');
$params = array();
if ($user_id) { $where[] = 'd.user_id = ?';    $params[] = $user_id; }
if ($search)  { $where[] = '(u.name LIKE ? OR d.title LIKE ?)'; $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; }
$where_sql = implode(' AND ', $where);

$count_params = $params;
$cnt = $db->prepare("SELECT COUNT(*) FROM diet_plans d JOIN users u ON u.id=d.user_id WHERE $where_sql");
$cnt->execute($count_params); $total = (int)$cnt->fetchColumn();

$stmt = $db->prepare(
    "SELECT d.*, u.name as user_name, u.email as user_email
     FROM diet_plans d JOIN users u ON u.id=d.user_id
     WHERE $where_sql ORDER BY d.created_at DESC LIMIT ? OFFSET ?"
);
$params[] = $limit; $params[] = $offset;
$stmt->execute($params);
$plans = $stmt->fetchAll();
$pages = ceil($total / $limit);

// If filtering by user, get user info
$filter_user = null;
if ($user_id) {
    $fu = $db->prepare('SELECT name, email FROM users WHERE id=?');
    $fu->execute(array($user_id)); $filter_user = $fu->fetch();
}

renderAdminHeader('გეგმები', 'plans');
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:18px;font-weight:500;margin:0 0 4px;">
      კვების გეგმები
      <?php if ($filter_user): ?>
        <span style="font-size:14px;font-weight:400;color:#888780;">
          — <?php echo sanitize($filter_user['name']); ?>
        </span>
      <?php endif; ?>
    </h1>
    <p style="font-size:13px;color:#888780;margin:0;">
      სულ <?php echo $total; ?> გეგმა
      <?php if ($filter_user): ?>
        &middot; <a href="/admin/plans.php" style="color:#1D9E75;text-decoration:none;">ყველა გეგმა</a>
      <?php endif; ?>
    </p>
  </div>
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($user_id): ?><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><?php endif; ?>
    <input type="text" name="q" class="adm-search" placeholder="მომხმარებელი ან სათაური..."
           value="<?php echo sanitize($search); ?>">
    <button type="submit" class="adm-btn adm-btn-primary">ძიება</button>
    <?php if ($search || $user_id): ?>
      <a href="/admin/plans.php" class="adm-btn adm-btn-outline">გასუფთავება</a>
    <?php endif; ?>
  </form>
</div>

<div class="adm-card">
  <table class="adm-table">
    <thead>
      <tr>
        <th>#</th>
        <th>სათაური</th>
        <th>მომხმარებელი</th>
        <th>კკალ</th>
        <th>დღეები</th>
        <th>თარიღი</th>
        <th>მოქმ.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td style="color:#888780;font-size:12px;"><?php echo $p['id']; ?></td>
        <td>
          <a href="/plan.php?id=<?php echo $p['id']; ?>" target="_blank"
             style="color:#1D9E75;text-decoration:none;font-weight:500;font-size:13px;">
            <?php echo sanitize($p['title']); ?>
          </a>
        </td>
        <td>
          <a href="/admin/plans.php?user_id=<?php echo $p['user_id']; ?>"
             style="text-decoration:none;color:#444441;">
            <?php echo sanitize($p['user_name']); ?>
          </a>
          <div style="font-size:11px;color:#888780;"><?php echo sanitize($p['user_email']); ?></div>
        </td>
        <td style="font-weight:500;color:#1D9E75;"><?php echo $p['target_calories']; ?></td>
        <td><span class="adm-badge badge-gray"><?php echo $p['days']; ?> დღე</span></td>
        <td style="font-size:12px;color:#888780;"><?php echo date('d/m/Y H:i', $p['created_at']); ?></td>
        <td>
          <a href="/admin/plans.php?delete=<?php echo $p['id']; ?><?php echo $user_id ? '&user_id='.$user_id : ''; ?>"
             class="adm-btn adm-btn-sm adm-btn-danger"
             onclick="return confirm('გეგმა წაიშლება. დარწმუნებული ხართ?')">წაშლა</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($plans)): ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#888780;">გეგმა ვერ მოიძებნა</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?php echo $i; ?><?php echo $search?'&q='.urlencode($search):''; ?><?php echo $user_id?'&user_id='.$user_id:''; ?>"
       style="padding:6px 12px;border-radius:8px;font-size:13px;text-decoration:none;border:1px solid <?php echo $i==$page?'#1D9E75':'#E8E6DF'; ?>;background:<?php echo $i==$page?'#1D9E75':'#fff'; ?>;color:<?php echo $i==$page?'#fff':'#444441'; ?>;">
      <?php echo $i; ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
