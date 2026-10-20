<!DOCTYPE html>
<html lang="fa" dir="rtl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?? 'کلاغ‌پر | Tolio Tools' ?></title>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐦‍⬛</text></svg>">

    <!-- Bootstrap is reused from the host installation (5.3 ships logical
         properties, so dir=rtl works without a second copy). -->
    <link href="/apps/idate/assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/apps/kalaqpar/assets/style.css">
</head>
<body class="klq-app">

<?php $this->section('content') ?>

<script src="/apps/idate/assets/bootstrap.bundle.min.js"></script>
<script src="/apps/kalaqpar/assets/script.js"></script>
</body>
</html>
