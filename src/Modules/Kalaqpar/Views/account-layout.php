<?php use Src\Modules\Kalaqpar\Services\Pwa; ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <title>حساب کاربری | کلاغ‌پر</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(Pwa::asset('bootstrap.rtl.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Pwa::asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(Pwa::asset('account.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="kq-body">
<?php $this->section('content') ?>
</body>
</html>
