<?php use Src\Modules\Kalaqpar\Services\PublicUrl; ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f8f5ed">
<title>کلاغ‌پر | اتصال اینترنت</title>
<link rel="stylesheet" href="<?= htmlspecialchars(\Src\Modules\Kalaqpar\Services\Pwa::asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="kq-body"><main class="kq"><section class="kq-card">
<h1>اتصال به کلاغ‌پر برقرار نشد</h1>
<p>مسابقهٔ چندنفره به اینترنت نیاز دارد؛ بازی آفلاین پشتیبانی نمی‌شود. پاسخ‌ها در حالت آفلاین ذخیره یا بعداً ارسال نمی‌شوند. زمان و امتیاز معتبر را سرور تعیین می‌کند.</p>
<p>پس از اتصال، دوباره تلاش کن تا وضعیت فعلی از سرور دریافت شود. باز کردن برنامه یا دعوت، تو را خودکار به اتاقی وارد نمی‌کند.</p>
<a class="kq-button kq-retry" href="">تلاش دوباره</a>
<a href="<?= htmlspecialchars(PublicUrl::local(url('', 'kalaqpar.tolio.ir')), ENT_QUOTES, 'UTF-8') ?>">رفتن به خانه</a>
</section></main></body></html>
