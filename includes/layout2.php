<?php
function renderHeader($title = 'NutroApp', $active = '') {
    $user = isLoggedIn() ? getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#1D9E75">
<title><?php echo htmlspecialchars($title); ?> — NutroApp</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<script>
// Apply dark mode before render to prevent flash
if(localStorage.getItem('darkMode')==='1'){document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('dark-mode');});}
</script>
</head>
<body class="<?php echo $user ? 'has-bottom-nav' : ''; ?> <?php echo isset($_COOKIE['lang']) && $_COOKIE['lang']==='en' ? 'lang-en' : ''; ?>">

<!-- ── DESKTOP NAVBAR ── -->
<nav class="navbar" id="navbar">
  <a class="nav-logo" href="/index.php">Nutro<span>App</span></a>

  <?php if ($user): ?>
  <!-- Hamburger (mobile only) -->
  <button class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="მენიუ">
    <span></span><span></span><span></span>
  </button>
  <!-- Desktop links -->
  <div class="nav-links" id="nav-links">
    <a href="/dashboard.php" class="<?php echo $active==='dashboard'?'active':''; ?>">მთავარი</a>
    <a href="/generate.php"  class="<?php echo $active==='generate'?'active':''; ?>">ახალი გეგმა</a>
    <a href="/history.php"   class="<?php echo $active==='history'?'active':''; ?>">ისტორია</a>
    <a href="/analyze.php"    class="<?php echo $active==='analyze'?'active':''; ?>">კალორია 🔥</a>
    <a href="/leaderboard.php" class="<?php echo $active==='leaderboard'?'active':''; ?>">🏆</a>
    <a href="/pricing.php"   class="<?php echo $active==='pricing'?'active':''; ?>">გეგმები</a>
    <a href="/tracker.php"   class="<?php echo $active==='tracker'?'active':''; ?>">📊</a>
    <a href="/notifications.php" class="<?php echo $active==='notifications'?'active':''; ?>" style="position:relative;">
      🔔<?php
      $db=getDB();$nc=$db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
      $nc->execute(array($_SESSION['user_id']));$nc=(int)$nc->fetchColumn();
      if($nc>0) echo '<span style="position:absolute;top:-4px;right:-6px;background:#D85A30;color:#fff;font-size:9px;border-radius:99px;padding:1px 4px;font-family:sans-serif;">'.($nc>9?'9+':$nc).'</span>';
      ?>
    </a>
    <a href="/profile.php"   class="<?php echo $active==='profile'?'active':''; ?>">პროფილი</a>
    <button id="dark-btn" onclick="toggleDarkMode()" style="background:none;border:none;cursor:pointer;font-size:16px;padding:4px;" title="Dark mode">🌙</button>
    <?php $cur_lang = isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'ka'; ?>
    <button onclick="setLang('<?php echo $cur_lang==='ka'?'en':'ka'; ?>')"
            style="background:none;border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;font-weight:500;padding:4px 8px;font-family:inherit;color:var(--gray-700);">
      <?php echo $cur_lang==='ka' ? 'EN' : 'KA'; ?>
    </button>
    <a href="/logout.php" class="btn-logout">გასვლა</a>
  </div>
  <?php else: ?>
  <div class="nav-links">
    <a href="/login.php"    class="<?php echo $active==='login'?'active':''; ?>">შესვლა</a>
    <a href="/register.php" class="btn-register">რეგისტრაცია</a>
  </div>
  <?php endif; ?>
</nav>

