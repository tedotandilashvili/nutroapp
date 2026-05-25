<?php
function renderHeader($title = 'NutroApp', $active = '') {
    $user = isLoggedIn() ? getCurrentUser() : null;
    $unread = 0;
    if ($user) {
        try {
            $db = getDB();
            $s = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
            $s->execute(array($_SESSION['user_id']));
            $unread = (int)$s->fetchColumn();
        } catch(Exception $e) {}
    }
    $initial = $user ? mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'),'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#F5F5F0">
<title><?php echo htmlspecialchars($title); ?> &mdash; NutroApp</title>
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<script>if(localStorage.getItem('darkMode')==='1'){document.documentElement.style.background='#111110';document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('dark-mode');});}</script>
</head>
<body class="<?php echo $user ? 'has-bottom-nav' : ''; ?>">

<nav class="navbar">
  <a class="nav-logo" href="<?php echo $user ? '/dashboard.php' : '/index.php'; ?>">
    Nutro<span>App</span>
  </a>

  <?php if ($user): ?>
  <div class="nav-links desktop-only">
    <a href="/dashboard.php" class="<?php echo $active==='dashboard'?'active':''; ?>">მთავარი</a>
    <a href="/generate.php"  class="<?php echo $active==='generate'?'active':''; ?>">&#10024; ახალი</a>
    <a href="/history.php"   class="<?php echo $active==='history'?'active':''; ?>">ისტორია</a>
    <a href="/analyze.php"   class="<?php echo $active==='analyze'?'active':''; ?>">ანალიზი</a>
    <a href="/mood.php"   class="<?php echo $active==='mood'?'active':''; ?>">განწყობა</a>
    <a href="/pricing.php"   class="<?php echo $active==='pricing'?'active':''; ?>">გეგმები</a>
  </div>
  <div class="nav-right">
    <?php if ($unread > 0): ?>
    <a href="/notifications.php" class="nav-icon-btn" title="შეტყობინებები">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 8A6 6 0 006 8v5l-2 3h16l-2-3V8"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span class="nav-badge"><?php echo $unread > 9 ? '9+' : $unread; ?></span>
    </a>
    <?php endif; ?>
    <button id="dark-btn" onclick="toggleDarkMode()" class="nav-icon-btn desktop-only" title="Dark mode">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path id="dark-icon-path" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>
    <button class="hamburger" id="hamburger-btn" onclick="openMenu()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>

  <?php else: ?>
  <div class="nav-links">
    <a href="/login.php" class="<?php echo $active==='login'?'active':''; ?>">შესვლა</a>
    <a href="/register.php" class="btn btn-primary btn-sm">რეგისტრაცია</a>
  </div>
  <?php endif; ?>
</nav>

<?php if ($user): ?>
<div id="menu-overlay" onclick="closeMenu()"></div>
<div id="menu-panel">
  <div class="panel-header">
    <div class="panel-avatar"><?php echo $initial; ?></div>
    <div style="flex:1;min-width:0;">
      <div class="panel-name"><?php echo htmlspecialchars($user['name']); ?></div>
      <div class="panel-email"><?php echo htmlspecialchars($user['email']); ?></div>
    </div>
    <button class="panel-close" onclick="closeMenu()">
      <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
  </div>
  <?php
  $sections = array(
    'კვება' => array(
      array('/dashboard.php','dashboard','&#127968; მთავარი'),
      array('/generate.php', 'generate', '&#10024; ახალი გეგმა'),
      array('/history.php',  'history',  '&#128203; ისტორია'),
      array('/shopping.php', 'shopping', '&#128722; სასყიდლო სია'),
      array('/plan_pdf.php', '',         '&#128196; PDF ექსპორტი'),
    ),
    'ჯანმრთელობა' => array(
      array('/tracker.php',  'tracker',  '&#128202; ტრეკინგი'),
      array('/analyze.php',  'analyze',  '&#128293; კალ. ანალიზი'),
      array('/progress.php', 'progress', '&#128247; Progress ფოტო'),
      array('/mood.php',     'mood',     '&#129504; განწყობის ტრეკინგი'),
    ),
    'საზოგადოება' => array(
      array('/leaderboard.php','leaderboard','&#127942; Leaderboard'),
      array('/referral.php',   'referral',   '&#127873; მეგობრის მოყვანა'),
      array('/chat.php',       'chat',       '&#128172; პრემიუმ ჩათი'),
    ),
    'ანგარიში' => array(
      array('/profile.php',       'profile',       '&#128100; პროფილი'),
      array('/pricing.php',       'pricing',       '&#128176; გამოწერა'),
      array('/notifications.php', 'notifications', '&#128276; შეტყობინებები'),
      array('/corporate.php',     '',              '&#127970; B2B'),
    ),
  );
  foreach ($sections as $sec => $items):
  ?>
  <div class="panel-section"><?php echo $sec; ?></div>
  <?php foreach ($items as $item):
    $is_active = isset($item[1]) && $item[1] && $active === $item[1];
  ?>
  <a href="<?php echo $item[0]; ?>" onclick="closeMenu()"
     class="panel-item <?php echo $is_active ? 'active' : ''; ?>"
     style="<?php echo $is_active ? 'color:var(--green);background:var(--green-soft);' : ''; ?>">
    <?php echo $item[2]; ?>
    <?php if (isset($item[1]) && $item[1] === 'notifications' && $unread > 0): ?>
      <span style="margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px;"><?php echo $unread; ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; endforeach; ?>
  <div class="panel-footer">
    <button onclick="toggleDarkMode()" class="panel-btn panel-btn-dark" id="panel-dark-btn">
      &#9790; <span id="panel-dark-label">Dark Mode</span>
    </button>
    <a href="/logout.php" class="panel-btn panel-btn-logout">&#10148; გასვლა</a>
  </div>
