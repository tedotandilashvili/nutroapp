<?php
// NutroApp - Admin Auth Helper
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function requireAdmin() {
    if (session_status() == PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

function adminLogin($username, $password) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
    $stmt->execute(array($username));
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $username;
        return true;
    }
    return false;
}

function renderAdminHeader($title = 'Admin', $active = '') {
    $nav_items = array(
        'dashboard' => array('url'=>'/admin/index.php',    'label'=>'Dashboard'),
        'users'     => array('url'=>'/admin/users.php',    'label'=>'მომხმარებლები'),
        'plans'     => array('url'=>'/admin/plans.php',    'label'=>'გეგმები'),
        'prices'    => array('url'=>'/admin/prices.php',   'label'=>'ფასები'),
    );
    ?><!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($title); ?> — NutroApp Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/main.css">
<style>
*{box-sizing:border-box;}
body{margin:0;background:#F4F3EE;font-family:'DM Sans',sans-serif;}
.adm-layout{display:flex;min-height:100vh;}
.adm-sidebar{width:220px;background:#1A1A18;flex-shrink:0;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;}
.adm-brand{padding:1.25rem 1.5rem;border-bottom:1px solid #2C2C2A;}
.adm-brand-name{font-size:18px;font-weight:500;color:#fff;text-decoration:none;display:block;}
.adm-brand-name span{color:#1D9E75;font-style:italic;}
.adm-brand-sub{font-size:11px;color:#5F5E5A;margin-top:2px;}
.adm-nav{padding:1rem 0;flex:1;}
.adm-nav a{display:flex;align-items:center;gap:10px;padding:9px 1.5rem;font-size:13px;color:#888780;text-decoration:none;transition:background .12s,color .12s;border-left:3px solid transparent;}
.adm-nav a:hover{background:#242422;color:#D3D1C7;}
.adm-nav a.active{background:#242422;color:#fff;border-left-color:#1D9E75;}
.adm-nav-icon{width:16px;height:16px;flex-shrink:0;opacity:.7;}
.adm-nav a.active .adm-nav-icon{opacity:1;}
.adm-nav-section{padding:8px 1.5rem 4px;font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#5F5E5A;margin-top:8px;}
.adm-footer-nav{padding:1rem 1.5rem;border-top:1px solid #2C2C2A;}
.adm-footer-nav a{display:block;font-size:12px;color:#5F5E5A;text-decoration:none;margin-bottom:6px;}
.adm-footer-nav a:hover{color:#888780;}
.adm-footer-nav a.danger{color:#993C1D;}
.adm-footer-nav a.danger:hover{color:#E24B4A;}
.adm-main{flex:1;min-width:0;}
.adm-topbar{background:#fff;border-bottom:1px solid #E8E6DF;padding:0 2rem;height:52px;display:flex;align-items:center;justify-content:space-between;}
.adm-topbar-title{font-size:15px;font-weight:500;color:#1A1A18;}
.adm-topbar-meta{font-size:12px;color:#888780;}
.adm-content{padding:2rem;}
.adm-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;}
.adm-stat{background:#fff;border:1px solid #E8E6DF;border-radius:12px;padding:1.25rem 1.5rem;}
.adm-stat-val{font-size:28px;font-weight:500;color:#1A1A18;line-height:1;margin-bottom:4px;}
.adm-stat-lbl{font-size:12px;color:#888780;}
.adm-stat-delta{font-size:12px;font-weight:500;margin-top:6px;}
.adm-stat-delta.up{color:#0F6E56;}
.adm-stat-delta.down{color:#993C1D;}
.adm-card{background:#fff;border:1px solid #E8E6DF;border-radius:12px;margin-bottom:1rem;}
.adm-card-head{padding:1rem 1.5rem;border-bottom:1px solid #F1EFE8;display:flex;align-items:center;justify-content:space-between;}
.adm-card-title{font-size:14px;font-weight:500;color:#1A1A18;}
.adm-card-body{padding:0;}
.adm-table{width:100%;border-collapse:collapse;font-size:13px;}
.adm-table th{text-align:left;padding:10px 1.5rem;font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.06em;color:#888780;border-bottom:1px solid #F1EFE8;background:#FAFAF8;}
.adm-table td{padding:11px 1.5rem;border-bottom:1px solid #F8F7F2;color:#444441;vertical-align:middle;}
.adm-table tr:last-child td{border-bottom:none;}
.adm-table tr:hover td{background:#FAFAF8;}
.adm-badge{display:inline-block;font-size:11px;font-weight:500;padding:3px 8px;border-radius:99px;}
.badge-green{background:#E1F5EE;color:#0F6E56;}
.badge-amber{background:#FAEEDA;color:#854F0B;}
.badge-red{background:#FCEBEB;color:#A32D2D;}
.badge-blue{background:#E6F1FB;color:#185FA5;}
.badge-gray{background:#F1EFE8;color:#5F5E5A;}
.adm-btn{display:inline-block;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;border:1px solid transparent;text-decoration:none;font-family:'DM Sans',sans-serif;transition:opacity .15s;}
.adm-btn-sm{padding:4px 10px;font-size:11px;}
.adm-btn-primary{background:#1D9E75;color:#fff;border-color:#1D9E75;}
.adm-btn-primary:hover{opacity:.85;}
.adm-btn-danger{background:#FCEBEB;color:#A32D2D;border-color:#F7C1C1;}
.adm-btn-danger:hover{background:#F7C1C1;}
.adm-btn-outline{background:#fff;color:#444441;border-color:#E8E6DF;}
.adm-btn-outline:hover{border-color:#1D9E75;color:#1D9E75;}
.adm-search{padding:7px 12px;border:1px solid #E8E6DF;border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;width:220px;outline:none;}
.adm-search:focus{border-color:#1D9E75;}
.price-badge{font-size:10px;padding:2px 6px;border-radius:4px;font-weight:500;vertical-align:middle;}
.badge-ai{background:#FAEEDA;color:#854F0B;}
.badge-manual{background:#E1F5EE;color:#0F6E56;}
@media(max-width:720px){.adm-sidebar{display:none;}.adm-content{padding:1rem;}}
</style>
</head>
<body>
<div class="adm-layout">
<aside class="adm-sidebar">
  <div class="adm-brand">
    <a class="adm-brand-name" href="/admin/">Nutro<span>App</span></a>
    <div class="adm-brand-sub">Admin Panel</div>
  </div>
  <nav class="adm-nav">
    <div class="adm-nav-section">მთავარი</div>
    <a href="/admin/index.php" class="<?php echo $active==='dashboard'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="9" y="1" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="1" y="9" width="6" height="6" rx="1.5" fill="currentColor"/><rect x="9" y="9" width="6" height="6" rx="1.5" fill="currentColor"/></svg>
      Dashboard
    </a>
    <div class="adm-nav-section">ანალიტიკა</div>
    <a href="/admin/finance.php" class="<?php echo $active==='finance'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><path d="M2 12l3-4 3 2 3-5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 14h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      ფინანსები
    </a>
    <div class="adm-nav-section">მართვა</div>
    <a href="/admin/users.php" class="<?php echo $active==='users'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      მომხმარებლები
    </a>
    <a href="/admin/plans.php" class="<?php echo $active==='plans'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5 6h6M5 9h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      გეგმები
    </a>
    <a href="/admin/payments.php" class="<?php echo $active==='payments'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="4" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M1 7h14" stroke="currentColor" stroke-width="1.5"/><circle cx="4" cy="10" r="1" fill="currentColor"/></svg>
      გადახდები
    </a>
    <a href="/admin/subscriptions.php" class="<?php echo $active==='subscriptions'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="4" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M4 4V3a2 2 0 014 0v1" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8.5" r="1" fill="currentColor"/></svg>
      გამოწერები
    </a>
    <a href="/admin/stores.php" class="<?php echo $active==='stores'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><path d="M2 2h12l-1.5 8H3.5L2 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="6" cy="14" r="1" fill="currentColor"/><circle cx="11" cy="14" r="1" fill="currentColor"/></svg>
      მაღაზიები
    </a>
    <a href="/admin/prices.php" class="<?php echo $active==='prices'?'active':''; ?>">
      <svg class="adm-nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v1.5M8 9.5V11M6.5 7.5c0-.828.672-1.5 1.5-1.5s1.5.672 1.5 1.5S8.828 9 8 9s-1.5.672-1.5 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      ფასები
    </a>
  </nav>
  <div class="adm-footer-nav">
    <a href="/index.php">&#8592; საიტი</a>
    <a href="/admin/test_email.php">📧 ელ.ფოსტის ტესტი</a>
    <a href="/admin/export.php">📊 CSV ექსპორტი</a>
    <a href="/admin/logout.php" class="danger">გასვლა</a>
  </div>
</aside>
<div class="adm-main">
  <div class="adm-topbar">
    <div class="adm-topbar-title"><?php echo htmlspecialchars($title); ?></div>
    <div class="adm-topbar-meta"><?php echo isset($_SESSION['admin_username']) ? sanitize($_SESSION['admin_username']) : ''; ?> &middot; <?php echo date('d/m/Y H:i'); ?></div>
  </div>
  <div class="adm-content">
<?php
    $flash = getFlash();
    if ($flash) echo '<div class="alert alert-'.$flash['type'].'" style="margin-bottom:1rem;">'.htmlspecialchars($flash['message']).'</div>';
}

function renderAdminFooter() {
    echo '</div></div></div></body></html>';
}
