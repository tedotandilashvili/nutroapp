<?php
require_once __DIR__ . '/auth_admin.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/prices.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    if (adminLogin($u, $p)) {
        header('Location: /admin/prices.php');
        exit;
    }
    $error = 'მომხმარებელი ან პაროლი არასწორია.';
}
?><!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — NutroApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<div class="main-content" style="max-width:380px;">
  <div style="text-align:center;margin-bottom:2rem;">
    <div style="font-size:22px;font-weight:500;">NutroApp <span style="color:#1D9E75;">Admin</span></div>
    <div style="font-size:13px;color:var(--gray-400);margin-top:4px;">ფასების მართვა</div>
  </div>
  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <div class="card">
    <form method="POST">
      <div class="form-group">
        <label>მომხმარებელი</label>
        <input type="text" name="username" class="form-control" autofocus required>
      </div>
      <div class="form-group">
        <label>პაროლი</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">შესვლა</button>
    </form>
  </div>
</div>
</body></html>
