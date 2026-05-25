<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NutroApp — პერსონალური კვების გეგმა</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --black: #0A0A08;
  --white: #F5F3EE;
  --green: #1D9E75;
  --green-dark: #0F6E56;
  --green-pale: #E1F5EE;
  --sand: #F0EDE4;
  --sand-dark: #D9D4C7;
  --text: #1A1916;
  --muted: #7A786F;
}

html { scroll-behavior: smooth; }

body {
  font-family: 'Syne', sans-serif;
  background: var(--white);
  color: var(--text);
  overflow-x: hidden;
}

/* ── NAV ── */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  padding: 1.25rem 3rem;
  display: flex; align-items: center; justify-content: space-between;
  mix-blend-mode: normal;
}
.nav::before {
  content: '';
  position: absolute; inset: 0;
  background: rgba(245,243,238,.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(26,25,22,.06);
}
.nav-logo {
  position: relative;
  font-family: 'Playfair Display', serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--text);
  text-decoration: none;
  letter-spacing: -.3px;
}
.nav-logo em { font-style: italic; color: var(--green); }
.nav-links {
  position: relative;
  display: flex; align-items: center; gap: 2rem;
}
.nav-links a {
  font-size: 13px;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  letter-spacing: .04em;
  transition: color .2s;
}
.nav-links a:hover { color: var(--text); }
.nav-cta {
  background: var(--text) !important;
  color: var(--white) !important;
  padding: 8px 20px;
  border-radius: 99px;
  font-size: 13px !important;
  font-weight: 600 !important;
  letter-spacing: .03em !important;
  transition: background .2s, transform .1s !important;
}
.nav-cta:hover { background: var(--green-dark) !important; transform: translateY(-1px); }

/* ── HERO ── */
.hero {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  position: relative;
  overflow: hidden;
}

.hero-left {
  padding: 140px 4rem 5rem 3rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  z-index: 2;
}

.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--green-dark);
  margin-bottom: 2rem;
}
.hero-tag::before {
  content: '';
  width: 24px; height: 1px;
  background: var(--green);
}

.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(48px, 5vw, 72px);
  font-weight: 700;
  line-height: 1.05;
  letter-spacing: -1.5px;
  color: var(--text);
  margin-bottom: 1.5rem;
}
.hero-title em {
  font-style: italic;
  color: var(--green);
}

.hero-sub {
  font-size: 16px;
  line-height: 1.7;
  color: var(--muted);
  max-width: 420px;
  margin-bottom: 2.5rem;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-main {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--text);
  color: var(--white);
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 600;
  padding: 14px 28px;
  border-radius: 99px;
  text-decoration: none;
  letter-spacing: .02em;
  transition: background .2s, transform .15s;
}
.btn-main:hover { background: var(--green-dark); transform: translateY(-2px); }
.btn-main svg { transition: transform .2s; }
.btn-main:hover svg { transform: translateX(3px); }

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  padding: 14px 4px;
  border-bottom: 1px solid transparent;
  transition: color .2s, border-color .2s;
}
.btn-ghost:hover { color: var(--text); border-color: var(--text); }

.hero-stats {
  display: flex;
  gap: 2rem;
  margin-top: 3.5rem;
  padding-top: 2rem;
  border-top: 1px solid var(--sand-dark);
}
.stat-num {
  font-family: 'Playfair Display', serif;
  font-size: 28px;
  font-weight: 700;
  color: var(--text);
  line-height: 1;
  margin-bottom: 3px;
}
.stat-lbl {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--muted);
}

/* ── HERO RIGHT ── */
.hero-right {
  background: var(--black);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero-visual {
  position: relative;
  width: 340px;
}

/* Floating card */
.float-card {
  background: #181815;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px;
  padding: 1.5rem;
  color: #fff;
  animation: floatUp 3s ease-in-out infinite alternate;
}
@keyframes floatUp {
  from { transform: translateY(0px); }
  to   { transform: translateY(-12px); }
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}
.card-title-sm {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.4);
}
.card-badge {
  background: rgba(29,158,117,.2);
  color: #5DCAA5;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 99px;
  letter-spacing: .06em;
}

