<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

http_response_code(404);
renderHeader('გვერდი ვერ მოიძებნა', '');
?>

<style>
.notfound-wrap {
    text-align: center;
    padding: 4rem 1rem 3rem;
    max-width: 480px;
    margin: 0 auto;
}
.notfound-code {
    font-family: 'DM Serif Display', serif;
    font-size: 96px;
    font-weight: 400;
    line-height: 1;
    color: #E8E6DF;
    margin-bottom: .5rem;
    font-style: italic;
}
.notfound-code span { color: #1D9E75; }
.notfound-title {
    font-size: 22px;
    font-weight: 500;
    color: var(--gray-900);
    margin-bottom: .75rem;
}
.notfound-sub {
    font-size: 15px;
    color: var(--gray-400);
    margin-bottom: 2rem;
    line-height: 1.6;
}
.notfound-links {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}
</style>

<div class="notfound-wrap">
    <div class="notfound-code">4<span>0</span>4</div>
    <div class="notfound-title">გვერდი ვერ მოიძებნა</div>
    <div class="notfound-sub">
        მოთხოვნილი გვერდი არ არსებობს ან გადატანილია.<br>
        შეამოწმეთ მისამართი ან დაბრუნდით მთავარ გვერდზე.
    </div>
    <div class="notfound-links">
        <?php if (isLoggedIn()): ?>
            <a href="/dashboard.php" class="btn btn-primary" style="padding:11px 24px;">
                მთავარი გვერდი
            </a>
            <a href="/generate.php" class="btn btn-outline" style="padding:11px 24px;">
                ახალი გეგმა
            </a>
        <?php else: ?>
            <a href="/index.php" class="btn btn-primary" style="padding:11px 24px;">
                მთავარი გვერდი
            </a>
            <a href="/login.php" class="btn btn-outline" style="padding:11px 24px;">
                შესვლა
            </a>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
