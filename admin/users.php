<?php
require_once __DIR__ . '/auth_admin.php';
requireAdmin();

$db = getDB();

// ── Delete user ────────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute(array((int)$_GET['delete']));
    setFlash('success', 'მომხმარებელი წაიშალა.');
    header('Location: /admin/users.php'); exit;
}

// ── Search / filter ────────────────────────────────────────────────────────────
$search = trim(isset($_GET['q']) ? $_GET['q'] : '');
$page   = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

if ($search) {
    $like  = '%' . $search . '%';
    $total = $db->prepare('SELECT COUNT(*) FROM users WHERE name LIKE ? OR email LIKE ?');
    $total->execute(array($like, $like)); $total = (int)$total->fetchColumn();
    $stmt  = $db->prepare(
        'SELECT u.*, up.goal, up.weight_kg, up.activity_level, up.budget,
                (SELECT COUNT(*) FROM diet_plans d WHERE d.user_id=u.id) as plan_count
         FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id
         WHERE u.name LIKE ? OR u.email LIKE ?
         ORDER BY u.created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute(array($like, $like, $limit, $offset));
} else {
    $total = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stmt  = $db->prepare(
        'SELECT u.*, up.goal, up.weight_kg, up.activity_level, up.budget,
                (SELECT COUNT(*) FROM diet_plans d WHERE d.user_id=u.id) as plan_count
         FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id
         ORDER BY u.created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute(array($limit, $offset));
}
$users = $stmt->fetchAll();
$pages = ceil($total / $limit);

$goal_labels = array('Weight Loss'=>'წ.დაკლება','Muscle Gain'=>'კ.მომატება','Maintenance'=>'შენარჩუნება');
$goal_badges = array('Weight Loss'=>'badge-amber','Muscle Gain'=>'badge-green','Maintenance'=>'badge-blue');

renderAdminHeader('მომხმარებლები', 'users');
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
  <div>
    <h1 style="font-size:18px;font-weight:500;margin:0 0 4px;">მომხმარებლები</h1>
    <p style="font-size:13px;color:#888780;margin:0;">სულ <?php echo $total; ?> მომხმარებელი</p>
  </div>
  <form method="GET" style="display:flex;gap:8px;">
    <input type="text" name="q" class="adm-search" placeholder="სახელი ან ელ.ფოსტა..."
           value="<?php echo sanitize($search); ?>">
    <button type="submit" class="adm-btn adm-btn-primary">ძიება</button>
    <?php if ($search): ?>
      <a href="/admin/users.php" class="adm-btn adm-btn-outline">გასუფთავება</a>
    <?php endif; ?>
  </form>
</div>

<div class="adm-card">
  <table class="adm-table">
    <thead>
      <tr>
        <th>#</th>
        <th>მომხმარებელი</th>
        <th>მიზანი</th>
        <th>წონა</th>
        <th>გეგმები</th>
        <th>რეგ. თარიღი</th>
        <th>მოქმ.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="color:#888780;font-size:12px;"><?php echo $u['id']; ?></td>
        <td>
          <div style="font-weight:500;"><?php echo sanitize($u['name']); ?></div>
          <div style="font-size:11px;color:#888780;"><?php echo sanitize($u['email']); ?></div>
        </td>
        <td>
          <?php if ($u['goal']): ?>
            <span class="adm-badge <?php echo isset($goal_badges[$u['goal']]) ? $goal_badges[$u['goal']] : 'badge-gray'; ?>">
              <?php echo isset($goal_labels[$u['goal']]) ? $goal_labels[$u['goal']] : $u['goal']; ?>
            </span>
          <?php else: ?>
            <span style="font-size:12px;color:#B4B2A9;">—</span>
          <?php endif; ?>
        </td>
        <td style="font-size:13px;color:#444441;">
          <?php echo $u['weight_kg'] ? $u['weight_kg'].' კგ' : '—'; ?>
        </td>
        <td>
          <span class="adm-badge <?php echo $u['plan_count']>0?'badge-green':'badge-gray'; ?>">
            <?php echo $u['plan_count']; ?>
          </span>
        </td>
        <td style="font-size:12px;color:#888780;"><?php echo date('d/m/Y', $u['created_at']); ?></td>
        <td>
          <div style="display:flex;gap:6px;">
            <a href="/admin/plans.php?user_id=<?php echo $u['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-outline">გეგმები</a>
            <a href="/admin/users.php?delete=<?php echo $u['id']; ?>"
               class="adm-btn adm-btn-sm adm-btn-danger"
               onclick="return confirm('<?php echo sanitize($u['name']); ?> წაიშლება მის ყველა გეგმასთან ერთად. დარწმუნებული ხართ?')">წაშლა</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#888780;">მომხმარებელი ვერ მოიძებნა</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?php echo $i; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>"
       style="padding:6px 12px;border-radius:8px;font-size:13px;text-decoration:none;border:1px solid <?php echo $i==$page?'#1D9E75':'#E8E6DF'; ?>;background:<?php echo $i==$page?'#1D9E75':'#fff'; ?>;color:<?php echo $i==$page?'#fff':'#444441'; ?>;">
      <?php echo $i; ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
