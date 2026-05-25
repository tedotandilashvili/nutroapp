<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company  = trim(isset($_POST['company'])  ? $_POST['company']  : '');
    $name     = trim(isset($_POST['name'])     ? $_POST['name']     : '');
    $email    = trim(isset($_POST['email'])    ? $_POST['email']    : '');
    $count    = (int)(isset($_POST['count'])   ? $_POST['count']    : 0);
    $message  = trim(isset($_POST['message'])  ? $_POST['message']  : '');

    if ($company && $name && $email && $count > 0) {
        $body = "<p><b>კომპანია:</b> $company</p>
                 <p><b>სახელი:</b> $name</p>
                 <p><b>ელ.ფოსტა:</b> $email</p>
                 <p><b>თანამშრომლები:</b> $count</p>
                 <p><b>შეტყობინება:</b> $message</p>";
        $html = emailTemplate('კორპორატიული მოთხოვნა', $body);
        sendEmail(SMTP_FROM, 'NutroApp Admin', 'B2B: '.$company.' ('.$count.' თანამშ.)', $html);
        $sent = true;
    } else {
        $error = 'გთხოვთ შეავსოთ ყველა სავალდებულო ველი.';
    }
}

$plans = array(
    array(
        'name'  => 'Starter',
        'users' => '10-25',
        'price' => 149,
        'features' => array('25 თანამშრომელი','7-დღიანი გეგმები','ადმინ პანელი','მომხმარებლების მართვა'),
    ),
    array(
        'name'  => 'Business',
        'users' => '25-100',
        'price' => 399,
        'popular' => true,
        'features' => array('100 თანამშრომელი','შეუზღუდავი გეგმები','ადმინ პანელი','პრიორიტეტული მხარდაჭერა','CSV ანგარიში'),
    ),
    array(
        'name'  => 'Enterprise',
        'users' => '100+',
        'price' => null,
        'features' => array('შეუზღუდავი მომხმარებელი','Custom branding','API წვდომა','დედიკირებული მენეჯერი','SLA guarantee'),
    ),
);

// Render without login required — marketing page
$header_started = false;
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>კორპორატიული გეგმა — NutroApp</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<nav class="navbar">
  <a class="nav-logo" href="/index.php">Nutro<span style="color:#1D9E75;font-style:italic;">App</span></a>
  <div class="nav-links">
    <a href="/pricing.php">გეგმები</a>
    <a href="/login.php">შესვლა</a>
  </div>
</nav>

<div style="max-width:800px;margin:5rem auto 3rem;padding:0 1rem;">

  <!-- Hero -->
  <div style="text-align:center;margin-bottom:3rem;">
    <div style="font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.12em;color:#1D9E75;margin-bottom:.75rem;">B2B</div>
    <h1 style="font-family:'DM Serif Display',serif;font-size:clamp(32px,5vw,52px);margin:0 0 1rem;line-height:1.1;">
      ჯანმრთელი<br><em style="color:#1D9E75;">გუნდი</em>
    </h1>
    <p style="font-size:16px;color:#888780;max-width:500px;margin:0 auto;">
      NutroApp-ი თქვენი კომპანიის თანამშრომლებისთვის — 
      ჯანმრთელი კვება, ნაკლები ავადმყოფობა, მეტი პროდუქტიულობა.
    </p>
  </div>

  <!-- Plans -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:3rem;">
    <?php foreach ($plans as $p): ?>
    <div style="background:#fff;border:<?php echo isset($p['popular'])?'2px solid #1D9E75':'1px solid #E8E6DF'; ?>;border-radius:16px;padding:1.5rem;position:relative;">
      <?php if (isset($p['popular'])): ?>
        <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:#1D9E75;color:#fff;font-size:11px;font-weight:500;padding:3px 12px;border-radius:99px;">პოპულარული</div>
      <?php endif; ?>
      <div style="font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:#888780;margin-bottom:.25rem;"><?php echo $p['name']; ?></div>
      <div style="font-size:14px;color:#444441;margin-bottom:1rem;"><?php echo $p['users']; ?> თანამშ.</div>
      <div style="margin-bottom:1.25rem;">
        <?php if ($p['price']): ?>
          <span style="font-size:32px;font-weight:500;"><?php echo $p['price']; ?></span>
          <span style="font-size:14px;color:#888780;"> ₾/თვე</span>
        <?php else: ?>
          <span style="font-size:22px;font-weight:500;">მოლაპარაკება</span>
        <?php endif; ?>
      </div>
      <ul style="list-style:none;padding:0;margin:0 0 1.25rem;display:flex;flex-direction:column;gap:6px;">
        <?php foreach ($p['features'] as $f): ?>
        <li style="font-size:12px;color:#444441;display:flex;gap:6px;align-items:flex-start;">
          <span style="color:#1D9E75;flex-shrink:0;">✓</span><?php echo $f; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <a href="#contact" style="display:block;text-align:center;padding:10px;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;background:<?php echo isset($p['popular'])?'#1D9E75':'transparent'; ?>;color:<?php echo isset($p['popular'])?'#fff':'#444441'; ?>;border:1px solid <?php echo isset($p['popular'])?'#1D9E75':'#E8E6DF'; ?>;">
        შეკვეთა
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Contact form -->
  <div id="contact" class="card">
    <div class="card-title">დაგვიკავშირდი</div>
    <?php if ($sent): ?>
      <div class="alert alert-success">✅ მოთხოვნა მიღებულია! 24 საათში დაგიკავშირდებით.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
      <form method="POST">
        <div class="grid-2">
          <div class="form-group">
            <label>კომპანიის სახელი *</label>
            <input type="text" name="company" class="form-control" required placeholder="მაგ. ABC კომპანია">
          </div>
          <div class="form-group">
            <label>თქვენი სახელი *</label>
            <input type="text" name="name" class="form-control" required placeholder="სახელი გვარი">
          </div>
          <div class="form-group">
            <label>ელ.ფოსტა *</label>
            <input type="email" name="email" class="form-control" required placeholder="name@company.ge">
          </div>
          <div class="form-group">
            <label>თანამშრომლების რაოდენობა *</label>
            <input type="number" name="count" class="form-control" required min="10" placeholder="მაგ. 50">
          </div>
        </div>
        <div class="form-group">
          <label>შეტყობინება</label>
          <textarea name="message" class="form-control" rows="3" placeholder="დამატებითი ინფო..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:12px 32px;">გაგზავნა</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<footer class="footer">
  <p>&copy; <?php echo date('Y'); ?> NutroApp — nutroapp.ge</p>
</footer>
</body>
</html>
