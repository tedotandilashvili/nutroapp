<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) redirect('/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((isset($_POST['email']) ? $_POST['email'] : ''));
    $password = (isset($_POST['password']) ? $_POST['password'] : '');

    if (empty($email) || empty($password)) {
        $error = 'გთხოვთ შეავსოთ ყველა ველი.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute(array($email));
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            setFlash('success', 'კეთილი იყოს თქვენი მობრძანება, ' . htmlspecialchars($user['name']) . '!');
            redirect('/dashboard.php');
        } else {
            $error = 'ელ.ფოსტა ან პაროლი არასწორია.';
        }
    }
}

renderHeader('შესვლა', '');
?>

<div class="auth-wrap">
  <div class="auth-logo">Nutro<span>App</span></div>
  <div class="auth-sub">ანგარიშში შესვლა</div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="">
      <div class="form-group">
        <label>ელ.ფოსტა</label>
        <input type="email" name="email" class="form-control" value="<?php echo sanitize((isset($_POST['email']) ? $_POST['email'] : '')); ?>" required autofocus>
      </div>
      <div class="form-group">
        <label>პაროლი</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
        შესვლა
      </button>
    </form>
  </div>

  <div class="auth-footer">
    ანგარიში არ გაქვთ? <a href="/register.php">რეგისტრაცია</a>
  </div>
</div>

<?php renderFooter(); ?>