<!-- ── MOBILE SLIDE MENU ── -->
<?php if ($user): ?>
<div class="mobile-overlay" id="mobile-overlay" onclick="toggleMenu()"></div>
<div class="mobile-menu" id="mobile-menu">
  <div class="mobile-menu-head">
    <span class="nav-logo" style="font-size:20px;">Nutro<span style="color:#1D9E75;font-style:italic;">App</span></span>
    <button class="mobile-menu-close" onclick="toggleMenu()">✕</button>
  </div>
  <div class="mobile-menu-user">
    <div class="mobile-avatar"><?php echo mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'),'UTF-8'); ?></div>
    <div>
      <div style="font-weight:500;font-size:14px;"><?php echo htmlspecialchars($user['name']); ?></div>
      <div style="font-size:12px;color:var(--gray-400);"><?php echo htmlspecialchars($user['email']); ?></div>
    </div>
  </div>
  <nav class="mobile-menu-nav">
    <a href="/dashboard.php" class="mmn-item <?php echo $active==='dashboard'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="2" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="11" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="11" width="7" height="7" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
      მთავარი
    </a>
    <a href="/generate.php" class="mmn-item <?php echo $active==='generate'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 7v6M7 10h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      ახალი გეგმა
    </a>
    <a href="/analyze.php" class="mmn-item <?php echo $active==='analyze'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 17l4-6 4 3 4-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      კალორია 🔥
    </a>
    <a href="/history.php" class="mmn-item <?php echo $active==='history'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      ისტორია
    </a>
    <a href="/pricing.php" class="mmn-item <?php echo $active==='pricing'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 7v1.5m0 3V13m-1.5-5.5c0-.828.672-1.5 1.5-1.5s1.5.672 1.5 1.5S10.828 9 10 9s-1.5.672-1.5 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      გამოწერა
    </a>
    <a href="/tracker.php" class="mmn-item <?php echo $active==='tracker'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 17l4-6 4 3 4-8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      ტრეკინგი 📊
    </a>
    <a href="/shopping.php" class="mmn-item <?php echo $active==='shopping'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 3h14l-2 10H5L3 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="7" cy="17" r="1.5" fill="currentColor"/><circle cx="13" cy="17" r="1.5" fill="currentColor"/></svg>
      სასყიდლო სია 🛒
    </a>
    <a href="/progress.php" class="mmn-item <?php echo $active==='progress'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.5"/><circle cx="7" cy="7" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 14l4-4 3 3 4-6 5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Progress ფოტო 📸
    </a>
    <a href="/leaderboard.php" class="mmn-item <?php echo $active==='leaderboard'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 3l2 4 5 .7-3.5 3.4.8 5L10 13.4l-4.3 2.7.8-5L3 7.7 8 7z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
      Leaderboard 🏆
    </a>
    <a href="/referral.php" class="mmn-item <?php echo $active==='referral'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 17c0-2.761 2.239-4 5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 11v6m-3-3h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      მოიყვანე მეგობარი 🎁
    </a>
    <a href="/profile.php" class="mmn-item <?php echo $active==='profile'?'mmn-active':''; ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M4 17c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      პროფილი
    </a>
  </nav>
  <div style="display:flex;gap:8px;padding:10px 1.25rem;border-top:1px solid var(--gray-100);">
    <button onclick="toggleDarkMode()" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--gray-200);background:none;cursor:pointer;font-size:13px;font-family:inherit;">🌙 Dark Mode</button>
  </div>
  <a href="/logout.php" class="mobile-logout">
    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M7 3H3v12h4M12 12l3-3-3-3M15 9H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    გასვლა
  </a>
</div>

<!-- ── BOTTOM NAV (mobile) ── -->
<nav class="bottom-nav">
  <a href="/dashboard.php" class="bn-item <?php echo $active==='dashboard'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="2" y="2" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="12" y="2" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="2" y="12" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="12" y="12" width="8" height="8" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
    <span>მთავარი</span>
  </a>
  <a href="/history.php" class="bn-item <?php echo $active==='history'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><rect x="3" y="3" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M7 8h8M7 11h8M7 14h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <span>გეგმები</span>
  </a>
  <a href="/generate.php" class="bn-item bn-center <?php echo $active==='generate'?'bn-active':''; ?>">
    <div class="bn-center-btn">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </div>
    <span>ახალი</span>
  </a>
  <a href="/leaderboard.php" class="bn-item <?php echo $active==='leaderboard'?'bn-active':''; ?>">
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 3l2.5 5 5.5.8-4 3.9 1 5.5L11 15.5l-5 2.7 1-5.5-4-3.9 5.5-.8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
    <span>Top</span>
  </a>
  <a href="#" class="bn-item" onclick="toggleMenu();return false;">
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><circle cx="11" cy="8" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M5 19c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <span>მენიუ</span>
  </a>
</nav>
<?php endif; ?>

<main class="main-content">
<?php
    $flash = getFlash();
    if ($flash): ?>
  <div class="alert alert-<?php echo $flash['type']; ?>">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
<?php endif;
}

function renderFooter() {
?>
</main>
<footer class="footer">
  <p>&copy; <?php echo date('Y'); ?> NutroApp — პერსონალური კვების გეგმა საქართველოში</p>
</footer>

<script>
function toggleMenu() {
  var menu    = document.getElementById('mobile-menu');
  var overlay = document.getElementById('mobile-overlay');
  if (!menu) return;
  var open = menu.classList.toggle('open');
  overlay.classList.toggle('open', open);
  document.body.style.overflow = open ? 'hidden' : '';
}
// Close menu on resize to desktop
window.addEventListener('resize', function() {
  if (window.innerWidth > 768) {
    var menu = document.getElementById('mobile-menu');
    var overlay = document.getElementById('mobile-overlay');
    if (menu) menu.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
  }
});
</script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(function(){});
}
// Dark mode — init already done in <head>
function toggleDarkMode() {
  var on = document.body.classList.toggle('dark-mode');
  localStorage.setItem('darkMode', on ? '1' : '0');
  var btn = document.getElementById('dark-btn');
  if (btn) btn.textContent = on ? '☀️' : '🌙';
}
// Set correct icon on page load
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('dark-btn');
  if (btn && localStorage.getItem('darkMode') === '1') btn.textContent = '☀️';
});
// Language
function setLang(lang) {
  localStorage.setItem('lang', lang);
  document.cookie = 'lang=' + lang + ';path=/;max-age=31536000';
  location.reload();
}
</script>
</body>
</html>
<?php
}