</div>

<nav class="bottom-nav">
  <a href="/dashboard.php" class="bn-item <?php echo $active==='dashboard'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
    <span>მთავარი</span>
  </a>
  <a href="/history.php" class="bn-item <?php echo $active==='history'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
    <span>გეგმები</span>
  </a>
  <a href="/generate.php" class="bn-item <?php echo $active==='generate'?'bn-active':''; ?>">
    <div class="bn-center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></div>
    <span>ახალი</span>
  </a>
  <a href="/tracker.php" class="bn-item <?php echo $active==='tracker'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-8 4 4 5-9"/></svg>
    <span>ტრეკინგი</span>
  </a>
  <button onclick="openMenu()" class="bn-item">
    <div style="width:28px;height:28px;border-radius:50%;background:var(--green-soft);border:1.5px solid rgba(22,163,112,.3);display:flex;align-items:center;justify-content:center;font-family:Outfit,sans-serif;font-size:13px;font-weight:700;color:var(--green-2);margin-bottom:2px;"><?php echo $initial; ?></div>
    <span>მენიუ</span>
  </button>
</nav>
<?php endif; ?>

<main class="main-content">
<?php
    $flash = getFlash();
    if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> flash"><?php echo htmlspecialchars($flash['message']); ?></div>
<?php endif;
}

function renderFooter() { ?>
</main>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> NutroApp &middot; nutroapp.ge</p></footer>
<script>
var _mo = false;
function openMenu() {
  var p=document.getElementById('menu-panel'),o=document.getElementById('menu-overlay'),h=document.getElementById('hamburger-btn');
  if(!p)return;
  p.classList.add('open'); o.style.display='block';
  setTimeout(function(){o.classList.add('visible');},10);
  if(h)h.classList.add('open');
  document.body.style.overflow='hidden'; _mo=true;
}
function closeMenu() {
  var p=document.getElementById('menu-panel'),o=document.getElementById('menu-overlay'),h=document.getElementById('hamburger-btn');
  if(!p)return;
  p.classList.remove('open'); o.classList.remove('visible');
  setTimeout(function(){o.style.display='none';},250);
  if(h)h.classList.remove('open');
  document.body.style.overflow=''; _mo=false;
}
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&_mo)closeMenu();});

var _sunPath='M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42M12 17a5 5 0 100-10 5 5 0 000 10z';
var _moonPath='M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z';
function toggleDarkMode() {
  var on=document.body.classList.toggle('dark-mode');
  localStorage.setItem('darkMode',on?'1':'0'); _updateDark(on);
}
function _updateDark(on) {
  var p=document.getElementById('dark-icon-path'); if(p)p.setAttribute('d',on?_sunPath:_moonPath);
  var l=document.getElementById('panel-dark-label'); if(l)l.textContent=on?'Light Mode':'Dark Mode';
  var b=document.getElementById('panel-dark-btn'); if(b)b.childNodes[0].textContent=on?'&#x2600;':'&#x263E;';
}
document.addEventListener('DOMContentLoaded',function(){
  var on=localStorage.getItem('darkMode')==='1';
  if(on)document.body.classList.add('dark-mode');
  _updateDark(on);
  var f=document.querySelector('.flash');
  if(f)setTimeout(function(){f.style.transition='all .4s';f.style.opacity='0';setTimeout(function(){f&&f.remove();},400);},3500);
});
if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js').catch(function(){});
</script>
</body></html>
<?php
}