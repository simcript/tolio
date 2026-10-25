<?php use Src\Modules\Kalaqpar\Services\{PublicUrl, Pwa}; ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f8f5ed">
    <meta name="apple-mobile-web-app-title" content="کلاغ‌پر">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="referrer" content="no-referrer">
    <meta name="kq-bootstrap" content="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
    <title>کلاغ‌پر | با هم بیشتر خوش می‌گذره</title>
    <link rel="manifest" href="<?= htmlspecialchars(PublicUrl::local(url('manifest.webmanifest', 'kalaqpar.tolio.ir')), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(Pwa::asset('apple-touch-icon.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="<?= htmlspecialchars(PublicUrl::local(url('assets/hero.svg', 'kalaqpar.tolio.ir')), ENT_QUOTES, 'UTF-8') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= htmlspecialchars(Pwa::asset('bootstrap.rtl.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Pwa::asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(Pwa::asset('app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(Pwa::asset('pwa.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="kq-body">
<?php $this->section('content') ?>
</body>
</html>