.meal-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
}
.meal-row:last-of-type { border-bottom: none; }
.meal-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-right: 10px;
}
.meal-info { flex: 1; }
.meal-name { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.85); }
.meal-ing { font-size: 11px; color: rgba(255,255,255,.3); margin-top: 1px; }
.meal-kcal { font-size: 12px; font-weight: 600; color: rgba(255,255,255,.5); }

.card-total {
  margin-top: 1rem;
  padding: 10px 14px;
  background: rgba(29,158,117,.12);
  border-radius: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card-total-lbl { font-size: 11px; color: rgba(255,255,255,.4); font-weight: 500; }
.card-total-val { font-size: 16px; font-weight: 700; color: #5DCAA5; }

/* Price badge floating */
.price-badge {
  position: absolute;
  top: -20px;
  right: -30px;
  background: var(--green);
  color: #fff;
  border-radius: 14px;
  padding: 10px 16px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .06em;
  animation: floatUp 2.5s ease-in-out 1s infinite alternate;
  white-space: nowrap;
}

.store-badge {
  position: absolute;
  bottom: -20px;
  left: -20px;
  background: #222220;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 12px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  animation: floatUp 3.5s ease-in-out .5s infinite alternate;
}
.store-dot { width: 8px; height: 8px; background: var(--green); border-radius: 50%; }
.store-text { font-size: 11px; color: rgba(255,255,255,.6); font-weight: 500; }

/* BG decoration */
.hero-right::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(29,158,117,.15) 0%, transparent 70%);
  top: 50%; left: 50%;
  transform: translate(-50%,-50%);
  pointer-events: none;
}

/* Grid lines decoration */
.hero-right::after {
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

/* ── FEATURES ── */
.features {
  padding: 7rem 3rem;
  background: var(--sand);
}

.section-eyebrow {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--green-dark);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-eyebrow::before {
  content: '';
  width: 20px; height: 1px;
  background: var(--green);
}

.section-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(36px, 4vw, 52px);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -1px;
  color: var(--text);
  max-width: 560px;
  margin-bottom: 4rem;
}
.section-title em { font-style: italic; color: var(--green); }

.feat-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5px;
  background: var(--sand-dark);
  border: 1.5px solid var(--sand-dark);
  border-radius: 20px;
  overflow: hidden;
}

.feat-item {
  background: var(--sand);
  padding: 2.5rem 2rem;
  transition: background .2s;
}
.feat-item:hover { background: var(--white); }

.feat-num {
  font-family: 'Playfair Display', serif;
  font-size: 11px;
  font-weight: 400;
  font-style: italic;
  color: var(--green);
  margin-bottom: 1.25rem;
  letter-spacing: .04em;
}

.feat-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: var(--green-pale);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1rem;
}
.feat-icon svg { width: 20px; height: 20px; }

.feat-title {
  font-size: 17px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: .5rem;
  line-height: 1.3;
}

.feat-desc {
  font-size: 13px;
  line-height: 1.65;
  color: var(--muted);
}

/* ── HOW IT WORKS ── */
.how {
  padding: 7rem 3rem;
  background: var(--black);
  color: #fff;
  position: relative;
  overflow: hidden;
}

.how::before {
  content: '';
  position: absolute;
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(29,158,117,.08) 0%, transparent 70%);
  top: 50%; right: -100px;
  transform: translateY(-50%);
  pointer-events: none;
}

