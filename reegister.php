<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) redirect('/dashboard.php');

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((isset($_POST['name']) ? $_POST['name'] : ''));
    $email    = trim((isset($_POST['email']) ? $_POST['email'] : ''));
    $password = (isset($_POST['password']) ? $_POST['password'] : '');
    $confirm  = (isset($_POST['confirm']) ? $_POST['confirm'] : '');

    if (empty($name))                          $errors[] = 'სახელი სავალდებულოა.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ელ.ფოსტა არასწორია.';
    if (strlen($password) < 6)                 $errors[] = 'პაროლი მინიმუმ 6 სიმბოლო უნდა იყოს.';
    if ($password !== $confirm)                $errors[] = 'პაროლები არ ემთხვევა.';

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute(array($email));
        if ($stmt->fetch()) {
            $errors[] = 'ეს ელ.ფოსტა უკვე რეგისტრირებულია.';
        } else {
            $ref_by = null;
            $ref_code_used = trim(isset($_POST['ref']) ? $_POST['ref'] : (isset($_GET['ref']) ? $_GET['ref'] : ''));
            if ($ref_code_used) {
                $ref_stmt = $db->prepare('SELECT id FROM users WHERE referral_code=?');
                $ref_stmt->execute(array($ref_code_used));
                $ref_owner = $ref_stmt->fetch();
                if ($ref_owner) $ref_by = $ref_owner['id'];
            }
            $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, created_at, referred_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array($name, $email, hashPassword($password), time(), $ref_by));
            $user_id = $db->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            require_once __DIR__ . '/includes/mailer.php';
            sendWelcomeEmail(array('name'=>$name,'email'=>$email));
            setFlash('success', 'კეთილი იყოს თქვენი მობრძანება, ' . htmlspecialchars($name) . '!');
            redirect('/profile.php');
        }
    }
}

renderHeader('რეგისტრაცია', '');
?>

<div class="auth-wrap">
  <div class="auth-logo">Nutro<span>App</span></div>
  <div class="auth-sub">ახალი ანგარიშის შექმნა</div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" action="">
      <div class="form-group">
        <label>სახელი</label>
        <input type="text" name="name" class="form-control" value="<?php echo sanitize((isset($_POST['name']) ? $_POST['name'] : '')); ?>" required>
      </div>
      <div class="form-group">
        <label>ელ.ფოსტა</label>
        <input type="email" name="email" class="form-control" value="<?php echo sanitize((isset($_POST['email']) ? $_POST['email'] : '')); ?>" required>
      </div>
      <div class="form-group">
        <label>პაროლი</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="form-group">
        <label>პაროლის დადასტურება</label>
        <input type="password" name="confirm" class="form-control" required>
      </div>
      <?php $ref_get = isset($_GET['ref']) ? sanitize($_GET['ref']) : ''; ?>
      <?php if ($ref_get): ?><input type="hidden" name="ref" value="<?php echo $ref_get; ?>"><?php endif; ?>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:.5rem;">
        რეგისტრაცია
      </button>
    </form>
  </div>

  <div class="auth-footer">
    უკვე გაქვთ ანგარიში? <a href="/login.php">შესვლა</a>
  </div>
</div>

<?php renderFooter(); ?>
