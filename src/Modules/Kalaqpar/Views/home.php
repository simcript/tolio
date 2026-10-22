<?php $this->start('content') ?>
<main id="kalaqpar" class="kq" data-base="<?= htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8') ?>" data-error="<?= htmlspecialchars($initial_error, ENT_QUOTES, 'UTF-8') ?>" data-invitation="<?= htmlspecialchars($invitation, ENT_QUOTES, 'UTF-8') ?>">
    <header class="kq-header">
        <button id="kq-back" class="kq-icon" aria-label="بازگشت به خانه" hidden>›</button>
        <div><h1 id="kq-title">کلاغ‌پر</h1><p id="kq-subtitle">ببین، تصمیم بگیر، امتیاز بگیر!</p></div>
        <span class="kq-brand" aria-hidden="true">↑</span>
    </header>
    <div id="kq-connection" role="status" class="kq-note kq-yellow" hidden></div>
    <div id="kq-worker" role="status" class="kq-note kq-yellow" hidden>سرویس بازی موقتاً در دسترس نیست. پاسخ‌های ثبت‌شده محفوظ هستند؛ در حال اتصال مجدد…</div>
    <div id="kq-message" role="status" aria-live="polite" hidden></div>
    <section id="kq-content" aria-label="بازی کلاغ‌پر"><div class="kq-card kq-center"><p><?= $initial_error !== '' ? htmlspecialchars($initial_error, ENT_QUOTES, 'UTF-8') : 'در حال آماده‌سازی بازی…' ?></p></div></section>
    <nav id="kq-nav" aria-label="ناوبری اصلی">
        <button data-page="home"><span aria-hidden="true">⌂</span>خانه</button>
        <button data-page="leaders"><span aria-hidden="true">♜</span>رتبه‌بندی</button>
        <button data-page="profile"><span aria-hidden="true">♙</span>پروفایل</button>
    </nav>
    <dialog id="kq-players" aria-labelledby="kq-players-title"><div class="kq-dialog-head"><h2 id="kq-players-title">بازیکن‌های مسابقه</h2><button class="kq-icon" id="kq-close-players" aria-label="بستن فهرست">×</button></div><p>رتبه‌ها پس از پایان هر دور به‌روز می‌شوند.</p><div id="kq-player-rows"></div></dialog>
    <noscript><div class="kq-note kq-yellow">برای بازی، جاوااسکریپت مرورگرت را فعال کن.</div></noscript>
</main>
<?php $this->end() ?>