.how .section-eyebrow { color: #5DCAA5; }
.how .section-eyebrow::before { background: #5DCAA5; }
.how .section-title { color: #fff; }

.steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 20px;
  overflow: hidden;
  margin-top: 4rem;
}

.step {
  background: var(--black);
  padding: 2.5rem 1.75rem;
  position: relative;
  transition: background .2s;
}
.step:hover { background: #111110; }

.step-num {
  font-family: 'Playfair Display', serif;
  font-size: 48px;
  font-weight: 700;
  font-style: italic;
  color: rgba(255,255,255,.06);
  line-height: 1;
  margin-bottom: 1.25rem;
}

.step-title {
  font-size: 15px;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  margin-bottom: .5rem;
}

.step-desc {
  font-size: 13px;
  line-height: 1.6;
  color: rgba(255,255,255,.35);
}

.step-arrow {
  position: absolute;
  top: 2.5rem; right: 1.75rem;
  color: rgba(255,255,255,.12);
  font-size: 18px;
}

/* ── PRICING TEASER ── */
.pricing-teaser {
  padding: 7rem 3rem;
  background: var(--white);
}

.plan-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-top: 4rem;
}

.plan-card {
  border: 1px solid var(--sand-dark);
  border-radius: 20px;
  padding: 2rem;
  transition: transform .2s, border-color .2s;
  position: relative;
}
.plan-card:hover { transform: translateY(-4px); border-color: var(--green); }
.plan-card.featured {
  background: var(--black);
  border-color: var(--black);
  color: #fff;
}

.plan-name {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: .5rem;
}
.plan-card.featured .plan-name { color: rgba(255,255,255,.4); }

.plan-ka { font-size: 18px; font-weight: 600; margin-bottom: 1.25rem; }

.plan-price-big {
  font-family: 'Playfair Display', serif;
  font-size: 42px;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 1.5rem;
}
.plan-price-big span { font-size: 18px; font-weight: 400; color: var(--muted); }
.plan-card.featured .plan-price-big span { color: rgba(255,255,255,.4); }

.plan-feature {
  font-size: 12px;
  color: var(--muted);
  padding: 6px 0;
  border-bottom: 1px solid var(--sand-dark);
  display: flex;
  gap: 8px;
  align-items: center;
}
.plan-card.featured .plan-feature { color: rgba(255,255,255,.5); border-color: rgba(255,255,255,.08); }
.plan-feature:last-of-type { border-bottom: none; }
.plan-feature::before { content: '—'; color: var(--green); font-size: 10px; flex-shrink: 0; }
.plan-card.featured .plan-feature::before { color: #5DCAA5; }

.plan-btn {
  display: block;
  margin-top: 1.5rem;
  padding: 11px;
  border-radius: 99px;
  text-align: center;
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: .03em;
  border: 1px solid var(--sand-dark);
  color: var(--text);
  transition: all .15s;
}
.plan-btn:hover { background: var(--text); color: var(--white); border-color: var(--text); }
.plan-card.featured .plan-btn {
  background: var(--green);
  border-color: var(--green);
  color: #fff;
}
.plan-card.featured .plan-btn:hover { background: var(--green-dark); border-color: var(--green-dark); }

/* ── CTA STRIP ── */
.cta-strip {
  padding: 5rem 3rem;
  background: var(--green);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
  flex-wrap: wrap;
}

.cta-text {
  font-family: 'Playfair Display', serif;
  font-size: clamp(28px, 3.5vw, 42px);
  font-weight: 700;
  font-style: italic;
  color: #fff;
  line-height: 1.2;
  max-width: 560px;
}

.btn-cta-white {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: #fff;
  color: var(--green-dark);
  font-family: 'Syne', sans-serif;
  font-size: 14px;
  font-weight: 700;
  padding: 15px 32px;
  border-radius: 99px;
  text-decoration: none;
  white-space: nowrap;
  transition: transform .15s, box-shadow .15s;
}
.btn-cta-white:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.15); }

/* ── FOOTER ── */
.footer {
  background: var(--black);
  color: rgba(255,255,255,.35);
  padding: 2rem 3rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 12px;
  letter-spacing: .04em;
}
.footer-logo {
  font-family: 'Playfair Display', serif;
  font-size: 16px;
  font-weight: 700;
  color: rgba(255,255,255,.6);
  text-decoration: none;
}
.footer-logo em { font-style: italic; color: #5DCAA5; }

/* ── ANIMATIONS ── */
.reveal {
  opacity: 0;
  transform: translateY(28px);
  transition: opacity .7s ease, transform .7s ease;
}
.reveal.visible {
  opacity: 1;
  transform: none;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .hero { grid-template-columns: 1fr; }
  .hero-right { min-height: 400px; }
  .feat-grid { grid-template-columns: 1fr; }
  .steps { grid-template-columns: 1fr 1fr; }
  .plan-row { grid-template-columns: 1fr; }
  .nav { padding: 1rem 1.5rem; }
  .hero-left { padding: 120px 1.5rem 3rem; }
  .features, .how, .pricing-teaser, .cta-strip { padding: 4rem 1.5rem; }
  .footer { padding: 1.5rem; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <a class="nav-logo" href="/">Nutro<em>App</em></a>
  <div class="nav-links">
    <a href="/pricing.php">გეგმები</a>
    <a href="/corporate.php">B2B</a>
    <a href="/login.php">შესვლა</a>
    <a href="/register.php" class="nav-cta">რეგისტრაცია</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-left">
    <div class="hero-tag reveal">პერსონალურად თქვენთვის გენერირებული კვება</div>
    <h1 class="hero-title reveal">
      იკვებე <em>სწორად,</em><br>
      იცხოვრე<br>
      ჯანსაღად
    </h1>
    <p class="hero-sub reveal">
      პერსონალური კვების გეგმა ქართული ბაზრის პროდუქტებით.
      თქვენი პირადი ასისტენტი ითვლის კალორიებს, ადარებს ფასებს და გთავაზობთ საუკეთესოს!
    </p>
    <div class="hero-actions reveal">
      <a href="/register.php" class="btn-main">
        ცადე უფასოდ
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
      <a href="/pricing.php" class="btn-ghost">პაკეტების ნახვა</a>
    </div>
    <div class="hero-stats reveal">
      <div>
        <div class="stat-num">10+</div>
        <div class="stat-lbl">მაღაზიათა ქსელი</div>
      </div>
      <div>
        <div class="stat-num">100+</div>
        <div class="stat-lbl">პროდუქტი</div>
      </div>
      <div>
        <div class="stat-num">პერსონალური</div>
        <div class="stat-lbl">გენერაცია</div>
      </div>
    </div>
  </div>

  <div class="hero-right">
    <div class="hero-visual">
      <div class="price-badge">Carrefour — 10.90 ₾ / კგ</div>
      <div class="float-card">
        <div class="card-header">
          <span class="card-title-sm">დღის გეგმა</span>
          <span class="card-badge">ინდივიდუალური ✓</span>
        </div>
        <div class="meal-row">
          <div class="meal-dot" style="background:#5DCAA5;"></div>
          <div class="meal-info">
            <div class="meal-name">შემწვარი კვერცხი</div>
            <div class="meal-ing">კვერცხი, პომიდორი, ხახვი</div>
          </div>
          <div class="meal-kcal">380 კკ</div>
        </div>
        <div class="meal-row">
          <div class="meal-dot" style="background:#EF9F27;"></div>
          <div class="meal-info">
            <div class="meal-name">ქათამი ბრინჯით</div>
            <div class="meal-ing">ქათამი, ბრინჯი, კიტრი</div>
          </div>
          <div class="meal-kcal">480 კკ</div>
        </div>
        <div class="meal-row">
          <div class="meal-dot" style="background:#7F77DD;"></div>
          <div class="meal-info">
            <div class="meal-name">მაწონი ნიგვზით</div>
            <div class="meal-ing">მაწონი, ნიგოზი</div>
          </div>
          <div class="meal-kcal">250 კკ</div>
        </div>
        <div class="card-total">
          <span class="card-total-lbl">სულ დღეში</span>
          <span class="card-total-val">1,110 კკალ</span>
        </div>
      </div>
      <div class="store-badge">
        <div class="store-dot"></div>
        <div class="store-text">Goodwill — იაფი ამ გეგმისთვის</div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features">
  <div class="section-eyebrow reveal">რატომ NutroApp</div>
  <h2 class="section-title reveal">კვების სრული <em>ეკოსისტემა</em> შენთვის</h2>
  <div class="feat-grid">
    <div class="feat-item reveal">
      <div class="feat-num">01</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="#1D9E75" stroke-width="1.5"/><path d="M10 7v3l2 2" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round"/></svg>
      </div>
      <div class="feat-title">პერსონალური კვების გეგმა</div>
      <div class="feat-desc">თქვენი ასისტენტი ქმნის პერსონალურ კვების გეგმას მიზნის, ასაკისა და აქტიურობის მიხედვით.</div>
    </div>
    <div class="feat-item reveal">
      <div class="feat-num">02</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><path d="M3 10h14M3 6h14M3 14h8" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round"/></svg>
      </div>
      <div class="feat-title">ფასების შედარება</div>
      <div class="feat-desc">Agrohub, 2Nabiji, Carrefour, Goodwill, Spar — ვადარებთ ფასებს და გირჩევთ, სად იყიდოთ.</div>
    </div>
    <div class="feat-item reveal">
      <div class="feat-num">03</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><path d="M10 3v14M3 10h14" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round"/></svg>
      </div>
      <div class="feat-title">კალორიების დათვლა</div>
      <div class="feat-desc">ავტომატური TDEE. პროტეინი, ნახშირწყლები, ცხიმი — ყველა მაკრო ელემენტი სწორად არის გათვლილი.</div>
    </div>
    <div class="feat-item reveal">
      <div class="feat-num">04</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="14" height="14" rx="3" stroke="#1D9E75" stroke-width="1.5"/><path d="M7 10l2 2 4-4" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div class="feat-title">ქართული პროდუქტები</div>
      <div class="feat-desc">მხოლოდ ადგილობრივად ხელმისაწვდომი საკვები</div>
    </div>
    <div class="feat-item reveal">
      <div class="feat-num">05</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><path d="M10 17l-7-7 7-7" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M17 10H3" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round"/></svg>
      </div>
      <div class="feat-title">გეგმების ისტორია</div>
      <div class="feat-desc">ყველა შენი კვების გეგმა ინახება. ნახე და შეადარე პროგრესი.</div>
    </div>
    <div class="feat-item reveal">
      <div class="feat-num">06</div>
      <div class="feat-icon">
        <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="8" r="3" stroke="#1D9E75" stroke-width="1.5"/><path d="M4 17c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="#1D9E75" stroke-width="1.5" stroke-linecap="round"/></svg>
      </div>
      <div class="feat-title">პერსონალიზაცია</div>
      <div class="feat-desc">ალერგია? დიეტური შეზღუდვა? ბიუჯეტი? ასისტენტი ყველაფერს ითვალისწინებს.</div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="how">
  <div class="section-eyebrow reveal">როგორ მუშაობს</div>
  <h2 class="section-title reveal">4 ნაბიჯი <em>უკეთეს</em> დღემდე</h2>
  <div class="steps">
    <div class="step reveal">
      <div class="step-num">01</div>
      <div class="step-arrow">→</div>
      <div class="step-title">დარეგისტრირდით</div>
      <div class="step-desc">შექმენით ანგარიში, შეავსეთ პროფილი — ასაკი, წონა, სიმაღლე, მიზანი.</div>
    </div>
    <div class="step reveal">
      <div class="step-num">02</div>
      <div class="step-arrow">→</div>
      <div class="step-title">აირჩიეთ გეგმა</div>
      <div class="step-desc">საბაზო, სტანდარტი ან პრემიუმი — თქვენი ბიუჯეტის მიხედვით.</div>
    </div>
    <div class="step reveal">
      <div class="step-num">03</div>
      <div class="step-arrow">→</div>
      <div class="step-title">გენერაცია</div>
      <div class="step-desc">ღილაკის დაჭერით პირადი ასისტენტი ქმნის სრულ კვების გეგმას რამდენიმე წუთში.</div>
    </div>
    <div class="step reveal">
      <div class="step-num">04</div>
      <div class="step-title">იშოპინგეთ ჭკვიანურად</div>
      <div class="step-desc">გირჩევთ რომელ მაღაზიაში იყიდოთ თითოეული პროდუქტი ყველაზე იაფად.</div>
    </div>
  </div>
</section>

<!-- PRICING TEASER -->
<section class="pricing-teaser">
  <div class="section-eyebrow reveal">გამოწერა</div>
  <h2 class="section-title reveal">მარტივი, <em>გამჭვირვალე</em> ფასები</h2>
  <div class="plan-row">
    <div class="plan-card reveal">
      <div class="plan-name">Low Cost</div>
      <div class="plan-ka">საბაზო</div>
      <div class="plan-price-big">19.99<span> ₾/თვე</span></div>
      <div class="plan-feature">5 გეგმა თვეში</div>
      <div class="plan-feature">მაქს. 5 დღე</div>
      <div class="plan-feature">კალორიების გათვლა</div>
      <div class="plan-feature">ლიდერბორდი და ჩელენჯები!</div>
      <a href="/register.php" class="plan-btn">დაიწყე</a>
    </div>
    <div class="plan-card featured reveal">
      <div class="plan-name">Medium</div>
      <div class="plan-ka">სტანდარტი</div>
      <div class="plan-price-big">29.99<span> ₾/თვე</span></div>
      <div class="plan-feature">20 გეგმა თვეში</div>
      <div class="plan-feature">მაქს. 7 დღე</div>
      <div class="plan-feature">ფასების შედარება</div>
      <div class="plan-feature">ლიდერბორდი და ჩელენჯები!</div>
      <a href="/register.php" class="plan-btn">ყველაზე პოპულარული</a>
    </div>
    <div class="plan-card reveal">
      <div class="plan-name">High Waltage</div>
      <div class="plan-ka">პრემიუმი</div>
      <div class="plan-price-big">49.99<span> ₾/თვე</span></div>
      <div class="plan-feature">შეუზღუდავი გეგმა</div>
      <div class="plan-feature">AI ფასების განახლება</div>
      <div class="plan-feature">პრიორიტეტული მიდგომა</div>
      <div class="plan-feature">მომხმარებლებთან ჩატი</div>
      <div class="plan-feature">ლიდერბორდი და ჩელენჯები!</div>
      <a href="/register.php" class="plan-btn">პრემიუმი</a>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-strip">
  <div class="cta-text reveal">
    დაიწყეთ<br>ჯანსაღი ცხოვრება დღეს
  </div>
  <a href="/register.php" class="btn-cta-white reveal">
უფასოდ ცდა
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
      <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </a>
</section>

<!-- FOOTER -->
<footer class="footer">
  <a class="footer-logo" href="/">Nutro<em>App</em></a>
  <span>&copy; <?php echo date('Y'); ?> NutroApp — nutroapp.ge</span>
  <div style="display:flex;gap:1.5rem;">
    <a href="/privacy-policy.php" style="color:inherit;text-decoration:none;">კონფიდენციალურობის პოლიტიკა</a>
    <a href="/refund-policy.php" style="color:inherit;text-decoration:none;">თანხის დაბრუნების პოლიტიკა</a>
    <a href="/about.php" style="color:inherit;text-decoration:none;">კომპანიის შესახებ</a>
    <a href="/pricing.php" style="color:inherit;text-decoration:none;">პაკეტები</a>
    <a href="/login.php" style="color:inherit;text-decoration:none;">შესვლა</a>
    <a href="/register.php" style="color:rgba(255,255,255,.6);text-decoration:none;">რეგისტრაცია</a>
  </div>
</footer>

<script>
// Scroll reveal
var reveals = document.querySelectorAll('.reveal');
var observer = new IntersectionObserver(function(entries) {
  entries.forEach(function(entry, i) {
    if (entry.isIntersecting) {
      setTimeout(function() {
        entry.target.classList.add('visible');
      }, (i % 4) * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });
reveals.forEach(function(el) { observer.observe(el); });

// Trigger hero reveals immediately
document.querySelectorAll('.hero .reveal').forEach(function(el, i) {
  setTimeout(function() { el.classList.add('visible'); }, 100 + i * 120);
});
</script>
</body>
</html>